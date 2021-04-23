<?php
	// connect to database
	include ("../connect.php");
	$database = Connection();
	
	// collect data in json
	$json = '[';
	// Ophalen van de meetdata van de lora sensoren
	// Query gebaseerd op http://stackoverflow.com/a/12625667/740048
	$time = date('Y-m-d H:i:s', time()-24*60*60);

	$result = $database->query("SELECT sensors_measurement.* FROM sensors_station INNER JOIN sensors_measurement ON (sensors_station.last_measurement = sensors_measurement.id) WHERE last_timestamp >= '$time' AND FIND_IN_SET(station_id,'203,417,517,519,661,749,751,752,753,755,756,758,759');");
	
	// exclude data from nodes that lost contact with their sensor
	while($table = $result->fetch_array(MYSQLI_ASSOC)) if ($table["humidity"]>-26 && $table["temperature"]>-26) {
		if ($json != "[") $json .= ",";
		$json.= '{"type":"Feature",';
		$json.= '"properties":{';
		$json.= '"type":"sensor",';
		$json.= '"id":"'.$table["station_id"].'",';
		$json.= '"temperature":"'.$table["temperature"].'",';
		$json.= '"humidity":"'.$table["humidity"].'",';
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
