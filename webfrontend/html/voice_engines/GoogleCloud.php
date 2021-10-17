<?php
function t2s($textstring, $filename)

{
	global $config;
		
		if (isset($_GET['lang'])) {
			$language = $_GET['lang'];
		} else {
			$language = $config['TTS']['messageLang'];
		}
		
		if (isset($_GET['voice'])) {
			$voice = $_GET['voice'];
		} else {
			$voice = $config['TTS']['voice'];
		}
								  		
		LOGGING("Google Cloud TTS has been successful selected", 7);	

		$params = [
			"audioConfig"=>[
				"audioEncoding"=>"MP3"
			],
			"input"=>[
				"text"=>$textstring
			],
			"voice"=>[
				"languageCode"=> $language,
				"name" => $voice
			]
		];
		$data_string = json_encode($params);
		$speech_api_key = $config['TTS']['API-key'];
		$url = 'https://texttospeech.googleapis.com/v1/text:synthesize';

		$handle = curl_init($url);

		curl_setopt($handle, CURLOPT_CUSTOMREQUEST, "POST"); 
		curl_setopt($handle, CURLOPT_POSTFIELDS, $data_string);  
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($handle, CURLOPT_HTTPHEADER, [                                                                          
			'Content-Type: application/json',                                                                                
			'Content-Length: ' . strlen($data_string),
			'X-Goog-Api-Key: ' . $speech_api_key
			]                                                                       
		);
		$response = curl_exec($handle);            
		$responseDecoded = json_decode($response, true);  
		curl_close($handle);
		
		if($responseDecoded['audioContent']){
			# Speicherort der MP3 Datei
			$file = $config['SYSTEM']['ttspath'] ."/". $filename . ".mp3";
			file_put_contents($file, base64_decode($responseDecoded['audioContent']));  
			LOGGING('The text has been passed to google engine for MP3 creation',5);
			return ($filename);       
		} 

		LOGGING('Something went wrong!',5);
		return;
}
