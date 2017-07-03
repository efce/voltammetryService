<?php

error_reporting(E_ALL);
ini_set('display_errors', True);
ini_set('error_reporting', E_ALL);

include 'mainPage.class.php';
mainPage::spawnInstance();// singleton class // 
$cPage = mainPage::getInstance();
$cPage->Load();
$cPage->Check();
$cPage->Draw();
echo '<p style="float: botton">PEAK MEM USAGE: ' . memory_get_peak_usage() . '</p>';
$cPage->PostDraw();

?>
