<?php
	error_reporting(E_ALL);
	
	$type = isset($_GET['type']) ? $_GET['type'] : false;
	$comma = isset($_GET['comma']) ? true : false;
	
	if (isset($_GET['start']) && $_GET['start']) $start = urldecode($_GET['start']);
	else if (isset($_GET['begin']) && $_GET['begin']) $start = urldecode($_GET['begin']);
	else $start = false;
	$end = (isset($_GET['end']) && $_GET['end']) ? urldecode($_GET['end']) : false;
	
	if(isset($_GET['ids']) && $_GET['ids']) {
		$ids = preg_replace_callback('/(\d+)-(\d+)/', function($m) {
			return implode(',', range($m[1], $m[2]));
		}, urldecode($_GET['ids']));
	}
	else $ids = false;
	
	$format = isset($_GET['format']) ? $_GET['format'] : '';
	if (isset($_GET['cmd'])) switch ($_GET['cmd']) {
		case 'show heatmap':
			header('Location: http://meetjestad.net/index_oud.php?layer=hittekaart&ids='.urlencode($ids).'&start='.urlencode($start).'&end='.urlencode($end));
			exit;
		case 'download CSV':
			$format = 'csv';
			break;
		case 'download JSON':
			$format = 'json';
			break;
	}
	
	function echoTableRow($data) {
		global $format;
		global $comma;
		static $rows = 0;
		static $cols = 0;
		static $fieldNames = array();
		
		if ($rows == 0) {
			$fieldNames = $data;
			$cols = count($data);
		}
		
		$output = "";
		switch($format) {
			case 'json':
				if ($rows>0) {
					if ($rows>1) $output.= ",";
					$output.= '{';
//					for($i=0;$i<$cols;$i++) $output.= ($i?",":"").'"'.$fieldNames[$i].'":"'.$data[$i].'"';
					for($i=0;$i<$cols;$i++) {
						if ($data[$i]) {
							if ($i) $output.= ",";
							$output.= '"'.$fieldNames[$i].'":';
							if ($fieldNames[$i]=='timestamp') $output.= '"'.$data[$i].'"';
							else $output.= $data[$i];
						}
					}
					$output.= '}';
				}
				break;
			case 'csv':
				for($i=0;$i<$cols;$i++) $output.= ($i?"\t":"").$data[$i];
				$output.= "\n";
				break;
		}
		$rows++;
		if ($comma) {
			// https://stackoverflow.com/questions/2293780/how-to-detect-a-floating-point-number-using-a-regular-expression#2293793
			$output = preg_replace_callback(
				'/(([1-9][0-9]*\.?[0-9]*)|(\.[0-9]+))([Ee][+-]?[0-9]+)?/',
				function ($matches) {
					return $number = str_replace('.', ',', $matches[0]);
				},
				$output
			);
		}
		echo $output;
	}
	
	// query: ?type=sensors|observations|stories&start=timestamp&end=timestamp&ids=1,2-4
	if ($type) {
		set_time_limit(0);                   // ignore php timeout
		ob_start();
		
		// output headers
		switch($format) {
			case 'csv':
				header('Content-Type: text/csv; charset=UTF-8');
				break;
			case 'json':
				header('Content-Type: application/json; charset=UTF-8');
				header("Access-Control-Allow-Origin: *");
				break;
			default:
				echo("Unsupported format");
				exit;
		}
		if (isset($_GET['download']) && $_GET['download'])
			header('Content-Disposition: attachment; filename="MjS-data.'.$format.'"');
		
		if ($type!='stories' && $format=='json') echo '[';
		
		switch($type) {
			case 'sensors':
				include ("../connect.php");
				$database = Connection();
				$WHERE = "";
				if ($start) $WHERE.= " WHERE timestamp >= '" . $database->real_escape_string($start) . "'";
				if ($end) $WHERE.= ($WHERE?" AND ":" WHERE ")."timestamp <= '" . $database->real_escape_string($end) . "'";
				if ($ids) $WHERE.= ($WHERE?" AND ":" WHERE ")."station_id IN (" . $database->real_escape_string($ids) . ")";
				$SORT = ' ORDER BY timestamp ASC';
				$query = "SELECT * FROM sensors_measurement".$WHERE.$SORT;
				$results = $database->query($query, MYSQLI_USE_RESULT) or die(mysqli_error($database)); ;
				
				echoTableRow(array("id", "timestamp", "longitude", "latitude", "temperature", "humidity", "lux", "supply", "pm2.5", "pm10"));

				while(($result = $results->fetch_array(MYSQLI_ASSOC)) != false) {
					// No valid position is encoded in the
					// database as 0,0, but readers of the
					// data (such as QGIS) take these as
					// literal coordinates.
					if ($result['latitude'] == 0)
						$result['latitude'] = '';
					if ($result['longitude'] == 0)
						$result['longitude'] = '';
					echoTableRow(array($result["station_id"], $result["timestamp"], $result["longitude"], $result["latitude"], $result["temperature"], $result["humidity"], $result["lux"], $result["supply"], $result["pm2_5"], $result["pm10"]));
					ob_flush();
					flush();
				}
				break;
			case 'observations':
				include ("../connect.php");
				$database = Connection();
				$WHERE = "";
				if ($start) $WHERE.= " WHERE datum >= '" . $database->real_escape_string($start) . "'";
				if ($end) $WHERE.= ($WHERE?" AND ":" WHERE ")."datum <= '" . $database->real_escape_string($end) . "'";
				
				$query = "SELECT soort_id,waarneming_id,datum,locatie,omschrijving FROM flora_observaties".$WHERE;
				$results = $database->query($query, MYSQLI_USE_RESULT);
				
				echoTableRow(array("datum", "longitude", "latitude", "soort_nl", "soort_la", "waarneming", "notitie"));
				
				while($result = $results->fetch_array(MYSQLI_ASSOC)) {
					$flora = $database->query("SELECT naam_nl,naam_la,afbeelding,omschrijving,waarnemingen FROM flora WHERE id=".$database->real_escape_string($result["soort_id"]));
					$species = $flora->fetch_array(MYSQLI_ASSOC);
					$waarnemingen = json_decode($species["waarnemingen"]);
					$omschrijving = filter_var(str_replace(array("\n", "\r", "\t")," ",$result['omschrijving']), FILTER_SANITIZE_STRING);
					$locatie = explode(',', $result["locatie"]);
					
					echoTableRow(array($result["datum"], $locatie[0], $locatie[1], $species["naam_nl"], $species["naam_la"], $waarnemingen[$result["waarneming_id"]], $omschrijving));
				}
				break;
			case 'stories':
				require_once 'jsonrpcphp/src/org/jsonrpcphp/JsonRPCClient.php';
				// This defines LS_BASEURL, LS_USER and LS_PASSWORD
				require_once('uvertelt.php');
				$survey_id = 993944;
				$myJSONRPCClient = new \org\jsonrpcphp\JsonRPCClient( LS_BASEURL.'/admin/remotecontrol' );
				$sessionKey= $myJSONRPCClient->get_session_key( LS_USER, LS_PASSWORD );
				$responses = $myJSONRPCClient->export_responses(
					$sessionKey,
					$survey_id,
					'json'
				);
				echo base64_decode($responses);
				break;
		}
		
		if ($type!='stories' && $format=='json') echo ']';
		exit;
	}
	
	if (file_exists('../sensorsets.json')) $sensorsets = json_decode(file_get_contents('../sensorsets.json'), true);
	else $sensorsets = array();
?>
<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<title>Meet je stad dataloket</title>
		<link rel="stylesheet" href="../css/pikaday.css">
		<link rel="stylesheet" href="../css/meetjestad.css">
		<style>
			body {
				font-family: Dosis;
				font-size: 12pt;
				padding: 5px;
				margin: 0px;
				box-sizing: border-box;
				text-align: justify;
			}
			h3 {
				margin-bottom:0px;
			}
			fieldset {
				width: 360px;
			}
			::-webkit-input-placeholder {
				font-style: italic;
			}
			:-moz-placeholder {
				font-style: italic;  
			}
			::-moz-placeholder {
				font-style: italic;  
			}
			:-ms-input-placeholder {  
				font-style: italic; 
			}
			#datalogo {
				width: 40%;
			}
			@font-face {
				font-family: Dosis;
				src: url('../style/fonts/Dosis-Regular.otf');
			}
			@font-face {
				font-family: Dosis;
				src: url('../style/fonts/Dosis-Bold.otf');
				font-weight: bold;
			}
		</style>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/date-fns/1.28.5/date_fns.min.js"></script>
		<script src="js/pikaday.js"></script>
		<script>
			function selectSet(id) {
				switch(id) {
<?
	foreach($sensorsets as $id => $set) echo 'case '.json_encode($id).': document.getElementById(\'idlist\').value = '.json_encode($set['ids']).'; break;'."\r\n"; 
?>
				}
			}
		</script>
	</head>
	<body>
		<form method="get" target="_blank">
			<!-- set this value to 0 to suppress the
			content-disposition header and allow the browser to
			show the file contents rather than prompting a "save
			as..." dialog. -->
			<input type="hidden" name="download" value="1">
			<img id="datalogo" src="../images/logo_dataloket.png"><br/>
			All data from Meet je stad is made available as <a href="https://opendatacommons.org/licenses/odbl/summary/">open data</a>.<br/> Using the data or interpretations thereof is at ones own risk.<br/> For more information, read the licenses for the <a href="http://opendatacommons.org/licenses/odbl/1.0/">database</a> and its <a href="http://opendatacommons.org/licenses/dbcl/1.0/">content</a>.
			<h3>1. Choose data type</h3>
			<input type="radio" name="type" value="sensors" onclick="document.getElementById('sensors').style.display='block'; document.getElementById('xml').style.display='block';" checked="checked"/> Measurements
			<input type="radio" name="type" value="observations" onclick="document.getElementById('sensors').style.display='none'; document.getElementById('xml').style.display='block';"/> Observations
			<input type="radio" name="type" value="stories" onclick="document.getElementById('sensors').style.display='none'; document.getElementById('xml').style.display='none'; document.getElementById('json').selected='selected';"/> Stories
			
<!--
			<h3>2. Kies experiment</h3>
			IF Sensoren:<br/>
			-klimaat<br/>
			-cityslam op datum ...<br/>
			-NO2<br/>
			-fijnstof<br/>
			IF Observaties:<br/>
			-flora<br/>
			-foto's
			
			<h3>3. Maak selectie (optioneel)</h3>
-->
			<h3>2. Apply filter (optional)</h3>
			<fieldset>
				<legend>Period</legend>
				from <input type="text" name="start" id="start" placeholder="2016-12-31,12:00" style="width:100px;"/> to <input type="text" name="end" id="end" placeholder="2017-01-01,12:00" style="width:100px;"/>
			</fieldset>
			<fieldset id="sensors">
				<legend>Sensor(s)</legend>
				enter one or more ids...
				<select onchange="selectSet(this.value);">
					<option selected="selected" disabled="disabled">...or choose a dataset</option>
<?
	foreach($sensorsets as $id => $set) echo '<option value="'.htmlspecialchars($id).'">'.htmlspecialchars($set['description']).'</option>';
?>
				</select><br/>
				<input type="text" name="ids" id="idlist" placeholder="2,5,19-23" style="width:330px; margin-top:5px;"/>
			</fieldset>
			
			<h3>3. Download data or generate map</h3>
			<fieldset id="data">
				<legend>Data</legend>
				<input type="checkbox" name="comma"/>Use comma as decimal separator<br/>
				<input type="submit" name="cmd" value="download CSV"/>
				<input type="submit" name="cmd" value="download JSON"/>
			</fieldset>
			<fieldset id="map">
				<legend>Map</legend>
				<input type="submit" name="cmd" value="show heatmap"/>
			</fieldset>
		</form>
		<script>
		var startDate,
			endDate,
			updateStartDate = function() {
				startPicker.setStartRange(startDate);
				endPicker.setStartRange(startDate);
				endPicker.setMinDate(startDate);
			},
			updateEndDate = function() {
				startPicker.setEndRange(endDate);
				startPicker.setMaxDate(endDate);
				endPicker.setEndRange(endDate);
			},
			startPicker = new Pikaday({
				field: document.getElementById('start'),
				format: 'YYYY-M-D,HH:mm',
				use24hour: true,
				showSeconds: false,
				incrementHourBy: 1,
				incrementMinuteBy: 5,
				i18n: {
					previousMonth : 'Vorige maand',
					nextMonth     : 'Volgende maand',
					months        : ['Januari','Februari','Maart','April','Mei','Juni','Juli','Augustus','September','Oktober','November','December'],
					weekdays      : ['Zondag','Maandag','Dinsdag','Woensdag','Donderdag','Vrijdag','Zaterdag'],
					weekdaysShort : ['Zo','Ma','Di','Wo','Do','Vr','Za']
				},
				toString: function(date, format) {
					return dateFns.format(date, format);
				},
				parse: function(dateString, format) {
					return dateFns.parse(dateString);
				},
				minDate: new Date(2015, 1, 1),
				maxDate: new Date(2020, 12, 31),
				onSelect: function() {
					startDate = this.getDate();
					updateStartDate();
				}
			}),
			endPicker = new Pikaday({
				field: document.getElementById('end'),
				format: 'YYYY-M-D,HH:mm',
				use24hour: true,
				showSeconds: false,
				incrementHourBy: 1,
				incrementMinuteBy: 5,
				i18n: {
					previousMonth : 'Vorige maand',
					nextMonth     : 'Volgende maand',
					months        : ['Januari','Februari','Maart','April','Mei','Juni','Juli','Augustus','September','Oktober','November','December'],
					weekdays      : ['Zondag','Maandag','Dinsdag','Woensdag','Donderdag','Vrijdag','Zaterdag'],
					weekdaysShort : ['Zo','Ma','Di','Wo','Do','Vr','Za']
				},
				toString: function(date, format) {
					return dateFns.format(date, format);
				},
				parse: function(dateString, format) {
					return dateFns.parse(dateString);
				},
				minDate: new Date(2015, 1, 1),
				maxDate: new Date(2020, 12, 31),
				onSelect: function() {
					endDate = this.getDate();
					updateEndDate();
				}
			}),
			_startDate = startPicker.getDate(),
			_endDate = endPicker.getDate();

			if (_startDate) {
				startDate = _startDate;
				updateStartDate();
			}

			if (_endDate) {
				endDate = _endDate;
				updateEndDate();
			}
		</script>
	</body>
</html>
