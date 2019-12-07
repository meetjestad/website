<?php
	// Ophalen van de meetdata
	include ("../connect.php");
	$database = Connection();

	$flora = '';
	$data = $database->query("SELECT id,naam_nl,naam_la,waarnemingen FROM flora");
	while($entry = $data->fetch_array(MYSQLI_ASSOC)) {
		if ($flora) $flora.= ',';
		$flora.= '{"id":"'.$entry["id"].'",';
		$flora.= '"naam_nl":"'.$entry["naam_nl"].'",';
		$flora.= '"naam_la":"'.$entry["naam_la"].'",';
		$flora.= '"waarnemingen":'.$entry["waarnemingen"].'}';
	}

	$obs = '';
	$i = 0;
	$result = $database->query("SELECT soort_id,waarneming_id,datum,locatie FROM flora_observaties");
	while($entry = $result->fetch_array(MYSQLI_ASSOC)) {
		if ($obs) $obs.= ',';
		$obs.= '{"soort_id":"'.$entry["soort_id"].'",';
		$obs.= '"waarneming_id":"'.$entry["waarneming_id"].'",';
		$obs.= '"datum":"'.date('d-m-Y',strtotime($entry["datum"])).'",';
		$obs.= '"locatie":['.$entry["locatie"].']}';
		$i++;
	}

	mysql_close();

	$json = '{';
	$json.= '"flora":['.$flora.'],';
	$json.= '"observations":['.$obs.']';
	$json.= '}';

	header("Access-Control-Allow-Origin: *");
	header("Content-Type: application/json; charset=UTF-8");
	echo($json);
?>
