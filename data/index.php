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

	$limit = isset($_GET['limit']) ? $_GET['limit'] : false;

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
					if ($rows>1) $output.= ",\n";
					$output.= '{';
//					for($i=0;$i<$cols;$i++) $output.= ($i?",":"").'"'.$fieldNames[$i].'":"'.$data[$i].'"';
					for($i=0;$i<$cols;$i++) {
						if ($data[$i]) {
							if ($i) $output.= ",";
							$output.= '"'.$fieldNames[$i].'":';
							if ($fieldNames[$i]=='timestamp') $output.= '"'.$data[$i].'"';
							else if ($fieldNames[$i]=='extra') $output.= '['.$data[$i].']';
							else $output.= $data[$i];
						}
					}
					$output.= '}';
				}
				break;
			case 'csv':
				for($i=0;$i<$cols;$i++) {
					$output.= ($i?"\t":"");
					// It seems that the R CSV import does not like unlabeled fields, so just output
					// a bunch of extra labels rather than just one. This is a bit of a hack, but
					// especially on big exports it would take a lot of extra time to figure out the
					// actual number of extra fields used...
					if ($rows == 0 && $data[$i] == 'extra') $output .= "extra1\textra2\textra3\textra4\textra5\textra6\textra7\textra8\textra9";
					else if ($fieldNames[$i]=='extra') $output.= str_replace(',', "\t", $data[$i]);
					else $output.= $data[$i];
				}
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
				$ids_int_array = array_map('intval', explode(',', $ids));
				if ($ids) $WHERE.= ($WHERE?" AND ":" WHERE ")."station_id IN (" . implode(',', $ids_int_array) . ")";
				$SORT = " ORDER BY timestamp ".($limit ? "DESC" : "ASC");
				$LIMIT = $limit ? " LIMIT ".$limit : "";
				$query = "SELECT * FROM sensors_measurement".$WHERE.$SORT.$LIMIT;
				$results = $database->query($query, MYSQLI_USE_RESULT) or die(mysqli_error($database)); ;

				echoTableRow(array("id", "timestamp", "firmware_version", "longitude", "latitude", "temperature", "humidity", "lux", "supply", "battery", "pm2.5", "pm10", "firmware_version", "extra"));

				while(($result = $results->fetch_array(MYSQLI_ASSOC)) != false) {
					// No valid position is encoded in the
					// database as 0,0, but readers of the
					// data (such as QGIS) take these as
					// literal coordinates.
					if ($result['latitude'] == 0)
						$result['latitude'] = '';
					if ($result['longitude'] == 0)
						$result['longitude'] = '';
					echoTableRow(array($result["station_id"], $result["timestamp"], $result["firmware_version"], $result["longitude"], $result["latitude"], $result["temperature"], $result["humidity"], $result["lux"], $result["supply"], $result["battery"], $result["pm2_5"], $result["pm10"], $result["firmware_version"], $result["extra"]));
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

				$query = "SELECT obs.waarneming_id,obs.datum,obs.locatie,obs.omschrijving,
					         flora.naam_nl,flora.naam_la,flora.waarnemingen
					  FROM flora_observaties AS obs JOIN flora ON (flora.id = soort_id)".$WHERE;
				$results = $database->query($query, MYSQLI_USE_RESULT);

				echoTableRow(array("datum", "longitude", "latitude", "soort_nl", "soort_la", "waarneming", "notitie"));

				while($result = $results->fetch_array(MYSQLI_ASSOC)) {
					$waarnemingen = json_decode($result["waarnemingen"]);
					$omschrijving = filter_var(str_replace(array("\n", "\r", "\t")," ",$result['omschrijving']), FILTER_SANITIZE_STRING);
					$locatie = explode(',', $result["locatie"]);

					echoTableRow(array($result["datum"], $locatie[0], $locatie[1], $result["naam_nl"], $result["naam_la"], $waarnemingen[$result["waarneming_id"]], $omschrijving));
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
				width: 100%;
			}
			#mainForm {
				width: 360px;
			}
			#manual {
				display: inline-block;
				float: left;
			}
			.code {
				font-family: monospace;
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
				width: 100%;
			}
			@font-face {
				font-family: Dosis;
				src: url('../css/fonts/Dosis-Regular.otf');
			}
			@font-face {
				font-family: Dosis;
				src: url('../css/fonts/Dosis-Bold.otf');
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
<?php if (!array_key_exists('nohome', $_GET)) { ?>
		<a id="homelink" class="menuDefault" href="/">&lt;HOME</a>
<?php } ?>
		<div id="mainForm">
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
				<fieldset>
					<legend>Map</legend>
					<input type="submit" name="cmd" value="show heatmap"/>
				</fieldset>
			</form>
		</div>
		<div id="manual">
<h3>Direct query</h3><br/>
You can query the database directly using the following query (GET request):<br/>
<br/>
<div class="code">https://meetjestad.net/data/?type=[sensors|flora|stories]&amp;format=[csv|json]&amp;limit=XXX</div>
<br/>
The server will offer a file for download containing the Meet je Stad data.<br/>
<br/>
<b>Attributes</b><br/>
The <b>format</b> attribute selects how to pack the data.<br/>
<ol>
<li>Use <b>csv</b> if you want to have a tab separated file for easy import in a spreadsheet or statistics tool.</li>
<li>Using <b>json</b> is convenient if you want to further process the data in a programming language, e.g. Python, PHP or Javascript.</li>
<li><b>sql</b> can be used if you want to help develop the website and need a local dataset to query.</li>
</ol>
The <b>type</b> attribute defines which kind of data to download:<br/>
<ol>
<li><b>sensors</b> selects measurements done by the Meet je Stad sensor stations</li>
<li><b>flora</b> selects flora observations put in through the website</li>
<li><b>stories</b> selects the stories database. Please note that CSV export is not possible for stories.</li>
</ol>
The <b>limit</b> attribute defines the number of rows to return. Setting it to ALL will return all data. Be careful though not to download more data than needed to prevent excessive server load.<br/>
<br>
<b>Time series</b><br/>
Data can be narrowed down to a number of nodes and a specific time window by adding <b>begin</b> and <b>end</b> attributes to the query, denoted as yyyy-mm-dd,hh:mm. E.g. begin=2017-11-16,12:00 will return data beginning 16th of November 2017 at noon. Times are specified in the UTC timezone.<br/>
<br/>
<b>Sensor sets</b><br/>
When sensordata is queried the <b>ids</b> attribute can be used to get data of a single node or a set of nodes. A comma separates the node numbers. A minus symbol can be used to get a range. E.g. ids=1,3-5,8 will return data for the nodes 1, 3, 4, 5 and 8.<br/>
<br/>
<b>Examples</b><br/>
Get latest measurement for a single node as a json record<br/>
<div class="code">https://meetjestad.net/data/?type=sensors&amp;ids=24&amp;format=json&amp;limit=1</div>
<br/>
Download a two day time series for a single node as a csv table<br/>
<div class="code">https://meetjestad.net/data/?type=sensors&amp;ids=24&amp;begin=2017-11-16,00:00&amp;end=2017-11-18,00:00&amp;format=csv&amp;limit=100</div>
<br/>
Download a data to create a heat map for a certain set of sensors<br/>
<div class="code">https://meetjestad.net/data/?type=sensors&amp;ids=11,14,19,26,31,37,41,47&amp;begin=2017-11-16,12:00&amp;end=2017-11-16,12:15&amp;format=json&amp;limit=100</div>
		</div>
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
