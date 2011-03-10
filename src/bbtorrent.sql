-- MySQL dump 10.13  Distrib 5.1.41, for debian-linux-gnu (x86_64)
-- Server version	5.1.41-3ubuntu12.9

--
-- Table structure for table `epguide_episodes`
--

DROP TABLE IF EXISTS `epguide_episodes`;
CREATE TABLE `epguide_episodes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `show_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT '0',
  `season` int(11) NOT NULL,
  `episode` int(11) NOT NULL,
  `prod_id` varchar(50) NOT NULL,
  `time` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `time_added` int(11) NOT NULL,
  `time_updated` int(11) NOT NULL,
  `downloaded` enum('0','1') NOT NULL DEFAULT '0',
  `source` varchar(255) NOT NULL,
  `link` varchar(255) NOT NULL,
  `trailer` varchar(255) NOT NULL,
  `thetvdb_episode_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `epguide_episodes`
--

LOCK TABLES `epguide_episodes` WRITE;
/*!40000 ALTER TABLE `epguide_episodes` DISABLE KEYS */;
/*!40000 ALTER TABLE `epguide_episodes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `epguide_shows`
--

DROP TABLE IF EXISTS `epguide_shows`;
CREATE TABLE `epguide_shows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `alias` varchar(50) NOT NULL,
  `lastrun` int(11) NOT NULL,
  `auto` enum('0','1') NOT NULL,
  `auto_download` enum('0','1') NOT NULL,
  `deny_pattern` varchar(255) NOT NULL,
  `time_offset` int(11) NOT NULL,
  `thetvdb_series_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `epguide_shows`
--

LOCK TABLES `epguide_shows` WRITE;
/*!40000 ALTER TABLE `epguide_shows` DISABLE KEYS */;
/*!40000 ALTER TABLE `epguide_shows` ENABLE KEYS */;
UNLOCK TABLES;

