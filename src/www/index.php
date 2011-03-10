<?php
error_reporting(E_ALL);
include("bbTorrent/bbTorrent.class.php");
if (!class_exists("bbTorrent")) {
	die("Error: I need my bbTorrent class! Check your include path");
}
include("functions.inc.php");

$site_path = $_SERVER['PHP_SELF'];
$site_path = substr($site_path, 0, strpos($site_path, '/index.php'));

$uri = urldecode($_SERVER['REQUEST_URI']);
$uri = str_replace($site_path, "", $uri);

if ($queryPos = strpos($uri, '?'))
	$uri = substr($uri, 0, $queryPos);
$path = explode("/", $uri);
$path = array_slice($path, 1);

$view = $path[0];

$bbTorrent = new bbTorrent;
$bbTorrent->init();

if ($bbTorrent->isError) {
	die();
}
$epguide =& epguide::instance($bbTorrent);
if ($view == 'ajax') {
	$action = (isset($path[1]) ? $path[1] : '');
	$response = array();
	switch ($action) {
	case 'episodedata':
		$episode_id = (isset($path[2]) ? (int)$path[2] : false);
		$data = $epguide->getEpisodeData($episode_id);
		$response['data'] = $data;
	}
	header("Content-Type: application/json");
	echo json_encode($response);
	exit;
} else if ($view == 'popup') {
	$episode_id = (isset($path[2]) ? (int)$path[2] : false);
	$ep = $epguide->getEpisodeData($episode_id);
	/*
Array
(
    [id] => 639
    [show_id] => 7
    [type] => 0
    [season] => 9
    [episode] => 12
    [prod_id] => 
    [time] => 1299463200
    [title] => The Hand That Rocks the Wheelchair
    [time_added] => 1299499230
    [time_updated] => 1299758807
    [downloaded] => 0
    [source] => epguides.com
    [link] => http://www.tvrage.com/Family_Guy/episodes/1064920555
    [trailer] => 
    [thetvdb_episode_id] => 2902301
    [thetvdb_data] => Array
        (
            [id] => 2902301
            [seasonid] => 166581
            [episodenumber] => 12
            [episodename] => The Hand That Rocks the Wheelchair
            [firstaired] => 2011-03-06
            [gueststars] => 
            [director] => 
            [writer] => 
            [overview] => Meg offers to check on Joe while Bonnie is out of town; Stewie tries to become more evil.
            [productioncode] => 
            [lastupdated] => 1297543987
            [flagged] => 0
            [dvd_discid] => 
            [dvd_season] => 
            [dvd_episodenumber] => 
            [dvd_chapter] => 
            [absolute_number] => 
            [filename] => episodes/75978/2902301.jpg
            [seriesid] => 75978
            [mirrorupdate] => 2011-02-12 12:53:07
            [imdb_id] => 
            [epimgflag] => 2
            [rating] => 7.6
            [seasonnumber] => 9
            [language] => en
        )

	 */
	?>
	<div class="episode_info">
		<table>
			<tr>
				<th>Title:</th>
				<td><?= $ep['title'] ?></td>
			</tr>
			<tr>
				<th>Episode #:</th>
				<td>s<?= str_pad($ep['season'], 2, '0', STR_PAD_LEFT) . 'ep' . str_pad($ep['episode'], 2, '0', STR_PAD_LEFT) ?></td>
			</tr>
			<?php if ($ep['thetvdb_data']): ?>
			<tr>
				<th>Original Airdate:</th>
				<td><?= strftime("%x", strtotime($ep['thetvdb_data']['firstaired'])) ?></td>
			</tr>
			<?php endif; ?>
		</table>
		<?php if ($ep['thetvdb_data']): ?>
			<p><?= $ep['thetvdb_data']['overview'] ?></p>
		<?php endif; ?>
	</div>
	<?php 
	exit;
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="no">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
		<meta http-equiv="Pragma" content="no-cache">
		<meta http-equiv="Cache-Control" content="no-cache, no-store">
		<title>bbTorrent - episode guide</title>
		<link rel="stylesheet" href="<?= $site_path ?>/css/style.css" type="text/css" media="all"/>
		<link rel="stylesheet" href="<?= $site_path ?>/css/popup.css" type="text/css" media="all"/>
		
		<script type="text/javascript" src="<?= $site_path ?>/js/prototype.js"></script>
		<script type="text/javascript" src="<?= $site_path ?>/js/scriptaculous/scriptaculous.js?load=effects,dragdrop"></script>
		<script type="text/javascript" src="<?= $site_path ?>/js/livepipe-ui/livepipe.js"></script>
		<script type="text/javascript" src="<?= $site_path ?>/js/livepipe-ui/window.js"></script>
		
		
	</head>
	<body>
		<div id="container">
			<div id="top">
				<div class="left"></div>
				<ul id="menu">
					<li><a href="<?= $site_path ?>/calendar">Calendar</a></li>
					<li><a href="<?= $site_path ?>/">Coming up</a></li>
					<li><a href="<?= $site_path ?>/log">Unpack log</a></li>
					<li><a href="<?= $site_path ?>/settings">Settings</a></li>
				</ul>
				<div class="right"></div>
			</div>
			<div id="body">
<?php
switch ($view) {
case 'calendar':
	include 'views/calendar.inc.php';
	break;
case 'log':
	include 'views/log.inc.php';
	break;
default:
	include 'views/default.inc.php';
	break;
}
?>
			</div>
		</div>
	</body>
</html>
