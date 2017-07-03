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
  `pH` varchar(5) NOT NULL DEFAULT '""',
  `main_electrolyte` varchar(64) DEFAULT '""',
  `working_electrode` varchar(128) DEFAULT '""',
  `reference_electrode` varchar(128) DEFAULT '""',
  `analyte` varchar(64) DEFAULT '""',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM AUTO_INCREMENT=36 DEFAULT CHARSET=latin2;');

$db->query('ALTER TABLE `' . $dbPrefix . '_data`
 ADD PRIMARY KEY (`id`), ADD KEY `pH` (`pH`), ADD KEY `main_electrolyte` (`main_electrolyte`), ADD KEY `working_electrode` (`working_electrode`), ADD KEY `analyte` (`analyte`), ADD KEY `reference_electrode` (`reference_electrode`);');

$db->query('ALTER TABLE `' . $dbPrefix . '_data`
MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;');

$db->query('CREATE TABLE IF NOT EXISTS `' . $dbPrefix . '_pages` (
`id` int(10) unsigned NOT NULL,
  `name` varchar(128) NOT NULL,
  `contents` varchar(2048) NOT NULL,
  `permissions` varchar(128) NOT NULL
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=latin2;');

$db->query('INSERT INTO `ULTRA_pages` (`id`, `name`, `contents`, `permissions`) VALUES
(1, \'index\', \'a:3:{s:4:"Body";s:96:"<a href="?name=uploadfile">Wgraj Plik</a><br><a href="?name=viewplot&plotId=-1">Pokaz wykres</a>";s:4:"Head";s:0:"";s:7:"Scripts";s:0:"";}\', \'0\'),
(2, \'uploadfile\', \'a:3:{s:4:"Body";s:20:"{ULTRA_fileuploader}";s:4:"Head";s:0:"";s:7:"Scripts";s:0:"";}\', \'\'),
(3, \'viewplot\', \'a:3:{s:4:"Body";s:17:"{ULTRA_plotMaker}";s:4:"Head";s:0:"";s:7:"Scripts";s:0:"";}\', \'\');\'');

$db->query('ALTER TABLE `ULTRA_pages`
 ADD PRIMARY KEY (`id`);');

?>
