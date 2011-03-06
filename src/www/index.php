<?php
include("bbTorrent/bbTorrent.class.php");
if (!class_exists("bbTorrent")) {
	die("Error: I need my bbTorrent class! Check your include path");
}
include("functions.inc.php");

$view = (isset($_GET['v']) ? $_GET['v'] : ''); 

$bbTorrent = new bbTorrent;
$bbTorrent->init();

if ($bbTorrent->isError) {
	die();
}

$epguide =& epguide::instance($bbTorrent);
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="no">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
		<meta http-equiv="Pragma" content="no-cache">
		<meta http-equiv="Cache-Control" content="no-cache, no-store">
		<title>bbTorrent - episode guide</title>
		<link rel="stylesheet" href="css/style.css" type="text/css" media="all"/>
	</head>
	<body>
		<div id="container">
			<div id="top">
				<div class="left"></div>
				<ul id="menu">
					<li><a href="?v=calendar">Calendar</a></li>
					<li><a href="?">Coming up</a></li>
					<li><a href="?v=log">Unpack log</a></li>
					<li><a href="?v=settings">Settings</a></li>
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