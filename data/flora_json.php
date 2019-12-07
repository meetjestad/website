<?php
	// Ophalen van de meetdata
	include ("../connect.php");
	$database = Connection();

	$flora = [];
	$data = $database->query("SELECT id,naam_nl,naam_la,waarnemingen FROM flora");
	while($entry = $data->fetch_array(MYSQLI_ASSOC)) {
		$flora[] = [
			'id' => $entry["id"],
			'naam_nl' => $entry["naam_nl"],
			'naam_la' => $entry["naam_la"],
			'waarnemingen' => json_decode($entry["waarnemingen"]),
		];
	}

	$obs = [];
	$i = 0;
	$result = $database->query("SELECT soort_id,waarneming_id,datum,locatie FROM flora_observaties");
	while($entry = $result->fetch_array(MYSQLI_ASSOC)) {
		$obs[] = [
			'soort_id' => $entry["soort_id"],
			'waarneming_id' => $entry["waarneming_id"],
			'datum' => date('d-m-Y',strtotime($entry["datum"])),
			'locatie' => array_map(floatval, explode(',', $entry["locatie"])),
		];
	}

	$json = json_encode([
		'flora' => $flora,
		'observations' => $obs,
	]);

	header("Access-Control-Allow-Origin: *");
	header("Content-Type: application/json; charset=UTF-8");
	echo($json);
?>
