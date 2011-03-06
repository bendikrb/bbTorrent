<?php 
include_once('rss_php.php');

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
	
	public function &getShows($full_return = false) {
		$shows =& $this->_shows;
		$shows_data =& $this->_showsData;
		if (count($shows) > 0) {
			if ($full_return) 
				return $shows_data;
			return $shows;
		}
		$db_link =& $this->bbtorrent->_db;
		$res = mysql_query("SELECT * FROM epguide_shows", $db_link);
		while ($row = mysql_fetch_assoc($res)) {
			$shows[$row['id']] = $row['title'];
			$shows_data[$row['id']] = $row;
		}
		if ($full_return) 
			return $shows_data;
		return $shows;
	}
	
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
		while ($row = mysql_fetch_assoc($res)) {
			$row['time'] += $row['time_offset'];
			$date = mktime(0,0,0, date('m', $row['time']), date('d', $row['time']), date('y', $row['time']) );
			if (!isset($ret[$date]))
				$ret[$date] = array();
			$ret[$date][] = $row;
		}
		return $ret;
	}
	
	
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
	
	
	public function matchEpisode($str, $show_id) {
		$ep = $this->extractEpisodeNo($str);
		if ($ep === false)
			return false;
		$season = $ep[0];
		$episode = $ep[1];
		
		$db_link =& $this->bbtorrent->_db;
		$res = mysql_query("SELECT * FROM epguide_episodes WHERE show_id = '" . $show_id . "' AND season = '$season' AND episode = '$episode' LIMIT 1", $db_link);
		if (mysql_num_rows($res) > 0) {
			$row = mysql_fetch_assoc($res);
			return $row;
		}
		return false;
	}
	
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
		
		$show_id = $this->getShowID($episode['show_title'], true);
		if (!$show_id) {
			return false;
		}
		$status = $this->createOrUpdateEpisode($show_id, $episode);
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
	public function createOrUpdateEpisode($show_id, $meta) {
		$db_link =& $this->bbtorrent->_db;
		
		$status = 0;
		
		$query = "SELECT * FROM epguide_episodes WHERE show_id = '$show_id' AND season='" . $meta['season'] . "' AND episode='" . $meta['episode'] . "'";
		$res = mysql_query($query, $db_link);
		if (mysql_num_rows($res) > 0) {
			$row = mysql_fetch_assoc($res);
			
			$episode_id = $row['id'];
			$update = array();
			
			/* Episode air date has changed */
			if ($row['time'] != $meta['time']) {
				$this->bbtorrent->log("Episode #$episode_id (s" . $meta['season'].'e'.$meta['episode'] . ") - updated time: '" . date('Y-m-d', $meta['time']) . "' (was '" . date('Y-m-d', $row['time']) . "') Diff: " . ($row['time']-$meta['time']) );
				$this->bbtorrent->log();
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
				
				$query_updates[] = "time_updated = UNIX_TIMESTAMP()";
				
				$query = "UPDATE epguide_episodes SET ";
				$query .= implode(', ', $query_updates);
				$query .= " WHERE id = '$episode_id'";
				if (!mysql_query($query, $db_link)) {
					$this->bbtorrent->setError("SQL: " . mysql_error($db_link) );
				}
				$status = 1;
			}
		} else {
			
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
				trailer
				) VALUES (
				'%s','%s','%s','%s','%s','%s','%s',UNIX_TIMESTAMP(),'%s','%s','%s'
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
				mysql_escape_string($meta['trailer'])
			);
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
		
		$db_link =& $this->bbtorrent->_db;
		$query = sprintf("INSERT INTO epguide_shows (
			title,
			auto,
			auto_download,
			lastrun
			) VALUES (
			'%s',
			'1',
			'1',
			'%s'
			);",
			mysql_escape_string($show_title),
			time()
		);
		if (!mysql_query($query, $db_link)) {
			$this->bbtorrent->setError("SQL: " . mysql_error($db_link) );
			return -1;
		}
		//$this->log("SQL: $query");
		$show_id = mysql_insert_id($db_link);
		
		return $show_id;
	}
	
	
	/**
	 * Searches database for show title, 
	 * creates new record if $force = true
	 * 
	 * @param string $show_title
	 * @param boolean $force = false
	 * @return boolean|number
	 */
	public function getShowID($show_title, $force = false) {
		$show_title = ucwords( strtolower($show_title) );
		
		if ( ($show_id = array_search($show_title, $this->_shows)) !== false ) {
			return $show_id;
		}
		$db_link =& $this->bbtorrent->_db;
		
		$res = mysql_query( sprintf("SELECT id FROM epguide_shows WHERE title LIKE '%s'", mysql_escape_string($show_title)), $db_link );
		if (mysql_num_rows($res) > 0) {
			$show_id = mysql_result($res, 0, 0);
			mysql_query("UPDATE epguide_shows SET lastrun = UNIX_TIMESTAMP() WHERE id='$show_id'", $db_link);
		} else if ($force) {
			$show_id = $this->createShow($show_title);
		}
		if ($show_id) {
			$this->_shows[$show_id] = $show_title;
			return $show_id;
		}
		return false;
	}
	
	
	public function syncReport() {
		$stattxt = array('Skipped', 'Updated', 'Inserted');
		
		$this->bbtorrent->log("Sync report:");
		$x = 0;
		foreach($this->_syncstatus as $source => $stats) {
			$this->bbtorrent->log($source . " sync report:");
			foreach($stats as $status => $count) {
				$this->bbtorrent->log(" - " . $stattxt[$status] . ":\t" . $count);
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
	 * @uses epguide::insertOrUpdate()
	 */
	public function sync($filter = array()) { }
	
	public function toString() {
		return '';
	}
}