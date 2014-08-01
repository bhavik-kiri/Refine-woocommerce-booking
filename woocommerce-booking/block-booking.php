<?php 
 
 
	/**
	 * Localisation
	 **/
	load_plugin_textdomain('bkap_block_booking', false, dirname( plugin_basename( __FILE__ ) ) . '/');

	/**
	 * bkap_deposits class
	 **/
	if (!class_exists('bkap_block_booking')) {

		class bkap_block_booking {

			public function __construct() {
				// Initialize settings
				register_activation_hook( __FILE__, array(&$this, 'block_booking_activate'));
				
				// used to add new settings on the product page booking box
				add_action('bkap_after_listing_enabled', array(&$this, 'show_field_settings'));
				add_action('init', array(&$this, 'load_ajax'));
				add_filter('bkap_save_product_settings', array(&$this, 'product_settings_save'), 10, 2);
				add_action('bkap_fixed_block_display_updated_price', array(&$this, 'show_updated_price'),10,3);
				add_filter('bkap_add_cart_item_data', array(&$this, 'add_cart_item_data'), 10, 2);
				add_filter('bkap_get_cart_item_from_session', array(&$this, 'get_cart_item_from_session'),11,2);

				add_action( 'woocommerce_before_add_to_cart_form', array(&$this, 'before_add_to_cart'));
				add_action( 'woocommerce_before_add_to_cart_button', array(&$this, 'booking_after_add_to_cart'));

			//	add_filter('bkap_get_item_data', array(&$this, 'get_item_data'), 10, 2 );
				add_action('bkap_deposits_update_order', array(&$this, 'order_item_meta'), 10,2);
				add_action('bkap_display_price_div', array(&$this, 'display_price'),10,1);
				
				$this->days = array('0' => 'Sunday',
						'1' => 'Monday',
						'2' => 'Tuesday',
						'3' => 'Wednesday',
						'4' => 'Thursday',
						'5' => 'Friday',
						'6' => 'Saturday'
				);
			}
			

			function block_booking_activate()
			{
			
				global $wpdb;
				
				$table_name = $wpdb->prefix . "booking_fixed_blocks";
				
				$sql = "CREATE TABLE IF NOT EXISTS ".$table_name." (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`global_id` int(11) NOT NULL,
				`post_id` int(11) NOT NULL,
				`block_name` varchar(50) NOT NULL,
                `number_of_days` int(11) NOT NULL,
				`start_day` varchar(50) NOT NULL,
                `end_day` varchar(50) NOT NULL,
				`price` double NOT NULL,
				`block_type` varchar(25) NOT NULL,
				PRIMARY KEY (`id`)s
				) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 " ;
				
				
				
				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				dbDelta($sql);
			}
			
			function load_ajax()
			{
				if ( !is_user_logged_in() )
				{
					add_action('wp_ajax_nopriv_save_booking_block',  array(&$this,'save_booking_block'));
					add_action('wp_ajax_nopriv_booking_block_table',  array(&$this,'booking_block_table'));
					add_action('wp_ajax_nopriv_delete_block',  array(&$this,'delete_block'));
					add_action('wp_ajax_nopriv_delete_all_blocks',  array(&$this,'delete_all_blocks'));
					add_action('wp_ajax_nopriv_save_global_season',  array(&$this,'save_global_season'));
					add_action('wp_ajax_nopriv_delete_global_season',  array(&$this,'delete_global_season'));
					add_action('wp_ajax_nopriv_delete_all_global_seasons',  array(&$this,'delete_all_global_seasons'));
				}
				else
				{
					add_action('wp_ajax_save_booking_block',  array(&$this,'save_booking_block'));
					add_action('wp_ajax_booking_block_table',  array(&$this,'booking_block_table'));
					add_action('wp_ajax_delete_block',  array(&$this,'delete_block'));
					add_action('wp_ajax_delete_all_blocks',  array(&$this,'delete_all_blocks'));
					add_action('wp_ajax_save_global_season',  array(&$this,'save_global_season'));
					add_action('wp_ajax_delete_global_season',  array(&$this,'delete_global_season'));
					add_action('wp_ajax_delete_all_global_seasons',  array(&$this,'delete_all_global_seasons'));
				}
			}

			function before_add_to_cart(){
			
			}

			function booking_after_add_to_cart(){	
				global $post, $wpdb, $woocommerce;
 				$booking_settings = get_post_meta($post->ID, 'woocommerce_booking_settings', true);

 				 if ((isset($booking_settings['booking_enable_multiple_day']) && $booking_settings['booking_enable_multiple_day'] == 'on') && (isset($booking_settings['booking_fixed_block_enable']) && $booking_settings['booking_fixed_block_enable'] == 'yes' )) {
 				 	
 				 	$results = $this->get_fixed_blocks($post->ID);
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
 				 	
					if (count($results) > 0)
					{

						printf ('

						<label> Select Period : </label><select name="block_option" id="block_option" >');
						foreach ($results as $key => $value)
						{
							printf('<option value=%s&%s&%s>%s</option>',$value->start_day,$value->number_of_days,$value->price,$value->block_name);
						} 
						printf ('</select> <br/> <br/>');
						?>
						<script type="text/javascript">	
						jQuery("#block_option").change(function()
						{
							if ( jQuery("#block_option").val() != "" )
							{
								var passed_id = this.value;
								var exploded_id = passed_id.split('&');
								console.log(exploded_id);
								jQuery("#block_option_start_day").val(exploded_id[0]);
								jQuery("#block_option_number_of_day").val(exploded_id[1]);
								jQuery("#block_option_price").val(exploded_id[2]);
								jQuery("#wapbk_hidden_date").val("");
								jQuery("#wapbk_hidden_date_checkout").val("");
								//jQuery("#show_time_slot").html("");
								jQuery("#show_time_slot").html("");
								jQuery("#booking_calender").datepicker("setDate");
								jQuery("#booking_calender_checkout").datepicker("setDate");
								
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
						echo ' <input type="hidden" id="block_option_enabled"  name="block_option_enabled" value="on"/> <input type="hidden" id="block_option_start_day"  name="block_option_start_day" value="'.$sd.'"/> <input type="hidden" id="block_option_number_of_day"  name="block_option_number_of_day" value="'.$nd.'"/><input type="hidden" id="block_option_price"  name="block_option_price" value="'.$pd.'"/>';	
					}
					else 
					{
						$number_of_fixed_price_blocks = 0;
						echo ' <input type="hidden" id="block_option_enabled"  name="block_option_enabled" value="off"/> <input type="hidden" id="block_option_start_day"  name="block_option_start_day" value=""/> <input type="hidden" id="block_option_number_of_day"  name="block_option_number_of_day" value=""/><input type="hidden" id="block_option_price"  name="block_option_price" value=""/>';
					}
 				 }
			}

			// function booking_after_add_to_cart(){
			// 	echo ' <input type="hidden" id="block_option_start_day"  name="block_option_start_day" value=""/> <input type="hidden" id="block_option_number_of_day"  name="block_option_number_of_day" value=""/>';
			// }

			function slot_type($product_id)
			{
				$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
				if($booking_settings['booking_enable_time'] == 'on')
				{
					if($booking_settings['booking_enable_multiple_time'] == "multiple" )
					{
						return 'multiple';
					}
				}
			}
			
			function display_price($product_id)
			{
				$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true);
				if(isset($_POST['booking_fixed_block_enable']) && $_POST['booking_partial_payment_radio']!=''):
					$currency_symbol = get_woocommerce_currency_symbol();
					$show_price = 'show';
					print('<div id="show_addon_price" name="show_addon_price" class="show_addon_price" style="display:'.$show_price.';">'.$currency_symbol.' 0</div>');
				endif;
			}

			function show_field_settings($product_id)
			{
				global $post, $wpdb;
				?>
				<script type="text/javascript">
					jQuery(".woo-nav-tab-wrapper").append("<a href=\"javascript:void(0);\" class=\"nav-tab\" id=\"block_booking\" onclick=\"tab_pay_display_2('block_booking')\"> <?php _e( 'Fixed Block Booking', 'woocommerce-booking' );?> </a>");
					function tab_pay_display_2(id){
						 
						jQuery( "#block_booking_page").show();
						jQuery( "#reminder_wrapper" ).hide();
						jQuery( "#payments_page").hide();
						jQuery( "#seasonal_pricing" ).hide();
						jQuery( "#tours_page").hide();
						jQuery( "#rental_page" ).hide();
						jQuery( "#date_time" ).hide();
						jQuery( "#listing_page" ).hide();
						jQuery( "#block_booking_price_page" ).hide();
						jQuery( "#list" ).attr("class","nav-tab");
						jQuery( "#addnew" ).attr("class","nav-tab");
						jQuery( "#tours" ).attr("class","nav-tab");
						jQuery( "#seasonalpricing" ).attr("class","nav-tab");
						jQuery( "#rental" ).attr("class","nav-tab");
						jQuery( "#payments" ).attr("class","nav-tab");
						jQuery( "#reminder" ).attr("class","nav-tab");
						jQuery( "#block_booking_price" ).attr("class","nav-tab");
						jQuery( "#block_booking" ).attr("class","nav-tab nav-tab-active");
				 
					}
				</script>
				<div id="block_booking_page" style="display:none;">
				<table class='form-table'>
					<tr id="seasonal_price">
						<th>
							<label for="booking_seasonl_pricing"><b><?php _e( 'Enable Fixed Block Booking', 'bkap_block_booking');?></b></label>
						</th>
						<td>
							<?php 
							$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
							$enabled_s_pricing = "";
							$add_block_button_show = 'none';
							if(isset($booking_settings['booking_fixed_block_enable'])) $product_enable_seasonal_pricing = $booking_settings['booking_fixed_block_enable'];
							
							if(isset($product_enable_seasonal_pricing) && $product_enable_seasonal_pricing == 'yes')
							{
								$enabled_s_pricing = "checked";
								$add_block_button_show = 'block';
								
							}
							?>
							<input type="checkbox" name="booking_fixed_block_enable" id="booking_fixed_block_enable" value="yes" <?php echo $enabled_s_pricing;?>></input>
							<img class="help_tip" width="16" height="16" data-tip="<?php _e('Enable to allow Fixed Block Pricing for the product.', 'bkap_block_booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png"/>
						</td>
					</tr>
				</table>
				<script type="text/javascript">
					jQuery("#booking_fixed_block_enable").change(function() {
						jQuery("#add_block_button").toggle();
						if(!jQuery('#booking_fixed_block_enable').attr('checked'))
						{
							document.getElementById("add_block").style.display = "none";
						}
						});
				</script>
				<p>
				<div id="add_block_button" name="add_block_button" style="display:<?php echo $add_block_button_show; ?>;">
				<input type="button" class="button-primary" value="Add New Booking Block" id="add_another" onclick="show_div_fixed_blocks()">
				</div>
				</p>
				
				<div id="add_block" name="add_block" style="display:none;">
				<table class="form-table">
				<script type="text/javascript">
							jQuery(document).ready(function()
							{
								var formats = ["d.m.y", "d-m-yyyy","MM d, yy"];
								jQuery("#fixed_block_start_date").datepick({dateFormat: formats[1], monthsToShow: 1, showTrigger: '#calImg'});
							});
							jQuery(document).ready(function()
							{
								var formats = ["d.m.y", "d-m-yyyy","MM d, yy"];
								jQuery("#fixed_block_end_date").datepick({dateFormat: formats[1], monthsToShow: 1, showTrigger: '#calImg'});
							});
				</script>
				<tr>
				 	<label for="add_block_label"><b><?php _e( 'Enter a Block:')?></b></label>
				</tr>
				<tr>
					<table>
					<input type="hidden" name="id_booking_block" value=""/>
					<tr>
						<td><b><label for="fixed_block_name"><?php _e( 'Booking Block Name: ', 'bkap_block_booking');?></b></label></td>
						<td><b><label for="number_of_days" ><?php _e( 'Number of days: ', 'bkap_block_booking');?></b></label></td>
						<td><b><label for="fixed_block_start_date" ><?php _e( 'Start Day: ', 'bkap_block_booking');?></b></label></td>
						<td><b><label for="fixed_block_end_date" ><?php _e( 'End Day: ', 'bkap_block_booking');?></b></label></td>
						<td><b><label for="fixed_block_price" ><?php _e( 'Price: ', 'bkap_block_booking');?></b></label><br></td>
					</tr>
				<tr>
						<td><input type="text" id="booking_block_name" name="booking_block_name"></input></td>
						<td><input type="text" id="number_of_days" name="number_of_days" size="10"></input></td>
						<td><select id="start_day" name="start_day">
						<?php 
						foreach ($this->days as $dkey => $dvalue)
						{
							?>
							<option value="<?php echo $dkey; ?>"><?php echo $dvalue; ?></option>
							<?php 
						}
						?>
					</select></td>
						<td><select id="end_day" name="end_day">
						<?php 
						foreach ($this->days as $dkey => $dvalue)
						{
							?>
							<option value="<?php echo $dkey; ?>"><?php echo $dvalue; ?></option>
							<?php 
						}
						?>
					</select></td>
						<td><input type="text" id="fixed_block_price" name="fixed_block_price" size="10"></input><br>
</td>
						 					
					<input type="hidden" id="table_id" name="table_id"></input><br>
					</tr>

					<tr>
					
					<td>
					<input type="button" class="button-primary" value="Save Block" id="save_another" onclick="save_booking_block()"></input>
					<input type="button" class="button-primary" value="Close" id="close_div" onclick="close_booking_block()"></input></td>
					<td colspan="4"></td>
					</tr>					

					</table>	
				</tr>
				</table>
				</div>
				
				<?php $this->booking_block_table(); ?>
				
				</div>

				<?php
				 
				print('<script type="text/javascript">
				function save_booking_block()
				{
					if (jQuery("#fixed_block_price").val() == "") 
					{
						alert("Price cannot be blank.");
						return;
					}
					var data = {
							post_id: "'.$post->ID.'", 
							booking_block_name: jQuery("#booking_block_name").val(),
							start_day: jQuery("#start_day").val(),
							end_day: jQuery("#end_day").val(),
							price: jQuery("#fixed_block_price").val(),
							number_of_days: jQuery("#number_of_days").val(),
							id: jQuery("#table_id").val(),
							action: "save_booking_block"
							};

							
							jQuery.ajax({
                            url: "'.get_admin_url().'/admin-ajax.php",
                            type: "POST",
                            data : data,
                            dataType: "html",
                            beforeSend: function() {
                             //loading	
                            },
                            success: function(data, textStatus, xhr) {
                                   jQuery("#block_booking_table").html(data);
                                	// reset and hide form
									jQuery("#add_block").hide();
									
									//jQuery("#add_block").closest("form").find("input[type=text], textarea").val("");
									jQuery("#booking_block_name").val("");
									jQuery("#number_of_days").val("");
									jQuery("#fixed_block_price").val("");
								 

                            },
                            error: function(xhr, textStatus, errorThrown) {
                              // error status
                            }
                        });


				}
				

				</script>');
				?>

				<script type="text/javascript">
				
				

				function show_div_fixed_blocks()
				{
			//		jQuery("input[name=booking_seasonal_pricing_radio]").attr("checked",false);
					jQuery("#fixed_block_name").val("");
					jQuery("#fixed_block_start_date").val("");
					jQuery("#fixed_block_end_date").val("");
					jQuery("#fixed_block_price").val("");
			//		jQuery("#booking_seasonal_pricing_operator").val("add");
					jQuery("#table_id").val("");
					document.getElementById("add_block").style.display = "block";
					jQuery("#add_block").show();
				}
				
				function close_booking_block()
				{
					document.getElementById("add_block").style.display = "none";
					jQuery("#add_block").closest("form").find("input[type=text], textarea").val("");
					jQuery("#table_id").val("");

				}
				
				jQuery(document).ready(function(){
					
					jQuery("table#list_blocks").on('click', 'a.edit_block',function()
					{
						var passed_id = this.id;
						var exploded_id = passed_id.split('&');
					//	alert("ID:" + exploded_id);
						jQuery("#booking_block_name").val(exploded_id[3]);
						jQuery("#number_of_days").val(exploded_id[5]);
						jQuery("#start_day").val(exploded_id[1]);
						jQuery("#end_day").val(exploded_id[2]);
						jQuery("#price").val(exploded_id[4]);
						jQuery("#table_id").val(exploded_id[0]);
						//jQuery("#booking_seasonal_pricing_operator").val(exploded_id[5]);

						if (exploded_id[6] == "amount") jQuery("input[id=booking_seasonal_pricing_amount]").attr("checked",true);
						else if (exploded_id[6] == "percent") jQuery("input[id=booking_seasonal_pricing_percent]").attr("checked",true);

						document.getElementById("add_block").style.display = "block";
						jQuery("#add_block").show();
						//alert('test');

					});

					jQuery("table#list_blocks").on('click','a.delete_block',function()
					{
						var y=confirm('Are you sure you want to delete this block?');
						//alert(y);
						if(y==true)
						{
							var passed_id = this.id;
							var data = {
								details: passed_id,
								action: 'delete_block'
							};	
							jQuery.post('<?php echo get_admin_url();?>/admin-ajax.php', data, function(response)
							{
								//alert('Got this from the server: ' + response);
								jQuery("#row_" + passed_id ).hide();
							});
						}
					});
					jQuery("table#list_blocks a.delete_all_blocks").click(function()
					{
						var y=confirm('Are you sure you want to delete all the blocks?');
						if(y==true)
						{
							//var passed_id = this.id;
							//	alert(exploded_id);
							var data = {
								//details: passed_id,
								action: "delete_all_blocks"
							};
							/*jQuery.post('<?php echo get_admin_url();?>/admin-ajax.php', data, function(response)
							{
								//	alert('Got this from the server: ' + response);
								console.log(response);
								jQuery("table#list_blocks").hide();
							});*/

							jQuery.ajax({
                            url: '<?php echo get_admin_url();?>/admin-ajax.php',
                            type: "POST",
                            data : data,

                            // dataType: "html",
                            beforeSend: function() {
                             //loading	

                            },
                            success: function(data, textStatus, xhr) {
								jQuery("table#list_blocks").hide();
								 console.log(data);
                            },
                            error: function(xhr, textStatus, errorThrown) {
                              // error status
                            }
                        });


						}
					});
				});
					
				</script>
				<?php 
			}
			
			function delete_block(){
				global $wpdb;
				echo $_POST['details'];
				$sql="DELETE FROM {$wpdb->prefix}booking_fixed_blocks where id = {$_POST['details']}";
 				echo $sql;
 				$wpdb->query($sql);
				die();
			}

			function delete_all_blocks(){
				global $wpdb;
				$sql="Truncate table wp_booking_fixed_blocks";
				
				$wpdb->query($sql);
				 
				die();

			}


			function save_booking_block()
			{
				// print_r($_POST);exit;
				 
				global $wpdb;
				$post_id = $_POST['post_id'];
				$booking_block_name = $_POST['booking_block_name'];
				$start_day = $_POST['start_day'];
				$end_day = $_POST['end_day'];
				$price = $_POST['price'];
				$number_of_days = $_POST['number_of_days'];

				$id = $_POST['id'];
					
				//$start_season_date = date('Y-m-d',strtotime($start_date));
				//$end_season_date = date('Y-m-d',strtotime($end_date));
			

					
				 
				if ( ($post_id !== "") && ($id == ""))
				{
					
					$insert_booking_block = "INSERT INTO {$wpdb->prefix}booking_fixed_blocks
					(post_id,global_id,block_name,number_of_days,start_day,end_day,price,block_type)
					VALUE(
					'{$post_id}',
					'',
					'{$booking_block_name}',
					'{$number_of_days}',
					'{$start_day}',
					'{$end_day}',
					'{$price}',
					'LOCAL' )";
					$wpdb->query($insert_booking_block);
					$this->booking_block_table();
				}
				else
				{
					
					$edit_season = "UPDATE `".$wpdb->prefix."booking_fixed_blocks`
					SET block_name = '".$booking_block_name."',
					start_day = '".$start_day."',
					end_day = '".$end_day."',
					number_of_days = '".$number_of_days."',
					price = '".$price."'
					WHERE id = '".$id."'";

					$wpdb->query($edit_season);
					//	echo ($insert_season);
					$id = $wpdb->insert_id;
					$this->booking_block_table();
				}
				//echo $id;
				die();
			}
			
			function booking_block_table(){
 				global $post, $wpdb;
 				$post_id='';
 				if (isset($post->ID)) $post_id=$post->ID;
				/* AJAX check  */
				if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
					/* special ajax here */
					//die($content);
					//echo 'this is ajax ='. 
					$post_id=$_POST['post_id'];

				}

				$query = "SELECT * FROM `".$wpdb->prefix."booking_fixed_blocks`
							WHERE post_id = '".$post_id."'";
				 
				$results = $wpdb->get_results($query);

				$var = "";
				$date_name = array("Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");
 
				foreach ($results as $key => $value)
				{
					$var .= '<tr id="row_'.$value->id.'">
							<td>'.$value->block_name.'</td>
							<td>'.$value->number_of_days.'</td>
							<td>'.$date_name[$value->start_day].'</td>
							<td>'.$date_name[$value->end_day].'</td>
							<td>'.$value->price.'</td>
							<td> <a href="javascript:void(0);" id="'.$value->id.'&'.$value->start_day.'&'.$value->end_day.'&'.$value->block_name.'&'.$value->price.'&'.$value->number_of_days.'" class="edit_block"> <img src="'.plugins_url().'/woocommerce-booking/images/edit.png" alt="Edit Season" title="Edit Season"></a> </td>
							<td> <a href="javascript:void(0);" id="'.$value->id.'" class="delete_block"> <img src="'.plugins_url().'/woocommerce-booking/images/delete.png" alt="Delete Season" title="Delete Season"></a> </td>
							</tr>';
				}
				?>
				<div id="block_booking_table">
					<p>
					<table class='wp-list-table widefat fixed posts' cellspacing='0' id='list_blocks'>
						<tr>
							<b>Booking Blocks</b>
						</tr>	
						<tr>
							<th> <?php _e('Block Name', 'bkap_block_booking');?> </th>
							<th> <?php _e('Number of Days', 'bkap_block_booking');?></th>
							<th> <?php _e('Start Day', 'bkap_block_booking');?> </th>
							<th> <?php _e('End Day', 'bkap_block_booking');?> </th>
							<th> <?php _e('Price', 'bkap_block_booking');?> </th>
							<th> <?php _e('Edit', 'bkap_block_booking');?> </th>
							<?php print('<th> <a href="javascript:void(0);" id="'.$post_id.'" class="delete_all_blocks"> Delete All </a> </th>');	?>  
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
			}

			function product_settings_save($booking_settings, $product_id)
			{
				if(isset($_POST['booking_fixed_block_enable']))
				{
					$booking_settings['booking_fixed_block_enable'] = $_POST['booking_fixed_block_enable'];
				}
				return $booking_settings;
			}
			
			function add_cart_item_data($cart_arr, $product_id)
			{
				$currency_symbol = get_woocommerce_currency_symbol();
				$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true);

				$fixed_blocks_count = $this->get_fixed_blocks_count($product_id);
				if(isset($booking_settings['booking_fixed_block_enable']) && $booking_settings['booking_fixed_block_enable'] == "yes" && $fixed_blocks_count > 0)
				{
					$product = get_product($product_id);
					$product_type = $product->product_type;
					
					if ( $product_type == 'variable')
					{
						$price = get_post_meta(  $_POST['variation_id'], '_sale_price', true);
						if($price == '')
						{
							$price = get_post_meta(  $_POST['variation_id'], '_regular_price', true);
						}
					}
					elseif($product_type == 'simple')
					{
						$price = get_post_meta( $product_id, '_sale_price', true);
						if($price == '')
						{
							$price = get_post_meta( $product_id, '_regular_price', true);
						}
					}
					$date_disp = $_POST['booking_calender'];
					if(isset($_POST['timeslot']))
						$time_multiple_disp = $_POST['timeslot'];
					else
						$time_multiple_disp = '';

					$hidden_date = $_POST['wapbk_hidden_date'];
					if (isset($booking_settings['booking_fixed_block_enable']) && $booking_settings['booking_fixed_block_enable'] == "yes" && isset($cart_arr['price'])) :
						//$price =0;//$cart_arr['price'];
						//$price = $_POST['block_option_price'];// $cart_arr['price'];
                                            if($product_type == 'variable'){ //Make this chnage
                                                $price = $price + $_POST['block_option_price'];// $cart_arr['price'];
                                            }
                                            else{
                                                $price =0;//$cart_arr['price'];
						$price = $_POST['block_option_price'];// $cart_arr['price'];
                                            }
                                            $diff_days=1; 
					else:
						$cart_arr['price'] = $price;
					endif;

					if (isset($booking_settings['booking_enable_multiple_day']) && $booking_settings['booking_enable_multiple_day'] == 'on')
					{	
						$diff_days = $_POST['wapbk_diff_days'];
						if (isset($booking_settings['booking_fixed_block_enable'])&& $booking_settings['booking_fixed_block_enable'] == "yes") 
						{
							$diff_days=1; 
							$total = $price * $diff_days;
							//echo $_POST['payment_type'];exit;
							if(isset($booking_settings['booking_partial_payment_enable']) && $booking_settings['booking_partial_payment_enable'] =='yes')
							{
								if(isset($booking_settings['allow_full_payment']) && $booking_settings['allow_full_payment'] == "yes")
								{
									if ($_POST['payment_type']=="partial_payment")
									{
										if(isset($booking_settings['booking_partial_payment_radio']) && $booking_settings['booking_partial_payment_radio']=='value')
										{
											$deposit = $booking_settings['booking_partial_payment_value_deposit'];
											//echo $deposit;
											$rem = $total-$deposit;
											$cart_arr['Total'] = $total;
											$cart_arr['Remaining'] = $rem;
											$cart_arr['Deposit'] = $deposit;
											$cart_arr ['price'] = $deposit;
										}
										elseif(isset($booking_settings['booking_partial_payment_radio']) &&		$booking_settings['booking_partial_payment_radio']=='percent')
										{
											$deposit = $total * ($booking_settings['booking_partial_payment_value_deposit']/100);
											//echo $deposit;
											//echo $total;
											$rem = $total-$deposit;
											$cart_arr ['price'] = $deposit;
											$cart_arr['Total'] = $total;
											$cart_arr['Remaining'] = $rem;
											$cart_arr['Deposit'] = $deposit;
										}
									}
									else if (isset($_POST['payment_type']) && $_POST['payment_type']=="full_payment")
									{
										$cart_arr ['price'] = $total;
										$cart_arr['Total'] = $total;
										$cart_arr['Remaining'] =0;
										$cart_arr['Deposit'] = $total;
									}
									//print_r($cart_arr);exit;
								}
								else
								{
									if(isset($booking_settings['booking_partial_payment_radio']) && $booking_settings['booking_partial_payment_radio']=='value')
									{
										$deposit = $booking_settings['booking_partial_payment_value_deposit'];
										$rem = $total-$deposit;
										$cart_arr['Total'] = $total;
										$cart_arr['Remaining'] = $rem;
										$cart_arr['Deposit'] = $deposit;
										$cart_arr ['price'] = $deposit;
									}
									elseif(isset($booking_settings['booking_partial_payment_radio']) &&		$booking_settings['booking_partial_payment_radio']=='percent')
									{
										$deposit = $total * ($booking_settings['booking_partial_payment_value_deposit']/100);
										$rem = $total-$deposit;
										$cart_arr ['price'] = $deposit;
										$cart_arr['Total'] = $total;
										$cart_arr['Remaining'] = $rem;
										$cart_arr['Deposit'] = $deposit;
									}
								}
							}
							else
							{
								$cart_arr ['price'] = $total;
							}
						}
						else
						{
							$cart_arr['price'] = $price * $diff_days;
						}
						//print_r($cart_arr);exit;
					}
					else
					{
						if (isset($booking_settings['booking_fixed_block_enable']) && $booking_settings['booking_fixed_block_enable'] == "yes")
						{
							$total = $price ;
							if(isset($booking_settings['booking_partial_payment_enable']) && $booking_settings['booking_partial_payment_enable'] =='on')
							{
								if(isset($booking_settings['allow_full_payment']) && $booking_settings['allow_full_payment'] == "yes")
								{
									if ($_POST['payment_type']=="partial_payment")
									{
										if(isset($booking_settings['booking_partial_payment_radio']) && $booking_settings['booking_partial_payment_radio']=='value')
										{
											$deposit = $booking_settings['booking_partial_payment_value_deposit'];
											$rem = $total-$deposit;
											$cart_arr['Total'] = $total;
											$cart_arr['Remaining'] = $rem;
											$cart_arr['Deposit'] = $deposit;
											$cart_arr ['price'] = $deposit;
										}
										elseif(isset($booking_settings['booking_partial_payment_radio']) &&		$booking_settings['booking_partial_payment_radio']=='percent')
										{
											$deposit = $total * ($booking_settings['booking_partial_payment_value_deposit']/100);
											$rem = $total-$deposit;
											$cart_arr ['price'] = $deposit;
											$cart_arr['Total'] = $total;
											$cart_arr['Remaining'] = $rem;
											$cart_arr['Deposit'] = $deposit;
										}
									}
									else if (isset($_POST['payment_type']) && $_POST['payment_type']=="full_payment")
									{
										$cart_arr ['price'] = $total;
										$cart_arr['Total'] = $total;
										$cart_arr['Remaining'] =0;
										$cart_arr['Deposit'] = $total;
									}
								}
								else
								{
									if(isset($booking_settings['booking_partial_payment_radio']) && $booking_settings['booking_partial_payment_radio']=='value')
									{
										$deposit = $booking_settings['booking_partial_payment_value_deposit'];
										$rem = $total-$deposit;
										$cart_arr['Total'] = $total;
										$cart_arr['Remaining'] = $rem;
										$cart_arr['Deposit'] = $deposit;
										$cart_arr ['price'] = $deposit;
									}
									elseif(isset($booking_settings['booking_partial_payment_radio']) &&		$booking_settings['booking_partial_payment_radio']=='percent')
									{
										$deposit = $total * ($booking_settings['booking_partial_payment_value_deposit']/100);
										$rem = $total-$deposit;
										$cart_arr ['price'] = $deposit;
										$cart_arr['Total'] = $total;
										$cart_arr['Remaining'] = $rem;
										$cart_arr['Deposit'] = $deposit;
									}
								}
							}
							else
							{
								$cart_arr ['price'] = $total;
							}
						}
						else
						{
							$cart_arr ['price'] = $price;
						}
					}
					$global_settings = json_decode(get_option('woocommerce_booking_global_settings'));
					if (isset($global_settings->enable_rounding) && $global_settings->enable_rounding == "on")
					{
						$cart_arr['price'] = round($cart_arr['price']);
						if(isset(	$cart_arr['Total']))
							$cart_arr['Total'] = round($cart_arr['Total']);
						if(isset(	$cart_arr['Deposit']))
							$cart_arr['Deposit'] = round($cart_arr['Deposit']);
						if(isset(	$cart_arr['Remaining']))
							$cart_arr['Remaining'] = round($cart_arr['Remaining']);	
					}
				}
				//print_r($cart_arr);
				return $cart_arr;
			}
			
			function get_cart_item_from_session( $cart_item, $values ) 
			{
				$booking_settings = get_post_meta($cart_item['product_id'], 'woocommerce_booking_settings', true);
				if(isset($booking_settings['booking_fixed_block_enable']) && $booking_settings['booking_fixed_block_enable'] == 'yes '&&is_plugin_active('bkap-deposits/deposits.php'))
				{
					if (isset($values['booking'])) :
					$cart_item['booking'] = $values['booking'];
					
					if($cart_item['booking'][0]['date'] != '')
					{
						if(isset($booking_settings['booking_fixed_block_enable']))
						{
							$cart_item = $this->add_cart_item( $cart_item );
						}
					}
					endif;
				}
				return $cart_item;
			}
			
			function add_cart_item( $cart_item ) 
			{
				// Adjust price if addons are set

				if (isset($cart_item['booking'])) :
					$extra_cost = 0;
					foreach ($cart_item['booking'] as $addon) :
							if (isset($addon['price']) && $addon['price']>0) $extra_cost += $addon['price'];
					endforeach;
								
					$cart_item['data']->set_price($extra_cost);
					
				endif;
				return $cart_item;
			}

			function get_item_data( $other_data, $cart_item ) 
			{
				$global_settings = json_decode(get_option('woocommerce_booking_global_settings'));
				$booking_settings = get_post_meta( $cart_item['product_id'], 'woocommerce_booking_settings', true);
				if(isset($booking_settings['booking_fixed_block_enable']) && $booking_settings['booking_fixed_block_enable'] == 'yes')
				{
					if(isset($booking_settings['booking_partial_payment_enable']) && $booking_settings['booking_partial_payment_enable'] =='yes' &&  is_plugin_active('bkap-deposits/deposits.php'))
					{
						$currency_symbol = get_woocommerce_currency_symbol();
						if (isset($cart_item['booking'])) 
						{
							$price = '';
							foreach ($cart_item['booking'] as $booking) 
							{
								if(isset($booking_settings['booking_partial_payment_radio']))
								{
									if(isset($cart_item['quantity']))
									{
										if (isset($global_settings->enable_rounding) && $global_settings->enable_rounding == "on")
										{
											$booking['Total'] = round($booking['Total'] * $cart_item['quantity']);
											$booking['Deposit'] = round($booking['Deposit'] * $cart_item['quantity']);
											$booking['Remaining'] = round($booking['Remaining'] * $cart_item['quantity']);
										}
										else
										{
											$booking['Total'] = $booking['Total'] * $cart_item['quantity'];
											$booking['Deposit'] = $booking['Deposit'] * $cart_item['quantity'];
											$booking['Remaining'] = $booking['Remaining'] * $cart_item['quantity'];
										}
									}
								}
								$price .= "<br> ".book_t('book.item-partial-total').": $currency_symbol".$booking['Total']."<br> ".book_t('book.item-partial-deposit').": $currency_symbol".$booking['Deposit']."<br>".book_t('book.item-partial-remaining').": 
								$currency_symbol".$booking['Remaining'];
							}
						}
						$other_data[] = array(
						'name'    => book_t('book.partial-payment-heading'),
						'display' => $price
					);
					}
				}
				return $other_data;
			}
			
			function order_item_meta( $values,$order) 
			{
				global $wpdb;
				$currency_symbol = get_woocommerce_currency_symbol();
				$product_id = $values['product_id'];
				$quantity = $values['quantity'];
				$booking = $values['booking'];
				$order_item_id = $order->order_item_id;
				$order_id = $order->order_id;
				$global_settings = json_decode(get_option('woocommerce_booking_global_settings'));
				$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true);
				if (isset($booking_settings['booking_fixed_block_enable']) && isset($booking_settings['booking_partial_payment_radio']) && is_plugin_active("bkap-deposits/deposits.php")){

					if (isset($global_settings->enable_rounding) && $global_settings->enable_rounding == "on")
					{
						woocommerce_add_order_item_meta($order_item_id,  book_t('book.item-partial-total'), $currency_symbol.round($values['booking'][0]['Total'] *$values['quantity']), true );
						woocommerce_add_order_item_meta($order_item_id,  book_t('book.item-partial-deposit'), $currency_symbol.round($values['booking'][0]['Deposit']* $values['quantity']), true );
						woocommerce_add_order_item_meta($order_item_id,  book_t('book.item-partial-remaining'), $currency_symbol.round($values['booking'][0]['Remaining']* $values['quantity']), true );
					}
					else
					{
						woocommerce_add_order_item_meta($order_item_id,  book_t('book.item-partial-total'), $currency_symbol.$values['booking'][0]['Total'] *$values['quantity'], true );
						woocommerce_add_order_item_meta($order_item_id,  book_t('book.item-partial-deposit'), $currency_symbol.$values['booking'][0]['Deposit']* $values['quantity'], true );
						woocommerce_add_order_item_meta($order_item_id,  book_t('book.item-partial-remaining'), $currency_symbol.$values['booking'][0]['Remaining']* $values['quantity'], true );
					}
				}
			}

			function show_updated_price($product_id,$variation_id,$oprice)
			{
				$product = get_product($product_id);
				$product_type = $product->product_type;
				
				if ($product_type == 'variable')
				{
					$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
				
					if (isset($booking_settings['booking_fixed_block_enable']) && $booking_settings['booking_fixed_block_enable']  == "yes")
					{
						if (isset($booking_settings['booking_partial_payment_enable']) && $booking_settings['booking_partial_payment_enable']== "yes")
						{
							if(isset($booking_settings['booking_partial_payment_radio']) && $booking_settings['booking_partial_payment_radio']=='value')
							{
								$price = $booking_settings['booking_partial_payment_value_deposit'];
							}
							elseif(isset($booking_settings['booking_partial_payment_radio']) && $booking_settings['booking_partial_payment_radio']=='percent')
							{
								$price = (($booking_settings['booking_partial_payment_value_deposit']*$oprice)/100);
							
							}
						}
						else
						{
                                                        $price = get_post_meta(  $variation_id, '_sale_price', true);
                                                        if($price == '')
                                                        {
                                                                $price = $oprice + get_post_meta(  $variation_id, '_regular_price', true);

                                                        }
							//$price = $oprice;
						}
					}
					else
					{
						$sale_price = get_post_meta( $variation_id, '_sale_price', true);
						if($sale_price == '')
						{
							$regular_price = get_post_meta( $variation_id, '_regular_price', true);
							$price = (($booking_settings['booking_partial_payment_value_deposit']*$regular_price)/100);
						}
						else
						{
							$price = (($booking_settings['booking_partial_payment_value_deposit']*$sale_price)/100);
						}
					}
					echo $price;
					die();		
				}
				elseif ($product_type == 'simple')
				{
					$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
					if (isset($booking_settings['booking_fixed_block_enable']) && $booking_settings['booking_fixed_block_enable']  == "yes")
					{
						$oprice = $_POST['block_option_price'];
						//echo "here".$oprice;
						if (isset($booking_settings['booking_partial_payment_enable']) && $booking_settings['booking_partial_payment_enable']== "yes")
						{
							if(isset($booking_settings['booking_partial_payment_radio']) && $booking_settings['booking_partial_payment_radio']=='value')
							{
								$price = $booking_settings['booking_partial_payment_value_deposit'];
							}
							elseif(isset($booking_settings['booking_partial_payment_radio']) && $booking_settings['booking_partial_payment_radio']=='percent')
							{
								$price = (($booking_settings['booking_partial_payment_value_deposit']*$oprice)/100);
							
							}
						}
						else
						{
							$price = $oprice;
						}
					}
					else
					{
						$sale_price = get_post_meta( $product_id, '_sale_price', true);
						if($sale_price == '')
						{
							$regular_price = get_post_meta( $product_id, '_regular_price', true);
							$price = (($booking_settings['booking_partial_payment_value_deposit']*$regular_price)/100);
						}
						else
						{
							$price = (($booking_settings['booking_partial_payment_value_deposit']*$sale_price)/100);
						}
					}
					echo $price;
					die();
				}
			}
			

			function get_fixed_blocks_count($post_id)
			{
				global $wpdb;
				$query = "SELECT * FROM `".$wpdb->prefix."booking_fixed_blocks` WHERE post_id = '".$post_id."'";
				$results = $wpdb->get_results($query);
				
				return count($results);
			}
			
			function get_fixed_blocks($post_id)
			{
				global $wpdb;
				$query = "SELECT * FROM `".$wpdb->prefix."booking_fixed_blocks` WHERE post_id = '".$post_id."'";
				$results = $wpdb->get_results($query);
			
				return $results;
			}
		}
	}
	$bkap_block_booking = new bkap_block_booking();
 
?>