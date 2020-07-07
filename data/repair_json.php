<?php
	// connect to database
	include ("../connect.php");
	include ("../node/healthlib.php");
	$database = Connection();

	$result = $database->query("SELECT sensors_measurement.* FROM sensors_station INNER JOIN sensors_measurement ON (sensors_station.last_measurement = sensors_measurement.id)");

	$features = [];
	while($table = $result->fetch_array(MYSQLI_ASSOC)) {
		if ($table['longitude'] == 0)
			continue;

		$properties = health($table["station_id"], 'array');
		if ($properties) $features[] = [
			'type' => 'Feature',
			'properties' => $properties,
			'geometry' => [
				'type' => 'Point',
				'coordinates' => [floatval($table["longitude"]), floatval($table["latitude"])],
			],
		];
	}

	$json = json_encode([
		'type' => 'FeatureCollection',
		'features' => $features,
	], JSON_PRETTY_PRINT);

	// output data
	header("Access-Control-Allow-Origin: *");
	header("Content-Type: application/json; charset=UTF-8");
	echo($json);
?>
