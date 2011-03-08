<?php 
function firstWeekDay( $timestamp ) {
	$day   = 1;
	$month = date('m', $timestamp);
	$year  = date('Y', $timestamp);
	$timestamp = mktime(0,0,0, $month, 1, $year );
	$weekDay = date('N', $timestamp);
	$lastMonth = strtotime('-1 day', $timestamp);
	if ( $weekDay > 1 ) {
		$day   = ( date('d', $lastMonth) - ($weekDay-2) );
		$month = date('m', $lastMonth );
		$year  = date('Y', $lastMonth );
	}
	return mktime(0,0,0, $month, $day, $year );
}
function lastWeekDay( $timestamp ) {
	$day   = date('t', $timestamp);
	$month = date('m', $timestamp);
	$year  = date('Y', $timestamp);
	$timestamp = mktime(0,0,0,$month,$day,$year);
	$weekDay = date('N', $timestamp);
	$nextMonth = strtotime('+1 day', $timestamp);
	if ( $weekDay < 7 ) {
		$day   = (7 - $weekDay);
		$month = date('m', $nextMonth);
		$year  = date('Y', $nextMonth);
	}
	return mktime(0,0,0, $month, $day, $year);
}
function substring($str, $len, $trail = '') {
	if ( strlen($str) > $len ) {
		return substr($str,0,$len) . $trail;
	}
	return $str;
}

function str_compress($str, $len, $glue = '...') {
	$strlen = strlen($str);
	if ($strlen > $len) {
		$center = ($strlen/2);
		$cut = (($strlen-$len)/2);
		$a = substr($str, 0, $center-$cut );
		$b = substr($str, -($len/2));
		return $a.$glue.$b;
	}
	return $str;
}

function distanceOfTimeInWords($fromTime, $toTime = 0, $showLessThanAMinute = false) {
	$distanceInSeconds = round(abs($toTime - $fromTime));
	$distanceInMinutes = round($distanceInSeconds / 60);
	
	if ( $distanceInMinutes <= 1 ) {
		if ( !$showLessThanAMinute ) {
			return ($distanceInMinutes == 0) ? 'n� nettopp' : '1 minutt';
		} else {
			if ( $distanceInSeconds < 5 )
				return 'mindre enn 5 sekund';
			if ( $distanceInSeconds < 10 )
				return 'mindre enn 10 sekund';
			if ( $distanceInSeconds < 20 )
				return 'mindre enn 20 sekund';
			if ( $distanceInSeconds < 40 )
				return 'halvt minutt';
			if ( $distanceInSeconds < 60 )
				return 'mindre enn ett minutt';
			
			return '1 minutt';
		}
	}
	if ( $distanceInMinutes < 45 )
		return $distanceInMinutes . ' min';
	if ( $distanceInMinutes < 90 )
		return '~ 1 hour';
	if ( $distanceInMinutes < 1440 )
		return round(floatval($distanceInMinutes) / 60.0) . ' hours';
	if ( $distanceInMinutes < 2880 )
		return '1 day';
	if ( $distanceInMinutes < 43200 )
		return round(floatval($distanceInMinutes) / 1440) . ' days';
	if ( $distanceInMinutes < 86400 )
		return '~ 1 mon';
	if ( $distanceInMinutes < 525600 )
		return round(floatval($distanceInMinutes) / 43200) . ' mon';
	if ( $distanceInMinutes < 1051199 )
		return '~ 1 year';
	
	return 'over ' . round(floatval($distanceInMinutes) / 525600) . ' years';
}
