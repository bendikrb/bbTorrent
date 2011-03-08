<?php
if (!isset($epguide)) {
	die();
}

$month = (isset($_GET['month']) ? $_GET['month'] : date('m'));
$year = (isset($_GET['year']) ? $_GET['year'] : date('Y'));

$timestamp = mktime(0,0,0, $month, 1, $year);

$time_start = firstWeekDay( $timestamp );
$time_end   = lastWeekDay( $timestamp );
$cal_list = $epguide->getEpisodes( $time_start, $time_end );

echo '<h1>Calendar</h1>';

echo '<h2>
<a href="?v=calendar&month=' . date('m', strtotime('-1 month', $timestamp)) . '&year=' . date('Y', strtotime('-1 month', $timestamp)) . '">&laquo;</a>
<a href="?v=calendar&month=' . date('m', strtotime('+1 month', $timestamp)) . '&year=' . date('Y', strtotime('+1 month', $timestamp)) . '">&raquo;</a>
' . ucwords( strftime("%B %G", $timestamp) ) . '</h2>';

echo '<table class="calendar" id="obj_calendar">' . "\n";
echo "<tr>\n\t<td>&nbsp;</td>\n";
for($i=342000;$i<=860400;$i+=86400) {
	echo "\t<td class=\"weekday\">" . strftime("%A", $i) . "</td>\n";
}
echo "</tr>\n";
$thisMonth = date('m', $timestamp);
$curTime = $time_start;
$today = mktime(0,0,0, date('m'), date('d'), date('y'));
$lastweek = 0;
while ( $curTime <= $time_end ) {
	$week = date('W', $curTime);
	if ( $week != $lastweek ) {
		if ( $lastweek != 0)
			echo "</tr>\n";
		echo "<tr class=\"row\">\n\t<td class=\"week\" date=\"" . date('d-m-y', $curTime) . "\" week=\"$week\">" . (int)$week . "</td>\n";
		$lastweek = $week;
	}
	$class = 'day';
	if ( date('m', $curTime) != $thisMonth )
		$class .= ' disabled';
	if ( date('N', $curTime) == 7 && strpos($class, 'disabled') === false )
		$class .= ' red';
	if ( $curTime == $today )
		$class .= ' today';
	echo "\t<td class=\"$class\" date=\"" . date('d-m-y', $curTime) . "\">";
	echo "<div class=\"date\">" . (int) date('d', $curTime) . '.</div>';
	if (isset($cal_list[$curTime]) ) {
		echo '<ul class="list">';
		foreach ($cal_list[$curTime] as $time => $row) {
			$text = $row['show_name'] . ' s' . str_pad($row['season'],2,'0',STR_PAD_LEFT) . 'e' . str_pad($row['episode'],2,'0',STR_PAD_LEFT);
			$diff = '';
			if (time() < $row['time']) {
				$diff = ' in about ' . distanceOfTimeInWords( time(), $row['time'] );
			}
			echo '<li><a href="' . $row['link'] . '" title="' . $text . $diff . '">' . str_compress($text,15) . '</a></li>';
		}
		echo '</ul>';
	}
	echo "</td>\n";
	$curTime = strtotime('+1 day', $curTime);
	if ( $curTime > $time_end )
		echo "</tr>\n";
}
echo '</table>';
?>