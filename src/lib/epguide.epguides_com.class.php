<?php 

class epguideEpguidesCom extends epguide {
	
	
	public function sync($filter = array()) {
		
		parent::sync();
		
		$shows =& $this->getShows(true);
		foreach($shows as $show_id => $show) {
			if (count($filter) > 0 && !in_array(strtolower($show['title']), $filter))
				continue;
			$this->bbtorrent->log(" Syncing " . $show['title']);
			$episodes = $this->_fetchEpisodes($show['title'], $show['alias']);
			foreach($episodes as $episode) {
				$episode['show_title'] = $show['title'];
				$this->insertOrUpdate($episode);
			}
			/* TODO: needs to update `lastrun` in epguide_shows */
		}
	}
	
	public function toString() {
		return 'epguides.com';
	}
	
	private function _adjustToLocalTime($timestamp) {
		
	}
	
	
	private function _fetchEpisodes($show, $show_alias = '') {
		$uri = 'http://epguides.com/' . str_replace(" ", "", strtolower($show) );
		if (!$html = @file_get_contents( $uri )) {
			$uri = 'http://epguides.com/' . str_replace(" ", "", strtolower($show_alias) );
			if (!$html = @file_get_contents($uri)) {
				return false;
			}
		}
		
		$html = substr( $html, strpos( $html, '<div id="eplist">') );
		$html = substr( $html, 0, strpos( $html, '</div>' ) );
		//$html = strip_tags( $html );
		$html = explode("\n", $html );

		$ret = array();
		foreach ($html as $x => $line) {
			$lineTrimmed = trim($line);
			if (empty($lineTrimmed))
				continue;
			$ep = $this->_parseEpguideLine(strip_tags($line));
			
			$regexp = "<a\s[^>]*href=(\"|'??)([^\"|' >]*?)\\1[^>]*>(.*)<\/a>";
			if(preg_match_all("/$regexp/siU", $line, $matches)) {
				$ep['link']    = (isset($matches[2][0]) ? $matches[2][0] : '');
				$ep['trailer'] = (isset($matches[2][1]) ? $matches[2][1] : '');
			}
			if ( (!$ep['season'] && !$ep['episode']) || (empty($ep['title']) && !$ep['time'])) {
				//echo "'".$line."'\n";
				continue;
			}
			$ret[] = $ep;
		}
		return $ret;
	}
	
	private function _parseEpguideLine($line) {
		
		$ep = array(
			'id'      => trim( substr($line, 0, strpos($line, '.') ) ),
			'no'      => trim( substr($line, 6, 9) ),
			'prodid'  => trim( substr($line, 16, 11) ),
			'date'    => trim( substr($line, 27, 12) ),
			'title'   => trim( substr($line, 39) ),
			'season'  => 0,
			'episode' => 0,
			'time'    => 0,
			'type'    => 0,
			'link'    => '',
			'trailer' => ''
		);
		
		$ep['title'] = str_replace(" [Recap]", "", $ep['title']);
		$ep['title'] = str_replace(" [Trailer]", "", $ep['title']);
		
		$noArr = explode( '-', $ep['no'] );
		$ep['season'] = trim($noArr[0]);
		$ep['episode'] = (isset($noArr[1]) ? trim($noArr[1]) : 0);
		if ( strlen($ep['episode']) > 2) {
			$ep['episode'] = substr($ep['episode'], 0, 2);
		}
		$ep['date'] = str_replace('/', ' ', $ep['date']);
		
		$ep['time'] = 0;
		//$this->bbtorrent->log($ep['date']);
		try {
			/* We assume they all air at 20:00 */
			/* We also assume U.S. Central time zone */
			$date = new DateTime($ep['date'] . ' 20:00:00', new DateTimeZone('US/Central'));
			$ep['time'] = $date->getTimestamp();
		} catch(Exception $e) {
			//$this->bbtorrent->log($ep['date'] . ' 20:00:00', E_USER_WARNING);
		}
		
		if ( $ep['season'] == 'S' ) {
			$ep['type'] = 'special';
			$ep['season'] = 0;
		} else if ($ep['season'] == 'P' ) {
			$ep['type'] = 'pilot';
			$ep['season'] = 0;
		} else if ( is_numeric($ep['season']) ) {
			$ep['type'] = '0';
		}
		
		//'Special S5                22/Apr/09    The Story of the Oceanic 6
		if (substr($line,0,7) == 'Special') {
			$ep['type'] = 'special';
			$ep['season'] = substr($line,9,3);
			$ep['episode'] = 0;
		}
		$ep['season'] = (int)$ep['season'];
		$ep['episode'] = (int)$ep['episode'];
		
        return $ep;
	}
	
	
}
