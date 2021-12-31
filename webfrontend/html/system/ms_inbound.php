#!/usr/bin/php
<?php

/**
/* Funktion : erstellt UDP Template für Loxone
/*
/* @param: 
/* @return: 
**/	

require_once "PHPSonos.php";
require_once "loxberry_system.php";
require_once "loxberry_log.php";

register_shutdown_function('shutdown');

global $sonoszonen, $config, $myIP;
#echo '<PRE>';

// Deklaration Variablen
$myFolder = "$lbpconfigdir";
$myIP = $_SERVER["SERVER_ADDR"];

$params = [	"name" => "Sonos PHP",
			"filename" => "$lbplogdir/sonos.log",
			"append" => 1,
			"addtime" => 1,
			];
$log = LBLog::newLog($params);

#LOGSTART("create XML file");	

// laden der config Dateien
if (!file_exists($myFolder.'/sonos.cfg')) {
	LOGERR('Sonos: ms_inbound.php: The file sonos.cfg could not be opened, please check/complete your Plugin Config!');
	exit(1);
} else {
	$tmpconfig = parse_ini_file(LBPCONFIGDIR.'/sonos.cfg', true);
	if ($tmpconfig === false)  {
		LOGERR('Sonos: ms_inbound.php: The file sonos.cfg could not be parsed, the file may be disrupted. Please check/save your Plugin Config or check file "sonos.cfg" manually!');
		exit(1);
	}
}

if (!file_exists($myFolder.'/player.cfg')) {
	LOGERR('Sonos: ms_inbound.php: The file player.cfg  could not be opened, please check/complete your Plugin Config!');
	exit(1);
} else {
	$tmpplayer = parse_ini_file($myFolder.'/player.cfg', true);
	if ($tmpplayer === false)  {
		LOGERR('Sonos: ms_inbound.php: The file player.cfg could not be parsed, the file may be disrupted. Please check/save your Plugin Config or check file "player.cfg" manually!');
		exit(1);
	}
}

if ($tmpconfig['LOXONE']['LoxDaten'] != 1)   {
	LOGWARN('Sonos: ms_inbound.php: The Communication to Loxone is switched off, please turn on 1st, save config and try again in order to use the template in Loxone!');
	#exit(1);
}
if (count($tmpplayer['SONOSZONEN']) < 1)  {
	LOGERR('Sonos: ms_inbound.php: There are no Sonos Players already fully configured, please check/complete your Plugin Config and save your config before downloading your Template!');
	exit(1);
}

echo $tmpconfig['LOXONE']['LoxPort'];
if (empty($tmpconfig['LOXONE']['LoxPort']))  {
	LOGERR('Sonos: ms_inbound.php: The Loxone UDP port is missing in your config, please check/complete your Plugin Config and save your config before downloading your Template!');
	exit(1);
}
if (empty($tmpconfig['LOXONE']['Loxone']))  {
	LOGWARN('Sonos: ms_inbound.php: You have not selected appropriate Miniserver for inbound communication, please check/complete your Plugin Config and save your config before downloading your Template!');
	#exit(1);
}
LOGOK("Sonos: ms_inbound.php: All Information has been collected successful");

$xmldoc = "VIU_Sonos_UDP.xml";

// prüfen ob Datei existiert, falls ja vorher löschen
if (file_exists($xmldoc)) {
	unlink ($lbphtmldir."/system/".$xmldoc);
	LOGOK("file '".$xmldoc."' been deleted");
	
}

// Vorbereitung der XML Datei
$text = '<?xml version="1.0" encoding="utf-8"?>';
$text .= "\n" . '<VirtualInUdp Title="Sonos4lox" Comment="by Sonos4lox" Address="" Port="'.$tmpconfig['LOXONE']['LoxPort'].'">' . "\r\n";
foreach ($tmpplayer['SONOSZONEN'] as $zone => $key)  {
	$text .= '	<VirtualInUdpCmd Title="Sonos: Volume '.$zone.'" Comment="" Address="" Check="vol_'.$zone.'@\v" Signed="true" Analog="true" SourceValLow="0" DestValLow="0" SourceValHigh="100" DestValHigh="100" DefVal="0" MinVal="0" MaxVal="100"/>' . "\r\n";
	$text .= '	<VirtualInUdpCmd Title="Sonos: Playstate '.$zone.'" Comment="" Address="" Check="stat_'.$zone.'@\v" Signed="true" Analog="true" SourceValLow="0" DestValLow="0" SourceValHigh="100" DestValHigh="100" DefVal="0" MinVal="0" MaxVal="3"/>' . "\r\n";
	$text .= '	<VirtualInUdpCmd Title="Sonos: Groupstate '.$zone.'" Comment="" Address="" Check="grp_'.$zone.'@\v" Signed="true" Analog="true" SourceValLow="0" DestValLow="0" SourceValHigh="100" DestValHigh="100" DefVal="0" MinVal="0" MaxVal="3"/>' . "\r\n";
}
$text .= '</VirtualInUdp>';
LOGOK("Sonos: ms_inbound.php: All Players and commands has been collected");

LOGINF("Sonos: ms_inbound.php: virtual input successfull executed");
file_put_contents($lbphtmldir."/system/".$xmldoc, $text);
LOGOK("Sonos: ms_inbound.php: file '".$xmldoc."' has been downloaded to ".$lbphtmldir."/system/");
return;


function shutdown()
{
	global $log;
	#$log->LOGEND("PHP finished");
	$log = LOGEND("");
	
}

?>