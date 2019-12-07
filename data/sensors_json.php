<?php
	// connect to database
	include ("../connect.php");
	$database = Connection();

	// collect data in json
	$json = '[';

	// Ophalen van de meetdata van de lora sensoren
	// Query gebaseerd op http://stackoverflow.com/a/12625667/740048
	$time = date('Y-m-d H:i:s', time()-24*60*60);

	$WHERE = "";
	if (isset($_GET['select']) && $_GET['select']=='all') {
		$WHERE = "";
	}
	elseif (isset($_GET['select']) && $_GET['select']=='gone') {
		$WHERE = "WHERE timestamp < '$time'";
	}
	elseif (isset($_GET['start']) || isset($_GET['end'])) {
		if ($_GET['start']) $WHERE = "WHERE timestamp >= '".urldecode($_GET['start'])."'";
		if ($_GET['end']) $WHERE.= ($WHERE?" AND ":"WHERE ")."timestamp <= '".urldecode($_GET['end'])."'";
	}
	else {
		$WHERE = "WHERE timestamp >= '$time'";
	}
	if (isset($_GET['ids'])) $WHERE.= ($WHERE?" AND ":" WHERE ")."station_id IN (".$_GET['ids'].")";

	if (isset($_GET['start']) || isset($_GET['end'])) {
		$result = $database->query("SELECT * FROM sensors_measurement $WHERE");
	}
	else {
		$result = $database->query("SELECT sensors_measurement.* FROM sensors_station INNER JOIN sensors_measurement ON (sensors_station.last_measurement = sensors_measurement.id) $WHERE");
	}

	// exclude data from nodes that lost contact with their sensor
	while($table = $result->fetch_array(MYSQLI_ASSOC)) if ($table["humidity"]>-26 && $table["temperature"]>-26) {
		if ($table['longitude'] == 0)
			continue;
		if ($json != "[") $json .= ",";
		$json.= '{"type":"Feature",';
		$json.= '"properties":{';
		$json.= '"type":"sensor",';
		$json.= '"id":"'.$table["station_id"].'",';
		$json.= '"temperature":"'.$table["temperature"].'",';
		$json.= '"humidity":"'.$table["humidity"].'",';
		$json.= '"light":"'.$table["lux"].'",';
		$json.= '"timestamp_utc":"'.$table["timestamp"].'",';
		$datetime = DateTime::createFromFormat('Y-m-d H:i:s', $table['timestamp'], new DateTimeZone('UTC'));
		$datetime->setTimeZone(new DateTImeZone('Europe/Amsterdam'));
		$json.= '"timestamp":"'.$datetime->format('Y-m-d H:i:s').'",';
		$json.= '"location": "sensor '.$table["station_id"].'"},';
		$json.= '"geometry":{';
		$json.= '"type":"Point",';
		$json.= '"coordinates":['.$table["longitude"].','.$table["latitude"].']}}';
	}

	// end json
	$json.= ']';
	$json = '{"type":"FeatureCollection","features":'.$json.'}';
	
	// output data
	header("Access-Control-Allow-Origin: *");
	header("Content-Type: application/json; charset=UTF-8");
	echo($json);
?>
