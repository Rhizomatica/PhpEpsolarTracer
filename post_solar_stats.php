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
 * ########################################################################
 * ### DO NOT PLUG IN THE CHARGE CONTROLLER UNTIL DRIVERS ARE INSTALLED ###
 * ########################################################################
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
 * git clone https://github.com/RPi-Distro/rpi-source.git
 * cd ~/repo/rpi-source
 * rpi-source <-- run this to recompile headers before building driver (next step)
 * 
 * get Exar USB Serial Driver driver files from: https://github.com/kasbert/epsolar-tracer/tree/master/xr_usb_serial_common-1a

 * git clone https://github.com/kasbert/epsolar-tracer.git <-- for xr_usb_serial_common
 * cd ~/repo/epsolar-tracer/xr_usb_serial_common-1a
 * make
 *
 * 
 * 	sudo cp -a ./xr_usb_serial_common-1a /usr/src/
 *	sudo dkms add -m xr_usb_serial_common -v 1a
 *  sudo dkms build -m xr_usb_serial_common -v 1a
 *  sudo dkms install -m xr_usb_serial_common -v 1a
 * 

 * 
 * https://www.raspberrypi.org/forums/viewtopic.php?t=171225
 * 
	Tips for Debugging
	------------------
	* Check that the USB UART is detected by the system
		# lsusb
	* Check that the CDC-ACM driver was not installed for the Exar USB UART
		# ls /dev/tty*

		To remove the CDC-ACM driver and install the driver:
		# sudo rmmod cdc-acm
		# sudo modprobe -r usbserial
		# sudo modprobe usbserial
		# cd /home/pi/repo/epsolar-tracer/xr_usb_serial_common-1a
		# sudo rmmod ./xr_usb_serial_common.ko
		# sudo insmod ./xr_usb_serial_common.ko

		* ###### reinstall raspberrypi-bootloader raspberrypi-kernel #####
		* sudo apt-get update && sudo apt-get install 
		* sudo apt-get install raspberrypi-kernel-headers
		* reboot

		 *  sudo chmod 666 /dev/ttyUSB0 <- permissions
		 * 
		 * allow writing to config file
		 * sudo chmod 666 /home/pi/repo/PhpEpsolarTracer/
		 * 
		 * load driver on boot
		 * sudo cp /home/pi/repo/epsolar-tracer/xr_usb_serial_common-1a/xr_usb_serial_common.ko /lib/modules/$(uname -r)/kernel/drivers/
		 * echo 'xr_usb_serial_common' | sudo tee -a /etc/modules
 * 
 * GENERATE FIREBASE TOKEN
 * https://console.firebase.google.com/u/0/project/cabin-3bebb/settings/serviceaccounts/adminsdk
 * 
 * 
 * crontab -e
 *   *_/5 * * * * php /home/pi/repo/PhpEpsolarTracer/post_solar_stats.php <-- remove the underscore
 * 
 * errors written to: /var/log/cron.log
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

function post_to_firebase($content, $configs){
	$auth_token = $configs["auth_token"];
	$auth_failures = $configs["auth_failures"];

	// /2020-01-24.json
	$local_time = new DateTime("now", new DateTimeZone('America/Chicago'));
	$timestamp = $local_time->format('Y-m-d');

	$url = "https://cabin-3bebb.firebaseio.com/solar_stats/" . $timestamp . ".json?auth=" . $auth_token;

	$response = http_post($url, $content);

	if (isset($response["error"]) && isset($auth_failures) && $auth_failures < 3 ){
		$configs['auth_failures'] = $auth_failures + 1;
		write_to_config($configs);


		error_log("auth failures: {$auth_failures}");
		error_log("error posting to firebase, re-authenticating. Error: " . implode(" ", $response), 0);
		$new_config = get_auth_token($configs);
		post_to_firebase($content, $new_config);
	}
	else if (!isset($auth_failures) || $auth_failures > 2){
		$message = "**** too many auth failures, quitting ****";
		error_log($message);
	}
	else {
		$message = "successfully posted to firebase";
		error_log($message);
		var_dump($response);
	}
}

function get_auth_token($configs){
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
	write_to_config($configs);
	return $configs;
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

function write_to_config($configs) {
	try {
		file_put_contents('config.php', '<?php return ' . var_export($configs, true) . ';');
	}
	catch (Exception $e) {
		$error_message =  $e->getMessage();
		error_log("Error writing to config file: {$error_message}");
	}
}

// ************** TESTING **************
// get_auth_token();
// $data = array('new' => "example...?");
// post_to_firebase(json_encode($data));

$configs = include('config.php');
$configs['auth_failures'] = 0;
write_to_config($configs);

$tracer = new PhpEpsolarTracer('/dev/ttyXRUSB0');

if ($tracer->getRealtimeData()) {
		$json = build_json_data($tracer->realtimeData);
		post_to_firebase($json, $configs);
	} 
else {
	print "Cannot get RealTime Data\n";
	post_to_firebase("Cannot get RealTime Data");
}

?>