<?php
$weekdays = array('booking_weekday_0' => 'Sunday',
				  'booking_weekday_1' => 'Monday',
				  'booking_weekday_2' => 'Tuesday',
				  'booking_weekday_3' => 'Wednesday',
				  'booking_weekday_4' => 'Thursday',
				  'booking_weekday_5' => 'Friday',
				  'booking_weekday_6' => 'Saturday'
				);
$days = array('0' => 'Sunday',
			  '1' => 'Monday',
			  '2' => 'Tuesday',
						'3' => 'Wednesday',
						'4' => 'Thursday',
						'5' => 'Friday',
						'6' => 'Saturday'
				);
				
				$from_slot_hrs = array();
				$from_slot_min = array();
				$to_slot_hrs = array();
				$to_slot_min = array();
				$time_note = array();
				$lockout_time = array();
				
				$languages = array(
							 'af' => 'Afrikaans',
							 'ar' => 'Arabic',
							 'ar-DZ' => 'Algerian Arabic',
							 'az' => 'Azerbaijani',
							 'id' => 'Indonesian',
							 'ms' => 'Malaysian',
							 'nl-BE' => 'Dutch Belgian',
							 'bs' => 'Bosnian',
							 'bg' => 'Bulgarian',
							 'ca' => 'Catalan',
							 'cs' => 'Czech',
							 'cy-GB' => 'Welsh',
							 'da' => 'Danish',
							 'de' => 'German',
							 'et' => 'Estonian',
							 'el' => 'Greek',
							 'en-AU' => 'English Australia',
							 'en-NZ' => 'English New Zealand',
							 'en-GB' => 'English UK',
							 'es' => 'Spanish',
							 'eo' => 'Esperanto',
							 'eu' => 'Basque',
							 'fo' => 'Faroese',
							 'fr' => 'French',
							 'fr-CH' => 'French Swiss',
							 'gl' => 'Galician',
							 'sq' => 'Albanian',
							 'ko' => 'Korean',
							 'hi' =>'Hindi India',
							 'hr' => 'Croatian',
							 'hy' => 'Armenian',
							 'is' => 'Icelandic',
							 'it' => 'Italian',
							 'ka' => 'Georgian',
							 'km' => 'Khmer',
							 'lv' => 'Latvian',
							 'lt' => 'Lithuanian',
							 'mk' => 'Macedonian',
							 'hu' => 'Hungarian',
							 'ml' => 'Malayam',
							 'nl' => 'Dutch',
							 'ja'=> 'Japanese',
							 'no' => 'Norwegian',
							 'th' => 'Thai',
							 'pl' => 'Polish',
							 'pt' => 'Portuguese',
							 'pt-BR' => 'Portuguese Brazil',
							 'ro' => 'Romanian',
							 'rm' => 'Romansh',
							 'ru' => 'Russian',
							 'sk' => 'Slovak',
							 'sl' => 'Slovenian',
							 'sr' => 'Serbian',
						//	 'sr-SR' => 'Serbian',
							 'fi' => 'Finnish',
							 'sv' => 'Swedish',
							 'ta' => 'Tamil',
							 'vi' => 'Vietnamese',
							 'tr' => 'Turkish',
							 'uk' => 'Ukrainian',
							 'zh-HK' => 'Chinese Hong Kong',
							 'zh-CN' => 'Chinese Simplified',
							 'zh-TW' => 'Chinese Traditional');
				
				$date_formats = array('mm/dd/y' => 'm/d/y',
										  'dd/mm/y' => 'd/m/y',
										  'y/mm/dd' => 'y/m/d',
										  'dd.mm.y' => 'd.m.y',
										  'y.mm.dd' => 'y.m.d',
										  'yy-mm-dd' => 'Y-m-d',
										  'dd-mm-y' => 'd-m-y',
										  'd M, y' => 'j M, y',
										  'd M, yy' => 'j M, Y',
										  'd MM, y' => 'j F, y',
										  'd MM, yy' => 'j F, Y',
										  'DD, d MM, yy' => 'l, j F, Y',
										  'D, M d, yy' => 'D, M j, Y',
										  'DD, M d, yy' => 'l, M j, Y',
										  'DD, MM d, yy' => 'l, F j, Y',
										  'D, MM d, yy' => 'D, F j, Y');
										
				$time_formats = array('12' => '12 hour',
											'24' => '24 hour');
										  
				$calendar_themes = array('smoothness' => 'Smoothness',
						'ui-lightness' => 'UI lightness',
						'ui-darkness' => 'UI darkness',
						'start' => 'Start',
						'redmond' => 'Redmond',
						'sunny' => 'Sunny',
						'overcast' => 'Overcast',
						'le-frog' => 'Le Frog',
						'flick' => 'Flick',
						'pepper-grinder' => 'Pepper Grinder',
						'eggplant' => 'Eggplant',
						'dark-hive' => 'Dark Hive',
						'cupertino' => 'Cupertino',
						'south-street' => 'South Street',
						'blitzer' => 'Blitzer',
						'humanity' => 'Humanity',
						'hot-sneaks' => 'Hot sneaks',
						'excite-bike' => 'Excite Bike',
						'vader' => 'Vader',
						'dot-luv' => 'Dot Luv',
						'mint-choc' => 'Mint Choc',
						'black-tie' => 'Black Tie',
						'trontastic' => 'Trontastic',
						'swanky-purse' => 'Swanky Purse'
				);

	global $calendar_themes, $time_formats, $date_formats, $languages, $days, $weekdays;

	function book_arrays($str)
	{
		global $calendar_themes, $time_formats, $date_formats, $languages, $days, $weekdays;
	
		return $$str;
	}
?>