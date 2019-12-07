<?
	// http://www.movable-type.co.uk/scripts/latlong.html
	function distance($p, $q) {
		$R = 6371e3; // metres
		$phi1 = deg2rad($p['lat']);
		$phi2 = deg2rad($p['lat']);
		$dphi = deg2rad($q['lat']-$p['lat']);
		$dlambda = deg2rad($q['lon']-$p['lon']);
		$a = sin($dphi/2.0) * sin($dphi/2.0) + cos($phi1) * cos($phi2) * sin($dlambda/2.0) * sin($dlambda/2.0);
		$c = 2.0 * atan2(sqrt($a), sqrt(1.0-$a));
		return $R * $c;
	}

	$datetime = new DateTime($_GET['date']);
	$date = $datetime->format('Y-m-d');
	$datetime->modify('+1 day');
	$nextday = $datetime->format('Y-m-d');

	$database = new mysqli("localhost", "meetjestad_usr", "ijsvrij", "meetjestad_db");

	$Toff = array();
	$ToffN = array();
	if (isset($_GET['calibrate'])) {
		// get data from mysql database
		$result = $database->query("SELECT * FROM slam_measurement WHERE timestamp >= '".$database->real_escape_string($date)."' AND timestamp < '".$database->real_escape_string($nextday)."'");
		$list = array();
		$i = 0;
		while($row = $result->fetch_array(MYSQLI_ASSOC)) {
			$list[$i] = array(
				"sensor" => $row["station_id"],
				"pos" => array("lon" => $row["longitude"], "lat" =>$row["latitude"]),
				"time" => strtotime($row["timestamp"]),
				"temp" => $row["temperature"],
				"cluster" => array()
			);
			$i++;
		}

		// find clusters of measurements close in space and time
		for ($i=0,$m=count($list); $i<$m-1; $i++) for ($j=$i+1; $j<$m; $j++) {
			if ($list[$i]["sensor"] != $list[$j]["sensor"]) {
				if (distance($list[$i]["pos"], $list[$j]["pos"])<25.0 && abs($list[$i]["time"] - $list[$j]["time"]) < 10*60) {
					$list[$i]["cluster"][] = $j;
					$list[$j]["cluster"][] = $i;
				}
			}
		}

		// use clusters of 3 or more to calculate an averaged temperature offset per sensor
		foreach($list as $calibrate) if (count($calibrate["cluster"]) > 2) {
			// calculate average temperature for cluster
			$Tavg = $calibrate["temp"];
			foreach ($calibrate["cluster"] as $id) $Tavg+= $list[$id]["temp"];
			$Tavg/= count($calibrate["cluster"]) + 1;

			// store temperature offset per sensor
			if (!isset($ToffN[$calibrate["sensor"]])) {
				$ToffN[$calibrate["sensor"]] = 0;
				$Toff[$calibrate["sensor"]] = 0;
			}
			$Toff[$calibrate["sensor"]] += $calibrate["temp"] - $Tavg;
			$ToffN[$calibrate["sensor"]]++;

			foreach ($calibrate["cluster"] as $id) {
				$data = $list[$id];

				if (!isset($ToffN[$data["sensor"]])) {
					$ToffN[$data["sensor"]] = 0;
					$Toff[$data["sensor"]] = 0;
				}
				$Toff[$data["sensor"]] += $data["temp"] - $Tavg;
				$ToffN[$data["sensor"]]++;
			}
		}
		foreach($Toff as $id => $val) $Toff[$id]/= $ToffN[$id];
	}

	$result = $database->query("SELECT * FROM slam_measurement WHERE timestamp >= '".$database->real_escape_string($date)."' AND timestamp < '".$database->real_escape_string($nextday)."'");
	$json = '[';
	while($row = $result->fetch_array(MYSQLI_ASSOC)) {
		if ($json != "[") $json .= ",";
		$json.= '{"type":"Feature",';
		$json.= '"properties":{';
		$json.= '"temperature":"'.round($row["temperature"]+(isset($Toff[$row["station_id"]])?$Toff[$row["station_id"]]:0),2).'",';
		$json.= '"humidity":"'.$row["humidity"].'"},';
		$json.= '"geometry":{';
		$json.= '"type":"Point",';
		$json.= '"coordinates":['.$row["longitude"].','.$row["latitude"].']}}';
	}
	$json.= ']';
	$json = '{"type":"FeatureCollection","features":'.$json.'}';

	// output data
	header("Access-Control-Allow-Origin: *");
	header("Content-Type: application/json; charset=UTF-8");
	echo($json);
?>
