<?php 

require_once('../../../../wp-load.php');

$args = array( 'post_type' => 'shop_order', 'post_status' => 'publish' );


$postslist = get_posts( $args );

$booking_time_label = get_option('book.item-meta-time');
$booking_date_label = get_option('book.item-meta-date');

// postslist foreach START
foreach ( $postslist as $post ) :
  setup_postdata( $post ); 
  
  $order_id = get_the_ID();
  $title = get_the_title();	
  
  $query = "SELECT * FROM 
  			".$wpdb->prefix."woocommerce_order_items AS o
			INNER JOIN ".$wpdb->prefix."woocommerce_order_itemmeta AS m
			ON o.order_item_id = m.order_item_id
			WHERE o.order_id = ".$order_id."";
  $order_info = $wpdb->get_results($query, 'ARRAY_A');      

	// order_info temp foreach START
  	foreach($order_info as $temp){
  

   // echo ("<br/><pre>");
  //print_r($order_info);
  //echo ("</pre><br/>-----------<br/>");
  
  		// Is booking time if START
		if($temp["meta_key"] == $booking_time_label){
			$is_reminders = false ;			
			$product_id = "";
			$booking_time_start_and_end = "";
			$booking_time = "";
			$start_date = "";
			$message = "";
			$email = "";
			$order_item_name = "";
			
			// order_info foreach START
			foreach($order_info as $order_item){
				
  	//echo ("<br/><pre>");
  	//print_r($order_item);
  	//echo ("</pre><br/>-----------<br/>");				
				
				
				
				if($order_item["meta_key"] == $booking_time_label){
					$booking_time_start_and_end = trim($order_item["meta_value"]);
					$booking_time = substr($booking_time_start_and_end, 0, 8);
					$booking_time = new DateTime($booking_time);
 					$booking_time = date_format($booking_time, 'H:i:s');

 					$order_item_name = $order_item["order_item_name"];
				}else if($order_item["meta_key"] == $booking_date_label){
					//$start_date = new DateTime($order_item["meta_value"]);
 					//$start_date = date_format($start_date, 'Y-m-d');
					$start_date = $order_item["meta_value"];
			
					$order_item_name = $order_item["order_item_name"];
				}else if($order_item["meta_key"] == "_product_id"){
					$product_id = $order_item["meta_value"];
					
					$order_item_name = $order_item["order_item_name"];
				}
			}
			// order_info foreach END
																
			$all_reminders = array_values(json_decode(get_post_meta( $product_id, "woocommerce_booking_emailreminders", true )));			
  			
			$is_global_rminders = false ;
			//Loard global reminders if not available product reminders 
			if(!$all_reminders){
				$all_reminders = array_values(json_decode(get_option("globalemailreminders"),true));				
				$is_global_rminders = true ;	
				
			}
			
			// all_reminders foreach START
			foreach($all_reminders as $reminder){
				
				if($is_global_rminders){
					$subject_orginal = $reminder["subject"];
					$subject = $reminder["subject"];
					$days = $reminder["days"];
					$hours = $reminder["hours"];
					$minutes = $reminder["minutes"];
					$message = $reminder["message"];
					$email = $reminder["email"];	
				}else{
					$subject = $reminder->subject;
					$subject_orginal = $reminder->subject;
					$days = $reminder->days;
					$hours = $reminder->hours;
					$minutes = $reminder->minutes;
					$message = $reminder->message;
					$email = $reminder->email;					
				}
				
				$reminders_array = json_decode(get_post_meta( $order_id, "_emailreminders_send_id", true ));
				
				$emailreminders_send_id = array();
				if ($reminders_array != null && count($reminders_array) > 0)
				{
					$emailreminders_send_id = array_values($reminders_array);
				}

				$is_send = false ;
				foreach($emailreminders_send_id as $send_id){
					
					if(trim($send_id) == trim($subject_orginal)){
						$is_send = true ;
						//echo ("***********Alredy Sended<br/>");
					}
				}
				
				// Is Send IF START
				if(!$is_send){
				
				/*echo ("days=[$days]<br/>");
				echo ("hours=[$hours]<br/>");
				echo ("minutes=[$minutes]<br/>");
				echo ("message=[$message]<br/>");
				echo ("email=[$email]<br/>");*/			
								
				$booking_date_time = new DateTime($start_date." ".$booking_time);
				$booking_date_time = date_format($booking_date_time, 'Y-m-d H:i:s'); 			
				
				$current_date_time = date("Y-m-d H:i:s");		
				
				$time_difference = strtotime($booking_date_time)-strtotime($current_date_time);
				
				$timer =  (((intval($days) * 24 ) * 60 )* 60 ) + ((intval($hours) * 60) * 60 ) + (intval($minutes) * 60); 
				
				/*echo ("[order_id=".$order_id."]<br/>");
				echo ("[product_id=".$product_id."]<br/>");
				echo ("[booking_date_time=".$booking_date_time."]<br/>");
				echo ("[current_date_time=".$current_date_time."]<br/>");
				echo ("[time_difference=".$time_difference."]<br/>");
				echo ("[timer=".$timer."]<br/>");*/	
				
				/*echo ("***************<br/>");
				echo ("[time1=".$time_difference."]<br/>");
				echo ("[time2=".$timer."]<br/>");
				echo ("[time_difference=".($time_difference < $timer)."]<br/>");*/
				
				// Is time_difference IF START			
				if($time_difference < $timer){					
					
					$to = get_post_meta( $order_id, "_billing_email", true ).",".$email;
					
					$message = str_replace("rn", "\r\n" ,$message);						
					$message = str_replace("[first_name]",get_post_meta( $order_id, "_billing_first_name", true ),$message);
					$message = str_replace("[last_name]",get_post_meta( $order_id, "_billing_last_name", true ),$message);
					$message = str_replace("[date]", $start_date ,$message);
					$message = str_replace("[time]", $booking_time_start_and_end ,$message);
					$message = str_replace("[shop_name]", get_option("blogname") ,$message);
					$message = str_replace("[shop_url]", get_option("siteurl") ,$message);
					$message = str_replace("[service]", $order_item_name ,$message);
					$message = str_replace("[order_number]", "#".$order_id , $message);
					
					
					$subject = str_replace("[first_name]",get_post_meta( $order_id, "_billing_first_name", true ),$subject);
					$subject = str_replace("[last_name]",get_post_meta( $order_id, "_billing_last_name", true ),$subject);
					$subject = str_replace("[date]", $start_date ,$subject);
					$subject = str_replace("[time]", $booking_time_start_and_end ,$subject);
					$subject = str_replace("[shop_name]", get_option("blogname") ,$subject);
					$subject = str_replace("[shop_url]", get_option("siteurl") ,$subject);
					$subject = str_replace("[service]", $order_item_name ,$subject);
					$subject = str_replace("[order_number]", "#".$order_id , $subject);
					
					$subject = stripslashes($subject);
					
					$headers = "";
										
					/*echo ("**************************<br/>[to=".$to."]<br/>");
					echo ("[subject=".$subject."]<br/>");
					echo ("[message=".$message."]<br/>");
					echo ("[headers=".$headers."]<br/>**************************<br/>");*/
					wp_mail( $to, $subject, $message, $headers );	
					wp_mail( "kothari.vishal@gmail.com", $subject, $message, $headers );
					
					array_push($emailreminders_send_id, $subject_orginal);
					
					update_post_meta($order_id, '_emailreminders_send_id',json_encode($emailreminders_send_id) );
								
				}
				// Is time_difference IF END			
				
				}
				// Is Send IF START
							
			}	
			// all_reminders foreach END		
			
						
			// is all_reminders IF END

		}
		// Is booking time if END
				
	}
	// order_info temp foreach END
   
endforeach; 
// postslist foreach END
wp_reset_postdata(); 
?> 
