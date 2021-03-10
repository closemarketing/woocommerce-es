<?php

/**
 * Class for admin.
 *
 * Description.
 *
 * @since 1.0
 */
class WCES_Admin {

    /**
     * Construct of Class
     */
    public function __construct() {
        // add the action 
        add_action ( 'admin_notices' , array( $this, 'action_admin_notices' ) );
        
    }

    /**
     *  define the admin_notices callback
     * */
    function action_admin_notices() {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e( '<img width="20" height="20" src="https://www.flaticon.es/svg/vstatic/svg/3522/3522675.svg?token=exp=1615373953~hmac=0224b5ab4e39ec52539818bed35036df"></img><img width="20" height="20" src="https://www.flaticon.es/svg/vstatic/svg/3522/3522283.svg?token=exp=1615374581~hmac=e5e7152686ffb169efdbc0cca4d23817"></img>
            ¡Subscribe to WooCommerce!<button type="submit" name="btnSub" id="btnSub" onclick=\'alert("Hello")\'>¡Subscribe!</button>', 'sample-text-domain' ); ?></p>
        </div>
        <?php
        
    }

    /**
     * Change variable so that it does not appear again
     */
    function change_dismiss()
    {
        $dismiss=true;
    }
}

new WCES_Admin;