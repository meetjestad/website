<?php
	function corr($a, $b) {
		$sum_ab = 0;
		$sum_a = 0;
		$sum_b = 0;
		$sum_a_sqr = 0;
		$sum_b_sqr = 0;
		$n = min(array(count($a), count($b)));
		for ($i = 0; $i < $n; $i++) {
			if (!isset($a[$i]) || !isset($b[$i])) { continue; }
			$sum_ab += $a[$i] * $b[$i];
			$sum_a += $a[$i];
			$sum_b += $b[$i];
			$sum_a_sqr += pow($a[$i], 2);
			$sum_b_sqr += pow($b[$i], 2);
		}
		return ($sum_ab/$n - $sum_a/$n * $sum_b/$n) / (sqrt($sum_a_sqr/$n - pow($sum_a/$n, 2)) * sqrt($sum_b_sqr/$n - pow($sum_b/$n, 2)));
	}

function health($id, $layout) {
	global $database;

	// === get measurements === //
	$latestResult = $database->query("SELECT * FROM sensors_station WHERE id = ".$database->real_escape_string($id));
	if($latestResult->num_rows === 0) {
		switch($layout) {
			case 'table':
				echo 'no data recorded';
				break;
			case 'row':
				echo '<th>'.htmlspecialchars($id).'</th><td colspan="5">no data recorded</td>';
				break;
			case 'json':
				echo json_encode(array('status' => 'no data recorded'));
				break;
		}
		return;
	}
	$latestRow = $latestResult->fetch_array(MYSQLI_ASSOC);
	$cacheResult = $database->query("SELECT * FROM sensors_health WHERE id = $id");
	$cacheRow = $cacheResult->fetch_array(MYSQLI_ASSOC);

	if($cacheResult->num_rows === 0 || $latestRow["last_timestamp"] > $cacheRow["timestamp"]) {
		// do assessment and write to health cache
		$result = $database->query("SELECT msr.*, msg.message FROM sensors_measurement AS msr LEFT JOIN sensors_message AS msg ON (msg.id = msr.message_id) WHERE msr.station_id = ".$database->real_escape_string($id)." ORDER BY msr.timestamp DESC LIMIT 100");

		$rows = 0;
		$lastfcnt = 0;
		$fcnt1 = 0;
		$fcnt2 = 0;
		while($row = $result->fetch_array(MYSQLI_ASSOC)) {
			if ($rows==0) { // most recent message
				$timestamp = $row["timestamp"];
				$latitude = $row["latitude"];
				$longitude = $row["longitude"];
				$supply = $row["supply"];
			}
			$message = json_decode($row['message'], true);
			$fcnt = $message['counter'];
			if ($rows==0) $fcnt1 = $fcnt;
			elseif ($fcnt==0 || $fcnt > $lastfcnt) break;
			$rows++;
			$lastfcnt = $fcnt;
			$hum[] = $row['humidity'];
			$tmp[] = $row['temperature'];
		}
		// Assess radio reception
		$fcnt2 = $lastfcnt;
		if ($fcnt1>0 && $fcnt2>0 && $fcnt1!=$fcnt2) {
			$radiosuccess = $rows/($fcnt1 - $fcnt2 + 1);
		}
		else $radiosuccess = '';

		// Assess humidity sensor health
		$countinvalidhum = 0;
		$countinvaliddhum = 0;
		for($i=0; $i<count($hum); $i++) {
			if ($hum[$i]<10.0 || $hum[$i]>100.0) $countinvalidhum++;
			if ($i>0) if (abs($hum[$i]-$hum[$i-1])==0 || abs($hum[$i]-$hum[$i-1])>50.0) $countinvaliddhum++;
		}
		$percinvalidhum = $countinvalidhum/count($hum);
		$percinvaliddhum = $countinvaliddhum/(count($hum)-1);

		$Rtmphum = round(corr($tmp, $hum), 2);

		$humhealth = ((1.0 - $percinvalidhum) + (1.0 - $percinvaliddhum) + 0.5*(1.0-$Rtmphum))/3.0;

		$result = $database->query("SELECT * FROM sensors_measurement WHERE station_id = ".$database->real_escape_string($id)." ORDER BY timestamp DESC LIMIT 1000");
		$rows = 0;
		$counthasgps = 0;
		while($row = $result->fetch_array(MYSQLI_ASSOC)) {
			if ($row["longitude"]!=0 && $row["latitude"]!=0) $counthasgps++;
			$rows++;
		}
		$perchasgps = $counthasgps/$rows;
		$gpscount = $rows;

		if ($cacheResult->num_rows === 0) {
			$q = $database->prepare("INSERT INTO sensors_health SET id = ?, timestamp = ?, humhealth = ?, perchasgps = ?, radiosuccess = ?, supply = ?, longitude = ?, latitude = ?");
			$q->bind_param('isdddddd', $id, $timestamp, $humhealth, $perchasgps, $radiosuccess, $supply, $longitude, $latitude);
			$q->execute();
		}
		else {
			$q = $database->prepare("UPDATE sensors_health SET timestamp = ?, humhealth = ?, perchasgps = ?, radiosuccess = ?, supply = ?, longitude = ?, latitude = ?");
			$q->bind_param('sdddddd', $timestamp, $humhealth, $perchasgps, $radiosuccess, $supply, $longitude, $latitude);
			$q->execute();
		}
	}
	else {
		// use data from health cache
		$timestamp = $cacheRow["timestamp"];
		$humhealth = $cacheRow["humhealth"];
		$perchasgps = $cacheRow["perchasgps"];
		$radiosuccess = $cacheRow["radiosuccess"];
		$supply = $cacheRow["supply"];
		$longitude = $cacheRow["longitude"];
		$latitude = $cacheRow["latitude"];
		// TODO: Put this value in the cache
		$fcnt1 = 0;
		$fcnt2 = 0;
		$gpscount = 0;
		$percinvalidhum = 0;
		$percinvaliddhum = 0;
		$Rtmphum = 0;
	}

	$lastseen = DateTime::createFromFormat('Y-m-d H:i:s', $timestamp, new DateTimeZone('UTC'));
	$lastseen->setTimeZone(new DateTImeZone('Europe/Amsterdam'));
	$lastseen = $lastseen->format('Y-m-d H:i:s');
	if ($latitude == '0.0' && $longitude == '0.0') $position = 'No position';
	else $position = '<a href="http://www.openstreetmap.org/?mlat='.$latitude.'&amp;mlon='.$longitude.'" target="_blank">'.$latitude.' / '.$longitude.'</a>';

	// Assess up/downtime
	$idletime = (time() - strtotime($lastseen))/60/60;

	if ($idletime > 24) {
		$idletime = round($idletime/24).' day'.($idletime > 24 ? 's' : '');
		$alivelight = 'red';
	}
	elseif ($idletime > 1) {
		$idletime = round($idletime).' hour'.($idletime > 1 ? 's' : '');
		$alivelight = 'orange';
	}
	else {
		$idletime = '';
		$alivelight = 'lime';
	}

	if ($humhealth>=0.75) $humiditylight = 'lime';
	elseif ($humhealth>=0.5) $humiditylight = 'orange';
	else $humiditylight = 'red';

	if ($radiosuccess) {
		if ($radiosuccess>=0.9) $radiolight = 'lime';
		elseif ($radiosuccess>=0.5) $radiolight = 'orange';
		else $radiolight = 'red';
	}

	if ($perchasgps>0.9) $gpslight = 'lime';
	elseif ($perchasgps>0.5) $gpslight = 'orange';
	else $gpslight = 'red';

	if ($supply>=3.3) $supplylight = 'lime';
	elseif ($supply>=3.27) $supplylight = 'orange';
	else $supplylight = 'red';

	switch($layout) {
		case 'table':
			echo '<table>';
			echo '<tr><th colspan="3">Alive</th></tr>';
			echo '<tr><td style="color:'.htmlspecialchars($alivelight).';">●</td><td>'.($idletime?'Offline since':'Online').'</td><td>'.($idletime?htmlspecialchars($idletime):'Seen last hour').'</td></tr>';
			echo '<tr><th colspan="3">Battery</th></tr>';
			echo '<tr><td style="color:'.htmlspecialchars($supplylight).';">●</td><td>Voltage</td><td>'.htmlspecialchars($supply).'V</td></tr>';
			echo '<tr><th colspan="3">Radio</th></tr>';
			if ($radiosuccess) echo '<tr><td style="color:'.htmlspecialchars($radiolight).';">●</td><td>Delivery</td><td>'.htmlspecialchars(round(100.0*$radiosuccess)).' % of last '.htmlspecialchars($fcnt1 - $fcnt2 + 1).' packets</td></tr>';
			echo '<tr><th colspan="3">Sensors</th></tr>';
			echo '<tr><td style="color:'.htmlspecialchars($gpslight).';">●</td><td>GPS</td><td>'.htmlspecialchars(round(100.0*$perchasgps)).' % present in last '.htmlspecialchars($gpscount).' packets</td></tr>';
			echo '<tr><td style="color:'.htmlspecialchars($humiditylight).';">●</td><td>Humidity</td><td>'.htmlspecialchars(round(100.0*$percinvalidhum)).' % invalid Φ (&lt;10% or &gt;100%)</td></tr>';
			echo '<tr><td></td><td></td><td>'.round(100.0*htmlspecialchars($percinvaliddhum)).' % invalid ΔΦ (=0 or &gt;50%)</td></tr>';
			echo '<tr><td></td><td></td><td>R <sub>TΦ</sub> = '.htmlspecialchars($Rtmphum).'</td></tr>';
			echo '</table>';
		break;
		case 'row':
			//~ echo '<th><a href="node/'.$id.'" target="_blank">'.$id.'</a></th><td><span style="color:'.$alivelight.';">●</span> '.($idletime?$idletime.' ago':'online').'</td><td><span style="color:'.$supplylight.';">●</span> '.$supply.'V</td><td><span style="color:'.$gpslight.';">●</span>'.round(100.0*$perchasgps).' % up</td><td><span style="color:'.$humiditylight.';">●</span>'.($humhealth>=0.75?'ok':($humhealth>=0.5?'moderate':'bad')).'</td><td>'.$position.'</td>';
			echo '<th><a href="node/'.htmlspecialchars($id).'" target="_blank">'.htmlspecialchars($id).'</a></th>';
			echo '<td><span style="color:'.htmlspecialchars($alivelight).';">●</span>'.($idletime?htmlspecialchars($idletime).' ago':'online').'</td>';
			echo '<td><span style="color:'.htmlspecialchars($supplylight).';">●</span> '.htmlspecialchars($supply).'V</td>';
			echo '<td><span style="color:'.htmlspecialchars($gpslight).';">●</span>'.htmlspecialchars(round(100.0*$perchasgps)).' % up</td>';
			echo '<td><span style="color:'.htmlspecialchars($humiditylight).';">●</span>'.($humhealth>=0.75?'ok':($humhealth>=0.5?'moderate':'bad')).'</td>';
		break;
		case 'json':
			$node = array(
				"id"=>$id,
				"idletime"=>$idletime,
				"alivelight"=>$alivelight,
				"supply"=>$supply,
				"supplylight"=>$supplylight,
				"perchasgps"=>$perchasgps,
				"gpslight"=>$gpslight,
				"humhealth"=>$humhealth,
				"humiditylight"=>$humiditylight,
				"position"=>array("lon"=>$longitude, "lat"=>$latitude)
			);
			echo json_encode($node);
		break;
	}
}
