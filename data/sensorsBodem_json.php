<?php
	// connect to database
	include ("../connect.php");
	$database = Connection();
	
	// collect data in json
	$json = '[';
	// Ophalen van de meetdata van de lora sensoren
	// Query gebaseerd op http://stackoverflow.com/a/12625667/740048
	$time = date('Y-m-d H:i:s', time()-24*60*60);

	$result = $database->query("SELECT sensors_measurement.* FROM sensors_station INNER JOIN sensors_measurement ON (sensors_station.last_measurement = sensors_measurement.id) WHERE last_timestamp >= '$time' AND FIND_IN_SET(station_id,'417,517,519,661,749,751,752,753,755,756,758,759,761,2003,2059,2060,2061,2062,2063,2064,2065,2066,2067,2068,2069,2070,2071,2072,2073,2074,2075,2076,2077,2078,2079,2080,2081,2082,2083,2084,2085,2091,2092,2093,2094,2095,2096,2097,2098');");
	
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
