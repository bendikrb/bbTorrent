<?php 
include_once('rss_php.php');

define('THETVDB_API_KEY', 'B918C68B9995AE81');

class epguide {
	
	var $bbtorrent;
	
	/**
	 * Holds our show ids and titles, to prevent unnecessary db queries 
	 * @var array
	 */
	var $_cache = array();
	
	var $_shows = array();
	var $_showsData = array();
	
	var $_syncstatus = array();
	
	public function __construct(&$bbtorrent) {
		$this->bbtorrent =& $bbtorrent;
	}
	
	/**
	 * Enter description here ...
	 * @param boolean $full_return
	 * @return array $shows:
	 */
	public function &getShows($full_return = false) {
		$shows =& $this->_shows;
		$shows_data =& $this->_showsData;
		if (count($shows) > 0) {
			if ($full_return) 
				return $shows_data;
			return $shows;
		}
		$db_link =& $this->bbtorrent->_db;
		
		$query = "SELECT * FROM epguide_shows";
		$res = mysql_query($query, $db_link);
		if ($this->bbtorrent->debug)
			$this->bbtorrent->log("SQL: $query");
		while ($row = mysql_fetch_assoc($res)) {
			$shows[$row['id']] = $row['title'];
			$shows_data[$row['id']] = $row;
		}
		if ($full_return) 
			return $shows_data;
		return $shows;
	}
	
	/**
	 * Enter description here ...
	 * @param int $from
	 * @param int $to
	 * @param int $limit
	 * @return array
	 */
	public function getEpisodes($from, $to, $limit = false) {
		$ret = array();
		
		$db_link =& $this->bbtorrent->_db;
		
		$query = sprintf("
		SELECT
		epguide_shows.title AS show_name,
		epguide_shows.time_offset,
		epguide_episodes.*
		FROM
		epguide_episodes
		LEFT JOIN epguide_shows ON epguide_episodes.show_id = epguide_shows.id
		WHERE
		epguide_episodes.time >= '%s' AND
		epguide_episodes.time <= '%s'
		ORDER BY
		epguide_episodes.time ASC,
		epguide_episodes.episode ASC
		" . ($limit!==false ? 'LIMIT '.$limit : '') . "
		", $from, $to);
		$res = mysql_query($query, $db_link);
		if ($this->bbtorrent->debug)
			$this->bbtorrent->log("SQL: $query");
		while ($row = mysql_fetch_assoc($res)) {
			$row['time'] += $row['time_offset'];
			$date = mktime(0,0,0, date('m', $row['time']), date('d', $row['time']), date('y', $row['time']) );
			if (!isset($ret[$date]))
				$ret[$date] = array();
			$ret[$date][] = $row;
		}
		return $ret;
	}
	
	
	/**
	 * Enter description here ...
	 * @param string $what
	 * @param string $with
	 * @param int $show_id
	 * @return array $match
	 */
	public function match($what, $with, $show_id = false) {
		switch($what) {
		case 'show_title':
			return $this->matchShowTitle($with);
			break;
		case 'episode':
			return $this->matchEpisode($with, $show_id);
			break;
		case 'movie':
			return $this->matchMovie($with);
			break;
		}
	}
	
	/**
	 * Enter description here ...
	 * @param strnig $str
	 * @return array|boolean
	 */
	public function matchShowTitle($str) {
		$known_shows =& $this->getShows(true);
		
		$title2 = strtolower($str);
		foreach($known_shows as $show_id => $show_data) {
			$show_title = $show_data['title'];
			
			$title1 = strtolower($show_title);
        	$title1abbr = "";
        	foreach (explode(" ", $title1) as $word) {
        		$title1abbr .= substr($word,0,1);
        	}
        	
        	/*
        	 * TODO: use strcmp?
        	 */
        	
        	/* Method 1 How.I.Met.Your.Mother == How i met your mother */
        	if (strpos(str_replace('.', ' ', $title2), $title1 ) === 0) {
        		//return array($show_id, $show_title);
        		return $show_data;
        	}
        	
        	/* Method 2 (xor-title.etc.etc) */
        	$check = strpos($title2, $title1);
        	if ($check > 0 && $check < 6) {
        		//return array($show_id, $show_title);
        		return $show_data;
        	}
        	
        	/* Method 2 (himym) */
        	if (strpos($title2, $title1abbr) === 0 && strlen($title1abbr) > 2) {
        		//return array($show_id, $show_title);
        		return $show_data;
        	}
		}
		return false;
	}
	
	
	/**
	 * Enter description here ...
	 * @param string $str
	 * @param int $show_id
	 * @return array|boolean:
	 */
	public function matchEpisode($str, $show_id) {
		$ep = $this->extractEpisodeNo($str);
		if ($ep === false)
			return false;
		$season = $ep[0];
		$episode = $ep[1];
		
		$db_link =& $this->bbtorrent->_db;
		$query = "SELECT * FROM epguide_episodes WHERE show_id = '" . $show_id . "' AND season = '$season' AND episode = '$episode' LIMIT 1";
		$res = mysql_query($query, $db_link);
		if ($this->bbtorrent->debug)
			$this->bbtorrent->log("SQL: $query");
		if (mysql_num_rows($res) > 0) {
			$row = mysql_fetch_assoc($res);
			return $row;
		}
		return false;
	}
	
	/**
	 * Enter description here ...
	 * @param string $str
	 * @return array
	 */
	public function extractEpisodeNo($str) {
		$filename = strtolower($str);
        
		/* Method 1: s01e01 */
		preg_match("/s([0-9][0-9])e([0-9][0-9])/", $filename, $matches);
		if (isset($matches[1]) && is_numeric($matches[1]) && is_numeric($matches[2])) {
			return array( (int)$matches[1], (int)$matches[2]);
		}
		
		/* Method 2: s1e01 */
		preg_match("/s([0-9])e([0-9][0-9])/", $filename, $matches);
		if (isset($matches[1]) && is_numeric($matches[1]) && is_numeric($matches[2])) {
			return array( (int)$matches[1], (int)$matches[2]);
		}
        
		/* Method 3: 01x01 */
		preg_match("/([0-9][0-9])x([0-9][0-9])/", $filename, $matches);
		if (isset($matches[1]) && is_numeric($matches[1]) && is_numeric($matches[2])) {
			return array( (int)$matches[1], (int)$matches[2]);
		}
        
		/* Method 4: 1x01 */
		preg_match("/([0-9])x([0-9][0-9])/", $filename, $matches);
		if (isset($matches[1]) && is_numeric($matches[1]) && is_numeric($matches[2])) {
			return array( (int)$matches[1], (int)$matches[2]);
		}
        
		/* Method 5: 0101 */
		preg_match("/([0-9][0-9])([0-9][0-9])/", $filename, $matches);
		if (isset($matches[1]) && is_numeric($matches[1]) && is_numeric($matches[2])) {
			return array( (int)$matches[1], (int)$matches[2]);
		}
        
		/* Method 6: Pt.01 */
		preg_match("/(pt|part)[.]([0-9][0-9])/", $filename, $matches);
		if (isset($matches[2]) && is_numeric($matches[2])) {
			return array(1, (int)$matches[2]);
		}
		
		/* Method 7: Pt.1 */
		preg_match("/(pt|part)[.]([0-9])/", $filename, $matches);
		if (isset($matches[2]) && is_numeric($matches[2])) {
			return array(1, (int)$matches[2]);
		}
		
		/* Method 8: Pt.IV */
		preg_match("/(PT|PART)[.](IX|IV|V?I{0,4})/", strtoupper($filename), $matches);
		if (isset($matches[2]) && !empty($matches[2])) {
			$cheatSheet = array('I' => 1,'II' => 2,'III' => 3,'IV' => 4,'V'  => 5,'VI' => 6,'VII' => 7,'VIII' => 8,'IX' => 9,'X' => 10,'XI' => 11,'XII' => 12,'XIII' => 13,'XIV' => 14,'XV' => 15);
			return array(1, ( isset($cheatSheet[$matches[2]]) ? $cheatSheet[$matches[2]] : 0) );
		}
		
		/* Method 9: 101 */
		preg_match("/([0-9])([0-9][0-9])/", $filename, $matches);
		if (isset($matches[1]) && is_numeric($matches[1]) && is_numeric($matches[2])) {
			return array($matches[1], $matches[2]);
		}
        return false;
	}
	
	
	/**
	 * Enter description here ...
	 * @param array $archive
	 * @return array|boolean
	 */
	public function matchMovie($archive) {
		
		$path = $archive['path'].'/'.$archive['nfo'];
		$nfo = file_get_contents($path);
        	
		/* Find url */
		$urls = array();
		preg_match_all('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)@', $nfo, $matches);
		if (isset($matches[0])) {
			foreach($matches[0] as $url) {
				if (strpos($url, 'imdb.com') === false)
					continue;
				$urls[] = $url;
			}
		}
        	
		if ($urls) {
			foreach ($urls as $url) {
				$data = file_get_contents($url);
				if (preg_match('/\<title\>([^"]+)\<\/title\>/', $data, $title)) {
					return $title[1];
				}
			}
		}
		return false;
	}
	
	
	/**
	 * Creates or updates an episode
	 * @param array $episode
	 * @example
	 * insertOrUpdate( array(
	 * 			'show_title' => 'Dexter',
	 * 			'title' => 'Pilot',
	 * 			'episode' => 1,
	 * 			'season'  => 1,
	 * 			'type'    => 0
	 * 		)
	 * );
	 * @return boolean
	 */
	public function insertOrUpdate($episode) {
		$show_data = $this->getShowData($episode['show_title'], true);
		if (!$show_data) {
			return false;
		}
		$status = $this->createOrUpdateEpisode($show_data, $episode);
		if (!isset($this->_syncstatus[$this->toString()])) {
			$this->_syncstatus[$this->toString()] = array(0 => 0, 1 => 0, 2 => 0);
		}
		$this->_syncstatus[$this->toString()][$status]++;
	}
	
	/**
	 * Creates or updates an episode based on title and time
	 * 
	 * @param int $show_id
	 * @param array $meta
	 * @return int $status (0 = error, 1 = updated, 2 = inserted)
	 */
	public function createOrUpdateEpisode($show_data, $meta) {
		$db_link =& $this->bbtorrent->_db;
		
		$status = 0;
		
		$show_id = $show_data['id'];
		
		$query = "SELECT * FROM epguide_episodes WHERE show_id = '$show_id' AND season='" . $meta['season'] . "' AND episode='" . $meta['episode'] . "'";
		$res = mysql_query($query, $db_link);
		if ($this->bbtorrent->debug)
			$this->bbtorrent->log("SQL: $query");
		if (mysql_num_rows($res) > 0) {
			$row = mysql_fetch_assoc($res);
			
			$episode_id = $row['id'];
			$update = array();
			
			/* Episode air date has changed */
			if ($row['time'] != $meta['time']) {
				$this->bbtorrent->log("Episode #$episode_id (s" . $meta['season'].'e'.$meta['episode'] . ") - updated time: '" . date('Y-m-d', $meta['time']) . "' (was '" . date('Y-m-d', $row['time']) . "') Diff: " . ($row['time']-$meta['time']) );
				$update['time'] = 1;
			}
			/* Episode title has changed */
			if (strcmp($row['title'], $meta['title']) !== 0) {
				$this->bbtorrent->log("Episode #$episode_id (s" . $meta['season'].'e'.$meta['episode'] . ") - updated title: '" . $meta['title'] . "' (was '" . $row['title'] . "')");
				$update['title'] = 1;
			}
			/* Source has changed */
			if (strcmp($row['source'], $this->toString()) !== 0) {
				$this->bbtorrent->log("Episode #$episode_id (s" . $meta['season'].'e'.$meta['episode'] . ") - updated source: '" . $this->toString() . "' (was '" . $row['source'] . "')");
				$update['source'] = 1;
			}
			/* Link has changed (for the better) */
			if (strcmp($row['link'], $meta['link']) !== 0 && !empty($meta['link'])) {
				$this->bbtorrent->log("Episode #$episode_id (s" . $meta['season'].'e'.$meta['episode'] . ") - updated link: '" . $meta['link'] . "' (was '" . $row['link'] . "')");
				$update['link'] = 1;
			}
			/* Trailer has changed (for the better) */
			if (strcmp($row['trailer'], $meta['trailer']) !== 0 && !empty($meta['trailer'])) {
				$this->bbtorrent->log("Episode #$episode_id (s" . $meta['season'].'e'.$meta['episode'] . ") - updated trailer: '" . $meta['trailer'] . "' (was '" . $row['trailer'] . "')");
				$update['trailer'] = 1;
			}
			
			if (!empty($row['thetvdb_episode_id'])) {
				$thetvdb_episode_data = $this->theTvDbGetEpisodeData($row['thetvdb_episode_id']);
			}
			
			/* TheTvDB Episode ID has changed */
			/*
			if ($row['thetvdb_episode_id'] != $meta['thetvdb_episode_id']) {
				$this->bbtorrent->log("Episode #$episode_id (s" . $meta['season'].'e'.$meta['episode'] . ") - updated thetvdb episode ID: '" . $meta['thetvdb_episode_id'] . "' (was '" . $row['thetvdb_episode_id'] . "')");
				$update['thetvdb_episode_id'] = 1;
			}
			*/
			
			
			if ($update) {
				$query_updates = array();
				if (isset($update['time']))
					$query_updates[] = "`time` = '" . mysql_escape_string($meta['time']) . "'";
				if (isset($update['title']))
					$query_updates[] = "title = '" . mysql_escape_string($meta['title']) . "'";
				if (isset($update['source']))
					$query_updates[] = "source = '" . mysql_escape_string($this->toString()) . "'";
				if (isset($update['link']))
					$query_updates[] = "link = '" . mysql_escape_string($meta['link']) . "'";
				if (isset($update['trailer']))
					$query_updates[] = "trailer = '" . mysql_escape_string($meta['trailer']) . "'";
//				if (isset($update['thetvdb_episode_id']))
//					$query_updates[] = "thetvdb_episode_id = '" . $meta['thetvdb_episode_id'] . "'";
				
				$query_updates[] = "time_updated = UNIX_TIMESTAMP()";
				
				$query = "UPDATE epguide_episodes SET ";
				$query .= implode(', ', $query_updates);
				$query .= " WHERE id = '$episode_id'";
				if ($this->bbtorrent->debug)
					$this->bbtorrent->log("SQL: $query");
				if (!mysql_query($query, $db_link)) {
					$this->bbtorrent->setError("SQL: " . mysql_error($db_link) );
				}
				$status = 1;
			}
		} else {
			$meta['thetvdb_episode_id'] = $this->theTvDbGetEpisodeId($show_data['thetvdb_series_id'], $meta['season'], $meta['episode']);
			
			$query = sprintf("INSERT into epguide_episodes (
				show_id,
				type,
				season,
				episode,
				prod_id,
				time,
				title,
				time_added,
				source,
				link,
				trailer,
				thetvdb_episode_id
				) VALUES (
				'%s','%s','%s','%s','%s','%s','%s',UNIX_TIMESTAMP(),'%s','%s','%s','%s'
				);",
				$show_id,
				$meta['type'],
				$meta['season'],
				$meta['episode'],
				'',
				$meta['time'],
				mysql_escape_string($meta['title']),
				$this->toString(),
				mysql_escape_string($meta['link']),
				mysql_escape_string($meta['trailer']),
				$meta['thetvdb_episode_id']
			);
			if ($this->bbtorrent->debug)
					$this->bbtorrent->log("SQL: $query");
			if (!mysql_query($query, $db_link)) {
				$this->bbtorrent->setError("SQL: " . mysql_error($db_link) );
				return -1;
			}
			$episode_id = mysql_insert_id($db_link);
			$this->bbtorrent->log("Added new episode #$episode_id: '" . $meta['title'] . "'");
			$status = 2;
		}
		return $status;
	}
	
	
	/**
	 * Creates show
	 * @param string $show_title
	 * @param array $opts
	 * @return number $show_id
	 */
	public function createShow($show_title, $opts = array()) {
		$this->bbtorrent->log("Creating new show: '$show_title'", E_USER_NOTICE);
		
		/* TheTVDB.com */
		$thetvdb_series_id = $this->theTvDbGetSeriesId($show_title);
		
		$db_link =& $this->bbtorrent->_db;
		$query = sprintf("INSERT INTO epguide_shows (
			title,
			auto,
			auto_download,
			lastrun,
			thetvdb_series_id
			) VALUES (
			'%s',
			'1',
			'1',
			'%s',
			'%s'
			);",
			mysql_escape_string($show_title),
			time(),
			$thetvdb_series_id
		);
		if ($this->bbtorrent->debug)
			$this->bbtorrent->log("SQL: $query");
		if (!mysql_query($query, $db_link)) {
			$this->bbtorrent->setError("SQL: " . mysql_error($db_link) );
			return -1;
		}
		$show_id = mysql_insert_id($db_link);
		$res = mysql_query("SELECT * FROM epguide_shows WHERE id='$show_id'", $db_link);
		$show_data = mysql_fetch_assoc($res);
		
		/* Fetch tvdb data */
		$show_data['thetvdb_data'] = $this->theTvDbGetSeriesData($thetvdb_series_id);
		
		
		return $show_data;
	}
	
	
	/**
	 * Searches database for show title, 
	 * creates new record if $force = true
	 * 
	 * @param string $show_title
	 * @param boolean $force = false
	 * @return boolean|number
	 */
	public function getShowData($show_title, $force = false) {
		$show_title = ucwords( strtolower($show_title) );
		
		if ( ($show_id = array_search($show_title, $this->_shows)) !== false ) {
			return $this->_showsData[$show_id];
		}
		$db_link =& $this->bbtorrent->_db;
		
		$query = sprintf("SELECT * FROM epguide_shows WHERE title LIKE '%s'", mysql_escape_string($show_title));
		$res = mysql_query($query, $db_link );
		if ($this->bbtorrent->debug)
			$this->bbtorrent->log("SQL: $query");
		if (mysql_num_rows($res) > 0) {
			$show_data = mysql_fetch_assoc($res);
			mysql_query("UPDATE epguide_shows SET lastrun = UNIX_TIMESTAMP() WHERE id='$show_id'", $db_link);
		} else if ($force) {
			$show_data = $this->createShow($show_title);
		}
		
		if (isset($show_data)) {
			if (!isset($show_data['thetvdb_data'])) {
				$show_data['thetvdb_data'] = ($show_data['thetvdb_series_id'] ? $this->theTvDbGetSeriesData($show_data['thetvdb_series_id']) : null);
			}
			$this->_shows[$show_data['id']] = $show_title;
			$this->_showsData[$show_data['id']] = $show_data;
			
			return $show_data;
		}
		return false;
	}
	
	
	public function getEpisodeData($episode_id) {
		$db_link =& $this->bbtorrent->_db;
		
		$query = sprintf("SELECT * FROM epguide_episodes WHERE id = '%s'", (int)$episode_id);
		if ($this->bbtorrent->debug)
			$this->bbtorrent->log("SQL: $query");
		$res = mysql_query($query, $db_link);
		if (mysql_num_rows($res) > 0) {
			$row = mysql_fetch_assoc($res);
			$row['thetvdb_data'] = ($row['thetvdb_episode_id'] ? $this->theTvDbGetEpisodeData($row['thetvdb_episode_id']) : null);
		} 
		return false;
	}
	
	
	/**
	 * Prints sync report to log
	 */
	public function syncReport() {
		$stattxt = array('Skipped', 'Updated', 'Inserted');
		
		$this->bbtorrent->log("Sync report:", E_USER_NOTICE);
		$x = 0;
		foreach($this->_syncstatus as $source => $stats) {
			$this->bbtorrent->log($source . " sync report:", E_USER_NOTICE);
			foreach($stats as $status => $count) {
				$this->bbtorrent->log(" - " . $stattxt[$status] . ":\t" . $count, E_USER_NOTICE);
				$x++;
			}
		}
		if ($x==0) {
			$this->bbtorrent->log(" - No changes made");
		}
	}
	
	/**
	 * Returns extended epguide class as specified by config
	 * @param object $bbtorrent
	 * @return object
	 */
	public static function instance(&$bbtorrent) {
		/* */
		$epguide = $bbtorrent->getConfig('epguide');
		
		$file_name  = 'epguide.' . strtolower($epguide['name']) . '.class.php';
		$class_name = 'epguide' . str_replace(' ', '', ucwords( str_replace('_', ' ', $epguide['name']) ) );
		
		$instance = null;
		@include( $file_name );
		if (class_exists($class_name)) {
			$instance = new $class_name($bbtorrent);
		}
		return $instance;
	}
	
	/**
	 * Syncs local database with external episode guide
	 * @overridden
	 * @uses epguide::insertOrUpdate()
	 */
	public function sync($filter = array()) {
		$this->theTvDbInit();
		
	}
	
	
	var $_thetvdb_mirror_url;
	var $_thetvdb_update_data;
	
	/**
	 * Enter description here ...
	 */
	public function theTvDbInit() {
		if (!$this->bbtorrent->isCli())
			return;
		
		$rss = new rss_php;
		/* Get a list of mirrors */
		$rss->load('http://www.thetvdb.com/api/' . THETVDB_API_KEY . '/mirrors.xml');
		$data = $rss->getRSS();
		$mirrors = array();
		foreach($data['Mirrors'] as $mirror) {
			$mirrors[] = $mirror;
		}
		$mirror = $mirrors[mt_rand(0, count($mirrors)-1)];
		
		$this->_thetvdb_mirror_url = $mirror['mirrorpath'];
		$this->_thetvdb_update_data = false;
		
		/* Get the current server time */
		if (!$server_timestamp = $this->theTvDbGetTimestamp()) {
			$rss->load('http://www.thetvdb.com/api/Updates.php?type=none');
			$data = $rss->getRSS();
			$server_timestamp = $data['Items']['Time'];
			$this->theTvDbSetTimestamp($server_timestamp);
		} else {
			$this->bbtorrent->log("Retrieving update data from TheTVDB.com");
			
			$DOMDocument = new DOMDocument;
			$DOMDocument->strictErrorChecking = false;
			$DOMDocument->load('http://www.thetvdb.com/api/Updates.php?type=all&time=' . $server_timestamp);
			
			$this->_thetvdb_update_data = array(
				'series'   => array(),
				'episodes' => array()
			);
			$nodes = $DOMDocument->getElementsByTagName('Series');
			foreach($nodes as $node) {
				$this->_thetvdb_update_data['series'][$node->textContent] = 1;
			}
			$nodes = $DOMDocument->getElementsByTagName('Series');
			foreach($nodes as $node) {
				$this->_thetvdb_update_data['episodes'][$node->textContent] = 1;
			}
		}
	}
	
	/**
	 * Enter description here ...
	 * @param unknown_type $series_id
	 * @param unknown_type $force
	 * @return multitype:multitype: 
	 */
	public function theTvDbGetSeriesData($series_id, $force = false) {
		$data_path = $this->bbtorrent->getConfig('epguide', 'data_path');
		
		if (!$this->_thetvdb_mirror_url) {
			$this->theTvDbInit();
		}
		
		if ($force == false) {
			if (file_exists($data_path.'/series/' . $series_id . '/en.xml')) {
				if (isset($this->_thetvdb_update_data['series'][$series_id])) {
					unset($this->_thetvdb_update_data['series'][$series_id]);
					$this->bbtorrent->log("Series data needs update!");
				} else {
					return $this->theTvDbParseSeriesData($series_id);
				}
			}
		}
		$zip_filename = '/tmp/' . $series_id . '.zip';
		
		$this->bbtorrent->log(" Getting series data from TheTVDB.com...");
		
		$url = $this->_thetvdb_mirror_url . '/api/' . THETVDB_API_KEY . '/series/' . $series_id . '/all/en.zip';
		$ch = curl_init( $url );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		
		if (!$result) {
			$this->bbtorrent->log("$url :: NO RESULT!!");
		}
		$fp = fopen($zip_filename, 'w');
		fwrite($fp, $result);
		fclose($fp);
		
		$extract_dir = $data_path . '/series';
		if (!file_exists($extract_dir)) {
			mkdir($extract_dir);
		}
		$extract_dir .= '/' . $series_id;
		if (!file_exists($extract_dir)) {
			mkdir($extract_dir);
		}
		
		$this->bbtorrent->log(" Extracting series data...");
		$files = array();
		$zip = zip_open($zip_filename);
		if ($zip) {
			while ($zip_entry = zip_read($zip)) {
				$file = basename(zip_entry_name($zip_entry));
				$fp = fopen($extract_dir.'/'.basename($file), "w+");
				if (zip_entry_open($zip, $zip_entry, "r")) {
					$buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
					zip_entry_close($zip_entry);
				}
				fwrite($fp, $buf);
				fclose($fp);
			}
			zip_close($zip);
		}
		
		/* Cleanup */
		unlink($zip_filename);
		
		return $this->theTvDbParseSeriesData($series_id);
	}
	
	
	/**
	 * Enter description here ...
	 * @param unknown_type $show_title
	 * @return number
	 */
	public function theTvDbGetSeriesId($show_title) {
		$show = array();
		$DOMDocument = new DOMDocument;
		$DOMDocument->strictErrorChecking = false;
		$DOMDocument->load('http://www.thetvdb.com/api/GetSeries.php?seriesname=' . urlencode($show_title));
		$nodes = $DOMDocument->getElementsByTagName('Series');
		foreach($nodes as $x => $node) {
			foreach($node->childNodes as $value) {
				if (substr($value->nodeName,0,1) == '#')
					continue;
				$show[$value->nodeName] = $value->textContent;
			}
			break;
		}
		return (isset($show['seriesid']) ? $show['seriesid'] : 0);
	}
	
	/**
	 * Enter description here ...
	 * @param unknown_type $series_id
	 * @param unknown_type $season
	 * @param unknown_type $episodenumber
	 * @return Ambiguous|number
	 */
	public function theTvDbGetEpisodeId($series_id, $season, $episodenumber) {
		$show_data = $this->theTvDbGetSeriesData($series_id);
		foreach($show_data['episodes'] as $episode) {
			if ($episode['SeasonNumber'] == $season && $episode['EpisodeNumber'] == $episodenumber) {
				return $episode['id'];
			}
		}
		return 0;
	}
	
	/**
	 * Enter description here ...
	 * @param unknown_type $episode_id
	 * @param unknown_type $force
	 * @return Ambigous <boolean, multitype:>
	 */
	public function theTvDbGetEpisodeData($episode_id, $force = false) {
		
		$data_path = $this->bbtorrent->getConfig('epguide', 'data_path');
		if (!$this->_thetvdb_mirror_url) {
			$this->theTvDbInit();
		}
		
		if ($force == false) {
			if (file_exists($data_path.'/episodes/' . $episode_id . '/en.xml')) {
				if (isset($this->_thetvdb_update_data['episodes'][$episode_id])) {
					unset($this->_thetvdb_update_data['episodes'][$episode_id]);
					$this->bbtorrent->log("Episode data needs update!");
				} else {
					return $this->theTvDbParseEpisodeData($episode_id);
				}
			}
		}
		if (!$this->bbtorrent->isCli())
			return;
		
		$this->bbtorrent->log(" Getting episode data from TheTVDB.com...");
		
		$url = $this->_thetvdb_mirror_url . '/api/' . THETVDB_API_KEY . '/episodes/' . $episode_id . '/en.xml';
		$ch = curl_init( $url );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if (!$result) {
			$this->bbtorrent->log("$url :: NO RESULT!!");
		}
		$xml_filename = $data_path . '/episodes';
		if (!file_exists($xml_filename)) {
			mkdir($xml_filename);
		}
		$xml_filename .= '/' . $episode_id;
		if (!file_exists($xml_filename)) {
			mkdir($xml_filename);
		}
		$xml_filename .= '/en.xml';
		$fp = fopen($xml_filename, 'w');
		fwrite($fp, $result);
		fclose($fp);
		
		return $this->theTvDbParseEpisodeData($episode_id);
	}
	
	private function theTvDbSaveEpisodeData($episode_id, &$DOM) {
		$data_path = $this->bbtorrent->getConfig('epguide', 'data_path');
		
		$xml_filename = $data_path . '/episodes';
		if (!file_exists($xml_filename)) {
			mkdir($xml_filename);
		}
		$xml_filename .= '/' . $episode_id;
		if (!file_exists($xml_filename)) {
			mkdir($xml_filename);
		}
		$xml_filename .= '/en.xml';
		
		$xml = $DOM->saveXML();
		$fp = fopen($xml_filename, 'w');
		fwrite($fp, $xml);
		fclose($fp);
	}
	
	/**
	 * Enter description here ...
	 * @param unknown_type $timestamp
	 */
	public function theTvDbSetTimestamp($timestamp) {
		$data_path = $this->bbtorrent->getConfig('epguide', 'data_path');
		$filename = $data_path . '/thetvdb.json';
		
		$data = array('timestamp' => $timestamp);
		$data = json_encode($data);
		
		$fp = fopen($filename, 'w');
		fwrite($fp, $data);
		fclose($fp);
	}
	
	/**
	 * Enter description here ...
	 * @return boolean
	 */
	public function theTvDbGetTimestamp() {
		$data_path = $this->bbtorrent->getConfig('epguide', 'data_path');
		$filename = $data_path . '/thetvdb.json';
		
		if (!file_exists($filename)) {
			return false;
		}
		$data = file_get_contents($filename);
		if (strlen($data) == 0) {
			return false;
		}
		$data = json_decode($data);
		return $data->timestamp;
	}
	
	
	
	/**
	 * Enter description here ...
	 * @param unknown_type $series_id
	 * @return multitype:multitype: 
	 */
	private function theTvDbParseSeriesData($series_id) {
		$data_path = $this->bbtorrent->getConfig('epguide', 'data_path');
		
		$filename = $data_path.'/series/' . $series_id . '/en.xml';
		
		$DOMDocument = new DOMDocument;
		$DOMDocument->strictErrorChecking = false;
		$DOMDocument->load($filename);
		
		$nodes = $DOMDocument->getElementsByTagName('Series')->item(0);
		$show_data = array();
		foreach($nodes->childNodes as $value) {
			if (substr($value->nodeName,0,1) == '#')
				continue;
			$show_data[$value->nodeName] = $value->textContent;
		}
		
		$episodes = array();
		$nodes = $DOMDocument->getElementsByTagName('Episode');
		foreach($nodes as $episode) {
			$episode_id = $episode->getElementsByTagName('id')->item(0)->textContent;
			$episodes[$episode_id] = array();
			foreach($episode->childNodes as $value) {
				if (substr($value->nodeName,0,1) == '#')
					continue;
				$episodes[$episode_id][$value->nodeName] = $value->textContent;
			}
			$DOMEpisode = DOMDocument::loadXML('<Data>' . $DOMDocument->saveXML($episode) . '</Data>');
			
			$this->theTvDbSaveEpisodeData($episode_id, $DOMEpisode);
		}
		return array(
			'show_data' => $show_data,
			'episodes'  => $episodes
		);
	}
	
	/**
	 * Enter description here ...
	 * @param unknown_type $episode_id
	 * @return boolean|multitype:
	 */
	private function theTvDbParseEpisodeData($episode_id) {
		$data_path = $this->bbtorrent->getConfig('epguide', 'data_path');
		
		$filename = $data_path.'/episodes/' . $episode_id . '/en.xml';
		
		if (!file_exists($filename)) {
			$this->bbtorrent->log(" File `$filename` does not exist!", E_USER_WARNING);
			return false;
		}
		
		$DOMDocument = new DOMDocument;
		$DOMDocument->strictErrorChecking = false;
		$DOMDocument->load($filename);
		
		$nodes = $DOMDocument->getElementsByTagName('Data')->item(0);
		$episode_data = array();
		foreach($nodes->childNodes as $value) {
			if (substr($value->nodeName,0,1) == '#')
				continue;
			$episode_data[$value->nodeName] = $value->textContent;
		}
		return $episode_data;
	}
	
	
	/**
	 * Enter description here ...
	 * @return string
	 */
	public function toString() {
		return '';
	}
}