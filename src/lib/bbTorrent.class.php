<?php
define('BBTORRENT_DEFAULT_CONFIG', '/etc/bbtorrent.conf');

include "epguide.generic.class.php";
include "rssfeed.generic.class.php";

class bbTorrent {

	var $config;
	var $options;
	
	var $isError = false;
	
	var $_log;
	var $_db;
	
	public function __construct() {
		register_shutdown_function(array($this, 'shutdown'));
	}
	
	public function init($argv = array()) {
		$config_file = BBTORRENT_DEFAULT_CONFIG;
		
		if ($this->isCli()) {
			//foreach($argv as $key=>$val) {
			//	$argv[$key] = strtolower($val);
			//}
			
			$params = array();
			$params['c::'] = 'Config';
			$params['v'] = 'Verbose';
			$params['e'] = 'Extract';
			$params['u'] = 'Update';
	
			$options = getopt(implode('', array_keys($params)) );
			/* Remove opts from argv */
			$pruneargv = array();
			foreach ($options as $option => $value) {
			        foreach ($argv as $key => $chunk) {
			                $regex = '/^'. (isset($option[1]) ? '--' : '-') . $option . '/';
			                if ($chunk == $value && $argv[$key-1][0] == '-' || preg_match($regex, $chunk)) {
			                        array_push($pruneargv, $key);
			                }
			        }
			}
			while ($key = array_pop($pruneargv)) unset($argv[$key]);
			
			/* Reset argv keys */
			unset($argv[0]);
			$argv = array_merge(array(),$argv);
			if (isset($options['c']) && !empty($options['c'])) {
			        $config_file = $options['c'];
			}
		}
		if (!file_exists($config_file)) {
			$this->setError('Unable to read config file: ' . $config_file);
		}
		$this->setConfig($config_file, $options);
		
		/* Check dependencies */
		if ($this->getConfig('rssfeeds', 'enabled') == '1' && !function_exists("curl_init")) {
			$this->setError("Curl module not found! Please install;");
			$this->setError(" $ sudo apt-get install php5-curl");
			return false;
		}
		
		$this->log("Start");
		
		$this->dbConnect();
		
		return $argv;
	}
	
	public function setConfig($config_ini = "", $options = array()) {
		$defaultConfig = array(
			'global' => array(
				'log_file'  => '',
				'log_level' => '',
				'debug'     => 0
			),
			'database' => array(
				'host'     => 'localhost',
				'username' => 'test',
				'password' => '',
				'database' => ''
			),
			'unpack' => array(
				'enabled'     => 1,
				'target'      => '/mnt/video',
				'watchfolder' => '/home/rtorrent/.watchfolder',
				'chmod'       => '0775',
				'chown'       => '',
				'extract_cmd' => 'unrar e %from% %to%',
				'rename'      => 1,
				'rename_pattern' => '%3$s - %1$d%2$02d - %4$s',
				'sudo'        => 0
			),
			'rssfeeds' => array(
				'enabled'     => 0,
				'tracker'     => array()
			),
			'epguide' => array(
				'name' => 'epguides_com'
			)
		);
		if (!is_array($config_ini) && !empty($config_ini)) {
			$config_ini = @parse_ini_file($config_ini, true);
		}
		
		/* Merge from config file */
		$config = $this->_mergeArray($defaultConfig, (array)$config_ini);
		
		/* Merge from runtime opts */
		if (isset($options['l']))
			$config['global']['log_file'] = $options['l'];
		if (isset($options['v']))
			$config['global']['verbose'] = 1;
		
		
		setlocale( LC_ALL, $config['global']['locale'] );
		$this->config = $config;
	}
	
	public function getConfig($section, $key = false) {
		if (!isset($this->config[$section]) || ($key !== false && !isset($this->config[$section][$key]))) {
			return '';
		}
		if ($key) {
			return $this->config[$section][$key];
		} else {
			return $this->config[$section];
		}
	}
	
	/*
	 * TODO?: move to seperate class(mysql/sqlite etc)
	 */
	public function dbConnect() {
		$conf = $this->getConfig('database');
		if (! $this->_db = @mysql_connect($conf['host'], $conf['username'], $conf['password'])) {
			$this->setError( "MySQL: " . mysql_error() );
			return;
		}
		if (! mysql_select_db($conf['database'], $this->_db)) {
			$this->setError( "MySQL: " . mysql_error() );
		}
		return $this->_db;
	}

	public function setError($err) {
		$this->isError = true;
		$this->log($err, E_USER_ERROR);
	}

	public function log($str, $level = E_ALL) {
		$loglevelstr = 'INFO';
		if ($level <= E_USER_NOTICE)
			$loglevelstr = 'NOTICE';
		if ($level <= E_USER_WARNING)
			$loglevelstr = 'WARNING';
		if ($level <= E_USER_ERROR)
			$loglevelstr = 'ERROR';
		
		$trace = $this->_debugBackTraceString( debug_backtrace(false) );
		
		$logline = date('Y-m-d H:i:s') . "\t" . $loglevelstr . "\t" . $str;
		if (!is_resource($this->_log)) {
        	$this->_log = @fopen($this->getConfig('global','log_file'), 'a');
        }
        if (is_resource($this->_log)) {
        	fwrite($this->_log, $logline . "\n");
        }
        if ($this->getConfig('global', 'verbose') || $this->isError) {
        	echo $logline . "\n";
        }
	}
	
	
	public function shutdown() {
		$this->log("Done");
		if (is_resource($this->_log)) {
			fclose($this->_log);
		}
	}
	
	public function checkUnpackConfig($conf) {
		/* Can write to target folder? */
		if (!is_writable($conf['target'])) {
			$this->log("Unable to write to target folder: '" . $conf['target'] . "'");
			return false;
		}
		return true;
	}
	
	
	/**
	 * 
	 */
	public function unpack($path = false, $target = false) {
		$conf = $this->getConfig('unpack');
		if (!$path) {
			$path = getcwd();
		}
		
		if (!file_exists($path)) {
			$this->log("Cannot unpack `$path`: No such file or directory");
			return false;
		}
		if ($target === false) {
			$target = $conf['target'];
		} else {
			$target = realpath($target);
		}
		
		$conf['target'] = $target;
		if (!$this->checkUnpackConfig($conf)) {
			return false;
		}
		
		$this->log("Unpacking files from: '$path'");
		$archives = $this->findArchives($path);
		if (count($archives) == 0) {
			$this->log("No archives found");
			return;
		}
		
		$this->log('Attempting to identify episode');
		$match = false;
		
		$epguide =& epguide::instance($this);
		foreach ($archives as $k => $archive) {
        	
			if ( ($show = $epguide->match('show_title', $archive['ufilename'])) !== false ) {
				//$show_id = $show[0];
				//$show_title = $show[1];
				$show_id    = $show['id'];
				$show_title = $show['title'];
				$this->log("Match, show #$show_id: $show_title");
				$match = true;
				
				/* Extend archive array */
				$archives[$k]['show_name'] = $show_title;
				$archives[$k]['target']    = $target . '/Series/' . $show_title . '/';
				
				if ( ($ep = $epguide->match('episode', $archive['filename'], $show_id)) !== false) {
					$this->log('Match: s' . $ep['season'] . 'e' . $ep['episode'] . ': ' . $ep['title']);
					
					/* Extend archive array */
					$archives[$k]['title']   = $ep['title'];
					$archives[$k]['season']  = $ep['season'];
					$archives[$k]['episode'] = $ep['episode'];
					$archives[$k]['target'] .= 'Season ' . str_pad($ep['season'],2,'0',STR_PAD_LEFT) . '/';
					
					if ($conf['rename_pattern']) {
						$target_filename = sprintf($conf['rename_pattern'],
								$ep['season'],
								$ep['episode'],
								$ep['title'],
								$show_title
							);
					} else {
						$target_filename = $ep['season'] . str_pad($ep['episode'], 2, '0', STR_PAD_LEFT) . ' - ' . $archives[$k]['title'];
					}
					$target_filename .= substr($archive['ufilename'], strrpos($archive['ufilename'], '.'));
					
					$archives[$k]['target_fname'] = $target_filename;
					$archives[$k]['friendly_name'] = $show_title . "\n" . $target_filename;
				}
				
				
				break;
			}
		}
		
		if (!$match) {
			$this->log("Unable to identify episode");
			$this->log('Attempting to identify movie (by imdb)');
	        
			foreach ($archives as $k => $archive) {
				if ($title = $epguide->match('movie', $archive)) {
					$this->log("Match: $title");
					$archives[$k]['film_name'] = $title;
					$archives[$k]['target'] = $target . "Movies/$title/";
					$archives[$k]['friendly_name'] = $title;
					$match = true;
				}
			}
		}
		if (!$match) {
			$this->log('No match. Giving up..');
			return false;
		}
		
		
		$this->unrar($archives);
	}
	
	public function unrar($archives) {
		$conf = $this->getConfig('unpack');
		
		/* TODO: use sudo -A */
		$sudo = ($conf['sudo'] ? 'sudo ' : '');
		
		foreach ($archives as $arch) {
			$target = $arch['target'];
			
			if (!isset($arch['filename'])) {
				continue;	
			}
			$this->log('Extracting file: ' . $arch['filename'] . '...');
			
			if (!is_dir($target)) {
				$this->log('Creating directory: ' . $target);
				
				//passthru("mkdir -p -m " . $conf['chmod'] . " " . escapeshellarg($target) . " > /dev/null 2>&1");
				passthru( $sudo."mkdir -p " . escapeshellarg($target) . " > /dev/null 2>&1");
				if ($conf['chown']) {
					$cmd = $sudo."chown -R " . $conf['chown'] . " " . escapeshellarg($target) . " > /dev/null 2>&1";
					passthru($cmd);
				}
				if ($conf['chmod']) {
					$cmd = $sudo.'chmod -R ' . $conf['chmod'] . ' ' . escapeshellarg($target) . ' > /dev/null 2>&1';
					passthru($cmd);
				}
			}
			
			$this->log('Extracting to ' . $target . ' ...');
			
			$cmd = $sudo.str_replace(array("%from%", "%to%"),
								array(escapeshellarg($arch['path'].'/'.$arch['filename']), escapeshellarg($target)),
										$conf['extract_cmd']);
			passthru( "$cmd  > /dev/null 2>&1");
			
			if ($arch['target_fname'] && $arch['ufilename']) {
				
				if ($conf['rename']) {
					$this->log('Renaming to ' . $arch['target_fname']);
					$cmd = $sudo."mv " . escapeshellarg($target.$arch['ufilename']) . ' ';
					$cmd .= escapeshellarg($target.$arch['target_fname']);
					passthru( "$cmd  > /dev/null 2>&1");
					$arch['ufilename'] = $arch['target_fname'];
				}
				if ($conf['chown']) {
					$cmd = $sudo."chown " . $conf['chown'] . " " . escapeshellarg($target.$arch['ufilename']);
					passthru($cmd);
				}
				if ($conf['chmod']) {
					$cmd = $sudo."chmod " . $conf['chmod'] . " " . escapeshellarg($target.$arch['ufilename']);
					passthru($cmd);
				}
			}
		}
		
	}
	
	
	public function findArchives($path) {
		$files = scandir($path);
		$archives = array();
		
		if ($nfo = bbTorrent::findnfo($files) )
			$this->log("- Found nfo: $nfo");
                
		if ( $rar = bbTorrent::findrar($files) ) {
			$this->log("- Found archive: $rar");
			$archives[] = array( 'path' => $path, 'nfo' => $nfo, 'filename' => $rar, 'ufilename' => bbTorrent::listrar($path.'/'.$rar) );
		} else if ($nfo) {
			$archives[] = array( 'path' => $path, 'nfo' => $nfo);
		}
		
		foreach ($files as $file) {
			if ( $file == '.' || $file == '..' )
				continue;
			if ( is_dir($path . '/' . $file) ) {
				$subdir = scandir( $path.'/'.$file );
				if ( $rar = bbTorrent::findrar( $subdir ) ) {
					$this->log("- Found archive: $rar");
					if ($nfo = bbTorrent::findnfo($subdir)) {
						$this->log("- Found nfo: $nfo");
					}
					$archives[] = array( 'path' => $path.'/'.$file, 'nfo' => $nfo, 'filename' => $rar, 'ufilename' => bbTorrent::listrar($path.'/'.$file.'/'.$rar) );
				}
			}
		}
		return $archives;
	}
	
	
	
	public function checkRssFeeds($filter = array()) {
		$conf = $this->getConfig('rssfeeds');
		if (!$conf['enabled']) {
			$this->log("RSS magic is disabled", E_USER_NOTICE);
			return;
		}
		foreach($conf['tracker'] as $tracker) {
			if (count($filter)>0 && !in_array($tracker, $filter))
				continue;
			$rssFeed =& rssfeed::instance($this, $tracker);
			$rssFeed->run();
			
		}
		
	}
	
	
	public function downloadTorrent($url) {
		$this->log("- Downloading torrent: `" . basename($url) . "`");
		
		$sh = curl_init( $url );
		$watchfolder = $this->getConfig('unpack', 'watchfolder');
		$dlfile = $watchfolder . '/' . basename($url);
		if (file_exists($dlfile)) {
			$this->log("- Aborting; File is already downloaded!");
			return false;
		}
		$this->log("- Target: `$watchfolder`");
		if ($hFile = @fopen( $dlfile, 'w' )) {
			curl_setopt($sh, CURLOPT_FILE, $hFile );
			curl_setopt($sh, CURLOPT_HEADER, 0 );
			curl_exec($sh);
			curl_close($sh);
			fclose($hFile);
			return true;
		} else {
			$this->log("Cannot write `" . $dlfile . "`: Permission denied");
			return false;
		}
	}
	
	
	/**
	 * Div helper functions
	 * 
	 */
	public function _debugBackTraceString($arr) {
		krsort($arr);
		array_pop($arr);
		$str = $arr[1]['class'];
		foreach($arr as $trace) {
			$str .= '->' . $trace['function'] . '()';
		}
		return $str;
	}
	public function _mergeArray($arr1, $arr2) {
		foreach($arr2 as $key => $value) {
			if(array_key_exists($key, $arr1) && is_array($value))
				$arr1[$key] = $this->_mergeArray($arr1[$key], $arr2[$key]);
			else
				$arr1[$key] = $value;
		}
		return $arr1;
	}
	public function isCli() {
		return (php_sapi_name() == 'cli' && empty($_SERVER['REMOTE_ADDR']));
	}
	
	
	
	/**
	** Div archive/filesys helpers
	**  
	**************************************************************************/
	
	
	
	public static function findrar($files) {
		$results = array();
		foreach( $files as $file ) {
			if ( $file == '.' || $file == '..' )
				continue;
			$fname = substr( $file, strrpos($file, '.') + 1 );
			if ( $fname == 'rar' )
				$results[] = $file;
		}
		
		$match = '';
		if ( count($results) > 1 ) {
			foreach ($results as $file) {
				if ( strpos($file, 'part01') !== false ) {
					$match = $file;
					break;
				}
			}
			/* Use last one found */
			if (!$match)
				$match = array_pop($results);
		} else if ( count($results) == 1 ) {
			$match = $results[0];
		}
		return $match;
	}
	
	public static function listrar($path) {
		exec("unrar lb " . escapeshellarg($path), $out);
		if ($out)
			return $out[0];
		return "";
	}
	
	public static function findnfo($files) {
		$results = array();
		foreach( $files as $file ) {
			if ( $file == '.' || $file == '..' )
				continue;
			$fname = substr( $file, strrpos($file, '.') + 1 );
			if ( $fname == 'nfo' )
				$results[] = $file;
		}
		$match = '';
		if ( count($results) > 1 ) {
			/* Use last one found */
			$match = array_pop($results);
		} else if ( count($results) == 1 ) {
			$match = $results[0];
		}
		return $match;
	}
}

?>
