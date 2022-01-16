<?php

/**
* Submodul: Playlist
*
**/

/**
/* Funktion : playlist --> lädt eine Playliste in eine Zone/Gruppe
/*
/* @param: Playliste                             
/* @return: nichts
**/

function playlist() {
	Global $debug, $sonos, $master, $sonoszone, $config, $volume;
	
	$master = $_GET['zone'];
	$sonos = new PHPSonos($config['sonoszonen'][$master][0]);
	if(isset($_GET['playlist'])) {
		$sonos->SetQueue("x-rincon-queue:" . trim($sonoszone[$master][1]) . "#0"); 
		$playlist = $_GET['playlist'];
		LOGGING("playlist.php: Playlist '".$_GET['playlist']."' has been found.", 7);
	} else {
		LOGGING("playlist.php: No playlist named '".$_GET['playlist']."' has been found.", 3);
		exit;
	}
	
	# Sonos Playlist ermitteln und mit übergebene vergleichen	
	$sonoslists=$sonos->GetSONOSPlaylists();
	print_r($sonoslists);
	$pleinzeln = 0;
	$gefunden = 0;
	
	#volume_group();
	while ($pleinzeln < count($sonoslists) ) {
		if($playlist == $sonoslists[$pleinzeln]["title"]) {
			$plfile = urldecode($sonoslists[$pleinzeln]["file"]);
			$sonos->ClearQueue();
			LOGGING("playlist.php: Queue has been cleared.", 7);
			$sonos->AddToQueue($plfile); //Datei hinzufügen
			LOGGING("playlist.php: Playlist has been added to Queue.", 7);
			$sonos->SetQueue("x-rincon-queue:". trim($sonoszone[$master][1]) ."#0"); 
			if(!isset($_GET['load'])) {
				$sonos = new PHPSonos($config['sonoszonen'][$master][0]);
				$sonos->SetVolume($volume);
				$sonos->Play();
			}
			LOGGING("playlist.php: Playlist is playing.", 7);
			$gefunden = 1;
		}
		$pleinzeln++;
			if (($pleinzeln == count($sonoslists) ) && ($gefunden != 1)) {
				$sonos->Pause()();
				LOGGING("playlist.php: No playlist with the specified name found.", 3);
				exit;
			}
		}	
		#$sonos = new PHPSonos($config['sonoszonen'][$master][0]);
		#$sonos->SetVolume($volume);
		#$sonos->Play();		
}

/**
* Function: zapzone --> checks each zone in network and if playing add current zone as member
*
* @param: empty
* @return: 
**/

function zapzone() {
	global $config, $volume, $tmp_tts, $sonos, $sonoszone, $master, $playzones, $count, $maxzap, $count_file, $curr_zone_file;
	
	if (file_exists($tmp_tts))  {
		LOGGING("playlist.php: Currently a T2S is running, we skip zapzone for now. Please try again later.",6);
		exit;
	}
	$sonos = new PHPSonos($sonoszone[$master][0]);
	$check_stat = getZoneStatus($master);
	if ($check_stat == "member")  {
		$sonos->BecomeCoordinatorOfStandaloneGroup();
		LOGGING("playlist.php: Zone ".$master." has been ungrouped.",5);
	}
	play_zones();
	$playingzones = $_SESSION["playingzone"];
	#print_r($playingzones);
	$max_loop = count($playingzones);
	$count = countzones();
	// if no zone is playing switch to nextradio
	if (empty($playingzones) or $count > count($playingzones)) {
		nextradio();
		LOGGING("playlist.php: Function nextradio has been used",6);
		sleep($maxzap);
		if(file_exists($count_file))  {
			unlink($count_file);
			LOGGING("playlist.php: Function zapzone has been reseted",6);
		}
		exit;
	}
	$currentZone = currentZone();
	// finally loop by call through array
    foreach ($playingzones as $key => $value) {
		if($key == $currentZone) {
            $nextZoneUrl 	= next($playingzones);
            $nextZoneKey    = key($playingzones);
            //if last element catched, move to first element
            if(!$nextZoneUrl)  {
                $nextZoneUrl 	= reset($playingzones);
                $nextZoneKey    = key($playingzones);
			}
			break;
        } else {
			next($playingzones);
		}
	}
	if (empty($nextZoneKey)) {
		$nextZoneKey = $key;
	}
	echo $nextZoneUrl;
	#echo '<br>Zone: ['.$nextZoneKey.']';
	saveCurrentZone($nextZoneKey);
	if ($config['VARIOUS']['announceradio'] == 1) {
		if ($check_stat == "single")  {
			say_zone($nextZoneKey);
		} else {
			LOGGING("playlist.php: Song/Artist could not be announced because Master is grouped",6);
		}
	}
	unset ($playingzones[$nextZoneKey]);
	$sonos = new PHPSonos($config['sonoszonen'][$master][0]);
	$sonos->SetAVTransportURI("x-rincon:" . $sonoszone[$nextZoneKey][1]);
	LOGGING("playlist.php: Zone ".$master." has been grouped as member to Zone ".$nextZoneKey, 7);
	$sonos->SetMute(false);
	$sonos->SetVolume($volume);
	}


/**
* Sub-Function for zapzone: saveCurrentZone --> saves current playing zone to file
*
* @param: Zone
* @return: 
**/

function saveCurrentZone($nextZoneKey) {
	global $curr_zone_file;
	
	$curr_zone_file = "/run/shm/sonos_currzone_mem.txt";
	
    if(!touch($curr_zone_file)) {
		LOGGING("playlist.php: No permission to write file", 3);
		exit;
    }
	$handle = fopen ($curr_zone_file, 'w');
    fwrite ($handle, $nextZoneKey);
    fclose ($handle);                
} 


/**
* Sub-Function for zapzone: currentZone --> open file and read last playing zone
*
* @param: 
* @return: last playing zone 
**/      

function currentZone() {
	global $config, $master, $curr_zone_file;
	
	$curr_zone_file = "/run/shm/sonos_currzone_mem.txt";
		
	$playingzones = $_SESSION["playingzone"];
	if(!touch($curr_zone_file)) {
		LOGGING("playlist.php: Could not open file", 3);
		exit;
    }
	$currentZone = file($curr_zone_file);
	if(empty($currentZone)) {
		reset($playingzones);
        $currentZone[0] = key($playingzones);
        saveCurrentZone($currentZone[0]);
    }
	return $currentZone[0];
}


/**
* Sub-Function for zapzone: play_zones --> scans through Sonos Network and create array of currently playing zones
*
* @param: 
* @return: array of zones 
**/

function play_zones() {
	global $sonoszone, $master, $sonos, $playingzones;
	
	$playzone = $sonoszone;
	unset($playzone[$master]); 
	foreach ($playzone as $key => $val) {
		$sonos = new PHPSonos($playzone[$key][0]);
		// only zones which are not a group member
		$zonestatus = getZoneStatus($key);
		if ($zonestatus <> 'member') {
			$tmpplay = $sonos->GetPositionInfo();
			// check if zone is currently playing and not TV then add to array
			if(($sonos->GetTransportInfo() == 1) and (substr($tmpplay["TrackURI"], 0, 18) != "x-sonos-htastream:")) {
				$playingzones[$key] = $val[1];
			}
		}
	}
	$_SESSION["playingzone"] = $playingzones;
	#print_r($playingzones);
	return array($playingzones);
}


/**
* Sub-Function for zapzone: countzones --> increment counter by each click
*
* @param: 
* @return: amount if clicks
**/

function countzones() {
	
	global $count_file;
	
	$count_file = "/run/shm/sonos_zapzone_mem_count.txt";
	
	if(!file_exists($count_file)){
        fopen($count_file, "a" );
        #$aufruf=0;
	}
	$counter=fopen($count_file,"r+"); 
	$output=fgets($counter,100);
	$output=$output+1;
	rewind($counter);
	fputs($counter,$output);
	return $output;
}




/** 
* Sub Function for T2S: SavePlaylist --> save temporally Playlist
*
* @param: empty
* @return: playlist "temp_t2s" saved
**/

function SavePlaylist() {
	global $sonos, $id;
	try {
		$sonos->SaveQueue("temp_t2s");
	} catch (Exception $e) {
		LOGGING("playlist.php: The temporary Playlist (PL) could not be saved because the list contains min. 1 Song (URL) which is not longer valid! Please check or remove the list!", 3);
		exit;
	}
	LOGGING("playlist.php: Temporally playlist has been saved.", 6);
}


/**
* Sub Function for T2S: DelPlaylist --> deletes previously saved temporally Playlist
*
* @param: empty
* @return: playlist "temp_t2s" deleted
**/

function DelPlaylist() {
	global $sonos;
	
	$playlists = $sonos->GetSonosPlaylists();
	$t2splaylist = recursive_array_search("temp_t2s",$playlists);
	if(!empty($t2splaylist)) {
		$sonos->DelSonosPlaylist($playlists[$t2splaylist]['id']);
	}
	LOGGING("playlist.php: Temporally playlist has been deleted.", 6);
}


/**
* Funktion : 	random_playlist --> lädt per Zufallsgenerator eine Playliste und spielt sie ab.
*
* @param: exceptions from Syntax
* @return: Playliste
**/

function random_playlist() {
	global $sonos, $sonoszone, $master, $min_vol, $volume, $config;
	
	if (isset($_GET['member'])) {
		LOGGING("playlist.php: This function could not be used with groups!", 3);
		exit;
	}
	$sonoslists = $sonos->GetSONOSPlaylists();
	print_r($sonoslists);
	if(!isset($_GET['except'])) {
		$countpl = count($sonoslists);
		$random = mt_rand(0, $countpl - 1);
	} else {
		$except = $_GET['except'];
		$exception = explode(',',$except);
		for($i = 0; $i < count($exception); $i++) {
			$exception[$i] = str_replace(' ', '', $exception[$i]);
		}
		foreach ($exception as $key => $val) {
			unset($sonoslists[$val]);
		}
		$sonoslists = array_values($sonoslists);
		$countpl = count($sonoslists);
		$random = mt_rand(0, $countpl - 1);
	}
	$plfile = urldecode($sonoslists[$random]["file"]);
	$tmp_volume = $sonos->GetVolume();
	$sonos->ClearQueue();
	$sonos->SetMute(false);
	$sonos->AddToQueue($plfile);
	$sonos->SetQueue("x-rincon-queue:". trim($sonoszone[$master][1]) ."#0"); 
	if(isset($_GET['volume'])) {
		$volume = $_GET['volume'];
	} elseif (isset($_GET['keepvolume'])) {
		if ($tmp_volume >= $min_vol)  {
			$volume = $tmp_volume;
		} else {
			$volume = $config['sonoszonen'][$master][4];
		}
	} else {
		$volume = $config['sonoszonen'][$master][4];
	}
	LOGGING("playlist.php: Random playlist has been added to Queue.", 6);
	$sonos->Play();
}


/**
* Funktion : 	next_dynamic --> Unterfunktion von 'nextpush', lädt PL oder Radio Favoriten, je nachdem was gerade läuft
*
* @param: empty
* @return: Playliste oder Radio
**/

function next_dynamic() {
	global $sonos, $sonoszone, $master;
	
	$titelgesammt = $sonos->GetPositionInfo();
	$titelaktuel = $titelgesammt["Track"];
	$playlistgesammt = count($sonos->GetCurrentPlaylist());
	$sonos->SetPlayMode('NORMAL');
	$sonos->SetMute(false);
	if (($titelaktuel < $playlistgesammt) or (substr($titelgesammt["TrackURI"], 0, 9) == "x-rincon:")) {
		checkifmaster($master);
		$sonos = new PHPSonos($sonoszone[$master][0]);
		$sonos->Next();
		LOGGING("playlist.php: Next Song in Playlist.", 7);
	} else {
		checkifmaster($master);
		$sonos = new PHPSonos($sonoszone[$master][0]);
		$sonos->SetTrack("1");
		LOGGING("playlist.php: Playlist starts at Song Number 1.", 7);
	}
	$sonos->Play();
}


/**
* Optional Sub-Function for zapzone: say_zone --> announce Zone before adding zone to master
*
* @param: $zone
* @return: 
**/
function say_zone($zone) {
			
	global $master, $sonoszone, $config, $volume, $min_vol, $actual, $sonos, $coord, $messageid, $filename, $MessageStorepath, $nextZoneKey, $filenameplaysay;
	require_once("addon/sonos-to-speech.php");
	
	// if batch has been choosed abort
	if(isset($_GET['batch'])) {
		LOGGING("playlist.php: The parameter batch could not be used to announce zone!", 4);
		exit;
	}
	saveZonesStatus(); // saves all Zones Status
	#$sonos->Stop();
	sleep(1);
	$sonos = new PHPSonos($sonoszone[$master][0]);
	#********************** NEW get text variables **********************
	$TL = LOAD_T2S_TEXT();
		
	$play_zone = $TL['SONOS-TO-SPEECH']['ANNOUNCE_ZONE'] ; 
 	#********************************************************************
	# Generiert und kodiert Ansage der Zone
	$text = ($play_zone.' '.$zone);
	$textstring = $text;
	$rawtext = md5($text);
	$filename = "$rawtext";
	select_t2s_engine();
	t2s($textstring, $filename);
	// get Coordinator of (maybe) pair or single player
	$coord = getRoomCoordinator($master);
	LOGGING("playlist.php: Room Coordinator been identified", 7);		
	$sonos = new PHPSonos($coord[0]); 
	$tmp_volume = $sonos->GetVolume();
	$sonos->SetMute(false);
	$volume = $volume + $config['TTS']['correction'];
	play_tts($filename);
	LOGGING("playlist.php: Zone Announcement has been played", 6);	
	restoreSingleZone();
	if(isset($_GET['volume'])) {
		$volume = $_GET['volume'];
	} elseif (isset($_GET['keepvolume'])) {
		if ($tmp_volume >= $min_vol)  {
			$volume = $tmp_volume;
		} else {
			$volume = $config['sonoszonen'][$master][4];
		}
	} else {
		$volume = $config['sonoszonen'][$master][4];
	}
	#$sonos->Play();
	return $volume;
}





?>