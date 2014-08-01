<?php 
 
 
	/**
	 * Localisation
	 **/
	load_plugin_textdomain('bkap_block_booking_price', false, dirname( plugin_basename( __FILE__ ) ) . '/');

	/**
	 * bkap_deposits class
	 **/
	if (!class_exists('bkap_block_booking_price')) {

		class bkap_block_booking_price {

			public function __construct() {
				// Initialize settings
				//register_activation_hook( __FILE__, array(&$this, 'block_booking_activate'));
				
				// used to add new settings on the product page booking box
				add_action('bkap_after_listing_enabled', array(&$this, 'show_field_settings'));
				add_action('init', array(&$this, 'load_ajax'));
				add_filter('bkap_save_product_settings', array(&$this, 'product_settings_save'), 10, 2);
				add_action('bkap_display_block_updated_price', array(&$this, 'show_updated_price'),10,5);
				add_filter('bkap_add_cart_item_data', array(&$this, 'add_cart_item_data'), 10, 2);
				add_filter('bkap_get_cart_item_from_session', array(&$this, 'get_cart_item_from_session'),11,2);
				//add_action( 'woocommerce_before_add_to_cart_form', array(&$this, 'before_add_to_cart'));
				add_action( 'woocommerce_before_add_to_cart_button', array(&$this, 'booking_after_add_to_cart'));	
			//	add_filter('bkap_get_item_data', array(&$this, 'get_item_data'), 10, 2 );
				add_action('bkap_deposits_update_order', array(&$this, 'order_item_meta'), 10,2);
				//add_action('bkap_display_price_div', array(&$this, 'display_price'),10,1);
			}
				
			function load_ajax()
			{
				if ( !is_user_logged_in() )
				{
					add_action('wp_ajax_nopriv_save_booking_block_price',  array(&$this,'save_booking_block_price'));
					add_action('wp_ajax_nopriv_show_price_div',  array(&$this,'show_price_div'));
					add_action('wp_ajax_nopriv_booking_block_price_table',  array(&$this,'booking_block_price_table'));
					add_action('wp_ajax_nopriv_delete_price_block',  array(&$this,'delete_price_block'));
					add_action('wp_ajax_nopriv_delete_all_price_blocks',  array(&$this,'delete_all_price_blocks'));
					//add_action('wp_ajax_nopriv_save_global_season',  array(&$this,'save_global_season'));
					//add_action('wp_ajax_nopriv_delete_global_season',  array(&$this,'delete_global_season'));
					//add_action('wp_ajax_nopriv_delete_all_global_seasons',  array(&$this,'delete_all_global_seasons'));
				}
				else
				{
					add_action('wp_ajax_save_booking_block_price',  array(&$this,'save_booking_block_price'));
					add_action('wp_ajax_show_price_div',  array(&$this,'show_price_div'));
					add_action('wp_ajax_booking_block_price_table',  array(&$this,'booking_block_price_table'));
					add_action('wp_ajax_delete_price_block',  array(&$this,'delete_price_block'));
					add_action('wp_ajax_delete_all_price_blocks',  array(&$this,'delete_all_price_blocks'));
					//add_action('wp_ajax_save_global_season',  array(&$this,'save_global_season'));
					//add_action('wp_ajax_delete_global_season',  array(&$this,'delete_global_season'));
					//add_action('wp_ajax_delete_all_global_seasons',  array(&$this,'delete_all_global_seasons'));
				}
			}

			function booking_after_add_to_cart()
			{	
				global $post, $wpdb;
 				$booking_settings = get_post_meta($post->ID, 'woocommerce_booking_settings', true);

				if (isset($booking_settings['booking_block_price_enable']) && $booking_settings['booking_block_price_enable'] == 'yes' ) 
				{
					echo ' <input type="hidden" id="block_option_enabled_price"  name="block_option_enabled_pric" value="on"/>';
					echo ' <input type="hidden" id="block_variable_option_price"  name="block_variable_option_price" value=""/>';
					echo ' <input type="hidden" id="wapbk_variation_value"  name="wapbk_variation_value" value=""/>';
				}	
				else
				{
					echo ' <input type="hidden" id="block_option_enabled_price"  name="block_option_enabled_price" value=""/>';
				}

			}
			/*function display_price($product_id)
			{
				$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true);
				if(isset($booking_settings['booking_block_price_enable']))
				{
					$currency_symbol = get_woocommerce_currency_symbol();
					$show_price = 'show';
					print('<div id="show_addon_price" name="show_addon_price" class="show_addon_price" style="display:'.$show_price.';">'.$currency_symbol.' 0</div>');
				}
			}*/
			function show_price_div()
			{
				$div = ' 
				<table class="form-table">
				<tr>
				 	<label for="add_block_label"><b>'.__("Enter a Block:","bkap_block_booking_price").'</b></label>
				</tr>
				<tr>
					<table>
					<input type="hidden" name="id_booking_block" value=""/>
					<tr>';
					$product_id = $_POST['post_id'];
					$product_attributes = get_post_meta($product_id, '_product_attributes', true);
					$i = 1;
					if($product_attributes != '')
					{
						foreach($product_attributes as $key => $value)
						{
							$div .= '<td><b><label for="attribute_'.$i.'_name">'.__( $value["name"] , "bkap_block_booking_price").'</b></label></td>';
							$i++;
						}
					}
					 $div .= '<td><b><label for="number_of_start_days">'.__( "Minimum Number of days: ", "bkap_block_booking_price").'</b></label></td>
						<td><b><label for="number_of_end_days" >'.__( "Maximum Number of days: ", "bkap_block_booking_price").'</b></label></td>
						<td><b><label for="price_per_day" >'.__( "Price Per Day: ", "bkap_block_booking_price").'</b></label><br></td>
						<td><b><label for="fixed_price" >'.__( "Fixed Price: ", "bkap_block_booking_price").'</b></label><br></td>
					</tr>
					<tr>';
					$i = 1;
					$j = 1;
					if($product_attributes != '')
					{
					foreach($product_attributes as $key => $value)
					{
						if(isset($_POST['attributes']) &&  $_POST['attributes'] != '')
						{
							$attributes = explode("|",$_POST['attributes']);
							array_pop($attributes);
							//print_r($attributes);
							$div .= '<td><select name="attribute_'.$i.'" id="attribute_'.$i.'" value="">';
							$value_array = explode('|',$value['value']);
							//print_r($value_array);
							foreach($value_array as $k => $v)
							{	
								//echo $v;
								if(substr($v,-1,1) === ' ')
								{
									$result = rtrim($v," ");
									//echo "here".$result;
								}
								else if (substr($v,0,1) === ' ')
								{
									$result = preg_replace("/ /","",$v,1);
								}
								else
								{
									$result =  $v;
								}
								if(array_key_exists($j-1,$attributes) && $result == $attributes[$j-1])
								{
									$div .= '<option name="attribute_'.$i.'_'.$j.'" id="attribute_'.$i.'_'.$j.'" value="'.$result.'" selected="selected">'.$result.'</option>';
								}
								else
								{
									$div .= '<option name="attribute_'.$i.'_'.$j.'" id="attribute_'.$i.'_'.$j.'" value="'.$result.'">'.$result.'</option>';
								}
							}
							$div .= '</select></td>';
						}
						else
						{
							$div .= '<td><select name="attribute_'.$i.'" id="attribute_'.$i.'" value="">';
							$value_array = explode('|',$value['value']);
							$j = 1;
							foreach($value_array as $k => $v)
							{	
								$div .= '<option name="attribute_'.$i.'_'.$j.'" id="attribute_'.$i.'_'.$j.'" value="'.$v.'">'.$v.'</option>';
							}
							$div .= '</select></td>';
						}
						$i++;
						$j++;
					}
					}
					if(isset($_POST["number_of_start_days"]) &&  $_POST["number_of_start_days"] != '')
					{
						$div .= '<td><input type="text" id="number_of_start_days" name="number_of_start_days" value="'.$_POST["number_of_start_days"].'"></input></td>';
					}
					else
					{
						$div .= '<td><input type="text" id="number_of_start_days" name="number_of_start_days" value=""></input></td>';
					}
					if(isset($_POST["number_of_end_days"]) &&  $_POST["number_of_end_days"] != '')
					{
						$div .= '<td><input type="text" id="number_of_end_days" name="number_of_end_days" size="10" value="'.$_POST["number_of_end_days"].'"></input></td>';
					}
					else
					{
						$div .= '<td><input type="text" id="number_of_end_days" name="number_of_end_days" size="10" value=""></input></td>';
					}
					if(isset($_POST["price_per_day"]) &&  $_POST["price_per_day"] != '')
					{
						$div .= '<td><input type="text" id="price_per_day" name="price_per_day" size="10" value="'.$_POST["price_per_day"].'"></input><br></td>';
					}
					else
					{
						$div .= '<td><input type="text" id="price_per_day" name="price_per_day" size="10" value=""></input><br></td>';
					}
					if(isset($_POST["fixed_price"]) &&  $_POST["fixed_price"] != '')
					{
						$div .= '<td><input type="text" id="fixed_price" name="fixed_price" size="10" value="'.$_POST["fixed_price"].'"></input><br></td>';
					}
					else
					{
						$div .= '<td><input type="text" id="fixed_price" name="fixed_price" size="10" value=""></input><br></td>';
					}
					if(isset($_POST['id']) && $_POST['id'] != '')
					{
						$div .= '<input type="hidden" id="table_id" name="table_id" value="'.$_POST['id'].'"></input><br>';
					}
					else
					{
						$div .= '<input type="hidden" id="table_id" name="table_id"></input><br>';
					}
					$div .= '<input type="hidden" id="attribute_count" name="attribute_count" value="'.count($product_attributes).'"></input></td>
					</tr>
					<tr>
						<td>
							<input type="button" class="button-primary" value="Save Block" id="save_another" onclick="save_booking_block_price()"></input>
							<input type="button" class="button-primary" value="Close" id="close_div" onclick="close_booking_block()"></input>
						</td>
						<td colspan="4"></td>
					</tr>					
					</table>	
				</tr>
				</table>';
				echo $div;
				die();
			}

			function show_field_settings($product_id)
			{
				global $post, $wpdb;
				?>
				<script type="text/javascript">
					jQuery(".woo-nav-tab-wrapper").append("<a href=\"javascript:void(0);\" class=\"nav-tab\" id=\"block_booking_price\" onclick=\"tab_pay_display_3('block_booking_price')\"> <?php _e( 'Price by range of days', 'woocommerce-booking' );?> </a>");
					function tab_pay_display_3(id){
						 
						jQuery( "#block_booking_price_page").show();
						jQuery( "#payments_page").hide();
						jQuery( "#seasonal_pricing" ).hide();
						jQuery( "#tours_page").hide();
						jQuery( "#date_time" ).hide();
						jQuery( "#listing_page" ).hide();
						jQuery( "#block_booking_page").hide();
						jQuery( "#rental_page").hide();
						jQuery( "#list" ).attr("class","nav-tab");
						jQuery( "#addnew" ).attr("class","nav-tab");
						jQuery( "#tours" ).attr("class","nav-tab");
						jQuery( "#seasonalpricing" ).attr("class","nav-tab");
						jQuery( "#payments" ).attr("class","nav-tab");
						jQuery( "#rental" ).attr("class","nav-tab");
						jQuery( "#block_booking" ).attr("class","nav-tab");
						jQuery( "#block_booking_price" ).attr("class","nav-tab nav-tab-active");
				 
					}
				</script>
				<div id="block_booking_price_page" style="display:none;">
				<table class='form-table'>
					<tr id="block_price">
						<th>
							<label for="booking_block_price"><b><?php _e( 'Enable Price by range of days', 'bkap_block_booking_price');?></b></label>
						</th>
						<td>
							<?php 
							$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
							$enabled_s_pricing = "";
							$add_season_button_show = 'none';
							if(isset($booking_settings['booking_block_price_enable'])) 
							{
								$product_enable_block_price = $booking_settings['booking_block_price_enable'];
							}
							else
							{
								$product_enable_block_price = '';
							}
							//echo $product_enable_block_price;exit;
							if($product_enable_block_price == 'yes')
							{
								$enabled_s_pricing = "checked";
								$add_season_button_show = 'block';	
							}
							?>
							<input type="checkbox" name="booking_block_price_enable" id="booking_block_price_enable" value="yes" <?php echo $enabled_s_pricing;?>></input>
							<img class="help_tip" width="16" height="16" data-tip="<?php _e('Enable to charge by range of days.', 'bkap_block_booking_price');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png"/>
						</td>
					</tr>
				</table>
				<script type="text/javascript">
					jQuery("#booking_block_price_enable").change(function() {
						jQuery("#add_block_price_button").toggle();
						});
				</script>
				<p>
				<div id="add_block_price_button" name="add_block_price_button" style="display:<?php echo $add_season_button_show; ?>;">
				<input type="button" class="button-primary" value="Add New Range" id="add_another_block_price" onclick="show_price_div()">
				</div>
				</p>
				<div id="add_block_price" name="add_block_price"></div>
				
				
				<?php $this->booking_block_price_table(); ?>
				
				</div>

				<?php
				print('<script type="text/javascript">
				function save_booking_block_price()
				{
					if (jQuery("#price_per_day").val() == "" && jQuery("#fixed_price").val() == "") 
					{
						alert("Both Price cannot be blank.");
						return;
					}
					else if(parseInt(jQuery("#number_of_start_days").val()) == "" || parseInt(jQuery("#number_of_end_days").val()) == "")
					{
						alert("Please enter a valid date range.");
						return;
					}
					else if(parseInt(jQuery("#number_of_start_days").val()) > parseInt(jQuery("#number_of_end_days").val()))
					{
						alert("Minimum number of days should be less than the Maximum number of days.");
						return;
					}
					var attribute_count = parseInt(jQuery("#attribute_count").val());
					var attributes = "";
					for (i = 1; i <= attribute_count; i++)
					{
						var attribute_value = (jQuery("#attribute_"+i).val()).trim();
						var block_attribute = attribute_value +"|";
						attributes = attributes + block_attribute;
					}	

					//var option = jQuery($this.id+" option:selected").attr("value");
					//alert(option);
					var data = {
							post_id: "'.$post->ID.'",
							attribute: attributes,
							number_of_start_days: jQuery("#number_of_start_days").val(),
							number_of_end_days: jQuery("#number_of_end_days").val(),
							price_per_day: jQuery("#price_per_day").val(),
							fixed_price: jQuery("#fixed_price").val(),
							id: jQuery("#table_id").val(),
							action: "save_booking_block_price"
							};
	
							jQuery.ajax({
                            url: "'.get_admin_url().'admin-ajax.php",
                            type: "POST",
                            data : data,
                            dataType: "html",
                            beforeSend: function() {
                             //loading	
                            },
                            success: function(data, textStatus, xhr) {
                                   jQuery("#block_price_booking_table").html(data);
                                	// reset and hide form
									jQuery("#add_block_price").hide();
									//jQuery("#add_block_price").closest("form").find("input[type=text], textarea").val("");
									jQuery("#number_of_start_days").val("");
									jQuery("#number_of_end_days").val("");
									jQuery("#price_per_day").val("");
									jQuery("#fixed_price").val("");
                            },
                            error: function(xhr, textStatus, errorThrown) {
                              // error status
                            }
                        });		
				}
				function show_price_div()
				{
					jQuery( "#ajax_img" ).show();
					var data = {
						post_id: "'.$post->ID.'", 
						id: jQuery("#table_id").val(),
						action: "show_price_div"
						};
						jQuery.post("'.get_admin_url().'/admin-ajax.php", data, function(response)
						{
							//alert("Got this from the server: " + response);
							jQuery( "#ajax_img" ).hide();
							jQuery("#add_block_price").show();
							jQuery("#add_block_price").html(response);
						});	
				}

				</script>');
				?>

				<script type="text/javascript">
				function close_booking_block()
				{
					//document.getElementById("add_block_price").style.display = "none";
					//jQuery("#add_block_price").closest("form").find("input[type=text], textarea").val("");
					jQuery("#add_block_price").hide("");
					jQuery("#table_id").val("");

				}
				
				jQuery(document).ready(function()
				{
					jQuery("table#list_block_price").on('click', '.edit_block',function()
					{
						var passed_id = this.id;
						var exploded_id = passed_id.split('&');
						var attribute_count = parseInt(jQuery("#attribute_count").val());
						var attributes = "";
						var n = 3 + parseInt(exploded_id[1]);
						for (i = 3; i < n ; i++)
						{
							var attribute_value = exploded_id[i].trim();
							var block_attribute = attribute_value+"|";
							attributes = attributes + block_attribute;
						}	
						
						var number_of_start_days = exploded_id[i];
						var number_of_end_days = exploded_id[i+1];
						var price_per_day = exploded_id[i+2];
						var fixed_price = exploded_id[i+3];
						jQuery( "#ajax_img" ).show();
						var post_id = exploded_id[2];
						var data = {
							post_id: post_id,
							attributes: attributes,
							id: exploded_id[0],
							number_of_start_days: number_of_start_days,
							number_of_end_days: number_of_end_days,
							price_per_day: price_per_day,
							fixed_price: fixed_price,
							action: "show_price_div"
						};
						jQuery.post('<?php echo get_admin_url();?>admin-ajax.php', data, function(response)
						{
							//alert("Got this from the server: " + response);
							jQuery("#ajax_img" ).hide();
							jQuery("#add_block_price").show();
							jQuery("#add_block_price").html(response);
						});	
					});

					jQuery("table#list_block_price").on('click','a.delete_price_block',function()
					{
						var y=confirm('Are you sure you want to delete this block?');
						if(y==true)
						{
							var passed_id = this.id;
							var data = {
								details: passed_id,
								action: 'delete_price_block'
							};
								
							jQuery.post('<?php echo get_admin_url();?>admin-ajax.php', data, function(response)
							{
								// 	alert('Got this from the server: ' + response);
								jQuery("#row_" + passed_id ).hide();
							});
						}
					});
					jQuery("table#list_block_price a.delete_all_price_blocks").click(function()
					{
						var y=confirm('Are you sure you want to delete all the blocks?');
						if(y==true)
						{
							//var passed_id = this.id;
							//	alert(exploded_id);
							var data = {
								//details: passed_id,
								action: "delete_all_price_blocks"
							};

							 
								
							/*jQuery.post('<?php echo get_admin_url();?>/admin-ajax.php', data, function(response)
							{
								//	alert('Got this from the server: ' + response);
								console.log(response);
								jQuery("table#list_seasons").hide();
							});*/

							jQuery.ajax({
                            url: '<?php echo get_admin_url();?>admin-ajax.php',
                            type: "POST",
                            data : data,

                            // dataType: "html",
                            beforeSend: function() {
                             //loading	

                            },
                            success: function(data, textStatus, xhr) {
								jQuery("table#list_block_price").hide();
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
			
			function delete_price_block()
			{
				global $wpdb;
				$sql="DELETE FROM {$wpdb->prefix}booking_block_price_meta where id = {$_POST['details']}";
 				$wpdb->query($sql);

				$sql_attribute ="DELETE FROM {$wpdb->prefix}booking_block_price_attribute_meta where block_id = {$_POST['details']}";
				$wpdb->query($sql_attribute);
				 
				die(); 
			}

			function delete_all_price_blocks(){
				global $wpdb;
				$sql="Truncate table wp_booking_block_price_meta";
				
				$wpdb->query($sql);

				$sql_attribute="Truncate table wp_booking_block_price_attribute_meta";
				$wpdb->query($sql_attribute);
				 
				die();

			}
			/*function endKey($array)
			{
				end($array);
				return key($array);
			}*/

			function save_booking_block_price()
			{
				global $wpdb;
				$post_id = $_POST['post_id'];
				$block_id = $_POST['id'];
				if(isset($_POST['attribute_count']))
				{
					$attribute_count = $_POST['attribute_count']; 
				}
				$attributes = explode("|",$_POST['attribute']);
				array_pop($attributes);
				$minimum_number_of_days = $_POST['number_of_start_days'];
				$maximum_number_of_days = $_POST['number_of_end_days'];
				$price_per_day = $_POST['price_per_day'];
				$fixed_price = $_POST['fixed_price'];
				$product_attributes = get_post_meta($post_id, '_product_attributes', true);
				$result = array();
				if (($post_id != "") && ($block_id == ""))
				{
					$insert_booking_block_price = "INSERT INTO {$wpdb->prefix}booking_block_price_meta
					(post_id,minimum_number_of_days,maximum_number_of_days,price_per_day,fixed_price)
					VALUE(
					'{$post_id}',
					'{$minimum_number_of_days}',
					'{$maximum_number_of_days}',
					'{$price_per_day}',
					'{$fixed_price}')";
					$wpdb->query($insert_booking_block_price);
					
					$select_id = 'SELECT MAX(id) as block_id FROM `'.$wpdb->prefix."booking_block_price_meta".'`';
					$results = $wpdb->get_results($select_id);
					$block_attribute_id = $results[0]->block_id;
					$i = 0;
					foreach($product_attributes as $k => $v)
					{
						$attribute_id = $i+1;
						$meta_value = $attributes[$i];
						$insert_booking_block_price_attribute = "INSERT INTO {$wpdb->prefix}booking_block_price_attribute_meta
							(post_id,block_id,attribute_id,meta_value)
							VALUE(
							'{$post_id}',
							'{$block_attribute_id}',
							'{$attribute_id}',
							'{$meta_value}')";
						//echo $insert_booking_block_price_attribute;exit;
						$wpdb->query($insert_booking_block_price_attribute);
						$i++;
					}
					$this->booking_block_price_table();

				}
				else
				{
					$edit_block_price = "UPDATE `".$wpdb->prefix."booking_block_price_meta`
					SET minimum_number_of_days = '".$minimum_number_of_days."',
					maximum_number_of_days = '".$maximum_number_of_days."',
					price_per_day = '".$price_per_day."',
					fixed_price = '".$fixed_price."'
					WHERE id = '".$block_id."'";
					$wpdb->query($edit_block_price);
					$i = 0;
					foreach($product_attributes as $k => $v)
					{
						$attribute_id = $i+1;
						$meta_value = $attributes[$i];
						$edit_block_price_attribute = "UPDATE `".$wpdb->prefix."booking_block_price_attribute_meta`
							SET meta_value = '".$meta_value."'
							WHERE block_id = '".$block_id."' AND
							attribute_id = '".$attribute_id."'";
						$wpdb->query($edit_block_price_attribute);
					}
					$this->booking_block_price_table();
				}
				//echo $id;
				die();
			}
			
			
			function booking_block_price_table()
			{
 				global $post,$wpdb;
 				if(isset($post))
				{
					$post_id = $post->ID;
				}
				else
				{
					$post_id = $_POST['post_id'];
				}
				//print_r($post_id);
				/* AJAX check  */
				if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') 
				{
					/* special ajax here */
					//die($content);
					//echo 'this is ajax ='. 
					$post_id=$_POST['post_id'];

				}
				$product_attributes = get_post_meta($post_id, '_product_attributes', true);
				$query = "SELECT * FROM `".$wpdb->prefix."booking_block_price_meta`
							WHERE post_id = '".$post_id."'";
				 
				$results = $wpdb->get_results($query);
				//print_r($results);
				$var = "";
				$i = 0;
				foreach ($results as $key => $value)
				{
					$var .= '<tr id="row_'.$value->id.'">';
					$query_attribute = "SELECT * FROM `".$wpdb->prefix."booking_block_price_attribute_meta`
							WHERE post_id = '".$post_id."'
							AND block_id = '".$value->id."'";
					 
					$results_attribute = $wpdb->get_results($query_attribute);
					$j = 1;
					//print_r($results_attribute);	
					$id = '';
					foreach($results_attribute as $k => $v)
					{
						$var .= '<td>'.$v->meta_value.'</td>';
						$id .= $v->meta_value."&";
						$j++;
					}
					$var .= '<td>'.$value->minimum_number_of_days.'</td>
							<td>'.$value->maximum_number_of_days.'</td>
							<td>'.$value->price_per_day.'</td>
							<td>'.$value->fixed_price.'</td>
							<td> <a href="javascript:void(0);" id="'.$value->id.'&'.count($product_attributes).'&'.$post_id.'&'.$id.$value->minimum_number_of_days.'&'.$value->maximum_number_of_days.'&'.$value->price_per_day.'&'.$value->fixed_price.'" class="edit_block"><img src="'.plugins_url().'/woocommerce-booking/images/edit.png" alt="Edit Block" title="Edit Block"></a> </td>
							<td> <a href="javascript:void(0);" id="'.$value->id.'" class="delete_price_block"> <img src="'.plugins_url().'/woocommerce-booking/images/delete.png" alt="Delete Block" title="Delete Block"></a> </td>
							</tr>';
				}
				?>
				<div id="block_price_booking_table">
					<p>
					<table class='wp-list-table widefat fixed posts' cellspacing='0' id='list_block_price'>
						<tr>
							<b>Booking Blocks</b>
						</tr>	
						<tr>
							<?php
							if($product_attributes != '')
							{
								foreach($product_attributes as $k => $v)
								{?>
									<th> <?php _e($v["name"], 'bkap_block_booking_price');?> </th>
								<?php
							}?>
							<th> <?php _e('Minimum number of Days', 'bkap_block_booking_price');?></th>
							<th> <?php _e('Maximum number of Days', 'bkap_block_booking_price');?> </th>
							<th> <?php _e('Price per day', 'bkap_block_booking_price');?> </th>
							<th> <?php _e('Fix price', 'bkap_block_booking_price');?> </th>
							<th> <?php _e('Edit', 'bkap_block_booking_price');?> </th>
							<?php print('<th> <a href="javascript:void(0);" id="'.$post_id.'" class="delete_all_price_blocks"> Delete All </a> </th>');	}?>  
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
				if(isset($_POST['booking_block_price_enable']) )
				{
						$booking_settings['booking_block_price_enable'] = $_POST['booking_block_price_enable'];
				}
				return $booking_settings;
			}
			
			function add_cart_item_data($cart_arr, $product_id)
			{
				$currency_symbol = get_woocommerce_currency_symbol();
				$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true);
				if(isset($booking_settings['booking_block_price_enable']) && $booking_settings['booking_block_price_enable'] == "yes")
				{
					if ($booking_settings['booking_enable_multiple_day'] == 'on')
					{	
						$diff_days = $_POST['wapbk_diff_days'];
						$price_type = explode(",",$_POST['block_variable_option_price']);
						//print_r($price_type);exit;
						if($price_type[1] == "fixed" || $price_type[1]  == 'per_day')
						{
							$diff_days=1;
						} 
						//print_r($_POST['block_option_price']);
						$total = $price_type[0] * $diff_days;
						if(isset($booking_settings['booking_partial_payment_enable']) && $booking_settings['booking_partial_payment_enable'] =='yes')
						{
							if($price_type != '')
							{
								$total = $price_type[2] * $diff_days;
							}
							if(isset($booking_settings['allow_full_payment']) && $booking_settings['allow_full_payment'] == "yes")
							{
								if ($_POST['payment_type']=="partial_payment")
								{
									if(isset($booking_settings['booking_partial_payment_radio']) && $booking_settings['booking_partial_payment_radio']=='value')
									{
										$deposit = $booking_settings['booking_partial_payment_value_deposit'];
										//echo $deposit;
										$rem = $total - $deposit;
										$cart_arr['Total'] = $total;
										$cart_arr['Remaining'] = $rem;
										$cart_arr['Deposit'] = $deposit;
										$cart_arr ['price'] = $deposit;
									}
									elseif(isset($booking_settings['booking_partial_payment_radio']) &&	$booking_settings['booking_partial_payment_radio']=='percent')
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
				}
				else
				{
					if (isset($booking_settings['booking_seasonal_pricing_enable']) && $booking_settings['booking_seasonal_pricing_enable'] != "yes") :
					$product = get_product($product_id);
					$product_type = $product->product_type;
				//	$diff_days = '';
					$diff_days = 1;
					if(isset($_POST['wapbk_diff_days']) && $_POST['wapbk_diff_days'] != '')
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
					if(isset($booking_settings['booking_partial_payment_enable']) && $booking_settings['booking_partial_payment_enable'] =='yes')
					{
						$total = $price;
						if(isset($booking_settings['allow_full_payment']) && $booking_settings['allow_full_payment'] == "yes")
						{
							if ($_POST['payment_type']=="partial_payment")
							{
								if(isset($booking_settings['booking_partial_payment_radio']) && $booking_settings['booking_partial_payment_radio']=='value')
								{
									$deposit = $booking_settings['booking_partial_payment_value_deposit'];
										//echo $deposit;
									$rem = $total - $deposit;
									$cart_arr['Total'] = $total;
									$cart_arr['Remaining'] = $rem;
									$cart_arr['Deposit'] = $deposit;
									$cart_arr ['price'] = $deposit;
								}
								elseif(isset($booking_settings['booking_partial_payment_radio']) &&	$booking_settings['booking_partial_payment_radio']=='percent')
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
							elseif(isset($booking_settings['booking_partial_payment_radio']) &&	$booking_settings['booking_partial_payment_radio']=='percent')
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
					endif;
				}
				if (isset($booking_settings['booking_seasonal_pricing_enable']) && $booking_settings['booking_seasonal_pricing_enable'] == "yes") :
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
				endif;
				//print_r($carr_arr);
				return $cart_arr;
			
			}
			function get_cart_item_from_session( $cart_item, $values ) 
			{
				$booking_settings = get_post_meta($cart_item['product_id'], 'woocommerce_booking_settings', true);
				if(isset($booking_settings['booking_block_price_enable']) && is_plugin_active('bkap-deposits/deposits.php'))
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
				//print_r($cart_item);
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
				if(isset($booking_settings['booking_block_price_enable']) && $booking_settings['booking_block_price_enable'] == "yes" )
				{
					//exit;
					if(isset($booking_settings['booking_partial_payment_enable']) && $booking_settings['booking_partial_payment_enable'] =='yes' &&  is_plugin_active('bkap-deposits/deposits.php'))
					{
						$currency_symbol = get_woocommerce_currency_symbol();
						if (isset($cart_item['booking'])) 
						{
							//echo "ehere";
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
				if (isset($booking_settings['booking_block_price_enable']) && isset($booking_settings['booking_partial_payment_radio']) && is_plugin_active("bkap-deposits/deposits.php")){

					if (isset($global_settings->enable_rounding) && $global_settings->enable_rounding == "on"){
					woocommerce_add_order_item_meta($order_item_id,  book_t('book.item-partial-total'), $currency_symbol.round($values['booking'][0]['Total'] *$values['quantity']), true );
					woocommerce_add_order_item_meta($order_item_id,  book_t('book.item-partial-deposit'), $currency_symbol.round($values['booking'][0]['Deposit']* $values['quantity']), true );
					woocommerce_add_order_item_meta($order_item_id,  book_t('book.item-partial-remaining'), $currency_symbol.round($values['booking'][0]['Remaining']* $values['quantity']), true );
					}
				
				else{
				woocommerce_add_order_item_meta($order_item_id,  book_t('book.item-partial-total'), $currency_symbol.$values['booking'][0]['Total'] *$values['quantity'], true );
				woocommerce_add_order_item_meta($order_item_id,  book_t('book.item-partial-deposit'), $currency_symbol.$values['booking'][0]['Deposit']* $values['quantity'], true );
				woocommerce_add_order_item_meta($order_item_id,  book_t('book.item-partial-remaining'), $currency_symbol.$values['booking'][0]['Remaining']* $values['quantity'], true );
				}
				}
					
			}

			function show_updated_price($product_id,$product_type,$variation_id_to_fetch,$checkin_date,$checkout_date)
			{
				//echo "here";
				global $wpdb;
				$results_price = array();
				if ($product_type == 'variable')
				{
					$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
					if (isset($booking_settings['booking_block_price_enable']) && $booking_settings['booking_block_price_enable'] == 'yes')
					{
						$variations_selected = array();
						$string_explode = '';
						$product_attributes = get_post_meta($product_id, '_product_attributes', true);
						$i = 0;
						foreach($product_attributes as $key => $value)
						{
							//print_r($_POST);
							if(isset($_POST['attribute_selected']))
							{
								$string_explode = explode("|",$_POST['attribute_selected']);
							}
							else
							{
								$string_explode = array();
							}
							$value_array = explode("|",$value['value']);
							$s_value = '';
							foreach($string_explode as $sk => $sv)
							{
								//echo $sv;
								if($sv == '')
								{
									unset($string_explode[$sk]);
								}
							}
							//print_r($string_explode);						
							foreach($value_array as $k => $v)
							{
								$string1 = str_replace(" ","",$v);
								if(count($string_explode) > 0)
								{
									$string2 = str_replace(" ","",$string_explode[$i+1]);
								}
								else
								{
									$string2 = '';
								}
								if(strtolower($string1) == strtolower($string2) /* $pos_value != 0*/)
								{
									//echo "here".$pos_value;
									if(substr($v, 0, -1) === ' ')
									{
										$result = rtrim($v," ");
										$variations_selected[$i] = $result;
									}
									if(substr($v, 0, 1) === ' ')
									{
										$result = preg_replace("/ /","",$v,1);
										$variations_selected[$i] = $result;
									}
									else
									{
										$variations_selected[$i] = $v;
									}
								}					
							}
							$i++;
						}
						//print_r($variations_selected);
						$j = 1;
						$k = 0;
						$attribute_sub_query = '';
						foreach($variations_selected as $key => $value)
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
						//print_r($results);
						$number_of_days =  strtotime($checkout_date) - strtotime($checkin_date);
				//		$number = floor($number_of_days/(60*60*24)) + 1;
						$number = floor($number_of_days/(60*60*24));
						if ( isset($booking_settings['booking_charge_per_day']) && $booking_settings['booking_charge_per_day'] == 'on' )
						{
							$number = $number + 1;
						}
						//echo $number;
						if($number == 0 && isset($booking_settings['booking_same_day']) && $booking_settings['booking_same_day'] == 'on')
							$number = 1;
						//echo $number;
						$e = 0;
						foreach($results as $k => $v)
						{
							$query = "SELECT price_per_day, fixed_price, maximum_number_of_days FROM `".$wpdb->prefix."booking_block_price_meta`
							WHERE id = '".$v->block_id."' AND post_id = '".$product_id."' AND minimum_number_of_days <='".$number."' AND maximum_number_of_days >='".$number."'";
							//echo $query;
							$results_price[$e] = $wpdb->get_results($query);
							$e++;
						}
						//print_r($results_price);
						if(count($results_price[0]) == 0)
						{
							$e = 0;
							foreach($results as $k => $v)
							{
								$query = "SELECT price_per_day, fixed_price, MAX(maximum_number_of_days) AS maximum_number_of_days FROM `".$wpdb->prefix."booking_block_price_meta`
								WHERE id = '".$v->block_id."' AND post_id = '".$product_id."' AND minimum_number_of_days <='".$number."'";
								//echo $query;
								$results_price[$e] = $wpdb->get_results($query);
								//print_r($results_price);
								$e++;
							}
							if(count($results_price) == 0)
							{
								if (isset($booking_settings['booking_partial_payment_enable']) && $booking_settings['booking_partial_payment_enable'] == "yes")
								{ 
									if(isset($booking_settings['booking_partial_payment_radio']) && $booking_settings['booking_partial_payment_radio']=='value')
									{
										$regular_price = get_post_meta( $variation_id_to_fetch, '_sale_price', true);
										if($regular_price == '')
										{
											$regular_price = get_post_meta( $variation_id_to_fetch, '_regular_price', true);
										}
										$price = $booking_settings['booking_partial_payment_value_deposit'];
										$price .= "-";
										$price .= "-".$regular_price;
									}
									elseif(isset($booking_settings['booking_partial_payment_radio']) && $booking_settings['booking_partial_payment_radio']=='percent')
									{
										$sale_price = get_post_meta( $variation_id_to_fetch, '_sale_price', true);
										if($sale_price == '')
										{
											$regular_price = get_post_meta( $variation_id_to_fetch, '_regular_price', true);
											$price = (($booking_settings['booking_partial_payment_value_deposit']*$regular_price)/100);
											$price .= "-";
											$price .= "-".$regular_price;
										}
										else
										{
											$price = (($booking_settings['booking_partial_payment_value_deposit']*$sale_price)/100);
											$price .= "-";
											$price .= "-".$sale_price;
										}
									}
									else
									{
										$sale_price = get_post_meta( $variation_id_to_fetch, '_sale_price', true);
										if($sale_price == '')
										{
											$regular_price = get_post_meta( $variation_id_to_fetch, '_regular_price', true);
											$price = $regular_price;
											$price .= "-";
										}
										else
										{
											$price = $sale_price;
											$price .= "-";
										}
									}
								}
								else
								{
									$sale_price = get_post_meta( $variation_id_to_fetch, '_sale_price', true);
										if($sale_price == '')
										{
											$regular_price = get_post_meta( $variation_id_to_fetch, '_regular_price', true);
											$price = $regular_price;
											$price .= "-";
										}
										else
										{
											$price = $sale_price;
											$price .= "-";
										}
								}
							}
							else
							{
								foreach($results_price as $k => $v)
								{
									if(!empty($results_price[$k]))
									{
										if(isset($booking_settings['booking_partial_payment_radio']) && $booking_settings['booking_partial_payment_radio']=='value')
										{
											$price = $booking_settings['booking_partial_payment_value_deposit'];
										}
										elseif(isset($booking_settings['booking_partial_payment_radio']) &&			$booking_settings['booking_partial_payment_radio']=='percent')
										{
											//$sale_price = get_post_meta( $variation_id_to_fetch, '_sale_price', true);
											if (isset($booking_settings['booking_block_price_enable']) && $booking_settings['booking_block_price_enable'] == "yes")
											{
												$regular_price = get_post_meta( $variation_id_to_fetch, '_sale_price', true);
												if($regular_price == '')
												{
													$regular_price = get_post_meta( $variation_id_to_fetch, '_regular_price', true);
												}
												$diff_days = '';
												if($v[0]->maximum_number_of_days < $number)
												{
													$diff_days = $number - $v[0]->maximum_number_of_days;
													if($v[0]->fixed_price != 0)
													{
														$oprice = $v[0]->fixed_price + ($regular_price * $diff_days);
														$pprice = "-fixed";
													}
													else
													{
														$oprice = ($v[0]->price_per_day * $v[0]->maximum_number_of_days) + ($regular_price * $diff_days);
														$pprice = "-per_day";
													}
												}
												$price = (($booking_settings['booking_partial_payment_value_deposit']*$oprice)/100);
												$price .= $pprice; 
												$price .= "-".$oprice; 
											}
											elseif($sale_price == '')
											{
												$regular_price = get_post_meta( $variation_id_to_fetch, '_regular_price', true);
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
											$regular_price = get_post_meta( $variation_id_to_fetch, '_sale_price', true);
											if($regular_price == '')
											{
												$regular_price = get_post_meta( $variation_id_to_fetch, '_regular_price', true);
											}
											//$diff_days = ''; 
											if($v[0]->maximum_number_of_days < $number)
											{
												$diff_days = $number - $v[0]->maximum_number_of_days;
												//echo $diff_days;
												if($v[0]->fixed_price != 0)
												{
													$price = $v[0]->fixed_price + ($regular_price * $diff_days);
													$price .= "-fixed";
													$price .= "-";
												}
												else
												{
													$price = ($v[0]->price_per_day * $v[0]->maximum_number_of_days) + ($regular_price * $diff_days);
													$price .= "-per_day";
													$price .= "-fixed";
													//echo $price;
												}
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
										//$sale_price = get_post_meta( $variation_id_to_fetch, '_sale_price', true);
										if (isset($booking_settings['booking_block_price_enable']) && $booking_settings['booking_block_price_enable'] == "yes")
										{
											$regular_price = get_post_meta( $variation_id_to_fetch, '_sale_price', true);
											if($regular_price == '')
											{
												$regular_price = get_post_meta( $variation_id_to_fetch, '_regular_price', true);
											}
											$diff_days = '';
											if($v[0]->maximum_number_of_days < $number)
											{
												$diff_days = $number - $v[0]->maximum_number_of_days;
												if($v[0]->fixed_price != 0)
												{
													$oprice = $v[0]->fixed_price + ($regular_price * $diff_days);
													$pprice = "-fixed";
												}
												else
												{
													$oprice = ($v[0]->price_per_day * $v[0]->maximum_number_of_days) + ($regular_price * $diff_days);
													$pprice = "-per_day";
												}
											}
											else
											{
												if($v[0]->fixed_price != 0)
												{
													$oprice = $v[0]->fixed_price;
													$pprice = "-fixed";
												}
												else
												{
													$oprice = $v[0]->price_per_day * $number;
													$pprice = "-per_day";
												}
											}
											$price = (($booking_settings['booking_partial_payment_value_deposit']*$oprice)/100);
											$price .= $pprice; 
											$price .= "-".$oprice; 
										}
										elseif($sale_price == '')
										{
											$regular_price = get_post_meta( $variation_id_to_fetch, '_regular_price', true);
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
										$regular_price = get_post_meta( $variation_id_to_fetch, '_sale_price', true);
										if($regular_price == '')
										{
											$regular_price = get_post_meta( $variation_id_to_fetch, '_regular_price', true);
										}
										//$diff_days = ''; 
										if($v[0]->maximum_number_of_days < $number)
										{
											$diff_days = $number - $v[0]->maximum_number_of_days;
											//echo $diff_days;
											if($v[0]->fixed_price != 0)
											{
												$price = $v[0]->fixed_price + ($regular_price * $diff_days);
												$price .= "-fixed";
												$price .= "-";
											}
											else
											{
												$price = ($v[0]->price_per_day * $v[0]->maximum_number_of_days) + ($regular_price * $diff_days);
												$price .= "-per_day";
												$price .= "-fixed";
												//echo $price;
											}
										}
										else
										{
											if($v[0]->fixed_price != 0)
											{
												$price = $v[0]->fixed_price;
												$price .= "-fixed";
												$price .= "-";
											}
											else
											{
												$price = $v[0]->price_per_day * $number;
												$price .= "-per_day";
												$price .= "-";
												//echo $price;
											}
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
					echo $price;
					die();	
				}
				elseif ($product_type == 'simple')
				{
					$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
					if (isset($booking_settings['booking_block_price_enable']) && $booking_settings['booking_block_price_enable'] == 'yes')
					{
						$number_of_days =  strtotime($checkout_date) - strtotime($checkin_date);
					//	$number = floor($number_of_days/(60*60*24)) + 1;
						$number = floor($number_of_days/(60*60*24));
						if ( isset($booking_settings['booking_charge_per_day']) && $booking_settings['booking_charge_per_day'] == 'on' )
						{
							$number = $number + 1;
						}
						//echo $number;
						if($number == 0 && isset($booking_settings['booking_same_day']) && $booking_settings['booking_same_day'] == 'on')
							$number = 1;
						$query = "SELECT price_per_day, fixed_price, maximum_number_of_days FROM `".$wpdb->prefix."booking_block_price_meta`
							WHERE post_id = '".$product_id."' AND minimum_number_of_days <='".$number."' AND maximum_number_of_days >='".$number."'";
							//echo $query;
						$results_price = $wpdb->get_results($query);
						if(count($results_price) == 0)
						{
							$query = "SELECT price_per_day, fixed_price, MAX(maximum_number_of_days) AS maximum_number_of_days FROM `".$wpdb->prefix."booking_block_price_meta`
							WHERE post_id = '".$product_id."' AND minimum_number_of_days <='".$number."'";
							//echo $query;
							$results_price = $wpdb->get_results($query);
							//print_r($results_price);
							if(count($results_price) == 0)
							{
								if (isset($booking_settings['booking_partial_payment_enable']) && $booking_settings['booking_partial_payment_enable'] == "yes")
								{ 
									if(isset($booking_settings['booking_partial_payment_radio']) && $booking_settings['booking_partial_payment_radio']=='value')
									{
										$regular_price = get_post_meta( $product_id, '_sale_price', true);
										if($regular_price == '')
										{
											$regular_price = get_post_meta( $product_id, '_regular_price', true);
										}
										
										$price = $booking_settings['booking_partial_payment_value_deposit'];
										$price .= "-value";
										$price .= "-".$regular_price;
									}
									elseif(isset($booking_settings['booking_partial_payment_radio']) && $booking_settings['booking_partial_payment_radio']=='percent')
									{
										$sale_price = get_post_meta( $product_id, '_sale_price', true);
										if($sale_price == '')
										{
											$regular_price = get_post_meta( $product_id, '_regular_price', true);
											$price = (($booking_settings['booking_partial_payment_value_deposit']*$regular_price)/100);
											$price .= "-percent";
											$price .= "-".$regular_price;

										}
										else
										{
											$price = (($booking_settings['booking_partial_payment_value_deposit']*$sale_price)/100);
											$price .= "-percent";
											$price .= "-".$sale_price;
										}
									}
									else
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
								}
								else
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
							}
							else
							{
								foreach($results_price as $k => $v)
								{
									//print_r($booking_settings);
									if(!empty($results_price[$k]))
									{
										if (isset($booking_settings['booking_partial_payment_enable']) && $booking_settings['booking_partial_payment_enable'] == "yes")
										{ 
											if(isset($booking_settings['booking_partial_payment_radio']) &&		$booking_settings['booking_partial_payment_radio']=='value')
											{
												$price = $booking_settings['booking_partial_payment_value_deposit'];
												if (isset($booking_settings['booking_block_price_enable']) && $booking_settings['booking_block_price_enable'] ==	"yes")
												{
													$regular_price = get_post_meta( $product_id, '_sale_price', true);
													if($regular_price == '')
													{
														$regular_price = get_post_meta( $product_id, '_regular_price', true);
													}
													if($v->maximum_number_of_days < $number)
													{
														$diff_days = $number - $v->maximum_number_of_days;
														if($v->fixed_price != 0)
														{
															$oprice = $v->fixed_price + ($regular_price * $diff_days);
															$pprice = "-fixed";
														}
														else
														{
															$oprice = ($v->price_per_day * $v->maximum_number_of_days) + ($regular_price * $diff_days);
															$pprice = "-per_day";
														}
													}
													$price .= $pprice;
													$price .= "-".$oprice;
												}
											}
											elseif(isset($booking_settings['booking_partial_payment_radio']) &&		$booking_settings['booking_partial_payment_radio']=='percent')
											{
												$sale_price = get_post_meta( $product_id, '_sale_price', true);
												if (isset($booking_settings['booking_block_price_enable']) && $booking_settings['booking_block_price_enable'] == "yes")
												{
													$regular_price = get_post_meta( $product_id, '_sale_price', true);
													if($regular_price == '')
													{
														$regular_price = get_post_meta( $product_id, '_regular_price', true);
													}
													if($v->maximum_number_of_days < $number)
													{
														$diff_days = $number - $v->maximum_number_of_days;
														if($v->fixed_price != 0)
														{
														
															$oprice = $v->fixed_price + ($regular_price * $diff_days);
															$pprice = "-fixed";
														}
														else
														{
															$oprice = ($v->price_per_day * $v->maximum_number_of_days) + ($regular_price * $diff_days);
															$pprice = "-per_day";
														}
													}
												
													//echo $oprice;
													//echo "here".$booking_settings['booking_partial_payment_value_deposit'];
													$price = (($booking_settings['booking_partial_payment_value_deposit']*$oprice)/100);
													$price .= $pprice;
													$price .= "-".$oprice; 
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
										}
										else if(isset($booking_settings['booking_block_price_enable']) && $booking_settings['booking_block_price_enable'] == "yes")
										{	
											//echo $v[0]->fixed_price;
											$regular_price = get_post_meta( $product_id, '_sale_price', true);
											if($regular_price == '')
											{
												$regular_price = get_post_meta( $product_id, '_regular_price', true);
											}
											if($v->maximum_number_of_days < $number)
											{
												$diff_days = $number - $v->maximum_number_of_days;
												if($v->fixed_price != 0)
												{	
													$price = $v->fixed_price + ($regular_price * $diff_days);
													$price .= "-fixed";
													$price .= "-";		
												}
												else
												{
													$price = ($v->price_per_day * $v->maximum_number_of_days) + ($regular_price * $diff_days);
													//echo $price;
													$price .= "-per_day";
													$price .= "-";
												}
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
							foreach($results_price as $k => $v)
							{
								//print_r($booking_settings);
								if(!empty($results_price[$k]))
								{
									if (isset($booking_settings['booking_partial_payment_enable']) && $booking_settings['booking_partial_payment_enable'] == "yes")
									{ 
										if(isset($booking_settings['booking_partial_payment_radio']) && $booking_settings['booking_partial_payment_radio']=='value')
										{
											$price = $booking_settings['booking_partial_payment_value_deposit'];
											if (isset($booking_settings['booking_block_price_enable']) && $booking_settings['booking_block_price_enable'] == "yes")
											{
												$regular_price = get_post_meta( $product_id, '_sale_price', true);
												if($regular_price == '')
												{
													$regular_price = get_post_meta( $product_id, '_regular_price', true);
												}
												if($v->fixed_price != 0)
												{
													$oprice = $v->fixed_price;
													$pprice = "-fixed";		
												}
												else
												{
													$oprice = $v ->price_per_day * $number;
													$pprice = "-per_day";
												}
												$price .= $pprice;
												$price .= "-".$oprice;
											}
										}
										elseif(isset($booking_settings['booking_partial_payment_radio']) && $booking_settings['booking_partial_payment_radio']=='percent')
										{
											$sale_price = get_post_meta( $product_id, '_sale_price', true);
											if (isset($booking_settings['booking_block_price_enable']) && $booking_settings['booking_block_price_enable'] == "yes")
											{
												$regular_price = get_post_meta( $product_id, '_sale_price', true);
												if($regular_price == '')
												{
													$regular_price = get_post_meta( $product_id, '_regular_price', true);
												}
												if($v->fixed_price != 0)
												{
													$oprice = $v->fixed_price;
													$pprice = "-fixed";		
												}
												else
												{
													$oprice = $v ->price_per_day * $number;
													$pprice = "-per_day";
												
												}
												//echo $oprice;
												//echo "here".$booking_settings['booking_partial_payment_value_deposit'];
												$price = (($booking_settings['booking_partial_payment_value_deposit']*$oprice)/100);
												$price .= $pprice;
												$price .= "-".$oprice; 
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
									}
									else if(isset($booking_settings['booking_block_price_enable']) && $booking_settings['booking_block_price_enable'] == "yes")
									{	
										//echo $v[0]->fixed_price;
										$regular_price = get_post_meta( $product_id, '_sale_price', true);
										if($regular_price == '')
										{
											$regular_price = get_post_meta( $product_id, '_regular_price', true);
										}
										if($v->fixed_price != 0)
										{
											$price = $v->fixed_price;
											$price .= "-fixed";
											$price .= "-";		
										}
										else
										{
											$price = $v ->price_per_day * $number;
											$price .= "-per_day";
											$price .= "-";
										}
									}
								}
								else
								{
									unset($results_price[$k]);
								}
							}
						}
						
						echo $price;
						die();
					}
				}
			}
		}
	
	}
	$bkap_block_booking_price = new bkap_block_booking_price();
?>