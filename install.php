#!/usr/bin/php
<?php
if (getenv('USER') != 'root') {
	die("Needs to be run as root!\n");
}

$MY_PATH = dirname(__FILE__);

$LIB_PATH = '/usr/share/php';
$BIN_PATH = '/usr/local/bin';
$WWW_PATH = '/var/www/epguide';
$DATA_PTH = '/var/www/epguide_data';
$CONF_PATH = '/etc/bbtorrent.conf';

$DB_CONF = array(
	'hostname' => 'localhost',
	'username' => 'bbtorrent',
	'password' => '',
	'database' => 'bbtorrent'
);

$configured = false;

while (!$configured) {
	echo "Please enter paths for where we'll install bbTorrent libraries and binaries\n";
	echo "If you are unsure, just press enter to keep default values\n\n";
	
	echo "Install path [$LIB_PATH]: ";
	$input = getinput();
	if (!empty($input)) {
		$LIB_PATH = $input;
	}
	
	echo "Binary path [$BIN_PATH]: ";
	$input = getinput();
	if (!empty($input)) {
		$BIN_PATH = $input;
	}
	/*
	echo "Config path [$CONF_PATH]: ";
	$input = getinput();
	if (!empty($input)) {
		$CONF_PATH = $input;
	}
	*/
	echo "Install episode guide? [yes|no]: ";
	$input = getinput();
	if ($input == 'yes') {
		echo "Episode guide path [$WWW_PATH]: ";
		$input = getinput();
		if (!empty($input)) {
			$WWW_PATH = $input;
		}
		echo "Data path [$DATA_PATH]: ";
		$input = getinput();
		if (!empty($input)) {
			$DATA_PATH = $input;
		}
	} else {
		$WWW_PATH = '';
	}
	echo "\n";
	echo "Please enter your database host and credentials..\n";
	$dbconfigured = false;
	while (!$dbconfigured) {
		echo "Hostname [" . $DB_CONF['hostname'] . "]: ";
		$input = getinput();
		if (!empty($input)) {
			$DB_CONF['hostname'] = $input;
		}
		echo "Username [" . $DB_CONF['username'] . "]: ";
		$input = getinput();
		if (!empty($input)) {
			$DB_CONF['username'] = $input;
		}
		echo "Password [" . $DB_CONF['password'] . "]: ";
		$input = getinput();
		if (!empty($input)) {
			$DB_CONF['password'] = $input;
		}
		echo "Database name [" . $DB_CONF['database'] . "]: ";
		$input = getinput();
		if (!empty($input)) {
			$DB_CONF['database'] = $input;
		}
		
		if (@mysql_connect($DB_CONF['hostname'], $DB_CONF['username'], $DB_CONF['password'])) {
			if (@mysql_select_db($DB_CONF['database'])) {
				$dbconfigured = true;
			}
		}
		
		if (!$dbconfigured) {
			echo "\nCannot connect using provided crendetials: " . mysql_error() . "\n";
			echo "\n";
		}
	}
	echo "\n";
	echo "Current configuration: \n";
	echo "Install paths:\n";
	echo "- `$LIB_PATH`\n";
	echo "- `$BIN_PATH`\n";
	if (!empty($WWW_PATH)) {
		echo "- `$WWW_PATH`\n";
	}
	echo "Database:\n";
	echo "- Username: " . $DB_CONF['username'] . "\n";
	echo "- Database: " . $DB_CONF['database'] . "\n";
	echo "\n";
	
	echo "Proceed with this configuration? [yes|no]: ";
	$input = getinput();
	if ($input == 'yes') {
		$configured = true;
	}
	
	passthru("clear");
	
	if ($configured) {
		install();
	}
}


function install() {
	global $MY_PATH,$LIB_PATH,$BIN_PATH,$CONF_PATH,$WWW_PATH,$DB_CONF;
	
	$src_files = array();
	$src_files['lib'] = array(
		$MY_PATH.'/src/lib/bbTorrent.class.php'             => '/bbTorrent',
		$MY_PATH.'/src/lib/epguide.generic.class.php'       => '/bbTorrent',
		$MY_PATH.'/src/lib/epguide.my_episodes.class.php'   => '/bbTorrent',
		$MY_PATH.'/src/lib/epguide.epguides_com.class.php'  => '/bbTorrent',
		$MY_PATH.'/src/lib/rssfeed.generic.class.php'       => '/bbTorrent',
		$MY_PATH.'/src/lib/rss_php.php'                     => ''
	);
	$src_files['bin'] = array(
		$MY_PATH.'/src/bin/bbtorrent' => ''
	);
	$src_files['www'] = array(
		$MY_PATH.'/src/www/index.php'               => '',
		$MY_PATH.'/src/www/functions.inc.php'       => '',
		$MY_PATH.'/src/www/css/style.css'           => '/css',
		$MY_PATH.'/src/www/gfx/bg_body.png'         => '/gfx',
		$MY_PATH.'/src/www/gfx/bg_menu_c.png'       => '/gfx',
		$MY_PATH.'/src/www/gfx/bg_menu_l.png'       => '/gfx',
		$MY_PATH.'/src/www/gfx/bg_menu_r.png'       => '/gfx',
		$MY_PATH.'/src/www/views/default.inc.php'   => '/views',
		$MY_PATH.'/src/www/views/calendar.inc.php'  => '/views',
		$MY_PATH.'/src/www/views/log.inc.php'       => '/views',
		$MY_PATH.'/src/www/views/settings.inc.php'  => '/views'
	);
	
	echo "Installing libraries...\n";
	if (!file_exists($LIB_PATH)) {
		mkdir($LIB_PATH);
	}
	foreach($src_files['lib'] as $srcfile => $dir) {
		$dstdir = $LIB_PATH . $dir; 
		if (!file_exists($dstdir)) {
			mkdir($dstdir);
		}
		$dstfile = $dstdir . '/' . basename($srcfile);
		echo "  `".basename($srcfile) . "` -> `$dstfile`\n";
		copy($srcfile, $dstfile);
	}
	echo "Installing binaries...\n";
	if (!file_exists($BIN_PATH)) {
		mkdir($BIN_PATH);
	}
	foreach($src_files['bin'] as $srcfile => $dir) {
		$dstdir = $BIN_PATH . $dir;
		if (!file_exists($dstdir)) {
			mkdir($dstdir);
		}
		$dstfile = $dstdir . '/' . basename($srcfile);
		echo "  `".basename($srcfile) . "` -> `$dstfile`\n";
		copy($srcfile, $dstfile);
		$cmd = "chmod a+x " . escapeshellarg($dstfile);
		passthru($cmd);
	}
	if (!empty($WWW_PATH)) {
		echo "Installing episode guide files...\n";
		if (!file_exists($WWW_PATH)) {
			mkdir($WWW_PATH);
		}
		foreach($src_files['www'] as $srcfile => $dir) {
			$dstdir = $WWW_PATH . $dir;
			if (!file_exists($dstdir)) {
				mkdir($dstdir);
			}
			$dstfile = $dstdir . '/' . basename($srcfile);
			echo "  `".basename($srcfile) . "` -> `$dstfile`\n";
			copy($srcfile, $dstfile);
		}
	}
	
	echo "Installing database...\n";
	
	$cmd = 'mysql -h' . $DB_CONF['hostname'] . ' -u' . $DB_CONF['username'] . ' -p' . $DB_CONF['password'] . " " . $DB_CONF['database'] . ' < ';
	$cmd .= $MY_PATH.'/src/bbtorrent.sql';
	exec($cmd);
	
	echo "Installing config...\n";
	$srcfile = $MY_PATH . '/src/bbtorrent.conf';
	$dstfile = $CONF_PATH;
	echo "  `".basename($srcfile) . "` -> `$dstfile`\n";
	copy($srcfile, $dstfile);
	
	echo "\nAll done!\n";
	echo "Please update database section of your config file:\n";
	echo "  [database]\n";
	echo "    host     = \"" . $DB_CONF['hostname'] . "\"\n";
	echo "    username = \"" . $DB_CONF['username'] . "\"\n";
	echo "    password = \"" . $DB_CONF['password'] . "\"\n";
	echo "    database = \"" . $DB_CONF['database'] . "\"\n";
	echo "\n";
	
}

function getinput() {
	$str = fread(STDIN, 1024);
	return str_replace("\n", '', trim($str));
}
