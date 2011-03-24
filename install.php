#!/usr/bin/php
<?php
if (getenv('USER') != 'root') {
	die("Needs to be run as root!\n");
}
/* Check dependencies */
if (!function_exists('mysql_connect')) {
	die("MySQL is not installed!\n");
}
if (!class_exists('DateTime')) {
	die("Required library `DateTime` not installed\n");
}


$MY_PATH = dirname(__FILE__);

$LIB_PATH = '/usr/share/php';
$BIN_PATH = '/usr/local/bin';
$WWW_PATH = '/var/www/epguide';
$DATA_PATH = '/var/www/epguide_data';
$CONF_PATH = '/etc/bbtorrent.conf';

$DB_CONF = array(
	'hostname' => 'localhost',
	'username' => 'bbtorrent',
	'password' => '',
	'database' => 'bbtorrent',
	'setup'    => false
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
	echo "Do you want me to setup database structure? [yes|no]: ";
	$input = getinput();
	if ($input == 'yes') {
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
					$DB_CONF['setup'] = true;
				}
			}
			
			if (!$dbconfigured) {
				echo "\nCannot connect using provided crendetials: " . mysql_error() . "\n";
				echo "\n";
			}
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
	if ($DB_CONF['setup']) {
		echo "Database:\n";
		echo "- Username: " . $DB_CONF['username'] . "\n";
		echo "- Database: " . $DB_CONF['database'] . "\n";
	}
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
		$MY_PATH.'/src/www/htaccess'                           => '',
		$MY_PATH.'/src/www/index.php'                          => '',
		$MY_PATH.'/src/www/functions.inc.php'                  => '',
		$MY_PATH.'/src/www/css/style.css'                      => '/css',
		$MY_PATH.'/src/www/css/popup.css'                      => '/css',
		$MY_PATH.'/src/www/gfx/bg_body.png'                    => '/gfx',
		$MY_PATH.'/src/www/gfx/bg_menu_c.png'                  => '/gfx',
		$MY_PATH.'/src/www/gfx/bg_menu_l.png'                  => '/gfx',
		$MY_PATH.'/src/www/gfx/bg_menu_r.png'                  => '/gfx',
		$MY_PATH.'/src/www/views/default.inc.php'              => '/views',
		$MY_PATH.'/src/www/views/calendar.inc.php'             => '/views',
		$MY_PATH.'/src/www/views/log.inc.php'                  => '/views',
		$MY_PATH.'/src/www/views/settings.inc.php'             => '/views',
		
		$MY_PATH.'/src/www/js/prototype.js'			           => '/js',
		$MY_PATH.'/src/www/js/livepipe-ui/livepipe.js'         => '/js/livepipe-ui',
		$MY_PATH.'/src/www/js/livepipe-ui/window.js'           => '/js/livepipe-ui',
		$MY_PATH.'/src/www/js/scriptaculous/scriptaculous.js'  => '/js/scriptaculous',
		$MY_PATH.'/src/www/js/scriptaculous/effects.js'        => '/js/scriptaculous'
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
				mkdir($dstdir, 0755, true);
			}
			$dstfile = $dstdir . '/' . basename($srcfile);
			echo "  `".basename($srcfile) . "` -> `$dstfile`\n";
			copy($srcfile, $dstfile);
		}
	}
	if ($DB_CONF['setup']) {
		echo "Installing database...\n";
	
		$cmd = 'mysql -h' . $DB_CONF['hostname'] . ' -u' . $DB_CONF['username'] . ' -p' . $DB_CONF['password'] . " " . $DB_CONF['database'] . ' < ';
		$cmd .= $MY_PATH.'/src/bbtorrent.sql';
		exec($cmd);
	}
	
	echo "Installing config...\n";
	$config = getConfig();
	$fp = fopen($CONF_PATH, 'w');
	fwrite($fp, $config);
	fclose($fp);
	
	echo "\nAll done!\n";
}

function getinput() {
	$str = fread(STDIN, 1024);
	return str_replace("\n", '', trim($str));
}


function getConfig() {
	global $DATA_PATH,$DB_CONF;
$conf = '
;
; bbTorrent config
;
[global]
  log_file = "/tmp/bbtorrent.log"
;; 0: INFO
;; 1: NOTICE
;; 2: WARNING
;; 3: ERROR
  log_level = 0
  debug    = 0
  verbose  = 0
  locale   = "nb_NO.utf8"

[database]
  host     = "' . $DB_CONF['hostname'] . '"
  username = "' . $DB_CONF['username'] . '"
  password = "' . $DB_CONF['password'] . '"
  database = "' . $DB_CONF['database'] . '"

[unpack]
  enabled        = 1
  target         = "/media/video"
  watchfolder    = "/home/torrent/.watch"
;  chmod          = "0775"
;  chown          = "www-data.www-data"
  extract_cmd    = "unrar e %from% %to%"
  rename         = 1

;; %1 = season (int)
;; %2 = episode (int)
;; %3 = episode title (string)
;; %4 = show title (string)
  rename_pattern = "%1$d%2$02d - %3$s"

;; Use sudo for mkdir/mv/extract_cmd.. Make sure it doesn\'t ask for password!
  sudo           = 0

[rssfeeds]
  enabled     = 1
  tracker[]   = "torrentleech"
  tracker[]   = "norbits"

[tracker_torrentleech]
   name = "torrentleech"
   url  = "http://rss.torrentleech.org/your_hash"
   mark = "/tmp/tl-rss-mark"

[tracker_norbits]
   name = "norbits"
   url  = "http://www.norbits.net/rss.php"
   mark = "/tmp/norbits-rss-mark"

[epguide]

   data_path = "' . $DATA_PATH . '"

;; epguides.com
;; 
;; Parses epguides.com/(show_name) for all shows defined in `epguide_shows` table
;; the `alias` column is used if no match is found (i.e. alias "Lost" for "Lost (2001)")
;; No further config needed
;; 
  name        = "epguides_com"

;;
;; www.myepisodes.com
;; 1) Sign up (for free)
;; 2) Set up your shows (My shows -> Change My Shows List)
;; 3) Set your time zone to US/Central (Profile -> Control Panel)
;;

;  name        = "my_episodes"
;  uid         = "uid"
;  hash        = "00000000000000000000000000000000"
';
	return $conf;
}
