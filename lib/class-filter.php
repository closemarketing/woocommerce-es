<?php 

/**
 * Class for Woocommerce
 */
class WooFilterShipping {
      /**
       * Class variables
      **/

      ////////////////////////////////////////////////////////////
      
      /**
       * Construct of class
       */
      public function __construct() {
            add_action('restrict_manage_posts', array($this,'wces_filter_post_type_by_taxonomy'));
            add_filter('parse_query', array($this,'wces_convert_id_to_term_in_query'));
      }

      /**
       * Shows dropdown in products
       * @return html dropdown
       */
      function wces_filter_post_type_by_taxonomy() {
            global $typenow;
            $post_type = 'product'; 
            $taxonomy  = 'product_shipping_class';

            if ($typenow == $post_type) {
                  $selected      = isset($_GET[$taxonomy]) ? $_GET[$taxonomy] : '';
                  $info_taxonomy = get_taxonomy($taxonomy);
                  wp_dropdown_categories(array(
                        'show_option_all' => __('Show All Shipping Classes','woocommerce-es'),
                        'show_option_none'   => __('Without Shipping Classes','woocommerce-es'),
                        'taxonomy'        => $taxonomy,
                        'name'            => $taxonomy,
                        'orderby'         => 'name',
                        'selected'        => $selected,
                        'show_count'      => true,
                        'hide_empty'      => false,
                  ));
            };
      }

      /**
       * Query for dropdown filter in products
       * @param  $query Query of actual filter
       * @return $query       modified
       */
      function wces_convert_id_to_term_in_query($query) {
            global $pagenow;
            $post_type = 'product'; 
            $taxonomy  = 'product_shipping_class';
            $q_vars    = &$query->query_vars;

            $terms_shipping = get_terms( 'product_shipping_class', array( 'hide_empty' => false) );
            $all_shipping = array();
            foreach($terms_shipping as $term) {
                  $all_shipping[] = $term->term_id;
            }

            if ( $pagenow == 'edit.php' && isset($q_vars['post_type']) && $q_vars['post_type'] == $post_type && isset($q_vars[$taxonomy]) && is_numeric($q_vars[$taxonomy]) && $q_vars[$taxonomy] != 0 ) {

                  if($q_vars[$taxonomy]==-1) { //no taxonomy
                        unset( $query->query_vars['product_shipping_class'] ); 
                        unset( $query->query['product_shipping_class'] ); 

                        $query->set( 'tax_query', array(
                              'relation' => 'AND',
                              array(
                                    'taxonomy' => 'product_shipping_class',
                                    'field' => 'term_id',
                                    'terms' => $all_shipping,
                                    'operator' => 'NOT IN'
                              )
                            )
                        ); 
                  } else {
                        $term = get_term_by('id', $q_vars[$taxonomy], $taxonomy);
                        $q_vars[$taxonomy] = $term->slug;
                  }
            }
            return $query;
      }

} //from class

global $woo_filter_shipping;

$woo_filter_shipping = new WooFilterShipping();

