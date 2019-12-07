<?
	// Ophalen van de meetdata
	$time = time()-60*5;
	include ("../connect.php");

	$database = Connection();
	$result = $database->query("SELECT soort_id,waarneming_id,datum,locatie,omschrijving FROM flora_observaties");

	// Output genereren voor openLayers
	$json = '[';
	while($table = $result->fetch_array(MYSQLI_ASSOC)) {
		$flora = $database->query("SELECT naam_nl,naam_la,afbeelding,omschrijving,waarnemingen FROM flora WHERE id=".$table["soort_id"]);
		$planten = $flora->fetch_array(MYSQLI_ASSOC);
		$waarnemingen = json_decode($planten["waarnemingen"]);
		$omschrijving = filter_var(str_replace(array("\n", "\r")," ",$table['omschrijving']), FILTER_SANITIZE_STRING);

		if ($json != "[") {$json .= ",";}
		$json.= '{"type":"Feature",';
		$json.= '"properties":{';
		$json.= '"type":"observation",';
		$json.= '"naam_nl":"'.$planten["naam_nl"].'",';
		$json.= '"naam_la":"'.$planten["naam_la"].'",';
		$json.= '"afbeelding":"'.$planten["afbeelding"].'",';
		$json.= '"datum":"'.date('d-m-Y',strtotime($table["datum"])).'",';
		$json.= '"waarneming":"'.$waarnemingen[$table["waarneming_id"]].'",';
		$json.= '"notitie":"'.$omschrijving.'"},';
		$json.= '"geometry":{';
		$json.= '"type":"Point",';
		$json.= '"coordinates":['.$table["locatie"].']}}';
	}
	$json.= ']';
	$json = '{"type":"FeatureCollection","features":'.$json.'}';

	header("Access-Control-Allow-Origin: *");
	header("Content-Type: application/json; charset=UTF-8");
	echo($json);
?>
