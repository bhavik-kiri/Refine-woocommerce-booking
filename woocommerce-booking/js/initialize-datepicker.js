function ad(dateObj, numDays)
{
	return dateObj.setDate(dateObj.getDate() + numDays);
}

function gd(date1, date2, interval)
{
	var second = 1000,
	minute = second * 60,
	hour = minute * 60,
	day = hour * 24,
	week = day * 7;
	date1 = new Date(date1).getTime();
	date2 = (date2 == 'now') ? new Date().getTime() : new Date(date2).getTime();
	var timediff = date2 - date1;
	if (isNaN(timediff)) return NaN;
		switch (interval) {
		case "years":
			return date2.getFullYear() - date1.getFullYear();
		case "months":
			return ((date2.getFullYear() * 12 + date2.getMonth()) - (date1.getFullYear() * 12 + date1.getMonth()));
		case "weeks":
			return Math.floor(timediff / week);
		case "days":
			return Math.floor(timediff / day);
		case "hours":
			return Math.floor(timediff / hour);
		case "minutes":
			return Math.floor(timediff / minute);
		case "seconds":
			return Math.floor(timediff / second);
		default:
			return undefined;
	}
}

function nd(date)
{
	var m = date.getMonth(), d = date.getDate(), y = date.getFullYear();
	var currentdt = m + '-' + d + '-' + y;
	
	var dt = new Date();
	var today = dt.getMonth() + '-' + dt.getDate() + '-' + dt.getFullYear();
	
	var holidayDates = eval('['+jQuery("#booking_holidays").val()+']');

	var globalHolidays = eval('['+jQuery("#booking_global_holidays").val()+']');
	
	for (iii = 0; iii < globalHolidays.length; iii++)
	{
		if( jQuery.inArray(d + '-' + (m+1) + '-' + y,globalHolidays) != -1 )
		{
			return [false,"","Holiday"];
		}
	}
	for (ii = 0; ii < holidayDates.length; ii++)
	{
		if( jQuery.inArray(d + '-' + (m+1) + '-' + y,holidayDates) != -1 )
		{
			return [false, "", "Holiday"];
		}
	}
	return [true];
}

function sp(date)
{
	var m = date.getMonth(), d = date.getDate(), y = date.getFullYear();
	var currentdt = m + '-' + d + '-' + y;
	
	var dt = new Date();
	var today = dt.getMonth() + '-' + dt.getDate() + '-' + dt.getFullYear();
	
	var deliveryDates = eval('['+jQuery("#wapbk_booking_dates").val()+']');

	for (ii = 0; ii < deliveryDates.length; ii++)
	{
		if( jQuery.inArray(d + '-' + (m+1) + '-' + y,deliveryDates) != -1 )
		{
			return [true];
		}
	}
	return [false];
}

function avd(date)
{
	var delay_days = parseInt(jQuery("#wapbk_minimumOrderDays").val());
	var noOfDaysToFind = parseInt(jQuery("#wapbk_number_of_dates").val());
	var recurring_days = jQuery("#wapbk_recurringDays").val();
	var specific_dates = jQuery("#wapbk_specificDates").val();
	
	if(isNaN(delay_days))
	{
		delay_days = 0;
	}
	if(isNaN(noOfDaysToFind))
	{
		noOfDaysToFind = 30;
	}

	var minDate = delay_days;
	var date = new Date();
	var t_year = date.getFullYear();
	var t_month = date.getMonth()+1;
	var t_day = date.getDate();
	var t_month_days = new Date(t_year, t_month, 0).getDate();
	
	var s_day = new Date( ad( date , delay_days ) );
	start = (s_day.getMonth()+1) + "/" + s_day.getDate() + "/" + s_day.getFullYear();
	var start_month = s_day.getMonth()+1;
	
	var end_date = new Date( ad( s_day , noOfDaysToFind ) );
	end = (end_date.getMonth()+1) + "/" + end_date.getDate() + "/" + end_date.getFullYear();
	//Calculate the last specific date
	var specific_max_date = start;
	var m = date.getMonth(), d = date.getDate(), y = date.getFullYear();
	var currentdt = m + '-' + d + '-' + y;
	
	var dt = new Date();
	var today = dt.getMonth() + '-' + dt.getDate() + '-' + dt.getFullYear();
	
	var deliveryDates = eval('['+jQuery("#wapbk_booking_dates").val()+']');
	
	for (ii = 0; ii < deliveryDates.length; ii++)
		{
			var split = deliveryDates[ii].split('-');
			var specific_date = split[1] + '/' + split[0] + '/' + split[2];
			var diff = gd(specific_max_date , specific_date , 'days');
			if (diff >= 0)
				{
				specific_max_date = specific_date;
				}
		}
	var loopCounter = gd(start , end , 'days');
	var prev = s_day;
	var new_l_end, is_holiday;
	for(var i=1; i<=loopCounter; i++)
	{
		var l_start = new Date(start);
		var l_end = new Date(end);
		new_l_end = l_end;
		var new_date = new Date(ad(l_start,i));

		var day = "";
		if (jQuery("#wapbk_multiple_day_booking").val() == '')
		{
			day = 'booking_weekday_' + new_date.getDay();
			day_check = jQuery("#wapbk_"+day).val();
			is_specific = sp(new_date);
		}
		else 
		{
			is_specific = 'true';
			day_check = 'on';
		}
		is_holiday = nd(new_date);

		if (is_specific == "false" || is_holiday != "true")
		{
			if( day_check != "on" || is_holiday != "true")
			{
				new_l_end = l_end = new Date(ad(l_end,1));
				end = (l_end.getMonth()+1) + "/" + l_end.getDate() + "/" + l_end.getFullYear();
				if (recurring_days != "on" && specific_dates == "on")
				{
					diff = gd(l_end , specific_max_date , 'days');
					if (diff >= 0)
					{
						loopCounter = gd(start , end , 'days');
					}
				}
				else
				{
					loopCounter = gd(start , end , 'days');
				}
			}
		}
	}
		return {
			minDate: minDate,
	        maxDate: l_end
	    };
}