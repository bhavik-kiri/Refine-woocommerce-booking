<?php 
/*
Plugin Name: WooCommerce Booking Plugin
Plugin URI: http://www.tychesoftwares.com/store/premium-plugins/woocommerce-booking-plugin
Description: This plugin lets you capture the Booking Date & Booking Time for each product thereby allowing your WooCommerce store to effectively function as a Booking system. It allows you to add different time slots for different days, set maximum bookings per time slot, set maximum bookings per day, set global & product specific holidays and much more.
Version: 1.7.6
Author: Ashok Rane
Author URI: http://www.tychesoftwares.com/
*/

/*require 'plugin-updates/plugin-update-checker.php';
$ExampleUpdateChecker = new PluginUpdateChecker(
	'http://www.tychesoftwares.com/plugin-updates/woocommerce-booking-plugin/info.json',
	__FILE__
);*/

global $BookUpdateChecker;
$BookUpdateChecker = '1.7.6';

// this is the URL our updater / license checker pings. This should be the URL of the site with EDD installed
define( 'EDD_SL_STORE_URL_BOOK', 'http://www.tychesoftwares.com/' ); // IMPORTANT: change the name of this constant to something unique to prevent conflicts with other plugins using this system

// the name of your product. This is the title of your product in EDD and should match the download title in EDD exactly
define( 'EDD_SL_ITEM_NAME_BOOK', 'Woocommerce Booking & Appointment Plugin' ); // IMPORTANT: change the name of this constant to something unique to prevent conflicts with other plugins using this system

if( !class_exists( 'EDD_BOOK_Plugin_Updater' ) ) {
	// load our custom updater if it doesn't already exist
	include( dirname( __FILE__ ) . '/plugin-updates/EDD_BOOK_Plugin_Updater.php' );
}

// retrieve our license key from the DB
$license_key = trim( get_option( 'edd_sample_license_key' ) );

// setup the updater
$edd_updater = new EDD_BOOK_Plugin_Updater( EDD_SL_STORE_URL_BOOK, __FILE__, array(
		'version' 	=> '1.7.6', 		// current version number
		'license' 	=> $license_key, 	// license key (used get_option above to retrieve from DB)
		'item_name' => EDD_SL_ITEM_NAME_BOOK, 	// name of this plugin
		'author' 	=> 'Ashok Rane'  // author of this plugin
)
);

include_once('lang.php');
include_once('bkap-config.php');
include_once('bkap-core-functions.php');
include_once('availability-search.php');
include_once('block-price-booking.php');
include_once('block-booking.php');
include_once('admin-bookings.php');
register_uninstall_hook( __FILE__, 'woocommerce_booking_delete');

function woocommerce_booking_delete(){
	
	global $wpdb;
	$table_name_booking_history = $wpdb->prefix . "booking_history";
	$sql_table_name_booking_history = "DROP TABLE " . $table_name_booking_history ;
	
	$table_name_order_history = $wpdb->prefix . "booking_order_history";
	$sql_table_name_order_history = "DROP TABLE " . $table_name_order_history ;

	$table_name_booking_block_price = $wpdb->prefix . "booking_block_price_meta";
	$sql_table_name_booking_block_price = "DROP TABLE " . $table_name_booking_block_price ;

	$table_name_booking_block_attribute = $wpdb->prefix . "booking_block_price_attribute_meta";
	$sql_table_name_booking_block_attribute = "DROP TABLE " . $table_name_booking_block_attribute ;

	$table_name_block_booking = $wpdb->prefix . "booking_fixed_blocks";
	$sql_table_name_block_booking = "DROP TABLE " . $table_name_block_booking;

	$table_name_booking_variable_lockout = $wpdb->prefix . "booking_variation_lockout_history";
	$sql_table_name_booking_variable_lockout = "DROP TABLE " . $table_name_booking_variable_lockout;
	
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	$wpdb->get_results($sql_table_name_booking_history);
	$wpdb->get_results($sql_table_name_order_history);
	$wpdb->get_results($sql_table_name_booking_block_price);
	$wpdb->get_results($sql_table_name_booking_block_attribute);
	$wpdb->get_results($sql_table_name_block_booking);
	$wpdb->get_results($sql_table_name_booking_variable_lockout);
	
	$sql_table_post_meta = "DELETE FROM `".$wpdb->prefix."postmeta` WHERE meta_key='woocommerce_booking_settings'";
	$results = $wpdb->get_results ( $sql_table_post_meta );
	
	$sql_table_option = "DELETE FROM `".$wpdb->prefix."options` WHERE option_name='woocommerce_booking_global_settings'";
	$results = $wpdb->get_results ($sql_table_option);
}

//if (is_woocommerce_active())
{
	/**
	 * Localisation
	 **/
	load_plugin_textdomain('woocommerce-booking', false, dirname( plugin_basename( __FILE__ ) ) . '/');

	/**
	 * woocommerce_booking class
	 **/
	if (!class_exists('woocommerce_booking')) {

		class woocommerce_booking {
			
			public function __construct() {
				
				
				//include_once('arrays.php');
				// Initialize settings
				register_activation_hook( __FILE__, array(&$this, 'bookings_activate'));
				add_action( 'plugins_loaded', array(&$this, 'bookings_update_db_check'));
				// Ajax calls
				add_action('init', array(&$this, 'book_load_ajax'));
				// WordPress Administration Menu
				add_action('admin_menu', array(&$this, 'woocommerce_booking_admin_menu'));
				
				// Display Booking Box on Add/Edit Products Page
				add_action('add_meta_boxes', array(&$this, 'booking_box'));
				
				// Processing Bookings
				add_action('woocommerce_process_product_meta', array(&$this, 'process_bookings_box'), 1, 2);
				
				// Scripts
				add_action( 'admin_enqueue_scripts', array(&$this, 'my_enqueue_scripts_css' ));
				add_action( 'admin_enqueue_scripts', array(&$this, 'my_enqueue_scripts_js' ));
				
				//add_action( 'woocommerce_before_main_content', array(&$this, 'front_side_scripts_js'));
				//add_action( 'woocommerce_before_main_content', array(&$this, 'front_side_scripts_css'));
				add_action( 'woocommerce_before_single_product', array(&$this, 'front_side_scripts_js'));
				add_action( 'woocommerce_before_single_product', array(&$this, 'front_side_scripts_css'));
				
				// Display on Products Page
				add_action( 'woocommerce_before_add_to_cart_form', array(&$this, 'before_add_to_cart'));
				add_action( 'woocommerce_before_add_to_cart_button', array(&$this, 'booking_after_add_to_cart'));
				
				// Ajax Calls
			//	require_once( ABSPATH . "wp-includes/pluggable.php" );
				
				add_action('wp_ajax_remove_time_slot', array(&$this, 'remove_time_slot'));
				add_action('wp_ajax_remove_day', array(&$this, 'remove_day'));
				add_action('wp_ajax_remove_specific', array(&$this, 'remove_specific'));
				add_action('wp_ajax_remove_recurring', array(&$this, 'remove_recurring'));
				
				add_filter('woocommerce_add_cart_item_data', array(&$this, 'add_cart_item_data'), 10, 2);
				add_filter('woocommerce_get_cart_item_from_session', array(&$this, 'get_cart_item_from_session'), 10, 2);
				add_filter( 'woocommerce_get_item_data', array(&$this, 'get_item_data'), 10, 2 );
				
				//$show_checkout_date_calendar = 1;
				if (isset($booking_settings['booking_enable_multiple_day']) && $booking_settings['booking_enable_multiple_day'] == 'on')
				{
					add_filter( 'woocommerce_add_cart_item', array(&$this, 'add_cart_item'), 10, 1 );
				}
				add_action( 'woocommerce_checkout_update_order_meta', array(&$this, 'order_item_meta'), 10, 2);
				add_action( 'woocommerce_order_item_meta', array(&$this, 'add_order_item_meta'), 10, 2 );
				add_action('woocommerce_before_checkout_process', array(&$this, 'quantity_check'));
				add_filter( 'woocommerce_add_to_cart_validation', array(&$this, 'validate_add_cart_item'), 10, 3 );
				add_action('woocommerce_order_status_cancelled' , array(&$this,'woocommerce_cancel_order'),10,1);
				add_action('woocommerce_order_status_refunded' , array(&$this,'woocommerce_cancel_order'),10,1);
				add_action('woocommerce_duplicate_product' , array(&$this,'product_duplicate'),10,2);
				add_action('woocommerce_check_cart_items', array(&$this,'quantity_check'));
				
				//Export date to ics file from order received page
				$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
				if (isset($saved_settings->booking_export) && $saved_settings->booking_export == 'on')
				{
					add_filter('woocommerce_order_details_after_order_table', array(&$this, 'export_to_ics'), 10, 3 );
				}
				
				//Add order details as an attachment
				if (isset($saved_settings->booking_attachment) && $saved_settings->booking_attachment == 'on')
				{
					add_filter('woocommerce_email_attachments', array(&$this, 'email_attachment'), 10, 3 );
				}
				
				add_action('admin_init', array(&$this, 'edd_sample_register_option'));
				add_action('admin_init', array(&$this, 'edd_sample_deactivate_license'));
				add_action('admin_init', array(&$this, 'edd_sample_activate_license'));	
				add_filter('woocommerce_my_account_my_orders_actions', array(&$this, 'add_cancel_button'), 10, 3 );
				add_filter('add_to_cart_fragments', array(&$this, 'woo_cart_widget_subtotal'));
			}
			
			/**
			 * Functions
			 */
			function add_cancel_button($order,$action){
					
				//echo home_url().apply_filters('woocommerce_get_cancel_order_url', add_query_arg('', ''));
				//echo "ORDER</pre>";print_r($order);echo "</pre>";
				//echo "ACTION</pre>";print_r($action);echo "</pre>";
			
				$myaccount_page_id = get_option( 'woocommerce_myaccount_page_id' );
				if ( $myaccount_page_id ) {
					$myaccount_page_url = get_permalink( $myaccount_page_id );
				}
			
				if (isset($_GET['order_id']) &&  $_GET['order_id'] == $action->id && $_GET['cancel_order'] == "true")
				{
					$order_obj = new WC_Order( $action->id );
					$order_obj->update_status( "cancelled" );
					print('<script type="text/javascript">
							location.href="'.$myaccount_page_url.'";
							</script>');
				//	wp_redirect($myaccount_page_url,301);
					
				}
			
				//apply_filters('woocommerce_get_cancel_order_url', add_query_arg('order_id', $action->id)."&cancel_order=true");
			
				if ($action->status != "cancelled")
				{
					$order['cancel'] = array(
							"url" => apply_filters('woocommerce_get_cancel_order_url', add_query_arg('order_id', $action->id)."&cancel_order=true"),//plugins_url("/cancel-order.php", __FILE__ )."?order_id=".$action->id,
							"name" => "Cancel");
				}
				return $order;
			}
			
			function book_load_ajax()
			{
				if ( !is_user_logged_in() )
				{
					add_action('wp_ajax_nopriv_get_per_night_price', array(&$this, 'get_per_night_price'));
					add_action('wp_ajax_nopriv_check_for_time_slot', array(&$this, 'check_for_time_slot'));
					//add_action('wp_ajax_nopriv_check_for_prices', array(&$this, 'check_for_prices'));
					add_action('wp_ajax_nopriv_insert_date', array(&$this, 'insert_date'));
					add_action('wp_ajax_nopriv_call_addon_price', array(&$this, 'call_addon_price'));
					add_action('wp_ajax_nopriv_display_results', array(&$this, 'display_results'));
				}
				else
				{
					add_action('wp_ajax_get_per_night_price', array(&$this, 'get_per_night_price'));
					add_action('wp_ajax_check_for_time_slot', array(&$this, 'check_for_time_slot'));
				//	add_action('wp_ajax_check_for_prices', array(&$this, 'check_for_prices'));
					add_action('wp_ajax_insert_date', array(&$this, 'insert_date'));
					add_action('wp_ajax_call_addon_price', array(&$this, 'call_addon_price'));
					add_action('wp_ajax_display_results', array(&$this, 'display_results'));
				}
			}
			function edd_sample_activate_license() {
					
				// listen for our activate button to be clicked
				if( isset( $_POST['edd_license_activate'] ) ) {
						
					// run a quick security check
					if( ! check_admin_referer( 'edd_sample_nonce', 'edd_sample_nonce' ) )
						return; // get out if we didn't click the Activate button
						
					// retrieve the license from the database
					$license = trim( get_option( 'edd_sample_license_key' ) );
			
						
					// data to send in our API request
					$api_params = array(
							'edd_action'=> 'activate_license',
							'license' 	=> $license,
							'item_name' => urlencode( EDD_SL_ITEM_NAME_BOOK ) // the name of our product in EDD
					);
						
					// Call the custom API.
					$response = wp_remote_get( add_query_arg( $api_params, EDD_SL_STORE_URL_BOOK ), array( 'timeout' => 15, 'sslverify' => false ) );
						
					// make sure the response came back okay
					if ( is_wp_error( $response ) )
						return false;
						
					// decode the license data
					$license_data = json_decode( wp_remote_retrieve_body( $response ) );
						
					// $license_data->license will be either "active" or "inactive"
						
					update_option( 'edd_sample_license_status', $license_data->license );
						
				}
			}
				
				
			/***********************************************
			 * Illustrates how to deactivate a license key.
			* This will descrease the site count
			***********************************************/
				
			function edd_sample_deactivate_license() {
					
				// listen for our activate button to be clicked
				if( isset( $_POST['edd_license_deactivate'] ) ) {
						
					// run a quick security check
					if( ! check_admin_referer( 'edd_sample_nonce', 'edd_sample_nonce' ) )
						return; // get out if we didn't click the Activate button
						
					// retrieve the license from the database
					$license = trim( get_option( 'edd_sample_license_key' ) );
			
						
					// data to send in our API request
					$api_params = array(
							'edd_action'=> 'deactivate_license',
							'license' 	=> $license,
							'item_name' => urlencode( EDD_SL_ITEM_NAME_BOOK ) // the name of our product in EDD
					);
						
					// Call the custom API.
					$response = wp_remote_get( add_query_arg( $api_params, EDD_SL_STORE_URL_BOOK ), array( 'timeout' => 15, 'sslverify' => false ) );
						
					// make sure the response came back okay
					if ( is_wp_error( $response ) )
						return false;
						
					// decode the license data
					$license_data = json_decode( wp_remote_retrieve_body( $response ) );
						
					// $license_data->license will be either "deactivated" or "failed"
					if( $license_data->license == 'deactivated' )
						delete_option( 'edd_sample_license_status' );
						
				}
			}
				
				
				
			/************************************
			 * this illustrates how to check if
			* a license key is still valid
			* the updater does this for you,
			* so this is only needed if you
			* want to do something custom
			*************************************/
				
			function edd_sample_check_license() {
					
				global $wp_version;
					
				$license = trim( get_option( 'edd_sample_license_key' ) );
					
				$api_params = array(
						'edd_action' => 'check_license',
						'license' => $license,
						'item_name' => urlencode( EDD_SL_ITEM_NAME_BOOK )
				);
					
				// Call the custom API.
				$response = wp_remote_get( add_query_arg( $api_params, EDD_SL_STORE_URL_BOOK ), array( 'timeout' => 15, 'sslverify' => false ) );
					
					
				if ( is_wp_error( $response ) )
					return false;
					
				$license_data = json_decode( wp_remote_retrieve_body( $response ) );
					
				if( $license_data->license == 'valid' ) {
					echo 'valid'; exit;
					// this license is still valid
				} else {
					echo 'invalid'; exit;
					// this license is no longer valid
				}
			}
				
			function edd_sample_register_option() {
				// creates our settings in the options table
				register_setting('edd_sample_license', 'edd_sample_license_key', array(&$this, 'edd_sanitize_license' ));
			}
				
				
			function edd_sanitize_license( $new ) {
				$old = get_option( 'edd_sample_license_key' );
				if( $old && $old != $new ) {
					delete_option( 'edd_sample_license_status' ); // new license has been entered, so must reactivate
				}
				return $new;
			}
				
			function edd_sample_license_page() {
				$license 	= get_option( 'edd_sample_license_key' );
				$status 	= get_option( 'edd_sample_license_status' );
			
				?>
				<div class="wrap">
					<h2><?php _e('Plugin License Options'); ?></h2>
					<form method="post" action="options.php">
					
						<?php settings_fields('edd_sample_license'); ?>
						
						<table class="form-table">
							<tbody>
								<tr valign="top">	
									<th scope="row" valign="top">
										<?php _e('License Key'); ?>
									</th>
									<td>
										<input id="edd_sample_license_key" name="edd_sample_license_key" type="text" class="regular-text" value="<?php esc_attr_e( $license ); ?>" />
										<label class="description" for="edd_sample_license_key"><?php _e('Enter your license key'); ?></label>
									</td>
								</tr>
								<?php if( false !== $license ) { ?>
									<tr valign="top">	
										<th scope="row" valign="top">
											<?php _e('Activate License'); ?>
										</th>
										<td>
											<?php if( $status !== false && $status == 'valid' ) { ?>
												<span style="color:green;"><?php _e('active'); ?></span>
												<?php wp_nonce_field( 'edd_sample_nonce', 'edd_sample_nonce' ); ?>
												<input type="submit" class="button-secondary" name="edd_license_deactivate" value="<?php _e('Deactivate License'); ?>"/>
											<?php } else {
												wp_nonce_field( 'edd_sample_nonce', 'edd_sample_nonce' ); ?>
												<input type="submit" class="button-secondary" name="edd_license_activate" value="<?php _e('Activate License'); ?>"/>
											<?php } ?>
										</td>
									</tr>
								<?php } ?>
							</tbody>
						</table>	
						<?php submit_button(); ?>
					
					</form>
				<?php
			}


			function export_to_ics($order){
			
				global $woocommerce,$wpdb;
			
				$order_obj = new WC_Order( $order->id );
			
				$order_items = $order_obj->get_items();
				//echo "order<pre>";print_r($order_items);echo "</pre>";
				$today_query = "SELECT * FROM `".$wpdb->prefix."booking_history` AS a1,`".$wpdb->prefix."booking_order_history` AS a2 WHERE a1.id = a2.booking_id AND a2.order_id = '".$order->id."'";
				$results_date = $wpdb->get_results ( $today_query );
				?>
					
						<?php 
							$c = 0;
							if($results_date)
							{
								foreach ($order_items as $item_key => $item_value)
								{
									$duplicate_of = get_post_meta($item_value['product_id'], '_icl_lang_duplicate_of', true);
									if($duplicate_of == '' && $duplicate_of == null)
									{
										$post_time = get_post($item_value['product_id']);
										$id_query = "SELECT ID FROM `".$wpdb->prefix."posts` WHERE post_date = '".$post_time->post_date."' ORDER BY ID LIMIT 1";
										$results_post_id = $wpdb->get_results ( $id_query );
										if( isset($results_post_id) ) {
											$duplicate_of = $results_post_id[0]->ID;
										}
										else
										{
											$duplicate_of = $item_value['product_id'];
										}
										//$duplicate_of = $item_value['product_id'];
									}
										
									$booking_settings = get_post_meta($item_value['product_id'], 'woocommerce_booking_settings', true);
									
									if (isset($booking_settings['booking_enable_date']) && $booking_settings['booking_enable_date'] == 'on' && isset($booking_settings['booking_enable_multiple_day']) && $booking_settings['booking_enable_multiple_day'] == '')
									{
										$book_global_settings = json_decode(get_option('woocommerce_booking_global_settings'));
									
										//$dt = new DateTime($item_value['Check-in Date']);
										//foreach ( $results_date as $date_key => $date_value )
										for ( $c = 0; $c < count($results_date); $c++ )
										{
											if ( $results_date[$c]->post_id == $duplicate_of )
											{
												//$dt = new DateTime($date_value->start_date);
												$dt = new DateTime($results_date[$c]->start_date);
												$time = 0;
												
												$time_start = 0;
												$time_end = 0;
												if (isset($booking_settings['booking_enable_time']) && $booking_settings['booking_enable_time'] == 'on')
												{
												//$time = explode(' - ', $item_value['Booking Time']);
												
													$time_start = explode(':', $results_date[$c]->from_time);
													$time_end = explode(':', $results_date[$c]->to_time);
												}
											
											//if (isset($time_start[1]))
											{
												$start_timestamp = strtotime($dt->format('Y-m-d')) + $time_start[0]*60*60 + $time_start[1]*60 + (time() - current_time('timestamp'));
											}
											
											if (isset($time_end[1]))
											{
												$end_timestamp = strtotime($dt->format('Y-m-d')) + $time_end[0]*60*60 + $time_end[1]*60 + (time() - current_time('timestamp'));
											}
											else
											{
												$end_timestamp = '';
											}
											//$c++;
									?>
								
								<form method="post" action="<?php echo plugins_url("/export-ics.php", __FILE__ );?>" id="export_to_ics">
									<input type="hidden" id="book_date_start" name="book_date_start" value="<?php echo $start_timestamp; ?>" />
									<input type="hidden" id="book_date_end" name="book_date_end" value="<?php echo $end_timestamp; ?>" />
									
									<!-- <input type="hidden" id="key_no_<?php echo $date_key; ?>" name="key_no_<?php echo $date_key; ?>" value="<?php echo $date_key; ?>" /> -->
									
									<input type="hidden" id="current_time" name="current_time" value="<?php echo current_time('timestamp'); ?>" />
									<input type="hidden" id="book_name" name="book_name" value="<?php echo $item_value['name']; ?>" />
									
									<input type="submit" id="exp_ics" name="exp_ics" value="<?php _e( 'Add to Calendar', 'woocommerce-booking' ); ?>" /> (<?php echo $item_value['name']; ?>)
									
								</form>
						<?php 
										}
									}
								}
								elseif (isset($booking_settings['booking_enable_date']) && $booking_settings['booking_enable_date'] == 'on' && isset($booking_settings['booking_enable_multiple_day']) && $booking_settings['booking_enable_multiple_day'] == 'on')
								{
									$book_global_settings = json_decode(get_option('woocommerce_booking_global_settings'));
										
									//$dt_start = new DateTime($item_value['Check-in Date']);
									//$dt_end = new DateTime($item_value['Check-out Date']);
										
									//foreach ( $results_date as $date_key => $date_value )
									for ( $c = 0; $c < count($results_date); $c++ )
									{
										if ( $results_date[$c]->post_id == $duplicate_of )
										{
											$dt_start = new DateTime($results_date[$c]->start_date);
											$dt_end = new DateTime($results_date[$c]->end_date);
											
											$start_timestamp = strtotime($dt_start->format('Y-m-d'));
											$end_timestamp = strtotime($dt_end->format('Y-m-d'));
								
											//$c++;
									?>
									<form method="post" action="<?php echo plugins_url("/export-ics.php", __FILE__ );?>" id="export_to_ics">
									
									<input type="hidden" id="book_date_start" name="book_date_start" value="<?php echo $start_timestamp; ?>" />
									<input type="hidden" id="book_date_end" name="book_date_end" value="<?php echo $end_timestamp; ?>" />
									<input type="hidden" id="current_time" name="current_time" value="<?php echo current_time('timestamp'); ?>" />
									<input type="hidden" id="book_name" name="book_name" value="<?php echo $item_value['name']; ?>" />
									
									<input type="submit" id="exp_ics" name="exp_csv" value="<?php _e( 'Add to Calendar', 'woocommerce-booking' ); ?>" /> (<?php echo $item_value['name']; ?>)
						
									</form>
									<?php 
										}
									}
								}
							}
							}
						?>
								
							<?php
							
						}
			
			function email_attachment ( $other, $order_id, $order ) {
			
				global $wpdb;
				
				$order_obj = new WC_Order( $order->id );
				
				$order_items = $order_obj->get_items();
				
				$random_hash = md5(date('r', time()));
				
				$today_query = "SELECT * FROM `".$wpdb->prefix."booking_history` AS a1,`".$wpdb->prefix."booking_order_history` AS a2 WHERE a1.id = a2.booking_id AND a2.order_id = '".$order->id."'";
				$results_date = $wpdb->get_results ( $today_query );
				
				$file = array();
				$c = 0;
				foreach ($order_items as $item_key => $item_value)
				{
					$duplicate_of = get_post_meta($item_value['product_id'], '_icl_lang_duplicate_of', true);
					if($duplicate_of == '' && $duplicate_of == null)
					{
						$post_time = get_post($item_value['product_id']);
						$id_query = "SELECT ID FROM `".$wpdb->prefix."posts` WHERE post_date = '".$post_time->post_date."' ORDER BY ID LIMIT 1";
						$results_post_id = $wpdb->get_results ( $id_query );
						if( isset($results_post_id) ) {
							$duplicate_of = $results_post_id[0]->ID;
						}
						else
						{
							$duplicate_of = $item_value['product_id'];
						}
						//$duplicate_of = $item_value['product_id'];
					}
					$booking_settings = get_post_meta($item_value['product_id'], 'woocommerce_booking_settings', true);
				
					if ((isset($booking_settings['booking_enable_date']) && $booking_settings['booking_enable_date'] == 'on') && (isset($booking_settings['booking_enable_multiple_day']) && $booking_settings['booking_enable_multiple_day'] == ''))
					{
						$book_global_settings = json_decode(get_option('woocommerce_booking_global_settings'));

						//foreach ( $results_date as $date_key => $date_value )
						for ( $c = 0; $c < count($results_date); $c++ )
						{
							if ( $results_date[$c]->post_id == $duplicate_of )
							{
								$dt = new DateTime($results_date[$c]->start_date);
								//$dt = new DateTime($date_value->start_date);
								//$dt = new DateTime($item_value['Check-in Date']);
							
								$time = 0;
								$time_start = 0;
								$time_end = 0;
								
								if ($booking_settings['booking_enable_time'] == 'on')
								{
									//$time = explode(' - ', $item_value['Booking Time']);
										
									$time_start = explode(':', $results_date[$c]->from_time);
									$time_end = explode(':', $results_date[$c]->to_time);
								}
									
								$start_timestamp = strtotime($dt->format('Y-m-d')) + $time_start[0]*60*60 + $time_start[1]*60 + (time() - current_time('timestamp'));
								if(isset($time_end[1]))
								{
									$end_timestamp = strtotime($dt->format('Y-m-d')) + $time_end[0]*60*60 + $time_end[1]*60 + (time() - current_time('timestamp'));
								}
								else 
								{
									$end_timestamp = 0;
								}
						
								$icsString = "
BEGIN:VCALENDAR
PRODID:-//Events Calendar//iCal4j 1.0//EN
VERSION:2.0
CALSCALE:GREGORIAN
BEGIN:VEVENT
DTSTART:".date('Ymd\THis\Z',$start_timestamp)."
DTEND:".date('Ymd\THis\Z',$end_timestamp)."
DTSTAMP:".date('Ymd\THis\Z',current_time('timestamp'))."
UID:".(uniqid())."
DESCRIPTION:".$item_value['name']."
SUMMARY:".$item_value['name']."
END:VEVENT
END:VCALENDAR";
				
								$file[$c] = 'MyCal_'.$c.'.ics';
								
								// Append a new person to the file								
								$current = $icsString;
								
								// Write the contents back to the file
								file_put_contents($file[$c], $current);
								
								//$c++;
							}
						}
					}
					elseif ((isset($booking_settings['booking_enable_date']) && $booking_settings['booking_enable_date'] == 'on') && (isset($booking_settings['booking_enable_multiple_day']) && $booking_settings['booking_enable_multiple_day'] == 'on'))
					{
						$book_global_settings = json_decode(get_option('woocommerce_booking_global_settings'));
						
						//foreach ( $results_date as $date_key => $date_value )
						for ( $c = 0; $c < count($results_date); $c++ )
						{
							if ( $results_date[$c]->post_id == $duplicate_of )
							{
								$dt_start = new DateTime($results_date[$c]->start_date);
								$dt_end = new DateTime($results_date[$c]->end_date);
					
								$start_timestamp = strtotime($dt_start->format('Y-m-d'));
								$end_timestamp = strtotime($dt_end->format('Y-m-d'));
		
								$icsString = "
BEGIN:VCALENDAR
PRODID:-//Events Calendar//iCal4j 1.0//EN
VERSION:2.0
CALSCALE:GREGORIAN
BEGIN:VEVENT
DTSTART:".date('Ymd\THis\Z',$start_timestamp)."
DTEND:".date('Ymd\THis\Z',$end_timestamp)."
DTSTAMP:".date('Ymd\THis\Z',current_time('timestamp'))."
UID:".(uniqid())."
DESCRIPTION:".$item_value['name']."
SUMMARY:".$item_value['name']."
END:VEVENT
END:VCALENDAR";
						
								$file[$c] = 'MyCal_'.$c.'.ics';
								
								// Append a new person to the file
								$current = $icsString;
								
								// Write the contents back to the file
								file_put_contents($file[$c], $current);
							}
						}
					}
					
				}
				
				return $file;
			}
			function product_duplicate($new_id, $post)
			{
				global $wpdb;
				$old_id = $post->ID;
				$duplicate_query = "SELECT * FROM `".$wpdb->prefix."booking_history` WHERE post_id = ".$old_id."";
				$results_date = $wpdb->get_results ( $duplicate_query );
				foreach($results_date as $key => $value)
				{
					$query_insert = "INSERT INTO `".$wpdb->prefix."booking_history`
					(post_id,weekday,start_date,end_date,from_time,to_time,total_booking,available_booking)
					VALUES (
					'".$new_id."',
					'".$value->weekday."',
					'".$value->start_date."',
					'".$value->end_date."',
					'".$value->from_time."',
					'".$value->to_time."',
					'".$value->total_booking."',
					'".$value->total_booking."' )";
					$wpdb->query( $query_insert );
				}
			}
			
			function woo_cart_widget_subtotal( $fragments )
			{
				global $woocommerce;
					
				$price = 0;
				foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $values )
				{
					if (isset($values['booking'])) $booking = $values['booking'];
					if (isset($booking[0]['price']) && $booking[0]['price'] != '') $price += ($booking[0]['price']) * $values['quantity'];
					else
					{
						if ($values['variation_id'] == '') $product_type = $values['data']->product_type;
						else $product_type = $values['data']->parent->product_type;
					
						if ($product_type == 'variable')
						{
							$sale_price = get_post_meta( $values['variation_id'], '_sale_price', true);
							if($sale_price == '')
							{
								$regular_price = get_post_meta( $values['variation_id'], '_regular_price',true);
								$price += $regular_price * $values['quantity'];
							}
							else
							{
								$price += $sale_price * $values['quantity'];
							}
						}
						elseif($product_type == 'simple')
						{
							$sale_price = get_post_meta( $values['product_id'], '_sale_price', true);
			
							if(!isset($sale_price) || $sale_price == '' || $sale_price == 0)
							{
								$regular_price = get_post_meta($values['product_id'], '_regular_price',true);
			
								$price += $regular_price * $values['quantity'];
							}
							else
							{
								$price += $sale_price * $values['quantity'];
							}
						}
					}
				}
			
				$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
				if (isset($saved_settings->enable_rounding) && $saved_settings->enable_rounding == "on")
					$total_price = round($price);
				else $total_price = number_format($price,2);
				
				ob_start();
				$currency_symbol = get_woocommerce_currency_symbol();
				print('<p class="total"><strong>Subtotal:</strong> <span class="amount">'.$currency_symbol.$total_price.'</span></p>');
					
				$fragments['p.total'] = ob_get_clean();
					
				return $fragments;
			}
			
			function validate_add_cart_item($passed,$product_id,$qty)
			{
				$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
				if ($booking_settings != '' && (isset($booking_settings['booking_enable_date']) && $booking_settings['booking_enable_date'] == 'on') /*&& (isset($booking_settings['booking_purchase_without_date']) && $booking_settings['booking_purchase_without_date'] != 'on')*/)
				{
					if(isset($booking_settings['booking_purchase_without_date']) && $booking_settings['booking_purchase_without_date'] == 'on')
					{
						if(isset($_POST['wapbk_hidden_date']) && $_POST['wapbk_hidden_date'] != "") 
						{
							$quantity = $this->quantity($product_id);
							if ($quantity == 'true') $passed = true;
							else $passed = false;
						}
						else $passed = true;
					}
					else
					{
						if(isset($_POST['wapbk_hidden_date']) && $_POST['wapbk_hidden_date'] != "")
						{
							$quantity = $this->quantity($product_id);
							if ($quantity == 'true') $passed = true;
							else $passed = false;
						}
						else $passed = false;
					}
					//echo $passed;exit;
				}
				else
					$passed = true;
				
				return $passed;
			}
			
			function quantity($post_id)
			{
				global $wpdb,$woocommerce;
				$booking_settings = get_post_meta($post_id , 'woocommerce_booking_settings', true);
				$post_title = get_post($post_id);
				$date_check = date('Y-m-d', strtotime($_POST['wapbk_hidden_date']));
					
				$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
				if (isset($saved_settings))	$time_format = $saved_settings->booking_time_format;
				else $time_format = "12";
				$quantity_check_pass = 'true';
				if(isset($_POST['variation_id']))
				{
					$variation_id = $_POST['variation_id'];
				}
				else
				{
					$variation_id = '';
				}	
				if($booking_settings['booking_enable_time'] == 'on')
				{
					$type_of_slot = apply_filters('bkap_slot_type',$post_id);
					if($type_of_slot == 'multiple')
					{
						$quantity_check_pass = apply_filters('bkap_validate_add_to_cart',$_POST,$post_id);
					}
					else
					{
						if(isset($_POST['time_slot']))
						{
							$time_range = explode("-", $_POST['time_slot']);
							$from_time = date('G:i', strtotime($time_range[0]));
							if(isset($time_range[1])) $to_time = date('G:i', strtotime($time_range[1]));
							else $to_time = '';
						}
						else
						{
							$to_time = '';
							$from_time = '';
						}
						if($to_time != '')
						{
							$query = "SELECT total_booking, available_booking, start_date FROM `".$wpdb->prefix."booking_history`
								WHERE post_id = '".$post_id."'
								AND start_date = '".$date_check."'
								AND from_time = '".$from_time."'
								AND to_time = '".$to_time."' ";
							$results = $wpdb->get_results( $query );
						}
						else
						{
							$query = "SELECT total_booking, available_booking, start_date FROM `".$wpdb->prefix."booking_history`
							WHERE post_id = '".$post_id."'
							AND start_date = '".$date_check."'
							AND from_time = '".$from_time."'";	
							$results = $wpdb->get_results( $query );
						}
			
						if (isset($results) && count($results) > 0)
						{
							if ($_POST['time_slot'] != "")
							{
								// if current format is 12 hour format, then convert the times to 24 hour format to check in database
								if ($time_format == '12')
								{
									$time_exploded = explode("-", $_POST['time_slot']);
									$from_time = date('h:i A', strtotime($time_exploded[0]));
									if(isset($time_exploded[1])) $to_time = date('h:i A', strtotime($time_exploded[1]));
									else $to_time = '';
									
									if($to_time != '') $time_slot_to_display = $from_time.' - '.$to_time;
									else $time_slot_to_display = $from_time;
								}
								else
								{
									if($to_time != '') $time_slot_to_display = $from_time.' - '.$to_time;
									else $time_slot_to_display = $from_time;
								}
								
								if( $results[0]->available_booking > 0 && $results[0]->available_booking < $_POST['quantity'] )
								{
									$message = $post_title->post_title.book_t('book.limited-booking-msg1') .$results[0]->available_booking.book_t('book.limited-booking-msg2').$time_slot_to_display.'.';
									wc_add_notice( $message, $notice_type = 'error');
									$quantity_check_pass = 'false';
								}
								elseif ( $results[0]->total_booking > 0 && $results[0]->available_booking == 0 )
								{
									$message = book_t('book.no-booking-msg1').$post_title->post_title.book_t('book.no-booking-msg2').$time_slot_to_display.book_t('book.no-booking-msg3');
									wc_add_notice( $message, $notice_type = 'error');
									$quantity_check_pass = 'false';
								}
							}
						}
					//check if the same product has been added to the cart for the same dates
						if ($quantity_check_pass == "true")
						{
							foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $values )
							{
								$booking = $values['booking'];
								$quantity = $values['quantity'];
								$product_id = $values['product_id'];
				
								if ($product_id == $post_id && $booking[0]['hidden_date'] == $_POST['wapbk_hidden_date'] && $booking[0]['time_slot'] == $_POST['time_slot'])
								{
									$total_quantity = $_POST['quantity'] + $quantity;
									if (isset($results) && count($results) > 0)
									{
										if ($results[0]->available_booking > 0 && $results[0]->available_booking < $total_quantity)
										{
											$message = $post_title->post_title.book_t('book.limited-booking-msg1') .$results[0]->available_booking.book_t('book.limited-booking-msg2').$time_slot_to_display.'.';
											wc_add_notice( $message, $notice_type = 'error');
											$quantity_check_pass = 'false';
										}
									}
								}
							}
						}
					}
				}
				elseif (isset($booking_settings['booking_enable_multiple_day']) && $booking_settings['booking_enable_multiple_day'] == 'on')
				{
					$date_checkout = date('d-n-Y', strtotime($_POST['wapbk_hidden_date_checkout']));
					$date_cheeckin = date('d-n-Y', strtotime($_POST['wapbk_hidden_date']));
					$order_dates = $this->betweendays($date_cheeckin, $date_checkout);
					$todays_date = date('Y-m-d');

					$query_date ="SELECT DATE_FORMAT(start_date,'%d-%c-%Y') as start_date,DATE_FORMAT(end_date,'%d-%c-%Y') as end_date FROM ".$wpdb->prefix."booking_history
						WHERE start_date >='".$todays_date."' AND post_id = '".$post_id."'";
				
					$results_date = $wpdb->get_results($query_date);
					//print_r($results_date);	

					$dates_new = array();
			
					foreach($results_date as $k => $v)
					{
						$start_date = $v->start_date;
						$end_date = $v->end_date;
						$dates = $this->betweendays($start_date, $end_date);
						$dates_new = array_merge($dates,$dates_new);
					}
					$dates_new_arr = array_count_values($dates_new);
			
					$lockout = "";
					if (isset($booking_settings['booking_date_lockout']))
					{
						$lockout = $booking_settings['booking_date_lockout'];
					}
					
					foreach ($order_dates as $k => $v)
					{
						if (array_key_exists($v,$dates_new_arr))
						{
							if ($lockout != 0 && $lockout < $dates_new_arr[$v] + $_POST['quantity'])
							{
								$available_tickets = $lockout - $dates_new_arr[$v];
								$message = $post_title->post_title.book_t('book.limited-booking-date-msg1')	.$available_tickets.book_t('book.limited-booking-date-msg2').$v.'.';
								wc_add_notice( $message, $notice_type = 'error');
								$quantity_check_pass = 'false';
							}
						}
						else
						{
							if ($lockout != 0 && $lockout < $_POST['quantity'])
							{
								$available_tickets = $lockout;
								$message = $post_title->post_title.book_t('book.limited-booking-date-msg1')	.$available_tickets.book_t('book.limited-booking-date-msg2').$v.'.';
								wc_add_notice( $message, $notice_type = 'error');
								$quantity_check_pass = 'false';
							}
						}
					}
					//check if the same product has been added to the cart for the same dates
					if ($quantity_check_pass == "true")
					{
						foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $values )
						{
							if (isset($values['booking'])) $booking = $values['booking'];
							$quantity = $values['quantity'];
							$product_id = $values['product_id'];
								
							if (isset($booking[0]['hidden_date']) && isset($booking[0]['hidden_date_checkout'])) $dates = $this->betweendays($booking[0]['hidden_date'], $booking[0]['hidden_date_checkout']);
							/*	echo "<pre>";
							 print_r($dates);
							echo "</pre>";*/
							if ($product_id == $post_id)
							{
								foreach ($order_dates as $k => $v)
								{
									if (array_key_exists($v,$dates_new_arr))
									{
										if (in_array($v,$dates))
										{
											if ($lockout != 0 && $lockout < $dates_new_arr[$v] + $_POST['quantity'] + $quantity)
											{
												$available_tickets = $lockout - $dates_new_arr[$v];
												$message = $post_title->post_title.book_t('book.limited-booking-date-msg1')	.$available_tickets.book_t('book.limited-booking-date-msg2').$v.'.';
												wc_add_notice( $message, $notice_type = 'error');
												$quantity_check_pass = 'false';
											}
										}
										else
										{
											if ($lockout != 0 && $lockout < $dates_new_arr[$v] + $_POST['quantity'])
											{
												$available_tickets = $lockout - $dates_new_arr[$v];
												$message = $post_title->post_title.book_t('book.limited-booking-date-msg1')	.$available_tickets.book_t('book.limited-booking-date-msg2').$v.'.';
												wc_add_notice( $message, $notice_type = 'error');
												$quantity_check_pass = 'false';
											}
										}
									}
									else
									{
										if (in_array($v,$dates))
										{
											if ($lockout != 0 && $lockout < $_POST['quantity'] + $quantity)
											{
												$available_tickets = $lockout;
												$message = $post_title->post_title.book_t('book.limited-booking-date-msg1')	.$available_tickets.book_t('book.limited-booking-date-msg2').$v.'.';
												wc_add_notice( $message, $notice_type = 'error');
												$quantity_check_pass = 'false';
											}
										}
										else
										{
											if ($lockout != 0 && $lockout < $_POST['quantity'])
											{
												$available_tickets = $lockout;
												$message = $post_title->post_title.book_t('book.limited-booking-date-msg1')	.$available_tickets.book_t('book.limited-booking-date-msg2').$v.'.';
												wc_add_notice( $message, $notice_type = 'error');
												$quantity_check_pass = 'false';
											}
										}
									}
								}
							}
						}
					}
				}
				else
				{
					$query = "SELECT total_booking, available_booking, start_date FROM `".$wpdb->prefix."booking_history`
						WHERE post_id = '".$post_id."'
						AND start_date = '".$date_check."' ";
					$results = $wpdb->get_results( $query );
					//print_r($results);exit;

					if (isset($results) && count($results) > 0)
					{
						if( $results[0]->available_booking > 0 && $results[0]->available_booking < $_POST['quantity'] )
						{
							$message = $post_title->post_title.book_t('book.limited-booking-date-msg1')	.$results[0]->available_booking.book_t('book.limited-booking-date-msg2').$results[0]->start_date.'.';
							wc_add_notice( $message, $notice_type = 'error');
							$quantity_check_pass = 'false';
						}
						elseif ( $results[0]->total_booking > 0 && $results[0]->available_booking == 0 )
						{
							$message = book_t('book.no-booking-date-msg1').$post_title->post_title.book_t('book.no-booking-date-msg2').$results[0]->start_date.book_t('book.no-booking-date-msg3');
							wc_add_notice( $message, $notice_type = 'error');
							$quantity_check_pass = 'false';
						}
					}
					if ($quantity_check_pass == "true")
					{
						foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $values )
						{
							if(array_key_exists('booking',$values))
							{
								$booking = $values['booking'];
							}
							else
							{
								$booking = array();
							}
							$quantity = $values['quantity'];
							$product_id = $values['product_id'];		
							if ($product_id == $post_id && $booking[0]['hidden_date'] == $_POST['wapbk_hidden_date'])
							{
								$total_quantity = $_POST['quantity'] + $quantity;
								if (isset($results) && count($results) > 0)
								{
									if( $results[0]->available_booking > 0 && $results[0]->available_booking < $total_quantity )
									{
										$message = $post_title->post_title.book_t('book.limited-booking-date-msg1')	.$results[0]->available_booking.book_t('book.limited-booking-date-msg2').$results[0]->start_date.'.';
										wc_add_notice( $message, $notice_type = 'error');
										$quantity_check_pass = 'false';
									}
								}
							}
						}
					}
				}
				return $quantity_check_pass;
			}
				
			function before_add_to_cart() {
			
				global $post,$wpdb;
				
				/*$defaults = array(
							'input_name'  => 'ITEMS',
							'input_value'  => '5',
							'max_value'  => '',
							'min_value'  => '0'
						);
						
				woocommerce_quantity_input($defaults);
*/
				$booking_settings = get_post_meta($post->ID, 'woocommerce_booking_settings', true);
				if ( $booking_settings != '' && (isset($booking_settings['booking_enable_date']) && $booking_settings['booking_enable_date'] == 'on') && (isset($booking_settings['booking_purchase_without_date']) && $booking_settings['booking_purchase_without_date'] != 'on')):
				?>
				<script type="text/javascript">

				jQuery(document).ready(function()
				{
					jQuery( ".single_add_to_cart_button" ).hide();
					jQuery( ".payment_type" ).hide();
					jQuery( ".quantity" ).hide();
					jQuery(".partial_message").hide();
				})
				</script>
				<?php 
				endif;
			}
			
			function bookings_update_db_check() {
				global $booking_plugin_version, $BookUpdateChecker;
		
				$booking_plugin_version = $BookUpdateChecker;
				
				if ($booking_plugin_version == "1.7.6") {
					$this->bookings_activate();
				}
			}
			
			function bookings_activate() {
				
				global $wpdb;
				
				$table_name = $wpdb->prefix . "booking_history";
				
				$sql = "CREATE TABLE IF NOT EXISTS $table_name (
						`id` int(11) NOT NULL AUTO_INCREMENT,
						`post_id` int(11) NOT NULL,
  						`weekday` varchar(50) NOT NULL,
  						`start_date` date NOT NULL,
  						`end_date` date NOT NULL,
						`from_time` varchar(50) NOT NULL,
						`to_time` varchar(50) NOT NULL,
						`total_booking` int(11) NOT NULL,
						`available_booking` int(11) NOT NULL,
						PRIMARY KEY (`id`)
				) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1" ;
				
				$order_table_name = $wpdb->prefix . "booking_order_history";
				$order_sql = "CREATE TABLE IF NOT EXISTS $order_table_name (
							`id` int(11) NOT NULL AUTO_INCREMENT,
							`order_id` int(11) NOT NULL,
							`booking_id` int(11) NOT NULL,
							PRIMARY KEY (`id`)
				)ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1" ;

				$table_name_price = $wpdb->prefix . "booking_block_price_meta";

				$sql_price = "CREATE TABLE IF NOT EXISTS ".$table_name_price." (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`post_id` int(11) NOT NULL,
                `minimum_number_of_days` int(11) NOT NULL,
				`maximum_number_of_days` int(11) NOT NULL,
                `price_per_day` double NOT NULL,
				`fixed_price` double NOT NULL,
				 PRIMARY KEY (`id`)
				) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 " ;
				
				$table_name_meta = $wpdb->prefix . "booking_block_price_attribute_meta";
				
				$sql_meta = "CREATE TABLE IF NOT EXISTS ".$table_name_meta." (
					`id` int(11) NOT NULL AUTO_INCREMENT,
					`post_id` int(11) NOT NULL,
					`block_id` int(11) NOT NULL,
					`attribute_id` varchar(50) NOT NULL,
					`meta_value` varchar(500) NOT NULL,
					 PRIMARY KEY (`id`)
					) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 " ;

				$block_table_name = $wpdb->prefix . "booking_fixed_blocks";
				
				$blocks_sql = "CREATE TABLE IF NOT EXISTS ".$block_table_name." (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`global_id` int(11) NOT NULL,
				`post_id` int(11) NOT NULL,
				`block_name` varchar(50) NOT NULL,
				`number_of_days` int(11) NOT NULL,
				`start_day` varchar(50) NOT NULL,
				`end_day` varchar(50) NOT NULL,
				`price` double NOT NULL,
				`block_type` varchar(25) NOT NULL,
				PRIMARY KEY (`id`)
				) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 " ;
				
				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				dbDelta($sql);
				dbDelta($order_sql);
				dbDelta($sql_price);
				dbDelta($sql_meta);
				dbDelta($blocks_sql);
				update_option('woocommerce_booking_db_version','1.7.6');
			
				$check_table_query = "SHOW COLUMNS FROM $table_name LIKE 'end_date'";
				
				$results = $wpdb->get_results ( $check_table_query );
				if (count($results) == 0)
				{
					$alter_table_query = "ALTER TABLE $table_name
											ADD `end_date` date AFTER  `start_date`";
					$wpdb->get_results ( $alter_table_query );
				}
				$alter_block_table_query = "ALTER TABLE `$block_table_name` CHANGE `price` `price` DECIMAL(10,2) NOT NULL;";
				$wpdb->get_results ( $alter_block_table_query );
				
				//Set default labels
				add_option('book.date-label','Start Date');
				add_option('checkout.date-label','<br>End Date');
				add_option('book.time-label','Booking Time');
				
				add_option('book.item-meta-date','Start Date');
				add_option('checkout.item-meta-date','End Date');
				add_option('book.item-meta-time','Booking Time');
				
				add_option('book.item-cart-date','Start Date');
				add_option('checkout.item-cart-date','End Date');
				add_option('book.item-cart-time','Booking Time');
			}
			
			function woocommerce_booking_admin_menu(){
			
				add_menu_page( 'Booking','Booking','manage_woocommerce', 'booking_settings',array(&$this, 'woocommerce_booking_page' ));
				$page = add_submenu_page('booking_settings', __( 'Settings', 'woocommerce-booking' ), __( 'Settings', 'woocommerce-booking' ), 'manage_woocommerce', 'woocommerce_booking_page', array(&$this, 'woocommerce_booking_page' ));
				$page = add_submenu_page('booking_settings', __( 'View Bookings', 'woocommerce-booking' ), __( 'View Bookings', 'woocommerce-booking' ), 'manage_woocommerce', 'woocommerce_history_page', array(&$this, 'woocommerce_history_page' ));
				$page = add_submenu_page('booking_settings', __( 'Activate License', 'woocommerce-booking' ), __( 'Activate License', 'woocommerce-booking' ), 'manage_woocommerce', 'booking_license_page', array(&$this, 'edd_sample_license_page' ));
				remove_submenu_page('booking_settings','booking_settings');
				do_action('bkap_add_submenu');
			}
			
			function woocommerce_history_page() {
				
				if (isset($_GET['action']))
				{
					$action = $_GET['action'];
				}
				else
				{
					$action = '';
				}
				if ($action == 'history' || $action == '')
				{
					$active_settings = "nav-tab-active";
				}
					
				?>
				
				<p></p>
												
				<!-- <h2 class="nav-tab-wrapper woo-nav-tab-wrapper">
				<a href="admin.php?page=woocommerce_history_page&action=history" class="nav-tab <?php echo $active_settings; ?>"> <?php _e( 'Booking History', 'woocommerce-ac' );?> </a>
				</h2> -->
				
				<?php
				
				if ( $action == 'history' || $action == '' )
				{
					global $wpdb;
						
					$query_order = "SELECT DISTINCT order_id FROM `" . $wpdb->prefix . "woocommerce_order_items`  ";
					$order_results = $wpdb->get_results( $query_order );
					
					$var = $today_checkin_var = $today_checkout_var = $booking_time = "";
					
					$booking_time_label = get_option('book.item-meta-time');
				//	echo $booking_time_label;
					
					foreach ( $order_results as $id_key => $id_value )
					{
						$order = new WC_Order( $id_value->order_id );
						
						$order_items = $order->get_items();
						
						$terms = wp_get_object_terms( $id_value->order_id, 'shop_order_status', array('fields' => 'slugs') );
						if( (isset($terms[0]) && $terms[0] != 'cancelled') && (isset($terms[0]) && $terms[0] != 'refunded'))
						{
						$today_query = "SELECT * FROM `".$wpdb->prefix."booking_history` AS a1,`".$wpdb->prefix."booking_order_history` AS a2 WHERE a1.id = a2.booking_id AND a2.order_id = '".$id_value->order_id."'";
						$results_date = $wpdb->get_results ( $today_query );

						$c = 0;
						foreach ($order_items as $items_key => $items_value )
						{
							$start_date = $end_date = $booking_time = "";
							
							$booking_time = array();
							//print_r($items_value);
							if (isset($items_value[$booking_time_label]))
							{
								$booking_time = explode(",",$items_value[$booking_time_label]);
							}
							
							$duplicate_of = get_post_meta($items_value['product_id'], '_icl_lang_duplicate_of', true);
							if($duplicate_of == '' && $duplicate_of == null)
							{
								$post_time = get_post($items_value['product_id']);
								if (isset($post_time))
								{
									$id_query = "SELECT ID FROM `".$wpdb->prefix."posts` WHERE post_date = '".$post_time->post_date."' ORDER BY ID LIMIT 1";
									$results_post_id = $wpdb->get_results ( $id_query );
									if( isset($results_post_id) ) {
										$duplicate_of = $results_post_id[0]->ID;
									}
									else
									{
										$duplicate_of = $items_value['product_id'];
									}
								}
								else {
									$duplicate_of = $items_value['product_id'];
								}
							}
						//	echo "<pre>";echo $id_value->order_id; print_r($booking_time);echo "</pre>";
							if ( isset($results_date[$c]->start_date) )
							{
								if (isset($results_date[$c]) && isset($results_date[$c]->start_date)) $start_date = $results_date[$c]->start_date;
								
								if (isset($results_date[$c]) && isset($results_date[$c]->end_date)) $end_date = $results_date[$c]->end_date;
								
								if ($start_date == '0000-00-00' || $start_date == '1970-01-01') $start_date = '';
								if ($end_date == '0000-00-00' || $end_date == '1970-01-01') $end_date = '';
								$amount = $items_value['line_total'] + $items_value['line_tax'];
								if(is_plugin_active('bkap-printable-tickets/printable-tickets.php'))
								{
									$var_details = apply_filters('bkap_view_bookings',$id_value->order_id,$results_date[$c]->booking_id,$items_value['qty']);
								}
								else
								{
									$var_details = array();
								}
								if (count($booking_time) > 0)
								{
								foreach ($booking_time as $time_key => $time_value)
								{	
									if(array_key_exists('ticket_id',$var_details) && array_key_exists('security_code',$var_details))
									{
									
										$var .= "<tr>
										<td>".$id_value->order_id."</td>
										<td>".$order->billing_first_name." ".$order->billing_last_name."</td>
										<td>".$items_value['name']."</td>
										<td>".$start_date."</td>
										<td>".$end_date."</td>
										<td>".$time_value."</td>
										<td>".$amount."</td>
										<td>".$order->completed_date."</td>
										".$var_details['ticket_id']."
										".$var_details['security_code']."
										<td><a href=\"post.php?post=". $id_value->order_id."&action=edit\">View Order</a></td>
										</tr>";
									}
									else
									{
										$var .= "<tr>
										<td>".$id_value->order_id."</td>
										<td>".$order->billing_first_name." ".$order->billing_last_name."</td>
										<td>".$items_value['name']."</td>
										<td>".$start_date."</td>
										<td>".$end_date."</td>
										<td>".$time_value."</td>
										<td>".$amount."</td>
										<td>".$order->completed_date."</td>
										<td><a href=\"post.php?post=". $id_value->order_id."&action=edit\">View Order</a></td>
										</tr>";
									}
									//foreach ($results_date as $key_date => $value_date )
									{
										/*$start_date_r = $end_date_r = '';
										if (isset($value_date->start_date)) $start_date_r = $value_date->start_date;
										if (isset($value_date->end_date)) $end_date_r = $value_date->end_date;
										
										if ($start_date_r == '0000-00-00' || $start_date_r == '1970-01-01') $start_date_r = '';
										if ($end_date_r == '0000-00-00' || $end_date_r == '1970-01-01') $end_date_r = '';*/
											
										if ( $start_date == date('Y-m-d' , current_time('timestamp') ) )
										{
										if(array_key_exists('ticket_id',$var_details) && array_key_exists('security_code',$var_details))

											{
												$today_checkin_var .= "<tr>
												<td>".$id_value->order_id."</td>
												<td>".$order->billing_first_name." ".$order->billing_last_name."</td>
												<td>".$items_value['name']."</td>
												<td>".$start_date."</td>
												<td>".$end_date."</td>
												<td>".$time_value."</td>
												<td>".$amount."</td>
												<td>".$order->completed_date."</td>
												".$var_details['ticket_id']."
												".$var_details['security_code']."
												<td><a href=\"post.php?post=". $id_value->order_id."&action=edit\">View Order</a></td>
												</tr>";
											}
											else 
											{
												$today_checkin_var .= "<tr>
												<td>".$id_value->order_id."</td>
												<td>".$order->billing_first_name." ".$order->billing_last_name."</td>
												<td>".$items_value['name']."</td>
												<td>".$start_date."</td>
												<td>".$end_date."</td>
												<td>".$time_value."</td>
												<td>".$amount."</td>
												<td>".$order->completed_date."</td>
												<td><a href=\"post.php?post=". $id_value->order_id."&action=edit\">View Order</a></td>
												</tr>";
											}
										}
										
										if ( $end_date == date('Y-m-d' , current_time('timestamp') ) )
										{
										if(array_key_exists('ticket_id',$var_details) && array_key_exists('security_code',$var_details))

											{
												$today_checkout_var .= "<tr>
												<td>".$id_value->order_id."</td>
												<td>".$order->billing_first_name." ".$order->billing_last_name."</td>
												<td>".$items_value['name']."</td>
												<td>".$start_date."</td>
												<td>".$end_date."</td>
												<td>".$time_value."</td>
												<td>".$amount."</td>
												<td>".$order->completed_date."</td>
												".$var_details['ticket_id']."
												".$var_details['security_code']."
												<td><a href=\"post.php?post=". $id_value->order_id."&action=edit\">View Order</a></td>
												</tr>";
											}
											else
											{	
												$today_checkout_var .= "<tr>
												<td>".$id_value->order_id."</td>
												<td>".$order->billing_first_name." ".$order->billing_last_name."</td>
												<td>".$items_value['name']."</td>
												<td>".$start_date."</td>
												<td>".$end_date."</td>
												<td>".$time_value."</td>
												<td>".$amount."</td>
												<td>".$order->completed_date."</td>
												<td><a href=\"post.php?post=". $id_value->order_id."&action=edit\">View Order</a></td>
												</tr>";
											}
										}
									}
									if ( $start_date != "" ) $c++;
								}
								}
								else 
								{
								if(array_key_exists('ticket_id',$var_details) && array_key_exists('security_code',$var_details))

									{
										$var .= "<tr>
										<td>".$id_value->order_id."</td>
										<td>".$order->billing_first_name." ".$order->billing_last_name."</td>
										<td>".$items_value['name']."</td>
										<td>".$start_date."</td>
										<td>".$end_date."</td>
										<td></td>
										<td>".$amount."</td>
										<td>".$order->completed_date."</td>
										".$var_details['ticket_id']."
										".$var_details['security_code']."
										<td><a href=\"post.php?post=". $id_value->order_id."&action=edit\">View Order</a></td>
										</tr>";
									}
									else
									{
										$var .= "<tr>
										<td>".$id_value->order_id."</td>
										<td>".$order->billing_first_name." ".$order->billing_last_name."</td>
										<td>".$items_value['name']."</td>
										<td>".$start_date."</td>
										<td>".$end_date."</td>
										<td></td>
										<td>".$amount."</td>
										<td>".$order->completed_date."</td>
										<td><a href=\"post.php?post=". $id_value->order_id."&action=edit\">View Order</a></td>
										</tr>";
									}
										
									if ( $start_date == date('Y-m-d' , current_time('timestamp') ) )
									{
									if(array_key_exists('ticket_id',$var_details) && array_key_exists('security_code',$var_details))

										{
											$today_checkin_var .= "<tr>
											<td>".$id_value->order_id."</td>
											<td>".$order->billing_first_name." ".$order->billing_last_name."</td>
											<td>".$items_value['name']."</td>
											<td>".$start_date."</td>
											<td>".$end_date."</td>
											<td></td>
											<td>".$amount."</td>
											<td>".$order->completed_date."</td>
											".$var_details['ticket_id']."
											".$var_details['security_code']."
											<td><a href=\"post.php?post=". $id_value->order_id."&action=edit\">View Order</a></td>
											</tr>";
										}
										else
										{
											$today_checkin_var .= "<tr>
											<td>".$id_value->order_id."</td>
											<td>".$order->billing_first_name." ".$order->billing_last_name."</td>
											<td>".$items_value['name']."</td>
											<td>".$start_date."</td>
											<td>".$end_date."</td>
											<td></td>
											<td>".$amount."</td>
											<td>".$order->completed_date."</td>
											<td><a href=\"post.php?post=". $id_value->order_id."&action=edit\">View Order</a></td>
											</tr>";
										}
									}
									
									if ( $end_date == date('Y-m-d' , current_time('timestamp') ) )
									{
										if(array_key_exists('ticket_id',$var_details) && array_key_exists('security_code',$var_details))
										{
											$today_checkout_var .= "<tr>
											<td>".$id_value->order_id."</td>
											<td>".$order->billing_first_name." ".$order->billing_last_name."</td>
											<td>".$items_value['name']."</td>
											<td>".$start_date."</td>
											<td>".$end_date."</td>
											<td></td>
											<td>".$amount."</td>
											<td>".$order->completed_date."</td>
											".$var_details['ticket_id']."
											".$var_details['security_code']."
											<td><a href=\"post.php?post=". $id_value->order_id."&action=edit\">View Order</a></td>
											</tr>";
										}
										else
										{
											$today_checkout_var .= "<tr>
											<td>".$id_value->order_id."</td>
											<td>".$order->billing_first_name." ".$order->billing_last_name."</td>
											<td>".$items_value['name']."</td>
											<td>".$start_date."</td>
											<td>".$end_date."</td>
											<td></td>
											<td>".$amount."</td>
											<td>".$order->completed_date."</td>
											<td><a href=\"post.php?post=". $id_value->order_id."&action=edit\">View Order</a></td>
											</tr>";
										}
									}
									if ( $start_date != "" ) $c++;
								}
							}
						}
						}
					}
						
					$swf_path = plugins_url()."/woocommerce-booking/TableTools/media/swf/copy_csv_xls.swf";
					?>
									
						<script>
						
						jQuery(document).ready(function() {
						 	var oTable = jQuery('.datatable').dataTable( {
									"bJQueryUI": true,
									"sScrollX": "",
									"bSortClasses": false,
									"aaSorting": [[0,'desc']],
									"bAutoWidth": true,
									"bInfo": true,
									"sScrollY": "100%",	
									"sScrollX": "100%",
									"bScrollCollapse": true,
									"sPaginationType": "full_numbers",
									"bRetrieve": true,
									"oLanguage": {
													"sSearch": "Search:",
													"sInfo": "Showing _START_ to _END_ of_TOTAL_ entries",
													"sInfoEmpty": "Showing 0 to 0 of 0 entries",
													"sZeroRecords": "No matching records found",
													"sInfoFiltered": "(filtered from _MAX_total entries)",
													"sEmptyTable": "No data available in table",
													"sLengthMenu": "Show _MENU_ entries",
													"oPaginate": {
																	"sFirst":    "First",
																	"sPrevious": "Previous",
																	"sNext":     "Next",
																	"sLast":     "Last"
																  }
												 },
									 "sDom": 'T<"clear"><"H"lfr>t<"F"ip>',
							         "oTableTools": {
											            "sSwfPath": "<?php echo plugins_url(); ?>/woocommerce-booking/TableTools/media/swf/copy_csv_xls_pdf.swf"
											        }
									 
						} );
					} );
						
						       
						</script>
						
						
						
						<div style="float: left;">
						<h2><strong>All Bookings</strong></h2>
						</div>
						<div>
						<table id="booking_history" class="display datatable" >
						    <thead>
						        <tr>
						        	<th><?php _e( 'Order ID' , 'woocommerce-booking' ); ?></th>
						            <th><?php _e( 'Customer Name' , 'woocommerce-booking' ); ?></th>
						            <th><?php _e( 'Product Name' , 'woocommerce-booking' ); ?></th>
						            <th><?php _e( 'Check-in Date' , 'woocommerce-booking' ); ?></th>
						            <th><?php _e( 'Check-out Date' , 'woocommerce-booking' ); ?></th>
						            <th><?php _e( 'Booking Time' , 'woocommerce-booking' ); ?></th>
						            <th><?php _e( 'Amount' , 'woocommerce-booking' ); ?></th>
						            <th><?php _e( 'Booking Date' , 'woocommerce-booking' ); ?></th>
						            <?php 
						            if (isset($var_details) && count($var_details) > 0 && $var_details != false)
						            {
							            if(array_key_exists('ticket_field',$var_details) && array_key_exists('security_field',$var_details))
							            {
							            	?>
											<th><?php _e( $var_details['ticket_field'],'woocommerce_booking' );?></th>
							            	<th><?php _e( $var_details['security_field'],'woocommerce_booking' );?></th>
							            <?php 
							            }
						            }
						            ?>
						            <th><?php _e( 'Action' , 'woocommerce-booking' ); ?></th>
						        </tr>
						    </thead>
						    <tbody>
					            <?php echo $var;?>
						    </tbody>
						</table>
						</div>
						
						<p></p>
						
						<div style="float: left;padding: 5">
						<h2><strong>Today Check-ins</strong></h2></div>
						<div>
						<table id="booking_history_today_check_in" class="display datatable" >
						    <thead>
						        <tr>
						        	<th><?php _e( 'Order ID' , 'woocommerce-booking' ); ?></th>
						            <th><?php _e( 'Customer Name' , 'woocommerce-booking' ); ?></th>
						            <th><?php _e( 'Product Name' , 'woocommerce-booking' ); ?></th>
						            <th><?php _e( 'Check-in Date' , 'woocommerce-booking' ); ?></th>
						            <th><?php _e( 'Check-out Date' , 'woocommerce-booking' ); ?></th>
						            <th><?php _e( 'Booking Time' , 'woocommerce-booking' ); ?></th>
						            <th><?php _e( 'Amount' , 'woocommerce-booking' ); ?></th>
						            <th><?php _e( 'Booking Date' , 'woocommerce-booking' ); ?></th>
						            <?php
						            if (isset($var_details) && count($var_details) > 0 && $var_details != false)
						            { 
							            if(array_key_exists('ticket_field',$var_details) && array_key_exists('security_field',$var_details))
							            {
							            	?>
											<th><?php _e( $var_details['ticket_field'],'woocommerce_booking' );?></th>
							            	<th><?php _e( $var_details['security_field'],'woocommerce_booking' );?></th>
							            <?php 
							            }
						            }
						            ?>
						            <th><?php _e( 'Action' , 'woocommerce-booking' ); ?></th>
						        </tr>
						    </thead>
						    <tbody>
					            <?php echo $today_checkin_var;?>
						    </tbody>
						</table>
						</div>
						
						<p></p>
						
						<div style="float: left;">
						<h2><strong>Today Check-outs</strong></h2></div>
						<div>
						<table id="booking_history_today_check_out" class="display datatable" >
						    <thead>
						        <tr>
						        	<th><?php _e( 'Order ID' , 'woocommerce-booking' ); ?></th>
						            <th><?php _e( 'Customer Name' , 'woocommerce-booking' ); ?></th>
						            <th><?php _e( 'Product Name' , 'woocommerce-booking' ); ?></th>
						            <th><?php _e( 'Check-in Date' , 'woocommerce-booking' ); ?></th>
						            <th><?php _e( 'Check-out Date' , 'woocommerce-booking' ); ?></th>
						            <th><?php _e( 'Booking Time' , 'woocommerce-booking' ); ?></th>
						            <th><?php _e( 'Amount' , 'woocommerce-booking' ); ?></th>
						            <th><?php _e( 'Booking Date' , 'woocommerce-booking' ); ?></th>
						            <?php 
						            if (isset($var_details) && count($var_details) > 0 && $var_details != false)
						            {
							            if(array_key_exists('ticket_field',$var_details) && array_key_exists('security_field',$var_details))
							            {
							            	?>
											<th><?php _e( $var_details['ticket_field'],'woocommerce_booking' );?></th>
							            	<th><?php _e( $var_details['security_field'],'woocommerce_booking' );?></th>
							            <?php 
							            }
						            }
						            ?>
						            <th><?php _e( 'Action' , 'woocommerce-booking' ); ?></th>
						        </tr>
						    </thead>
						    <tbody>
					            <?php echo $today_checkout_var;?>
						    </tbody>
						</table>
						</div>
					<?php 
					
				}
			}
			
			function woocommerce_booking_page() {
				
				if (isset($_GET['action']))
				{
					$action = $_GET['action'];
				}
				else
				{
					$action = '';
				}
				if ($action == 'settings' || $action == '')
				{
					$active_settings = "nav-tab-active";
				}
				else
				{
					$active_settings = '';
				}
					
				if ($action == 'labels')
				{
					$active_labels = "nav-tab-active";
				}
				else
				{
					$active_labels = '';
				}
			/*	if ($action == 'reminders_settings')
				{
					$active_reminders_settings = "nav-tab-active";
				}*/
				?>
								
				<h2 class="nav-tab-wrapper woo-nav-tab-wrapper">
				<a href="admin.php?page=woocommerce_booking_page&action=settings" class="nav-tab <?php echo $active_settings; ?>"> <?php _e( 'Global Booking Settings', 'woocommerce-ac' );?> </a>
				<a href="admin.php?page=woocommerce_booking_page&action=labels" class="nav-tab <?php echo $active_labels; ?>"> <?php _e( 'Booking Labels', 'woocommerce-ac' );?> </a>
			<!-- 	<a href="admin.php?page=woocommerce_booking_page&action=reminders_settings" class="nav-tab <?php echo $active_reminders_settings; ?>"> <?php _e( 'Email Reminders', 'woocommerce-ac' );?> </a> -->
				</h2>
				
				<?php
			/*	if( $action == 'reminders_settings'){
						
					if (isset($_GET["p"])) $p = $_GET["p"];
					else $p = '';
					if (isset($_GET["id"])) $id = $_GET["id"];
					else $id = '';
						
					if($p == "update"){
						$all_reminders = array_values(json_decode(get_option("globalemailreminders"),true));
				
						if(!$all_reminders){
							$all_reminders = array ();
						}
						//echo "<pre>";print_r(stripslashes($_POST["erem_subject"]));echo "</pre>";exit;
						$id = $_POST["id"];
						$subject = stripslashes($_POST["erem_subject"]);
						$message = stripslashes($_POST["erem_message"]);
						$days = $_POST["erem_days"];
						$hours = $_POST["erem_hours"];
						$minutes = $_POST["erem_minutes"];
						$total_minutes = $minutes + ($hours * 60) + (($days * 24 )*60);
						$email = $_POST["erem_email"];
				
				
				
				
						if($id != ""){
							$all_reminders[$id]["subject"] =  $subject;
							$all_reminders[$id]["message"] =  $message;
							$all_reminders[$id]["days"] =  $days;
							$all_reminders[$id]["hours"] =  $hours;
							$all_reminders[$id]["minutes"] =  $minutes;
							$all_reminders[$id]["total_minutes"] =  $total_minutes;
							$all_reminders[$id]["email"] =  $email;
						}else{
								
							array_push($all_reminders,
									array('subject' => $subject,
											'message' => $message,
											'days' => $days,
											'hours' => $hours,
											'minutes' => $minutes,
											'total_minutes' => $total_minutes,
											'email' => $email) );
						}
				
						update_option( "globalemailreminders", json_encode($all_reminders) );
				
					}if($p == "delete"){
						$all_reminders = array_values(json_decode(get_option("globalemailreminders"),true));
						unset($all_reminders[$id]);
						update_option( "globalemailreminders", json_encode($all_reminders) );
					}
					?>
				                 <style type="text/css">
								#wpfooter{
									display:none !important;	
								}
								</style>
				                <a href="admin.php?page=woocommerce_booking_page&action=reminders_settings&p=manage" > <?php _e( 'New Email Reminders', 'woocommerce-ac' );?> </a>
				                <?php
								if( $p == 'manage'){	
							//	$all_reminders = array_values(json_decode(get_option("globalemailreminders"),true));
								$email_reminders = json_decode(get_option("globalemailreminders"),true);
								if ($email_reminders != '') $all_reminders = array_values($email_reminders);
								else $all_reminders = '';
								
								if (isset($all_reminders[$id]["subject"])) $subject = $all_reminders[$id]["subject"];
								else $subject = '';
								
								if (isset($all_reminders[$id]["message"])) $message = $all_reminders[$id]["message"];
								else $message = '';
								
								if (isset($all_reminders[$id]["days"])) $days = $all_reminders[$id]["days"];
								else $days = '';
								
								if (isset($all_reminders[$id]["hours"])) $hours = $all_reminders[$id]["hours"];
								else $hours = '';
								
								if (isset($all_reminders[$id]["minutes"])) $minutes = $all_reminders[$id]["minutes"];
								else $minutes = '';
								
								if (isset($all_reminders[$id]["email"])) $email_copy = $all_reminders[$id]["email"];
								else $email_copy = '';
								?>               
				                <div id="global_reminder_manage">
				                <form name="gerem_form" id="gerem_form" action="admin.php?page=woocommerce_booking_page&action=reminders_settings&p=update" method="post">
				                <table class='wp-list-table widefat fixed posts' cellspacing='0' >
				                    <tr>
				                        <td width="30%">Subject</td>
				                        <td width="70%">
				                        <input type="text" style="width: 400px;" name="erem_subject" id="erem_subject" value="<?php echo $subject; ?>" />
				                        <img class="help_tip" width="16" height="16" data-tip="<?php _e('Subject', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
				                        </td>
				                    </tr>
				                    <tr>
				                        <td>Message<br/><i>Available short codes<br/>First Name = [first_name]<br/>Last Name = [last_name]<br/>Booking Date = [date]<br/>Booking Time = [time]<br/>Shop Name = [shop_name]<br/>Shop URL = [shop_url]<br/>Service = [service]<br/>Order Number = [order_number]</i><br/></td>
				                        <td>
				                            <textarea rows='15' style="width: 100%;" name="erem_message" id="erem_message"><?php echo $message; ?></textarea>
				                        <img class="help_tip" width="16" height="16" data-tip="<?php _e('Message', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
				                        </td>
				                    </tr>
				                    <tr>
				                        <td>Time slot</td>
				                        <td>
				                        <select name="erem_days">
				                        	<option value="0">-Days-</option>
				                        	<?php
				                            for($x = 1; $x < 360 ; $x++){
												if($days == $x){
													echo ('<option value="'.$x.'" selected="selected">'.$x.'</option>');
												}else{
													echo ('<option value="'.$x.'">'.$x.'</option>');
												}
												
											}
											?> 
				                                                   
				                        </select>
				                        <select name="erem_hours">
				                        	<option value="0">-Hours-</option>
				                        	<?php
				                            for($x = 1; $x < 24 ; $x++){
												if($hours == $x){
													echo ('<option value="'.$x.'" selected="selected">'.$x.'</option>');
												}else{
													echo ('<option value="'.$x.'">'.$x.'</option>');
												}																
											}
											?>                            
				                        </select>
				                        <select name="erem_minutes">
				                        	<option value="0">-Minutes-</option>
				                        	<?php
				                            for($x = 1; $x < 60 ; $x++){
												if($minutes == $x){
													echo ('<option value="'.$x.'" selected="selected">'.$x.'</option>');
												}else{
													echo ('<option value="'.$x.'">'.$x.'</option>');
												}
											}
											?>                            
				                        </select>
				                        <img class="help_tip" width="16" height="16" data-tip="<?php _e('Time slot', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
				                        </tr>
				                    </tr>
				                    <tr>
				                        <td>Extra email address to send a copy to (separated by comma)</td>
				                        <td>
				                        <input type="text" style="width: 400px;" name="erem_email" id="erem_email" value="<?php echo $email_copy; ?>" />
				                        <img class="help_tip" width="16" height="16" data-tip="<?php _e('Extra email address to send a copy to (separated by comma)', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
				                        </td>
				                    </tr>
				                    <tr>
				                        <td><input type="hidden" name="id" id="id" value="<?php echo $_GET["id"]; ?>" /></td>
				                        <td>
				                        <?php if($id != ""){
											echo ('<input type="submit" value="Update Reminder" />');	
										}else{
											echo ('<input type="submit" value="Add Reminder" />');	
										}?>
				                        </td>
				                    </tr>
				                </table>
				                </form>
				                </div>
				                <?php
								}else if( $p == 'view'){
									$all_reminders = array_values(json_decode(get_option("globalemailreminders"),true));						
								?>
				                
				                <div id="global_reminder_view">
				                <table class='wp-list-table widefat fixed posts' cellspacing='0' >
				                    <tr>
				                        <th width="30%">Subject</th>
				                        <td width="70%"><?php echo $all_reminders[$id]["subject"]; ?></td>
				                    </tr>
				                    <tr>
				                        <th>Message</th>
				                        <td><?php echo $all_reminders[$id]["message"]; ?></td>
				                    </tr>
				                    <tr>
				                        <th>Time slot</th>
				                        <td><?php echo $all_reminders[$id]["days"]; ?>D / <?php echo $all_reminders[$id]["hours"]; ?>H / <?php echo $all_reminders[$id]["minutes"]; ?>M</tr>
				                    </tr>
				                    <tr>
				                        <th>Extra email address to send a copy to (separated by comma)</th>
				                        <td><?php echo $all_reminders[$id]["email"]; ?></td>
				                    </tr>
				                </table>
				                </div>
				                <?php
								}
								?>
				                <p>&nbsp;</p>
				                All Global Reminders
				                <table class="form-table" width="95%">
				                	<tr>
				                    	<th width="10">#</th>
				                        <th>Subject</th>
				                        <th width="100">Time slot</th>
				                        <th width="50">View</th>
				                        <th width="50">Update</th>
				                        <th width="50">Delete</th>
				                    </tr>
				                    <?php 
				                    $email_reminders = json_decode(get_option("globalemailreminders"),true);
				                    if ($email_reminders != '') $all_reminders = array_values($email_reminders);
				                    else $all_reminders = '';
								//	$all_reminders = array_values(json_decode(get_option("globalemailreminders"),true));
									$count = 0;
									if($all_reminders)
									foreach ($all_reminders as $reminders){
										
									?>
									<tr>
				                    	<td width="10"  valign="top"><?php echo($count + 1); ?></td>
				                        <td  valign="top"><?php echo $reminders["subject"]; ?></td>
				                        <td width="100"><?php echo $reminders["days"]; ?> Days <br/> <?php echo $reminders["hours"]; ?> Hours <br/> <?php echo $reminders["minutes"]; ?> Minutes</td>
				                        <td  valign="top">
				                        <a href="admin.php?page=woocommerce_booking_page&action=reminders_settings&p=view&id=<?php echo($count); ?>"> <?php _e( 'view', 'woocommerce-booking' );?> </a>
				                        </td>
				                        <td  valign="top">
				                        <a href="admin.php?page=woocommerce_booking_page&action=reminders_settings&p=manage&id=<?php echo($count); ?>"> <?php _e( 'update', 'woocommerce-booking' );?> </a>
				                        </td>
				                        <td valign="top"><a href="admin.php?page=woocommerce_booking_page&action=reminders_settings&p=delete&id=<?php echo($count); ?>" onclick="return confirm('Are you sure you want to delete this ?')">Delete</a></td>
				                    </tr>
				                    <?php	
									$count++;
									}
									?>
				                </table>
				                </div>				    
				                <?php	
								}*/
								
				if( $action == 'labels'){
				
					$labels_product_page = array(
						'book.date-label'=>'Check-in Date',
						'checkout.date-label'=>'Check-out Date',     		
						'book.time-label'=> 'Booking Time' );
					$labels_order_page = array(
						'book.item-meta-date'=>'Check-in Date',
						'checkout.item-meta-date'=>'Check-out Date',
						'book.item-meta-time'=>'Booking Time');
					$labels_cart_page = array(
						'book.item-cart-date'=>'Check-in Date',
						'checkout.item-cart-date'=>'Check-out Date',
						'book.item-cart-time'=>'Booking Time');
					
					if ( isset( $_POST['wapbk_booking_settings_frm'] ) && $_POST['wapbk_booking_settings_frm'] == 'labelsave' ) { 
						
						foreach($labels_product_page as $key=>$label){
							update_option($key, $_POST[str_replace(".","_",$key)]);
						}
						foreach($labels_order_page as $key=>$label){
							update_option($key, $_POST[str_replace(".","_",$key)]);
						}
						foreach($labels_cart_page as $key=>$label){
							update_option($key, $_POST[str_replace(".","_",$key)]);
						}
					?>
					<div id="message" class="updated fade"><p><strong><?php _e( 'Your settings have been saved.', 'woocommerce-booking' ); ?></strong></p></div>
					<?php } ?>
				
					<div id="content">
						  <form method="post" action="" id="booking_settings">
							  <input type="hidden" name="wapbk_booking_settings_frm" value="labelsave">
							 
							  <div id="poststuff">
									<div class="postbox">
										<h3 class="hndle"><?php _e( 'Labels', 'woocommerce-booking' ); ?></h3>
										<div>
										  <table class="form-table">
										  <tr> 
											<td colspan="2"><h2><strong><?php _e( 'Labels on product page', 'woocommerce-booking' ); ?></strong></h2></td>
											<td><img style="margin-right:550px;" class="help_tip" width="16" height="16" data-tip="<?php _e('This sets the Labels on the Product Page.', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" /></td>
										  </tr>
											<?php foreach ($labels_product_page as $key=>$label): 
												$value = get_option($key);
											?>
											<tr>
												<th>
													<label for="booking_language"><b><?php _e($label, 'woocommerce-booking'); ?></b></label>
												</th>
												<td>
													<input id="<?php echo $key?>" name="<?php echo $key?>" value="<?php echo $value;?>" >
												</td>
											</tr>
											<?php endforeach;?>
											<tr> 
											<td colspan="2"><h2><strong><?php _e( 'Labels on order received page and in email notification', 'woocommerce-booking' ); ?></strong></h2></td>
											<td><img style="margin-right:550px;" class="help_tip" width="16" height="16" data-tip="<?php _e('This sets the Labels on the Order Recieved and Email Notification page.', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" /></td>
											</tr>
											<?php foreach ($labels_order_page as $key=>$label): 
												$value = get_option($key);
											?>
											<tr>
												<th>
													<label for="booking_language"><b><?php _e($label, 'woocommerce-booking'); ?></b></label>
												</th>
												<td>
													<input id="<?php echo $key?>" name="<?php echo $key?>" value="<?php echo $value;?>" >
												</td>
											</tr>
											<?php endforeach;?>
											<tr> 
											<td colspan="2"><h2><strong><?php _e( 'Labels on Cart & Check-out Page', 'woocommerce-booking' ); ?></strong></h2></td>
											<td><img style="margin-right:550px;" class="help_tip" width="16" height="16" data-tip="<?php _e('This sets the Label on the Cart and the Checkout page.', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" /></td>
											</tr>
											<?php foreach ($labels_cart_page as $key=>$label): 
												$value = get_option($key);
											?>
											<tr>
												<th>
													<label for="booking_language"><b><?php _e($label, 'woocommerce-booking'); ?></b></label>
												</th>
												<td>
													<input id="<?php echo $key?>" name="<?php echo $key?>" value="<?php echo $value;?>" >
												</td>
											</tr>
											<?php endforeach;?>
											<tr>
												<th>
												<input type="submit" name="Submit" class="button-primary" value="<?php _e( 'Save Changes', 'woocommerce-booking' ); ?>" />
												</th>
											</tr>
											</table>
										</div>
									</div>
								</div>
							</form>
					</div>
					<?php							
				}		

				if( $action == 'settings' || $action == '' )
				{
					// Save the field values
					if ( isset( $_POST['wapbk_booking_settings_frm'] ) && $_POST['wapbk_booking_settings_frm'] == 'save' )
					{
						$calendar_theme = trim($_POST['wapbk_calendar_theme']);
						$calendar_themes = book_arrays('calendar_themes');
						$calendar_theme_name = $calendar_themes[$calendar_theme];
						
						$booking_settings = new stdClass();
						$booking_settings->booking_language = $_POST['booking_language'];
						$booking_settings->booking_date_format = $_POST['booking_date_format'];
						$booking_settings->booking_time_format = $_POST['booking_time_format'];
						$booking_settings->booking_months = $_POST['booking_months'];	
						$booking_settings->booking_calendar_day = $_POST['booking_calendar_day'];	
						if (isset($_POST['booking_enable_rounding']))
							$booking_settings->enable_rounding = $_POST['booking_enable_rounding'];
						else
							$booking_settings->enable_rounding = '';
						if(isset($_POST['booking_add_to_calendar']))
							$booking_settings->booking_export = $_POST['booking_add_to_calendar'];													
						else						
							$booking_settings->booking_export = '';
						if(isset($_POST['booking_add_to_email']))
							$booking_settings->booking_attachment = $_POST['booking_add_to_email'];
						else
							$booking_settings->booking_attachment = '';
						$booking_settings->booking_themes = $calendar_theme;
						$booking_settings->booking_global_holidays = $_POST['booking_global_holidays'];
						if(isset($_POST['booking_global_timeslot']))
							$booking_settings->booking_global_timeslot = $_POST['booking_global_timeslot'];
						else
							$booking_settings->booking_global_timeslot = '';
						if(isset($_POST['booking_global_selection']))
							$booking_settings->booking_global_selection = $_POST['booking_global_selection'];
						else
							$booking_settings->booking_global_selection = '';
						$booking_settings = apply_filters( 'bkap_save_global_settings', $booking_settings);
						$woocommerce_booking_settings = json_encode($booking_settings);
							
						update_option('woocommerce_booking_global_settings',$woocommerce_booking_settings);
						//exit;
					}
					?>
								
					<?php if ( isset( $_POST['wapbk_booking_settings_frm'] ) && $_POST['wapbk_booking_settings_frm'] == 'save' ) { ?>
					<div id="message" class="updated fade"><p><strong><?php _e( 'Your settings have been saved.', 'woocommerce-booking' ); ?></strong></p></div>
					<?php } ?>
					
					<?php 
					$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
					?>
					<div id="content">
						  <form method="post" action="" id="booking_settings">
						  	  <input type="hidden" name="wapbk_booking_settings_frm" value="save">
						  	  <input type="hidden" name="wapbk_calendar_theme" id="wapbk_calendar_theme" value="<?php if (isset($saved_settings)) echo $saved_settings->booking_themes;?>">
							  <div id="poststuff">
									<div class="postbox">
										<h3 class="hndle"><?php _e( 'Settings', 'woocommerce-booking' ); ?></h3>
										<div>
										  <table class="form-table">
										  
										  	<tr>
										  		<th>
										  			<label for="booking_language"><b><?php _e('Language:', 'woocommerce-booking'); ?></b></label>
										  		</th>
										  		<td>
										  			<select id="booking_language" name="booking_language">
										  			<?php
										  			$language_selected = "";
										  			if (isset($saved_settings->booking_language))
										  			{
										  				$language_selected = $saved_settings->booking_language;
										  			}
										  			
										  			if ( $language_selected == "" ) $language_selected = "en-GB";
													$languages = book_arrays('languages');
										  			
										  			foreach ( $languages as $key => $value )
										  			{
										  				$sel = "";
										  				if ($key == $language_selected)
										  				{
										  					$sel = " selected ";
										  				}
										  				echo "<option value='$key' $sel>$value</option>";
										  			}
										  			?>
										  			</select>
										  			<img class="help_tip" width="16" height="16" data-tip="<?php _e('Choose the language for your booking calendar.', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
										  		</td>
										  	</tr>
										  	
										  	<tr>
										  		<th>
										  			<label for="booking_date_format"><b><?php _e('Date Format:', 'woocommerce-booking');?></b></label>
										  		</th>
										  		<td>
										  			<select id="booking_date_format" name="booking_date_format">
										  			<?php
										  			if (isset($saved_settings))
										  			{ 
										  				$date_format = $saved_settings->booking_date_format;
										  			}
										  			else
										  			{
										  				$date_format = "";
										  			}
													$date_formats = book_arrays('date_formats');
										  			foreach ($date_formats as $k => $format)
										  			{
										  				printf( "<option %s value='%s'>%s</option>\n",
										  						selected( $k, $date_format, false ),
										  						esc_attr( $k ),
										  						date($format)
										  				);
										  			}
										  			?>
										  			</select>
										  			<img class="help_tip" width="16" height="16" data-tip="<?php _e('The format in which the booking date appears to the customers on the product page once the date is selected', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
										  		</td>
										  	</tr>
										  	
										  	<tr>
										  		<th>
										  			<label for="booking_time_format"><b><?php _e('Time Format:', 'woocommerce-booking');?></b></label>
										  		</th>
										  		<td>
										  			<select id="booking_time_format" name="booking_time_format">
										  			<?php
										  			$time_format = ""; 
										  			if (isset($saved_settings))
										  			{
										  				$time_format = $saved_settings->booking_time_format;
										  			}
													$time_formats = book_arrays('time_formats');
										  			foreach ($time_formats as $k => $format)
										  			{
										  				printf( "<option %s value='%s'>%s</option>\n",
										  						selected( $k, $time_format, false ),
										  						esc_attr( $k ),
										  						$format
										  				);
										  			}
										  			?>
										  			</select>
										  			<img class="help_tip" width="16" height="16" data-tip="<?php _e('The format in which booking time appears to the customers on the product page once the time / time slot is selected', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
										  		</td>
										  	</tr>
										  	
										  	<tr>
										  		<th>
										  			<label for="booking_months"><b><?php _e('Number of months to show in calendar:','woocommerce-booking');?></b></label>
										  		</th>
										  		<td>
										  			<?php 
										  			$no_months_1 = "";
										  			$no_months_2 = "";
										  			if (isset($saved_settings))
										  			{
											  			if ( $saved_settings->booking_months == 1)
											  			{
											  				$no_months_1 = "selected";
											  				$no_months_2 = "";
											  			}
											  			elseif ( $saved_settings->booking_months == 2)
											  			{
											  				$no_months_2 = "selected";
											  				$no_months_1 = "";
											  			}
										  			}
										  			?>
										  			<select id="booking_months" name="booking_months">
										  			<option <?php echo $no_months_1;?> value="1"> 1 </option>
										  			<option <?php echo $no_months_2;?> value="2"> 2 </option>
										  			</select>
										  			<img class="help_tip" width="16" height="16" data-tip="<?php _e('The number of months to be shown on the calendar. If the booking dates spans across 2 months, then dates of 2 months can be shown simultaneously without the need to press Next or Back buttons.', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
										  		</td>
										  	</tr>
										  	<tr>
										  		<th>
										  			<label for="booking_calendar_day"><b><?php _e('First Day on Calendar:', 'woocommerce-booking'); ?></b></label>
										  		</th>
										  		<td>
										  			<select id="booking_calendar_day" name="booking_calendar_day">
										  			<?php
										  			$day_selected = "";
										  			if (isset($saved_settings->booking_calendar_day))
										  			{
										  				$day_selected = $saved_settings->booking_calendar_day;
										  			}
										  			
										  			if ( $day_selected == "" ) $day_selected = get_option('start_of_week');
										  			$days = book_arrays('days');
										  			foreach ( $days as $key => $value )
										  			{
										  				$sel = "";
										  				if ($key == $day_selected)
										  				{
										  					$sel = " selected ";
										  				}
										  				echo "<option value='$key' $sel>$value</option>";
										  			}
										  			?>
										  			</select>
										  			<img class="help_tip" width="16" height="16" data-tip="<?php _e('Choose the language for your booking calendar.', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
										  		</td>
										  	</tr>
										  	<tr>
										  		<th>
										  			<label for="booking_add_to_calendar"><b><?php _e('Show "Add to Calendar" button on Order Received page:', 'woocommerce-booking');?></b></label>
										  		</th>
										  		<td>
										  			<?php
										  			$export_ics = ""; 
									  				if (isset($saved_settings->booking_export) && $saved_settings->booking_export == 'on')
									  				{
									  					$export_ics = 'checked';
									  				}

										  			?>
										  			<input type="checkbox" id="booking_add_to_calendar" name="booking_add_to_calendar" <?php echo $export_ics; ?>/>
										  			<img class="help_tip" width="16" height="16" data-tip="<?php _e('Shows the \'Add to Calendar\' button on the Order Received page. On clicking the button, an ICS file will be downloaded.', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
										  		</td>
										  	</tr>
										  	
										  	<tr>
										  		<th>
										  			<label for="booking_add_to_email"><b><?php _e('Send bookings as attachments (ICS files) in email notifications:', 'woocommerce-booking');?></b></label>
										  		</th>
										  		<td>
										  			<?php
										  			$email_ics = ""; 
									  				if (isset($saved_settings->booking_attachment) && $saved_settings->booking_attachment == 'on')
									  				{
									  					$email_ics = 'checked';
									  				}

										  			?>
										  			<input type="checkbox" id="booking_add_to_email" name="booking_add_to_email" <?php echo $email_ics; ?>/>
										  			<img class="help_tip" width="16" height="16" data-tip="<?php _e('Allow customers to export bookings as ICS file after placing an order. Sends ICS files as attachments in email notifications.', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
										  		</td>
										  	</tr>
											<tr>
										  		<th>
										  			<label for="booking_theme"><b><?php _e('Preview Theme & Language:','woocommerce-booking');?></b></label>
										  		</th>
										  		<td>
													<?php 
										  			$global_holidays = "";
										  			if (isset($saved_settings))
										  			{
											  			if ( $saved_settings->booking_global_holidays != "" )
											  			{
											  				$global_holidays = "addDates: ['".str_replace(",", "','", $saved_settings->booking_global_holidays)."']";
														}
										  			}
										  			?>
										  			
										  			<img style="margin-left:250px;" class="help_tip" width="16" height="16" data-tip="<?php _e('Select the theme for the calendar. You can choose a theme which blends with the design of your website.', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png"/>

										  			<div>
										  	
													<script type="text/javascript">
														
													  jQuery(document).ready(function()
													  {
														  	
															jQuery("#booking_new_switcher").themeswitcher({
														    	onclose: function()
														    	{
														    		var cookie_name = this.cookiename;
														    		jQuery("input#wapbk_calendar_theme").val(jQuery.cookie(cookie_name));
														    	},
														    	imgpath: "<?php echo plugins_url().'/woocommerce-booking/images/';?>",
														    	loadTheme: "smoothness"
														    });
													
															var date = new Date();
															jQuery.datepicker.setDefaults( jQuery.datepicker.regional[ "en-GB" ] );
															jQuery('#booking_switcher').multiDatesPicker({
																dateFormat: "d-m-yy",
																altField: "#booking_global_holidays",
																<?php echo $global_holidays;?>
															});
															
															jQuery(function() {
															
															
															jQuery.datepicker.setDefaults( jQuery.datepicker.regional[ "" ] );
															jQuery( "#booking_switcher" ).datepicker( jQuery.datepicker.regional[ "en-GB" ] );
															jQuery( "#booking_new_switcher" ).datepicker( jQuery.datepicker.regional[ "<?php echo $language_selected;?>" ] );
															jQuery( "#booking_language" ).change(function() {
															jQuery( "#booking_new_switcher" ).datepicker( "option",
															jQuery.datepicker.regional[ jQuery(this).val() ] );
															
															});
															jQuery(".ui-datepicker-inline").css("font-size","1.4em");
														//	jQuery( "#booking_language" ).change(function() {
														//	jQuery( "#booking_switcher" ).datepicker( "option",
														//	jQuery.datepicker.regional[ "en-GB" ] );
															});
														//	});
															
															/*function append_date(date,inst)
															{
																var monthValue = inst.selectedMonth+1;
																var dayValue = inst.selectedDay;
																var yearValue = inst.selectedYear;

																var current_dt = dayValue + "-" + monthValue + "-" + yearValue;

																jQuery('#booking_global_holidays').append(current_dt+",");
															}*/

															//jQuery('#booking_global_holidays').multiDatesPicker();
													  });
													</script>
													
													<div id="booking_new_switcher" name="booking_new_switcher"></div>
										  		</td>
										  	</tr>
										  	
										  	<tr>
										  		<th>
										  			<label for="booking_global_holidays"><b><?php _e('Select Holidays / Exclude Days / Black-out days:', 'woocommerce-booking');?></b></label>
										  		</th>
										  		<td>
										  			<textarea rows="4" cols="80" name="booking_global_holidays" id="booking_global_holidays"></textarea>
										  			<img class="help_tip" width="16" height="16" data-tip="<?php _e('Select dates for which the booking will be completely disabled for all the products in your WooCommerce store.', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" style="vertical-align:top;"/><br>
										  			Please click on the date in calendar to add or delete the date from the holiday list.
													<div id="booking_switcher" name="booking_switcher"></div>
										  		</td>
										  	</tr>
										  	<tr>
												<th>
													<label for="booking_global_timeslot"><b><?php _e('Global Time Slot Booking:', 'woocommerce-booking');?></b></label>
												</th>
											<td>
												<?php
												$global_timeslot = ""; 
												if (isset($saved_settings->booking_global_timeslot) && $saved_settings->booking_global_timeslot == 'on')
												{
													$global_timeslot = "checked";
												}
												?>
												<input type="checkbox" id="booking_global_timeslot" name="booking_global_timeslot" <?php echo $global_timeslot; ?>/>
												<img class="help_tip" width="16" height="16" data-tip="<?php _e('Please select this checkbox if you want ALL time slots to be unavailable for booking in all products once the lockout for that time slot is reached for any product.', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" style="vertical-align:top;"/><br>
											</td>
											</tr>
											
										  	<tr>
										  		<th>
										  			<label for="booking_enable_rounding"><b><?php _e('Enable Rounding of Prices:', 'woocommerce-booking');?></b></label>
										  		</th>
										  		<td>
										  			<?php
										  			$rounding = ""; 
									  				if (isset($saved_settings->enable_rounding) && $saved_settings->enable_rounding == 'on')
									  				{
									  					$rounding = 'checked';
									  				}

										  			?>
										  			<input type="checkbox" id="booking_enable_rounding" name="booking_enable_rounding" <?php echo $rounding; ?>/>
										  			<img class="help_tip" w`idth="16" height="16" data-tip="<?php _e('Rounds the Price to the nearest Integer value.', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
										  		</td>
										  	</tr>
											<tr>
												<th>
													<label for="booking_global_selection"><b><?php _e('Duplicate dates from first product in the cart to other products:', 'woocommerce-booking');?></b></label>
												</th>
											<td>
												<?php
												$global_selection = ""; 
												if (isset($saved_settings->booking_global_selection) && $saved_settings->booking_global_selection == 'on')
												{
													$global_selection = "checked";
												}
												?>
												<input type="checkbox" id="booking_global_selection" name="booking_global_selection" <?php echo $global_selection; ?>/>
												<img class="help_tip" width="16" height="16" data-tip="<?php _e('Please select this checkbox if you want to select the date globally for All products once selected for a product and added to cart.', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" style="vertical-align:top;"/><br>
											</td>
											</tr>
											<?php do_action('bkap_after_global_holiday_field');?>
										  	<tr>
										  		<th>
										  		<input type="submit" name="Submit" class="button-primary" value="<?php _e( 'Save Changes', 'woocommerce-booking' ); ?>" />
										  		</th>
										  	</tr>
										  	
										  </table>
										</div>
									</div>
								</div>
							</form>
					</div>
										  
					<?php 
				}
			}
			
			function my_enqueue_scripts_css() {
			
				if ( get_post_type() == 'product'  || (isset($_GET['page']) && $_GET['page'] == 'woocommerce_booking_page' ) || 
					(isset($_GET['page']) && $_GET['page'] == 'woocommerce_history_page' ) || (isset($_GET['page']) && $_GET['page'] == 'operator_bookings') || (isset($_GET['page']) && $_GET['page'] == 'woocommerce_availability_page'))
				{
					wp_enqueue_style( 'booking', plugins_url('/css/booking.css', __FILE__ ) , '', '', false);
					wp_enqueue_style( 'datepick', plugins_url('/css/jquery.datepick.css', __FILE__ ) , '', '', false);
					
					wp_enqueue_style( 'woocommerce_admin_styles', plugins_url() . '/woocommerce/assets/css/admin.css' );
				
					$calendar_theme = 'base';
					wp_enqueue_style( 'jquery-ui', "http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/$calendar_theme/jquery-ui.css" , '', '', false);
				
					wp_enqueue_style( 'TableTools', plugins_url('/TableTools/media/css/TableTools.css', __FILE__ ) , '', '', false);
				}
				if((isset($_GET['page']) && $_GET['page'] == 'woocommerce_booking_page' ) ||
						(isset($_GET['page']) && $_GET['page'] == 'woocommerce_history_page' ))
				{
					wp_enqueue_style( 'dataTable', plugins_url('/css/data.table.css', __FILE__ ) , '', '', false);
				}
			}
			
			function my_enqueue_scripts_js() {
				
				if ( get_post_type() == 'product'  || (isset ($_GET['page']) && $_GET['page'] == 'woocommerce_booking_page') || (isset ($_GET['page']) && $_GET['page'] == 'woocommerce_availability_page') )
				{
					wp_enqueue_script( 'jquery' );
					
					wp_deregister_script( 'jqueryui');
					wp_enqueue_script( 'jqueryui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js', '', '', false );
					
					wp_enqueue_script( 'jquery-ui-datepicker' );
					
					wp_register_script( 'multiDatepicker', plugins_url().'/woocommerce-booking/js/jquery-ui.multidatespicker.js');
					wp_enqueue_script( 'multiDatepicker' );
					
					wp_register_script( 'datepick', plugins_url().'/woocommerce-booking/js/jquery.datepick.js');
					wp_enqueue_script( 'datepick' );
					
					$current_language = json_decode(get_option('woocommerce_booking_global_settings'));
					if (isset($current_language))
					{
						$curr_lang = $current_language->booking_language;
					}
					else
					{
						$curr_lang = "";
					}
					if ( $curr_lang == "" ) $curr_lang = "en-GB";
					
				}
				
				// below files are only to be included on booking settings page
				if (isset($_GET['page']) && $_GET['page'] == 'woocommerce_booking_page')
				{
					wp_register_script( 'woocommerce_admin', plugins_url() . '/woocommerce/assets/js/admin/woocommerce_admin.js', array('jquery', 'jquery-ui-widget', 'jquery-ui-core'));
					wp_enqueue_script( 'woocommerce_admin' );
					wp_enqueue_script( 'themeswitcher', plugins_url('/js/jquery.themeswitcher.min.js', __FILE__), '', '', false );
					wp_enqueue_script("lang", plugins_url("/js/i18n/jquery-ui-i18n.js", __FILE__), '', '', false);
					
					wp_enqueue_script(
							'jquery-tip',
							plugins_url('/js/jquery.tipTip.minified.js', __FILE__),
							'',
							'',
							false
					);
				}
				
				if (isset($_GET['page']) && $_GET['page'] == 'woocommerce_history_page' || (isset($_GET['page']) && $_GET['page'] == 'operator_bookings'))
				{
					wp_register_script( 'dataTable', plugins_url().'/woocommerce-booking/js/jquery.dataTables.js');
					wp_enqueue_script( 'dataTable' );
					
					wp_register_script( 'TableTools', plugins_url().'/woocommerce-booking/TableTools/media/js/TableTools.js');
					wp_enqueue_script( 'TableTools' );
						
					wp_register_script( 'ZeroClip', plugins_url().'/woocommerce-booking/TableTools/media/js/ZeroClipboard.js');
					wp_enqueue_script( 'ZeroClip' );
					
					/*wp_register_script( 'woocommerce_admin', plugins_url() . '/woocommerce/assets/js/admin/woocommerce_admin.js', array('jquery', 'jquery-ui-widget', 'jquery-ui-core'));
					wp_enqueue_script( 'woocommerce_admin' );*/
				}
			}
			
			function front_side_scripts_js() {
			
				if( is_product() || is_page())
				{
					wp_enqueue_script(
							'initialize-datepicker.js',
							plugins_url('/js/initialize-datepicker.js', __FILE__),
							'',
							'',
							false
					);
					wp_enqueue_script( 'jquery' );
					
					wp_enqueue_script( 'jquery-ui-datepicker' );
				//	wp_deregister_script( 'jquery-ui');
				//	wp_enqueue_script( 'jquery-ui-js','http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.23/jquery-ui.min.js', '', '', false );
					
					if(isset($_GET['lang']) && $_GET['lang'] != '' && $_GET['lang'] != null)
					{
						$curr_lang = $_GET['lang'];
					}
					else
					{
						$current_language = json_decode(get_option('woocommerce_booking_global_settings'));
						if (isset($current_language))
						{
							$curr_lang = $current_language->booking_language;
						}
						else
						{
							$curr_lang = "";
						}
						if ( $curr_lang == "" ) $curr_lang = "en-GB";
					}
					wp_enqueue_script("$curr_lang", plugins_url("/js/i18n/jquery.ui.datepicker-$curr_lang.js", __FILE__), '', '', false);
				}
			}
			
			function front_side_scripts_css() {
					
				$calendar_theme = json_decode(get_option('woocommerce_booking_global_settings'));
				$calendar_theme_sel = "";
				if (isset($calendar_theme))
				{
					$calendar_theme_sel = $calendar_theme->booking_themes;
				}
				if ( $calendar_theme_sel == "" ) $calendar_theme_sel = 'smoothness';
				//wp_enqueue_style( 'jquery-ui', "http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/$calendar_theme_sel/jquery-ui.css" , '', '', false);
				wp_enqueue_style( 'jquery-ui', plugins_url('/css/themes/'.$calendar_theme_sel.'/jquery-ui.css', __FILE__ ) , '', '', false);
				wp_enqueue_style( 'booking', plugins_url('/css/booking.css', __FILE__ ) , '', '', false);
			}
			
			function booking_box() {
				
				add_meta_box( 'woocommerce-booking', __('Booking', 'woocommerce-booking'), array(&$this, 'meta_box'),'product');
			}
			
			function meta_box() {
				
				?>
				<script type="text/javascript">

				// On Radio Button Selection
				jQuery(document).ready(function(){
			/*		jQuery( "input[name='booking_method_select']" ).change(function() {
						if ( jQuery( "input[name='booking_method_select']:checked" ).val() == "booking_specific_booking" )
						{
							jQuery( "#selective_booking" ).show();
							jQuery( "#booking_enable_weekday" ).hide();
						}
						else if ( jQuery( "input[name='booking_method_select']:checked" ).val() == "booking_recurring_booking" )
						{
							jQuery( "#selective_booking" ).hide();
							jQuery( "#booking_enable_weekday" ).show();
						}
							
						
					}); */ 

					jQuery("table#list_bookings_specific a.remove_time_data, table#list_bookings_recurring a.remove_time_data").click(function()
							{
								//alert('hello there');
								var y=confirm('Are you sure you want to delete this time slot?');
								if(y==true)
								{
									var passed_id = this.id;
									var exploded_id = passed_id.split('&');
									var data = {
											details: passed_id,
											action: 'remove_time_slot'
									};

									jQuery.post('<?php echo get_admin_url();?>/admin-ajax.php', data, function(response)
									{
										//alert('Got this from the server: ' + response);
										jQuery("#row_" + exploded_id[0] + "_" + exploded_id[2] ).hide();
									});
								}
								
					});

					jQuery("table#list_bookings_specific a.remove_day_data, table#list_bookings_recurring a.remove_day_data").click(function()
							{
							//	alert('hello there');
								var y=confirm('Are you sure you want to delete this day?');
								if(y==true)
								{
									var passed_id = this.id;
									var exploded_id = passed_id.split('&');
									var data = {
											details: passed_id,
											action: 'remove_day'
									};
									//alert('hello there');
									jQuery.post('<?php echo get_admin_url();?>/admin-ajax.php', data, function(response)
											{
												//alert('Got this from the server: ' + response);
												jQuery("#row_" + exploded_id[0]).hide();
											});
								
								}
							});

					jQuery("table#list_bookings_specific a.remove_specific_data").click(function()
							{
							//	alert('hello there');
								var y=confirm('Are you sure you want to delete all the specific date records?');
								if(y==true)
								{
									var passed_id = this.id;
								//	alert(passed_id);
								//	var exploded_id = passed_id.split('&');
									var data = {
											details: passed_id,
											action: 'remove_specific'
									};
								//	alert('hello there');
									jQuery.post('<?php echo get_admin_url();?>/admin-ajax.php', data, function(response)
											{
												//alert('Got this from the server: ' + response);
												jQuery("table#list_bookings_specific").hide();
											});
								}
							});
					
					jQuery("table#list_bookings_recurring a.remove_recurring_data").click(function()
							{
							//	alert('hello there');
								var y=confirm('Are you sure you want to delete all the recurring weekday records?');
								if(y==true)
								{
									var passed_id = this.id;
								//	var exploded_id = passed_id.split('&');
									var data = {
											details: passed_id,
											action: 'remove_recurring'
									};
								//	alert('hello there');
									jQuery.post('<?php echo get_admin_url();?>/admin-ajax.php', data, function(response)
											{
												//alert('Got this from the server: ' + response);
												jQuery("table#list_bookings_recurring").hide();
											}); 
								}
							});
					
					jQuery("#booking_enable_multiple_day").change(function() {
						if(jQuery('#booking_enable_multiple_day').attr('checked'))
						{
							jQuery('#booking_method').hide();
							jQuery('#booking_time').hide();
							jQuery('#booking_enable_weekday').hide();
							jQuery('#selective_booking').hide();
							jQuery('#purchase_without_date').hide();
						}
						else 
						{
							jQuery('#booking_method').show();
							jQuery('#inline_calender').show();
							jQuery('#booking_time').show();
							jQuery('#booking_enable_weekday').show();
							jQuery('#selective_booking').show();
							jQuery('#purchase_without_date').show();
						}
					});
				});
				
				function add_new_div(id){

					var exploded_id = id.split('[');
					var new_var = parseInt(exploded_id[1]) + parseInt(1);
					var new_html_var = jQuery('#time_slot_empty').html();
					var re = new RegExp('\\[0\\]',"g");
					new_html_var = new_html_var.replace(re, "["+new_var+"]");
					
					jQuery("#time_slot").append(new_html_var);
					jQuery('#add_another').attr("onclick","add_new_div('["+new_var+"]')");
				}

				function tabs_display(id){

					if( id == "addnew" )
					{
					//	jQuery( "#reminder_wrapper" ).hide();
						jQuery( "#date_time" ).show();
						jQuery( "#listing_page" ).hide();
						jQuery( "#payments_page" ).hide();
						jQuery( "#tours_page" ).hide();
						jQuery( "#rental_page" ).hide();
						jQuery( "#seasonal_pricing" ).hide();
						jQuery( "#block_booking_price_page" ).hide();
						jQuery( "#block_booking_page").hide();
						jQuery( "#addnew" ).attr("class","nav-tab nav-tab-active");
					//	jQuery( "#reminder" ).attr("class","nav-tab");
						jQuery( "#list" ).attr("class","nav-tab");
						jQuery( "#rental" ).attr("class","nav-tab");
						jQuery( "#tours" ).attr("class","nav-tab");
						jQuery( "#seasonalpricing" ).attr("class","nav-tab");
						jQuery( "#payments" ).attr("class","nav-tab");
						jQuery( "#block_booking_price" ).attr("class","nav-tab");
						jQuery( "#block_booking" ).attr("class","nav-tab");
					}
					else if( id == "list" )
					{
				//		jQuery( "#reminder_wrapper" ).hide();
						jQuery( "#date_time" ).hide();
						jQuery( "#rental_page" ).hide();
						jQuery( "#seasonal_pricing" ).hide();
						jQuery( "#payments_page" ).hide();
						jQuery( "#tours_page" ).hide();
						jQuery( "#listing_page" ).show();
						jQuery( "#block_booking_price_page" ).hide();
						jQuery( "#block_booking_page").hide();
						jQuery( "#list" ).attr("class","nav-tab nav-tab-active");
						jQuery( "#addnew" ).attr("class","nav-tab");
					//	jQuery( "#reminder" ).attr("class","nav-tab");
						jQuery( "#rental" ).attr("class","nav-tab");
						jQuery( "#tours" ).attr("class","nav-tab");
						jQuery( "#seasonalpricing" ).attr("class","nav-tab");
						jQuery( "#payments" ).attr("class","nav-tab");
						jQuery( "#block_booking_price" ).attr("class","nav-tab");
						jQuery( "#block_booking" ).attr("class","nav-tab");
					}
				/*	else if( (id == "reminder") | (id == "reminder_manage_link") | (id == "reminder_view_link") | (id == "reminder_update_link") )
					{
						jQuery( "#reminder_wrapper" ).show();
						jQuery( "#date_time" ).hide();
						jQuery( "#rental_page" ).hide();
						jQuery( "#seasonal_pricing" ).hide();
						jQuery( "#payments_page" ).hide();
						jQuery( "#tours_page" ).hide();
						jQuery( "#listing_page" ).hide();
						jQuery( "#list" ).attr("class","nav-tab");
						jQuery( "#addnew" ).attr("class","nav-tab");
						jQuery( "#reminder" ).attr("class","nav-tab nav-tab-active");
						jQuery( "#rental" ).attr("class","nav-tab");
						jQuery( "#tours" ).attr("class","nav-tab");
						jQuery( "#seasonalpricing" ).attr("class","nav-tab");
						jQuery( "#payments" ).attr("class","nav-tab");
						
						if((id == "reminder_manage_link") | (id == "reminder_update_link"))
						{
							jQuery( "#reminder_manage" ).show();
							jQuery( "#reminder_view" ).hide();
						}
						else if(id == "reminder_view_link")
						{
							jQuery( "#reminder_view" ).show();
							jQuery( "#reminder_manage" ).hide();
						}					
					}*/
				}

				</script>
				
	<!-- 	 	<form id="booking_form" method="post" action="">  -->
				<h1 class="nav-tab-wrapper woo-nav-tab-wrapper">
				<a href="javascript:void(0);" class="nav-tab nav-tab-active" id="addnew" onclick="tabs_display('addnew')"> <?php _e( 'Booking Options', 'woocommerce-booking' );?> </a>
				<a href="javascript:void(0);" class="nav-tab " id="list" onclick="tabs_display('list')"> <?php _e( 'View/Delete Booking Dates, Time Slots', 'woocommerce-booking' );?> </a>
		<!-- 	<a href="javascript:void(0);" class="nav-tab " id="reminder" onclick="tabs_display('reminder')"> <?php _e( 'Email Reminder', 'woocommerce-booking' );?> </a> -->
				</h1>
				
		<!-- 	<div id="reminder_wrapper" style="display:none;" >                       



<div id="obj_wrapper">

</div>
<input type="button" onClick="diplicate_obj('','','0','0','0','')" value="Add New Reminder" />
<input type="hidden" id="diplicate_obj_count" value="0"/>                       
       
<script type="text/javascript">
function diplicate_obj (subject, message, days, hours, minutes, email) {
	
	var diplicate_obj_count = document.getElementById("diplicate_obj_count");
	var count = parseInt(diplicate_obj_count.value);	
	var obj_wrapper_name = "obj_wrapper";
	
	if(count != 0){
		obj_wrapper_name = "obj_wrapper_"+(count-1);
	}
	
	var obj_wrapper = document.getElementById(obj_wrapper_name);	
		
	var obj = "";
	//alert(message);
	obj += "<table id='tbl_"+obj_wrapper_name+"' class='wp-list-table widefat fixed posts email_remind_table' cellspacing='0' >";
	obj += "<tr><td colspan='2' align='right'><a href='#all_reminders' onclick='javascript:hideReminders(\"tbl_"+obj_wrapper_name+"\")'>&#10006; Close</a></td></tr>"	
	obj += "<tr>";
	obj += "<td width='30%'>Subject</td>";
	obj += "<td width='70%'>";
	obj += "<input type='text' style='width: 400px;' name='erem_subject["+count+"]' value='"+subject+"' />";
	obj += "<img class='help_tip' width='16' height='16' data-tip='<?php _e('Subject', 'woocommerce-booking');?>' src='<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png' />";
	obj += "</td>";
	obj += "</tr>";
	obj += "<tr>";
	obj += "<td>Message<br/><i>Available short codes<br/>First Name = [first_name]<br/>Last Name = [last_name]<br/>Booking Date = [date]<br/>Booking Time = [time]<br/>Shop Name = [shop_name]<br/>Shop URL = [shop_url]<br/>Service = [service]<br/>Order Number = [order_number]</i><br/></td>";
	obj += "<td>";
	obj += "<textarea rows='15' style='width: 100%;' name='erem_message["+count+"]' >"+message+"</textarea>";
	obj += "<img class='help_tip' width='16' height='16' data-tip='<?php _e('Message', 'woocommerce-booking');?>' src='<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png' />";
	obj += "</td>";
	obj += "</tr>";
	obj += "<tr>";
	obj += "<td>Time slot</td>";
	obj += "<td>";
	obj += "<select name='erem_days["+count+"]'>";
	obj += "<option value='0'>-Days-</option>";
	<?php
	for($x = 1; $x < 365 ; $x++){		
		echo("if(days == ".$x."){");
		echo("obj += \"<option value='".$x."' selected='selected'>".$x."</option>\";");
		echo("}else{");
		echo("obj += \"<option value='".$x."'>".$x."</option>\";");
		echo("}");					
	}
	?>	
	

									  
	obj += "</select>";
	obj += "<select name='erem_hours["+count+"]'>";
	obj += "<option value='0'>-Hours-</option>";
	<?php
	for($x = 1; $x < 24 ; $x++){
		echo("if(hours == ".$x."){");
		echo("obj += \"<option value='".$x."' selected='selected'>".$x."</option>\";");
		echo("}else{");
		echo("obj += \"<option value='".$x."'>".$x."</option>\";");
		echo("}");
	}
	?>						  
	obj += "</select>";
	obj += "<select name='erem_minutes["+count+"]'>";
	obj += "<option value='0'>-Minutes-</option>";
	<?php
	for($x = 1; $x < 60 ; $x++){
		echo("if(minutes == ".$x."){");
		echo("obj += \"<option value='".$x."' selected='selected'>".$x."</option>\";");
		echo("}else{");
		echo("obj += \"<option value='".$x."'>".$x."</option>\";");
		echo("}");	
	}
	?>						
	obj += "</select>";
	obj += "<img class='help_tip' width='16' height='16' data-tip='<?php _e('Time slot', 'woocommerce-booking');?>' src='<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png' />";
	obj += "</tr>";
	obj += "</tr>";
	obj += "<tr>";
	obj += "<td>Extra email address to send a copy to (separated by comma)</td>";
	obj += "<td>";
	obj += "<input type='text' style='width: 400px;' name='erem_email["+count+"]' value='"+email+"' />";
	obj += "<img class='help_tip' width='16' height='16' data-tip='<?php _e('Extra email address to send a copy to (separated by comma)', 'woocommerce-booking');?>' src='<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png' />";
	obj += "</td>";
	obj += "</tr>";	
	obj += "</table>";
	obj += "<p> </p>";
	obj += "<div id='obj_wrapper_"+count+"'></div>";	
				
	obj_wrapper.innerHTML = obj_wrapper.innerHTML+obj+"<p> </p>";	
	diplicate_obj_count.value = count+1;		
}


function showReminders (obj){
	//document.getElementsByClassName('.email_remind_table').style.backgroundColor	
	var obj1 = document.getElementById(obj);
	obj1.style.display = "block";
}
function hideReminders (obj){
	var obj1 = document.getElementById(obj);
	obj1.style.display = "none";
}

function deleteReminders (obj1, obj2){
	
	var r = confirm("Are you sure you want to delete this ?");
	if (r == true){
		document.getElementById(obj1).remove();
		document.getElementById(obj2).remove();
	}
}


</script>    

<?php
global $post;
$all_reminders = get_post_meta($post->ID, 'woocommerce_booking_emailreminders', true);
//echo $all_reminders;exit;
if(isset($all_reminders))
{
	$all_reminders = array_values(json_decode($all_reminders,true));
	
	?>
<p>&nbsp;</p>
All Reminders
<table class="form-table" width="95%" id="all_reminders">
    <tr>
        <th width="10">#</th>
        <th>Subject</th>
        <th width="100">Time slot</th>
        <th width="100">View/Update</th>
        <th width="50">Delete</th>
    </tr>	
	<?php
	$count = 0;
	foreach($all_reminders as $reminder){			
		$wrapper_id = "";
		echo ("<script type='text/javascript'>");
		
		if($count == "0"){
			$wrapper_id = "obj_wrapper";
			
		}else{
			$wrapper_id = "obj_wrapper_".($count-1) ;
		}
		
		$reminder_message = $reminder["message"];
		//echo $reminder["message"];exit;
		echo ("diplicate_obj('".$reminder["subject"]."',
		'".$reminder_message."' ,
		'".$reminder["days"]."' ,
		'".$reminder["hours"]."' ,
		'".$reminder["minutes"]."' ,
		'".$reminder["email"]."');");
		
		echo ("var obj_wrapper = document.getElementById('tbl_".$wrapper_id."');");
		echo ("obj_wrapper.style.display = 'none';");
		
		echo ("</script>");
		
		?>
    <tr id="email_reminder_view_row_<?php echo $count; ?>">
        <td width="10"  valign="top"><?php echo($count + 1); ?></td>
        <td  valign="top"><?php echo $reminder["subject"]; ?></td>
        <td width="100"><?php echo $reminder["days"]; ?> Days <br/> <?php echo $reminder["hours"]; ?> Hours <br/> <?php echo $reminder["minutes"]; ?> Minutes</td>        
        <td  valign="top">
        <a href="#<?php echo $wrapper_id; ?>" onclick="javascript:showReminders('tbl_<?php echo $wrapper_id; ?>');"> <?php _e( 'Edit', 'woocommerce-booking' );?> </a>
        </td>
        <td valign="top"><a href="#all_reminders" onclick="javascript:deleteReminders('email_reminder_view_row_<?php echo $count; ?>','tbl_<?php echo $wrapper_id; ?>');">Delete</a></td>
    </tr>		
		<?php
		$count++;
	}
?>
</table> 
<?php
}
?>
                </div> -->
                
				<div id="date_time">
				<table class="form-table">
				<?php 
				global $post, $wpdb;
				$duplicate_of = get_post_meta($post->ID, '_icl_lang_duplicate_of', true);
				if($duplicate_of == '' && $duplicate_of == null)
				{
					$post_time = get_post($post->ID);
					$id_query = "SELECT ID FROM `".$wpdb->prefix."posts` WHERE post_date = '".$post_time->post_date."' ORDER BY ID LIMIT 1";
					$results_post_id = $wpdb->get_results ( $id_query );
					if( isset($results_post_id) ) {
						$duplicate_of = $results_post_id[0]->ID;
					}
					else
					{
						$duplicate_of = $post->ID;
					}
					//$duplicate_of = $item_value['product_id'];
				}
				do_action('bkap_before_enable_booking', $duplicate_of);
				$booking_settings = get_post_meta($duplicate_of, 'woocommerce_booking_settings', true);
				$add_button_show = 'none';
				$enable_time_checked = '';
				if (isset($booking_settings['booking_enable_time']) && $booking_settings['booking_enable_time'] == 'on')
				{
					$add_button_show = 'block';
					$enable_time_checked = ' checked ';
				}
					
				?>
					<tr>
					<th>
					<label for="booking_enable_date"  style="color: brown"> <b> <?php _e( 'Enable Booking Date:', 'woocommerce-booking' );?> </b> </label>
					</th>
					<td>
					<?php 
					$enable_date = '';
					if( isset($booking_settings['booking_enable_date']) && $booking_settings['booking_enable_date'] == 'on' )
					{
						$enable_date = 'checked';
					}
					?>
					<input type="checkbox" id="booking_enable_date" name="booking_enable_date" <?php echo $enable_date;?> >
					<img class="help_tip" width="16" height="16" data-tip="<?php _e('Enable Booking Date on Products Page', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
					</td>
				</tr>
				<?php 
				do_action('bkap_before_enable_multiple_days', $duplicate_of);
				?>
				<tr>
					<th>
					<label for="booking_enable_multiple_day"  style="color: brown"> <b> <?php _e( 'Allow multiple day booking:', 'woocommerce-booking' );?> </b> </label>
					</th>
					<td>
					<?php 
					$enable_multiple_day = '';
					$booking_method_div = $booking_time_div = 'table-row';
					$purchase_without_date = 'show';
					if( isset($booking_settings['booking_enable_multiple_day']) && $booking_settings['booking_enable_multiple_day'] == 'on' )
					{
						$enable_multiple_day = 'checked';
						$booking_method_div = 'none';
						$booking_time_div = 'none';
						$purchase_without_date = 'none';
					}
					?>
					<input type="checkbox" id="booking_enable_multiple_day" name="booking_enable_multiple_day" <?php echo $enable_multiple_day;?> >
					<img class="help_tip" width="16" height="16" data-tip="<?php _e('Enable Multiple day Bookings on Products Page', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
					</td>
				</tr>
				
				<?php /* if(!isset($booking_settings['booking_enable_multiple_day']) || $booking_settings['booking_enable_multiple_day'] != 'on')
					$show = "show";
					else
					$show = "none"; */?>
					<tr id="inline_calender" style="display:show">
					<th>
					<label for="enable_inline_calendar"> <b> <?php _e( 'Enable Inline Calendar:', 'woocommerce-booking' );?> </b> </label>
					</th>
					<td>
					<?php 
					$enable_inline_calendar = '';
					if( isset($booking_settings['enable_inline_calendar']) && $booking_settings['enable_inline_calendar'] == 'on' )
					{
						$enable_inline_calendar= 'checked';
					}
					?>
					<input type="checkbox" id="enable_inline_calendar" name="enable_inline_calendar" <?php echo $enable_inline_calendar;?> >
					<img class="help_tip" width="16" height="16" data-tip="<?php _e('Enable Inline Calendar on Products Page', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
					</td>
				</tr>
				<?php do_action('bkap_before_booking_method_select', $duplicate_of);
				?>
				<tr id="booking_method" style="display:<?php echo $booking_method_div;?>;">
					<th>
					<label for="booking_method_select"> <b> <?php _e( 'Select Booking Method(s):', 'woocommerce-booking');?></b></label>
					</th>
					<td>
					<?php 
					$specific_booking_chk = '';
					$recurring_div_show = $specific_dates_div_show = 'none';
					if( (isset($booking_settings['booking_specific_booking']) && $booking_settings['booking_specific_booking'] == 'on') && $booking_settings['booking_enable_multiple_day'] != 'on' )
					{
						$specific_booking_chk = 'checked';
						$specific_dates_div_show = 'block';
					}
					$recurring_booking = '';
					if( (isset($booking_settings['booking_recurring_booking']) && $booking_settings['booking_recurring_booking'] == 'on') && $booking_settings['booking_enable_multiple_day'] != 'on' )
					{
						$recurring_booking = 'checked';
						$recurring_div_show = 'block';
					}
					?>
					<b>Current Booking Method: </b>
					<?php 
					if ($specific_booking_chk != 'checked' && $recurring_booking != 'checked') echo "None";
					if ($specific_booking_chk == 'checked' && $recurring_booking == 'checked') echo "Specific Dates, Recurring Weekdays";
					if ($specific_booking_chk == 'checked' && $recurring_booking != 'checked') echo "Specific Dates";
					if ($specific_booking_chk != 'checked' && $recurring_booking == 'checked') echo "Recurring Weekdays";
					?> 
					<br>
					<input type="checkbox" name="booking_specific_booking" id="booking_specific_booking" onClick="book_method(this)" <?php echo $specific_booking_chk; ?>> <b> <?php _e('Specific Dates', 'woocommerce-booking');?> </b></input>
					<img style="margin-left:40px;"  class="help_tip" width="16" height="16" data-tip="<?php _e('Please enable/disable the specific booking dates and recurring weekdays using these checkboxes. Upon checking them, you shall be able to further select dates or weekdays.', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" /><br>
					<input type="checkbox" name="booking_recurring_booking" id="booking_recurring_booking" onClick="book_method(this)" <?php echo $recurring_booking; ?> > <b> <?php _e('Recurring Weekdays', 'woocommerce-booking');?> </b></input><br>
					<i>Details of current weekdays and specific dates are available in the second tab.</i>
					
					</td>
				</tr>
				</table>
				
							<script type="text/javascript">
								function book_method(chk)
								{
									if ( jQuery( "input[name='booking_specific_booking']").attr("checked"))
									{
										document.getElementById("selective_booking").style.display = "block";
										document.getElementById("booking_enable_weekday").style.display = "none";
									}
									if (jQuery( "input[name='booking_recurring_booking']").attr("checked"))
									{
										document.getElementById("booking_enable_weekday").style.display = "block";
										document.getElementById("selective_booking").style.display = "none";
									}
									if ( jQuery( "input[name='booking_specific_booking']").attr("checked") && jQuery( "input[name='booking_recurring_booking']").attr("checked"))
									{
										document.getElementById("booking_enable_weekday").style.display = "block";
										document.getElementById("selective_booking").style.display = "block";
									}
									if ( !jQuery( "input[name='booking_specific_booking']").attr("checked") && !jQuery( "input[name='booking_recurring_booking']").attr("checked"))
									{
										document.getElementById("booking_enable_weekday").style.display = "none";
										document.getElementById("selective_booking").style.display = "none";
									}
								}
								</script>

				<div id="booking_enable_weekday" name="booking_enable_weekday" style="display:<?php echo $recurring_div_show; ?>;">
				<table class="form-table">
				<tr>
					<th>
					<label for="booking_enable_weekday_dates"> <b> <?php _e( 'Booking Days:', 'woocommerce-booking' );?> </b> </label>
					</th>
					<td>
					<fieldset class="days-fieldset">
							<legend><b>Days:</b></legend>
							<?php 
							$weekdays = book_arrays('weekdays');
							foreach ( $weekdays as $n => $day_name)
							{
								print('<input type="checkbox" name="'.$n.'" id="'.$n.'" />
								<label for="'.$day_name.'">'.$day_name.'</label>
								<br>');
							}?>
							</fieldset>
					<img class="help_tip" width="16" height="16" data-tip="<?php _e('Select Weekdays', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
					</td>
				</tr>
				</table>
				</div>
				
				<div id="selective_booking" name="selective_booking" style="display:<?php echo $specific_dates_div_show; ?>;">
				<table class="form-table">
				<script type="text/javascript">
							jQuery(document).ready(function()
							{
							var formats = ["d.m.y", "d-m-yyyy","MM d, yy"];
							jQuery("#booking_specific_date_booking").datepick({dateFormat: formats[1], multiSelect: 999, monthsToShow: 1, showTrigger: '#calImg'});
							});
				</script>
				<tr>
					<th>
					<label for="booking_specific_date_booking"><b><?php _e( 'Specific Date Booking:', 'woocommerce-booking');?></b></label>
					</th>
					<td>
					<textarea rows="4" cols="80" name="booking_specific_date_booking" id="booking_specific_date_booking"></textarea>
					<img class="help_tip" width="16" height="16" data-tip="<?php _e('Select the specific dates that you want to enable for booking', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" style="vertical-align:top;"/>
					</td>
				</tr>
				</table>
				</div>
				
				<table class="form-table">
				<tr>
					<th>
					<label for="booking_lockout_date"><b><?php _e( 'Lockout Date after X orders:', 'woocommerce-booking');?></b></label>
					</th>
					<td>
					<?php 
					$lockout_date = "";
					if ( isset($booking_settings['booking_date_lockout']) && $booking_settings['booking_date_lockout'] != "" )
					{
						$lockout_date = $booking_settings['booking_date_lockout'];
					}
					else
					{
						$lockout_date = "60";
					}
					?>
					<input type="text" name="booking_lockout_date" id="booking_lockout_date" value="<?php echo $lockout_date;?>" >
					<img class="help_tip" width="16" height="16" data-tip="<?php _e('Set this field if you want to place a limit on maximum bookings on any given date. If you can manage up to 15 bookings in a day, set this value to 15. Once 15 orders have been booked, then that date will not be available for further bookings.', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
					</td>
				</tr>
				<?php 
				do_action('bkap_before_minimum_days', $duplicate_of);?>
				<tr>
					<th>
					<label for="booking_minimum_number_days"><b><?php _e( 'Minimum Booking time (in days):', 'woocommerce-booking');?></b></label>
					</th>
					<td>
					<?php 
					$min_days = 0;
					if ( isset($booking_settings['booking_minimum_number_days']) && $booking_settings['booking_minimum_number_days'] != "" )
					{
						$min_days = $booking_settings['booking_minimum_number_days'];
					}
					?>
					<input type="text" name="booking_minimum_number_days" id="booking_minimum_number_days" value="<?php echo $min_days;?>" >
					<img class="help_tip" width="16" height="16" data-tip="<?php _e('Enable Booking after X number of days from current date. The customer can select a booking date that is available only after the minimum days that are entered here. For example, if you need 3 days advance notice for a booking, enter 3 here.', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
					</td>
				</tr>
				<?php 
				do_action('bkap_before_number_of_dates', $duplicate_of);
				?>
				<tr>
					<th>
					<label for="booking_maximum_number_days"><b><?php _e( 'Number of Dates to choose:', 'woocommerce-booking');?></b></label>
					</th>
					<td>
					<?php 
					$max_date = "";
					if ( isset($booking_settings['booking_maximum_number_days']) && $booking_settings['booking_maximum_number_days'] != "" )
					{
						$max_date = $booking_settings['booking_maximum_number_days'];
					}
					else
					{
						$max_date = "30";
					}		
					?>
					<input type="text" name="booking_maximum_number_days" id="booking_maximum_number_days" value="<?php echo $max_date;?>" >
					<img class="help_tip" width="16" height="16" data-tip="<?php _e('The maximum number of booking dates you want to be available for your customers to choose from. For example, if you take only 2 months booking in advance, enter 60 here.', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
					</td>
				</tr>
				<?php 
				do_action('bkap_before_purchase_without_date', $duplicate_of);
				?>
				<tr id="purchase_without_date" style="display:<?php echo $purchase_without_date?>;">
					<th>
					<label for="booking_purchase_without_date"><b><?php _e( 'Purchase without choosing a date:', 'woocommerce-booking');?></b></label>
					</th>
					<td>
					<?php 
					$date_show = '';
					if( isset($booking_settings['booking_purchase_without_date']) && $booking_settings['booking_purchase_without_date'] == 'on' )
					{
						$without_date = 'checked';
					}
					else
					{
						$without_date = '';
					}
					?>
					<input type="checkbox" name="booking_purchase_without_date" id="booking_purchase_without_date" <?php echo $without_date; ?>> 
					<img style="margin-left:40px;"  class="help_tip" width="16" height="16" data-tip="<?php _e('Enables your customers to purchase without choosing a date. This is useful in cases where the customer wants to gift the item.');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" /><br>
					<i>Useful if you want your customers to be able to purchase the item without choosing the date or as a Gift. Select this option if you want the ADD TO CART button always visible on the product page.</i>
					</td>
				</tr>
				<?php 
				do_action('bkap_before_product_holidays', $duplicate_of);
				?>				
				<script type="text/javascript">
							jQuery(document).ready(function()
							{
							var formats = ["d.m.y", "d-m-yyyy","MM d, yy"];
							jQuery("#booking_product_holiday").datepick({dateFormat: formats[1], multiSelect: 999, monthsToShow: 1, showTrigger: '#calImg'});
							});
				</script>
				<tr>
					<th>
					<label for="booking_product_holiday"><b><?php _e( 'Select Holidays / Exclude Days / Black-out days:', 'woocommerce-booking');?></b></label>
					</th>
					<td>
					<?php 
					$product_holiday = "";
					if ( isset($booking_settings['booking_product_holiday']) && $booking_settings['booking_product_holiday'] != "" )
					{
						$product_holiday = $booking_settings['booking_product_holiday'];
					}
					?>
					<textarea rows="4" cols="80" name="booking_product_holiday" id="booking_product_holiday"><?php echo $product_holiday; ?></textarea>
					<img class="help_tip" width="16" height="16" data-tip="<?php _e('Select dates for which the booking will be completely disabled only for this product.', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" style="vertical-align:top;"/><br>
					<i>Please click on the date in calendar to add or delete the date from the holiday list.</i>
					</td>
				</tr>
				<?php 
				do_action('bkap_before_enable_time', $duplicate_of);
				?>
				<tr id="booking_time" style="display:<?php echo $booking_time_div; ?>;">
					<th>
					<label for="booking_enable_time" style="color: brown"><b><?php _e( 'Enable Booking Time:', 'woocommerce-booking');?></b></label>
					</th>
					<td>
					<?php 
					$enable_time = "";
					if( isset($booking_settings['booking_enable_time']) && $booking_settings['booking_enable_time'] == "on" )
					{
						$enable_time = "checked";
					}
					?>
					<input type="checkbox" name="booking_enable_time" id="booking_enable_time" <?php echo $enable_time;?> onClick="timeslot(this)">
					<img class="help_tip" width="16" height="16" data-tip="<?php _e('Enable time (or time slots) on the product. Add any number of booking time slots once you have checked this.', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" /><br>
					<i><font color="brown">Please select the checkbox to add Time Slots<br>
					You can manage the Time Slots using the "View/Delete Booking Dates, Time Slots" tab shown above, next to "Booking Options".</i></font>
					</td>
				</tr>
				<?php
					do_action('bkap_after_time_enabled',$duplicate_of);
				?>
				</table>
				<script type="text/javascript">
					function timeslot(chk)
					{
						jQuery("#add_button").toggle();
						if ( !jQuery( "input[name='booking_enable_time']").attr("checked"))
					    {
							document.getElementById("time_slot").style.display = "none";
					    }
						if( jQuery( "input[name='booking_enable_time']").attr("checked"))
						{
							document.getElementById("time_slot").style.display = "block";
						}
					}
				</script>
					
				<div id="time_slot_empty" name="time_slot_empty" style="display:none;">
				<table class="form-table">
				<tr>
					<th>
					<label for="time_slot_label"><b><?php _e( 'Enter a Time Slot:')?></b></label>
					</th>
					<td>
					<b><?php _e( 'From: ', 'woocommerce-booking');?></b>
					<select name="booking_from_slot_hrs[0]" id="booking_from_slot_hrs[0]">
    				<?php
					for ($i=0;$i<24;$i++)
    				{
						printf( "<option %s value='%s'>%s</option>\n",
						selected( $i, '', false ),
						esc_attr( $i ),
						$i
						);
    				}
    				?>
    				</select> Hours
    				
    				<select name="booking_from_slot_min[0]" id="booking_from_slot_min[0]">
    				<?php
					for ($i=0;$i<60;$i++)
    				{
    					if ( $i < 10 )
    					{
    						$i = '0'.$i;
    					}
						printf( "<option %s value='%s'>%s</option>\n",
						selected( $i, '', false ),
						esc_attr( $i ),
						$i
						);
    				}
    				?>
    				</select> Minutes
    				&nbsp;&nbsp;&nbsp;
    				
    				<b><?php _e( 'To: ', 'woocommerce-booking');?></b>
					<select name="booking_to_slot_hrs[0]" id="booking_to_slot_hrs[0]">
    				<?php
					for ($i=0;$i<24;$i++)
    				{
						printf( "<option %s value='%s'>%s</option>\n",
						selected( $i, '', false ),
						esc_attr( $i ),
						$i
						);
    				}
    				?>
    				</select> Hours
    				
    				<select name="booking_to_slot_min[0]" id="booking_to_slot_min[0]">
    				<?php
					for ($i=0;$i<60;$i++)
    				{
    					if ( $i < 10 )
    					{
    						$i = '0'.$i;
    					}
						printf( "<option %s value='%s'>%s</option>\n",
						selected( $i, '', false ),
						esc_attr( $i ),
						$i
						);
    				}
    				?>
    				</select> Minutes
    				<br>
    				<i>If do not want a time range, please leave the To Hours and To Minutes unchanged (set to 0).</i><br><br/>
    				
    				<label for="booking_lockout_time"><b><?php _e( 'Lockout time slot after X orders:')?></b></label><br>
					<input type="text" name="booking_lockout_time[0]" id="booking_lockout_time[0]" value="30" />
					<input type="hidden" id="wapbk_slot_count" name="wapbk_slot_count" value="[0]" /><br>
					<i>Please enter a number to limit the number of bookings for this time slot. This time slot will be shown on the website <b>only when the lockout field value is greater than 0.</b></i><br>
					<br/>
					
					<label for="booking_global_check_lockout"><b><?php _e( 'Make Unavailable for other products once lockout is reached')?></b></label><br/>
					<input type="checkbox" name="booking_global_check_lockout[0]" id="booking_global_check_lockout[0]">
					<i>Please select this checkbox if you want this time slot to be unavailable for all products once the lockout is reached.</i>
					
    				<br/><br/>
    				<label for="booking_time_note"><b><?php _e('Note (optional)', 'woocommerce-booking')?></b></label><br>
    				<textarea class="short" name="booking_time_note[0]" id="booking_time_note[0]" rows="2" cols="50"></textarea>

    				</td>
					
				</tr>
				</table>
				</div>
				
				<div id="time_slot" name="time_slot">
				</div>
				
				<p>
				<div id="add_button" name="add_button" style="display:<?php echo $add_button_show; ?>;">
				<input type="button" class="button-primary" value="Add Time Slot" id="add_another" onclick="add_new_div('[0')">
				</div>
				</p>
				
				<!-- <input type="submit" name="save_booking" value="<?php _e('Save Booking', 'woocommerce-booking');?>" class="button-primary"> -->
				
				</div>
				
				<div id="listing_page" style="display:none;" >
				<table class='wp-list-table widefat fixed posts' cellspacing='0' id='list_bookings_specific'>
					<tr>
						<b>Specific Date Time Slots</b>
					</tr>
					<tr>
						<th> <?php _e('Day', 'woocommerce-booking');?> </th>
						<th> <?php _e('Start Time', 'woocommerce-booking');?> </th>
						<th> <?php _e('End Time', 'woocommerce-booking');?> </th>
						<th> <?php _e('Note', 'woocommerce-booking');?> </th>
						<th> <?php _e('Maximum Bookings', 'woocommerce-booking');?> </th>
						<th> <?php _e('Global Check', 'woocommerce_booking');?> </th>
						<?php print('<th> <a href="javascript:void(0);" id="'.$duplicate_of.'" class="remove_specific_data">Delete All </a> </th>');?>
					</tr>
					
					<?php 	
					$var = "";		
					//$prices = $booking_settings['booking_recurring_prices'];
					//print_r($prices);
					if ( isset($booking_settings['booking_time_settings']) && $booking_settings['booking_time_settings'] != '' ) :
					foreach( $booking_settings['booking_time_settings'] as $key => $value )
					{
						if ( substr($key,0,7) != "booking" )
						{
							$date_disp = $key;
							foreach( $value as $date_key => $date_value )
							{
								print('<tr id="row_'.$date_key.'_'.$date_disp.'" >');
								print('<td> '.$date_disp.' </td>');
								print('<td> '.$date_value['from_slot_hrs'].':'.$date_value['from_slot_min'].' </td>');
								print('<td> '.$date_value['to_slot_hrs'].':'.$date_value['to_slot_min'].' </td>');
								//print('<td>  &nbsp; </td>');
								print('<td> '.$date_value['booking_notes'].' </td>');
								print('<td> '.$date_value['lockout_slot'].' </td>');
								print('<td> '.$date_value['global_time_check'].' </td>');
								print('<td> <a href="javascript:void(0);" id="'.$date_key.'&'.$duplicate_of.'&'.$date_disp.'&'.$date_value['from_slot_hrs'].':'.$date_value['from_slot_min'].'&'.$date_value['to_slot_hrs'].':'.$date_value['to_slot_min'].'" class="remove_time_data"> <img src="'.plugins_url().'/woocommerce-booking/images/delete.png" alt="Remove Time Slot" title="Remove Time Slot"></a> </td>');
								print('</tr>');
							}
							
						}
						elseif ( substr($key,0,7) == "booking" )
						{
							$date_pass = $key;
							$weekdays = book_arrays('weekdays');
							$date_disp = $weekdays[$key];
							//$price = $prices[$key."_price"];
							foreach( $value as $date_key => $date_value )
							{
								$global_time_check = '';
								if(isset($date_value['global_time_check']))
									$global_time_check = $date_value['global_time_check'];
								$var .= '<tr id="row_'.$date_key.'_'.$date_pass.'" >
								<td> '.$date_disp.' </td>
								<td> '.$date_value['from_slot_hrs'].':'.$date_value['from_slot_min'].' </td>
								<td> '.$date_value['to_slot_hrs'].':'.$date_value['to_slot_min'].' </td>
								<td> '.$date_value['booking_notes'].' </td>
								<td> '.$date_value['lockout_slot'].' </td>
								<td> '.$global_time_check.' </td>
								<td> <a href="javascript:void(0);" id="'.$date_key.'&'.$duplicate_of.'&'.$date_pass.'&'.$date_value['from_slot_hrs'].':'.$date_value['from_slot_min'].'&'.$date_value['to_slot_hrs'].':'.$date_value['to_slot_min'].'" class="remove_time_data"> <img src="'.plugins_url().'/woocommerce-booking/images/delete.png" alt="Remove Time Slot" title="Remove Time Slot"></a> </td>
								</tr>';
							}
						}
					}
					endif;
					if ( isset($booking_settings['booking_enable_multiple_day']) && $booking_settings['booking_enable_multiple_day'] != 'on' ) :
						$query = "SELECT * FROM `".$wpdb->prefix."booking_history`
						WHERE post_id='".$duplicate_of."' AND from_time='' AND to_time='' AND end_date='0000-00-00'";
						$results = $wpdb->get_results ( $query );
						
						foreach ( $results as $key => $value )
						{
							if (substr($value->weekday, 0, 7) != "booking")
							{
								$date_key = date('j-n-Y',strtotime($value->start_date));
								print('<tr id="row_'.$date_key.'" >');
								print('<td> '.$date_key.' </td>');
								print('<td> &nbsp; </td>');
								print('<td> &nbsp; </td>');
								print('<td> &nbsp; </td>');
								print('<td> '.$value->total_booking.' </td>');
								print('<td> &nbsp; </td>');
								print('<td> <a href="javascript:void(0);" id="'.$date_key.'&'.$duplicate_of.'" class="remove_day_data"> <img src="'.plugins_url().'/woocommerce-booking/images/delete.png" alt="Remove Date" title="Remove Date"></a> </td>');
								print('</tr>');	
							}
							elseif (substr($value->weekday, 0, 7) == "booking" && $value->start_date == "0000-00-00")
							{
								$weekdays = book_arrays('weekdays');
								//$price = $prices[$value->weekday."_price"];
								$date_disp = $weekdays[$value->weekday];
								$var .= '<tr id="row_'.$value->weekday.'" >
									<td> '.$date_disp.' </td>
									<td>  &nbsp; </td>
									<td>  &nbsp; </td>
									<td>  &nbsp; </td>
									<td> '.$value->total_booking.' </td>
									<td>  &nbsp; </td>
								 	<td> <a href="javascript:void(0);" id="'.$value->weekday.'&'.$duplicate_of.'" class="remove_day_data"> <img src="'.plugins_url().'/woocommerce-booking/images/delete.png" alt="Remove Day" title="Remove Day"></a> </td>
									</tr>';
							}
						}
					endif;
					?>
				
				</table>
				
				<p>
				<table class='wp-list-table widefat fixed posts' cellspacing='0' id='list_bookings_recurring'>
					<tr>
						<b>Recurring Days Time Slots</b>
					</tr>
					<tr>
						<th> <?php _e('Day', 'woocommerce-booking');?> </th>
						<th> <?php _e('Start Time', 'woocommerce-booking');?> </th>
						<th> <?php _e('End Time', 'woocommerce-booking');?> </th>
						<th> <?php _e('Note', 'woocommerce-booking');?> </th> 
						<th> <?php _e('Maximum Bookings', 'woocommerce-booking');?> </th>
						<th> <?php _e('Global Check', 'woocommerce-booking');?>
						<?php print('<th> <a href="javascript:void(0);" id="'.$duplicate_of.'" class="remove_recurring_data"> Delete All </a> </th>');	?>
					</tr>
				<?php 
				if (isset($var))
				{
					echo $var;
				}
				?>
				</table>
				</p>
				</div>
				<?php 
					do_action('bkap_after_listing_enabled', $duplicate_of);
				?>
			<!--  	</form>  -->
				<?php
				
				
			}
			
			function process_bookings_box( $post_id, $post ) {
				
				global $wpdb;
			
				//Save Email Reminders
		/*		$subject = '';
				if (isset($_POST["erem_subject"])) $subject = $_POST["erem_subject"];
				$message = '';//echo "<pre>";print_r(($_POST["erem_message"][0]));echo "</pre>";exit;
				if (isset($_POST["erem_message"])) $message = stripslashes($_POST["erem_message"][0]);//stripslashes($_POST["erem_message"])str_replace(array("\n", "\r"), '', $_POST["erem_message"]);
				$days = '';
				if (isset($_POST["erem_days"])) $days = $_POST["erem_days"];
				$hours =  '';
				if (isset($_POST["erem_hours"])) $hours = $_POST["erem_hours"];
				$minutes = '';
				if (isset($_POST["erem_minutes"])) $minutes = $_POST["erem_minutes"];
				$email = '';
				if (isset($_POST["erem_email"])) $email = $_POST["erem_email"];
				
				$all_reminders = array();
				
				$count = 0;
				if (isset($subject) && $subject != '')
				{
					foreach($subject as $v){
						if(trim($v) != "")
						{
							$total_minutes = $minutes[$count] + ($hours[$count] * 60) + (($days[$count] * 24 )*60);
							$all_reminders[$count] = array('subject' => $subject[$count],
									'message' => $message[$count],
									'days' => $days[$count],
									'hours' => $hours[$count],
									'minutes' => $minutes[$count],
									'total_minutes' => $total_minutes,
									'email' => $email[$count]);
								
							$count++;
						}
					}
				}
				//$all_reminders_str = str_replace('\r\n', "", json_encode($all_reminders));
				$sts = update_post_meta($post_id, 'woocommerce_booking_emailreminders', json_encode($all_reminders) );*/
				
				// Save Bookings
				$product_bookings = array();
				$duplicate_of = get_post_meta($post_id, '_icl_lang_duplicate_of', true);
				if($duplicate_of == '' && $duplicate_of == null)
				{
					$post_time = get_post($post_id);
					$id_query = "SELECT ID FROM `".$wpdb->prefix."posts` WHERE post_date = '".$post_time->post_date."' ORDER BY ID LIMIT 1";
					$results_post_id = $wpdb->get_results ( $id_query );
					if( isset($results_post_id) ) {
						$duplicate_of = $results_post_id[0]->ID;
					}
					else
					{
						$duplicate_of = $post_id;
					}
					//$duplicate_of = $item_value['product_id'];
				}
				$woo_booking_dates = get_post_meta($duplicate_of, 'woocommerce_booking_settings', true);
				//$booking_settings = get_post_meta($post_id, 'woocommerce_booking_settings', true);
				//print_r($woo_booking_dates);
				
				$enable_inline_calendar = $enable_date = $enable_multiple_day = $specific_booking_chk = $recurring_booking = "";
				
				if(isset($_POST['enable_inline_calendar']))
				
					$enable_inline_calendar = $_POST['enable_inline_calendar'];
				
				if (isset($_POST['booking_enable_date']))
				{
					$enable_date = $_POST['booking_enable_date'];
				}
				
				if (isset($_POST['booking_enable_multiple_day']))
				{
					$enable_multiple_day = $_POST['booking_enable_multiple_day'];
				}
				
				if (isset($_POST['booking_specific_booking']))
				{
					$specific_booking_chk = $_POST['booking_specific_booking'];
				}
				
				$recurring_booking="";
				if (isset($_POST['booking_recurring_booking']))
				{
					$recurring_booking = $_POST['booking_recurring_booking'];
				}
				 
				$booking_days = array();
				$new_day_arr = array();
				$weekdays = book_arrays('weekdays');
				foreach ($weekdays as $n => $day_name)
				{
					if ( isset($woo_booking_dates['booking_recurring']) && count($woo_booking_dates['booking_recurring']) > 1 )
					{
						if ( isset($_POST[$n]) && $_POST[$n] == 'on' || isset($_POST[$n]) && $_POST[$n] == '')
						{
							$new_day_arr[$n] = $_POST[$n];
						}
						if ( isset($_POST[$n]) && $_POST[$n] == 'on' )
						{
							$booking_days[$n] = $_POST[$n];
						}
						else 
						{
							$booking_days[$n] = $woo_booking_dates['booking_recurring'][$n];
						}
					}
					else 
					{
						if (isset($_POST[$n]))
						{
							$new_day_arr[$n] = $_POST[$n];
							$booking_days[$n] = $_POST[$n];
						}
						else $new_day_arr[$n] = $booking_days[$n] = '';
					}
					/*if ( isset($woo_booking_dates['booking_recurring_prices']) && count($woo_booking_dates['booking_recurring_prices']) > 1 )
					{
						if ( isset($_POST[$n."_price"]) && $_POST[$n."_price"] != '' )
						{
							$new_day_arr_price[$n."_price"] = $_POST[$n."_price"];
						}
						else 
						{
							$new_day_arr_price[$n."_price"] = $woo_booking_dates['booking_recurring_prices'][$n."_price"];
						}
					}
					else
					{
						if (isset($_POST[$n."_price"]))
						{
							$new_day_arr_price[$n."_price"] = $_POST[$n."_price"];
						}
						else $new_day_arr_price[$n."_price"] = '';
					}*/
				}

			 
				$specific_booking = '';
				if (isset($_POST['booking_specific_date_booking']))
				{
					$specific_booking = $_POST['booking_specific_date_booking'];
				}
				if($specific_booking != '')
					$specific_booking_dates = explode(",",$specific_booking);
				else
					$specific_booking_dates = array();
				
				$specific_stored_days = array();
				if( isset($woo_booking_dates['booking_specific_date']) && count($woo_booking_dates['booking_specific_date']) > 0) $specific_stored_days = $woo_booking_dates['booking_specific_date'];
			
				foreach ( $specific_booking_dates as $key => $value )
				{
					if (trim($value != "")) $specific_stored_days[] = $value;
				}
				if(isset($_POST['booking_minimum_number_days']))
			 		$minimum_number_days = $_POST['booking_minimum_number_days'];
				else 
					$minimum_number_days = '';
				if(isset($_POST['booking_maximum_number_days']))
					$maximum_number_days = $_POST['booking_maximum_number_days'];
				else 
					$maximum_number_days = '';
				$without_date="";
				if (isset($_POST['booking_purchase_without_date']))
				{
					$without_date = $_POST['booking_purchase_without_date'];
				}
				$lockout_date = '';
				if(isset($_POST['booking_lockout_date']))
					$lockout_date = $_POST['booking_lockout_date'];
				$product_holiday = '';
				if(isset($_POST['booking_product_holiday']))
					$product_holiday = $_POST['booking_product_holiday'];
			
				$enable_time = '';
				if (isset($_POST['booking_enable_time']))
				{
					$enable_time = $_POST['booking_enable_time'];
				}
				$slot_count_value = '';
				if(isset($_POST['wapbk_slot_count']))
				{
					$slot_count = explode("[", $_POST['wapbk_slot_count']);
					$slot_count_value = intval($slot_count[1]);
				}
				$date_time_settings = array();
				$time_settings = array();
				if( $specific_booking != "" )
				{
					foreach ( $specific_booking_dates as $day_key => $day_value )
					{
						$date_tmstmp = strtotime($day_value);
						$date_save = date('Y-m-d',$date_tmstmp);
							if (isset($_POST['booking_enable_time']) && $_POST['booking_enable_time'] == "on")
							{
								$j=1;
								if(isset($woo_booking_dates['booking_time_settings']) && is_array($woo_booking_dates['booking_time_settings']))
								{
									if (array_key_exists($day_value,$woo_booking_dates['booking_time_settings']))
									{
										foreach ( $woo_booking_dates['booking_time_settings'][$day_value] as $dtkey => $dtvalue )
										{
											$date_time_settings[$day_value][$j] = $dtvalue;
											$j++;
										}
									}
								}
								$k = 1;
								for($i=($j + 1); $i<=($j + $slot_count_value); $i++)
								{
									if( isset($_POST['booking_from_slot_hrs'][$k]) && $_POST['booking_from_slot_hrs'][$k] != 0 )
									{
										$time_settings['from_slot_hrs'] = $_POST['booking_from_slot_hrs'][$k];
										$time_settings['from_slot_min'] = $_POST['booking_from_slot_min'][$k];
										$time_settings['to_slot_hrs'] = $_POST['booking_to_slot_hrs'][$k];
										$time_settings['to_slot_min'] = $_POST['booking_to_slot_min'][$k];
										$time_settings['booking_notes'] = $_POST['booking_time_note'][$k];
										$time_settings['lockout_slot'] = $_POST['booking_lockout_time'][$k];
										if(isset($_POST['booking_global_check_lockout'][$k]))
										{
											$time_settings['global_time_check'] = $_POST['booking_global_check_lockout'][$k];
										}
										else
										{
											$time_settings['global_time_check'] = '';
										}
										$date_time_settings[$day_value][$i] = $time_settings;
										$from_time = $_POST['booking_from_slot_hrs'][$k].":".$_POST['booking_from_slot_min'][$k];
										$to_time = "";
										if(isset($_POST['booking_to_slot_hrs'][$k]) && $_POST['booking_to_slot_hrs'][$k] != 0 )
										{
											$to_time = $_POST['booking_to_slot_hrs'][$k].":".$_POST['booking_to_slot_min'][$k];
										}

										$query_delete = "DELETE FROM `".$wpdb->prefix."booking_history`
													WHERE post_id = '".$duplicate_of."'
													AND start_date = '".$date_save."'
													AND from_time = ''
													AND to_time = ''";
										$wpdb->query($query_delete);
										$query_insert = "INSERT INTO `".$wpdb->prefix."booking_history`
													 (post_id,weekday,start_date,end_date,from_time,to_time,total_booking,available_booking)
													 VALUES (
													 '".$duplicate_of."',
													 '',
													 '".$date_save."',
													 '0000-00-00',
													 '".$from_time."',
													 '".$to_time."',
													 '".$_POST['booking_lockout_time'][$k]."',
													 '".$_POST['booking_lockout_time'][$k]."' )";
										$wpdb->query( $query_insert );
									}
									$k++;
								}
							}
							else
							{
								$query_delete = "DELETE FROM `".$wpdb->prefix."booking_history`
													WHERE post_id = '".$duplicate_of."'
													AND start_date = '".$date_save."'";
									$wpdb->query($query_delete);
								$query_insert = "INSERT INTO `".$wpdb->prefix."booking_history`
											(post_id,weekday,start_date,end_date,from_time,to_time,total_booking,available_booking)
											VALUES (
											'".$duplicate_of."',
											'',
											'".$date_save."',
											'0000-00-00',
											'',
											'',
											'".$_POST['booking_lockout_date']."',
											'".$_POST['booking_lockout_date']."' )";
								$wpdb->query( $query_insert );
							}
						
						}
					}
					if ( count($new_day_arr) >= 1 )
					{
						foreach ( $new_day_arr as $wkey => $wvalue )
						{
							if( $wvalue == 'on' )
							{
								if (isset($_POST['booking_enable_time']) && $_POST['booking_enable_time'] == "on")
								{
									$j=1;
									if(isset($woo_booking_dates['booking_time_settings']) && is_array($woo_booking_dates['booking_time_settings']))
									{
										if (array_key_exists($wkey,$woo_booking_dates['booking_time_settings']))
										{
											foreach ( $woo_booking_dates['booking_time_settings'][$wkey] as $dtkey => $dtvalue )
											{
												$date_time_settings[$wkey][$j] = $dtvalue;
												$j++;
											}
										}
									}
									$k = 1;
									for($i=($j + 1); $i<=($j + $slot_count_value); $i++)		
									{
										if(isset($_POST['booking_from_slot_hrs'][$k]) && $_POST['booking_from_slot_hrs'][$k] != 0 )
										{
											$time_settings['from_slot_hrs'] = $_POST['booking_from_slot_hrs'][$k];
											$time_settings['from_slot_min'] = $_POST['booking_from_slot_min'][$k];
											$time_settings['to_slot_hrs'] = $_POST['booking_to_slot_hrs'][$k];
											$time_settings['to_slot_min'] = $_POST['booking_to_slot_min'][$k];
											$time_settings['booking_notes'] = $_POST['booking_time_note'][$k];
											$time_settings['lockout_slot'] = $_POST['booking_lockout_time'][$k];
											if(isset($_POST['booking_global_check_lockout'][$k]))
											{
												$time_settings['global_time_check'] = $_POST['booking_global_check_lockout'][$k];
											}
											else
											{
												$time_settings['global_time_check'] = '';
											}
											$date_time_settings[$wkey][$i] = $time_settings;
											$from_time = $_POST['booking_from_slot_hrs'][$k].":".$_POST['booking_from_slot_min'][$k];
											$to_time = "";
											if(isset($_POST['booking_to_slot_hrs'][$k]) && $_POST['booking_to_slot_hrs'][$k] != 0 )
											{
												$to_time = $_POST['booking_to_slot_hrs'][$k].":".$_POST['booking_to_slot_min'][$k];
											}
										
											$query_delete = "DELETE FROM `".$wpdb->prefix."booking_history`
													WHERE post_id = '".$duplicate_of."'
													AND weekday = '".$wkey."'
													AND from_time = ''
													AND to_time = ''";
											$wpdb->query($query_delete);
											
											$query_insert_week = "INSERT INTO `".$wpdb->prefix."booking_history`
														(post_id,weekday,start_date,end_date,from_time,to_time,total_booking,available_booking)
														VALUES (
														'".$duplicate_of."',
														'".$wkey."',
														'0000-00-00',
														'0000-00-00',
														'".$from_time."',
														'".$to_time."',
														'".$_POST['booking_lockout_time'][$k]."',
														'".$_POST['booking_lockout_time'][$k]."') ";
											$wpdb->query( $query_insert_week );
										}	
										$k++;	
									}
								}
								else
								{
									$query_delete = "DELETE FROM `".$wpdb->prefix."booking_history`
													WHERE post_id = '".$duplicate_of."'
													AND weekday = '".$wkey."'";
									$wpdb->query($query_delete);
									$query_insert_week = "INSERT INTO `".$wpdb->prefix."booking_history`
													(post_id,weekday,start_date,end_date,from_time,to_time,total_booking,available_booking)
													VALUES (
													'".$duplicate_of."',
													'".$wkey."',
													'0000-00-00',
													'0000-00-00',
													'',
													'',
													'".$_POST['booking_lockout_date']."',
													'".$_POST['booking_lockout_date']."') ";
									$wpdb->query( $query_insert_week );
								}
						
							}
						}
					}
				
					$new_time_settings = $woo_booking_dates;
					//if ( count($woo_booking_dates) > 1 )
					{
						foreach ( $date_time_settings as $dtkey => $dtvalue )
						{
							$new_time_settings['booking_time_settings'][$dtkey] = $dtvalue;
						}
					}
					 
				//echo $enable_inline_calendar;exit;
				$booking_settings = array();
				$booking_settings['booking_enable_date'] = $enable_date;
				$booking_settings['enable_inline_calendar'] = $enable_inline_calendar;
				$booking_settings['booking_enable_multiple_day'] = $enable_multiple_day;
				$booking_settings['booking_specific_booking'] = $specific_booking_chk;
				$booking_settings['booking_recurring_booking'] = $recurring_booking;
				$booking_settings['booking_recurring'] = $booking_days;
				//$booking_settings['booking_recurring_prices'] = $new_day_arr_price;
				$booking_settings['booking_specific_date'] = $specific_stored_days;
				$booking_settings['booking_minimum_number_days'] = $minimum_number_days;
				$booking_settings['booking_maximum_number_days'] = $maximum_number_days;
				$booking_settings['booking_purchase_without_date'] = $without_date;
				$booking_settings['booking_date_lockout'] = $lockout_date;
				$booking_settings['booking_product_holiday'] = $product_holiday;
				$booking_settings['booking_enable_time'] = $enable_time;
				if (isset($new_time_settings['booking_time_settings'])) $booking_settings['booking_time_settings'] = $new_time_settings['booking_time_settings'];
				else $booking_settings['booking_time_settings'] = '';
				$booking_settings = (array) apply_filters( 'bkap_save_product_settings', $booking_settings, $duplicate_of );
				//echo "<pre>"; print_r($booking_settings); echo "</pre>"; exit;
				update_post_meta($duplicate_of, 'woocommerce_booking_settings', $booking_settings);
			}
			
			function betweendays($StartDate, $EndDate)
			{
				$Days[] = $StartDate;
				$CurrentDate = $StartDate;
			
				$CurrentDate_timestamp = strtotime($CurrentDate);
				$EndDate_timestamp = strtotime($EndDate);
				if($CurrentDate_timestamp != $EndDate_timestamp)
				{	
					while($CurrentDate_timestamp < $EndDate_timestamp)
					{
						$CurrentDate = date("d-n-Y", strtotime("+1 day", strtotime($CurrentDate)));
						$CurrentDate_timestamp = $CurrentDate_timestamp + 86400;
						$Days[] = $CurrentDate;
					}
					array_pop($Days);
				}
				return $Days;
			}
			function date_lockout($start_date)
			{
				global $wpdb,$post;
				$duplicate_of = get_post_meta($post->ID, '_icl_lang_duplicate_of', true);
				if($duplicate_of == '' && $duplicate_of == null)
				{
					$post_time = get_post($post->ID);
					$id_query = "SELECT ID FROM `".$wpdb->prefix."posts` WHERE post_date = '".$post_time->post_date."' ORDER BY ID LIMIT 1";
					$results_post_id = $wpdb->get_results ( $id_query );
					if( isset($results_post_id) ) {
						$duplicate_of = $results_post_id[0]->ID;
					}
					else
					{
						$duplicate_of = $post->ID;
					}
					//$duplicate_of = $item_value['product_id'];
				}
				$date_lockout = "SELECT sum(total_booking) - sum(available_booking) AS bookings_done FROM `".$wpdb->prefix."booking_history`
				WHERE start_date='".$start_date."' AND post_id='".$duplicate_of."'";
					//echo $date_lockout;
				$results_date_lock = $wpdb->get_results($date_lockout);
					//print_r($results_date_lock);
				$bookings_done = $results_date_lock[0]->bookings_done;
				return $bookings_done;
			}

			function booking_after_add_to_cart() {

				global $post, $wpdb,$woocommerce;
				$duplicate_of = get_post_meta($post->ID, '_icl_lang_duplicate_of', true);
				if($duplicate_of == '' && $duplicate_of == null)
				{
					$post_time = get_post($post->ID);
					$id_query = "SELECT ID FROM `".$wpdb->prefix."posts` WHERE post_date = '".$post->post_date."' ORDER BY ID LIMIT 1";
					$results_post_id = $wpdb->get_results ( $id_query );
					if( isset($results_post_id) ) {
						$duplicate_of = $results_post_id[0]->ID;
					}
					else
					{
						$duplicate_of = $post->ID;
					}
					//$duplicate_of = $post->ID;
				}
				do_action('bkap_print_hidden_fields',$duplicate_of);
				$method_to_show = 'check_for_time_slot';
				$get_method = ajax_on_select_date();
				//echo $get_method;exit;
				if(isset($get_method) && $get_method == 'multiple_time')
				{
					$method_to_show = apply_filters('bkap_function_slot','');
					
				}
				else
				{
					$method_to_show = 'check_for_time_slot';
				}
				
				$booking_settings = get_post_meta($duplicate_of, 'woocommerce_booking_settings', true);
				$global_settings = json_decode(get_option('woocommerce_booking_global_settings'));
				$sold_individually = get_post_meta($post->ID, '_sold_individually', true);
				print('<input type="hidden" id="wapbk_sold_individually" name="wapbk_sold_individually" value="'.$sold_individually.'">');
				//	default global settings
				if ($global_settings == '')
				{
					$global_settings = new stdClass();
					$global_settings->booking_date_format = 'd MM, yy';
					$global_settings->booking_time_format = '12';
					$global_settings->booking_months = '1';
				}
				//rounding settings
				$rounding = "";
				if (isset($global_settings->enable_rounding) && $global_settings->enable_rounding == "on") $rounding = "yes";
				else $rounding = "no";
				print('<input type="hidden" id="wapbk_round_price" name="wapbk_round_price" value="'.$rounding.'">');

				if (isset($global_settings->booking_global_selection) && $global_settings->booking_global_selection == "on") $selection = "yes";
				else $selection = "no";
				print('<input type="hidden" id="wapbk_global_selection" name="wapbk_global_selection" value="'.$selection.'">');
				
				if ( $booking_settings != '' ) :
				// fetch specific booking dates
				if(isset($booking_settings['booking_specific_date']))
					$booking_dates_arr = $booking_settings['booking_specific_date'];
				else 
					$booking_dates_arr = array();
				$booking_dates_str = "";
				if ($booking_settings['booking_specific_booking'] == "on")
				{					if(!empty($booking_dates_arr)){
						foreach ($booking_dates_arr as $k => $v)
						{
							$booking_dates_str .= '"'.$v.'",';
						}					}
					$booking_dates_str = substr($booking_dates_str,0,strlen($booking_dates_str)-1);		
				}
				print('<input type="hidden" name="wapbk_booking_dates" id="wapbk_booking_dates" value=\''.$booking_dates_str.'\'>');
				if (isset($global_settings->booking_global_holidays))
				{
					$book_global_holidays = $global_settings->booking_global_holidays;
					$book_global_holidays = substr($book_global_holidays,0,strlen($book_global_holidays));
					$book_global_holidays = '"'.str_replace(',', '","', $book_global_holidays).'"';
				}
				else
				{
					$book_global_holidays = "";
				}
				print('<input type="hidden" name="wapbk_booking_global_holidays" id="wapbk_booking_global_holidays" value=\''.$book_global_holidays.'\'>');
				
				$booking_holidays_string = '"'.str_replace(',', '","', $booking_settings['booking_product_holiday']).'"';
				print('<input type="hidden" name="wapbk_booking_holidays" id="wapbk_booking_holidays" value=\''.$booking_holidays_string.'\'>');
				
				//Default settings
				$default = "Y";
				if ((isset($booking_settings['booking_recurring_booking']) && $booking_settings['booking_recurring_booking'] == "on") || (isset($booking_settings['booking_specific_booking']) && $booking_settings['booking_specific_booking'] == "on")) $default = "N";

				foreach ($booking_settings['booking_recurring'] as $wkey => $wval)
				{
					if ($default == "Y")
					{
						print('<input type="hidden" name="wapbk_'.$wkey.'" id="wapbk_'.$wkey.'" value="on">');
					}
					else
					{
						if ($booking_settings['booking_recurring_booking'] == "on")	
						{
							print('<input type="hidden" name="wapbk_'.$wkey.'" id="wapbk_'.$wkey.'" value="'.$wval.'">');
						}
						else
						{
							print('<input type="hidden" name="wapbk_'.$wkey.'" id="wapbk_'.$wkey.'" value="">');
						}
					}
				}
				if (isset($booking_settings['booking_time_settings']))
				{
					print('<input type="hidden" name="wapbk_booking_times" id="wapbk_booking_times" value=\''.$booking_settings['booking_time_settings'].'\'>');
				}
				else
				{
					print('<input type="hidden" name="wapbk_booking_times" id="wapbk_booking_times" value="">');
				}
								
		/*		echo "<pre>";
				print_r($global_settings);
				print_r($booking_settings);
				echo "</pre>"; */  
				if (isset($booking_settings['booking_minimum_number_days']))
				{
					print('<input type="hidden" name="wapbk_minimumOrderDays" id="wapbk_minimumOrderDays" value="'.$booking_settings['booking_minimum_number_days'].'">');
				}
				else
				{
					print('<input type="hidden" name="wapbk_minimumOrderDays" id="wapbk_minimumOrderDays" value="">');
				}
				if (isset($booking_settings['booking_maximum_number_days']))
				{
		 			print('<input type="hidden" name="wapbk_number_of_dates" id="wapbk_number_of_dates" value="'.$booking_settings['booking_maximum_number_days'].'">');
				}
				else 
				{
					print('<input type="hidden" name="wapbk_number_of_dates" id="wapbk_number_of_dates" value="">');
				}
				if (isset($booking_settings['booking_enable_time']))
				{
		 			print('<input type="hidden" name="wapbk_bookingEnableTime" id="wapbk_bookingEnableTime" value="'.$booking_settings['booking_enable_time'].'">');
				}
				else
				{
					print('<input type="hidden" name="wapbk_bookingEnableTime" id="wapbk_bookingEnableTime" value="">');
				}
				if (isset($booking_settings['booking_recurring_booking']))
				{
		 			print('<input type="hidden" name="wapbk_recurringDays" id="wapbk_recurringDays" value="'.$booking_settings['booking_recurring_booking'].'">');
				}
				else
				{
					print('<input type="hidden" name="wapbk_recurringDays" id="wapbk_recurringDays" value="">');
				}
				if (isset($booking_settings['booking_specific_booking']))
				{
		 			print('<input type="hidden" name="wapbk_specificDates" id="wapbk_specificDates" value="'.$booking_settings['booking_specific_booking'].'">');
				}
				else 
				{
					print('<input type="hidden" name="wapbk_specificDates" id="wapbk_specificDates" value="">');
				}
		 		
		 		endif;
				//Lockout Dates
				$lockout_query = "SELECT DISTINCT start_date FROM `".$wpdb->prefix."booking_history`
								WHERE post_id='".$duplicate_of."'
								AND total_booking > 0
								AND available_booking = 0";
				$results_lockout = $wpdb->get_results ( $lockout_query );
				
				$lockout_query = "SELECT DISTINCT start_date FROM `".$wpdb->prefix."booking_history`
				WHERE post_id='".$duplicate_of."'
				AND available_booking > 0";
				//echo $lockout_query;
				$results_lock = $wpdb->get_results ( $lockout_query );
				$lockout_date = '';
					//print_r($results_lock);exit;
		/*		foreach($results_lock as $key => $value)
				{
					$start_date = $value->start_date;
					$bookings_done = $this->date_lockout($start_date);
					if($bookings_done >= $booking_settings['booking_date_lockout'])
					{
						$lockout = explode("-",$start_date);
						$lockout_date .= '"'.intval($lockout[2])."-".intval($lockout[1])."-".$lockout[0].'",';
					}
				}
				$lockout_str = substr($lockout_date,0,strlen($lockout_date)-1);*/
				foreach ($results_lockout as $k => $v)
				{
					foreach($results_lock as $key => $value)
					{
						if ($v->start_date == $value->start_date)
						{
							$date_lockout = "SELECT COUNT(start_date) FROM `".$wpdb->prefix."booking_history`
												WHERE post_id='".$duplicate_of."'
												AND start_date='".$v->start_date."'
												AND available_booking = 0";
							$results_date_lock = $wpdb->get_results($date_lockout);
							
							if ($booking_settings['booking_date_lockout'] > $results_date_lock[0]->{'COUNT(start_date)'}) unset($results_lockout[$k]);	
						} 
					}
				}

				$lockout_dates_str = "";
				foreach ($results_lockout as $k => $v)
				{
					$lockout_temp = $v->start_date;
					$lockout = explode("-",$lockout_temp);
					$lockout_dates_str .= '"'.intval($lockout[2])."-".intval($lockout[1])."-".$lockout[0].'",';
					$lockout_temp = "";
				}
				$lockout_dates_str = substr($lockout_dates_str,0,strlen($lockout_dates_str)-1);
			//	$lockout_dates = $lockout_dates_str.",".$lockout_str;
				$lockout_dates = $lockout_dates_str;
				print('<input type="hidden" name="wapbk_lockout_days" id="wapbk_lockout_days" value=\''.$lockout_dates.'\'>');
				$todays_date = date('Y-m-d');
				//print_r($todays_date);
				$query_date ="SELECT DATE_FORMAT(start_date,'%d-%c-%Y') as start_date,DATE_FORMAT(end_date,'%d-%c-%Y') as end_date FROM ".$wpdb->prefix."booking_history WHERE (start_date >='".$todays_date."' OR end_date >='".$todays_date."') AND post_id = '".$duplicate_of."'";
				//echo $query_date;
				$results_date = $wpdb->get_results($query_date);
				//print_r($results_date);
				$dates_new = array();
				$booked_dates = array();
				foreach($results_date as $k => $v)
				{
					
					$start_date = $v->start_date;
					$end_date = $v->end_date;
					$dates = $this->betweendays($start_date, $end_date);
					//print_r($dates);
					$dates_new = array_merge($dates,$dates_new);
				}
			//	Enable the start date for the booking period for checkout
				foreach ($results_date as $k => $v)
				{
					$start_date = $v->start_date;
			//		echo ($start_date);
					$end_date = $v->end_date;
			//		echo ($end_date);
					$new_start = strtotime("+1 day", strtotime($start_date));
					$new_start = date("d-m-Y",$new_start);
			//		echo $new_start;
					$dates = $this->betweendays($new_start, $end_date);
					$booked_dates = array_merge($dates,$booked_dates);
				}
			//	print_r($dates);
			//	print_r($booked_dates);
				$dates_new_arr = array_count_values($dates_new);
				$booked_dates_arr = array_count_values($booked_dates);
				//print_r($dates_new_arr);
				$lockout = "";
				if (isset($booking_settings['booking_date_lockout']))
				{
					$lockout = $booking_settings['booking_date_lockout'];
				}
				//echo $lockout;
				$new_arr_str = '';
				foreach($dates_new_arr as $k => $v)
				{
					if($v >= $lockout && $lockout != 0)
					{
						//print_r($v);
						//print_r($lockout);
						$date_temp = $k;
						$date = explode("-",$date_temp);
						$new_arr_str .= '"'.intval($date[0])."-".intval($date[1])."-".$date[2].'",';
						$date_temp = "";
					//	$new_arr_str .=  '"'.$k.'",';
					}
				}
				//$new_arr_str = date('d-m-Y',$new_arr_str);
				$new_arr_str = substr($new_arr_str,0,strlen($new_arr_str)-1);
				//print_r($new_arr_str);
				print("<input type='hidden' id='wapbk_hidden_booked_dates' name='wapbk_hidden_booked_dates' value='".$new_arr_str."'/>");
				//checkout calendar booked dates
				$blocked_dates = array();
				$booked_dates_str = "";
				foreach ($booked_dates_arr as $k => $v)
				{
					if($v >= $lockout && $lockout != 0)
					{
						$date_temp = $k;
						$date = explode("-",$date_temp);
						$date_without_zero_prefixed = intval($date[0])."-".intval($date[1])."-".$date[2];
						$booked_dates_str .= '"'.intval($date[0])."-".intval($date[1])."-".$date[2].'",';
						$date_temp = "";
						$blocked_dates[] = $date_without_zero_prefixed;
						//	$new_arr_str .=  '"'.$k.'",';
					}
				}
				if (isset($booked_dates_str))
				{
					$booked_dates_str = substr($booked_dates_str,0,strlen($booked_dates_str)-1);
				}
				else
				{
					$booked_dates_str = "";
				}
						
				print("<input type='hidden' id='wapbk_hidden_booked_dates_checkout' name='wapbk_hidden_booked_dates_checkout' value='".$booked_dates_str."'/>");
				
				if(isset($booking_settings['booking_recurring']))
					$recurring_date = $booking_settings['booking_recurring'];
				else 
					$recurring_date = array();
				if(isset($booking_settings['booking_specific_date']))
					$specific_date = $booking_settings['booking_specific_date'];
				else
					$specific_date = array();
				//print_r($specific_date);
				if(isset($booking_settings['booking_product_holiday']))
				$holiday_array = explode(',',$booking_settings['booking_product_holiday']);
				if(isset($global_settings->booking_global_holidays))
				{
					$global_holidays = explode(',',$global_settings->booking_global_holidays);
				}
				else
				{
					$global_holidays = array();
				}
				$current_date = date('d-m-Y');
				$current_day = date('N',strtotime($current_date)); 
				if(isset($booking_settings['booking_minimum_number_days']))
					$min_date = date('j-n-Y', strtotime('+'.$booking_settings['booking_minimum_number_days'].' day',strtotime($current_date)));
				else
					$min_date = '';
				//var_dump($min_date);
				//echo $min_date;exit;
				$i = 0;
                                if(isset($specific_date) && $specific_date != '')
                                {
                                    foreach($specific_date as $key => $val)
                                    {
                                            $min_specific = date('j-n-Y',min(array_map('strtotime', $specific_date)));
                                            if(strtotime($min_specific) < strtotime($specific_date[$i]))
                                            {
                                                    unset($specific_date[$i]);
                                                    if(in_array($min_specific, $holiday_array) || in_array($min_specific,$global_holidays))
                                                    {
                                                            unset($specific_date[array_search($min_specific,$specific_date)]);
                                                            //print_r($specific_date);
                                                            //$min_specific = $specific_date[$i+1];
                                                            /*if(!in_array($min_specific, $holiday_array) || !in_array($min_specific,$global_holidays))
                                                            {	
                                                                    //break;
                                                            }*/

                                                    }
                                            }
                                            $i++;
                                    }
                                }
				//$min_date;
				$first_enable_day = '';
				$default_date = '';
				$min_day = date('N',strtotime($min_date)); 
				$default_date_recurring = $min_date;
				//print_r($recurring_date);  
				
				if(isset($booking_settings['booking_enable_multiple_day']) && $booking_settings['booking_enable_multiple_day'] != 'on' && isset($booking_settings['booking_recurring_booking']) && $booking_settings['booking_recurring_booking'] == 'on')
				{          
				for($i = 0;; $i++)
				{
					//echo $min_day;
					if(isset($recurring_date['booking_weekday_'.$min_day]) && $recurring_date['booking_weekday_'.$min_day] == 'on')
					{
						//echo $default_date_recurring;
						//print_r($holiday_array);
						if(in_array($default_date_recurring, $holiday_array) || in_array($default_date_recurring,$global_holidays))
						{
							if($min_day < 6)
							{
								$min_day = $min_day + 1;
							}
							else
							{
								$min_day = $min_day - $min_day;
							}
							$default_date_recurring = date('j-n-Y', strtotime('+1day',strtotime($default_date_recurring)));
							//echo $default_date_recurring;
						}
						else
						{
							break;
						}
						
					}
					else
					{
						if($min_day < 6)
						{
							$min_day = $min_day + 1;
						}
						else
						{
							$min_day = $min_day - $min_day;
						}
						$default_date_recurring =  date('j-n-Y', strtotime('+1day',strtotime($default_date_recurring)));
						/*if($min_day > $i)
						{
							if(isset($recurring_date['booking_weekday_'.$i]) && $recurring_date['booking_weekday_'.$i] == 'on')
							{
								//echo $i;
								$first_enable_day = $i - $min_day ;                             
								$default_date_recurring =  date('j-n-Y', strtotime('+'.$first_enable_day.' day',strtotime($min_date)));
								//echo $default_date_recurring;
								if(in_array($default_date_recurring, $holiday_array) || in_array($default_date_recurring,$global_holidays))
								{
								}
								else
								{
									break;
								}
							}
						}
						else if($min_day < $i)
						{
							if(isset($recurring_date['booking_weekday_'.$i]) && $recurring_date['booking_weekday_'.$i] == 'on')
							{//echo $i;
                        	    $first_enable_day =  $min_day - 1 ;                           
								$default_date_recurring =  date('j-n-Y', strtotime('+'.$first_enable_day.' day',strtotime($min_date)));
								var_dump($default_date_recurring);
                            	if(in_array($default_date_recurring, $holiday_array) || in_array($default_date_recurring,$global_holidays))
								{
								}
								else
								{
									break;
								}
							}
						}
						else
						{	
							if($recurring_date['booking_weekday_'.$i] == 'on')
							{
								//echo $i;
								$first_enable_day = $i + $min_day -1 ;
								$default_date_recurring =  date('j-n-Y', strtotime('+'.$first_enable_day.' day',strtotime($min_date)));
								echo $default_date_recurring;
								if(in_array($default_date_recurring, $holiday_array) || in_array($default_date_recurring,$global_holidays))
								{
								}
								else
								{	
									break;
								}                            
							}
							//echo "ehre";
						}*/	
					}
				}
				}
				//echo $default_date_recurring;
				if($first_enable_day != '' && $booking_settings['booking_recurring_booking'] == 'on' && $booking_settings['booking_specific_booking'] == 'on')
				{
					$default_date_recurring= date('d-m-Y', strtotime('+'.$first_enable_day.' day',strtotime($min_date)));
                                        
                                       
					if(strtotime($default_date_recurring) < strtotime($min_specific))
					{
						$default_date  = $default_date_recurring;
					}
					else
					{
						$default_date = $min_specific;
                                                
					}
				}
				else if(isset($booking_settings['booking_specific_booking']) && $booking_settings['booking_specific_booking'] == 'on' && $booking_settings['booking_recurring_booking'] != 'on')
				{
					$default_date = $min_specific;
                                        
				}
				else if(isset($booking_settings['booking_recurring_booking']) && $booking_settings['booking_recurring_booking'] == 'on' && $booking_settings['booking_specific_booking'] != 'on')
				{
					//echo $min_date;
					
					$default_date  = $default_date_recurring;
                                      // var_dump($default_date);
					/*if(in_array(date('j-n-Y', strtotime($default_date, $holiday_array)) || in_array(date('j-n-Y', strtotime($default_date, $)))
					{

					}*/
				}
                                
                //var_dump($default_date);
				print("<input type='hidden' id='wapbk_hidden_default_date' name='wapbk_hidden_default_date' value='".$default_date."'/>");
                                
				if ( isset($booking_settings['booking_enable_date']) && $booking_settings['booking_enable_date'] == 'on' )
				{
					print ('<label style="margin-top:5em;">'.get_option("book.date-label").': </label><input type="text" id="booking_calender" name="booking_calender" class="booking_calender" style="cursor: text!important;" readonly/>
							<img src="'.plugins_url().'/woocommerce-booking/images/cal.gif" width="20" height="20" style="cursor:pointer!important;" id ="checkin_cal"/><div id="inline_calendar"></div>
						');
					//$show_checkout_date_calendar = 1;
					$options_checkin = $options_checkout = array();
					$options_checkin_calendar = '';
					if (isset($booking_settings['booking_enable_multiple_day']) && $booking_settings['booking_enable_multiple_day'] == 'on')
					{
						print ('<label>'.get_option("checkout.date-label").': </label><input type="text" id="booking_calender_checkout" name="booking_calender_checkout" class="booking_calender" style="cursor: text!important;" readonly/>
									<img src="'.plugins_url().'/woocommerce-booking/images/cal.gif" width="20" height="20" style="cursor:pointer!important;" id ="checkout_cal"/><div id="inline_calendar_checkout"></div>
								');
						if (isset($booking_settings['enable_inline_calendar']) && $booking_settings['enable_inline_calendar'] == 'on')
						{
							//echo "here";exit;
							$options_checkout[] = "minDate: 1";
							$options_checkin_calendar = 'jQuery("#inline_calendar").datepicker("option", "onSelect",function(date,inst) 
							{
								var monthValue = inst.selectedMonth+1;
								var dayValue = inst.selectedDay;
								var yearValue = inst.selectedYear;

								var current_dt = dayValue + "-" + monthValue + "-" + yearValue;
								//alert(current_dt);
								if(jQuery("#wapbk_same_day").val() == "on")
								{
									if (current_dt != "")
									{
										var split = current_dt.split("-");
										split[1] = split[1] - 1;
										var minDate = new Date(split[2],split[1],split[0]);
										minDate.setDate(minDate.getDate());
								
										jQuery( "#inline_calendar_checkout" ).datepicker( "option", "minDate", minDate);
									}
								}
								else
								{	
									if (current_dt != "")
									{
										var split = current_dt.split("-");
										split[1] = split[1] - 1;
										var minDate = new Date(split[2],split[1],split[0]);
									
										minDate.setDate(minDate.getDate() + 1);
								
										jQuery( "#inline_calendar_checkout" ).datepicker( "option", "minDate", minDate);
									}
								}
								jQuery("#wapbk_hidden_date").val(current_dt);
							});';
							$options_checkout[] = "onSelect: get_per_night_price";
							$options_checkin[] = "onSelect: set_checkin_date";
							$options_checkout[] = "beforeShowDay: check_booked_dates";
							$options_checkin[] = "beforeShowDay: check_booked_dates";
						}
						else
						{
							$options_checkout[] = "minDate: 1";
							$options_checkin[] = 'onClose: function( selectedDate ) {
													if (jQuery("#block_option_enabled").val()=="on")
													{
														//alert();
														//jQuery("#show_time_slot").show();
														var nod= parseInt(jQuery("#block_option_number_of_day").val(),10);										
														//alert(nod);
														if (jQuery("#wapbk_hidden_date").val() != "")
														{
															
															var num_of_day= jQuery("#block_option_number_of_day").val();
															var split = jQuery("#wapbk_hidden_date").val().split("-");
															split[1] = split[1] - 1;		
															var minDate = new Date(split[2],split[1],split[0]);
									
															minDate.setDate(minDate.getDate() + nod ); 
															
															//var kc = new Date(Date.parse(selectedDate)); 
															
															//kc.setDate(kc.getDate() + nod );
 			 												//var strDate = kc.getDate() + "-" + (kc.getMonth() + 1) + "-" + objDate.getFullYear();
															//var newDate = kc.toDateString(); 
															  
															 //newDate = new Date( Date.parse( newDate ) );
			   												 console.log(minDate);
														 
			   												jQuery("#booking_calender_checkout").datepicker("setDate",minDate);

 			   												
															//var objDate= jQuery( "#booking_calender_checkout").datepicker("getDate");
															//var strDate = objDate.getDate() + "-" + (objDate.getMonth() + 1) + "-" + objDate.getFullYear();
															
															 //jQuery("#wapbk_hidden_date_checkout").val(strDate);
															
															 calculate_price();
															 
															 // disabled calendar	
															 //jQuery( "#booking_calender_checkout" ).prop("disabled", true);
															 //jQuery("#show_time_slot").hide();
															 //jQuery(".amount").hide();
														}
													}
													else
													{
														if (jQuery("#wapbk_hidden_date").val() != "")
														{				
															if(jQuery("#wapbk_same_day").val() == "on")
															{
																if (jQuery("#wapbk_hidden_date").val() != "")
																{				
																	var split = jQuery("#wapbk_hidden_date").val().split("-");
																	split[1] = split[1] - 1;		
																	var minDate = new Date(split[2],split[1],split[0]);
									
																	minDate.setDate(minDate.getDate()); 
									
																	jQuery( "#booking_calender_checkout" ).datepicker( "option", "minDate", minDate);
																}
															}
															else
															{
																var split = jQuery("#wapbk_hidden_date").val().split("-");
																split[1] = split[1] - 1;		
																var minDate = new Date(split[2],split[1],split[0]);
															
																minDate.setDate(minDate.getDate() + 1); 
									
																jQuery( "#booking_calender_checkout" ).datepicker( "option", "minDate", minDate);
															}
														}
													}
										}';
							$options_checkout[] = "onSelect: get_per_night_price";
							$options_checkin[] = "onSelect: set_checkin_date";
							$options_checkout[] = "beforeShowDay: check_booked_dates";
							$options_checkin[] = "beforeShowDay: check_booked_dates";
						}
					}
					else 
					{
						$options_checkin[] = "beforeShowDay: show_book";
						$options_checkin[] = "onSelect: show_times";
					}
					
					$options_checkin_str = '';
					if (count($options_checkin) > 0)
					{
						$options_checkin_str = implode(',', $options_checkin);
					}
					//echo $options_checkin_str;
					
					$options_checkout_str = '';
					if (count($options_checkout) > 0)
					{
						$options_checkout_str = implode(',', $options_checkout);
					}
					
					$product = get_product($post->ID);
					$product_type = $product->product_type;
					
					//echo 'here <pre>';print_r($woocommerce);echo '</pre>';
					//echo 'here <pre>';print_r($post);echo '</pre>';
					//echo 'here <pre>';print_r($product);echo '</pre>';
					$attribute_change_var = '';
					if ($product_type == 'variable')
					{
						$variations = $product->get_available_variations();
						$attributes = $product->get_variation_attributes();
						//echo 'vars are <pre>';print_r($variations);echo '</pre>';
						//echo 'vars are <pre>';print_r($attributes);echo '</pre>';
						$attribute_fields_str = "";
						$attribute_name = "";
						$attribute_value = "";
						$attribute_value_selected = "";
						$attribute_fields = array();
						$i = 0;
						foreach ($variations as $var_key => $var_val)
						{
							foreach ($var_val['attributes'] as $a_key => $a_val)
							{
								if (!in_array($a_key, $attribute_fields))
								{
									$attribute_fields[] = $a_key;
									$attribute_fields_str .= ",\"$a_key\": jQuery(\"[name='$a_key']\").val() ";
									$key = str_replace("attribute_","",$a_key);
									$attribute_value .= "attribute_values =  attribute_values + '|' + jQuery('#".$key."').val();";
									$attribute_value_selected .= "attribute_selected =  attribute_selected + '|' + jQuery('#".$key." :selected').text();";
									$on_change_attributes[] = $a_key;
								}
								$i++;
							}
						}
					//echo 'default are ';echo $attribute_fields_str;echo '</pre>';
					$on_change_attributes_str = implode(',#',$on_change_attributes);
					//$on_change_attributes_str = str_replace("attribute_","",$on_change_attributes_str);
					$on_change_attributes_str = settype(str_replace("attribute_","",$on_change_attributes_str),'string');
					//$attribute_change_var = 'jQuery("number-of-adults,#number-of-children").change(function()

					$attribute_change_var = 'jQuery(document).on("change",jQuery("#'.$on_change_attributes_str.'"),function()
					{
						if (jQuery("#wapbk_hidden_date").val() != "" && jQuery("#wapbk_hidden_date_checkout").val() != "") calculate_price();
					});';
						//echo $attribute_change_var;
						print("<input type='hidden' id='wapbk_hidden_booked_dates' name='wapbk_hidden_booked_dates'/>");					
						print("<input type='hidden' id='wapbk_hidden_booked_dates_checkout' name='wapbk_hidden_booked_dates_checkout'/>");
						
					}
					elseif ($product_type == 'simple')
					{
						$attribute_fields_str = ",\"tyche\": 1";
					}
					
					$js_code = $blocked_dates_hidden_var = '';
					$block_dates = array();
					$block_dates = (array) apply_filters( 'bkap_block_dates', $post->ID , $blocked_dates );
					//print_r($block_dates);exit;	
					if (isset($block_dates) && count($block_dates) > 0)
					{
						$i = 1;
						$bvalue = array();
						$add_day = '';
						$same_day = '';
						$date_label = '';
						foreach ($block_dates as $bkey => $bvalue)
						{
							if (isset($bvalue['dates']) && count($bvalue['dates']) > 0) $blocked_dates_str = '"'.implode('","', $bvalue['dates']).'"';
							else $blocked_dates_str = "";
						/*	if ( ( isset($bvalue['field_name']) && $bvalue['field_name'] == '' )  || !isset($bvalue['field_name']) )	$bvalue['field_name'] = $i;
							$fld_name = 'woobkap_'.str_replace(' ','_', $bvalue['field_name']);*/
							$field_name = $i;
							if ( ( isset($bvalue['field_name']) && $bvalue['field_name'] != '' ) ) $field_name = $bvalue['field_name'];
							$fld_name = 'woobkap_'.str_replace(' ','_', $field_name);
							//echo $fld_name;
							$blocked_dates_hidden_var .= "<input type='hidden' id='".$fld_name."' name='".$fld_name."' value='".$blocked_dates_str."'/>";
							$i++;
							if(isset($bvalue['add_days_to_charge_booking']))
								$add_day = $bvalue['add_days_to_charge_booking'];
							if($add_day == '')
							{
								$add_day = 0;
							}
							if(isset($bvalue['same_day_booking']))
								$same_day = $bvalue['same_day_booking'];
							else
								$same_day = '';
							print("<input type='hidden' id='wapbk_same_day' name='wapbk_same_day' value='".$same_day."'/>");
						}
					
					//	if (!isset($bvalue['date_label'])) $bvalue['date_label'] = 'Unavailable for Booking';
						if (isset($bvalue['date_label']) && $bvalue['date_label'] != '') 
							$date_label = $bvalue['date_label'];
						else
							$date_label = 'Unavailable for Booking';
						
						//if (isset($bvalue['add_days_to_charge_booking']) && $bvalue['add_days_to_charge_booking'] == '') $add_day = 0;
						
						$js_code = '
						var '.$fld_name.' = eval("["+jQuery("#'.$fld_name.'").val()+"]");
						for (i = 0; i < '.$fld_name.'.length; i++)
						{
						if( jQuery.inArray(d + "-" + (m+1) + "-" + y,'.$fld_name.') != -1 )
						{
						return [false, "", "'.$date_label.'"];
					}
					}
					';
						$js_block_date  = '
							var '.$fld_name.' = eval("["+jQuery("#'.$fld_name.'").val()+"]");
							var date = new_end = new Date(CheckinDate);
							var m = date.getMonth(), d = date.getDate(), y = date.getFullYear();
							//alert(count);
							for (var i = 1; i<= count;i++)
							{
								if( jQuery.inArray(d + "-" + (m+1) + "-" + y,'.$fld_name.') != -1 )
								{
									jQuery("#wapbk_hidden_date_checkout").val("");
									jQuery("#booking_calender_checkout").val("");
									jQuery( ".single_add_to_cart_button" ).hide();
									jQuery( ".quantity" ).hide();
									CalculatePrice = "N";
									alert("Some of the dates in the selected range are on rent. Please try another date range.");
									break;
								}
								new_end = new Date(ad(new_end,1));
								//alert(new_end);
								var m = new_end.getMonth(), d = new_end.getDate(), y = new_end.getFullYear();
							}';
					}
						
			/*		print ('<div id="show_time_slot" name="show_time_slot" class="show_time_slot"> </div>
							<input type="hidden" id="multiple_day_booking" name="multiple_day_booking" value="'.$booking_settings['booking_enable_multiple_day'].'"/>*/
					
					print ('<div id="show_time_slot" name="show_time_slot" class="show_time_slot"> </div>
							<input type="hidden" id="total_price_calculated" name="total_price_calculated"/>
							<input type="hidden" id="wapbk_multiple_day_booking" name="wapbk_multiple_day_booking" value="'.$booking_settings['booking_enable_multiple_day'].'"/>');
						//	do_action('bkap_display_updated_price',$post->ID,$_POST);
					if (!isset($booking_settings['booking_enable_multiple_day'])){
							
						do_action('bkap_display_price_div',$post->ID);
					}
						
					if (isset($booking_settings['booking_enable_multiple_day']) && $booking_settings['booking_enable_multiple_day'] != "on")
					{
						/*if(isset($booking_settings['booking_recurring_booking']) && $booking_settings['booking_recurring_booking'] == 'on')
						{
							print ('<div id="show_prices" name="show_prices" class="show_prices"> </div>
									<input type="hidden" id="recurring_price" name="recurring_price"/>
								');
							$currency_symbol = get_woocommerce_currency_symbol();
							print("<input type='hidden' id='wapbk_currency' name='wapbk_currency' value='".$currency_symbol."'/>");
							//echo $currency_symbol;
							$quantity_change_var =  'jQuery("form.cart").on("change", "input.qty", function()
							{
								alert();
								//show_times();
								//calculate_price();
							});';
							echo $quantity_change_var;
						}*/
						$type_of_slot = apply_filters('bkap_slot_type',$post->ID);
						if(isset($type_of_slot) && $type_of_slot != 'multiple')
						{
							do_action('bkap_display_price_div',$post->ID);
						}
						$currency_symbol = get_woocommerce_currency_symbol();
						$addon_price = 'var data = {
						id: '.$duplicate_of.',
						details: jQuery("#wapbk_hidden_date").val(),
						action: "call_addon_price"
						'.$attribute_fields_str.'
						};
					
						jQuery.post("'.get_admin_url().'/admin-ajax.php", data, function(amt)
						{
						//		alert("Got this from the server: " + amt);
						if (jQuery("#wapbk_round_price").val() == "yes")
						{
							var price = Math.round(amt);
						}
						else
						{
							var price = parseFloat(amt).toFixed(2);
						}
						jQuery("#show_addon_price").html("'.$currency_symbol.'" + price);
						});';
						if ($product_type == 'variable')
						{
							$attribute_change_single_day_var = 'jQuery(document).on("change",jQuery("#'.$on_change_attributes_str.'"),function()
							{
							if (jQuery("#wapbk_hidden_date").val() != "")
							{
							'.$addon_price.'
						}
						});';
						}
						else
							$attribute_change_single_day_var = '';

						$do_slot = 'jQuery("input[name=\"timeslot[]\"]").change(function()
						{
							var seasonal = jQuery("#seasonal").val();
							if(seasonal == "yes")
							{
								var adjustment = eval("["+jQuery("#adjustment").val()+"]");
								var value = jQuery("#adjustment_amount_or_percent").val();
								var operator = jQuery("#adjustment_operator").val();
								var operator_array = operator.split(",");
								var value_array = value.split(",");
								var count_value = adjustment.length;
							}
							var length = jQuery("group1 input[type=checkbox]:checked").length;
							var id = this.id;
							var product_price = parseInt(jQuery("#wapbk_price").val());
							//alert(product_price);
							var symbol = jQuery("#wapbk_symbol").val();
							var price = jQuery("#show_price").html();
							price = price.replace(symbol,"");
							var new_price = parseInt(price);
							var sold_individually = jQuery("#wapbk_sold_individually").val();
							if(jQuery("input[name=\"timeslot[]\"]:checked").length > 0)
							{
								jQuery( ".single_add_to_cart_button" ).show();
								if(sold_individually == "yes")
								{
									jQuery( ".quantity" ).hide();
								}
								else
								{
									jQuery( ".quantity" ).show();
								}
							}
							else
							{
								jQuery( ".single_add_to_cart_button" ).hide();
								jQuery( ".quantity" ).hide();
							}
							if ( jQuery("#"+ id).is(":checked"))
							{
								if(seasonal == "yes")
								{
									var price_new = new_price + product_price;
									for(var i=0;i<count_value;i++)
									{
										if(value_array[i] == "percent")
										{
											adjustment[i] = adjustment[i] * product_price;
											if(operator_array[i] == "add")
											{
												price_new = price_new + adjustment[i];
											}
											else if(operator_array[i] == "subtract")
											{
												price_new = price_new + adjustment[i];
											}
										}
										else if(value_array[i] == "amount")
										{
											if(operator_array[i] == "add")
											{
												price_new = price_new + adjustment[i];
											}
											else
											{
												price_new = price_new + adjustment[i];
											}								
										}
										else
										{
											var price_new = new_price + product_price;
										}
									}	
								}
								else
								{
									var price_new = new_price + product_price;
								}
							}
							else
							{
								if(seasonal == "yes")
								{
									var price_new = new_price - product_price;
									for(var i=0;i<count_value;i++)
									{
										if(value_array[i] == "percent")
										{
											adjustment[i] = adjustment[i] * product_price;
											if(operator_array[i] == "add")
											{
												price_new = price_new - adjustment[i];
											}
											else if(operator_array[i] == "subtract")
											{
												price_new = price_new - adjustment[i];
											}
										}
										else if(value_array[i] == "amount")
										{
											if(operator_array[i] == "add")
											{
												price_new = price_new - adjustment[i];
											}
											else
											{
												price_new = price_new - adjustment[i];
											}								
										}
										else
										{
											var price_new = new_price - product_price;
										}
									}	
								}	
								else
								{
									var price_new = new_price - product_price;
								}
							}
						jQuery("#show_price").html(symbol+" "+price_new);
						jQuery("#wapbk_hidden_price").val(price_new);
						});';
						$quantity_change_var = '';
					}
					else
					{
						$addon_price = "";
						$attribute_change_single_day_var = "";
						$do_slot = "";
						$currency_symbol = get_woocommerce_currency_symbol();
						print("<input type='hidden' id='wapbk_currency' name='wapbk_currency' value='".$currency_symbol."'/>");
						//echo $currency_symbol;
						$quantity_change_var =  'jQuery("form.cart").on("change", "input.qty", function() 
						{
							calculate_price();
						});';
					}
					$day_selected = "";
					//$global_settings = json_decode(get_option('woocommerce_booking_global_settings'));
					if(isset($global_settings->booking_calendar_day))
					{
						$day_selected = $global_settings->booking_calendar_day;
					}
					else
					{
						$day_selected = get_option("start_of_week");
					}
					
					if (isset($booking_settings['enable_inline_calendar']) && $booking_settings['enable_inline_calendar'] == 'on'){
						$current_language = json_decode(get_option('woocommerce_booking_global_settings'));
                       //echo "in inline calendar";
						if (isset($current_language))

						{

							$curr_lang = $current_language->booking_language;

						}else
							$curr_lang = "en-GB";
							$hidden_date = '';
							$hidden_date_checkout = '';
							global $bkap_block_booking;
								$number_of_fixed_price_blocks = $bkap_block_booking->get_fixed_blocks_count($post->ID);
								if (isset($booking_settings['booking_partial_payment_enable']) && $booking_settings['booking_partial_payment_enable'] =='yes' && $booking_settings['booking_partial_payment_radio'] == 'value' && is_plugin_active('bkap-deposits/deposits.php') && !isset($booking_settings['booking_fixed_block_enable']) && !isset($booking_settings['booking_block_price_enable']))
									$price_value = 'if(sold_individually == "yes")
													{
														var total_price = parseFloat(response);
													}
													else
													{
														var total_price = parseFloat(response) * parseInt(quantity);
													}';
								elseif (isset($booking_settings['booking_partial_payment_enable']) && $booking_settings['booking_partial_payment_enable'] =='yes' && $booking_settings['booking_partial_payment_radio']=='percent' && is_plugin_active('bkap-deposits/deposits.php') && !isset($booking_settings['booking_fixed_block_enable']) && !isset($booking_settings['booking_block_price_enable']))
									$price_value = 'if(sold_individually == "yes")
													{
														var total_price = parseInt(diffDays) * parseFloat(response);
													}
													else
													{
														var total_price = parseInt(diffDays) * parseFloat(response) * parseInt(quantity);
													}';
								elseif (isset($booking_settings['booking_fixed_block_enable']) && $booking_settings['booking_fixed_block_enable'] =='yes' && (isset($number_of_fixed_price_blocks) && $number_of_fixed_price_blocks > 0))
									$price_value = 'if(sold_individually == "yes")
													{
														var total_price = parseFloat(response);
													}
													else
													{
														var total_price = parseFloat(response) * parseInt(quantity);
													}';
								else
									$price_value = 'if(sold_individually == "yes")
													{
														var total_price = parseInt(diffDays) * parseFloat(response);
													}
													else
													{
														var total_price = parseInt(diffDays) * parseFloat(response) * parseInt(quantity);
													}';
							if (isset($global_settings->booking_global_selection) && $global_settings->booking_global_selection == "on")
							{
								foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $values )
								{
									//print_r($values);exit;
									if(array_key_exists('booking',$values))
									{
										$booking = $values['booking'];
										$hidden_date = $booking[0]['hidden_date'];
										if(array_key_exists("hidden_date_checkout",$booking[0]))
										{
											$hidden_date_checkout = $booking[0]['hidden_date_checkout'];
										}
									}
									break;
									//print_r($hidden_date_checkout);
								}
							}
							print('<input type="hidden" id="wapbk_hidden_date" name="wapbk_hidden_date" value="'.$hidden_date.'"/>
							<input type="hidden" id="wapbk_hidden_date_checkout" name="wapbk_hidden_date_checkout" value="'.$hidden_date_checkout.'"/>
							<input type="hidden" id="wapbk_diff_days" name="wapbk_diff_days" />
							'.$blocked_dates_hidden_var.'
							<div id="ajax_img" name="ajax_img"> <img src="'.plugins_url().'/woocommerce-booking/images/ajax-loader.gif"> </div>
						<script type="text/javascript">
						jQuery( "#ajax_img" ).hide();
						jQuery(document).ready(function()
						{
						'.$attribute_change_var.' 
						'.$quantity_change_var.'
						'.$attribute_change_single_day_var.' 
						var formats = ["d.m.y", "d-m-yy","MM d, yy"];
						var split = jQuery("#wapbk_hidden_default_date").val().split("-");
						split[1] = split[1] - 1;		
						var default_date = new Date(split[2],split[1],split[0]);
						//alert(default_date);
                        //alert(default_date);
						jQuery.extend(jQuery.datepicker, { afterShow: function(event)
						{
							jQuery.datepicker._getInst(event.target).dpDiv.css("z-index", 9999);
						}});
						jQuery(function() {
						jQuery("#inline_calendar").datepicker({
                                                        
							beforeShow: avd,
                            defaultDate: default_date,
							minDate:jQuery("#wapbk_minimumOrderDays").val(),
							maxDate:jQuery("#wapbk_number_of_dates").val(),
							altField: "#booking_calender",
							dateFormat: "'.$global_settings->booking_date_format.'",
							numberOfMonths: parseInt('.$global_settings->booking_months.'),
							'.$options_checkin_str.' ,
							}).focus(function (event)
							{
								jQuery.datepicker.afterShow(event);
							});
                            //alert(default_date);
							if(jQuery("#wapbk_global_selection").val() == "yes" && jQuery("#block_option_enabled").val() != "on")
							{
								var split = jQuery("#wapbk_hidden_date").val().split("-");
								split[1] = split[1] - 1;		
								var CheckinDate = new Date(split[2],split[1],split[0]);
								var timestamp = Date.parse(CheckinDate); 
								if (isNaN(timestamp) == false) 
								{ 
									var default_date_selection = new Date(timestamp);
									jQuery("#inline_calendar").datepicker("setDate",default_date_selection);
								}
							}
							jQuery("#inline_calendar").datepicker("option",jQuery.datepicker.regional[ "'.$curr_lang.'" ]);
							jQuery("#inline_calendar").datepicker("option", "dateFormat","'.$global_settings->booking_date_format.'");
							jQuery("#inline_calendar").datepicker("option", "firstDay","'.$day_selected.'");
							//jQuery("#inline_calendar").datepicker("option", "defaulDate",default_date);
							'.$options_checkin_calendar.'
							});
							jQuery("#ui-datepicker-div").wrap("<div class=\"hasDatepicker\"></div>");');
}
							else {
								
								global $bkap_block_booking;
								$number_of_fixed_price_blocks = $bkap_block_booking->get_fixed_blocks_count($post->ID);
								if (isset($booking_settings['booking_partial_payment_enable']) && $booking_settings['booking_partial_payment_enable'] =='yes' && $booking_settings['booking_partial_payment_radio'] == 'value' && is_plugin_active('bkap-deposits/deposits.php') && !isset($booking_settings['booking_fixed_block_enable']) && !isset($booking_settings['booking_block_price_enable']))
									$price_value = 'if(sold_individually == "yes")
													{
														var total_price = parseFloat(response);
													}
													else
													{
														var total_price = parseFloat(response) * parseInt(quantity);
													}';
								elseif (isset($booking_settings['booking_partial_payment_enable']) && $booking_settings['booking_partial_payment_enable'] =='yes' && $booking_settings['booking_partial_payment_radio']=='percent' && is_plugin_active('bkap-deposits/deposits.php') && !isset($booking_settings['booking_fixed_block_enable']) && !isset($booking_settings['booking_block_price_enable']))
									$price_value = 'if(sold_individually == "yes")
													{
														var total_price = parseInt(diffDays) * parseFloat(response);
													}
													else
													{
														var total_price = parseInt(diffDays) * parseFloat(response) * parseInt(quantity);
													}';
								elseif (isset($booking_settings['booking_fixed_block_enable']) && $booking_settings['booking_fixed_block_enable'] =='yes' && (isset($number_of_fixed_price_blocks) && $number_of_fixed_price_blocks > 0))
									$price_value = 'if(sold_individually == "yes")
													{
														var total_price = parseFloat(response);
													}
													else
													{
														var total_price = parseFloat(response) * parseInt(quantity);
													}';
								else
									$price_value = 'if(sold_individually == "yes")
													{
														var total_price = parseInt(diffDays) * parseFloat(response);
													}
													else
													{
														var total_price = parseInt(diffDays) * parseFloat(response) * parseInt(quantity);
													}';
								$hidden_date = '';
								$hidden_date_checkout = '';
								//echo $price_value;
								if (isset($global_settings->booking_global_selection) && $global_settings->booking_global_selection == "on")
								{
									foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $values )
									{
										//print_r($values);exit;
										if(array_key_exists('booking',$values))
										{
											$booking = $values['booking'];
											$hidden_date = $booking[0]['hidden_date'];
											if(array_key_exists("hidden_date_checkout",$booking[0]))
											{
												$hidden_date_checkout = $booking[0]['hidden_date_checkout'];
											}
										}
										break;
										//print_r($hidden_date_checkout);
									}
								}
								print('<input type="hidden" id="wapbk_hidden_date" name="wapbk_hidden_date" value="'.$hidden_date.'"/>
							<input type="hidden" id="wapbk_hidden_date_checkout" name="wapbk_hidden_date_checkout" value="'.$hidden_date_checkout.'"/>
							<input type="hidden" id="wapbk_diff_days" name="wapbk_diff_days" />
							'.$blocked_dates_hidden_var.'
							<div id="ajax_img" name="ajax_img"> <img src="'.plugins_url().'/woocommerce-booking/images/ajax-loader.gif"> </div>
							<script type="text/javascript">
							jQuery( "#ajax_img" ).hide();
							jQuery(document).ready(function()
							{
								'.$attribute_change_var.' 
								'.$quantity_change_var.'
								'.$attribute_change_single_day_var.' 
								var formats = ["d.m.y", "d-m-yy","MM d, yy"];
                                var split = jQuery("#wapbk_hidden_default_date").val().split("-");
                                split[1] = split[1] - 1;		
                                var default_date = new Date(split[2],split[1],split[0]);
								//alert(default_date);
								jQuery.extend(jQuery.datepicker, { afterShow: function(event)
								{
									jQuery.datepicker._getInst(event.target).dpDiv.css("z-index", 9999);
								}});

								jQuery("#booking_calender").datepicker({
                                                                
									beforeShow: avd,
                                    defaultDate: default_date,
									dateFormat: "'.$global_settings->booking_date_format.'",
									numberOfMonths: parseInt('.$global_settings->booking_months.'),
									firstDay: parseInt('.$day_selected.'),
									'.$options_checkin_str.' ,
									}).focus(function (event)
									{
										jQuery.datepicker.afterShow(event);
									});
                                                                        
                                                                       //alert(default_date);
									if(jQuery("#wapbk_global_selection").val() == "yes" && jQuery("#block_option_enabled").val() != "on")
									{
										var split = jQuery("#wapbk_hidden_date").val().split("-");
										split[1] = split[1] - 1;		
										var CheckinDate = new Date(split[2],split[1],split[0]);
										var timestamp = Date.parse(CheckinDate); 
										if (isNaN(timestamp) == false) 
										{ 
											var default_date = new Date(timestamp);
											jQuery("#booking_calender").datepicker("setDate",default_date);
										}
									}
									jQuery("#ui-datepicker-div").wrap("<div class=\"hasDatepicker\"></div>");');
									print ('
										jQuery("#checkin_cal").click(function() {
										jQuery("#booking_calender").datepicker("show");
									});');
							}
							
					
					if ($booking_settings['booking_enable_multiple_day'] == 'on')
					{
						if (isset($booking_settings['enable_inline_calendar']) && $booking_settings['enable_inline_calendar'] == 'on')
						{
							print ('jQuery(document).ready(function()
									{
									jQuery("#inline_calendar_checkout").datepicker({
										dateFormat: "'.$global_settings->booking_date_format.'",
										numberOfMonths: parseInt('.$global_settings->booking_months.'),
										'.$options_checkout_str.' ,
										altField: "#booking_calender_checkout",
										onClose: function( selectedDate ) {
											jQuery( "#inline_calendar" ).datepicker( "option", "maxDate", selectedDate );
										},
										}).focus(function (event)
										{
											jQuery.datepicker.afterShow(event);
										});
										if(jQuery("#wapbk_global_selection").val() == "yes" && jQuery("#block_option_enabled").val() != "on")
										{
											var split = jQuery("#wapbk_hidden_date_checkout").val().split("-");
											split[1] = split[1] - 1;		
											var CheckoutDate = new Date(split[2],split[1],split[0]);
											var timestamp = Date.parse(CheckoutDate);
											if (isNaN(timestamp) == false) 
											{ 
												var default_date = new Date(timestamp);
												jQuery("#inline_calendar_checkout").datepicker("setDate",default_date);
												calculate_price();
											}
										}
										jQuery("#checkout_cal").click(function() {
										jQuery("#inline_calendar_checkout").datepicker("show");
								});
								jQuery("#inline_calendar_checkout").datepicker("option", "firstDay","'.$day_selected.'");
							});
							');
						}
						else
						{
							print ('jQuery("#booking_calender_checkout").datepicker({
								dateFormat: "'.$global_settings->booking_date_format.'",
								numberOfMonths: parseInt('.$global_settings->booking_months.'),
								firstDay: '.$day_selected.',
								'.$options_checkout_str.' , 
								onClose: function( selectedDate ) {
									jQuery( "#booking_calender" ).datepicker( "option", "maxDate", selectedDate );
								},
								}).focus(function (event)
								{
									jQuery.datepicker.afterShow(event);
								}); 
								if(jQuery("#wapbk_global_selection").val() == "yes" && jQuery("#block_option_enabled").val() != "on")
								{
									var split = jQuery("#wapbk_hidden_date_checkout").val().split("-");
									split[1] = split[1] - 1;		
									var CheckoutDate = new Date(split[2],split[1],split[0]);
									var timestamp = Date.parse(CheckoutDate);
									if (isNaN(timestamp) == false) 
									{ 
										var default_date = new Date(timestamp);
										jQuery("#booking_calender_checkout").datepicker("setDate",default_date);
										calculate_price();
									}
								}
								jQuery("#checkout_cal").click(function() {
								jQuery("#booking_calender_checkout").datepicker("show");
							});');
						}
					}
					
					$currency_symbol = get_woocommerce_currency_symbol();
					print('});
					function check_booked_dates(date)
					{
						var m = date.getMonth(), d = date.getDate(), y = date.getFullYear();
						var holidayDates = eval("["+jQuery("#wapbk_booking_holidays").val()+"]");
						var globalHolidays = eval("["+jQuery("#wapbk_booking_global_holidays").val()+"]");
						var bookedDates = eval("["+jQuery("#wapbk_hidden_booked_dates").val()+"]");	
						var bookedDatesCheckout = eval("["+jQuery("#wapbk_hidden_booked_dates_checkout").val()+"]");
						//alert(bookedDates);
                                                //alert(bookedDatesCheckout);
						var block_option_start_day= jQuery("#block_option_start_day").val();
					 	var block_option_price= jQuery("#block_option_price").val();
						for (iii = 0; iii < globalHolidays.length; iii++)
						{
							if( jQuery.inArray(d + "-" + (m+1) + "-" + y,globalHolidays) != -1 )
							{
								return [false, "", "'.__("Holiday","woocommerce-booking").'"];
							}
						}
						
						for (ii = 0; ii < holidayDates.length; ii++)
						{
							if( jQuery.inArray(d + "-" + (m+1) + "-" + y,holidayDates) != -1 )
							{
								return [false, "","'.__("Holiday","woocommerce-booking").'"];
							}
						}
						var id_booking = jQuery(this).attr("id");
						if (id_booking == "booking_calender" || id_booking == "inline_calendar")
						{
							for (iii = 0; iii < bookedDates.length; iii++)
							{
								//alert(bookedDates);
								if( jQuery.inArray(d + "-" + (m+1) + "-" + y,bookedDates) != -1 )
								{
									return [false, "", "'.__("Unavailable for Booking","woocommerce-booking").'"];
								}
							}
						}
							
						if (id_booking == "booking_calender_checkout" || id_booking == "inline_calendar_checkout") 
						{
							for (iii = 0; iii < bookedDatesCheckout.length; iii++)
							{
								//alert(bookedDates);
								if( jQuery.inArray(d + "-" + (m+1) + "-" + y,bookedDatesCheckout) != -1 )
								{
									return [false, "", "'.__("Unavailable for Booking","woocommerce-booking").'"];
								}
							}
						}
						var block_option_enabled= jQuery("#block_option_enabled").val();

						if (block_option_enabled =="on")
						{
							if ( id_booking == "booking_calender" || id_booking == "inline_calendar" )
							{
								if (block_option_start_day == date.getDay())
					            {
					              return [true];
					            }
					            else
					            {
					            	return [false];
					            }
				       		}

				       		var bcc_date=jQuery( "#booking_calender_checkout").datepicker("getDate");

							var dd = bcc_date.getDate();
							var mm = bcc_date.getMonth()+1; //January is 0!
							var yyyy = bcc_date.getFullYear();
							var checkout = dd + "-" + mm + "-"+ yyyy;
							jQuery("#wapbk_hidden_date_checkout").val(checkout);

				       		if (id_booking == "booking_calender_checkout" || id_booking == "inline_calendar_checkout"){

 
				       			if (Date.parse(bcc_date) === Date.parse(date)){
				       					return [true];
				       			}else{
				       					return [false];
				       			}
				       		}
				       	}
						'.$js_code.' 
						return [true];
					}

					function show_book(date)
					{
						//var date = new Date();
						var m = date.getMonth(), d = date.getDate(), y = date.getFullYear();
						// .html() is used when we have zip code groups enabled
						var deliveryDates = eval("["+jQuery("#wapbk_booking_dates").val()+"]");
						
						var holidayDates = eval("["+jQuery("#wapbk_booking_holidays").val()+"]");
							
						var globalHolidays = eval("["+jQuery("#wapbk_booking_global_holidays").val()+"]");
						
						//Lockout Dates
						var lockoutdates = eval("["+jQuery("#wapbk_lockout_days").val()+"]");
						
						var bookedDates = eval("["+jQuery("#wapbk_hidden_booked_dates").val()+"]");
						var dt = new Date();
						var today = dt.getMonth() + "-" + dt.getDate() + "-" + dt.getFullYear();
						for (iii = 0; iii < lockoutdates.length; iii++)
						{
							if( jQuery.inArray(d + "-" + (m+1) + "-" + y,lockoutdates) != -1 )
							{
								return [false, "", "'.__("Booked","woocommerce-booking").'"];
	
							}
						}	
						
						for (iii = 0; iii < globalHolidays.length; iii++)
						{
							if( jQuery.inArray(d + "-" + (m+1) + "-" + y,globalHolidays) != -1 )
							{
								return [false, "", "'.__("Holiday","woocommerce-booking").'"];
							}
						}
						
						for (ii = 0; ii < holidayDates.length; ii++)
						{
							if( jQuery.inArray(d + "-" + (m+1) + "-" + y,holidayDates) != -1 )
							{
								return [false, "","'.__("Holiday","woocommerce-booking").'"];
							}
						}
					
						for (i = 0; i < bookedDates.length; i++)
						{
							//alert(bookedDates);
							if( jQuery.inArray(d + "-" + (m+1) + "-" + y,bookedDates) != -1 )
							{
								return [false, "","'.__("Unavailable for Booking","woocommerce-booking").'"];
							}
						}
						'.$js_code.' 	
						for (i = 0; i < deliveryDates.length; i++)
						{
							if( jQuery.inArray(d + "-" + (m+1) + "-" + y,deliveryDates) != -1 )
							{
								return [true];
							}
						}

						var day = "booking_weekday_" + date.getDay();
						if (jQuery("#wapbk_"+day).val() == "on")
						{
							return [true];
						}
						return [false];
					}

					function show_times(date,inst)
					{
						var monthValue = inst.selectedMonth+1;
						var dayValue = inst.selectedDay;
						var yearValue = inst.selectedYear;

						var current_dt = dayValue + "-" + monthValue + "-" + yearValue;
						var sold_individually = jQuery("#wapbk_sold_individually").val();
						var quantity = jQuery("input[class=\"input-text qty text\"]").attr("value");
						//alert(quantity);
						jQuery("#wapbk_hidden_date").val(current_dt);
						/*if (jQuery("#wapbk_recurringDays").val() == "on" && jQuery("#wapbk_recurringDays").val() != "")
						{
							jQuery( "#ajax_img" ).show();
							var data = {
								current_date: current_dt,
								post_id: "'.$duplicate_of.'", 
								action: "check_for_prices"
								'.$attribute_fields_str.'
								};
										
								jQuery.post("'.get_admin_url().'/admin-ajax.php", data, function(response)
								{
									//	alert("Got this from the server: " + response);
									jQuery( "#ajax_img" ).hide();
									if(response != "")
									{
										if(sold_individually == "yes")
	                                    {
											var total_price = parseFloat(response);
										}
										else
										{
											var total_price = parseFloat(response) * parseInt(quantity);
										}
										jQuery("#show_prices").html("'.$currency_symbol.'" + total_price);
										jQuery("#recurring_price").val(total_price);
									}
								});
						}*/
						if (jQuery("#wapbk_bookingEnableTime").val() == "on" && jQuery("#wapbk_booking_times").val() != "")
						{
							jQuery( "#ajax_img" ).show();
								
							//jQuery.datepicker.formatDate("d-m-yy", new Date(yearValue, dayValue, monthValue) );
							var time_slots_arr = jQuery("#wapbk_booking_times").val();
							var data = {
								current_date: current_dt,
								post_id: "'.$duplicate_of.'", 
								action: "'.$method_to_show.'"
								'.$attribute_fields_str.'
								};
										
								jQuery.post("'.get_admin_url().'/admin-ajax.php", data, function(response)
								{
							//	alert("Got this from the server: " + response);
								jQuery( "#ajax_img" ).hide();
								jQuery("#show_time_slot").html(response);
								jQuery("#time_slot").change(function()
								{
									if ( jQuery("#time_slot").val() != "" )
									{
										jQuery( ".single_add_to_cart_button" ).show();
                                        jQuery( ".payment_type" ).show();
										if(sold_individually == "yes")
										{
											jQuery( ".quantity" ).hide();
                                            jQuery( ".payment_type" ).hide();
											jQuery(".partial_message").hide();
										}
										else
										{
											jQuery( ".quantity" ).show();
											jQuery( ".payment_type" ).show();
										}
							
									}
									else if ( jQuery("#time_slot").val() == "" )
									{
										jQuery( ".single_add_to_cart_button" ).hide();
										jQuery( ".quantity" ).hide();
                                        jQuery( ".payment_type" ).hide();
										jQuery(".partial_message").hide();
									}
								})
								'.$do_slot.'
							});
						}
						else
						{
							if ( jQuery("#wapbk_hidden_date").val() != "" )
							{
								var data = {
								current_date: current_dt,
								post_id: "'.$duplicate_of.'",
								action: "insert_date"
								'.$attribute_fields_str.'
								};
								jQuery.post("'.get_admin_url().'/admin-ajax.php", data, function(response)
								{
									jQuery( ".single_add_to_cart_button" ).show();
                                    jQuery( ".payment_type" ).show()
									if(sold_individually == "yes")
										{
											jQuery( ".quantity" ).hide();
										}
										else
										{
											jQuery( ".quantity" ).show();
										}
							
								});
							}
							else if ( jQuery("#wapbk_hidden_date").val() == "" )
							{
								jQuery( ".single_add_to_cart_button" ).hide();
								jQuery( ".quantity" ).hide();
                                jQuery( ".payment_type" ).hide()
								jQuery(".partial_message").hide();
							}
						}'.$addon_price.'
                                                    
                        set_partial_payment_deposit(monthValue,dayValue,yearValue);
					}
                                        
                    function set_partial_payment_deposit(monthValue,dayValue,yearValue)
					{
						//alert(monthValue);
						//alert(dayValue);
						//alert(yearValue);
						// set deposit x days
						var deposit_days_value =jQuery("#wapbk_hidden_deposit_days").val();	
						//alert(deposit_days_value);
						var diff = 1;
						var currentDate = new Date();
						var dd = currentDate.getDate();
						var mm = currentDate.getMonth()+1;
						var yy = currentDate.getFullYear();

	 					var dateDiff = function ( d1, d2 ) {
						    var diff = Math.abs(d1 - d2);
						    if (Math.floor(diff/86400000)) {
						        return Math.floor(diff/86400000) ;
						    /*} else if (Math.floor(diff/3600000)) {
						        return Math.floor(diff/3600000) + " hours";
						    } else if (Math.floor(diff/60000)) {
						        return Math.floor(diff/60000) + " minutes";
							*/ } else {
						        return 0;
						    }
						};
						var calendar_date=dayValue + "," + monthValue + "," + yearValue;
						var current_date=dd + "," + mm + "," + yy;
						var dt_interval= dateDiff(new Date(yy,mm,dd), new Date(yearValue,monthValue,dayValue)) // -> 12 days	
									 
						//  alert(dt_interval);
						// alert (deposit_days_value );
						//jQuery("input:radio[name=payment_type][disabled=true]:first").attr("checked", true);
						
					 	jQuery(".payment_type.partial input:radio").attr("disabled", false);
					 	jQuery(".partial_message").hide();

			
						if (! ( deposit_days_value=="" || deposit_days_value== 0) )
						{
						// console.log(dt_interval);
						// console.log(deposit_days_value);

						
							if (dt_interval < deposit_days_value){
								jQuery(".payment_type input:radio:not(:disabled):first-child").attr("checked", true);
								jQuery(".payment_type.partial input:radio").attr("disabled", true);
							 	jQuery(".partial_message").show();
								
							}
					 	}
					}
                    	
					function set_checkin_date(date,inst)
					{
						var monthValue = inst.selectedMonth+1;
						var dayValue = inst.selectedDay;
						var yearValue = inst.selectedYear;

						var current_dt = dayValue + "-" + monthValue + "-" + yearValue;
						jQuery("#wapbk_hidden_date").val(current_dt);
						// Check if any date in the selected date range is unavailable
						if (jQuery("#wapbk_hidden_date").val() != "" && jQuery("#wapbk_hidden_date_checkout").val() != "")
						{
							var CalculatePrice = "Y";
							var split = jQuery("#wapbk_hidden_date").val().split("-");
							split[1] = split[1] - 1;		
							var CheckinDate = new Date(split[2],split[1],split[0]);
								
							var split = jQuery("#wapbk_hidden_date_checkout").val().split("-");
							split[1] = split[1] - 1;
							var CheckoutDate = new Date(split[2],split[1],split[0]);
								
							var date = new_end = new Date(CheckinDate);
							var m = date.getMonth(), d = date.getDate(), y = date.getFullYear();
								
							var bookedDates = eval("["+jQuery("#wapbk_hidden_booked_dates").val()+"]");
							var holidayDates = eval("["+jQuery("#wapbk_booking_holidays").val()+"]");
							var globalHolidays = eval("["+jQuery("#wapbk_booking_global_holidays").val()+"]");
						
							var count = gd(CheckinDate, CheckoutDate, "days");
							//Locked Dates
							for (var i = 1; i<= count;i++)
								{
									if( jQuery.inArray(d + "-" + (m+1) + "-" + y,bookedDates) != -1 )
									{
										jQuery("#wapbk_hidden_date").val("");
										jQuery("#booking_calender").val("");
										jQuery( ".single_add_to_cart_button" ).hide();
										jQuery( ".quantity" ).hide();
										CalculatePrice = "N";
										alert("Some of the dates in the selected range are unavailable. Please try another date range.");
										break;
									}
								new_end = new Date(ad(new_end,1));
								var m = new_end.getMonth(), d = new_end.getDate(), y = new_end.getFullYear();													
								}
							//Global Holidays
							var date = new_end = new Date(CheckinDate);
							var m = date.getMonth(), d = date.getDate(), y = date.getFullYear();
						
							for (var i = 1; i<= count;i++)
								{
									if( jQuery.inArray(d + "-" + (m+1) + "-" + y,globalHolidays) != -1 )
									{
										jQuery("#wapbk_hidden_date").val("");
										jQuery("#booking_calender").val("");
										jQuery( ".single_add_to_cart_button" ).hide();
										jQuery( ".quantity" ).hide();
										CalculatePrice = "N";
										alert("Some of the dates in the selected range are unavailable. Please try another date range.");
										break;
									}
								new_end = new Date(ad(new_end,1));
								var m = new_end.getMonth(), d = new_end.getDate(), y = new_end.getFullYear();													
								}
							//Product Holidays
							var date = new_end = new Date(CheckinDate);
							var m = date.getMonth(), d = date.getDate(), y = date.getFullYear();
						
							for (var i = 1; i<= count;i++)
								{
									if( jQuery.inArray(d + "-" + (m+1) + "-" + y,holidayDates) != -1 )
									{
										jQuery("#wapbk_hidden_date").val("");
										jQuery("#booking_calender").val("");
										jQuery( ".single_add_to_cart_button" ).hide();
										jQuery( ".quantity" ).hide();
										CalculatePrice = "N";
										alert("Some of the dates in the selected range are unavailable. Please try another date range.");
										break;
									}
								new_end = new Date(ad(new_end,1));
								var m = new_end.getMonth(), d = new_end.getDate(), y = new_end.getFullYear();													
								}
							if (CalculatePrice == "Y") calculate_price();
						}
					}
					
					function get_per_night_price(date,inst)
					{
						var monthValue = inst.selectedMonth+1;
						var dayValue = inst.selectedDay;
						var yearValue = inst.selectedYear;

						var current_dt = dayValue + "-" + monthValue + "-" + yearValue;
						jQuery("#wapbk_hidden_date_checkout").val(current_dt);
						//set_partial_payment_deposit(monthValue,dayValue,yearValue);
						calculate_price();
					}
							
					function calculate_price()
					{
						// Check if any date in the selected date range is unavailable
						var CalculatePrice = "Y";				
						var split = jQuery("#wapbk_hidden_date").val().split("-");
						set_partial_payment_deposit(split[1],split[0],split[2]);
						split[1] = split[1] - 1;		
						var CheckinDate = new Date(split[2],split[1],split[0]);
						
						
						var split = jQuery("#wapbk_hidden_date_checkout").val().split("-");
						split[1] = split[1] - 1;
						var CheckoutDate = new Date(split[2],split[1],split[0]);
						//alert(CheckoutDate);
						var date = new_end = new Date(CheckinDate);
						var m = date.getMonth(), d = date.getDate(), y = date.getFullYear();
						
						var bookedDates = eval("["+jQuery("#wapbk_hidden_booked_dates").val()+"]");
						var holidayDates = eval("["+jQuery("#wapbk_booking_holidays").val()+"]");
						var globalHolidays = eval("["+jQuery("#wapbk_booking_global_holidays").val()+"]");
					
						var count = gd(CheckinDate, CheckoutDate, "days");
						//Locked Dates
						//alert(count);
						//alert(new_end);
						for (var i = 1; i<= count;i++)
							{
								if( jQuery.inArray(d + "-" + (m+1) + "-" + y,bookedDates) != -1 )
								{
									jQuery("#wapbk_hidden_date_checkout").val("");
									jQuery("#booking_calender_checkout").val("");
									jQuery( ".single_add_to_cart_button" ).hide();
									jQuery( ".quantity" ).hide();
									CalculatePrice = "N";
									alert("Some of the dates in the selected range are unavailable. Please try another date range.");
									break;
								}
								new_end = new Date(ad(new_end,1));
								var m = new_end.getMonth(), d = new_end.getDate(), y = new_end.getFullYear();
							}

						//Global Holidays
						var date = new_end = new Date(CheckinDate);
						var m = date.getMonth(), d = date.getDate(), y = date.getFullYear();
						//	alert(new_end);
						for (var i = 1; i<= count;i++)
							{
								if( jQuery.inArray(d + "-" + (m+1) + "-" + y,globalHolidays) != -1 )
								{
									jQuery("#wapbk_hidden_date_checkout").val("");
									jQuery("#booking_calender_checkout").val("");
									jQuery( ".single_add_to_cart_button" ).hide();
									jQuery( ".quantity" ).hide();
									CalculatePrice = "N";
									alert("Some of the dates in the selected range are unavailable. Please try another date range.");
									break;
								}
								new_end = new Date(ad(new_end,1));
								var m = new_end.getMonth(), d = new_end.getDate(), y = new_end.getFullYear();
							}
						//Product Holidays
						var date = new_end = new Date(CheckinDate);
						var m = date.getMonth(), d = date.getDate(), y = date.getFullYear();
						//	alert(new_end);
						for (var i = 1; i<= count;i++)
							{
								if( jQuery.inArray(d + "-" + (m+1) + "-" + y,holidayDates) != -1 )
								{
									jQuery("#wapbk_hidden_date_checkout").val("");
									jQuery("#booking_calender_checkout").val("");
									jQuery( ".single_add_to_cart_button" ).hide();
									jQuery( ".quantity" ).hide();
									CalculatePrice = "N";
									alert("Some of the dates in the selected range are unavailable. Please try another date range.");
									break;
								}
								new_end = new Date(ad(new_end,1));
								var m = new_end.getMonth(), d = new_end.getDate(), y = new_end.getFullYear();
							}
							'.$js_block_date.'
						// Calculate the price	
						if (CalculatePrice == "Y")
						{
							//alert(block_option_price);
							var oneDay = 24*60*60*1000; // hours*minutes*seconds*milliseconds
							var sold_individually = jQuery("#wapbk_sold_individually").val();
							var firstDate = CheckinDate;
							var secondDate = CheckoutDate;
							var value_charge = '.$add_day.';
							//alert(value_charge);
							var diffDays = Math.abs((firstDate.getTime() - secondDate.getTime())/(oneDay));
							//alert(diffDays);
							diffDays = diffDays + value_charge;
							jQuery("#wapbk_diff_days").val(diffDays);
							//alert(diffDays);
							var quantity = jQuery("input[class=\"input-text qty text\"]").attr("value");
							//alert(quantity);
							jQuery( "#ajax_img" ).show();
							
							//alert(jQuery(".attribute_number-of-adults").val());
							//alert(jQuery("[name=attribute_number-of-adults]").val());
							var data = {
									current_date: jQuery("#wapbk_hidden_date_checkout").val(),
									checkin_date: jQuery("#wapbk_hidden_date").val(),
									attribute_selected: jQuery("#wapbk_variation_value").val(),
									block_option_price: jQuery("#block_option_price").val(),
									post_id: "'.$duplicate_of.'", 
									action: "get_per_night_price",
									product_type: "'.$product_type.'"
									'.$attribute_fields_str.' 
									
								};
							jQuery.post("'.get_admin_url().'/admin-ajax.php", data, function(response)
							{
								jQuery( "#ajax_img" ).hide();
								
								if (isNaN(parseInt(response)))
								{
									jQuery("#show_time_slot").html(response)
								}
								else
								{
									if (jQuery("#block_option_enabled_price").val() == "on")
									{
										var split_str = response;
										var exploded = split_str.split("-");
										var price_type = exploded[1];
										//alert(exploded[0]);
										if(price_type == "fixed" || price_type == "per_day")
                                        {
											if(sold_individually == "yes")
                                            {
                                               var total_price = parseFloat(exploded[0]);
                                               alert(total_price);
                                            }
											else
                                            {
                                               var total_price = parseFloat(exploded[0]) * parseInt(quantity);
                                            }
					//		alert(total_price);
										}
										else
										{
											if(sold_individually == "yes")
	                                        {
												var total_price = parseInt(diffDays) * parseFloat(exploded[0]);
											}
											else
											{
												var total_price = parseInt(diffDays) * parseFloat(exploded[0]) * parseInt(quantity);
											}
										}
										jQuery("#block_variable_option_price").val(parseFloat(exploded[0])+","+price_type+","+exploded[2]);
									}
									else
									{
										'.$price_value.'
									}	
									//alert(diffDays);
									//alert(total_price);
									if (jQuery("#wapbk_round_price").val() == "yes")
									{
										var price = Math.round(total_price);
									}
									else if (jQuery("#wapbk_round_price").val() == "no")
									{
										var price = parseFloat(total_price).toFixed(2);
									}		
									//alert(price);
									jQuery("#show_time_slot").html("'.$currency_symbol.'" + price);
									jQuery("#total_price_calculated").val(price);
								}
								jQuery( ".single_add_to_cart_button" ).show();
                                jQuery( ".payment_type" ).show();
								if(sold_individually == "yes")
								{
									jQuery( ".quantity" ).hide();
								}
								else
								{
									jQuery( ".quantity" ).show();
								}
						//	alert(response);
	
							}); 
						}
					}
					</script>');
				
				}
				if (isset($booking_settings['booking_partial_payment_enable']) && $booking_settings['booking_partial_payment_enable'] == "yes")
				{
					if ( isset($booking_settings['allow_full_payment']) && $booking_settings['allow_full_payment'] == "yes")
					{		
						?>
						<label class="payment_type partial"><input type="radio" checked="" class="payment_type" name="payment_type" value="partial_payment"><?php __('Partial Payment','woocommerce-booking')?></label>
						<label class="payment_type full"><input type="radio" class="payment_type" name="payment_type" value="full_payment"> <?php __('Full Payment','woocommerce-booking')?></label>
						<?php 
						if (isset($global_settings->partial_payment_disabled_message)) { ?>
						<div class="partial_message">
						<p> 
							 <?php echo $global_settings->partial_payment_disabled_message ?>
						</p>


					</div>	
					<?php
					} // endif
					}
				}
				do_action("bkap_before_add_to_cart_button",$booking_settings);
			}
			/*function check_for_prices() 
			{
				//echo "here";
				$booking_settings = get_post_meta($_POST['post_id'],'woocommerce_booking_settings',true);
				//$recurring_prices = $booking_settings['booking_recurring_prices'];
				$day_to_check = date("w",strtotime($_POST['current_date']));
				$price = $recurring_prices['booking_weekday_'.$day_to_check.'_price'];
				//echo "here".$price;
				if($price == '' || $price == 0)
				{
					$product = get_product($_POST['post_id']);
					$product_type = $product->product_type;
					if ($product_type == 'variable')
					{
					//	print_r($_POST);
						$variation_id_to_fetch = $this->get_selected_variation_id($_POST['post_id'], $_POST);
						if ($variation_id_to_fetch != "")
						{
							$sale_price = get_post_meta( $variation_id_to_fetch, '_sale_price', true);
							if($sale_price == '')
							{
								$regular_price = get_post_meta( $variation_id_to_fetch, '_regular_price',true);
								echo $regular_price;
							}
							else
							{
								echo $sale_price;
							}
						}
						else echo "Please select an option."; 
					}
					elseif ($product_type == 'simple')
					{
						$sale_price = get_post_meta( $_POST['post_id'], '_sale_price', true);
						if($sale_price == '')
						{
							$regular_price = get_post_meta( $_POST['post_id'], '_regular_price',true);
							echo $regular_price;
						}
						else
						{
							echo $sale_price;
						}
					}
				}
				else
				{
					echo $price;
				}
				die();
			}
			*/
			function check_for_time_slot() {
				
				global $wpdb;

				$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
				if (isset($saved_settings))
				{
					$time_format = $saved_settings->booking_time_format;
				}
				else
				{
					$time_format = '12';
				}
			//	if ($time_format == '') $time_format = '12';

				// time format to be used for 'value' attributes of dropdowns is always 12-hour format
				$time_format_value = 'G:i';
				
				if ($time_format == '12')
				{
					$time_format_to_show = 'h:i A';
				}
				else 
				{
					$time_format_to_show = 'H:i';
				}

				$current_date = $_POST['current_date'];
				$date_to_check = date('Y-m-d', strtotime($current_date));
				$day_check = "booking_weekday_".date('w', strtotime($current_date));
				$from_time_value = '';
				$from_time = '';
				$post_id = $_POST['post_id'];
				$product = get_product($post_id);
				$product_type = $product->product_type;
				if ( $product_type == 'variable')
					$variation_id = $this->get_selected_variation_id($post_id, $_POST);
				else $variation_id = "";

				$check_query = "SELECT * FROM `".$wpdb->prefix."booking_history`
								WHERE start_date='".$date_to_check."'
								AND post_id='".$post_id."'
								AND available_booking > 0 ORDER BY STR_TO_DATE(from_time,'%H:%i')
								";
				$results_check = $wpdb->get_results ( $check_query );

				if ( count($results_check) > 0 )
				{
					$drop_down = "<label>".get_option('book.time-label').": </label><select name='time_slot' id='time_slot' class='time_slot'>";
					$drop_down .= "<option value=''>".__( 'Choose a Time', 'woocommerce-booking')."</option>";
					$specific = "N";
					foreach ( $results_check as $key => $value )
					{
						if ($value->weekday == "") 
						{
							$specific = "Y";
							if ($value->from_time != '') 
							{
								$from_time = date($time_format_to_show, strtotime($value->from_time));
								$from_time_value = date($time_format_value, strtotime($value->from_time));
							}
							$to_time = $value->to_time;
							
							if( $to_time != '' )
							{
								$to_time = date($time_format_to_show, strtotime($value->to_time));
								$to_time_value = date($time_format_value, strtotime($value->to_time));
								$drop_down .= "<option value='".$from_time_value." - ".$to_time_value."'>".$from_time."-".$to_time."</option>";
							}
							else
							{
								$drop_down .= "<option value='".$from_time_value."'>".$from_time."</option>";
							}
						}
					}
					if ($specific == "N")
					{
						foreach ( $results_check as $key => $value )
						{
							if ($value->from_time != '')
							{
								$from_time = date($time_format_to_show, strtotime($value->from_time));
								$from_time_value = date($time_format_value, strtotime($value->from_time));
							}
							
							$to_time = $value->to_time;
							
							if( $to_time != '' )
							{
								$to_time = date($time_format_to_show, strtotime($value->to_time));
								$to_time_value = date($time_format_value, strtotime($value->to_time));
								$drop_down .= "<option value='".$from_time_value." - ".$to_time_value."'>".$from_time."-".$to_time."</option>";
							}
							else
							{
								if ($value->from_time != '')
								{
									$drop_down .= "<option value='".$from_time_value."'>".$from_time."</option>";
								}
							}	
						}

						
						$check_day_query = "SELECT * FROM `".$wpdb->prefix."booking_history`
											WHERE weekday='".$day_check."'
											AND post_id='".$post_id."'
											AND start_date='0000-00-00'
											AND available_booking > 0 ORDER BY STR_TO_DATE(from_time,'%H:%i')";
						$results_day_check = $wpdb->get_results ( $check_day_query );

						
						//remove duplicate time slots that have available booking set to 0
						foreach ($results_day_check as $k => $v)
						{
							$from_time_qry = date($time_format_value, strtotime($v->from_time));
							if ($v->to_time != '') $to_time_qry = date($time_format_value, strtotime($v->to_time));
							else $to_time_qry = "";

							$time_check_query = "SELECT * FROM `".$wpdb->prefix."booking_history`
										WHERE start_date='".$date_to_check."'
										AND post_id='".$post_id."'
										AND from_time='".$from_time_qry."'
										AND to_time='".$to_time_qry."' ORDER BY STR_TO_DATE(from_time,'%H:%i')";
							$results_time_check = $wpdb->get_results ( $time_check_query );

							
							if (count($results_time_check) > 0) unset($results_day_check[$k]);
						}
						
						
						//remove duplicate time slots that have available booking > 0
						foreach ($results_day_check as $k => $v)
						{
							foreach ($results_check as $key => $value)
							{
								if ($v->from_time != '' && $v->to_time != '')
								{
									$from_time_chk = date($time_format_value, strtotime($v->from_time));
									if ($value->from_time == $from_time_chk)
									{
										if ($v->to_time != '') $to_time_chk = date($time_format_value, strtotime($v->to_time));
										if ($value->to_time == $to_time_chk) unset($results_day_check[$k]);
									}
								}
								else
								{
									if($v->from_time == $value->from_time)
									{
										if ($v->to_time == $value->to_time) unset($results_day_check[$k]);
									}
								}
							}
						}
						
						foreach ( $results_day_check as $key => $value )
						{
							if ($value->from_time != '')
							{
								$from_time = date($time_format_to_show, strtotime($value->from_time));
								$from_time_value = date($time_format_value, strtotime($value->from_time));
							}

							$to_time = $value->to_time;
						
							if ( $to_time != '' )
							{
								$to_time = date($time_format_to_show, strtotime($value->to_time));
								$to_time_value = date($time_format_value, strtotime($value->to_time));
								$drop_down .= "<option value='".$from_time_value." - ".$to_time_value."'>".$from_time."-".$to_time."</option>";
							}
							else
							{
								if ($value->from_time != '') 
								{
									$drop_down .= "<option value='".$from_time_value."'>".$from_time."</option>";
								}
							}

							$insert_date = "INSERT INTO `".$wpdb->prefix."booking_history`
											(post_id,weekday,start_date,end_date,from_time,to_time,total_booking,available_booking)
											VALUES (
											'".$post_id."',
											'".$day_check."',
											'".$date_to_check."',
											'0000-00-00',
											'".$from_time_value."',
											'".$to_time_value."',
											'".$value->total_booking."',
											'".$value->available_booking."' )";
							$wpdb->query( $insert_date );
						}
					}
				}
				else 
				{
					$check_day_query = "SELECT * FROM `".$wpdb->prefix."booking_history`
										 WHERE weekday='".$day_check."'
										 AND post_id='".$post_id."'
										 AND start_date='0000-00-00'
										 AND available_booking > 0 ORDER BY STR_TO_DATE(from_time,'%H:%i')";
					$results_day_check = $wpdb->get_results ( $check_day_query );

					
					if (!$results_day_check)
					{
						$check_day_query = "SELECT * FROM `".$wpdb->prefix."booking_history`
											WHERE weekday='".$day_check."'
											AND post_id='".$post_id."'
											AND start_date='0000-00-00'
											AND total_booking = 0
											AND available_booking = 0 ORDER BY STR_TO_DATE(from_time,'%H:%i')";
						$results_day_check = $wpdb->get_results ( $check_day_query );
					}
					
					if ($results_day_check)
					{
						$drop_down = "<label>".get_option('book.time-label'). ": </label><select name='time_slot' id='time_slot' class='time_slot'>";
						$drop_down .= "<option value=''>" . __( 'Choose a Time', 'woocommerce-booking') . "</option>";
						foreach ( $results_day_check as $key => $value )
						{
							if ($value->from_time != '')
							{
								$from_time = date($time_format_to_show, strtotime($value->from_time));
								$from_time_value = date($time_format_value, strtotime($value->from_time));
							}
							else $from_time = $from_time_value = "";
	
							$to_time = $value->to_time;
							
							if ( $to_time != '' )
							{
								$to_time = date($time_format_to_show, strtotime($value->to_time));
								$to_time_value = date($time_format_value, strtotime($value->to_time));
								$drop_down .= "<option value='".$from_time_value." - ".$to_time_value."'>".$from_time."-".$to_time."</option>";
							}
							else 
							{
								$drop_down .= "<option value='".$from_time_value."'>".$from_time."</option>";
								$to_time = $to_time_value = "";
							}
							
							$insert_date = "INSERT INTO `".$wpdb->prefix."booking_history`
											(post_id,weekday,start_date,end_date,from_time,to_time,total_booking,available_booking)
											VALUES (
											'".$post_id."',
											'".$day_check."',
											'".$date_to_check."',
											'0000-00-00',
											'".$from_time_value."',
											'".$to_time_value."',
											'".$value->total_booking."',
											'".$value->available_booking."' )";
							$wpdb->query( $insert_date );
						}
					}
					else
					{
						$check_query = "SELECT * FROM `".$wpdb->prefix."booking_history`
										WHERE start_date='".$date_to_check."'
										AND post_id='".$post_id."'
										AND total_booking = 0
										AND available_booking = 0 ORDER BY STR_TO_DATE(from_time,'%H:%i')
							";
						$results_check = $wpdb->get_results ( $check_query );
						
						$drop_down = "<label>".get_option('book.time-label'). ": </label><select name='time_slot' id='time_slot' class='time_slot'>";
						$drop_down .= "<option value=''>" . __( 'Choose a Time', 'woocommerce-booking') . "</option>";
						foreach ( $results_check as $key => $value )
						{
							if ($value->from_time != '')
							{
								$from_time = date($time_format_to_show, strtotime($value->from_time));
								$from_time_value = date($time_format_value, strtotime($value->from_time));
							}
							else $from_time = $from_time_value = "";
						
							$to_time = $value->to_time;
								
							if ( $to_time != '' )
							{
								$to_time = date($time_format_to_show, strtotime($value->to_time));
								$to_time_value = date($time_format_value, strtotime($value->to_time));
								$drop_down .= "<option value='".$from_time_value." - ".$to_time_value."'>".$from_time."-".$to_time."</option>";
							}
							else
							{
								$drop_down .= "<option value='".$from_time_value."'>".$from_time."</option>";
								$to_time = $to_time_value = "";
							}
						}
					}
				}
				
				echo $drop_down;
				die();
			}
			
			function insert_date() 
			{
				global $wpdb;
			
				$current_date = $_POST['current_date'];
				$date_to_check = date('Y-m-d', strtotime($current_date));
				$day_check = "booking_weekday_".date('w', strtotime($current_date));
				
				$post_id = $_POST['post_id'];
				$product = get_product($post_id);
				$product_type = $product->product_type;
				if ( $product_type == 'variable')
					$variation_id = $this->get_selected_variation_id($post_id, $_POST);
				else $variation_id = "";
				
				$check_query = "SELECT * FROM `".$wpdb->prefix."booking_history`
							WHERE start_date='".$date_to_check."'
							AND post_id='".$post_id."'
							AND available_booking > 0";
				$results_check = $wpdb->get_results ( $check_query );
			
				if ( !$results_check )
				{
					$check_day_query = "SELECT * FROM `".$wpdb->prefix."booking_history`
									WHERE weekday='".$day_check."'
									AND post_id='".$post_id."'
									AND start_date='0000-00-00'
									AND available_booking > 0";
					$results_day_check = $wpdb->get_results ( $check_day_query );	
					if (!$results_day_check)
					{
						$check_day_query = "SELECT * FROM `".$wpdb->prefix."booking_history`
										WHERE weekday='".$day_check."'
										AND post_id='".$post_id."'
										AND start_date='0000-00-00'
										AND total_booking = 0 
										AND available_booking = 0";
						$results_day_check = $wpdb->get_results ( $check_day_query );	
					}
				
					foreach ( $results_day_check as $key => $value )
					{
						$insert_date = "INSERT INTO `".$wpdb->prefix."booking_history`
										(post_id,weekday,start_date,end_date,from_time,to_time,total_booking,available_booking)
										VALUES (
										'".$post_id."',
										'".$day_check."',
										'".$date_to_check."',
										'0000-00-00',
										'',
										'',
										'".$value->total_booking."',
										'".$value->available_booking."' )";
						$wpdb->query( $insert_date );
					}
				}

				die();
			}
			
			function call_addon_price()
			{
				//	global $post;
				$product_id = $_POST['id'];
				$booking_date_format = $_POST['details'];
				$booking_date = date('Y-m-d',strtotime($booking_date_format));
					
				$product = get_product($product_id);
				$product_type = $product->product_type;
					
				if ( $product_type == 'variable')
					$variation_id = $this->get_selected_variation_id($product_id, $_POST);
				else $variation_id = "0";
				//	echo "Variation ID: " . $variation_id;
				$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
				if (isset($booking_settings['booking_partial_payment_enable']) && $booking_settings['booking_partial_payment_radio']!='' &&  is_plugin_active('bkap-deposits/deposits.php')){
					$price = apply_filters("bkap_add_updated_addon_price",$product_id,$booking_date,$variation_id);
					do_action('bkap_deposits_display_updated_price',$product_id,$variation_id,$price);
				}else
					do_action('bkap_display_updated_addon_price',$product_id,$booking_date,$variation_id);
			}
			
			function get_per_night_price() {
				
				global $wpdb;
				$product_type = $_POST['product_type'];
				$product_id = $_POST['post_id'];
				$check_in_date = $_POST['checkin_date'];
				$check_out_date = $_POST['current_date'];
				if ($product_type == 'variable')
					$variation_id_to_fetch = $this->get_selected_variation_id($product_id, $_POST);
				else $variation_id_to_fetch = 0;
				
				$checkin_date = date('Y-m-d',strtotime($check_in_date));
				$checkout_date = date('Y-m-d',strtotime($check_out_date));
				
				do_action("bkap_display_multiple_day_updated_price",$product_id,$product_type,$variation_id_to_fetch,$checkin_date,$checkout_date);
				
				$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
				if(isset($booking_settings['booking_block_price_enable']) && $booking_settings['booking_block_price_enable'] == 'yes')
				{
					do_action('bkap_display_block_updated_price',$product_id,$product_type,$variation_id_to_fetch,$checkin_date,$checkout_date);
					exit;
				}
				else if (isset($booking_settings['booking_fixed_block_enable']) && $booking_settings['booking_fixed_block_enable'] == "yes")
				{
					$price = $_POST['block_option_price'];
					do_action('bkap_fixed_block_display_updated_price',$product_id,$variation_id_to_fetch,$price);
					exit;
				}
				else if (isset($booking_settings['booking_partial_payment_enable']) && is_plugin_active('bkap-deposits/deposits.php')) 
				{
					$price = apply_filters("bkap_add_multiple_day_updated_price",$product_id,$product_type,$variation_id_to_fetch,$checkin_date,$checkout_date);
					do_action('bkap_deposits_display_updated_price',$product_id,$variation_id_to_fetch,$price);
					exit;
				}
				if ($product_type == 'variable')
					$variation_id_to_fetch = $this->get_selected_variation_id($product_id, $_POST);
				else $variation_id_to_fetch = 0;
				
				if ($product_type == 'variable')
				{
				//	print_r($_POST);
					$variation_id_to_fetch = $this->get_selected_variation_id($product_id, $_POST);
					if ($variation_id_to_fetch != "")
					{
						$sale_price = get_post_meta( $variation_id_to_fetch, '_sale_price', true);
				
						if($sale_price == '')
						{
							$regular_price = get_post_meta( $variation_id_to_fetch, '_regular_price',true);
							
							echo $regular_price;
						}
						else
						{
							echo $sale_price;
						}
					}
					else echo "Please select an option."; 
				}
				elseif ($product_type == 'simple')
				{
					$sale_price = get_post_meta( $_POST['post_id'], '_sale_price', true);
					if($sale_price == '')
					{
						$regular_price = get_post_meta( $_POST['post_id'], '_regular_price',true);
						echo $regular_price;
					}
					else
					{
						echo $sale_price;
					}
				}

				die();
			}
			

			function get_selected_variation_id($product_id, $post_data)
			{
				global $wpdb;
				//print_r($post_data);
				$product = get_product($product_id);
				$variations = $product->get_available_variations();
				$attributes = $product->get_variation_attributes();
				$attribute_fields_str = "";
				$attribute_fields = array();
				$variation_id_arr = $variation_id_exclude = array();
				foreach ($variations as $var_key => $var_val)
				{
					$attribute_sub_query = '';
					$variation_id = $var_val['variation_id'];
					foreach ($var_val['attributes'] as $a_key => $a_val)
					{
						$attribute_name = $a_key;
						// for each attribute, we are checking the value selected by the user
						if (isset($post_data[$attribute_name]))
						{
							$attribute_sub_query[] = " (`meta_key` = '$attribute_name' AND `meta_value` = '$post_data[$attribute_name]')  ";
							$attribute_sub_query_str = " (`meta_key` = '$attribute_name' AND (`meta_value` = '$post_data[$attribute_name]' OR `meta_value` = ''))  ";
							$check_price_query = "SELECT * FROM `".$wpdb->prefix."postmeta`
												WHERE 
												$attribute_sub_query_str 
												AND 
												post_id='".$variation_id."' ";
						//	echo $check_price_query.'<br>';
							$results_price_check = $wpdb->get_results ( $check_price_query );
							//print_r($results_price_check);
							// if no records are found, then that variation_id is put in exclude array
							if (count($results_price_check) > 0)
							{
								if (!in_array($variation_id, $variation_id_arr))
									$variation_id_arr[] = $variation_id;
							}
							else 
							{
								if (!in_array($variation_id, $variation_id_exclude))
									$variation_id_exclude[] = $variation_id;
							}
						}
					}
				}
				// here we remove all variation ids from the $variation_id_arr that are present in the $variation_id_exclude array
				// this should leave us with only 1 variation id
				$variation_id_final = array_diff($variation_id_arr, $variation_id_exclude);
			//	echo 'here <pre>';print_r($variation_id_final);echo '</pre>';
				$variation_id_to_fetch = array_pop($variation_id_final);
				
				return $variation_id_to_fetch;
			}
			
			function add_cart_item( $cart_item ) {

				// Adjust price if addons are set
				global $wpdb;
				if (isset($cart_item['booking'])) :
					
					$extra_cost = 0;
					
					foreach ($cart_item['booking'] as $addon) :
						
						if (isset($addon['price']) && $addon['price']>0) $extra_cost += $addon['price'];
						
					endforeach;
					
					$duplicate_of = get_post_meta($cart_item['product_id'], '_icl_lang_duplicate_of', true);
					if($duplicate_of == '' && $duplicate_of == null)
					{
					//	$duplicate_of = $cart_item['product_id'];
						$post_time = get_post($cart_item['product_id']);
						$id_query = "SELECT ID FROM `".$wpdb->prefix."posts` WHERE post_date = '".$post_time->post_date."' ORDER BY ID LIMIT 1";
						$results_post_id = $wpdb->get_results ( $id_query );
						if( isset($results_post_id) ) {
							$duplicate_of = $results_post_id[0]->ID;
						}
						else
						{
							$duplicate_of = $cart_item['product_id'];
						}
					}
					$product = get_product($cart_item['product_id']);
				//	$product = get_product($duplicate_of);
					$product_type = $product->product_type;
					
					if ( $product_type == 'variable')
					{
						$sale_price = get_post_meta( $cart_item['variation_id'], '_sale_price', true);
						if($sale_price == '')
						{
							$regular_price = get_post_meta( $cart_item['variation_id'], '_regular_price', true);
							$extra_cost = $extra_cost - $regular_price;
						}
						else
						{
							$extra_cost = $extra_cost - $sale_price;
						}
					}
					elseif($product_type == 'simple')
					{
						$sale_price = get_post_meta( $cart_item['product_id'], '_sale_price', true);
					//	$sale_price = get_post_meta( $duplicate_of, '_sale_price', true);
						if($sale_price == '')
						{
							$regular_price = get_post_meta( $cart_item['product_id'], '_regular_price', true);
						//	$regular_price = get_post_meta( $duplicate_of, '_regular_price', true);
							$extra_cost = $extra_cost - $regular_price;
						}
						else
						{
							$extra_cost = $extra_cost - $sale_price;
						}
					}
					$cart_item['data']->adjust_price( $extra_cost );
					
				endif;
				//echo "add_cart_item is ";echo "<pre>";print_r($cart_item);echo "</pre>";
				return $cart_item;
			}
			
			function add_cart_item_data( $cart_item_meta, $product_id )
			{
				global $wpdb;
				$duplicate_of = get_post_meta($product_id, '_icl_lang_duplicate_of', true);
				if($duplicate_of == '' && $duplicate_of == null)
				{
					//	$duplicate_of = $cart_item['product_id'];
					$post_time = get_post($product_id);
					$id_query = "SELECT ID FROM `".$wpdb->prefix."posts` WHERE post_date = '".$post_time->post_date."' ORDER BY ID LIMIT 1";
					//echo "<pre>";print_r($id_query);echo "</pre>";exit;
					$results_post_id = $wpdb->get_results ( $id_query );
					//	print_r($results_post_id);
					if( isset($results_post_id) ) {
						$duplicate_of = $results_post_id[0]->ID;
					}
					else
					{
						$duplicate_of = $product_id;
					}
				}
				
				if (isset($_POST['booking_calender']))
				{
					$date_disp = $_POST['booking_calender'];
				}
				if (isset($_POST['time_slot']))
				{
					$time_disp = $_POST['time_slot'];
				}
				if (isset($_POST['wapbk_hidden_date']))
				{
					$hidden_date = $_POST['wapbk_hidden_date'];
				}
				
				$booking_settings = get_post_meta($duplicate_of, 'woocommerce_booking_settings', true);
			
				//$show_checkout_date_calendar\
				$product = get_product($product_id);
		
				$product_type = $product->product_type;
				if(isset($booking_settings['booking_enable_multiple_day']) && $booking_settings['booking_enable_multiple_day'] == 'on')
				{
					if(isset($_POST['booking_calender_checkout']))
					{
						$date_disp_checkout = $_POST['booking_calender_checkout'];
					}
					if(isset($_POST['wapbk_hidden_date_checkout']))
					{
						$hidden_date_checkout = $_POST['wapbk_hidden_date_checkout'];
					}
					$diff_days = '';
					if(isset($_POST['wapbk_diff_days']))
					{
						$diff_days = $_POST['wapbk_diff_days'];
					}
					
					if ($product_type == 'variable')
					{
						$sale_price = get_post_meta( $_POST['variation_id'], '_sale_price', true);
						if($sale_price == '')
						{
							$regular_price = get_post_meta( $_POST['variation_id'], '_regular_price',true);
							$price = $regular_price * $diff_days;
						}
						else
						{
							$price = $sale_price * $diff_days;
						}
					}
					elseif($product_type == 'simple')
					{
						$sale_price = get_post_meta( $product_id, '_sale_price', true);
		
						if(!isset($sale_price) || $sale_price == '' || $sale_price == 0)
						{
							$regular_price = get_post_meta($product_id, '_regular_price',true);
		
							$price = $regular_price * $diff_days;
						}
						else
						{
							$price = $sale_price * $diff_days;
						}
					}
				}
				else
				{
					$price = '';
				}
				//print_r($booking_settings);exit;
				//Round the price if needed
				$round_price = $price;
				$global_settings = json_decode(get_option('woocommerce_booking_global_settings'));
				if (isset($global_settings->enable_rounding) && $global_settings->enable_rounding == "on")
					$round_price = round($price);
				$price = $round_price;
				
				if (isset($date_disp))
				{
				/*	$cart_arr = array(	'date' 		=> $date_disp,
										'time_slot' => $time_disp,
										'hidden_date' => $hidden_date  
									);*/
					$cart_arr = array();
					if (isset($date_disp))
					{
						$cart_arr['date'] = $date_disp;
					}
					if (isset($time_disp))
					{
						$cart_arr['time_slot'] = $time_disp;
					}
					if (isset($hidden_date))
					{
						$cart_arr['hidden_date'] = $hidden_date;
					}
					if ($booking_settings['booking_enable_multiple_day'] == 'on')
					{
						$cart_arr['date_checkout'] = $date_disp_checkout;
						$cart_arr['hidden_date_checkout'] = $hidden_date_checkout;
						$cart_arr['price'] = $price;
					}
					else if(isset($booking_settings['booking_recurring_booking']) && $booking_settings['booking_recurring_booking'] == 'on')
					{
						$cart_arr['price'] = $price;
					}
					if (isset($_POST['variation_id'])) $variation_id = $_POST['variation_id'];
					else $variation_id = '0';
					$type_of_slot = apply_filters('bkap_slot_type',$product_id);
					if($type_of_slot == 'multiple')
					{
						$cart_arr = (array) apply_filters('bkap_multiple_add_cart_item_data', $cart_item_meta, $product_id);
					}
					else
					{
						$cart_arr = (array) apply_filters('bkap_add_cart_item_data', $cart_arr, $product_id);
						
						if(is_plugin_active('bkap-seasonal-pricing/seasonal_pricing.php'))
							$cart_arr = (array) apply_filters('bkap_addon_add_cart_item_data', $cart_arr, $product_id, $variation_id);
						
						//print_r($cart_arr);exit;
					}
					$cart_item_meta['booking'][] = $cart_arr;
				}
				
				//echo "add_cart_item_data is ";echo "<pre>";print_r($cart_item_meta);echo "</prE>";exit;
				return $cart_item_meta;
			}
			
			function get_cart_item_from_session( $cart_item, $values ) {
				
				global $wpdb;
				$duplicate_of = get_post_meta($cart_item['product_id'], '_icl_lang_duplicate_of', true);
				if($duplicate_of == '' && $duplicate_of == null)
				{
					//	$duplicate_of = $cart_item['product_id'];
					$post_time = get_post($cart_item['product_id']);
					$id_query = "SELECT ID FROM `".$wpdb->prefix."posts` WHERE post_date = '".$post_time->post_date."' ORDER BY ID LIMIT 1";
					//echo "<pre>";print_r($id_query);echo "</pre>";exit;
					$results_post_id = $wpdb->get_results ( $id_query );
					//	print_r($results_post_id);
					if( isset($results_post_id) ) {
						$duplicate_of = $results_post_id[0]->ID;
					}
					else
					{
						$duplicate_of = $cart_item['product_id'];
					}
				}
				if (isset($values['booking'])) :
					
					$cart_item['booking'] = $values['booking'];
					//print_r($cart_item);
					$booking_settings = get_post_meta($duplicate_of, 'woocommerce_booking_settings', true);
					//$show_checkout_date_calendar = 1;
					if (isset($booking_settings['booking_enable_multiple_day']) && $booking_settings['booking_enable_multiple_day'] == 'on')
					{
						$cart_item = $this->add_cart_item( $cart_item );
					}
					$type_of_slot = apply_filters('bkap_slot_type',$cart_item['product_id']);
					if($type_of_slot == 'multiple')
					{
						$cart_item = (array) apply_filters('bkap_get_cart_item_from_session', $cart_item , $values);
					}
					else
					{
						$cart_item = (array) apply_filters('bkap_get_cart_item_from_session', $cart_item , $values);
					}
				endif;
				//echo "get_cart_item_from_session ";echo "<pre>";print_r($cart_item);echo "</prE>";exit;
				return $cart_item;
			}
			
			function get_item_data( $other_data, $cart_item ) {
				global $wpdb;
				//echo "<pre>";print_r($cart_item);echo "</pre>";//exit;
				if (isset($cart_item['booking'])) :
					$duplicate_of = get_post_meta($cart_item['product_id'], '_icl_lang_duplicate_of', true);
					if($duplicate_of == '' && $duplicate_of == null)
					{
					//	$duplicate_of = $cart_item['product_id'];
						$post_time = get_post($cart_item['product_id']);
						$id_query = "SELECT ID FROM `".$wpdb->prefix."posts` WHERE post_date = '".$post_time->post_date."' ORDER BY ID LIMIT 1";
						$results_post_id = $wpdb->get_results ( $id_query );
					//	print_r($results_post_id);
						if( isset($results_post_id) ) {
							$duplicate_of = $results_post_id[0]->ID;
						}
						else
						{
							$duplicate_of = $cart_item['product_id'];
						}
					}
				//	echo $duplicate_of;
					foreach ($cart_item['booking'] as $booking) :
					
						$name = get_option('book.item-cart-date');
						//if ($booking['price']>0) $name .= ' (' . woocommerce_price($booking['price']) . ')';
						if (isset($booking['date']) && $booking['date'] != "")
						{
							$other_data[] = array(
									'name'    => $name,
									'display' => $booking['date']
							);
						}
						if (isset($booking['date_checkout']) && $booking['date_checkout'] != "")
						{
							//$show_checkout_date_calendar = 1;
						//	$booking_settings = get_post_meta($cart_item['product_id'], 'woocommerce_booking_settings', true);
							$booking_settings = get_post_meta($duplicate_of, 'woocommerce_booking_settings', true);
							//print_r($booking_settings);
							if ($booking_settings['booking_enable_multiple_day'] == 'on')
							{
								$other_data[] = array(
										'name'    => get_option('checkout.item-cart-date'),
										'display' => $booking['date_checkout']
								);
								
							}
						}
						if (isset($booking['time_slot']) && $booking['time_slot'] != "")
						{
							$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
							if (isset($saved_settings))	
							{
								$time_format = $saved_settings->booking_time_format;
							}
							else
							{
								$time_format = "12";
							}
							$time_slot_to_display = $booking['time_slot'];
						//	if ($time_format == "" OR $time_format == "NULL") $time_format = "12";
							if ($time_format == '12')
							{
								$time_exploded = explode("-", $time_slot_to_display);
								$from_time = date('h:i A', strtotime($time_exploded[0]));
								if (isset($time_exploded[1])) $to_time = date('h:i A', strtotime($time_exploded[1]));
								else $to_time = "";
								if ($to_time != "") $time_slot_to_display = $from_time.' - '.$to_time;
								else $time_slot_to_display = $from_time;
							}
							$type_of_slot = apply_filters('bkap_slot_type',$cart_item['product_id']);
							if($type_of_slot != 'multiple')
							{
								$name = get_option('book.item-cart-time');
								$other_data[] = array(
									'name'    => $name,
									'display' => $time_slot_to_display
								);
							}
						}
					/*	$price = $cart_item['booking']['0']['price'];
						$price -= $cart_item['line_total']; 
						$cart_item['data']->adjust_price( $price );*/
				//		echo "price is : " . $price;echo "<pre>";print_r($other_data);print_r($cart_item);echo "</pre>";//exit;
						$type_of_slot = apply_filters('bkap_slot_type',$cart_item['product_id']);
						if($type_of_slot == 'multiple')
						{
							
							$other_data = apply_filters('bkap_timeslot_get_item_data',$other_data, $cart_item);
						}
						else 
						{
							$other_data = apply_filters('bkap_get_item_data',$other_data, $cart_item);
						}
					endforeach;
					
				endif;
				
				return $other_data;
			}
			
			function add_order_item_meta( $item_meta, $cart_item ) {
					
				// Add the fields
				global $wpdb;
				
				$quantity = $cart_item['quantity'];
					
				$post_id = $cart_item['product_id'];
					
				if (isset($cart_item['booking'])) :
					
					foreach ($cart_item['booking'] as $booking) :
					
						$date_select = $booking['date'];
						$name = get_option('book.item-meta-date');
						$item_meta->add( $name, $date_select );

						if ($booking['time_slot'] != "")
						{
							$time_select = $booking['time_slot'];

							$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
							$time_format = $saved_settings->booking_time_format;
							if ($time_format == "" OR $time_format == "NULL") $time_format = "12";
							$time_slot_to_display = $booking['time_slot'];
							if ($time_format == '12')
							{
								$time_exploded = explode("-", $time_slot_to_display);
								$from_time = date('h:i A', strtotime($time_exploded[0]));
								$to_time = date('h:i A', strtotime($time_exploded[1]));
								$time_slot_to_display = $from_time.' - '.$to_time;
							}
							
							$time_exploded = explode("-", $time_select);
							$name = get_option('book.item-meta-time');
							$item_meta->add( $name, $time_slot_to_display );
						}		
						$hidden_date = $booking['hidden_date'];
						$date_query = date('Y-m-d', strtotime($hidden_date));
							
						$query = "UPDATE `".$wpdb->prefix."booking_history`
							SET available_booking = available_booking - ".$quantity."
							WHERE post_id = '".$post_id."' AND
							start_date = '".$date_query."' AND
							from_time = '".trim($time_exploded[0])."' AND
							to_time = '".trim($time_exploded[1])."' ";
						$wpdb->query( $query );
					
						if (mysql_affected_rows($wpdb) == 0)
						{
							$from_time = date('H:i', strtotime($time_exploded[0]));
							$to_time = date('H:i', strtotime($time_exploded[1]));
							$query = "UPDATE `".$wpdb->prefix."booking_history`
										SET available_booking = available_booking - ".$quantity."
										WHERE post_id = '".$post_id."' AND
										start_date = '".$date_query."' AND
										from_time = '".$from_time."' AND
										to_time = '".$to_time."' ";
											
							$wpdb->query( $query );
						}
						
					endforeach;
					
				endif;
			}
			
			function order_item_meta( $item_meta, $cart_item ) 
			{
				if ( version_compare( WOOCOMMERCE_VERSION, "2.0.0" ) < 0 )
					{
						return;
					}
				// Add the fields
				global $wpdb;
								
				global $woocommerce;
				
				//print_r($product_id);
				//exit;
				$order_item_ids = array();
				$sub_query = "";
				$ticket_content = array();
				$i = 0;
	            foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $values ) 
				{
					$_product = $values['data'];
					//print_r($_product);
					if(array_key_exists("variation_id",$values))
					{
						$variation_id = $values['variation_id'];
					}
					else
					{
						$variation_id = '';
					}
	                if (isset($values['booking'])) $booking = $values['booking'];
					$quantity = $values['quantity'];
					$post_id = get_post_meta($values['product_id'], '_icl_lang_duplicate_of', true);
					if($post_id == '' && $post_id == null)
					{
						$post_time = get_post($values['product_id']);
						$id_query = "SELECT ID FROM `".$wpdb->prefix."posts` WHERE post_date = '".$post_time->post_date."' ORDER BY ID LIMIT 1";
						$results_post_id = $wpdb->get_results ( $id_query );
						if( $results_post_id ) {
							$post_id = $results_post_id[0]->ID;;
						}
						else
						{
							$post_id = $values['product_id'];
						}
					}
					//$post_id = $values['product_id'];
					$post_title = $_product->get_title();
				    // Fetch line item
					//echo addslashes($post_title);
					if (count($order_item_ids) > 0) 
					{
						$order_item_ids_to_exclude = implode(",", $order_item_ids);
						$sub_query = " AND order_item_id NOT IN (".$order_item_ids_to_exclude.")";
					}
					
					$query = "SELECT order_item_id,order_id FROM `".$wpdb->prefix."woocommerce_order_items`
								WHERE order_id = '".$item_meta."' AND order_item_name = '".addslashes($post_title)."'".$sub_query;
					//echo $query;exit;	
					$results = $wpdb->get_results( $query );
					//print_r($results);echo "<br>";
					$order_item_ids[] = $results[0]->order_item_id;
					$order_id = $results[0]->order_id;
					$order_obj = new WC_order($order_id);
					$details = array();
					$product_ids = array();
					//print_r($order_obj);
					$order_items = $order_obj->get_items();
					
					$type_of_slot = apply_filters('bkap_slot_type',$post_id);
					if(is_plugin_active('bkap-tour-operators/tour_operators_addon.php'))
					{
						do_action('bkap_operator_update_order',$values,$results[0]);
					}
					$booking_settings = get_post_meta( $post_id, 'woocommerce_booking_settings', true);
					if(isset($booking_settings['booking_partial_payment_enable']) && isset($booking_settings['booking_partial_payment_radio']) && $booking_settings['booking_partial_payment_radio']!='' &&  is_plugin_active('bkap-deposits/deposits.php'))
					{
						do_action('bkap_deposits_update_order',$values,$results[0]);
					}
					if($type_of_slot == 'multiple')
					{
						do_action('bkap_update_booking_history',$values,$results[0]);
					}
					else
					{
						if (isset($values['booking'])) :
						//	print_r($values['booking']);exit;
						$details = array();	
						if ($booking[0]['date'] != "")
						{
							$name = get_option('book.item-meta-date');
							$date_select = $booking[0]['date'];
							
							woocommerce_add_order_item_meta( $results[0]->order_item_id, $name, sanitize_text_field( $date_select, true ) );
						}
						if (array_key_exists('date_checkout',$booking[0]) && $booking[0]['date_checkout'] != "")
						{
							$booking_settings = get_post_meta($post_id, 'woocommerce_booking_settings', true);

							if ($booking_settings['booking_enable_multiple_day'] == 'on')
							{
								$name_checkout = get_option('checkout.item-meta-date');
								$date_select_checkout = $booking[0]['date_checkout'];
								//echo $results[0]->order_item_id;exit;
								woocommerce_add_order_item_meta( $results[0]->order_item_id, $name_checkout, sanitize_text_field( $date_select_checkout, true ) );
							}
						}
						if (array_key_exists('time_slot',$booking[0]) && $booking[0]['time_slot'] != "")
						{
							$time_slot_to_display = '';
							$time_select = $booking[0]['time_slot'];
							$time_exploded = explode("-", $time_select);
							//print_r($time_exploded);
							$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
							if (isset($saved_settings)) 
							{
								$time_format = $saved_settings->booking_time_format;
							}
							else
							{
								$time_format = "12";
							}
							$time_slot_to_display = '';
							$from_time = trim($time_exploded[0]);
							if(isset($time_exploded[1]))$to_time = trim($time_exploded[1]);
							else $to_time = '';
							if ($time_format == '12')
							{
								$from_time = date('h:i A', strtotime($time_exploded[0]));
								if(isset($time_exploded[1]))$to_time = date('h:i A', strtotime($time_exploded[1]));
							}
							$query_from_time = date('G:i', strtotime($time_exploded[0]));
							if(isset($time_exploded[1]))$query_to_time = date('G:i', strtotime($time_exploded[1]));
							else $query_to_time = '';
							if($to_time != '')
							{
								$time_slot_to_display = $from_time.' - '.$to_time;
							}
							else
							{
								$time_slot_to_display = $from_time;
							}
							woocommerce_add_order_item_meta( $results[0]->order_item_id,  get_option('book.item-meta-time'), $time_slot_to_display, true );
							
						}
						$hidden_date = $booking[0]['hidden_date'];
						$date_query = date('Y-m-d', strtotime($hidden_date));
						if(array_key_exists('hidden_date_checkout',$booking[0]))
						{
							$date_checkout = $booking[0]['hidden_date_checkout'];
							$date_checkout_query = date('Y-m-d',strtotime($date_checkout));
						}
						
						if (isset($booking_settings['booking_enable_multiple_day'])&& $booking_settings['booking_enable_multiple_day'] == 'on')
						{
							for ($i = 0; $i < $quantity; $i++)
							{
								$query = "INSERT INTO `".$wpdb->prefix."booking_history`
										(post_id,weekday,start_date,end_date,from_time,to_time,total_booking,available_booking)
										VALUES (
										'".$post_id."',
										'',
										'".$date_query."',
										'".$date_checkout_query."',
										'',
										'',
										'0',
										'0' )";
								$wpdb->query( $query );
							}
							$new_booking_id = mysql_insert_id();
							$order_query = "INSERT INTO `".$wpdb->prefix."booking_order_history`
											(order_id,booking_id)
											VALUES (
											'".$order_id."',
											'".$new_booking_id."' )";
							$wpdb->query( $order_query );
						}
						else 
						{						
							if(isset($booking[0]['time_slot']) && $booking[0]['time_slot'] != "")
							{
								if($query_to_time != "")
								{
									$query = "UPDATE `".$wpdb->prefix."booking_history`
											SET available_booking = available_booking - ".$quantity."
											WHERE post_id = '".$post_id."' AND
											start_date = '".$date_query."' AND
											from_time = '".$query_from_time."' AND
											to_time = '".$query_to_time."' AND
											total_booking > 0";
									$wpdb->query( $query );
									
									$select = "SELECT * FROM `".$wpdb->prefix."booking_history`
										WHERE post_id = '".$post_id."' AND
										start_date = '".$date_query."' AND
										from_time = '".$query_from_time."' AND
										to_time = '".$query_to_time."' ";
									$select_results = $wpdb->get_results( $select );
									foreach($select_results as $k => $v)
									{
										$details[$post_id] = $v;
									}
								}
								else
								{
									$query = "UPDATE `".$wpdb->prefix."booking_history`
											SET available_booking = available_booking - ".$quantity."
											WHERE post_id = '".$post_id."' AND
											start_date = '".$date_query."' AND
											from_time = '".$query_from_time."' AND
											total_booking > 0";
									$wpdb->query( $query );
									
									$select = "SELECT * FROM `".$wpdb->prefix."booking_history`
											WHERE post_id = '".$post_id."' AND
											start_date = '".$date_query."' AND
											from_time = '".$query_from_time."'";
									$select_results = $wpdb->get_results( $select );
									foreach($select_results as $k => $v)
									{
										$details[$post_id] = $v;
									}
								}
							}
							else
							{
								$query = "UPDATE `".$wpdb->prefix."booking_history`
											SET available_booking = available_booking - ".$quantity."
											WHERE post_id = '".$post_id."' AND
											start_date = '".$date_query."' AND
											total_booking > 0";
								$wpdb->query( $query );
							}

						}

						if (isset($booking_settings['booking_enable_multiple_day']) && $booking_settings['booking_enable_multiple_day'] != 'on')
						{
							if(array_key_exists('date',$booking[0]) && $booking[0]['time_slot'] != "")
							{
								if($query_to_time != '')
								{
									$order_select_query = "SELECT id FROM `".$wpdb->prefix."booking_history`
										WHERE post_id = '".$post_id."' AND
										start_date = '".$date_query."' AND
										from_time = '".$query_from_time."' AND
										to_time = '".$query_to_time."' ";
									$order_results = $wpdb->get_results( $order_select_query );
								}
								else
								{
									$order_select_query = "SELECT id FROM `".$wpdb->prefix."booking_history`
										WHERE post_id = '".$post_id."' AND
										start_date = '".$date_query."' AND
										from_time = '".$query_from_time."'";
									$order_results = $wpdb->get_results( $order_select_query );
								}
							}
							else
							{
								$order_select_query = "SELECT id FROM `".$wpdb->prefix."booking_history`
									WHERE post_id = '".$post_id."' AND
									start_date = '".$date_query."'";
								$order_results = $wpdb->get_results( $order_select_query );
							}
							$j = 0;
							foreach($order_results as $k => $v)
							{	
								$booking_id = $order_results[$j]->id;
								$order_query = "INSERT INTO `".$wpdb->prefix."booking_order_history`
												(order_id,booking_id)
												VALUES (
												'".$order_id."',
												'".$booking_id."' )";
								$wpdb->query( $order_query );
								$j++;
							}
						}
						endif; 
					}
					$book_global_settings = json_decode(get_option('woocommerce_booking_global_settings'));
					$booking_settings = get_post_meta($post_id, 'woocommerce_booking_settings' , true);
					if(isset($booking_settings['booking_time_settings']))
					{
					if (isset($booking_settings['booking_time_settings'][$hidden_date])) $lockout_settings = $booking_settings['booking_time_settings'][$hidden_date];
					else $lockout_settings = array();
					if(count($lockout_settings) == 0)
					{
						$week_day = date('l',strtotime($hidden_date));
						$weekdays = book_arrays('weekdays');
						$weekday = array_search($week_day,$weekdays);
						if (isset($booking_settings['booking_time_settings'][$weekday])) $lockout_settings = $booking_settings['booking_time_settings'][$weekday];
						else $lockout_settings = array();
					}
					$from_lockout_time = explode(":",$query_from_time);
					$from_hours = $from_lockout_time[0];
					$from_minute = $from_lockout_time[1];
					if(isset($query_to_time) && $query_to_time != '')
					{
						$to_lockout_time = explode(":",$query_to_time);
						$to_hours = $to_lockout_time[0];
						$to_minute = $to_lockout_time[1];
					}
					else
					{
						$to_hours = '';
						$to_minute = '';
					}
 					foreach($lockout_settings as $l_key => $l_value)
					{
						if($l_value['from_slot_hrs'] == $from_hours && $l_value['from_slot_min'] == $from_minute && $l_value['to_slot_hrs'] == $to_hours && $l_value['to_slot_min'] == $to_minute)
						{
							if (isset($l_value['global_time_check'])) $global_timeslot_lockout = $l_value['global_time_check'];
							else $global_timeslot_lockout = '';
						}
					}
					}
					if(isset($book_global_settings->booking_global_timeslot) && $book_global_settings->booking_global_timeslot == 'on' || $global_timeslot_lockout == 'on')
					{
						$args = array( 'post_type' => 'product', 'posts_per_page' => -1 );
						$product = query_posts( $args );
						foreach($product as $k => $v)
						{
							$product_ids[] = $v->ID;
						}
						foreach($product_ids as $k => $v)
						{
							$duplicate_of = get_post_meta($v, '_icl_lang_duplicate_of', true);
							if($duplicate_of == '' && $duplicate_of == null)
							{
								$post_time = get_post($v);
								$id_query = "SELECT ID FROM `".$wpdb->prefix."posts` WHERE post_date = '".$post_time->post_date."' ORDER BY ID LIMIT 1";
								$results_post_id = $wpdb->get_results ( $id_query );
								if( isset($results_post_id) ) {
									$duplicate_of = $results_post_id[0]->ID;
								}
								else
								{
									$duplicate_of = $v;
								}
								//$duplicate_of = $item_value['product_id'];
							}
							$booking_settings = get_post_meta($v, 'woocommerce_booking_settings' , true);
							if(isset($booking_settings['booking_enable_time']) && $booking_settings['booking_enable_time'] == 'on')
							{
								//echo "<pre>";print_r($details);echo "</pre>";exit;
								if(!array_key_exists($duplicate_of,$details))
								{	
									foreach($details as $key => $val)
									{
										$booking_settings = get_post_meta($duplicate_of, 'woocommerce_booking_settings', true);
										//echo"<pre>";print_r($booking_settings);echo"</pre>";exit;
										$start_date = $val->start_date;
										$from_time = $val->from_time;
										$to_time = $val->to_time;
										if($to_time != "")
										{
											$query = "UPDATE `".$wpdb->prefix."booking_history`
											SET available_booking = available_booking - ".$quantity."
											WHERE post_id = '".$duplicate_of."' AND
											start_date = '".$date_query."' AND
											from_time = '".$from_time."' AND
											to_time = '".$to_time."' ";
											$updated = $wpdb->query( $query );
											if($updated == 0)
											{
												if($val->weekday == '')
												{
													$week_day = date('l',strtotime($date_query));
													$weekdays = book_arrays('weekdays');
													$weekday = array_search($week_day,$weekdays);
													//echo $weekday;exit;
												}
												else
												{
													$weekday = $val->weekday;
												}
                                                $results = array();
												$query = "SELECT * FROM `".$wpdb->prefix."booking_history`
													WHERE post_id = '".$duplicate_of."'
													AND weekday = '".$weekday."'";
												//echo $query;exit;
												$results = $wpdb->get_results( $query );
												if (!$results) break;
												else
												{	
													//print_r($results);exit;
													foreach($results as $r_key => $r_val)
													{
														if($from_time == $r_val->from_time && $to_time == $r_val->to_time)
														{
															$available_booking = $r_val->available_booking - $quantity;
															$query_insert = "INSERT INTO `".$wpdb->prefix."booking_history`
																(post_id,weekday,start_date,from_time,to_time,total_booking,available_booking)
																VALUES (
																'".$duplicate_of."',
																'".$weekday."',
																'".$start_date."',
																'".$r_val->from_time."',
																'".$r_val->to_time."',
																'".$r_val->available_booking."',
																'".$available_booking."' )";
																	//echo $query_insert;exit;
															$wpdb->query( $query_insert );
	
														}
														else
														{
															$from_lockout_time = explode(":",$r_val->from_time);
															$from_hours = $from_lockout_time[0];
															$from_minute = $from_lockout_time[1];
															if(isset($query_to_time) && $query_to_time != '')
															{
																$to_lockout_time = explode(":",$r_val->to_time);
																$to_hours = $to_lockout_time[0];
																$to_minute = $to_lockout_time[1];
															}
															foreach($lockout_settings as $l_key => $l_value)
															{
																if($l_value['from_slot_hrs'] == $from_hours && $l_value['from_slot_min'] == $from_minute && $l_value['to_slot_hrs'] == $to_hours && $l_value['to_slot_min'] == $to_minute)
																{
																	$query_insert = "INSERT INTO `".$wpdb->prefix."booking_history`
																		(post_id,weekday,start_date,from_time,to_time,total_booking,available_booking)
																	VALUES (
																	'".$duplicate_of."',
																	'".$weekday."',
																	'".$start_date."',
																	'".$r_val->from_time."',
																	'".$r_val->to_time."',
																	'".$r_val->available_booking."',
																	'".$r_val->available_booking."' )";
																	$wpdb->query( $query_insert );
																}
															}
														}
													}
												}
											}
										}
										else
										{
											$query = "UPDATE `".$wpdb->prefix."booking_history`
											SET available_booking = available_booking - ".$quantity."
											WHERE post_id = '".$duplicate_of."' AND
											start_date = '".$date_query."' AND
											from_time = '".$from_time."'
											AND to_time = ''";
											//$wpdb->query( $query );
											$updated = $wpdb->query( $query );
											if($updated == 0)
											{
												if($val->weekday == '')
												{
													$week_day = date('l',strtotime($date_query));
													$weekdays = book_arrays('weekdays');
													$weekday = array_search($week_day,$weekdays);
													//echo $weekday;exit;
												}
												else
												{
													$weekday = $val->weekday;
												}
                                                $results= array();
												$query = "SELECT * FROM `".$wpdb->prefix."booking_history`
													WHERE post_id = '".$duplicate_of."'
													AND weekday = '".$weekday."'
													AND to_time = '' ";
												$results = $wpdb->get_results( $query );
												if (!$results) break;
												else
												{
													foreach($results as $r_key => $r_val)
													{
														if($from_time == $r_val->from_time)
														{
															$available_booking = $r_val->available_booking - $quantity;
															$query_insert = "INSERT INTO `".$wpdb->prefix."booking_history`
															(post_id,weekday,start_date,from_time,total_booking,available_booking)
															VALUES (
															'".$duplicate_of."',
															'".$weekday."',
															'".$start_date."',
															'".$r_val->from_time."',
															'".$r_val->available_booking."',
															'".$available_booking."' )";
															$wpdb->query( $query_insert );
														}
														else
														{
															$from_lockout_time = explode(":",$r_val->from_time);
															$from_hours = $from_lockout_time[0];
															$from_minute = $from_lockout_time[1];
															foreach($lockout_settings as $l_key => $l_value)
															{
																if($l_value['from_slot_hrs'] == $from_hours && $l_value['from_slot_min'] == $from_minute)
																{
																	$query_insert = "INSERT INTO `".$wpdb->prefix."booking_history`
																	(post_id,weekday,start_date,from_time,total_booking,available_booking)
																	VALUES (
																	'".$duplicate_of."',
																	'".$weekday."',
																	'".$start_date."',
																	'".$r_val->from_time."',
																	'".$r_val->available_booking."',
																	'".$r_val->available_booking."' )";
																	$wpdb->query( $query_insert );
																}
															}
														}
													}
												}
											}
										}
									}
								}
							}
						}
					}
					$ticket = array(apply_filters('bkap_send_ticket',$values,$order_obj));
					$ticket_content = array_merge($ticket_content,$ticket);
					$i++;
				}
				//exit;//print_r($ticket_content);exit;
				do_action('bkap_send_email',$ticket_content);
			}
			
			function quantity_check()
			{
				global $woocommerce, $wpdb;

				foreach ( $woocommerce->cart->cart_contents as $key => $value )
				{
					$duplicate_of = get_post_meta($value['product_id'], '_icl_lang_duplicate_of', true);
					if($duplicate_of == '' && $duplicate_of == null)
					{
						$post_time = get_post($value['product_id']);
						$id_query = "SELECT ID FROM `".$wpdb->prefix."posts` WHERE post_date = '".$post_time->post_date."' ORDER BY ID LIMIT 1";
						$results_post_id = $wpdb->get_results ( $id_query );
						if( isset($results_post_id) ) {
							$duplicate_of = $results_post_id[0]->ID;
						}
						else
						{
							$duplicate_of = $value['product_id'];
						}
						//$duplicate_of = $item_value['product_id'];
					}
 
					$booking_settings = get_post_meta($duplicate_of , 'woocommerce_booking_settings' , true);
					$post_title = get_post($value['product_id']);
					$date_check = '';
					if (isset($value['booking'][0]['hidden_date'])) $date_check = date('Y-m-d', strtotime($value['booking'][0]['hidden_date']));
					else $date_check = '';
					
					$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
					if (isset($saved_settings))
					{
						$time_format = $saved_settings->booking_time_format;
					}
					else
					{
						$time_format = "12";
					}
					if(isset($value['variation_id']))
					{
						$variation_id = $value['variation_id'];
					}
					else
					{
						$variation_id = '';
					}
					if(isset($booking_settings['booking_enable_time']) && $booking_settings['booking_enable_time'] == 'on')
					{
						
						$type_of_slot = apply_filters('bkap_slot_type',$duplicate_of);
						if($type_of_slot == 'multiple')
						{
							do_action('bkap_validate_cart_items',$value);
						}
						else
						{	
							if (isset($value['booking'][0]['time_slot'])) 
							{
								$time_range = explode("-", $value['booking'][0]['time_slot']);
								$from_time = date('G:i', strtotime($time_range[0]));
								if(isset($time_range[1])) $to_time = date('G:i', strtotime($time_range[1]));
								else $to_time = '';
							}
							else
							{
								$to_time = '';
								$from_time = '';
							}
							
							if($to_time != '')
							{
								$query = "SELECT total_booking, available_booking, start_date FROM `".$wpdb->prefix."booking_history`
										WHERE post_id = '".$duplicate_of."'
										AND start_date = '".$date_check."'
										AND from_time = '".$from_time."'
										AND to_time = '".$to_time."' ";
								$results = $wpdb->get_results( $query );
							}
							else
							{
								$query = "SELECT total_booking, available_booking, start_date FROM `".$wpdb->prefix."booking_history`
									WHERE post_id = '".$duplicate_of."'
									AND start_date = '".$date_check."'
									AND from_time = '".$from_time."'";
								$results = $wpdb->get_results( $query );
							}
							if (!$results) break;
							else	
							{
								if ($value['booking'][0]['time_slot'] != "")
								{
									// if current format is 12 hour format, then convert the times to 24 hour format to check in database
									if ($time_format == '12')
									{
										$time_exploded = explode("-", $value['booking'][0]['time_slot']);
										$from_time = date('h:i A', strtotime($time_exploded[0]));
										if(isset($time_range[1])) $to_time = date('h:i A', strtotime($time_exploded[1]));
										else $to_time = '';
										if($to_time != '')
										{
											$time_slot_to_display = $from_time.' - '.$to_time;
										}
										else
										{
											$time_slot_to_display = $from_time;
										}
									}
									else
									{
										if($to_time != '')
										{
											$time_slot_to_display = $from_time.' - '.$to_time;
										}
										else
										{
											$time_slot_to_display = $from_time;
										}
									}
									if( $results[0]->available_booking > 0 && $results[0]->available_booking < $value['quantity'] )
									{
										$message = $post_title->post_title.book_t('book.limited-booking-msg1') .$results[0]->available_booking.book_t('book.limited-booking-msg2').$time_slot_to_display.'.';
										wc_add_notice( $message, $notice_type = 'error');
									}
									elseif ( $results[0]->total_booking > 0 && $results[0]->available_booking == 0 )
									{	
										$message = book_t('book.no-booking-msg1').$post_title->post_title.book_t('book.no-booking-msg2').$time_slot_to_display.book_t('book.no-booking-msg3');
										wc_add_notice( $message, $notice_type = 'error');
									}
								}
							}
						}
					}
					else if (isset($booking_settings['booking_enable_multiple_day']) && $booking_settings['booking_enable_multiple_day'] == 'on')
					{
						$date_checkout = date('d-n-Y', strtotime($value['booking'][0]['hidden_date_checkout']));
						$date_cheeckin = date('d-n-Y', strtotime($value['booking'][0]['hidden_date']));
						$order_dates = $this->betweendays($date_cheeckin, $date_checkout);
						$todays_date = date('Y-m-d');

						$query_date ="SELECT DATE_FORMAT(start_date,'%d-%c-%Y') as start_date,DATE_FORMAT(end_date,'%d-%c-%Y') as end_date FROM ".$wpdb->prefix."booking_history
							WHERE start_date >='".$todays_date."' AND post_id = '".$duplicate_of."'";
						$results_date = $wpdb->get_results($query_date);	

						//print_r($results_date);
						$dates_new = array();
							
						foreach($results_date as $k => $v)
						{
							$start_date = $v->start_date;
							$end_date = $v->end_date;
							$dates = $this->betweendays($start_date, $end_date);
							//print_r($dates);
							$dates_new = array_merge($dates,$dates_new);
						}
						$dates_new_arr = array_count_values($dates_new);
							
						$lockout = "";
						if (isset($booking_settings['booking_date_lockout']))
						{
							$lockout = $booking_settings['booking_date_lockout'];
						}

						foreach ($order_dates as $k => $v)
						{
							if (array_key_exists($v,$dates_new_arr))
							{
								if ($lockout != 0 && $lockout < $dates_new_arr[$v] + $value['quantity'])
								{
									$available_tickets = $lockout - $dates_new_arr[$v];
									$message = $post_title->post_title.book_t('book.limited-booking-date-msg1')	.$available_tickets.book_t('book.limited-booking-date-msg2').$v.'.';
									wc_add_notice( $message, $notice_type = 'error');
								}
							}
							else
							{
								if ($lockout != 0 && $lockout < $value['quantity'])
								{
									$available_tickets = $lockout;
									$message = $post_title->post_title.book_t('book.limited-booking-date-msg1')	.$available_tickets.book_t('book.limited-booking-date-msg2').$v.'.';
									wc_add_notice( $message, $notice_type = 'error');
								}
							}
						}
					}
					else
					{	
						$query = "SELECT total_booking,available_booking, start_date FROM `".$wpdb->prefix."booking_history`
									WHERE post_id = '".$duplicate_of."'
									AND start_date = '".$date_check."' ";
						$results = $wpdb->get_results( $query );

						if(!$results) break;
						else
						{
							if( $results[0]->available_booking > 0 && $results[0]->available_booking < $value['quantity'] )
							{
								$message = $post_title->post_title.book_t('book.limited-booking-date-msg1')	.$results[0]->available_booking.book_t('book.limited-booking-date-msg2').$results[0]->start_date.'.';
								wc_add_notice( $message, $notice_type = 'error');
								
							}
							elseif ( $results[0]->total_booking > 0 && $results[0]->available_booking == 0 )
							{
								$message = book_t('book.no-booking-date-msg1').$post_title->post_title.book_t('book.no-booking-date-msg2').$results[0]->start_date.book_t('book.no-booking-date-msg3');
								wc_add_notice( $message, $notice_type = 'error');
								
							}
						}
					}
				}
			}

			function remove_time_slot() {
				
				global $wpdb;
				
				if(isset($_POST['details']))
				{
					$details = explode("&", $_POST['details']);
				
				$date_delete = $details[2];
				$date_db = date('Y-m-d', strtotime($date_delete));
				$id_delete = $details[0];
				$book_details = get_post_meta($details[1], 'woocommerce_booking_settings', true);
				
				unset($book_details[booking_time_settings][$date_delete][$id_delete]);
				if( count($book_details[booking_time_settings][$date_delete]) == 0 )
				{
					unset($book_details[booking_time_settings][$date_delete]);
					if ( substr($date_delete,0,7) == "booking" )
					{
						$book_details[booking_recurring][$date_delete] = '';
					}
					elseif ( substr($date_delete,0,7) != "booking" )
					{
						$key_date = array_search($date_delete, $book_details[booking_specific_date]);
						unset($book_details[booking_specific_date][$key_date]);
					}
				}
				update_post_meta($details[1], 'woocommerce_booking_settings', $book_details);
			
				if ( substr($date_delete,0,7) != "booking" )
				{
					if ($details[4] == "0:00") $details[4] = "";
					$delete_query = "DELETE FROM `".$wpdb->prefix."booking_history`
								 WHERE
								 post_id = '".$details[1]."' AND
								 start_date = '".$date_db."' AND
								 from_time = '".$details[3]."' AND
								 to_time = '".$details[4]."' ";
					//echo $delete_query;
					$wpdb->query($delete_query);
					
					if ($details[3] != "") $from_time = date('h:i A', strtotime($details[3]));
					if ($details[4] != "") $to_time = date('h:i A', strtotime($details[4]));

					$delete_query = "DELETE FROM `".$wpdb->prefix."booking_history`
								WHERE
								post_id = '".$details[1]."' AND
								start_date = '".$date_db."' AND
								from_time = '".$from_time."' AND
								to_time = '".$to_time."' ";
								
					$wpdb->query($delete_query);
						
				}
				elseif ( substr($date_delete,0,7) == "booking" )
				{
					if ($details[4] == "0:00") $details[4] = "";
					$delete_query = "DELETE FROM `".$wpdb->prefix."booking_history`
								 WHERE
								 post_id = '".$details[1]."' AND
								 weekday = '".$date_delete."' AND
								 from_time = '".$details[3]."' AND
								 to_time = '".$details[4]."' ";
					//echo $delete_query;
					$wpdb->query($delete_query);
					
					if ($details[3] != "") $from_time = date('h:i A', strtotime($details[3]));
					if ($details[4] != "") $to_time = date('h:i A', strtotime($details[4]));
					
					$delete_query = "DELETE FROM `".$wpdb->prefix."booking_history`
								WHERE
								post_id = '".$details[1]."' AND
								weekday = '".$date_delete."' AND
								from_time = '".$from_time."' AND
								to_time = '".$to_time."' ";
								
					$wpdb->query($delete_query);
				}
				}
				
			}
			
			function remove_day() {
			
				global $wpdb;
			
				if(isset($_POST['details']))
				{
				$details = explode("&", $_POST['details']);
				$date_delete = $details[0];
				$book_details = get_post_meta($details[1], 'woocommerce_booking_settings', true);
				
				if ( substr($date_delete,0,7) != "booking" )
				{
					$date_db = date('Y-m-d', strtotime($date_delete));
					
					$key_date = array_search($date_delete, $book_details[booking_specific_date]);
					unset($book_details[booking_specific_date][$key_date]);
					
					$delete_query = "DELETE FROM `".$wpdb->prefix."booking_history`
									WHERE
									post_id = '".$details[1]."' AND
									start_date = '".$date_db."' ";
					//echo $delete_query;
					$wpdb->query($delete_query);
						
				}
				elseif ( substr($date_delete,0,7) == "booking" )
				{
					$book_details[booking_recurring][$date_delete] = '';
					$delete_query = "DELETE FROM `".$wpdb->prefix."booking_history`
									WHERE
									post_id = '".$details[1]."' AND
									weekday = '".$date_delete."' ";
					//echo $delete_query;
					$wpdb->query($delete_query);
						
				}
				update_post_meta($details[1], 'woocommerce_booking_settings', $book_details);
				}
			}
			
		function remove_specific() {
				
				global $wpdb;
				
				if(isset($_POST['details']))
				{
				$details = $_POST['details'];
				$book_details = get_post_meta($details, 'woocommerce_booking_settings', true);
			
				foreach( $book_details[booking_specific_date] as $key => $value )
				{
					if (array_key_exists($value,$book_details[booking_time_settings])) unset($book_details[booking_time_settings][$value]);
				}
				unset($book_details[booking_specific_date]);
				update_post_meta($details, 'woocommerce_booking_settings', $book_details);

				$delete_query = "DELETE FROM `".$wpdb->prefix."booking_history`
								 WHERE
								 post_id = '".$details."' AND
								 weekday = '' ";
				//echo $delete_query;
				$wpdb->query($delete_query);
				}
			}
			
			function remove_recurring() {
			
			
				global $wpdb;
				
				if(isset($_POST['details']))
				{
				$details = $_POST['details'];
				$book_details = get_post_meta($details, 'woocommerce_booking_settings', true);
				$weekdays = book_arrays('weekdays');
				foreach ($weekdays as $n => $day_name)
				{
					if (array_key_exists($n,$book_details[booking_time_settings])) unset($book_details[booking_time_settings][$n]);
					$book_details[booking_recurring][$n] = '';
					$delete_query = "DELETE FROM `".$wpdb->prefix."booking_history`
									WHERE
									post_id = '".$details."' AND
									weekday = '".$n."' ";
					$wpdb->query($delete_query);
				}
				
				update_post_meta($details, 'woocommerce_booking_settings', $book_details);
				}
			
			}
			function woocommerce_cancel_order($order_id)
			{
				global $wpdb,$post;
				$array = array();
				$order_obj = new WC_order($order_id);
				$order_items = $order_obj->get_items();
				$select_query = "SELECT booking_id FROM `".$wpdb->prefix."booking_order_history`
								WHERE order_id='".$order_id."'";
				$results = $wpdb->get_results ( $select_query );
				
				foreach($results as $k => $v)
				{
					$b[] = $v->booking_id;
					$select_query_post = "SELECT post_id,id FROM `".$wpdb->prefix."booking_history`
								WHERE id='".$v->booking_id."'";
					$results_post[] = $wpdb->get_results($select_query_post);
				}
				//exit;
				if (isset($results_post) && count($results_post) > 0 && $results_post != false)
				{
					foreach($results_post as $k => $v)
					{
						if (isset($v[0]->id)) $a[$v[0]->post_id][] = $v[0]->id;
					//	$a[$v[0]->post_id][] = $v[0]->id;
					}	
				}
				$i = 0;
				foreach($order_items as $item_key => $item_value)
				{
					$product_id = get_post_meta($item_value['product_id'], '_icl_lang_duplicate_of', true);
					if($product_id == '' && $product_id == null)
					{
						$post_time = get_post($item_value['product_id']);
						$id_query = "SELECT ID FROM `".$wpdb->prefix."posts` WHERE post_date = '".$post_time->post_date."' ORDER BY ID LIMIT 1";
						$results_post_id = $wpdb->get_results ( $id_query );
						if( isset($results_post_id) ) {
							$product_id = $results_post_id[0]->ID;
						}
						else
						{
							$product_id = $item_value['product_id'];
						}
						//$duplicate_of = $item_value['product_id'];
					}
					if(array_key_exists("variation_id",$item_value))
					{
						$variation_id = $item_value['variation_id'];
					}
					else
					{
						$variation_id = '';
					}
					if(in_array($product_id,(array)$array))
					{
					}
					else
					{
						$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
						$qty = $item_value['qty'];
						if (isset($a[$product_id])) $result = $a[$product_id];
						$e = 0;
						$from_time = '';
						$to_time = '';
						$date_date = '';
						$end_date = '';
						if (isset($result) && count($result) > 0 && $result != false)
						{
							foreach($result as $k =>$v)
							{
								$booking_id = $result[$e];
								if(isset($booking_settings['booking_enable_multiple_day']) && $booking_settings['booking_enable_multiple_day'] == 'on')
								{
									$select_data_query = "SELECT start_date,end_date FROM `".$wpdb->prefix."booking_history`
													WHERE id='".$booking_id."'";
										$results_data = $wpdb->get_results ( $select_data_query );
										$j=0;
										foreach($results_data as $k => $v)
										{
											$start_date = $results_data[$j]->start_date;
											$end_date = $results_data[$j]->end_date;
											$sql_delete_query = "DELETE FROM `".$wpdb->prefix."booking_history` WHERE id = '".$booking_id."' AND start_date =	'".$start_date."' AND end_date = '".$end_date."' ";
											$wpdb->query( $sql_delete_query );
											$j++;
										}
								}
								else if(isset($booking_settings['booking_enable_time']) && $booking_settings['booking_enable_time'] == 'on')
								{
									$type_of_slot = apply_filters('bkap_slot_type',$product_id);
									if($type_of_slot == 'multiple')
									{
										do_action('bkap_order_status_cancelled',$order_id,$item_value,$booking_id);
									}
									else
									{
										$select_data_query = "SELECT * FROM `".$wpdb->prefix."booking_history`
												WHERE id='".$booking_id."'";
										$results_data = $wpdb->get_results ( $select_data_query );
										$j=0;
										foreach($results_data as $k => $v)
										{
											$start_date = $results_data[$j]->start_date;
											$from_time = $results_data[$j]->from_time;
											$to_time = $results_data[$j]->to_time;
											if($from_time != '' && $to_time != '' || $from_time != '')
											{
												if($to_time != '')
												{
													$query = "UPDATE `".$wpdb->prefix."booking_history`
														SET available_booking = available_booking + ".$qty."
														WHERE 
														id = '".$booking_id."' AND
													start_date = '".$start_date."' AND
													from_time = '".$from_time."' AND
													to_time = '".$to_time."'";

													$select = "SELECT * FROM `".$wpdb->prefix."booking_history`
													WHERE post_id = '".$product_id."' AND
													start_date = '".$start_date."' AND
													from_time = '".$from_time."' AND
													to_time = '".$to_time."'";
													$select_results = $wpdb->get_results( $select );
													foreach($select_results as $k => $v)
													{
														$details[$product_id] = $v;
													}
												}
												else
												{
													$query = "UPDATE `".$wpdb->prefix."booking_history`
														SET available_booking = available_booking + ".$qty."
														WHERE 
														id = '".$booking_id."' AND
														start_date = '".$start_date."' AND
														from_time = '".$from_time."'";
													$select = "SELECT * FROM `".$wpdb->prefix."booking_history`
													WHERE post_id = '".$product_id."' AND
													start_date = '".$start_date."' AND
													from_time = '".$from_time."'";
													$select_results = $wpdb->get_results( $select );
													foreach($select_results as $k => $v)
													{
														$details[$product_id] = $v;
													}
												}
												$wpdb->query( $query );
											}	
											$j++;
										}
									}
								}
								else
								{
									$select_data_query = "SELECT * FROM `".$wpdb->prefix."booking_history`
											WHERE id='".$booking_id."'";
									$results_data = $wpdb->get_results ( $select_data_query );
									$j=0;
									foreach($results_data as $k => $v)
									{
										$start_date = $results_data[$j]->start_date;
										$from_time = $results_data[$j]->from_time;
										$to_time = $results_data[$j]->to_time;
										$query = "UPDATE `".$wpdb->prefix."booking_history`
											SET available_booking = available_booking + ".$qty."
											WHERE 
											id = '".$booking_id."' AND
											start_date = '".$start_date."' AND
											from_time = '' AND
											to_time = ''";
										$wpdb->query( $query );
									}
									$j++;
								}
								$e++;
							}
						}
					}
					$book_global_settings = json_decode(get_option('woocommerce_booking_global_settings'));
					
					$label =  get_option("book.item-meta-date");
					//print_r($item_value);
					//$date = str_replace("-","/",$item_value[$label]);
					$hidden_date = date('d-n-Y',strtotime($start_date));
					//print_r($hidden_date);
					if (isset($booking_settings['booking_time_settings'][$hidden_date])) $lockout_settings = $booking_settings['booking_time_settings'][$hidden_date];
					else $lockout_settings = array();
					if(count($lockout_settings) > 0)
					{
						$week_day = date('l',strtotime($hidden_date));
						//print_r($week_day);
						$weekdays = book_arrays('weekdays');
						//print_r($weekdays);
						$weekday = array_search($week_day,$weekdays);
						if (isset($booking_settings['booking_time_settings'][$weekday])) $lockout_settings = $booking_settings['booking_time_settings'][$weekday];
						else $lockout_settings = array();
						//print_r($lockout_settings);
					}
					$from_lockout_time = explode(":",$from_time);
					if(isset($from_lockout_time[0]))
						$from_hours = $from_lockout_time[0];
					else
						$from_hours = '';
					if(isset($from_lockout_time[1]))
						$from_minute = $from_lockout_time[1];
					else 
						$from_minute = '';
					if($to_time != '')
					{
						$to_lockout_time = explode(":",$to_time);
						$to_hours = $to_lockout_time[0];
						$to_minute = $to_lockout_time[1];
					}
					else
					{
						$to_hours = '';
						$to_minute = '';
					}
					if(count($lockout_settings) > 0)
					{
						foreach($lockout_settings as $l_key => $l_value)
						{
							if($l_value['from_slot_hrs'] == $from_hours && $l_value['from_slot_min'] == $from_minute && $l_value['to_slot_hrs'] == $to_hours && $l_value['to_slot_min'] == $to_minute)
							{
								if (isset($l_value['global_time_check'])) $global_timeslot_lockout = $l_value['global_time_check'];
								else $global_timeslot_lockout = '';
								//print_r($global_timeslot_lockout);
							}
						}
					}
					//print_r($book_global_settings);
					//print_r($lockout_settings);exit;
					if($book_global_settings->booking_global_timeslot == 'on' || $global_timeslot_lockout == 'on')
					{
						$args = array( 'post_type' => 'product', 'posts_per_page' => -1 );
						$product = query_posts( $args );
						foreach($product as $k => $v)
						{
							$product_ids[] = $v->ID;
						}
						//print_r($details);
						//print_r($product_ids);exit;
						foreach($product_ids as $k => $v)
						{
							$duplicate_of = get_post_meta($v, '_icl_lang_duplicate_of', true);
							if($duplicate_of == '' && $duplicate_of == null)
							{
								$post_time = get_post($v);
								$id_query = "SELECT ID FROM `".$wpdb->prefix."posts` WHERE post_date = '".$post_time->post_date."' ORDER BY ID LIMIT 1";
								$results_post_id = $wpdb->get_results ( $id_query );
								if( isset($results_post_id) ) {
									$duplicate_of = $results_post_id[0]->ID;
								}
								else
								{
									$duplicate_of = $v;
								}
								//$duplicate_of = $item_value['product_id'];
							}
							$booking_settings = get_post_meta($v, 'woocommerce_booking_settings' , true);
							if(isset($booking_settings['booking_enable_time']) && $booking_settings['booking_enable_time'] == 'on')
							{
								//echo "ehere";exit;
								if(count($details) > 0)
								{
									if(!array_key_exists($duplicate_of,$details))
									{	
										foreach($details as $key => $val)
										{
											//$booking_settings = get_post_meta($duplicate_of, 'woocommerce_booking_settings', true);
											//echo"<pre>";print_r($booking_settings);echo"</pre>";exit;
											$start_date = $val->start_date;
											$from_time = $val->from_time;
											$to_time = $val->to_time;
											if($to_time != "")
											{
												$query = "UPDATE `".$wpdb->prefix."booking_history`
												SET available_booking = available_booking + ".$qty."
												WHERE post_id = '".$duplicate_of."' AND
												start_date = '".$start_date."' AND
												from_time = '".$from_time."' AND
												to_time = '".$to_time."'";
												$wpdb->query($query);
											//echo $query;exit;
											}
											else
											{
												$query = "UPDATE `".$wpdb->prefix."booking_history`
													SET available_booking = available_booking + ".$qty."
													WHERE post_id = '".$duplicate_of."' AND
													start_date = '".$start_date."' AND
													from_time = '".$from_time."'";
												//$wpdb->query( $query );
												$wpdb->query( $query );	
											}
										}
									}
								}
							}
						}
					}
					$i++;
					$array[] = $product_id;
				}
			}
		}		
	}
	
	$woocommerce_booking = new woocommerce_booking();
	
}