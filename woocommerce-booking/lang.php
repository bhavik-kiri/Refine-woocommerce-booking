<?php

$book_lang = 'en';
$book_translations = array(
	'en' => array(
	
		// Labels for Booking Date & Booking Time on the product page
		'book.date-label'     		=> "Start Date", 
		'checkout.date-label'     	=> "<br>End Date", 
		'book.time-label'     		=> "Booking Time",
		'book.item-comments'		=> "Comments",
			
		// Message shown on checkout page if the Quantity exceeds the maximum available bookings for that time slot
		// Message Text: "product name" has only "X" tickets available for the time slot "from-to hours"
		'book.limited-booking-msg1'	=> " has only ",
		'book.limited-booking-msg2'	=> " tickets available for the time slot ",
		
		//Message shown on checkout page if the time slot for the chosen date has been fully booked
		//Message Text: "For "product name", the time slot of "time slot" has been fully booked. Please try another time slot.  
		'book.no-booking-msg1'		=> "For ",
		'book.no-booking-msg2'		=> ", the timeslot of ",
		'book.no-booking-msg3'		=> " has been fully booked. Please try another time slot.",
		
		// Labels for Booking Date & Booking Time on the "Order Received" page on the web and in the notification email to customer & admin
		'book.item-meta-date'		=> "Start Date",
		'checkout.item-meta-date'		=> "End Date",
		'book.item-meta-time'		=> "Booking Time",
			
		// Labels for Booking Date & Booking Time on the Cart Page and the Checkout page
		'book.item-cart-date'		=> "Start Date",
		'checkout.item-cart-date'		=> "End Date",
		'book.item-cart-time'		=> "Booking Time",
			
		// Message shown on checkout page if the Quantity exceeds the maximum available bookings for that date
		// Message Text: "product name" has only "X" tickets available for the date "date"
		'book.limited-booking-date-msg1'	=> " has only ",
		'book.limited-booking-date-msg2'	=> " tickets available for the date ",
		
		//Message shown on checkout page if the chosen date has been fully booked
		//Message Text: "For "product name", the date of "date" has been fully booked. Please try another date.
		'book.no-booking-date-msg1'		=> "For ",
		'book.no-booking-date-msg2'		=> ", the date of ",
		'book.no-booking-date-msg3'		=> " has been fully booked. Please try another date.",
		//Labels for partial payment addon
		'book.item-partial-total'	=> "Total ",
		'book.item-partial-deposit'	=> "Partial Deposit ",
		'book.item-partial-remaining'	=> "Amount Remaining",
		'book.partial-payment-heading'	=> "Partial Payment",
	),
	
	);
	
	
global $book_translations, $book_lang;

function book_t($str)
{
	global $book_translations, $book_lang;
	
	return $book_translations[$book_lang][$str];
}


?>