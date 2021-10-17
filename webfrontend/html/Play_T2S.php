<?php

/**
* Submodul: Play_T2S
*
**/

/**
* New Function for T2S: say --> replacement/enhancement for sendmessage/sendgroupmessage
*
* @param: empty
* @return: nothing
**/

function say() {
			
	if(!isset($_GET['member'])) {
		sendmessage();
	} else {
		sendgroupmessage();
	}	
}


/**
* Function : create_tts --> creates an MP3 File based on Text Input
*
* @param: 	Text of Messasge ID
* @return: 	MP3 File
**/		

function create_tts() {
	global $sonos, $config, $filename, $MessageStorepath, $player, $messageid, $textstring, $home, $time_start, $tmp_batch, $MP3path, $filenameplay, $textstring, $volume, $tts_stat;
	
	# setze 1 für virtuellen Texteingang (T2S Start)
	$tts_stat = 1;
	send_tts_source($tts_stat);
	
	if (isset($_GET['greet']))  {
		$Stunden = intval(strftime("%H"));
		$TL = LOAD_T2S_TEXT();
		switch ($Stunden) {
			# Gruß von 04:00 bis 10:00h
			case $Stunden >=4 && $Stunden <10:
				$greet = $TL['GREETINGS']['MORNING_'.mt_rand (1, 5)];
			break;
			# Gruß von 10:00 bis 17:00h
			case $Stunden >=10 && $Stunden <17:
				$greet = $TL['GREETINGS']['DAY_'.mt_rand (1, 5)];
			break;
			# Gruß von 17:00 bis 22:00h
			case $Stunden >=17 && $Stunden <22:
				$greet = $TL['GREETINGS']['EVENING_'.mt_rand (1, 5)];
			break;
			# Gruß nach 22:00h
			case $Stunden >=22:
				$greet = $TL['GREETINGS']['NIGHT_'.mt_rand (1, 5)];
			break;
			default:
				$greet = "";
			break;
		}
	} else {
		$greet = "";
	}
	// messageid has been entered so skip rest and start with play
	if (isset($_GET['messageid'])) {
		$messageid = $_GET['messageid'];
		if (file_exists($config['SYSTEM']['mp3path']."/".$messageid.".mp3") === true)  {
			LOGGING("Messageid '".$messageid."' has been entered", 7);
		} else {
			LOGGING("The corrosponding messageid file '".$messageid.".mp3' does not exist or could not be played. Please check your directory or syntax!", 3);
			exit;
		}	
		#play_tts($messageid);
		return;
	}
						
	$rampsleep = $config['TTS']['rampto'];
	
	isset($_GET['text']) ? $text = $_GET['text'] : $text = '';
	if(isset($_GET['weather'])) {
		// calls the weather-to-speech Function
		if(isset($_GET['lang']) and $_GET['lang'] == "nb-NO" or @$_GET['voice'] == "Liv") {
			include_once("addon/weather-to-speech_no.php");
		} else {
			include_once("addon/weather-to-speech.php");
		}
		$textstring = substr(w2s(), 0, 500);
		LOGGING("weather-to-speech plugin has been called", 7);
		} 
	elseif (isset($_GET['clock'])) {
		// calls the clock-to-speech Function
		include_once("addon/clock-to-speech.php");
		$textstring = c2s();
		LOGGING("clock-to-speech plugin has been called", 7);
		}
	elseif (isset($_GET['pollen'])) {
		// calls the pollen-to-speech Function
		include_once("addon/pollen-to-speach.php");
		$textstring = substr(p2s(), 0, 500);
		LOGGING("pollen-to-speech plugin has been called", 7);
		}
	elseif (isset($_GET['warning'])) {
		// calls the weather warning-to-speech Function
		include_once("addon/weather-warning-to-speech.php");
		$textstring = substr(ww2s(), 0, 500);
		LOGGING("weather warning-to-speech plugin has been called", 7);
	}
	elseif (isset($_GET['distance'])) {
		// calls the time-to-destination-speech Function
		include_once("addon/time-to-destination-speech.php");
		$textstring = substr(tt2t(), 0, 500);
		LOGGING("time-to-distance speech plugin has been called", 7);
		}
	elseif (isset($_GET['witz'])) {
		// calls the weather warning-to-speech Function
		include_once("addon/gimmicks.php");
		$textstring = substr(GetWitz(), 0, 1000);
		LOGGING("Joke plugin has been called", 7);
		}
	elseif (isset($_GET['bauernregel'])) {
		// calls the weather warning-to-speech Function
		include_once("addon/gimmicks.php");
		$textstring = substr(GetTodayBauernregel(), 0, 500);
		LOGGING("Bauernregeln plugin has been called", 7);
		}
	elseif (isset($_GET['abfall'])) {
		// calls the wastecalendar-to-speech Function
		include_once("addon/waste-calendar-to-speech.php");
		$textstring = substr(muellkalender(), 0, 500);
		LOGGING("waste calendar-to-speech  plugin has been called", 7);
		}
	elseif (isset($_GET['calendar'])) {
		// calls the calendar-to-speech Function
		include_once("addon/waste-calendar-to-speech.php");
		$textstring = substr(calendar(), 0, 500);
		LOGGING("calendar-to-speech plugin has been called", 7);
		}
	elseif (isset($_GET['sonos'])) {
		// calls the sonos-to-speech Function
		include_once("addon/sonos-to-speech.php");
		$textstring = s2s();
		$rampsleep = false;
		LOGGING("sonos-to-speech plugin has been called", 7);
		}
	elseif ((!isset($_GET['text'])) and (isset($_GET['playbatch']))) {
		LOGGING("no text has been entered", 3);
		exit();
		}
	elseif ($text <> '') {
		// prepares the T2S message
		if (empty($greet))  {
			$textstring = $text;
			LOGGING("Textstring has been entered", 7);	
		} else {
			$textstring = $greet.". ".$text;
			LOGGING("Greeting + Textstring has been entered", 7);		
		}	
	}	
	
	// encrypt MP3 file as MD5 Hash
	$filename  = md5($textstring);
	#echo 'textstring: '.$textstring.'<br>';
	#echo 'filename: '.$filename.'<br>';
	// calls the various T2S engines depending on config)
	if ($textstring != '') {
		if ($config['TTS']['t2s_engine'] == 1001) {
			include_once("voice_engines/VoiceRSS.php");
		}
		if ($config['TTS']['t2s_engine'] == 3001) {
			include_once("voice_engines/MAC_OSX.php");	
		}
		if ($config['TTS']['t2s_engine'] == 6001) {
			include_once("voice_engines/ResponsiveVoice.php");
		}
		if ($config['TTS']['t2s_engine'] == 7001) {
			include_once("voice_engines/Google.php");	
		}
		if ($config['TTS']['t2s_engine'] == 5001) {
			include_once("voice_engines/Pico_tts.php");	
		}
		if ($config['TTS']['t2s_engine'] == 4001) {
			include_once("voice_engines/Polly.php");	
		}
		if ($config['TTS']['t2s_engine'] == 8001) {
			include_once("voice_engines/GoogleCloud.php");	
		}
		//echo filesize($config['SYSTEM']['ttspath']."/".$filename.".mp3");
	
	if(file_exists($config['SYSTEM']['ttspath']."/".$filename.".mp3") && empty($_GET['nocache'])) {
		LOGGING("MP3 grabbed from cache: '$textstring' ", 6);
	} else {
		t2s($textstring, $filename);
		if (($config['TTS']['t2s_engine'] == 6001) or ($config['TTS']['t2s_engine'] == 7001) or ($config['TTS']['t2s_engine'] == 4001))    {
			// ** generiere MP3 ID3 Tags **
			require_once("system/bin/getid3/getid3.php");
			#$getID3 = new getID3;
			#write_MP3_IDTag($textstring);
		}
		// check if filename is < 1 Byte
		if (filesize($config['SYSTEM']['ttspath']."/".$filename.".mp3") < 1)  {
			LOGGING("Something went wrong with your T2S, the filesize is 0 Byte :-( Please check/save your config, check authorisation for User loxberry and try again!", 3);	
			exit(1);
		}
		echo $textstring;
		
	}
	return $filename;
	}
}


/**
* Function : play_tts --> play T2S or MP3 File
*
* @param: 	MessageID, Parameter zur Unterscheidung ob Gruppen oder EInzeldurchsage
* @return: empty
**/		

function play_tts($filename) {
	global $volume, $config, $messageid, $sonos, $text, $messageid, $sleeptimegong, $sonoszone, $sonoszonen, $master, $coord, $actual, $textstring, $player, $time_start, $t2s_batch, $filename, $textstring, $home, $MP3path, $lbpplugindir, $logpath, $try_play, $MessageStorepath, $filename, $tts_stat;
		
		$coord = getRoomCoordinator($master);
		$sonos = new PHPSonos($coord[0]);
		if (isset($_GET['messageid'])) {
			// Set path if messageid
			LOGGING("Path for messageid's been adopted", 7);
			$messageid = $_GET['messageid'];
		} else {
			// Set path if T2S
			LOGGING("Path for T2S been adopted", 7);	
		}
		// if Playbar is in Modus TV switch to Playlist 1st
		if (substr($actual[$master]['PositionInfo']["TrackURI"], 0, 18) == "x-sonos-htastream:")  {  
			$sonos->SetQueue("x-rincon-queue:".$coord[1]."#0");
			LOGGING("Playbar was playing", 7);		
		}
		// Playlist is playing
		$save_plist = count($sonos->GetCurrentPlaylist());
		
		// if Playlist has more then 998 entries
		if ($save_plist > 998) {
			// save temporally playlist
			SavePlaylist();
			$sonos->ClearQueue();
			LOGGING("Queue has been cleared", 7);		
			$message_pos = 1;
			LOGGING("Playlist has more then 998 songs", 6);		
		}
		// if Playlist has more then 1 or less then 999 entries
		if ($save_plist >= 1 && $save_plist <= 998) {
			$message_pos = count($sonos->GetCurrentPlaylist()) + 1;
		} else {
			// No Playlist is playing
			$message_pos = 1;
		}
			
		// Playgong/jingle to be played upfront
		#*****************************************************************************************************
		if(isset($_GET['playgong'])) {
			if ($_GET['playgong'] == 'no')	{
				LOGGING("'playgong=no' could not be used in syntax, only 'playgong=yes' or 'playgong=file' are allowed", 3);
				exit;
			}
			if(empty($config['MP3']['file_gong'])) {
				LOGGING("Standard file for jingle is missing in Plugin config. Please maintain before usage.", 3);
				exit;	
			}
			if (($_GET['playgong'] != "yes") and ($_GET['playgong'] != "no") and ($_GET['playgong'] != " ")) {
				$file = $_GET['playgong'];
				$file = $file.'.mp3';
				$valid = mp3_files($file);
				if ($valid === true) {
					$jinglepath = $config['SYSTEM']['httpinterface']."/mp3/".trim($file);
					$sonos->AddToQueue($jinglepath);
					LOGGING("Individual jingle '".trim($file)."' added to Queue", 7);	
				} else {
					LOGGING("Entered jingle '".$file."' for playgong is not valid or nothing has been entered. Please correct your syntax", 3);
					exit;
				}
			} else {
				$jinglepath = $config['SYSTEM']['httpinterface']."/mp3/".trim($config['MP3']['file_gong']);
				$sonos->AddToQueue($jinglepath);
				LOGGING("Standard jingle '".trim($config['MP3']['file_gong'])."' added to Queue", 7);	
			}
		}
		#******************************************************************************************************
		// if batch has been created add all T2S
		$filenamebatch = "t2s_batch.txt";
		if ((file_exists($filenamebatch)) and (!isset($_GET['playbatch']))){
			$t2s_batch = file($filenamebatch, FILE_IGNORE_NEW_LINES);
			foreach ($t2s_batch as $t2s => $t2s_value) {
				$sonos->AddToQueue($t2s_value.".mp3");
			}
			LOGGING("Messages from batch has been added to Queue", 7);	
		} else {
			// if no batch has been created add single T2S
			$t2s_file = file_exists($config['SYSTEM']['ttspath']."/".$filename.".mp3");
			$meid_file = file_exists($config['SYSTEM']['mp3path']."/".$messageid.".mp3");
			if (($t2s_file  === true) or ($meid_file  === true))  {
				if ($t2s_file  === true)  {
					# check if T2S has been saved/coded correctly
					if (filesize($config['SYSTEM']['ttspath']."/".$filename.".mp3") == 0)  {
						LOGGING("Something went wrong :-( the file has not been saved. Please check your storage device and your T2S Engine settings!", 3);
						exit;						
					}
					$sonos->AddToQueue($config['SYSTEM']['httpinterface']."/".$filename.".mp3");
					LOGGING("T2S '".trim($filename).".mp3' has been added to Queue", 7);
				} else {
					$sonos->AddToQueue($config['SYSTEM']['httpinterface']."/mp3/".$messageid.".mp3");
					LOGGING("MP3 File '".trim($messageid).".mp3' has been added to Queue", 7);
					$filename = $messageid;
				}
			} else {
				LOGGING("The file '".trim($filename).".mp3' does not exist or could not be played. Please check your directory or your T2S settings!", 3);
				exit;
			}
		}
		$sonos->SetQueue("x-rincon-queue:".trim($sonoszone[$master][1])."#0");
		$sonos->SetPlayMode('NORMAL');
		LOGGING("Playmode has been set to NORMAL", 7);		
		$sonos->SetTrack($message_pos);
		LOGGING("Message has been set to Position '".$message_pos."' in current Queue", 7);		
		$sonos->SetGroupMute(false);
		$sonos->SetVolume($volume);
		LOGGING("Mute for relevant Player(s) has been turned off", 7);		
		try {
			$try_play = $sonos->Play();
			LOGGING("T2S has been passed to Sonos Application", 5);	
			LOGGING("In case the announcement wasn't played please check any Messages appearing in the Sonos App during processing the request.", 5);	
		} catch (Exception $e) {
			LOGGING("The requested T2S message ".trim($messageid).".mp3 could not be played!", 3);
			$notification = array (	"PACKAGE" => $lbpplugindir,
									"NAME" => "Sonos",
									"MESSAGE" => "The requested T2S message could not be played!",
									"SEVERITY" => 3,
									"fullerror" => "the received error: ".$try_play,
									"LOGFILE" => LBPLOGDIR . "/sonos.log"
									);
			notify_ext($notification);
		}
		$abort = false;
		sleep($sleeptimegong); // wait according to config
		while ($sonos->GetTransportInfo()==1) {
			usleep(200000); // check every 200ms
		}
		// If batch T2S has been be played
		if (!empty($t2s_batch))  {
			$i = $message_pos;
			foreach ($t2s_batch as $t2s => $value) {
				$mess_pos = $message_pos;
				$sonos->RemoveFromQueue($mess_pos);
				$i++;
			} 
			unlink ($filenamebatch);
			LOGGING("T2S batch files has been removed from Queue", 7);	
		} else {
			// If single T2S has been be played
			$sonos->RemoveFromQueue($message_pos);
			LOGGING("T2S has been removed from Queue", 7);	
			if(isset($_GET['playgong'])) {		
				$sonos->RemoveFromQueue($message_pos);
				LOGGING("Jingle has been removed from Queue", 7);	
			}	
		}	
		// if Playlist has more than 998 entries
		if ($save_plist > 998) {
			$sonos->ClearQueue();
			LOGGING("Queue has been cleared", 7);		
			LoadPlaylist("temp_t2s");
			LOGGING("Temporary saved playlist 'temp_t2s' has been loaded back into Queue", 7);		
			DelPlaylist();
			LOGGING("Temporary playlist 'temp_t2s' has been finally deleted", 7);		
		// if Playlist has less than or equal 998 entries
		}
		LOGGING("T2S play process has been successful finished", 6);
		return $actual;
		
		
}


/**
* Function : sendmessage --> translate a text into speech for a single zone
*
* @param: Text or messageid (Number)
* @return: 
**/

function sendmessage() {
			global $text, $master, $messageid, $logging, $textstring, $voice, $config, $actual, $player, $volume, $source, $sonos, $coord, $time_start, $filename, $sonoszone, $tmp_batch, $mode, $MP3path, $tts_stat;
			
			$time_start = microtime(true);
			if ((empty($config['TTS']['t2s_engine'])) or (empty($config['TTS']['messageLang'])))  {
				LOGGING("There is no T2S engine/language selected in Plugin config. Please select before using T2S functionality.", 3);
				exit();
			}
			if ((!isset($_GET['text'])) && (!isset($_GET['messageid'])) && (!isset($_GET['sonos'])) &&
				(!isset($_GET['text'])) && (!isset($_GET['weather'])) && (!isset($_GET['abfall'])) &&
				(!isset($_GET['witz'])) && (!isset($_GET['pollen'])) && (!isset($_GET['warning'])) &&
				(!isset($_GET['bauernregel'])) && (!isset($_GET['distance'])) && (!isset($_GET['clock'])) && 
				(!isset($_GET['calendar'])) && (!isset($_GET['action'])) == 'playbatch') {
				LOGGING("Wrong Syntax, please correct! Even 'say&text=' or 'say&messageid=' are necessary to play an anouncement. (check Wiki)", 3);	
				exit;
			}
			// if batch has been choosed save filenames to a txt file and exit
			if(isset($_GET['batch'])) {
				if((isset($_GET['volume'])) or (isset($_GET['rampto'])) or (isset($_GET['playmode'])) or (isset($_GET['playgong']))) {
					LOGGING("The parameter volume, rampto, playmode or playgong are not allowed to be used in conjunction with batch. Please remove from syntax!", 4);
					exit;
				}
				if (isset($_GET['messageid'])) {
					$messageid = $_GET['messageid'];
				} else {
					create_tts();
				}
				// creates file to store T2S filenames
				$filenamebatch = "t2s_batch.txt";
				$file = fopen($filenamebatch, "a+");
				if($file == false ) {
					LOGGING("There is no T2S batch file to be written!", 3);
					exit();
				}
				if (strlen($filename) == '32') {
					fwrite($file, $config['SYSTEM']['httpinterface']."/".$filename."\r\n");
					LOGGING("T2S '".$filename.".mp3' has been added to batch", 7);
					LOGGING("Please ensure to call later '...action=playbatch', otherwise the messages could be played uncontrolled", 5);					
				} else {
					fwrite($file, $config['SYSTEM']['httpinterface']."/".$MP3path."/".$messageid."\r\n");
					LOGGING("Messageid '".$messageid."' has been added to batch", 7);
					LOGGING("Please ensure to call later '...action=playbatch', otherwise the messages could be played uncontrolled", 5);										
				}
				fclose($file);
				exit;
			}
			#if(isset($_GET['volume']) && is_numeric($_GET['volume']) && $_GET['volume'] >= 0 && $_GET['volume'] <= 100) {
			#	$volume = $_GET['volume'];
				#LOGGING("Volume from syntax been adopted", 7);		
			#} else 	{
				// übernimmt Standard Lautstärke der angegebenen Zone aus config.php
			#	$volume = $config['sonoszonen'][$master][3];
				#LOGGING("Standard Volume from zone ".$master."  been used", 7);		
			#}
			#checkaddon();
			#checkTTSkeys();
			$save = saveZonesStatus(); // saves all Zones Status
			SetVolumeModeConnect($mode = '0', $master);
			$return = getZoneStatus($master); // get current Zone Status (Single, Member or Master)
			if($return == 'member') {
				if(isset($_GET['sonos'])) { // check if Zone is Group Member, then abort
					LOGGING("The specified zone is part of a group! There are no information available.", 4);
				exit;
				}
			}
			create_tts();
			// stop 1st before Song Name been played
			$test = $sonos->GetPositionInfo();
			if (($return == 'master') or ($return == 'member')) {
				$sonos->BecomeCoordinatorOfStandaloneGroup();  // in case Member or Master then remove Zone from Group
				LOGGING("Zone ".$master." has been removed from group", 6);		
			}
			if (substr($test['TrackURI'], 0, 18) == "x-sonos-htastream:") {
				$sonos->SetQueue("x-rincon-queue:". $sonoszone[$master][1] ."#0");
				LOGGING("Streaming/TV endet successful", 7);		
			}
			#if ((substr($test, 0, 18) !== "x-sonos-htastream:") and (!isset($_GET['sonos'])))  {
			if (!isset($_GET['sonos']))  {
				$sonos->Stop();
				usleep(200000);
			}
			// get Coordinator of (maybe) pair or single player
			$coord = getRoomCoordinator($master);
			LOGGING("Room Coordinator has been identified", 7);		
			$sonos = new PHPSonos($coord[0]); 
			$sonos->SetMute(false);
			play_tts($messageid);
			restoreSingleZone();
			$mode = "";
			$actual[$master]['CONNECT'] == 'true' ? $mode = '1' : $mode = '0';
			SetVolumeModeConnect($mode, $master);
			$time_end = microtime(true);
			$t2s_time = $time_end - $time_start;
			#echo "Die T2S dauerte ".round($t2s_time, 2)." Sekunden.\n";
			LOGGING("The requested single T2S tooks ".round($t2s_time, 2)." seconds to be processed.", 5);	
					
	}

/**
* Function : sendgroupmessage --> translate a text into speech for a group of zones
*
* @param: Text or messageid (Number)
* @return: 
**/
			
function sendgroupmessage() {			
			global $coord, $sonos, $text, $sonoszone, $member, $master, $zone, $messageid, $logging, $textstring, $voice, $config, $mute, $volume, $membermaster, $getgroup, $checkgroup, $time_start, $mode, $modeback, $actual;
			
			$time_start = microtime(true);
			if ((empty($config['TTS']['t2s_engine'])) or (empty($config['TTS']['messageLang'])))  {
				LOGGING("There is no T2S engine/language selected in Plugin config. Please select before using T2S functionality.", 3);
				exit();
			}
			if ((!isset($_GET['text'])) && (!isset($_GET['messageid'])) && (!isset($_GET['sonos'])) &&
				(!isset($_GET['text'])) && (!isset($_GET['weather'])) && (!isset($_GET['abfall'])) &&
				(!isset($_GET['witz'])) && (!isset($_GET['pollen'])) && (!isset($_GET['warning'])) &&
				(!isset($_GET['bauernregel'])) && (!isset($_GET['distance'])) && (!isset($_GET['clock'])) && 
				(!isset($_GET['calendar'])) && (!isset($_GET['action'])) == 'playbatch') {
				LOGGING("Wrong Syntax, please correct! Even 'say&text=' or 'say&messageid=' are necessary to play an anouncement. (check Wiki)", 3);	
				exit;
			}
			if(isset($_GET['batch'])) {
				LOGGING("The parameter batch is not allowed to be used in groups. Please use single message to prepare your batch!", 4);
				exit;
			}
			if(isset($_GET['volume']) or isset($_GET['groupvolume']))  { 
				isset($_GET['volume']) ? $groupvolume = $_GET['volume'] : $groupvolume = $_GET['groupvolume'];
				if ((!is_numeric($groupvolume)) or ($groupvolume < 0) or ($groupvolume > 200)) {
					LOGGING("The entered volume of ".$groupvolume." must be even numeric or between 0 and 200! Please correct", 4);	
				}
			}
			if(isset($_GET['sonos'])) {
				LOGGING("The parameter 'sonos' couldn't be used for group T2S!", 4);
				exit;
			}
			#checkaddon();
			#checkTTSkeys();
			$master = $_GET['zone'];
			$member = $_GET['member'];
			create_tts();
			// if parameter 'all' has been entered all zones were grouped
			if($member === 'all') {
				#$member = array();
				$memberon = array();
				foreach ($sonoszone as $zone => $ip) {
					$zoneon = checkZoneOnline($zone);
					// exclude master Zone
					if ($zone != $master) {
						if ($zoneon === (bool)true)  {
							array_push($memberon, $zone);
						}
					}
				}
				$member = $memberon;
				LOGGING("All Players has been grouped to Player ".$master, 5);	
			} else {
				$member = explode(',', $member);
				$memberon = array();
				foreach ($member as $value) {
					$zoneon = checkZoneOnline($value);
					if ($zoneon === (bool)true)  {
						array_push($memberon, $value);
					} else {
						LOGGING("Player '".$value."' could not be added to the group!!", 4);
					}
				}
				$member = $memberon;
			}
			if (in_array($master, $member)) {
				LOGGING("The zone ".$master." could not be entered as member again. Please remove from Syntax '&member=".$master."' !", 3);
				exit;
			}
			// prüft alle Member ob Sie Online sind und löscht ggf. Member falls nicht Online
			#checkZonesOnline($member);
			$coord = getRoomCoordinator($master);
			LOGGING("Room Coordinator has been identified", 7);		
			// speichern der Zonen Zustände
			$save = saveZonesStatus(); // saves all Zones Status
			foreach($member as $newzone) {
				SetVolumeModeConnect($mode = '0', $newzone);
				SetVolumeModeConnect($mode = '0', $master);
			}
			// create Group for Announcement
			$masterrincon = $coord[1]; 
			$sonos = new PHPSonos($coord[0]);
			$sonos->BecomeCoordinatorOfStandaloneGroup();
			LOGGING("Group Coordinator has been made to single zone", 7);		
			// grouping
			foreach ($member as $zone) {
				$sonos = new PHPSonos($sonoszone[$zone][0]);
				if ($zone != $master) {
					$sonos->SetAVTransportURI("x-rincon:" . $masterrincon); 
					LOGGING("Member '$zone' is now connected to Master Zone", 7);		
				}
			}
			#sleep($config['TTS']['sleepgroupmessage']); // warten gemäß config.php bis Gruppierung abgeschlossen ist
			$sonos = new PHPSonos($coord[0]);
			$sonos->SetPlayMode('NORMAL'); 
			$sonos->SetQueue("x-rincon-queue:". $coord[1] ."#0");
			if (!isset($_GET['sonos']))  {
				$sonos->Stop();
			}
			#create_tts();
			$group = $member;
			// master der array hinzufügen
			array_push($group, $master);
			// Regelung des Volumes für T2S
			$sonos->SetVolume($volume);
			volume_group();
			play_tts($messageid);
			// wiederherstellen der Ursprungszustände
			restoreGroupZone();
			LOGGING("Restore previous settings will be called", 7);		
			foreach($member as $newzone) {
				$mode = "";
				$actual[$newzone]['CONNECT'] == 'true' ? $mode = '1' : $mode = '0';
				SetVolumeModeConnect($mode, $newzone);
			}
			$mode = "";
			$actual[$master]['CONNECT'] == 'true' ? $mode = '1' : $mode = '0';
			SetVolumeModeConnect($mode, $master);
			#$modeback = '1' ? $mode = '1' : $mode = '0';
			#SetVolumeModeConnect($mode);
			$time_end = microtime(true);
			$t2s_time = $time_end - $time_start;
			#echo "Die T2S dauerte ".round($t2s_time, 2)." Sekunden.\n";
			LOGGING("The requested group T2S tooks ".round($t2s_time, 2)." seconds to be processed.", 5);					
}

/**
* New Function for T2S: t2s_playbatch --> allows T2S to be played in batch mode
*
* @param: empty
* @return: T2S
**/
function t2s_playbatch() {
	global $textstring;
			
	$textstring = true;
	$filenamebatch = "t2s_batch.txt";
	if (!file_exists($filenamebatch)) {
		LOGGING("There is no T2S batch file to be played!", 4);
		exit();
	}
	say();
}



/**
* Function : send_tts_source --> sendet eine 1 zu Beginn von T2S und eine 0 am Ende
*
* @param: 0 oder 1
* @return: leer
**/

function send_tts_source($tts_stat)  {
	
	require_once('system/io-modul.php');
	global $config, $tmp_tts, $sonoszone, $master, $ms, $tts_stat, $config; 
	
	$tmp_tts = "/run/shm/tmp_tts";
	if ($tts_stat == 1)  {
			if(!touch($tmp_tts)) {
			LOGGING("No permission to write file", 3);
			exit;
		}
		$handle = fopen ($tmp_tts, 'w');
		fwrite ($handle, $tts_stat);
		fclose ($handle); 
	} 
	
	// check if Data transmission is switched off
	if(!is_enabled($config['LOXONE']['LoxDaten'])) {
		return;
	}
	
	// ceck if configured MS is fully configured
	if (!isset($ms[$config['LOXONE']['Loxone']])) {
		LOGWARN ("Your selected Miniserver from Sonos4lox Plugin config seems not to be fully configured. Please check your LoxBerry miniserver config!") ;
		exit(1);
	}
			
	if($config['LOXONE']['LoxDaten'] == 1) {
		// obtain selected Miniserver from Plugin config
		$my_ms = $ms[$config['LOXONE']['Loxone']];
		# send TEXT data
		$lox_ip			= $my_ms['IPAddress'];
		$lox_port 	 	= $my_ms['Port'];
		$loxuser 	 	= $my_ms['Admin'];
		$loxpassword 	= $my_ms['Pass'];
		$loxip = $lox_ip.':'.$lox_port;
		try {
			$data['t2s_'.$master] = $tts_stat;
			#$check_exist = http_send($config['LOXONE']['Loxone'], $data, $value = null);
			#var_dump($check_exist);
			#exit;
			#ms_send_mem($config['LOXONE']['Loxone'], $data, $value = null);
			$handle = @get_file_content("http://$loxuser:$loxpassword@$loxip/dev/sps/io/t2s_$master/$tts_stat"); // Radio oder Playliste
		} catch (Exception $e) {
			LOGERR("The connection to Loxone could not be initiated, we have to abort...");	
			exit;
		}
	return;
	}
}

?>