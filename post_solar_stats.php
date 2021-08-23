<?php

/*
 * PHP EpSolar Tracer Class (PhpEpsolarTracer) v0.9
 *
 * Library for communicating with 
 * Epsolar/Epever Tracer BN MPPT Solar Charger Controller
 *
 * THIS PROGRAM COMES WITH ABSOLUTELY NO WARRANTIES !
 * USE IT AT YOUR OWN RISKS !
 *
 * Copyright (C) 2016 under GPL v. 2 license
 * 13 March 2016
 *
 * @author Luca Soltoggio
 * http://www.arduinoelettronica.com/
 * https://arduinoelectronics.wordpress.com/
 *
 * This is an example on how to use the library
 *
 * It queries and prints all charger controller's registries
 *
 * lsusb
 * Bus 001 Device 006: ID 04e2:1411 Exar Corp. 
 * 
 * ls /dev/tty*
 * /dev/ttyACM0 <-- this is no bueno
 * /dev/ttyXRUSB0 <-- should be
 * 

 * https://github.com/toggio/PhpEpsolarTracer/issues/4
 * 
 * 
 * get Exar USB Serial Driver driver files from: https://github.com/kasbert/epsolar-tracer/tree/master/xr_usb_serial_common-1a

 * sudo apt-get install dkms raspberrypi-kernel-headers  
 * 	sudo cp -a ../xr_usb_serial_common-1a /usr/src/
 *	dkms add -m xr_usb_serial_common -v 1a
 *  dkms build -m xr_usb_serial_common -v 1a
 *  dkms install -m xr_usb_serial_common -v 1a
 * 
	Tips for Debugging
	------------------
	* Check that the USB UART is detected by the system
		# lsusb
	* Check that the CDC-ACM driver was not installed for the Exar USB UART
		# ls /dev/tty*

		To remove the CDC-ACM driver and install the driver:

		# rmmod cdc-acm
		# modprobe -r usbserial
		# modprobe usbserial
		# insmod ./xr_usb_serial_common.ko

 *  sudo chmod 666 /dev/ttyUSB0 <- permissions
 * 
 * GENERATE FIREBASE TOKEN
 * https://console.firebase.google.com/u/0/project/cabin-3bebb/settings/serviceaccounts/adminsdk
 */
 
require_once 'PhpEpsolarTracer.php';

function build_json_data($real_time_data){
	$timestamp = date("c");
	$local_time = new DateTime("now", new DateTimeZone('America/Chicago'));

	$array_voltage = $real_time_data[0];
	$array_current = $real_time_data[1];
	$battery_voltage = $real_time_data[3];
	$battery_charging_current = $real_time_data[4];
	$load_voltage = $real_time_data[6];
	$load_current = $real_time_data[7];

	$data = array('timestamp' => $timestamp, 
				  'local_time' => $local_time->format('Y-m-d H:i:s'), 
				  'array_voltage' => $array_voltage, 
				  'array_current'  => $array_current,
				  'battery_voltage' => $battery_voltage,
				  'battery_charging_current' => $battery_charging_current,
				  "load_voltage" => $load_voltage,
				  "load_current" => $load_current);

	return json_encode($data);
}

function post_to_firebase($content){

	$configs = include('config.php');
	$auth_token = $configs["auth_token"];

	// /2020-01-24.json
	$local_time = new DateTime("now", new DateTimeZone('America/Chicago'));
	$timestamp = $local_time->format('Y-m-d');

	$url = "https://cabin-3bebb.firebaseio.com/solar_stats/" . $timestamp . ".json?auth=" . $auth_token;

	$response = http_post($url, $content);

	if (isset($response["error"])){
		error_log("error posting to firebase, re-authenticating. Error: " . implode(" ", $response), 0);
		get_auth_token();
		post_to_firebase($content);
	}
	else {
		print("successfully posted to firebase\r\n");
		var_dump($response);
	}
}

function get_auth_token(){

	$configs = include('config.php');
	
	$web_api_key = $configs["web_api_key"];
	$authUrl = "https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=$web_api_key";

	$jsonData = array(
		'email' => $configs["email"],
		'password' => $configs["password"],
		'returnSecureToken' => true
	);
	
	$jsonDataEncoded = json_encode($jsonData);

	$firebase_auth_response = http_post($authUrl, $jsonDataEncoded);

	// write new auth token to config
	$configs['auth_token'] = $firebase_auth_response["idToken"];
	file_put_contents('config.php', '<?php return ' . var_export($configs, true) . ';');

}

function http_post($url, $body){

	// use key 'http' even if you send the request to https://...
	$options = array(
		'http' => array(
			'header'  => "Content-type: application/json\r\n",
			'method'  => 'POST',
			'content' => $body,
			'ignore_errors' => true
		)
	);

	$context = stream_context_create($options);
	$response = file_get_contents($url, false, $context);

	if ($response === FALSE) { 
		error_log("Error in http_post()");
		error_log("Error: " . $response);
		return $response;
	}
	else{
		return json_decode($response, TRUE);
	}
}

// ************** TESTING **************
// get_auth_token();
// $data = array('new' => "example...?");
// post_to_firebase(json_encode($data));

$tracer = new PhpEpsolarTracer('/dev/ttyXRUSB0');

if ($tracer->getRealtimeData()) {
		$json = build_json_data($tracer->realtimeData);
		post_to_firebase($json);
	} 
else {
	print "Cannot get RealTime Data\n";
	post_to_firebase("Cannot get RealTime Data");
}

?>