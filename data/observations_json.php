<?php
	// Ophalen van de meetdata
	$time = time()-60*5;
	include ("../connect.php");

	$database = Connection();
	$result = $database->query("SELECT soort_id,waarneming_id,datum,locatie,omschrijving FROM flora_observaties");

	// Output genereren voor openLayers
	$features = [];
	while($table = $result->fetch_array(MYSQLI_ASSOC)) {
		$flora = $database->query("SELECT naam_nl,naam_la,afbeelding,omschrijving,waarnemingen FROM flora WHERE id=".$database->real_escape_string($table["soort_id"]));
		$planten = $flora->fetch_array(MYSQLI_ASSOC);
		$waarnemingen = json_decode($planten["waarnemingen"]);

		$features[] = [
			'type' => 'Feature',
			'properties' => [
				'type' => 'observation',
				'naam_nl' => $planten["naam_nl"],
				'naam_la' => $planten["naam_la"],
				'afbeelding' => $planten["afbeelding"],
				'datum' => date('d-m-Y',strtotime($table["datum"])),
				'waarneming' => $waarnemingen[$table["waarneming_id"]],
				'notitie' => $table["omschrijving"],
			],
			'geometry' => [
				'type' => 'Point',
				'coordinates' => array_map('floatval', explode(',', $table["locatie"])),
			],
		];
	}
	$json = json_encode([
		'type' => 'FeatureCollection',
		'features' => $features,
	]);

	header("Access-Control-Allow-Origin: *");
	header("Content-Type: application/json; charset=UTF-8");
	echo($json);
?>
