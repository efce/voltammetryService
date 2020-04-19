<?php
include "config.php";
include "revertdb.class.php";

$db = new revertdb($dbHost, $dbUser, $dbPass, $dbDatabase);

$db->query('
CREATE TABLE IF NOT EXISTS `' . $dbPrefix . '_data` (
`id` int(10) unsigned NOT NULL,
  `orginal_name` varchar(128) NOT NULL,
  `system_name` varchar(128) NOT NULL,
  `data` longtext NOT NULL,
  `owner` int(11) NOT NULL,
  `extra` varchar(512) NOT NULL,
  `pH` varchar(5) NOT NULL DEFAULT \'\',
  `main_electrolyte` varchar(64) DEFAULT \'\',
  `working_electrode` varchar(128) DEFAULT \'\',
  `reference_electrode` varchar(128) DEFAULT \'\',
  `analyte` varchar(64) DEFAULT \'\',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM AUTO_INCREMENT=36 DEFAULT CHARSET=latin2;');
echo('.');
$db->query('ALTER TABLE `' . $dbPrefix . '_data`
 ADD PRIMARY KEY (`id`), ADD KEY `pH` (`pH`), ADD KEY `main_electrolyte` (`main_electrolyte`), ADD KEY `working_electrode` (`working_electrode`), ADD KEY `analyte` (`analyte`), ADD KEY `reference_electrode` (`reference_electrode`);');
echo('.');
$db->query('ALTER TABLE `' . $dbPrefix . '_data`
MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;');

echo('.');
$db->query('
CREATE TABLE IF NOT EXISTS `' . $dbPrefix . '_analytes` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`meta_id` int(10) unsigned NOT NULL,
`name` varchar(256) unsigned NOT NULL,
`concantrations` float,
PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=36 DEFAULT CHARSET=latin2;');

echo('.');
$db->query('
CREATE TABLE IF NOT EXISTS `' . $dbPrefix . '_conc_units` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`name` varchar(128) NOT NULL,
`multiply_to_mg_L` float NOT NULL,
PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin2;');

echo('.');
$db->query('
CREATE TABLE IF NOT EXISTS `' . $dbPrefix . '_mesdata` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`meta_id` int(10) unsigned NOT NULL,
`process_id` int(10) unsigned NOT NULL,
  `data` longtext NOT NULL,
`nr_of_curves` int(10) NOT NULL,
`nr_of_points` int(10) NOT NULL,
`img_filename` varchar(256) DEFAULT NULL,
`img_updated` int(2) NOT NULL DEFAULT 0,
`img_firstPointX` float DEFAULT NULL,
`img_firstPointY` float DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=36 DEFAULT CHARSET=latin2;');

echo('.');
$db->query('
CREATE TABLE IF NOT EXISTS `' . $dbPrefix . '_caldata` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`cal_math_id` int(10) unsigned NOT NULL,
`mes_id` int(10) unsigned NOT NULL,
`anal_id` int(10) unsigned NOT NULL,
  `data` longtext NOT NULL,
`img_filename` varchar(256) NOT NULL,
`img_updated` int(2) NOT NULL,
`equation` varchar(128) NOT NULL,
`st_add_result` float NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=36 DEFAULT CHARSET=latin2;');

echo('.');
$db->query('CREATE TABLE IF NOT EXISTS `' . $dbPrefix . '_pages` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `contents` varchar(2048) NOT NULL,
  `permissions` varchar(128) NOT NULL,
  `only_logged` int(2) NOT NULL DEFAULT 0,
PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=latin2;');

echo('.');
$db->query('CREATE TABLE IF NOT EXISTS `' . $dbPrefix . '_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(256) NOT NULL,
  `password` varchar(256) NOT NULL,
  `name` varchar(128) DEFAULT \'""\',
  `status` varchar(8) NOT NULL,
PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=latin2;');

echo('.');
$db->query('CREATE TABLE IF NOT EXISTS `' . $dbPrefix . '_metadata` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `file_name` varchar(256) NOT NULL,
  `was_processed` int(2) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `is_public` int(2) NOT NULL,
  `E_start` float NOT NULL,
  `E_end` float NOT NULL,
  `E_step` float not NULL,
  `voltammetry_type` enum(\'LSV\',\'SCV\',\'NPV\',\'DPV\',\'SQW\') DEFAULT \'LSV\' NOT NULL,
  `is_cv` int(2) DEFAULT 0,
  `file_added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=36 DEFAULT CHARSET=latin2;');


/* CONTENT */
echo('.');
$db->query('INSERT INTO `' . $dbPrefix . '_pages` (`id`, `name`, `contents`, `permissions`) VALUES
(1, \'index\', \'a:3:{s:4:"Body";s:96:"<a href="?name=uploadfile">Wgraj Plik</a><br><a href="?name=viewplot&plotId=-1">Pokaz wykres</a>";s:4:"Head";s:0:"";s:7:"Scripts";s:0:"";}\', \'0\'),
(2, \'uploadfile\', \'a:3:{s:4:"Body";s:20:"{ULTRA_FileUploader}";s:4:"Head";s:0:"";s:7:"Scripts";s:0:"";}\', \'\'),
(3, \'viewplot\', \'a:3:{s:4:"Body";s:17:"{ULTRA_plotMaker}";s:4:"Head";s:0:"";s:7:"Scripts";s:0:"";}\', \'\'),
(4, \'manage\', \'a:3:{s:4:"Body";s:17:"{ULTRA_DataEditor}";s:4:"Head";s:0:"";s:7:"Scripts";s:0:"";}\', \'\');');
(5, \'data\', \'a:3:{s:4:"Body";s:19:"{ULTRA_DataHandler}";s:4:"Head";s:0:"";s:7:"Scripts";s:0:"";}\', \'\');');

$db->query('INSERT INTO `' . $dbPrefix . '_users` (`name`, `email`, `password`, `status`) VALUES
(\'FC\', \'filip.ciepiela@gmail.com\', MD5(\'filip.ciepiela@gmail.comtest\'), \'ok\');');

?>
