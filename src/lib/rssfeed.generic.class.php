<?php 
include_once("rss_php.php");

class rssfeed {
	
	var $bbtorrent;
	
	var $conf;
	
	public function __construct(&$bbtorrent) {
		$this->bbtorrent =& $bbtorrent;
	}
	
	public function setConfig($conf) {
		$this->conf = $conf;
	}
	
	public function toString() {
		return 'generic rss-feed';
	}
	
	
	public function run() {
		$url = $this->conf['url'];
		$rss = new rss_php;
		if (!$rss->load($url)) {
			$this->bbtorrent->setError("Unable to load RSS: '$url'");
			return false;
		}
		
		
		
		$mark = 0;
		if (isset($this->conf['mark'])) {
			$mark = file_get_contents($this->conf['mark']);
		}
		$items = array_reverse( $rss->getItems() );
		foreach($items as $item) {
			$date = strtotime($item['pubDate']);
			if ($mark != 0 && $date < ($mark+1)) // seen it
				continue;
			//$this->bbtorrent->log("RSS: " . $item['title']);
			$this->processItem($item);
		}
		if (isset($this->conf['mark'])) {
			if ( ($fp = @fopen($this->conf['mark'], 'w')) !== false) {
				fwrite($fp, $date);
				fclose($fp);
			}
		}
	}
	
	
	public function processItem($item) {
		$epguide =& epguide::instance($this->bbtorrent);
		
		if ( ($show_data = $epguide->match('show_title', $item['title'])) !== false) {
			$this->bbtorrent->log('- Match on title: "' . $show_data['title'] . '"');
			if (!$show_data['auto_download']) {
				return false;
			}
			if (!empty($show_data['deny_pattern'])) {
				$check = preg_match($show_data['deny_pattern'], $item['title']);
				if ($check) {
					$this->bbtorrent->log('- Denied: ' . $show_data['deny_pattern']);
					return false;
				}
			}
			if ( ($episode = $epguide->match('episode', $item['title'], $show_data['id'])) !== false) {
				$this->bbtorrent->log('- Match on episode: s' . $episode['season'] . 'e' . $episode['episode'] . ' "' . $episode['title'] . '"');
				
				
				if ($episode['downloaded'] == '1') {
					$this->bbtorrent->log('- Already downloaded!');
					return false;
				}
				return $this->bbtorrent->downloadTorrent($item['link']);
			}
		}
		return false;
	}
	
	/**
	 * Returns extended rss feed class
	 * @param object $bbtorrent
	 * @return object
	 */
	public static function instance(&$bbtorrent, $tracker_name) {
		/* */
		$instance = null;
		$conf = $bbtorrent->getConfig('tracker_'.$tracker_name);
		if (!$conf) {
			$instance = new rssfeed($bbtorrent);
			return $instance;
		}
		
		$file_name  = 'rssfeed.' . strtolower($conf['name']) . '.class.php';
		$class_name = 'rssfeed' . str_replace(' ', '', ucwords( str_replace('_', ' ', $conf['name']) ) );
		
		if (!file_exists($file_name)) {
			$instance = new rssfeed($bbtorrent);
			$instance->setConfig($conf);
			return $instance;
		}
		include( $file_name );
		if (class_exists($class_name)) {
			$instance = new $class_name($bbtorrent);
			$instance->setConfig($conf);
		}
		return $instance;
	}
}

?>