<?php 
//if (is_woocommerce_active())
{
	/**
	 * Localisation
	 **/
	load_plugin_textdomain('bkap_admin_bookings', false, dirname( plugin_basename( __FILE__ ) ) . '/');

	/**
	 * bkap_admin_bookings class
	 **/
	if (!class_exists('bkap_admin_bookings')) {

		class bkap_admin_bookings {

			public function __construct() {
				// Initialize settings
				register_activation_hook( __FILE__, array(&$this, 'admin_bookings_activate'));
				
				// Scripts
				add_action( 'admin_enqueue_scripts', array(&$this, 'admin_booking_enqueue_scripts_css' ));
				add_action( 'admin_enqueue_scripts', array(&$this, 'admin_booking_enqueue_scripts_js' ));
				add_action('init', array(&$this, 'admin_load_ajax'));
				add_action( 'wp_ajax_woocommerce_remove_order_item_meta', array(&$this, 'woocommerce_ajax_remove_order_item_meta_admin' ), 10, 3);
				add_action( 'wp_ajax_woocommerce_remove_order_item', array(&$this,'woocommerce_ajax_remove_order_item_admin' ));
				
				// used to add new settings on the product page booking box
				add_action('wp_ajax_woocommerce_add_order_item_meta', array(&$this, 'booking_order_box') );
				//add_action('woocommerce_order_item_line_item_html', array(&$this, 'booking_order_button') );
				//add_action('woocommerce_admin_order_item_values', array(&$this, 'booking_order_box'), 10, 3 );
				//add_action( 'woocommerce_order_item_meta', array(&$this, 'add_order_item_meta_admin'), 10, 2 );
				add_action('woocommerce_process_shop_order_meta', array(&$this, 'update_order_details'), 10, 3 );
				//add_filter('manage_edit-shop_order_columns', array(&$this, 'another_function'));
				
				//add_action('woocommerce_calculate_totals', array(&$this, 'new_function' ));
				
			}
			function admin_load_ajax()
			{
				if ( !is_user_logged_in() )
				{
					add_action('wp_ajax_nopriv_check_for_time_slot_admin', array(&$this, 'check_for_time_slot_admin'));
					add_action('wp_ajax_nopriv_insert_admin_date', array(&$this, 'insert_admin_date'));
				}
				else
				{
					add_action('wp_ajax_check_for_time_slot_admin', array(&$this, 'check_for_time_slot_admin'));
					add_action('wp_ajax_insert_admin_date', array(&$this, 'insert_admin_date'));
				}
			}
			function admin_bookings_activate()
			{
			
				global $wpdb;
				
				$table_name = $wpdb->prefix . "";
				
				$sql = "" ;
				
				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				dbDelta($sql);
				
			}
			
			function get_post_by_title($post_title, $output = OBJECT) {
				
				global $wpdb;
				$post = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type='product'", $post_title ));
				if ( $post )
					return get_post($post, $output);
				
				return null;
			}
			function get_per_night_price($post_id, $days, $booking_settings, $variation_id) {
			
				global $wpdb;
				//echo $variation_id;exit;
				$product_id = $post_id;
				if ($variation_id != '')
				{
					//$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
					if (isset($booking_settings['booking_block_price_enable']))
					{
						$_product = new WC_Product_Variation( $variation_id );
						$var_attributes = $_product->get_variation_attributes( );
						
						$attribute_names = str_replace("-", " ", $var_attributes);
						/* Querying the database */
						$j = 1;
						$k = 0;
						$attribute_sub_query = '';
						foreach($attribute_names as $key => $value)
						{
							//echo "here".$value;
							$attribute_sub_query .= " c".$k.".attribute_id = '$j' AND c".$k.".meta_value = '$value' AND";
							$j++;
							$k++;
						}
						
						$query = "SELECT c0.block_id FROM `".$wpdb->prefix."booking_block_price_attribute_meta` AS c0
									JOIN `".$wpdb->prefix."booking_block_price_attribute_meta` AS c1 ON c1.block_id=c0.block_id
									WHERE ".$attribute_sub_query." c0.post_id = '".$product_id."'";
									//print_r("here".$query);
						$results = $wpdb->get_results ( $query );
						
						//$number_of_days =  strtotime($checkout_date) - strtotime($checkin_date);
						$number = $days;
						$e = 0;
						foreach($results as $k => $v)
						{
							$query = "SELECT price_per_day, fixed_price FROM `".$wpdb->prefix."booking_block_price_meta`
							WHERE id = '".$v->block_id."' AND post_id = '".$product_id."' AND minimum_number_of_days <='".$number."' AND maximum_number_of_days >= '".$number."'";
							//echo $query;
							$results_price[$e] = $wpdb->get_results($query);
							$e++;
						}
						
						$price = 0;
						
						foreach($results_price as $k => $v)
						{
							if(!empty($results_price[$k]))
							{
								if(isset($booking_settings['booking_partial_payment_radio']) && $booking_settings['booking_partial_payment_radio']=='value')
								{
									$price = $booking_settings['booking_partial_payment_value_deposit'];
								}
								elseif(isset($booking_settings['booking_partial_payment_radio']) &&		$booking_settings['booking_partial_payment_radio']=='percent')
								{
									$sale_price = get_post_meta( $variation_id, '_sale_price', true);
									if (isset($booking_settings['booking_block_price_enable']) && $booking_settings['booking_block_price_enable'] == "yes")
									{
										if($v[0]->fixed_price != 0)
										{
											$oprice = $v[0]->fixed_price;
											$pprice = "-fixed";
										}
										else
										{
											$oprice = $v[0]->price_per_day;
											$pprice = "-per_day";
										}
										$price = (($booking_settings['booking_partial_payment_value_deposit']*$oprice)/100);
										$price .= $pprice;
									}
									elseif($sale_price == '')
									{
										$regular_price = get_post_meta( $variation_id, '_regular_price', true);
										$price = (($booking_settings['booking_partial_payment_value_deposit']*$regular_price)/100);
									}
									else
									{
										$price = (($booking_settings['booking_partial_payment_value_deposit']*$sale_price)/100);
									}
								}
								else if(isset($booking_settings['booking_block_price_enable']) && $booking_settings['booking_block_price_enable'] == "yes")
								{
									//echo $v[0]->fixed_price;
									if($v[0]->fixed_price != 0)
									{
										$price = $v[0]->fixed_price;
										$price .= "-fixed";
									}
									else
									{
										$price = $v[0]->price_per_day;
										$price .= "-per_day";
									}
								}
							}
							else
							{
								unset($results_price[$k]);
							}
						}
					}
				}
				else
				{
					$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
					if (isset($booking_settings['booking_block_price_enable']))
					{
						//$number_of_days =  strtotime($checkout_date) - strtotime($checkin_date);
						$number = $days;
						$query = "SELECT price_per_day, fixed_price FROM `".$wpdb->prefix."booking_block_price_meta`
							WHERE post_id = '".$product_id."' AND minimum_number_of_days <='".$number."' AND maximum_number_of_days >= '".$number."'";
							//echo $query;
						$results_price = $wpdb->get_results($query);
						if(count($results_price) == 0)
						{
							$sale_price = get_post_meta( $product_id, '_sale_price', true);
							if($sale_price == '')
							{
								$regular_price = get_post_meta( $product_id, '_regular_price', true);
								$price = $regular_price;
								$price .= "-";
							}
							else
							{
								$price = $sale_price;
								$price .= "-";
							}
						}
						else
						{
							foreach($results_price as $k => $v)
							{
								//print_r($v);
								if(!empty($results_price[$k]))
								{
									if(isset($booking_settings['booking_partial_payment_radio']) && $booking_settings['booking_partial_payment_radio']=='value')
									{
										$price = $booking_settings['booking_partial_payment_value_deposit'];
									}
									elseif(isset($booking_settings['booking_partial_payment_radio']) && $booking_settings['booking_partial_payment_radio']=='percent')
									{
										$sale_price = get_post_meta( $product_id, '_sale_price', true);
										if (isset($booking_settings['booking_block_price_enable']) && $booking_settings['booking_block_price_enable'] == "yes")
										{
											if($v->fixed_price != 0)
											{
												$oprice = $v->fixed_price;
												$pprice = "-fixed";
											}
											else
											{
												$oprice = $v->price_per_day;
												$pprice = "-per_day";
											}
											$price = (($booking_settings['booking_partial_payment_value_deposit']*$oprice)/100);
											$price .= $pprice;
										}
										elseif($sale_price == '')
										{
											$regular_price = get_post_meta( $product_id, '_regular_price', true);
											$price = (($booking_settings['booking_partial_payment_value_deposit']*$regular_price)/100);
										}
										else
										{
											$price = (($booking_settings['booking_partial_payment_value_deposit']*$sale_price)/100);
										}
									}
									else if(isset($booking_settings['booking_block_price_enable']) && $booking_settings['booking_block_price_enable'] == "yes")
									{	
										//echo $v[0]->fixed_price;
										if($v->fixed_price != 0)
										{
											$price = $v->fixed_price;
											$price .= "-fixed";
										}
										else
										{
											$price = $v->price_per_day;
											$price .= "-per_day";
										}
									}
								}
								else
								{
									unset($results_price[$k]);
								}
							}
						}
					}
				}
				//echo "<pre>";print_r($price);echo "</pre>";exit;
				return $price;
			}
			function insert_admin_date() 
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
			function get_fixed_blocks($post_id)
			{
				global $wpdb;
				$query = "SELECT * FROM `".$wpdb->prefix."booking_fixed_blocks` WHERE post_id = '".$post_id."'";
				$results = $wpdb->get_results($query);
			
				return $results;
			}
			function get_fixed_blocks_count($post_id)
			{
				global $wpdb;
				$query = "SELECT * FROM `".$wpdb->prefix."booking_fixed_blocks` WHERE post_id = '".$post_id."'";
				$results = $wpdb->get_results($query);
				
				return count($results);
			}
			function date_lockout($start_date,$post_id)
			{
				global $wpdb,$post;
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
				$date_lockout = "SELECT sum(total_booking) - sum(available_booking) AS bookings_done FROM `".$wpdb->prefix."booking_history`
				WHERE start_date='".$start_date."' AND post_id='".$duplicate_of."'";
					//echo $date_lockout;
				$results_date_lock = $wpdb->get_results($date_lockout);
					//print_r($results_date_lock);
				$bookings_done = $results_date_lock[0]->bookings_done;
				return $bookings_done;
			}
			function booking_order_box()
			{

				global $woocommerce, $wpdb;
				
				check_ajax_referer( 'order-item', 'security' );
				$meta_id_next = '';
				$meta_id = '';
				$meta_id = woocommerce_add_order_item_meta( absint( $_POST['order_item_id'] ), __( 'Name', 'woocommerce'), __( 'Value', 'woocommerce' ) );
				//print_r($meta_ids);
				//echo $meta_id; die();
				/*$meta_query = $check_query = "SELECT MAX(meta_id) AS meta_id FROM `".$wpdb->prefix."woocommerce_order_itemmeta`
						WHERE order_item_id ='".$_POST['order_item_id']."'
						";
				$meta_results = $wpdb->get_results($meta_query);
				$meta_id = $meta_results[0]->meta_id;*/
				$bookings_added = woocommerce_get_order_item_meta($_POST['order_item_id'], get_option("book.date-label"), true);
				if($bookings_added == '')
				{
					//print_r("ehre".$bookings_added);die();
					if ( $meta_id )
					{
						$check_query = "SELECT meta_value AS product_id FROM `".$wpdb->prefix."woocommerce_order_itemmeta`
						WHERE meta_key ='_product_id'
						AND order_item_id ='".$_POST['order_item_id']."'
						";
					$results_check = $wpdb->get_results ( $check_query );
					$product_id = $results_check[0]->product_id;
					//print_r($product_id);
					$prod_id = get_post_meta($product_id, '_icl_lang_duplicate_of', true);
					if($prod_id == '' && $prod_id == null)
					{
						//	$duplicate_of = $cart_item['product_id'];
						$post_time = get_post($product_id);
						$id_query = "SELECT ID FROM `".$wpdb->prefix."posts` WHERE post_date = '".$post_time->post_date."' ORDER BY ID LIMIT 1";
						$results_post_id = $wpdb->get_results ( $id_query );
					if( isset($results_post_id) ) {
						$prod_id = $results_post_id[0]->ID;
					}
					else
					{
						$prod_id = $product_id;
					}
				}
				$cart_item_key = $_POST['order_item_id'];
				$product_settings = get_post_meta($prod_id, 'woocommerce_booking_settings', true);
				$i = 0;
				//echo "PROD<pre>";print_r($product_settings);echo "</pre>";
				echo '<input type="hidden" id="order_item_ids"  name="order_item_ids" value="'.$_POST['order_item_id'].'"/>';
				 if ((isset($product_settings['booking_enable_multiple_day']) && $product_settings['booking_enable_multiple_day'] == 'on') && (isset($product_settings['booking_fixed_block_enable']) && $product_settings['booking_fixed_block_enable'] == 'yes' )) 
				{
 				 	
 				 	$results = $this->get_fixed_blocks($prod_id);
 				 	//print_r($results);die();

					if (count($results) > 0)
					{	
						echo '<tr data-meta_id="'.$meta_id.'"><td><input type="text" name="meta_key[' . $meta_id . ']" value="Select Period" /></td><td><select name="meta_value[' . $meta_id . ']" id="admin_block_option_'.$_POST['order_item_id'].'">';
						
						foreach ($results as $key => $value)
						{
							echo '<option id = '.$value->start_day.'&'.$value->number_of_days.'&'.$value->price.' value="'.$value->block_name.'">'.$value->block_name.'</option>';
						} 
						echo '</select></td><td width="1%"><button class="remove_order_item_meta button">&times;</button></td></tr>';
						$meta_id_start = $meta_id + 1;
						$meta_id_end = $meta_id_start +  1; 
						echo '<input type="hidden" id="meta_id_start"  name="meta_id_start" value="'.$meta_id_start.'"/>';
						echo '<input type="hidden" id="meta_id_end"  name="meta_id_end" value="'.$meta_id_end.'"/>';
						?>
						<script type="text/javascript">	
						var order_item_id = jQuery("#order_item_ids").val();
						jQuery("#admin_block_option_"+order_item_id).change(function()
						{
							//alert();
							if ( jQuery("#admin_block_option_"+order_item_id).val() != "" )
							{
								var passed_id = jQuery(this).children(":selected").attr("id");
								var exploded_id = passed_id.split('&');
								console.log(exploded_id);
								var meta_id_start = jQuery("#meta_id_start").val();
								var meta_id_end = jQuery("#meta_id_end").val();
								jQuery("#admin_block_option_start_day_"+order_item_id).val(exploded_id[0]);
								jQuery("#admin_block_option_number_of_day_"+order_item_id).val(exploded_id[1]);
								jQuery("#admin_block_option_price_"+order_item_id).val(exploded_id[2]);
								jQuery("#wapbk_admin_hidden_date_"+order_item_id).val("");
								jQuery("#wapbk_admin_hidden_date_checkout_"+order_item_id).val("");
								//jQuery("#show_time_slot").html("");
								jQuery("#admin_booking_calender_"+ meta_id_start).datepicker("setDate");
								jQuery("#admin_booking_calender_checkout_" + meta_id_end).datepicker("setDate");
							}
						});
	
	
						</script>
	
						<?php

						if (count($results)>=0)
						{
							$sd=$results[0]->start_day;
							$nd=$results[0]->number_of_days;
							$pd=$results[0]->price;
						}
						echo ' <input type="hidden" id="admin_block_option_enabled_'.$_POST['order_item_id'].'"  name="admin_block_option_enabled_'.$_POST['order_item_id'].'" value="on"/> 

						<input type="hidden" id="admin_block_option_start_day_'.$_POST['order_item_id'].'"  name="admin_block_option_start_day_'.$_POST['order_item_id'].'" value="'.$sd.'"/> 
						
						<input type="hidden" id="admin_block_option_number_of_day_'.$_POST['order_item_id'].'"  name="admin_block_option_number_of_day_'.$_POST['order_item_id'].'" value="'.$nd.'"/>
					
						<input type="hidden" id="admin_block_option_price_'.$_POST['order_item_id'].'"name="admin_block_option_price_'.$_POST['order_item_id'].'" value="'.$pd.'"/>';	
					}
					else 
					{
						$number_of_fixed_price_blocks = 0;
						echo ' <input type="hidden" id="admin_block_option_enabled_'.$_POST['order_item_id'].'"  name="admin_block_option_enabled_'.$_POST['order_item_id'].'" value="off"/>
						
						<input type="hidden" id="admin_block_option_start_day_'.$_POST['order_item_id'].'"  name="admin_block_option_start_day_'.$_POST['order_item_id'].'" value=""/> 
						
						<input type="hidden" id="admin_block_option_number_of_day_'.$_POST['order_item_id'].'"  name="admin_block_option_number_of_day_'.$_POST['order_item_id'].'" value=""/>
						
						<input type="hidden" id="admin_block_option_price_'.$_POST['order_item_id'].'"  name="admin_block_option_price_'.$_POST['order_item_id'].'" value=""/>';
					}
					$meta_ids[$cart_item_key][$i] = $meta_id;
					$meta_id = $meta_id + 1;
					$i++;
 				 }
				if (isset($product_settings['booking_enable_date']) && $product_settings['booking_enable_date'] == 'on' ):
					$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
					//echo "<pre>";print_r($saved_settings);echo "</pre>";
					
					//woocommerce_add_order_item_meta( $item_number,get_option("book.date-label"),'');
					//$order = new WC_Order($order_id);
					//$meta = $order->has_meta($item_number);
					if ($saved_settings == '')
					{
						$saved_settings = new stdClass();
						$saved_settings->booking_date_format = 'd MM, yy';
						$saved_settings->booking_time_format = '12';
						$saved_settings->booking_months = '1';
					}
					$meta_ids[$cart_item_key][$i] = $meta_id;
					$i++;
					echo '<tr data-meta_id="'.$meta_id.'"><td><input type="text" name="meta_key[' . $meta_id . ']" value="'.get_option("book.item-meta-date").'" /></td><td><input type="text" name="meta_value[' . $meta_id . ']" value="" /></td><td width="1%"><button class="remove_order_item_meta button">&times;</button></td></tr>';
					if ( $product_settings != '' ) :
					// fetch specific booking dates
					$booking_dates_arr = $product_settings['booking_specific_date'];
					
					$booking_dates_str = "";
					$meta_id_next = 0;
					//$meta_id_checkout = 0;
					if ($product_settings['booking_specific_booking'] == "on")
					{
						if(!empty($booking_dates_arr)){
							foreach ($booking_dates_arr as $k => $v)
							{
								$booking_dates_str .= '"'.$v.'",';
							}
						}
						$booking_dates_str = substr($booking_dates_str,0,strlen($booking_dates_str)-1);
					}
					print('<input type="hidden" name="wapbk_admin_booking_dates_'.$_POST['order_item_id'].'" id="wapbk_admin_booking_dates_'.$_POST['order_item_id'].'" value=\''.$booking_dates_str.'\'>');
					
					if (isset($saved_settings->booking_global_holidays))
					{
						$book_global_holidays = $saved_settings->booking_global_holidays;
						$book_global_holidays = substr($book_global_holidays,0,strlen($book_global_holidays));
						$book_global_holidays = '"'.str_replace(',', '","', $book_global_holidays).'"';
					}
					else
					{
						$book_global_holidays = '';
					}
					//echo "here";die();
					print('<input type="hidden" name="wapbk_admin_booking_global_holidays_'.$_POST['order_item_id'].'" id="wapbk_admin_booking_global_holidays_'.$_POST['order_item_id'].'" value=\''.$book_global_holidays.'\'>');
					
					$booking_holidays_string = '"'.str_replace(',', '","', $product_settings['booking_product_holiday']).'"';
					print('<input type="hidden" name="wapbk_admin_booking_holidays_'.$_POST['order_item_id'].'" id="wapbk_admin_booking_holidays_'.$_POST['order_item_id'].'" value=\''.$booking_holidays_string.'\'>');
					
					//Default settings
					$default = "Y";
					if ((isset($product_settings['booking_recurring_booking']) && $product_settings['booking_recurring_booking'] == "on") || (isset($product_settings['booking_specific_booking']) && $product_settings['booking_specific_booking'] == "on")) $default = "N";
					
					foreach ($product_settings['booking_recurring'] as $wkey => $wval)
					{
						if ($default == "Y")
						{
							print('<input type="hidden" name="wapbk_admin_'.$wkey.'_'.$_POST['order_item_id'].'" id="wapbk_admin_'.$wkey.'_'.$_POST['order_item_id'].'" value="on">');
						}
						else
						{
							if ($product_settings['booking_recurring_booking'] == "on")
							{
								print('<input type="hidden" name="wapbk_admin_'.$wkey.'_'.$_POST['order_item_id'].'" id="wapbk_admin_'.$wkey.'_'.$_POST['order_item_id'].'" value="'.$wval.'">');
							}
							else
							{
								print('<input type="hidden" name="wapbk_admin_'.$wkey.'_'.$_POST['order_item_id'].'" id="wapbk_admin_'.$wkey.'_'.$_POST['order_item_id'].'" value="">');
							}
						}
					}
					if (isset($product_settings['booking_time_settings']))
					{
						print('<input type="hidden" name="wapbk_admin_booking_times_'.$_POST['order_item_id'].'" id="wapbk_admin_booking_times_'.$_POST['order_item_id'].'" value=\''.$product_settings['booking_time_settings'].'\'>');
					}
					else
					{
						print('<input type="hidden" name="wapbk_admin_booking_times_'.$_POST['order_item_id'].'" id="wapbk_admin_booking_times_'.$_POST['order_item_id'].'" value="">');
					}
					
					if (isset($product_settings['booking_enable_multiple_day']))
					{
						print('<input type="hidden" id="wapbk_admin_multiple_day_booking_'.$_POST['order_item_id'].'" name="wapbk_admin_multiple_day_booking_'.$_POST['order_item_id'].'" value="'.$product_settings['booking_enable_multiple_day'].'"/>');
					}
					else
					{
						print('<input type="hidden" id="wapbk_admin_multiple_day_booking_'.$_POST['order_item_id'].'" name="wapbk_admin_multiple_day_booking_'.$_POST['order_item_id'].'" value=""/>');
					}
						
					if (isset($product_settings['booking_minimum_number_days']))
					{
						print('<input type="hidden" name="wapbk_admin_minimumOrderDays_'.$_POST['order_item_id'].'" id="wapbk_admin_minimumOrderDays_'.$_POST['order_item_id'].'" value="'.$product_settings['booking_minimum_number_days'].'">');
					}
					else
					{
						print('<input type="hidden" name="wapbk_admin_minimumOrderDays_'.$_POST['order_item_id'].'" id="wapbk_admin_minimumOrderDays_'.$_POST['order_item_id'].'" value="">');
					}
					if (isset($product_settings['booking_maximum_number_days']))
					{
						print('<input type="hidden" name="wapbk_admin_number_of_dates_'.$_POST['order_item_id'].'" id="wapbk_admin_number_of_dates_'.$_POST['order_item_id'].'" value="'.$product_settings['booking_maximum_number_days'].'">');
					}
					else
					{
						print('<input type="hidden" name="wapbk_admin_number_of_dates_'.$_POST['order_item_id'].'" id="wapbk_admin_number_of_dates_'.$_POST['order_item_id'].'" value="">');
					}
					if (isset($product_settings['booking_enable_time']))
					{
						print('<input type="hidden" name="wapbk_admin_bookingEnableTime_'.$_POST['order_item_id'].'" id="wapbk_admin_bookingEnableTime_'.$_POST['order_item_id'].'" value="'.$product_settings['booking_enable_time'].'">');
					}
					else
					{
						print('<input type="hidden" name="wapbk_admin_bookingEnableTime_'.$_POST['order_item_id'].'" id="wapbk_admin_bookingEnableTime_'.$_POST['order_item_id'].'" value="">');
					}
					if (isset($product_settings['booking_recurring_booking']))
					{
						print('<input type="hidden" name="wapbk_admin_recurringDays_'.$_POST['order_item_id'].'" id="wapbk_admin_recurringDays_'.$_POST['order_item_id'].'" value="'.$product_settings['booking_recurring_booking'].'">');
					}
					else
					{
						print('<input type="hidden" name="wapbk_admin_recurringDays_'.$_POST['order_item_id'].'" id="wapbk_admin_recurringDays_'.$_POST['order_item_id'].'" value="">');
					}
					if (isset($product_settings['booking_specific_booking']))
					{
						print('<input type="hidden" name="wapbk_admin_specificDates_'.$_POST['order_item_id'].'" id="wapbk_admin_specificDates_'.$_POST['order_item_id'].'" value="'.$product_settings['booking_specific_booking'].'">');
					}
					else
					{
						print('<input type="hidden" name="wapbk_admin_specificDates_'.$_POST['order_item_id'].'" id="wapbk_admin_specificDates_'.$_POST['order_item_id'].'" value="">');
					}
					//echo "here";die();
					global $woocommerce_booking;
					$lockout_query = "SELECT DISTINCT start_date FROM `".$wpdb->prefix."booking_history`
								WHERE post_id='".$prod_id."'
								AND total_booking > 0
								AND available_booking = 0";
					$results_lockout = $wpdb->get_results ( $lockout_query );
					//print_r($results_lockout);die();
					$lockout_query = "SELECT DISTINCT start_date FROM `".$wpdb->prefix."booking_history`
					WHERE post_id='".$prod_id."'
					AND available_booking > 0";
					//echo $lockout_query;die();
					$results_lock = $wpdb->get_results ( $lockout_query );
					$lockout_date = '';
						//print_r($results_lock);exit;
					foreach($results_lock as $key => $value)
					{
						$start_date = $value->start_date;
						$bookings_done = $this->date_lockout($start_date,$prod_id);
						if($bookings_done >= $product_settings['booking_date_lockout'])
						{
							$lockout = explode("-",$start_date);
							$lockout_date .= '"'.intval($lockout[2])."-".intval($lockout[1])."-".$lockout[0].'",';
						}
					}
					//echo $date_lockout;die();
					$lockout_str = substr($lockout_date,0,strlen($lockout_date)-1);
					foreach ($results_lockout as $k => $v)
					{
						foreach($results_lock as $key => $value)
						{
							if ($v->start_date == $value->start_date)
							{
								$date_lockout = "SELECT COUNT(start_date) FROM `".$wpdb->prefix."booking_history`
												WHERE post_id='".$prod_id."'
												AND start_date='".$v->start_date."'
												AND available_booking = 0";
								$results_date_lock = $wpdb->get_results($date_lockout);
							
								if ($product_settings['booking_date_lockout'] > $results_date_lock[0]->{'COUNT(start_date)'}) unset($results_lockout[$k]);	
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
					$lockout_dates = $lockout_dates_str.",".$lockout_str;
					print('<input type="hidden" name="wapbk_admin_lockout_days_'.$_POST['order_item_id'].'" id="wapbk_admin_lockout_days_'.$_POST['order_item_id'].'" value=\''.$lockout_dates.'\'>');
					$todays_date = date('Y-m-d');
					//print_r($todays_date);die();
					$query_date ="SELECT DATE_FORMAT(start_date,'%d-%c-%Y') as start_date,DATE_FORMAT(end_date,'%d-%c-%Y') as end_date FROM ".$wpdb->prefix."booking_history WHERE start_date >='".$todays_date."' AND post_id = '".$prod_id."'";
					//echo $query_date;
					$results_date = $wpdb->get_results($query_date);
					//print_r($results_date);
					$dates_new = array();
					$booked_dates = array();
					foreach($results_date as $k => $v)
					{
						$start_date = $v->start_date;
						$end_date = $v->end_date;
						$dates = $woocommerce_booking->betweendays($start_date, $end_date);
						//print_r($dates);
						$dates_new = array_merge($dates,$dates_new);
					}
				//	Enable the start date for the booking period for checkout
					foreach ($results_date as $k => $v)
					{
						$start_date = $v->start_date;
						//echo ($start_date);
						$end_date = $v->end_date;
						//echo ($end_date);
						$new_start = strtotime("+1 day", strtotime($start_date));
						$new_start = date("d-m-Y",$new_start);
						//echo $new_start;
						$dates = $woocommerce_booking->betweendays($new_start, $end_date);
						$booked_dates = array_merge($dates,$booked_dates);
					}
					//print_r($dates);
					//print_r($booked_dates);
					$dates_new_arr = array_count_values($dates_new);
					$booked_dates_arr = array_count_values($booked_dates);
					//print_r($booking_settings);
					$lockout = "";
					if (isset($product_settings['booking_date_lockout']))
					{
						$lockout = $product_settings['booking_date_lockout'];
					}
					//echo "ehre".$lockout;
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
							//$new_arr_str .=  '"'.$k.'",';
						}
					}
					//$new_arr_str = date('d-m-Y',$new_arr_str);
					$new_arr_str = substr($new_arr_str,0,strlen($new_arr_str)-1);
					//print_r($new_arr_str);
					print("<input type='hidden' id='wapbk_admin_hidden_booked_dates_".$_POST['order_item_id']."' name='wapbk_admin_hidden_booked_dates_".$_POST['order_item_id']."' value='".$new_arr_str."'/>");
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
					print("<input type='hidden' id='wapbk_admin_hidden_booked_dates_checkout_".$_POST['order_item_id']."' name='wapbk_admin_hidden_booked_dates_checkout_".$_POST['order_item_id']."' value='".$booked_dates_str."'/>");
					print('<input type="hidden" id="wapbk_admin_hidden_date_'.$_POST['order_item_id'].'" name="wapbk_admin_hidden_date_'.$_POST['order_item_id'].'" />');
					print('<input type="hidden" id="wapbk_admin_hidden_date_checkout_'.$_POST['order_item_id'].'" name="wapbk_admin_hidden_date_checkout_'.$_POST['order_item_id'].'" />');
					
					endif;
					
					$method_to_show = 'check_for_time_slot_admin';
					
					$options_checkin = $options_checkout = array();
					//$options_checkin = $options_checkout = array();
					$js_code = $blocked_dates_hidden_var = '';
					$block_dates = array();
					$block_dates = (array) apply_filters( 'bkap_block_dates', $prod_id , $blocked_dates );
					//print_r($block_dates);exit;
					if (isset($block_dates) && count($block_dates) > 0)
					{
						$i = 1;
						$bvalue = array();
						$add_day = '';
						$same_day = '';
						foreach ($block_dates as $bkey => $bvalue)
						{
							if (isset($bvalue['dates']) && count($bvalue['dates']) > 0) $blocked_dates_str = '"'.implode('","', $bvalue['dates']).'"';
							else $blocked_dates_str = "";
							/*	if ( ( isset($bvalue['field_name']) && $bvalue['field_name'] == '' )  || !isset($bvalue['field_name']) )	$bvalue['field_name'] = $i;
							 $fld_name = 'woobkap_'.str_replace(' ','_', $bvalue['field_name']);*/
							$field_name = $i;
							if ( ( isset($bvalue['field_name']) && $bvalue['field_name'] != '' ) ) $field_name = $bvalue['field_name'];
							$fld_name = 'woobkap_'.str_replace(' ','_', $field_name);
							$fld_name_admin = $fld_name.'_'.$_POST['order_item_id'];
							//echo $fld_name;
							print("<input type='hidden' id='".$fld_name_admin."' name='".$fld_name_admin."' value='".$blocked_dates_str."'/>");
							$i++;
							if(isset($bvalue['add_days_to_charge_booking']))
								$add_day = $bvalue['add_days_to_charge_booking'];
							if($add_day == '')
							{
								$add_day = 0;
							}
							print("<input type='hidden' id='add_days_".$_POST['order_item_id']."' name='add_days_'".$_POST['order_item_id']."' value='".$blocked_dates_str."'/>");
							if(isset($bvalue['same_day_booking']))
								$same_day = $bvalue['same_day_booking'];
							print("<input type='hidden' id='wapbk_admin_same_day_".$_POST['order_item_id']."' name='wapbk_admin_same_day_".$_POST['order_item_id']."' value='".$same_day."'/>");
						}
							
						//	if (!isset($bvalue['date_label'])) $bvalue['date_label'] = 'Unavailable for Booking';
						if (isset($bvalue['date_label']) && $bvalue['date_label'] != '')
							$date_label =  $bvalue['date_label'];
						else
							$date_label = 'Unavailable for Booking';
						//if (isset($bvalue['add_days_to_charge_booking']) && $bvalue['add_days_to_charge_booking'] == '') $add_day = 0;
						$js_code = '
						var '.$fld_name_admin.' = eval("["+jQuery("#'.$fld_name_admin.'").val()+"]");
						for (i = 0; i < '.$fld_name_admin.'.length; i++)
						{
							if( jQuery.inArray(d + "-" + (m+1) + "-" + y,'.$fld_name_admin.') != -1 )
							{
								//alert();
								return [false, "", "'.$date_label.'"];
							}
						}
					';
						$js_block_date  = '
						var '.$fld_name_admin.' = eval("["+jQuery("#'.$fld_name_admin.'").val()+"]");
						var date = new_end = new Date(CheckinDate);
						var m = date.getMonth(), d = date.getDate(), y = date.getFullYear();
						//alert(count);
						for (var i = 1; i<= count;i++)
						{
						if( jQuery.inArray(d + "-" + (m+1) + "-" + y,'.$fld_name_admin.') != -1 )
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
					
					if (isset($product_settings['booking_enable_multiple_day']) && $product_settings['booking_enable_multiple_day'] == 'on')
					{
						//woocommerce_add_order_item_meta( $item_number,get_option("checkout.date-label"),'');
						
						$meta_id_next = $meta_id + 1;
						$meta_ids[$cart_item_key][$i] = $meta_id_next;
						echo '<tr data-meta_id="'.$meta_id_next.'"><td><input type="text" name="meta_key[' . $meta_id_next. ']" value="'.strip_tags(get_option("checkout.item-meta-date")).'" /></td><td><input type="text" name="meta_value[' . $meta_id_next. ']" value="" /></td><td width="1%"><button class="remove_order_item_meta button">&times;</button></td></tr>';
						$options_checkout[] = "minDate: 1";
						$options_checkin[] = 'onClose: function( selectedDate, inst ) {
						
						var monthValue = inst.selectedMonth+1;
						var dayValue = inst.selectedDay;
						var yearValue = inst.selectedYear;
						
						var current_sel_dt = dayValue + "-" + monthValue + "-" + yearValue;
						
						jQuery("#wapbk_admin_hidden_date_'.$_POST['order_item_id'].'").val(current_sel_dt);
						jQuery( "tr[data-meta_id=\"'.$meta_id_next.'\"]" ).show();
						if(jQuery("#admin_block_option_enabled_'.$_POST['order_item_id'].'").val() == "on")
						{
							///alert();
							//jQuery("#show_time_slot").show();
							var nod= parseInt(jQuery("#admin_block_option_number_of_day_'.$_POST['order_item_id'].'").val(),10);										if(jQuery("#wapbk_admin_hidden_date_'.$_POST['order_item_id'].'").val() != "")
							{
								var num_of_day= jQuery("#admin_block_option_number_of_day_'.$_POST['order_item_id'].'").val();
								var split = jQuery("#wapbk_admin_hidden_date_'.$_POST['order_item_id'].'").val().split("-");
								split[1] = split[1] - 1;		
								var minDate = new Date(split[2],split[1],split[0]);
									
								minDate.setDate(minDate.getDate() + nod ); 
								//var kc=new Date(Date.parse(selectedDate)); 
								//kc.setDate(kc.getDate() + nod );								
								//var newDate = kc.toDateString(); 
															  
								//newDate = new Date( Date.parse( newDate ) );
			   					console.log(minDate);
														 
			   					jQuery("input[name=\"meta_value['.$meta_id_next.']\"]").datepicker("setDate",minDate);

 			   												
								//var objDate= jQuery( "#admin_booking_calender_checkout").datepicker("getDate");
								//var strDate = objDate.getDate() + "-" + (objDate.getMonth() + 1) + "-" + objDate.getFullYear();
															
								//jQuery("#wapbk_hidden_date_checkout").val(strDate);
															
								// calculate_price();
															 
								// disabled calendar	
								 //jQuery( "#booking_calender_checkout" ).prop("disabled", true);
								 //jQuery("#show_time_slot").hide();
								//jQuery(".amount").hide();
							}
						}
						else
						{
							if (jQuery("#wapbk_admin_hidden_date_'.$_POST['order_item_id'].'").val() != "")
							{
								if(jQuery("#wapbk_admin_same_day_'.$_POST['order_item_id'].'").val() == "on")
								{
									if (jQuery("#wapbk_admin_hidden_date_'.$_POST['order_item_id'].'").val() != "")
									{
										var split = jQuery("#wapbk_admin_hidden_date_'.$_POST['order_item_id'].'").val().split("-");
										split[1] = split[1] - 1;
										var minDate = new Date(split[2],split[1],split[0]);
							
										minDate.setDate(minDate.getDate());
										//alert(minDate);
										jQuery( "input[name=\"meta_value['.$meta_id_next.']\"]" ).datepicker( "option", "minDate", minDate);
									}
								}
								else
								{
									var split = jQuery("#wapbk_admin_hidden_date_'.$_POST['order_item_id'].'").val().split("-");
									split[1] = split[1] - 1;
									var minDate = new Date(split[2],split[1],split[0]);
							
									minDate.setDate(minDate.getDate() + 1);
										//alert(minDate);
									jQuery( "input[name=\"meta_value['.$meta_id_next.']\"]" ).datepicker( "option", "minDate", minDate);
								}
							}
						}
						
						}';
						$options_checkout[] = "onSelect: get_per_night_price";
						$options_checkin[] = "onSelect: set_checkin_date";
						$options_checkout[] = "beforeShowDay: check_booked_dates";
						$options_checkin[] = "beforeShowDay: check_booked_dates";
					}
					else if(isset($product_settings['booking_enable_time']) && $product_settings['booking_enable_time'] == 'on')
					{
						$meta_id_next = $meta_id + 1;
						$meta_ids[$cart_item_key][$i] = $meta_id_next;
						//woocommerce_add_order_item_meta( $item_number,get_option('book.time-label'),'');
						echo '<tr data-meta_id="'.$meta_id_next.'"><td><input type="text" name="meta_key[' . $meta_id_next. ']" value="'.get_option('book.time-label').'" /></td><td><input type="text" name="meta_value[' . $meta_id_next. ']" value="" /></td><td width="1%"><button class="remove_order_item_meta button">&times;</button></td></tr>';
						//$meta = $order->has_meta($item_number);
						$options_checkin[] = "beforeShowDay: show_book";
						$options_checkin[] = "onSelect: show_times";
						$options_checkin[] = 'onClose: function( selectedDate, inst ) {
						
						var monthValue = inst.selectedMonth+1;
						var dayValue = inst.selectedDay;
						var yearValue = inst.selectedYear;
						
						var current_sel_dt = dayValue + "-" + monthValue + "-" + yearValue;
						
						jQuery("#wapbk_admin_hidden_date_'.$_POST['order_item_id'].'").val(current_sel_dt);
						jQuery( "tr[data-meta_id=\"'.$meta_id_next.'\"]" ).show();
						}';
					}
					else
					{
						
						$options_checkin[] = 'onClose: function( selectedDate, inst ) {
						
						var monthValue = inst.selectedMonth+1;
						var dayValue = inst.selectedDay;
						var yearValue = inst.selectedYear;
						
						var current_sel_dt = dayValue + "-" + monthValue + "-" + yearValue;
						
						jQuery("#wapbk_admin_hidden_date_'.$_POST['order_item_id'].'").val(current_sel_dt);
						}';
						$options_checkin[] = "beforeShowDay: show_book";
						$options_checkin[] = "onSelect: show_times";
					}	
					$options_checkin_str = '';
					if (count($options_checkin) > 0)
					{
						$options_checkin_str = implode(',', $options_checkin);
					}
					
					$options_checkout_str = '';
					if (count($options_checkout) > 0)
					{
						$options_checkout_str = implode(',', $options_checkout);
					}
					$meta_ids_str = '';
					if (count($meta_ids) > 0)
					{
						$meta_ids_str = implode(',', $meta_ids[$cart_item_key]);
					}
					echo '<input type="hidden" name="meta_ids'.$cart_item_key.'" id="meta_ids'.$cart_item_key.'" value="'.$meta_ids_str.'" />';

					print('
					<script type="text/javascript">
					jQuery(document).ready(function()
						{	
							jQuery.extend(jQuery.datepicker, { afterShow: function(event)
							{
								jQuery.datepicker._getInst(event.target).dpDiv.css("z-index", 9999);
							}});
						
						var today = new Date();
						jQuery(function() {
						jQuery( "tr[data-meta_id=\"'.$meta_id_next.'\"]" ).hide();
					    jQuery( "input[name=\"meta_value['.$meta_id.']\"]" ).datepicker({
							beforeShow: avd,
							minDate:today,
							dateFormat: "'.$saved_settings->booking_date_format.'",						
							numberOfMonths: parseInt('.$saved_settings->booking_months.'),
							'.$options_checkin_str.' ,
					}).focus(function (event)
					{
						jQuery.datepicker.afterShow(event);
					});
					});
					jQuery( "input[name=\"meta_value['.$meta_id.']\"]" ).wrap("<div class=\"hasDatepicker\"></div>");
					jQuery( "input[name=\"meta_value['.$meta_id.']\"]" ).attr("id","admin_booking_calender_'.$meta_id.'");
					jQuery( "input[name=\"meta_value['.$meta_id_next.']\"]" ).attr("id","admin_booking_calender_checkout_'.$meta_id_next.'");
					});
				
					');

					//if (isset($product_settings['booking_enable_timeslot']) == 'on')
					
					if (isset($product_settings['booking_enable_multiple_day']) && $product_settings['booking_enable_multiple_day'] == 'on')
					{
						print ('jQuery("input[name=\"meta_value['.$meta_id_next.']\"]").datepicker({
									dateFormat: "'.$saved_settings->booking_date_format.'",
									numberOfMonths: parseInt('.$saved_settings->booking_months.'),
									'.$options_checkout_str.' ,
									onClose: function( selectedDate ) {
									jQuery( "input[name=\"meta_value['.$meta_id.']\"]" ).datepicker( "option", "maxDate", selectedDate );
						},
						}).focus(function (event)
						{
									jQuery.datepicker.afterShow(event);
						});
						jQuery( "input[name=\"meta_value['.$meta_id_next.']\"]" ).wrap("<div class=\"hasDatepicker\"></div>");
						');
					}
					
					print('
					function check_booked_dates(date)
					{
						var m = date.getMonth(), d = date.getDate(), y = date.getFullYear();
						var holidayDates = eval("["+jQuery("#wapbk_admin_booking_holidays_'.$_POST['order_item_id'].'").val()+"]");
						var globalHolidays = eval("["+jQuery("#wapbk_admin_booking_global_holidays_'.$_POST['order_item_id'].'").val()+"]");
						//alert(jQuery("#wapbk_admin_hidden_booked_dates_'.$_POST['order_item_id'].'").val());
						var bookedDates=eval("[" + jQuery("#wapbk_admin_hidden_booked_dates_'.$_POST['order_item_id'].'").val() + "]");
						var bookedDatesCheckout = eval("["+jQuery("#wapbk_admin_hidden_booked_dates_checkout_'.$_POST['order_item_id'].'").val()+"]");

						var block_option_start_day= jQuery("#admin_block_option_start_day_'.$_POST['order_item_id'].'").val();
					 	var block_option_price= jQuery("#admin_block_option_price_'.$_POST['order_item_id'].'").val();
						//alert(block_option_start_day);
						for (iii = 0; iii < globalHolidays.length; iii++)
						{
							if( jQuery.inArray(d + "-" + (m+1) + "-" + y,globalHolidays) != -1 )
							{
								return [false, "", "Holiday"];
							}
						}
						for (ii = 0; ii < holidayDates.length; ii++)
						{
							if( jQuery.inArray(d + "-" + (m+1) + "-" + y,holidayDates) != -1 )
							{
								return [false, "", "Holiday"];
							}
						}
						var id_booking = jQuery(this).attr("id");
						//alert(id_booking);
						if (id_booking == "admin_booking_calender_'.$meta_id.'" || id_booking == "inline_calendar")
						{
							for (iii = 0; iii < bookedDates.length; iii++)
							{
								//alert(bookedDates);
								if( jQuery.inArray(d + "-" + (m+1) + "-" + y,bookedDates) != -1 )
								{
									return [false, "", "Unavailable for Booking"];
								}
							}
						}	
						if (id_booking == "admin_booking_calender_checkout_'.$meta_id_next.'" || id_booking == "inline_calendar_checkout") 
						{
							//alert();
							for (iii = 0; iii < bookedDatesCheckout.length; iii++)
							{
								//alert(bookedDates);
								if( jQuery.inArray(d + "-" + (m+1) + "-" + y,bookedDatesCheckout) != -1 )
								{
									return [false, "", "Unavailable for Booking"];
								}
							}
						}
						var block_option_enabled= jQuery("#admin_block_option_enabled_'.$_POST['order_item_id'].'").val();
	
						if (block_option_enabled =="on")
						{
							if ( id_booking == "admin_booking_calender_'.$meta_id.'" || id_booking == "inline_calendar" )
							{
								//alert(date.getDay());
								if (block_option_start_day == date.getDay())
					            {
					              return [true];
					            }
					            else
					            {
									return [false];
					            }
				       		}
				       		var bcc_date=jQuery( "input[name=\"meta_value['.$meta_id_next.']\"]").datepicker("getDate");
							//alert(bcc_date);
							if(bcc_date != null)
							{
								var dd = bcc_date.getDate();
								var mm = bcc_date.getMonth()+1; //January is 0!
								var yyyy = bcc_date.getFullYear();
								var checkout = dd + "-" + mm + "-"+ yyyy;
								jQuery("#wapbk_admin_hidden_date_checkout_'.$_POST['order_item_id'].'").val(checkout);

						   		if (id_booking == "admin_booking_calender_checkout_'.$meta_id_next.'" || id_booking == "inline_calendar_checkout"){

 
				       			if (Date.parse(bcc_date) === Date.parse(date)){
				       					return [true];
				       			}else{
				       					return [false];
				       			}
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
						var deliveryDates = eval("["+jQuery("#wapbk_admin_booking_dates_'.$_POST['order_item_id'].'").val()+"]");
						
						var holidayDates = eval("["+jQuery("#wapbk_admin_booking_holidays_'.$_POST['order_item_id'].'").val()+"]");
							
						var globalHolidays = eval("["+jQuery("#wapbk_admin_booking_global_holidays_'.$_POST['order_item_id'].'").val()+"]");
						
						//Lockout Dates
						var lockoutdates = eval("["+jQuery("#wapbk_admin_lockout_days_'.$_POST['order_item_id'].'").val()+"]");
						
						var bookedDates = eval("["+jQuery("#wapbk_admin_hidden_booked_dates_'.$_POST['order_item_id'].'").val()+"]");
						var dt = new Date();
						var today = dt.getMonth() + "-" + dt.getDate() + "-" + dt.getFullYear();
						for (iii = 0; iii < lockoutdates.length; iii++)
						{
							if( jQuery.inArray(d + "-" + (m+1) + "-" + y,lockoutdates) != -1 )
							{
								return [false, "", "Booked"];
	
							}
						}	
						
						for (iii = 0; iii < globalHolidays.length; iii++)
						{
							if( jQuery.inArray(d + "-" + (m+1) + "-" + y,globalHolidays) != -1 )
							{
								return [false, "", "Holiday"];
							}
						}
						
						for (ii = 0; ii < holidayDates.length; ii++)
						{
							if( jQuery.inArray(d + "-" + (m+1) + "-" + y,holidayDates) != -1 )
							{
								return [false, "", "Holiday"];
							}
						}
					
						for (i = 0; i < bookedDates.length; i++)
						{
							//alert(bookedDates);
							if( jQuery.inArray(d + "-" + (m+1) + "-" + y,bookedDates) != -1 )
							{
								return [false, "", "Unavailable for Booking"];
							}
						}
						 	
						for (i = 0; i < deliveryDates.length; i++)
						{
							if( jQuery.inArray(d + "-" + (m+1) + "-" + y,deliveryDates) != -1 )
							{
								return [true];
							}
						}

						var day = "booking_weekday_" + date.getDay();
						var name = day+"_"+'.$_POST['order_item_id'].';
						if (jQuery("#wapbk_admin_"+name).val() == "on")
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
						var sold_individually = jQuery("#wapbk_sold_individually_'.$_POST['order_item_id'].'").val();
						jQuery("#wapbk_admin_hidden_date_'.$_POST['order_item_id'].'").val(current_dt);
						if (jQuery("#wapbk_admin_bookingEnableTime_'.$_POST['order_item_id'].'").val() == "on" && jQuery("#wapbk_admin_booking_times_'.$_POST['order_item_id'].'").val() != "")
						{
							//jQuery.datepicker.formatDate("d-m-yy", new Date(yearValue, dayValue, monthValue) );
							var time_slots_arr = jQuery("#wapbk_admin_booking_times_'.$_POST['order_item_id'].'").val();
							var data = {
								current_date: current_dt,
								post_id: "'.$prod_id.'",
								action: "'.$method_to_show.'"
								
								};
										
								jQuery.post("'.get_admin_url().'/admin-ajax.php", data, function(response)
								{
								jQuery( "tr[data-meta_id=\"'.$meta_id_next.'\"]" ).show();
							
								var select = jQuery("<select style=\"width:100%;\">");
								select.append(jQuery("<option>").val("Choose a Time").html("Choose an option"));
								//alert(response);
								var time_slots = response.split("|");
								for (var i = 0; i <= time_slots.length; ++i) 
								{
									if(time_slots[i] != "" && time_slots[i] != null)
										select.append(jQuery("<option>").val(time_slots[i]).html(time_slots[i]));
								}
								//response;
								//alert('.$meta_id_next.');
								select.val(1).attr({name: "meta_value['.$meta_id_next.']"}).change(function(){
								    
								});
								jQuery("input[name=\"meta_value['.$meta_id_next.']\"]").replaceWith(select);
								//alert("Got this from the server: " + response);
								jQuery( "#ajax_img" ).hide();
								jQuery("#show_time_slot").html(response);
								jQuery("#time_slot").change(function()
								{
									if ( jQuery("#time_slot").val() != "" )
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
									else if ( jQuery("#time_slot").val() == "" )
									{
										jQuery( ".single_add_to_cart_button" ).hide();
										jQuery( ".quantity" ).hide();
									}
								})
								
							});
						}
						else
						{
							if ( jQuery("#wapbk_admin_hidden_date_'.$_POST['order_item_id'].'").val() != "" )
							{
								var data = {
								current_date: current_dt,
								post_id: "'.$prod_id.'", 
								action: "insert_admin_date"
								};
								jQuery.post("'.get_admin_url().'/admin-ajax.php", data, function(response)
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
							
								});
							}
							else if ( jQuery("#wapbk_admin_hidden_date_'.$_POST['order_item_id'].'").val() == "" )
							{
								jQuery( ".single_add_to_cart_button" ).hide();
								jQuery( ".quantity" ).hide();
							}
						}
					}
							
					function set_checkin_date(date,inst)
					{
						var monthValue = inst.selectedMonth+1;
						var dayValue = inst.selectedDay;
						var yearValue = inst.selectedYear;

						var current_dt = dayValue + "-" + monthValue + "-" + yearValue;
						jQuery("#wapbk_admin_hidden_date_'.$_POST['order_item_id'].'").val(current_dt);
						// Check if any date in the selected date range is unavailable
						if (jQuery("#wapbk_admin_hidden_date_'.$_POST['order_item_id'].'").val() != "" )
						{
							var CalculatePrice = "Y";
							var split = jQuery("#wapbk_admin_hidden_date_'.$_POST['order_item_id'].'").val().split("-");
							split[1] = split[1] - 1;		
							var CheckinDate = new Date(split[2],split[1],split[0]);
								
							var split = jQuery("#wapbk_admin_hidden_date_checkout_'.$_POST['order_item_id'].'").val().split("-");
							split[1] = split[1] - 1;
							var CheckoutDate = new Date(split[2],split[1],split[0]);
								
							var date = new_end = new Date(CheckinDate);
							var m = date.getMonth(), d = date.getDate(), y = date.getFullYear();
								
							var bookedDates = eval("["+jQuery("#wapbk_admin_hidden_booked_dates_'.$_POST['order_item_id'].'").val()+"]");
							var holidayDates = eval("["+jQuery("#wapbk_admin_booking_holidays_'.$_POST['order_item_id'].'").val()+"]");
							var globalHolidays = eval("["+jQuery("#wapbk_admin_booking_global_holidays_'.$_POST['order_item_id'].'").val()+"]");
						
							var count = gd(CheckinDate, CheckoutDate, "days");
							//Locked Dates
							for (var i = 1; i<= count;i++)
								{
									if( jQuery.inArray(d + "-" + (m+1) + "-" + y,bookedDates) != -1 )
									{
										jQuery("#wapbk_admin_hidden_date_'.$_POST['order_item_id'].'").val("");
										jQuery("input[name=\"meta_value['.$meta_id.']\"]").val("");
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
										jQuery("#wapbk_admin_hidden_date_'.$_POST['order_item_id'].'").val("");
										jQuery("input[name=\"meta_value['.$meta_id.']\"]").val("");
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
										jQuery("#wapbk_admin_hidden_date_'.$_POST['order_item_id'].'").val("");
										jQuery("input[name=\"meta_value['.$meta_id.']\"]").val("");
										CalculatePrice = "N";
										alert("Some of the dates in the selected range are unavailable. Please try another date range.");
										break;
									}
								new_end = new Date(ad(new_end,1));
								var m = new_end.getMonth(), d = new_end.getDate(), y = new_end.getFullYear();													
								}
							'.$js_block_date.'
							//if (CalculatePrice == "Y") calculate_price();
						}
					}
							
					function get_per_night_price(date,inst)
					{
						var monthValue = inst.selectedMonth+1;
						var dayValue = inst.selectedDay;
						var yearValue = inst.selectedYear;

						var current_dt = dayValue + "-" + monthValue + "-" + yearValue;
						jQuery("#wapbk_admin_hidden_date_checkout_'.$_POST['order_item_id'].'").val(current_dt);
						
					}
					
					</script>
					');
					//echo "CHECK<pre>";print_r($a);echo "</pre>";
					//return $a;*/
				endif;
					
					//print_r($meta_ids);
				}
				}
				else
				{
					echo '<tr data-meta_id="'.$meta_id.'"><td><input type="text" name="meta_key[' . $meta_id . ']" value="" /><textarea name="meta_key[' . $meta_id . ']" value="" /></td><td width="1%"><button class="remove_order_item_meta button">&times;</button></td></tr>';
				}
				die();
				
			}
			
			function check_for_time_slot_admin() {
			
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
			
				$check_query = "SELECT * FROM `".$wpdb->prefix."booking_history`
				WHERE start_date='".$date_to_check."'
				AND post_id='".$post_id."'
				AND available_booking > 0 ORDER BY STR_TO_DATE(from_time,'%H:%i')
				";
				$results_check = $wpdb->get_results ( $check_query );
			
				if ( count($results_check) > 0 )
				{
					$drop_down = "";
					//$drop_down .= "<option value=''>Choose a Time</option>";
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
								$drop_down .= $from_time_value." - ".$to_time_value."|";
								//$drop_down .= "value_$key='".$from_time_value." - ".$to_time_value."'";
							}
							else
							{
								$drop_down .= $from_time_value."|";
								//$drop_down .= "value_$key='".$from_time_value."'";
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
								$drop_down .= $from_time_value." - ".$to_time_value."|";
								//$drop_down .= "value_$key='".$from_time_value." - ".$to_time_value."'";
							}
							else
							{
								if ($value->from_time != '')
								{
									$drop_down .= $from_time_value."|";
									//$drop_down .= "value_$key='".$from_time_value."'";
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
								$drop_down .= $from_time_value." - ".$to_time_value."|";
								//$drop_down .= "value_$key='".$from_time_value." - ".$to_time_value."'";
							}
							else
							{
								if ($value->from_time != '')
								{
									$drop_down .= $from_time_value."|";
									//$drop_down .= "value_$key='".$from_time_value."'";
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
						$drop_down = "";
						//$drop_down = "jQuery(\"<select>\");";
						//$drop_down = "<label>".get_option('book.time-label'). ": </label><select name='time_slot' id='time_slot' class='time_slot'>";
						//$drop_down .= "<option value=''>Choose a Time</option>";
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
								$drop_down .= $from_time_value." - ".$to_time_value."|";
								//$drop_down .= "value_$key='".$from_time_value." - ".$to_time_value."'";
							}
							else
							{
								$drop_down .= $from_time_value."|";
								//$drop_down .= "value_$key='".$from_time_value."'";
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
			
						//$drop_down = "jQuery(\"<select>\");";
						$drop_down = "";
						//$drop_down = "<label>".get_option('book.time-label'). ": </label><select name='time_slot' id='time_slot' class='time_slot'>";
						//$drop_down .= "<option value=''>Choose a Time</option>";
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
								$drop_down .= $from_time_value." - ".$to_time_value."|";
								//$drop_down .= "value_$key='".$from_time_value." - ".$to_time_value."'";
							}
							else
							{
								$drop_down .= $from_time_value."|";
								//$drop_down .= "value_$key='".$from_time_value."'";
								$to_time = $to_time_value = "";
							}
						}
					}
				}
			
				echo $drop_down;
				die();
			}
			
			function woocommerce_ajax_remove_order_item_meta_admin(){
				
				$args = array(
						'meta_query'  => array(
								array(
										'key' => $_POST['meta_id']
								)
						)
				);
				$my_query = new WP_Query( $args );
				
				global $wpdb;
				$results = $wpdb->get_results( "select order_item_id from `".$wpdb->prefix."woocommerce_order_itemmeta` where meta_id = ".$_POST['meta_id']." ", ARRAY_A );
				//$order_id = woocommerce_get_order_id_by_order_key($_POST['meta_id']);
				
				$order_id = $wpdb->get_results( "select order_id from `".$wpdb->prefix."woocommerce_order_items` where order_item_id = ".$results[0]['order_item_id']." ", ARRAY_A );
				
				$this->woocommerce_cancel_order($order_id[0]['order_id'],$results[0]['order_item_id']);
				//echo "<pre>";print_r($results);echo "</pre>";exit;
			}
			function woocommerce_ajax_remove_order_item_admin() {
				global $woocommerce, $wpdb;
			
				check_ajax_referer( 'order-item', 'security' );
			
				$order_item_ids = $_POST['order_item_ids'];
			
				if ( sizeof( $order_item_ids ) > 0 ) {
					foreach( $order_item_ids as $id ) {
						//woocommerce_delete_order_item( absint( $id ) );
						$order_id = $wpdb->get_results( "select order_id from `".$wpdb->prefix."woocommerce_order_items` where order_item_id = ".$id." ", ARRAY_A );
						//print_r($order_id);
						$this->woocommerce_cancel_order($order_id[0]['order_id'],$id);
					}
				}
				//die();
			}
			function woocommerce_cancel_order($order_id,$order_item_id)
			{
				global $wpdb,$post;
				$array = array();
				$order_obj = new WC_order($order_id);
				$order_items = $order_obj->get_items();
				$select_query = "SELECT booking_id FROM `".$wpdb->prefix."booking_order_history`
				WHERE order_id='".$order_id."'";
				$results = $wpdb->get_results ( $select_query );
				$post_id = woocommerce_get_order_item_meta($order_item_id, "_product_id", true);
				$booking_settings = get_post_meta($post_id, 'woocommerce_booking_settings', true);
				$checkin_date= woocommerce_get_order_item_meta($order_item_id, get_option('book.item-meta-date'), true);//print_r($results);
				$start_date = date("Y-m-d",strtotime($checkin_date));
				if (isset($booking_settings['booking_enable_multiple_day']) && $booking_settings['booking_enable_multiple_day'] == 'on')
				{
					$checkout_date = woocommerce_get_order_item_meta($order_item_id, strip_tags(get_option('checkout.item-meta-date')), true);
					$end_date = date("Y-m-d",strtotime($checkout_date));
					//echo "<pre>";print_r($results);echo "</pre>";exit;
					foreach($results as $k => $v)
					{
						$b[] = $v->booking_id;
						$select_query_post = "SELECT post_id,id FROM `".$wpdb->prefix."booking_history`
						WHERE id='".$v->booking_id."' AND start_date='".$start_date."' AND end_date ='".$end_date."' AND post_id=".$post_id;
						$results_post[] = $wpdb->get_results($select_query_post);
					}
				}
				else if(isset($booking_settings['booking_enable_time']) && $booking_settings['booking_enable_time'] == 'on')
				{
					$timeslot = woocommerce_get_order_item_meta($order_item_id, get_option('book.item-meta-time'), true);
					$time_slot = explode("-",$timeslot);
					$from_time = date("G:i",strtotime($time_slot[0]));
					$to_time = date("G:i",strtotime($time_slot[1]));
					//echo "<pre>";print_r($results);echo "</pre>";exit;
					foreach($results as $k => $v)
					{
						$b[] = $v->booking_id;
						$select_query_post = "SELECT post_id,id FROM `".$wpdb->prefix."booking_history`
						WHERE id='".$v->booking_id."' AND start_date='".$start_date."' AND from_time ='".$from_time."' AND to_time ='".$to_time."' AND post_id=".$post_id;
						$results_post[] = $wpdb->get_results($select_query_post);
					}
				}
				else
				{
					foreach($results as $k => $v)
					{
						$b[] = $v->booking_id;
						$select_query_post = "SELECT post_id,id FROM `".$wpdb->prefix."booking_history`
						WHERE id='".$v->booking_id."' AND start_date='".$start_date."' AND post_id=".$post_id;
						$results_post[] = $wpdb->get_results($select_query_post);
					}
				}
				foreach($results_post as $k => $v)
				{
					$a[$v[0]->post_id][] = $v[0]->id;
			
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
					//$product_id = $item_value['product_id'];
					if(in_array($product_id,(array)$array))
					{
					}
					else
					{
						$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
						$qty = $item_value['qty'];
						$result = $a[$product_id];
						$e = 0;
						foreach($result as $k =>$v)
						{
							$booking_id = $result[$e];
							if($booking_settings['booking_enable_multiple_day'] == 'on')
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
							else if($booking_settings['booking_enable_time'] == 'on')
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
											}
											else
											{
												$query = "UPDATE `".$wpdb->prefix."booking_history`
												SET available_booking = available_booking + ".$qty."
												WHERE
												id = '".$booking_id."' AND
												start_date = '".$start_date."' AND
												from_time = '".$from_time."'";
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
					$i++;
					$array[] = $product_id;
				}
			}
			
			function new_function($this_a){
				
				echo $this_a;
			}
			function update_order_details($order_id, $post)
			{	
				global $wpdb, $woocommerce;
				$order_query = "SELECT * FROM `".$wpdb->prefix."booking_order_history`
				WHERE order_id = '".$order_id."' ";
				$existing_order_result = $wpdb->get_results( $order_query );
				$new_order = false;
				$edit_order = true;
				if (count($existing_order_result) == 0) { 
					$new_order = true;
					$edit_order = false; 
				}
				if ( isset( $_POST['save'] ) && $_POST['save'] )
				{
					$order = new WC_Order( $order_id );	
					$items = $order->get_items();	
					//print_r($_POST);exit;
					foreach ( $items as $cart_item_key => $values )
					{	
						//echo "here"; 
						$order_item_id = array();
						if(isset($_POST['order_item_id'])) $order_item_id = $_POST['order_item_id'];
						//print_r($order_item_id);
						foreach ($order_item_id as $oid_key => $oid_value)
						{
							if ($cart_item_key == $oid_value)
							{
								$meta = $order->has_meta($cart_item_key);
								$existing_quantity = woocommerce_get_order_item_meta($cart_item_key, '_qty', true);
								$order_item_qty = $_POST['order_item_qty'];
								//print_r($order_item_qty);
								$quantity = $order_item_qty[$oid_value];
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
								if(isset($_POST['meta_key']))
								{
									$meta_keys = $_POST['meta_key'];
								}
								else
								{
									$meta_keys = array();
								}
								if(isset($_POST['meta_value']))
								{
									$meta_values = $_POST['meta_value'];
								}
								else 
								{
									$meta_values = array();
								}
								$item_key = $_POST['order_item_id'];
						
								$line_subtotal = $_POST['line_subtotal'];
								//print_r($line_subtotal);
								$booking = array();
								$variation_id = $values['variation_id'];
								$order_key_exists = '';
								//echo $oid_value;
								if(isset($_POST['meta_ids'.$cart_item_key]))
								{
									$meta_ids = explode(",",$_POST['meta_ids'.$cart_item_key]);
								}
								else
								{
									$id_query = "SELECT meta_id FROM `".$wpdb->prefix."woocommerce_order_itemmeta` WHERE order_item_id = '".$cart_item_key."'";
									$results = $wpdb->get_results ( $id_query );
									$i = 0;
									$meta_ids = array();
									foreach($results as $k => $v)
									{
										$meta_ids[] = $v->meta_id;
										$i++;
									}
								}
								//print_r($meta_ids);
								foreach ( $meta as $mk_key => $mk_value )
								{
									if ($mk_value['meta_key'] == get_option('book.item-meta-date') || $mk_value['meta_key'] == strip_tags(get_option("checkout.item-meta-date")) && in_array($mk_key,$meta_ids))
									{
										$key = $mk_value['meta_key'];
										$meta_id = $mk_value['meta_id'];
										//echo $key;
										$booking[$key] = $mk_value['meta_value'];
										$order_key_exists = 'Y';
									}
									
								}
								//print_r($meta);exit;
								if(count($booking) == 0)
								{
									foreach ( $meta_keys as $mk_key => $mk_value )
									{
										foreach ( $meta_values as $mv_key => $mv_value )
										{
											if ($mk_key == $mv_key && in_array($mk_key,$meta_ids))
											{
												$booking[$mk_value] = $mv_value;
												$order_key_exists = 'N';
											}
										}
									}
								}
								$booking_settings = get_post_meta( $post_id, 'woocommerce_booking_settings', true);
								//echo get_option('book.item-meta-time');
								//print_r($meta_keys);
								//print_r($booking);
								$date_name = get_option('book.item-meta-date');
								global $bkap_block_booking;
										$number_of_fixed_price_blocks = $bkap_block_booking->get_fixed_blocks_count($post_id);
								//print_r($number_of_fixed_price_blocks);exit;
								$check_out_name = strip_tags(get_option("checkout.item-meta-date"));
								if (isset($booking_settings['booking_fixed_block_enable']) && $booking_settings['booking_fixed_block_enable'] == 'yes' &&  (isset($number_of_fixed_price_blocks) && $number_of_fixed_price_blocks > 0) && isset($booking[$check_out_name]) && $booking[$check_out_name] != "")
								{
									if (isset($booking[$date_name]) && $booking[$date_name] != "")
									{
										$date_select = $booking[$date_name];
										woocommerce_update_order_item_meta( $cart_item_key, $date_name, sanitize_text_field( $date_select, true ) );
									}
								}
								
								$date_checkout_select = '';
								if (isset($booking[$check_out_name]) && $booking[$check_out_name] != "")
								{
									//echo "here in checkout";	
									//print_r($booking[$check_out_name]);exit;
									$date_checkout_select = $booking[$check_out_name];
									woocommerce_update_order_item_meta( $cart_item_key, $check_out_name, sanitize_text_field( $date_checkout_select, true ) );
									if ($order_key_exists == 'Y')
									{
										$total_price = $line_subtotal[$cart_item_key];
										$line_subtotal[$cart_item_key] = $total_price;
									}
									else 
									{
										
										if (isset($_POST['wapbk_admin_hidden_date_checkout_'.$cart_item_key]))
											$checkout_date = $_POST['wapbk_admin_hidden_date_checkout_'.$cart_item_key];
									
										if (isset($_POST['wapbk_admin_hidden_date_'.$cart_item_key]))
											$checkin_date = $_POST['wapbk_admin_hidden_date_'.$cart_item_key];
										$days = (strtotime($checkout_date) - strtotime($checkin_date)) / (60*60*24);
										//echo $days;
										//print_r($_POST['']);exit;
										if (isset($_POST['wapbk_admin_same_day_'.$cart_item_key]) && $_POST['wapbk_admin_same_day_'.$cart_item_key] == 'on')
										{
											if ($days >= 0)
											{
												//if(is_plugin_active('bkap-rental/rental.php') && isset($booking_settings['booking_charge_per_day']) && $booking_settings['booking_charge_per_day'] == 'on')
												if(isset($_POST['add_days_'.$cart_item_key]))
												{
													$days = $days + $_POST['add_days_'.$cart_item_key];
												}
												//echo $days;exit;
												$total_price = $days * $line_subtotal[$cart_item_key] * $values['qty'];
											}
										}
										else
										{
											if ($days > 0)
											{
												//if(is_plugin_active('bkap-rental/rental.php') && isset($booking_settings['booking_charge_per_day']) && $booking_settings['booking_charge_per_day'] == 'on')
												if(isset($_POST['add_days_'.$cart_item_key]))	
												{
													$days = $days + $_POST['add_days_'.$cart_item_key];
												}
												//echo $days;exit;
												$total_price = $days * $line_subtotal[$cart_item_key] * $values['qty'];
											}
										}
										//print_r($number_of_fixed_price_blocks);exit;
										if (isset($booking_settings['booking_fixed_block_enable']) && $booking_settings['booking_fixed_block_enable'] == 'yes'  &&(isset($number_of_fixed_price_blocks) && $number_of_fixed_price_blocks > 0))
										{
											if(isset($_POST['admin_block_option_price_'.$cart_item_key]))
											{
												$total_price = $_POST['admin_block_option_price_'.$cart_item_key];
											}
											else
											{
												$total_price = '';
											}
											//echo $total_price;exit;
										}
										else if(isset($booking_settings['booking_block_price_enable']) && $booking_settings['booking_block_price_enable'] == 'yes')
										{
											$get_price = $this->get_per_night_price($post_id, $days, $booking_settings, $values['variation_id']);
											//echo $get_price;
											$price_exploded = explode("-", $get_price);
											$total_price = '';
											if(isset($price_exploded[1]) && $price_exploded[1] == "per_day")
											{
												$total_price = $days * $price_exploded[0] * $values['qty'];
											}
											else if(isset($price_exploded[1]) && $price_exploded[1] == "fixed")
											{
												$total_price = $price_exploded[0] * $values['qty'];
											}
											//echo $total_price;
										}
									// Round the price if rounding is enabled
									}
									$global_settings = json_decode(get_option('woocommerce_booking_global_settings'));
									if (isset($global_settings->enable_rounding) && $global_settings->enable_rounding == "on")
									{
										$round_price = round($total_price);
										$total_price = $round_price;
									}
									$line_subtotal[$cart_item_key] = $total_price;
									//print_r($line_subtotal);
									$query_update_subtotal = "UPDATE `".$wpdb->prefix."woocommerce_order_itemmeta`
									SET meta_value = '".woocommerce_clean( $total_price )."'
									WHERE order_item_id = '".$cart_item_key."'
									AND meta_key = '_line_subtotal'";
									$wpdb->query( $query_update_subtotal );
									//print_r($query_update_subtotal);
									$_POST['line_subtotal'] = $line_subtotal;
							
									$query_update_total =  "UPDATE `".$wpdb->prefix."woocommerce_order_itemmeta`
									SET meta_value = '".woocommerce_clean( $total_price )."'
									WHERE order_item_id = '".$cart_item_key."'
									AND meta_key = '_line_total'";
									$wpdb->query( $query_update_total );
									$_POST['line_total'] = $line_subtotal;
								}
								$time_name = get_option('book.item-meta-time');
						
								if (isset($booking[$time_name]) && $booking[$time_name] != "")
								{
									$time_select = $booking[$time_name];
									$time_exploded = explode("-", $time_select);
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
									woocommerce_update_order_item_meta( $cart_item_key,  $time_name, $time_slot_to_display);
								}
								$query_update_quantity =  "UPDATE `".$wpdb->prefix."woocommerce_order_itemmeta`
										SET meta_value = '".woocommerce_clean( $quantity )."'
										WHERE order_item_id = '".$cart_item_key."'
										AND meta_key = '_qty'";
								//echo $query_update_quantity;
								$wpdb->query( $query_update_quantity );
								//echo $quantity;
								$order_item_qty[$cart_item_key] = $quantity;
								$_POST['order_item_qty'] = $order_item_qty;
								if ($new_order == false && $edit_order == true) 
								{
									if (isset($booking_settings['booking_enable_multiple_day']) && $booking_settings['booking_enable_multiple_day'] == 'on')
									{
										$booking_ids = array();
										if(in_array($check_out_name,$booking))
										{
											$start_date = date("Y-m-d",strtotime($booking[$date_name]));
											$end_date = date("Y-m-d",strtotime($booking[$check_out_name]));
											$query_result = "SELECT COUNT(*) as bookings_done FROM `".$wpdb->prefix."booking_history`
												WHERE
												start_date = '".$start_date."' AND end_date = '".$end_date."' AND post_id = ".$post_id;
										//echo $query_result;
											$item_results_lockout = $wpdb->get_results( $query_result );
											$lockout = "";
											if (isset($booking_settings['booking_date_lockout']))
											{
												$lockout = $booking_settings['booking_date_lockout'];
											}
											if(count($item_results_lockout) > 0)
											{
												$booking_available = $lockout - $item_results_lockout[0]->bookings_done;
												foreach ($existing_order_result as $ekey => $evalue)
												{
													$booking_id = $evalue->booking_id;
												//print_r($evalue);
													$query = "SELECT * FROM `".$wpdb->prefix."booking_history`
														WHERE
														id = $booking_id ";
													$item_results = $wpdb->get_results( $query );
													//print_r($item_results);
													if(count($item_results) > 0)
													{
														$booking_ids[] = $booking_id;
													}
												}
												if ($order_key_exists == 'Y')
												{
													if ($existing_quantity < $quantity)
													{
														if($quantity <= $booking_available)
														{
															for ($i = $existing_quantity; $i < $quantity; $i++)
															{
																$query = "INSERT INTO `".$wpdb->prefix."booking_history`
																	(post_id,weekday,start_date,end_date,from_time,to_time,total_booking,available_booking)
																	VALUES (
																	'".$post_id."',
																	'',
																	'".$start_date."',
																	'".$end_date."',
																	'',
																	'',
																	'0',
																	'0' )";
																$wpdb->query( $query );
																$new_booking_id = mysql_insert_id();
																$order_query = "INSERT INTO `".$wpdb->prefix."booking_order_history`
																	(order_id,booking_id)
																	VALUES (
																	'".$order_id."',
																	'".$new_booking_id."' )";
																$wpdb->query( $order_query );
															}
														}
														else if($lockout != 0)
														{
															$query_date = "DELETE FROM `".$wpdb->prefix."woocommerce_order_itemmeta` 
																WHERE 
																order_item_id = ".$cart_item_key." AND meta_key = 'Name'";
															$wpdb->query( $query_date );
															/*$query_checkout_date = "DELETE FROM `".$wpdb->prefix."woocommerce_order_itemmeta` 
															WHERE 
															order_item_id = ".$cart_item_key." AND meta_key = '".$check_out_name."'";
														$wpdb->query( $query_checkout_date);*/
														$post = get_post($post_id);
													//print_r($post);
														$title = $post->post_title;
														print('
															<script type="text/javascript">
															alert("The item you changed the quantity for '.$title.', exceeds the quantity available for your selected dates. Please reduce the quantity or remove it from your order."); 
															window.history.back();
															//return;
														</script>');
														exit;
													}
												}
												elseif ($existing_quantity > $quantity)
												{
													for ($i = $quantity; $i < $existing_quantity; $i++)
													{
														$query = "DELETE FROM `".$wpdb->prefix."booking_history` 
															WHERE 
															id = ".$booking_ids[$i];
															$wpdb->query( $query );
															$order_query = "DELETE FROM `".$wpdb->prefix."booking_order_history`
																WHERE order_id = '".$order_id."' 
																AND 
																booking_id = '".$booking_ids[$i]."'";
															$wpdb->query( $order_query );
														}
													}	
												}
												else if ($order_key_exists == 'N')
												{
													if($quantity <= $booking_available)
													{
														for ($i = $existing_quantity; $i <= $quantity; $i++)
														{
															$query = "INSERT INTO `".$wpdb->prefix."booking_history`
															(post_id,weekday,start_date,end_date,from_time,to_time,total_booking,available_booking)
															VALUES (
																'".$post_id."',
																'',
																'".$start_date."',
																'".$end_date."',
																'',
																'',
																'0',
																'0' )";
															$wpdb->query( $query );
															$new_booking_id = mysql_insert_id();
															$order_query = "INSERT INTO `".$wpdb->prefix."booking_order_history`
															(order_id,booking_id)
															VALUES (
															'".$order_id."',
															'".$new_booking_id."' )";
															$wpdb->query( $order_query );
														}
													}
													else if($lockout != 0)
													{
														$query_date = "DELETE FROM `".$wpdb->prefix."woocommerce_order_itemmeta`
														WHERE
														order_item_id = ".$cart_item_key." AND meta_key = 'Name'";
														$wpdb->query( $query_date );
															
													/*$query_checkout_date = "DELETE FROM `".$wpdb->prefix."woocommerce_order_itemmeta`
													WHERE
													order_item_id = ".$cart_item_key." AND meta_key = '".$check_out_name."'";
													$wpdb->query( $query_checkout_date);
													$post = get_post($post_id);
													//print_r($post);*/
													$title = $post->post_title;
													print('
															<script type="text/javascript">
														alert("The item you changed the quantity for '.$title.', exceeds the quantity available for your selected dates. Please reduce the quantity or remove it from your order.");
														window.history.back();
														//return;
														</script>');
														exit;
													}
												}
											}
										}
									}
									else if (isset($booking_settings['booking_enable_time']) && $booking_settings['booking_enable_time'] == 'on')
									{	
										if(array_key_exists($date_name,$booking))
											$start_date = date("Y-m-d",strtotime($booking[$date_name]));
										if(array_key_exists($time_name, $booking))
										{
											$time_slot = explode("-",$booking[$time_name]);
											$from_time = date("G:i",strtotime($time_slot[0]));
											$to_time = date("G:i",strtotime($time_slot[1]));
											$query_result = "SELECT available_booking,total_booking FROM `".$wpdb->prefix."booking_history`
													WHERE
													start_date = '".$start_date."' AND from_time = '".$from_time."' AND to_time = '".$to_time."' AND post_id = ".$post_id." AND total_booking > 0";
											//echo $query_result;
											$item_results_lockout = $wpdb->get_results( $query_result );
											if(count($item_results_lockout) > 0)
											{
												$booking_available = $item_results_lockout[0]->available_booking;
												/*foreach ($existing_order_result as $ekey => $evalue)
												{
													$booking_id = $evalue->booking_id;
													//print_r($evalue);
													$query = "SELECT * FROM `".$wpdb->prefix."booking_history`
														WHERE
													id = $booking_id ";
													$item_results = $wpdb->get_results( $query );
													//print_r($item_results);
													if(count($item_results) > 0)
													{
														$booking_ids[] = $booking_id;
													}
												}*/
												if ($order_key_exists == 'Y')
												{
													if ($existing_quantity < $quantity)
													{
														if($quantity <= $booking_available)
														{
															$qty = $quantity - $existing_quantity;
															if($to_time != "")
															{
																$query = "UPDATE `".$wpdb->prefix."booking_history`
																SET available_booking = available_booking - ".$qty."
																WHERE post_id = '".$post_id."' AND
																start_date = '".$start_date."' AND
																from_time = '".$from_time."' AND
																to_time = '".$to_time."' AND
																total_booking > 0";
																$wpdb->query( $query );
											
															$select = "SELECT * FROM `".$wpdb->prefix."booking_history`
																WHERE post_id = '".$post_id."' AND
																start_date = '".$start_date."' AND
																from_time = '".$from_time."' AND
																to_time = '".$to_time."' ";
																$select_results = $wpdb->get_results( $select );
																foreach($select_results as $k => $v)
																{
																	$details[$post_id] = $v;
																}
															}
															else
															{
																$query = "UPDATE `".$wpdb->prefix."booking_history`
																SET available_booking = available_booking - ".$qty."
																WHERE post_id = '".$post_id."' AND
																start_date = '".$start_date."' AND
																from_time = '".$from_time."' AND
																total_booking > 0";
																$wpdb->query( $query );
												
																$select = "SELECT * FROM `".$wpdb->prefix."booking_history`
																WHERE post_id = '".$post_id."' AND
																start_date = '".$start_date."' AND
																from_time = '".$from_time."'";
																$select_results = $wpdb->get_results( $select );
																foreach($select_results as $k => $v)
																{
																	$details[$post_id] = $v;
																}
															}
														}
														else
														{
															$query_date = "DELETE FROM `".$wpdb->prefix."woocommerce_order_itemmeta` 
																WHERE 
																order_item_id = ".$cart_item_key." AND meta_key = 'Name'";
															$wpdb->query( $query_date );
															$wpdb->query( $query_checkout_date);
															$post = get_post($post_id);
															//print_r($post);
															$title = $post->post_title;
															print('
																<script type="text/javascript">
																alert("The item you changed the quantity for '.$title.', exceeds the quantity available for your selected date and time slot. Please reduce the quantity or remove it from your order."); 
																window.history.back();
																//return;
																</script>');
															exit;
														}
													}
													elseif ($existing_quantity > $quantity)
													{
														$qty = $existing_quantity - $quantity;
														if($to_time != "")
														{
															$query = "UPDATE `".$wpdb->prefix."booking_history`
															SET available_booking = available_booking + ".$qty."
															WHERE post_id = '".$post_id."' AND
															start_date = '".$start_date."' AND
															from_time = '".$from_time."' AND
															to_time = '".$to_time."' AND
															total_booking > 0";
															$wpdb->query( $query );
											
															$select = "SELECT * FROM `".$wpdb->prefix."booking_history`
															WHERE post_id = '".$post_id."' AND
															start_date = '".$start_date."' AND
															from_time = '".$from_time."' AND
															to_time = '".$to_time."' ";
															$select_results = $wpdb->get_results( $select );
															foreach($select_results as $k => $v)
															{
																$details[$post_id] = $v;
															}
														}
														else
														{
															$query = "UPDATE `".$wpdb->prefix."booking_history`
															SET available_booking = available_booking + ".$qty."
															WHERE post_id = '".$post_id."' AND
															start_date = '".$start_date."' AND
															from_time = '".$from_time."' AND
															total_booking > 0";
															$wpdb->query( $query );
												
															$select = "SELECT * FROM `".$wpdb->prefix."booking_history`
															WHERE post_id = '".$post_id."' AND
															start_date = '".$start_date."' AND
															from_time = '".$from_time."'";
															$select_results = $wpdb->get_results( $select );
															foreach($select_results as $k => $v)
															{
																$details[$post_id] = $v;
															}
														}
													}
												}
												else if ($order_key_exists == 'N')
												{
													if($quantity <= $booking_available)
													{
														if($to_time != "")
														{
															$query = "UPDATE `".$wpdb->prefix."booking_history`
															SET available_booking = available_booking - ".$quantity."
															WHERE post_id = '".$post_id."' AND
															start_date = '".$start_date."' AND
															from_time = '".$from_time."' AND
															to_time = '".$to_time."' AND
															total_booking > 0";
														//echo $query;exit;
															$wpdb->query( $query );
													
															$select = "SELECT * FROM `".$wpdb->prefix."booking_history`
																WHERE post_id = '".$post_id."' AND
																start_date = '".$start_date."' AND
																from_time = '".$from_time."' AND
																to_time = '".$to_time."' ";
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
															start_date = '".$start_date."' AND
															from_time = '".$from_time."' AND
															total_booking > 0";
															$wpdb->query( $query );
										
															$select = "SELECT * FROM `".$wpdb->prefix."booking_history`
															WHERE post_id = '".$post_id."' AND
															start_date = '".$start_date."' AND
															from_time = '".$from_time."'";
															$select_results = $wpdb->get_results( $select );
															foreach($select_results as $k => $v)
															{
																$details[$post_id] = $v;
															}
														}
													}
													else
													{
														$query_date = "DELETE FROM `".$wpdb->prefix."woocommerce_order_itemmeta` 
															WHERE 
															order_item_id = ".$cart_item_key." AND meta_key = 'Name'";
														$wpdb->query( $query_date );
														$wpdb->query( $query_checkout_date);
														$post = get_post($post_id);
														//print_r($post);
														$title = $post->post_title;
														print('
															<script type="text/javascript">
															alert("The item you changed the quantity for '.$title.', exceeds the quantity available for your selected date and time slot. Please reduce the quantity or remove it from your order."); 
															window.history.back();
															//return;
														</script>');
														exit;
													}
												}
											}
										}
									}
									else
									{
										$start_date = date("Y-m-d",strtotime($booking[$date_name]));
										$query_result = "SELECT available_booking,total_booking FROM `".$wpdb->prefix."booking_history`
												WHERE
												start_date = '".$start_date."' AND post_id = ".$post_id." AND total_booking > 0";
										//echo $query_result;
										$item_results_lockout = $wpdb->get_results( $query_result );
										if(count($item_results_lockout) > 0)
										{
											$booking_available = $item_results_lockout[0]->available_booking;
											if ($order_key_exists == 'Y')
											{
												if ($existing_quantity < $quantity)
												{
													if($quantity <= $booking_available)
													{
														$qty = $quantity - $existing_quantity;
														$query = "UPDATE `".$wpdb->prefix."booking_history`
															SET available_booking = available_booking - ".$qty."
															WHERE post_id = '".$post_id."' AND
															start_date = '".$start_date."' AND
															total_booking > 0";
														$wpdb->query( $query );
													}
													else
													{
														$query_date = "DELETE FROM `".$wpdb->prefix."woocommerce_order_itemmeta` 
															WHERE 
															order_item_id = ".$cart_item_key." AND meta_key = 'Name'";
														$wpdb->query( $query_date );
														$post = get_post($post_id);
														//print_r($post);
														$title = $post->post_title;
														print('
															<script type="text/javascript">
															alert("The item you changed the quantity for '.$title.', exceeds the quantity available for your selected date. Please reduce the quantity or remove it from your order."); 
															window.history.back();
															//return;
															</script>');
														exit;
													}
												}
												else if ($existing_quantity > $quantity)
												{
													$qty = $existing_quantity - $quantity;
													$query = "UPDATE `".$wpdb->prefix."booking_history`
															SET available_booking = available_booking + ".$qty."
															WHERE post_id = '".$post_id."' AND
															start_date = '".$start_date."' AND
															total_booking > 0";
													$wpdb->query( $query );
												}
											}
											else if ($order_key_exists == 'N')
											{
												if($quantity <= $booking_available)
												{
													$query = "UPDATE `".$wpdb->prefix."booking_history`
														SET available_booking = available_booking - ".$quantity."
														WHERE post_id = '".$post_id."' AND
														start_date = '".$start_date."' AND
														total_booking > 0";
													$wpdb->query( $query );
												}
												else
												{
													$query_date = "DELETE FROM `".$wpdb->prefix."woocommerce_order_itemmeta` 
															WHERE 
															order_item_id = ".$cart_item_key." AND meta_key = 'Name'";
												$wpdb->query( $query_date );
												$post = get_post($post_id);
													//print_r($post);
												$title = $post->post_title;
												print('
															<script type="text/javascript">
															alert("The item you changed the quantity for '.$title.', exceeds the quantity available for your selected date. Please reduce the quantity or remove it from your order."); 
															window.history.back();
															//return;
														</script>');
													exit;
												}
											}
										}
									}
								}
								else
								{
									//
									//echo $booking['wapbk_admin_hidden_date'];exit;
									if (isset($_POST['wapbk_admin_hidden_date_'.$cart_item_key])) :
										$hidden_date = $_POST['wapbk_admin_hidden_date_'.$cart_item_key];
									$date_query = date('Y-m-d', strtotime($hidden_date));
									if(isset($_POST['wapbk_admin_hidden_date_checkout_'.$cart_item_key]))
									{
										$date_checkout = $_POST['wapbk_admin_hidden_date_checkout_'.$cart_item_key];
										$date_checkout_query = date('Y-m-d',strtotime($date_checkout));
									}
									//echo $_POST['wapbk_admin_hidden_date_'.$cart_item_key];
									//echo $_POST['wapbk_admin_hidden_date_checkout_'.$cart_item_key];exit;
									//echo "_POST['wapbk_admin_hidden_date_checkout_'".$cart_item_key."]";exit;
									if (isset($booking_settings['booking_enable_multiple_day'])&& $booking_settings['booking_enable_multiple_day'] == 'on')
									{
										$query_result = "SELECT COUNT(*) as bookings_done FROM `".$wpdb->prefix."booking_history`
												WHERE
												start_date = '".$date_query."' AND end_date = '".$date_checkout_query."' AND post_id = ".$post_id;
										//echo $query_result;
										$item_results_lockout = $wpdb->get_results( $query_result );
										$lockout = "";
										if (isset($booking_settings['booking_date_lockout']))
										{
											$lockout = $booking_settings['booking_date_lockout'];
										}
										if(count($item_results_lockout) > 0)
										{
											$booking_available = $lockout - $item_results_lockout[0]->bookings_done;
											if($quantity <= $booking_available)
											{
												for ($i = $existing_quantity; $i <= $quantity; $i++)
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
													$new_booking_id = mysql_insert_id();
													$order_query = "INSERT INTO `".$wpdb->prefix."booking_order_history`
														(order_id,booking_id)
														VALUES (
														'".$order_id."',
														'".$new_booking_id."' )";
													$wpdb->query( $order_query );
												}
											}
											else if($lockout != 0)
											{
												$query_date = "DELETE FROM `".$wpdb->prefix."woocommerce_order_itemmeta`
													WHERE
													order_item_id = ".$cart_item_key." AND meta_key = 'Name'";
												$wpdb->query( $query_date );
												//$wpdb->query( $query_checkout_date);
												$post = get_post($post_id);
													//print_r($post);
												$title = $post->post_title;
												print('
															<script type="text/javascript">
													alert("The item you changed the quantity for '.$title.', exceeds the quantity available for your selected dates. Please reduce the quantity or remove it from your order.");
													window.history.back();
													//return;
													</script>');
												exit;
											}
										}
									}
									else
									{
										if(isset($booking[$time_name]) && $booking[$time_name] != "")
										{
											//echo $item_name;
											//print_r($booking);exit;
											$time_slot = explode("-",$booking[$time_name]);
											$from_time = date("G:i",strtotime($time_slot[0]));
											if(isset($time_slot[1]))
											{
												$to_time = date("G:i",strtotime($time_slot[1]));
											}
											else
											{
												$to_time = '';
											}
											$query_result = "SELECT available_booking,total_booking FROM `".$wpdb->prefix."booking_history`
												WHERE
												start_date = '".$date_query."' AND from_time = '".$from_time."' AND to_time = '".$to_time."' AND post_id = ".$post_id." AND total_booking > 0";
												//echo $query_result;
											$item_results_lockout = $wpdb->get_results( $query_result );
											if(count($item_results_lockout) > 0)
											{
												$booking_available = $item_results_lockout[0]->available_booking;
												if($quantity <= $booking_available)
												{
													if($to_time != "")
													{
														$query = "UPDATE `".$wpdb->prefix."booking_history`
															SET available_booking = available_booking - ".$quantity."
															WHERE post_id = '".$post_id."' AND
															start_date = '".$date_query."' AND
															from_time = '".$from_time."' AND
															to_time = '".$to_time."' AND
															total_booking > 0";
														$wpdb->query( $query );
													
														$select = "SELECT * FROM `".$wpdb->prefix."booking_history`
															WHERE post_id = '".$post_id."' AND
															start_date = '".$date_query."' AND
															from_time = '".$from_time."' AND
															to_time = '".$to_time."' ";
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
															from_time = '".$from_time."' AND
															total_booking > 0";
														$wpdb->query( $query );
										
														$select = "SELECT * FROM `".$wpdb->prefix."booking_history`
															WHERE post_id = '".$post_id."' AND
															start_date = '".$date_query."' AND
															from_time = '".$from_time."'";
														$select_results = $wpdb->get_results( $select );
														foreach($select_results as $k => $v)
														{	
															$details[$post_id] = $v;
														}
													}
												}
												else
												{
													$query_date = "DELETE FROM `".$wpdb->prefix."woocommerce_order_itemmeta` 
															WHERE 
															order_item_id = ".$cart_item_key." AND meta_key = 'Name'";
													$wpdb->query( $query_date );
													$post = get_post($post_id);
													//print_r($post);
													$title = $post->post_title;
													print('
															<script type="text/javascript">
															alert("The item you changed the quantity for '.$title.', exceeds the quantity available for your selected date and time slot. Please reduce the quantity or remove it from your order."); 
															window.history.back();
															//return;
														</script>');
													exit;
												}
											}
										}
										else
										{
											//$start_date = date("Y-m-d",strtotime($booking[$date_name]));
											$query_result = "SELECT available_booking,total_booking FROM `".$wpdb->prefix."booking_history`
												WHERE
												start_date = '".$date_query."' AND post_id = ".$post_id." AND total_booking > 0";
											//echo $query_result;exit;
											$item_results_lockout = $wpdb->get_results( $query_result );
											$booking_available = $item_results_lockout[0]->available_booking;
											if($quantity <= $booking_available)
											{
												$query = "UPDATE `".$wpdb->prefix."booking_history`
													SET available_booking = available_booking - ".$quantity."
													WHERE post_id = '".$post_id."' AND
													start_date = '".$date_query."' AND
													total_booking > 0";
													$wpdb->query( $query );
												}
												else
												{
													$query_date = "DELETE FROM `".$wpdb->prefix."woocommerce_order_itemmeta` 
															WHERE 
															order_item_id = ".$cart_item_key." AND meta_key = 'Name'";
													$wpdb->query( $query_date );
													$post = get_post($post_id);
													//print_r($post);
													$title = $post->post_title;
													print('
																<script type="text/javascript">
															alert("The item you changed the quantity for '.$title.', exceeds the quantity available for your selected date. Please reduce the quantity or remove it from your order."); 
															window.history.back();
															//return;
														</script>');
													exit;
												}
											}
										}
										if(isset($booking[$time_name]) &&  $booking[$time_name]!= "")
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
										endif;
								}
							}
						}
					}
				}
				//exit;
			}
			function seasonal_pricing_page()
			{
				
			}
			
			function admin_booking_enqueue_scripts_css() 
			{		
				//if ( (isset($_GET['post_type']) && $_GET['post_type'] == 'shop_order' ) )
				if(get_post_type() == 'shop_order')
				{
					//wp_enqueue_style( 'datepick', plugins_url('/css/jquery.datepick.css', __FILE__ ) , '', '', false);
					//wp_enqueue_style( 'woocommerce_admin_styles', plugins_url() . '/woocommerce/assets/css/admin.css' );
					
					$calendar_theme = json_decode(get_option('woocommerce_booking_global_settings'));
					$calendar_theme_sel = "";
					if (isset($calendar_theme))
					{
						$calendar_theme_sel = $calendar_theme->booking_themes;
					}
					if ( $calendar_theme_sel == "" ) $calendar_theme_sel = 'smoothness';
					//wp_enqueue_style( 'jquery-ui', "http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/$calendar_theme_sel/jquery-ui.css" , '', '', false);
					wp_enqueue_style( 'jquery-ui', plugins_url('/css/themes/'.$calendar_theme_sel.'/jquery-ui.css', __FILE__ ) , '', '', false);
					
					wp_enqueue_style( 'jquery.ui.theme', plugins_url('/css/themes/'.$calendar_theme_sel.'/jquery.ui.theme.css', __FILE__ ) , '', '', false);
				}
			}
			
			function admin_booking_enqueue_scripts_js() {
			
				//echo get_post_type();
				//if ( (isset ($_GET['post_type']) && $_GET['post_type'] == 'shop_order') || (isset ($_GET['post']) ) )
				if(get_post_type() == 'shop_order')
				{
			//		wp_register_script( 'datepick', plugins_url().'/bkap-admin-bookings/js/jquery.datepick.js');
					wp_register_script( 'datepick', plugins_url().'/woocommerce-booking/js/jquery.datepick.js');
					wp_enqueue_script( 'datepick' );
					
					wp_enqueue_script(
							'initialize-datepicker-admin.js',
							plugins_url('/js/initialize-datepicker-admin.js', __FILE__),
							'',
							'',
							false
					);
					/*wp_enqueue_script(
							'jquery-tip',
							plugins_url('/js/jquery.tipTip.minified.js', __FILE__),
							'',
							'',
							false
					);
					
					wp_register_script( 'woocommerce_admin', plugins_url() . '/woocommerce/assets/js/admin/woocommerce_admin.js', array('jquery', 'jquery-ui-widget', 'jquery-ui-core'));
					wp_enqueue_script( 'woocommerce_admin' );*/
					
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
			
		}
	
	}
	$bkap_admin_bookings = new bkap_admin_bookings();
}
?>