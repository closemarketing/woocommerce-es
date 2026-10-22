<?php
/**
 * Tests that the Action Scheduler sync logs are scoped per connector.
 *
 * Command: composer test -- --filter=SyncLogsScopeTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\CRON;

/**
 * Class SyncLogsScopeTest.
 */
class SyncLogsScopeTest extends WP_UnitTestCase {

	/**
	 * Returns the action IDs of a logs result.
	 *
	 * @param string $connector_id Connector ID.
	 * @return array
	 */
	private function log_ids( $connector_id ) {
		$result = CRON::get_sync_logs( $connector_id );
		return array_map( 'intval', array_column( $result['actions'], 'id' ) );
	}

	/**
	 * Each connector only sees its own actions; legacy ones (no args) go to the active connector.
	 *
	 * @return void
	 */
	public function test_logs_are_scoped_to_connector() {
		update_option( 'connect_ecommerce', array( 'connector' => 'holded' ) );

		$holded    = as_schedule_single_action( time() + 60, 'conecom_sync_one_hour', array( 'holded' ) );
		$clientify = as_schedule_single_action( time() + 60, 'conecom_sync_one_hour', array( 'clientify' ) );
		$legacy    = as_schedule_single_action( time() + 60, 'conecom_sync_one_hour', array() );

		$this->assertEqualsCanonicalizing( array( $holded, $legacy ), $this->log_ids( 'holded' ) );
		$this->assertSame( array( $clientify ), $this->log_ids( 'clientify' ) );
		$this->assertContains( $clientify, $this->log_ids( '' ) );
		$this->assertContains( $holded, $this->log_ids( '' ) );
	}
}
