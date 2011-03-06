<?php 
include_once('rss_php.php');


class epguideMyEpisodes extends epguide {
	
	
	public function sync($filter = array()) {
		$conf = $this->bbtorrent->getConfig('epguide');
		
		$url = 'http://www.myepisodes.com/rss.php?feed=unacquired';
		$url .= '&uid=' . $conf['uid'];
		$url .= '&pwdmd5=' . $conf['hash'];
		
		$rss = new rss_php;
		$rss->load($url);
		
		$data = $rss->getRSS();
		$items = $data['rss']['channel'];
		
		foreach($items as $tag => $item) {
			if (count(explode(':',$tag)) < 2)
				continue;
			$episode = $this->_parseTitleTag($item['title']);
			if (count($filter) > 0 && !in_array(strtolower($episode['show_title']), $filter))
				continue;
			$episode['link'] = $item['link'];
			$episode['trailer'] = '';
			$this->insertOrUpdate($episode);
		}
	}
	
	public function toString() {
		return 'myepisodes.com';
	}
	
	
	private function _parseTitleTag($str) {
		/*[ Smallville ][ 10x16 ][ Scion ][ 05-Mar-2011 ]*/
		$parts = explode("][", $str);
		$ret = array();
		foreach(array('show_title','ep_season','title','date') as $x => $key) {
			$ret[$key] = trim( str_replace(array(']','['), '', $parts[$x]) );
			
			if ($key == 'ep_season') {
				$ep_season = explode('x', $ret[$key]);
				$ret['season']  = (int)$ep_season[0];
				$ret['episode'] = (int)$ep_season[1];
			}
			if ($key == 'date') {
				/* We assume they all air at 20:00 */
				/* We also assume U.S. Central time zone */
				$date = new DateTime($ret[$key] . ' 20:00:00', new DateTimeZone('US/Central'));
				$ret['time'] = $date->getTimestamp();
			}
		}
		return $ret;
	}
	
	
}
