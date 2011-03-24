<h1>Coming up...</h1>
<table>

<?php
$time_start = time();
$time_end   = strtotime('+1 month', $time_start);

$list = $epguide->getEpisodes( $time_start, $time_end, 10 );
foreach($list as $date => $episodes) {
	foreach($episodes as $ep) {
		$diff = distanceOfTimeInWords( time(), $ep['time'] );
		$ep['no'] = sprintf('%1$02dx%2$02d', $ep['season'], $ep['episode']);
		echo '
		<tr>
			<td width="110">' . $diff . '</td>
			<td width="80">' . strftime("%d. %b", $ep['time']) . '</td>
			<td width="150"><a href="' . $ep['link'] . '">' . $ep['show_name'] . '</a></td>
			<td width="70">' . $ep['no'] . '</td>
			<td><a href="' . $ep['link'] . '" target="_blank">' . $ep['title'] . '</a></td>
		</tr>
		';
	}
}
?>
</table>
