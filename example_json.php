<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
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
 * It creates a web page with tracer datas
 *
 */

require_once 'PhpEpsolarTracer.php';
$tracer = new PhpEpsolarTracer('/dev/ttyUSB0');

$fields = array(

	"info" => array(
		"manufacturer",
		"model",
		"version",
	),

	"rated" => array(
		"pv_rated_voltage",
		"pv_rated_current",
		"pv_rated_power",
		"rated_charging_voltage",
		"rated_charging_current",
		"rated_charging_power",
		"charging_mode",
		"rated_load_current",
	),

	"real_time" => array(
		"pv_voltage",
		"pv_current",
		"pv_power",
		"battery_voltage",
		"battery_charging_current",
		"battery_charging_power",
		"load_voltage",
		"load_current",
		"load_power",
		"battery_temperature",
		"charger_temperature",
		"heat_sink_temperature",
		"battery_soc",
		"remote_battery_temperature",
		"system_rated_voltage",
		"battery_status",
		"equipment_status",
	),

	"statistics" => array(
		"max_pv_voltage_today",
		"min_pv_voltage_today",
		"max_battery_voltage_today",
		"min_battery_voltage_today",
		"consumed_energy_today",
		"consumed_energy_this_month",
		"consumed_energy_this_year",
		"total_consumed_energy",
		"generated_energy_today",
		"generated_energy_this_month",
		"generated_energy_this_year",
		"total_generated_energy",
		"carbon_dioxide_reduction",
		"net_battery_current",
		"battery_temperature",
		"ambient_temperature",
	),

	"settings" => array(
		"battery_type",
		"battery_capacity",
		"temperature_compensation_coeff",
		"high_voltage_disconnect",
		"charging_limit_voltage",
		"over_voltage_reconnect",
		"equalization_voltage",
		"boost_voltage",
		"float_voltage",
		"boost_reconnect_voltage",
		"low_voltage_reconnect",
		"under_voltage_recover",
		"under_voltage_warning",
		"low_voltage_disconnect",
		"discharging_limit_voltage",
		"realtime_clock_sec",
		"realtime_clock_min",
		"realtime_clock_hour",
		"realtime_clock_day",
		"realtime_clock_month",
		"realtime_clock_year",
		"equalization_charging_cycle",
		"battery_temp_warning_hi_limit",
		"battery_temp_warning_low_limit",
		"controller_temp_hi_limit",
		"controller_temp_hi_limit_reconnect",
		"components_temp_hi_limit",
		"components_temp_hi_limit_reconnect",
		"line_impedance",
		"night_time_threshold_voltage",
		"light_signal_on_delay_time",
		"day_time_threshold_voltage",
		"light_signal_off_delay_time",
		"load_controlling_mode",
		"working_time_length1_min",
		"working_time_length1_hour",
		"working_time_length2_min",
		"working_time_length2_hour",
		"turn_on_timing1_sec",
		"turn_on_timing1_min",
		"turn_on_timing1_hour",
		"turn_off_timing1_sec",
		"turn_off_timing1_min",
		"turn_off_timing1_hour",
		"turn_on_timing2_sec",
		"turn_on_timing2_min",
		"turn_on_timing2_hour",
		"turn_off_timing2_sec",
		"turn_off_timing2_min",
		"turn_off_timing2_hour",
		"length_of_night_min",
		"length_of_night_hour",
		"battery_rated_voltage_code",
		"load_timing_control_selection",
		"default_load_on_off",
		"equalize_duration",
		"boost_duration",
		"discharging_percentage",
		"charging_percentage",
		"management_mode",
	),

	"coils" => array(
		"manual_control_load",
		"enable_load_test_mode",
		"force_load_on_off",
	),

	"discrete" => array(
		"over_temperature_inside_device",
		"day_night",
	),
);

$data = array();

$sections = array(
	"info" 			=> array("getInfoData", "infoData"),
	"rated" 		=> array("getRatedData", "ratedData"),
	"real_time" 	=> array("getRealtimeData", "realtimeData"),
	"statistics" 	=> array("getStatData", "statData"),
	"settings" 		=> array("getSettingData", "settingData"),
	"coils" 		=> array("getCoilData", "coilData"),
	"discrete" 		=> array("getDiscreteData", "discreteData"),
);

foreach ($sections as $section => $spec) {
	list ($fn, $dataArray) = $spec;

	if ($tracer->$fn()) {
		fillData($data, $section, $tracer->$dataArray);
	} else {
		// Try again for one failure
		if ($tracer->$fn()) {
			fillData($data, $section, $tracer->$dataArray);
		} else {
			// Fail completely for two failures
			fail();
		}
	}
}

if (isset($tracer->realtimeData[15])) {
	$batt_status = $tracer->realtimeData[15];
	$charge_status = $tracer->realtimeData[16];

	$batt_status_volt = array(
		"NORMAL",
		"OVER_VOLT",
		"UNDER_VOLT",
		"LOW_DISCONNECT",
		"FAULT"
	)[$batt_status & 7];

	$batt_status_temp = array(
		"NORMAL",
		"OVER_TEMP",
		"BELOW_TEMP",
	)[($batt_status >> 4) & 15];

	$charge_phase = array(
		"NOT_CHARGING",
		"FLOAT",
		"BOOST",
		"EQUALIZATION"
	)[($charge_status >> 2) & 3];

	$pv_volt_status = array(
		"NORMAL",
		"NOT_CONNECTED",
		"OVER_VOLT",
		"ERROR"
	)[($charge_status >> 14) & 3];

	$sett = $tracer->settingData;

	$data["status"] = array(
		"date_time" => sprintf("20%02d-%02d-%02dT%02d:%02d:%02d", $sett[20], $sett[19], $sett[18], $sett[17], $sett[16], $sett[15]),
		"battery_status" => array(
			"battery_status_voltage" => $batt_status_volt,
			"battery_status_temperature" => $batt_status_temp,
			"battery_internal_resistance_abnormal" => (bool)($batt_status & 256),
			"battery_rated_voltage_error" => (bool)($batt_status & 32768),
		),
		"charging_status" => array(
			"running" => (bool)($charge_status & 1),
			"fault" => (bool)($charge_status & 2),
			"charging_phase" => $charge_phase,
			"pv_short" => (bool)($charge_status & 16),
			"load_mosfet_short" => (bool)($charge_status & 128),
			"load_short" => (bool)($charge_status & 256),
			"load_over_current" => (bool)($charge_status & 512),
			"pv_over_current" => (bool)($charge_status & 1024),
			"anti_reverse_mosfet_short" => (bool)($charge_status & 2048),
			"charging_or_anti_reverse_mosfet_short" => (bool)($charge_status & 4096),
			"charging_mosfet_short" => (bool)($charge_status & 8192),
			"pv_voltage_status" => $pv_volt_status,
		),
	);
} else {
	fail();
}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

echo json_encode($data);

function fillData (&$data, $field, $array) {
	global $fields;

	$data[$field] = array();

	foreach ($array as $i => $value) {
		$data[$field][$fields[$field][$i]] = $value;
	}
}

function fail () {
	header("HTTP/1.1 500 Server Error");
	header("Access-Control-Allow-Origin: *");
	exit;
}