<?php 
class Custom_WooCommerce_Widget_Product_Search extends WP_Widget {
 
 function Custom_WooCommerce_Widget_Product_Search() {

		/* Widget variable settings. */
		$this->woo_widget_cssclass = 'Custom widget_product_search';
		$this->woo_widget_description = __( 'Allows customers to search all the products based on checkin & checkout dates.', 'woocommerce-booking' );
		$this->woo_widget_idbase = 'woocommerce_booking_availability_search';
		$this->woo_widget_name = __( 'WooCommerce Bookings Availability Search', 'woocommerce-booking' );

		/* Widget settings. */
		$widget_ops = array( 'classname' => $this->woo_widget_cssclass, 'description' => $this->woo_widget_description );

		/* Create the widget. */
		$this->WP_Widget('custom_product_search', $this->woo_widget_name, $widget_ops);
	}

	/**
	 * widget function.
	 *
	 * @see WP_Widget
	 * @access public
	 * @param array $args
	 * @param array $instance
	 * @return void
	 */
	 function get_custom_page_url($page_name)
{
    global $wpdb;
    $page_name_id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_name = '".$page_name."'  AND post_status = 'publish' AND post_type = 'page' ");
    $page_permalink = get_permalink($page_name_id);
    return $page_permalink;
}
	function widget( $args, $instance ) {
		extract($args);

		$start_date = $instance['start_date_label'];
		$end_date = $instance['end_date_label'];
		$search_label = $instance['search_label'];
		$text_label = $instance['text_label'];
		if(isset($instance['title_label']))
		{
			$title = $instance['title_label'];
		}
		else 
		{
			$title = '';
		}
		$title = apply_filters('widget_title', $title, $instance, $this->id_base);
		//$title = apply_filters('widget_title', $title, $instance, $this->id_base);

		echo $before_widget;
			if ( $title )
                    echo $before_title . $title . $after_title;

$url = plugins_url();
$action = get_permalink( woocommerce_get_page_id( 'shop' ) );
$calendar_theme = json_decode(get_option('woocommerce_booking_global_settings'));
if (isset($calendar_theme))
	{
		$calendar_theme_sel = $calendar_theme->booking_themes;
		$booking_language = $calendar_theme->booking_language;
		$date_format = $calendar_theme->booking_date_format;
	}
	else 
			{$calendar_theme_sel = "smoothness";
			$booking_language = "en-GB";
			$date_format = "yy-mm-dd";
		}

wp_enqueue_style('jquery-ui',"$url/woocommerce-booking/css/themes/$calendar_theme_sel/jquery-ui.css");
wp_enqueue_script('jquery-ui');
wp_register_script('jquery-ui-datepicker2',"$url/woocommerce-booking/js/i18n/jquery.ui.datepicker-$booking_language.js");
wp_enqueue_script('jquery-ui-datepicker2');
wp_enqueue_script('jquery-ui-datepicker');

$abc =  <<<HTML
<script type="text/javascript">
jQuery(document).ready(function(){	
var today = new Date();
var dd = today.getDate();		
				
	 jQuery( "#w_check_in" ).datepicker({minDate: today,dateFormat:"$date_format",altField: "#w_checkin",altFormat: "yy-mm-dd",onClose: function( selectedDate ) {
												
	jQuery("#w_check_out" ).datepicker("option", "minDate", selectedDate);
													}});
	jQuery("#w_check_out").datepicker({minDate:today,dateFormat:"$date_format",altField: "#w_checkout",altFormat: "yy-mm-dd"});


	jQuery("#w_check_in").datepicker("option",jQuery.datepicker.regional[ "$booking_language" ]);
	jQuery("#w_check_out").datepicker("option",jQuery.datepicker.regional[ "$booking_language" ]);
	
	jQuery("#ui-datepicker-div").wrap("<div class=\"hasDatepicker\"></div>");
});
</script>
<div id="wrapper" style="width:300px !important;" >
	<form role="search" method="get" id="searchform" action="$action">
	<table>
		<tr>
			<td>$start_date&nbsp;</td>
			<td><input  id="w_check_in" name="w_check_in" style="width:160px"  type="text" readonly/><input type="hidden" id="w_checkin" name="w_checkin"></td>
		</tr>
		<tr>
			<td>
			$end_date&nbsp
			</td>
			<td>
			<input  id="w_check_out" name="w_check_out" style="width:160px" type="text"  readonly/><input type="hidden" id="w_checkout" name="w_checkout">
			</td>
		</tr>
		<tr>
		<td></td><td><input type="submit" id="search" value="$search_label" /></td>
		</tr>
		<tr><td></td></tr>
		<tr>
		<td colspan="2">$text_label</td>
		</tr>
		
	</table>
</form>
 </div>
HTML;
echo $abc;

		echo $after_widget;
	}

	/**
	 * update function.
	 *
	 * @see WP_Widget->update
	 * @access public
	 * @param array $new_instance
	 * @param array $old_instance
	 * @return array
	 */
	function update( $new_instance, $old_instance ) {
		$instance['start_date_label'] = strip_tags(stripslashes($new_instance['start_date_label']));
		$instance['end_date_label'] = strip_tags(stripslashes($new_instance['end_date_label']));
		$instance['search_label'] = strip_tags(stripslashes($new_instance['search_label']));
		$instance['text_label'] = stripslashes($new_instance['text_label']);
		$instance['title_label'] = stripslashes($new_instance['title_label']);
		return $instance;
	}

	/**
	 * form function.
	 *
	 * @see WP_Widget->form
	 * @access public
	 * @param array $instance
	 * @return void
	 */
	function form( $instance ) {
		global $wpdb;
		?>
			<p><label for="<?php echo $this->get_field_id('title_label'); ?>"><?php _e('Title Label:', 'woocommerce-booking' ) ?></label>
			<input type="text" class="widefat" id="<?php echo esc_attr( $this->get_field_id('title_label') ); ?>" name="<?php echo esc_attr( $this->get_field_name('title_label') ); ?>" value="<?php if (isset ( $instance['title_label'])) {echo esc_attr( $instance['title_label'] );} ?>" /></p>

			<p><label for="<?php echo $this->get_field_id('start_date_label'); ?>"><?php _e('Start Date Label:', 'woocommerce-booking' ) ?></label>
			<input type="text" class="widefat" id="<?php echo esc_attr( $this->get_field_id('start_date_label') ); ?>" name="<?php echo esc_attr( $this->get_field_name('start_date_label') ); ?>" value="<?php if (isset ( $instance['start_date_label'])) {echo esc_attr( $instance['start_date_label'] );} ?>" /></p>
			<p><label for="<?php echo $this->get_field_id('end_date_label'); ?>"><?php _e('End Date Label:', 'woocommerce-booking' ) ?></label>
			<input type="text" class="widefat" id="<?php echo esc_attr( $this->get_field_id('end_date_label') ); ?>" name="<?php echo esc_attr( $this->get_field_name('end_date_label') ); ?>" value="<?php if (isset ( $instance['end_date_label'])) {echo esc_attr( $instance['end_date_label'] );} ?>" /></p>
			<p><label for="<?php echo $this->get_field_id('search_label'); ?>"><?php _e('Search Button Label:', 'woocommerce-booking' ) ?></label>
			<input type="text" class="widefat" id="<?php echo esc_attr( $this->get_field_id('search_label') ); ?>" name="<?php echo esc_attr( $this->get_field_name('search_label') ); ?>" value="<?php if (isset ( $instance['search_label'])) {echo esc_attr( $instance['search_label'] );} ?>" /></p>
			<p><label for="<?php echo $this->get_field_id('text_label'); ?>"><?php _e('Text (appears below Search button)', 'woocommerce-booking' ) ?></label>
			<textarea class="widefat" id="<?php echo esc_attr( $this->get_field_id('text_label') ); ?>" name="<?php echo esc_attr( $this->get_field_name('text_label') ); ?>"><?php if (isset ( $instance['text_label'])) {echo esc_attr( $instance['text_label'] );} ?></textarea></p>
		<?php
	}
 
}