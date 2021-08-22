<?php
	// === get url === //
	$path = substr($_SERVER["PHP_SELF"], 0, strpos($_SERVER["PHP_SELF"], basename(__FILE__)));
	// Use REDIRECT_URL when set, since when we are mod_rewritten, this
	// corresponds to the url that would have corresponded to the rewritten
	// request (which then matches the prefix of PHP_SELF).
	$uri = isset($_SERVER["REDIRECT_URL"]) ? $_SERVER["REDIRECT_URL"] : $_SERVER["REQUEST_URI"];
	$query = substr($uri, strlen($path));

	if (empty($query)) {
		// This should not be needed if our .htaccess redirect would
		// not match the empty url, but somehow this didn't work...
		header('Location: index.php');
		die;
	}

	include ("../connect.php");
	$database = Connection();

	include ("healthlib.php");
	$id = intval($query);

	// === get measurements === //
	$result = $database->query("SELECT * FROM sensors_measurement WHERE station_id='".$database->real_escape_string($id)."' ORDER BY timestamp DESC LIMIT 1");
	$row = $result->fetch_array(MYSQLI_ASSOC);
	if (!$row)
		die ("Node with id ".htmlspecialchars($id)." not found");

	$timestamp = DateTime::createFromFormat('Y-m-d H:i:s', $row["timestamp"], new DateTimeZone('UTC'));
	$timestamp->setTimeZone(new DateTImeZone('Europe/Amsterdam'));
	$timestamp = $timestamp->format('Y-m-d H:i:s');
	$lasttime = substr($timestamp, -8);
	$lastdate = date_format(date_create($timestamp), "d-m 'y");
	$offline = (time() - strtotime($timestamp))/60/60 > 1 ? true : false;

	$latitude = $row["latitude"];
	$longitude = $row["longitude"];

	$temperature = round($row["temperature"], 1);
	$humidity = round($row["humidity"], 1);
	$light = $row["lux"];

	$setlist = '';
	$sensorsets = json_decode(file_get_contents('../sensorsets.json'), true);
	foreach($sensorsets as $setid => $set) {
		$ids = preg_replace_callback('/(\d+)-(\d+)/', function($m) {
			return implode(',', range($m[1], $m[2]));
		}, $sensorsets[$setid]['ids']);
		if (in_array($id, explode(',', $ids)))
			$setlist.= '<option value="../maps/'.htmlspecialchars($setid).'">'.htmlspecialchars($set['description']).'</option>';
	}
?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
		<meta http-equiv="refresh" content="900" />
		<title>Meet je stad! Node <?=htmlspecialchars($id)?></title>
		<link rel="icon" href="../images/favicon.png" type="image/x-icon" />
		<style>
			body {
				font-family: Dosis;
				font-size: 12pt;
				padding: 5px;
				margin: 0px;
			}
			#flex {
				display: flex;
			}
			legend {
				font-weight: bold;
				margin-top: 0px;
				margin-bottom: 0px;
				text-align: center;
			}
			th {
				text-align:left;
			}
			.pane {
				flex: 1;
				width: 30%;
				float: left;
				background-color: #f8f8f8;
				margin: 5px;
				padding: 10px;
				border: solid 1px #888;
				max-width: 600px;
			}
			@media all and (max-width: 860px)  {
				#flex {
					display: block;
				}
				.pane {
					clear: left;
					width: 90%;
				}
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
		<script>
			function drawSeries() {
				var selection = document.getElementById("dataSelector").elements;
				var time = selection["time"].value;
				var series = '';
				if (selection["temperature"].checked) series+= (series?',':'') + 'temperature';
				if (selection["humidity"].checked) series+= (series?',':'') + 'humidity';
<?php if ($light !== null) {?>
				if (selection["lux"].checked) series+= (series?',':'') + 'lux';
<?php } ?>
				document.getElementById('series').src = 'series.php?id=<?=htmlspecialchars(rawurlencode($id))?>&time='+time+'&series='+series+'&width='+(document.getElementById('paneData').offsetWidth-120);
			}
		</script>
	</head>
	<body onload="drawSeries();">
		<div style="display:table; margin:0 auto;">
			<img style="text-align:left; vertical-align:top;" src="../images/logo_node.png"/>
			<div style="display:inline-block; padding:8px; font-size:22pt; font-weight:bold;"><?=$query?></div>
		</div>
		<div id="flex">
			<fieldset class="pane" id="paneData">
				<legend>data</legend>
				<form id="dataSelector">
					<table>
						<tr><th colspan="4">Latest&nbsp;measurements</th>
<?php if ($offline) {?>
						<tr><td colspan="4"><i>Warning: node is offline!</i></td>
<?php } ?>
						<tr><td>Temperature</td><td>T</td><td style="text-align:right;"><?=$temperature?></td><td>⁰C</td></tr>
						<tr><td>Relative Humidity</td><td>Φ</td><td style="text-align:right;"><?=$humidity?></td><td>%</td></tr>
<?php if ($light !== null) {?>
						<tr><td>Illuminance</td><td>E</td><td style="text-align:right;"><?=$light?></td><td>lx</td></tr>
<?php } ?>
					</table>
					<b>Time series</b><br/>
					<img id="series" style="float:left;" src=""/>
					<div style="display:inline-block; float:left;">
						<input type="checkbox" name="temperature" checked="checked" onclick="drawSeries();"/><span style="color:#fb6127;">T</span><br/>
						<input type="checkbox" name="humidity" onclick="drawSeries();"/><span style="color:#5677fc;">Φ</span><br/>
<?php if ($light !== null) {?>
						<input type="checkbox" name="lux" onclick="drawSeries();"/><span style="color:#8f8fbf;">E</span>
<?php } ?>
					</div>
					<input style="margin-left:40px;" type="radio" name="time" value="1" checked="checked" onclick="drawSeries();"/> 1D
					<input type="radio" name="time" value="7" onclick="drawSeries();"/> 1W
					<input type="radio" name="time" value="30" onclick="drawSeries();"/> 1M
					<input type="radio" name="time" value="365" onclick="drawSeries();"/> 1Y
					<input type="radio" name="time" value="0" onclick="drawSeries();"/> All
				</form>
				<br/>
<!--
				download <input type="button" value="CSV"/><input type="button" value="JSON"/><br/>
-->
			</fieldset>
			<fieldset class="pane">
				<legend>node</legend>
				<iframe width="180" height="180" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://www.openstreetmap.org/export/embed.html?bbox=<?=$longitude-0.01?>%2C<?=$latitude-0.005?>%2C<?=$longitude+0.01?>%2C<?=$latitude+0.005?>&amp;layer=mapnik&amp;marker=<?=$latitude?>%2C<?=$longitude?>" style="border: 1px solid black; margin-right:5px; float:left;"></iframe>
				<table>
					<tr><th colspan="2">Last&nbsp;seen</th>
					<tr><td>Date</td><td><?=$lastdate?></td></tr>
					<tr><td>Time</td><td><?=$lasttime?></td></tr>
					<tr><td>Lon</td><td><?=$longitude?>E</td></tr>
					<tr><td>Lat</td><td><?=$latitude?>N</td></tr>
				</table>
				<p style="clear:left;"></p>
<!--
				<b>Description</b>
				<table style="clear:left;">
					<tr><td>Height</td><td></td></tr>
					<tr><td>Orientation</td><td></td></tr>
					<tr><td>Rig</td><td></td></tr>
					<tr><td>Sun/shadow</td><td></td></tr>
				</table>
				picture<br/>
-->

<?php if ($setlist) {?>
					Part of set(s) <select id="setlist"><?=$setlist?></select> <input type="button" value="Go" onclick="window.location=document.getElementById('setlist').value;"/>
<?php } ?>
			</fieldset>
			<fieldset class="pane">
				<legend>health</legend>
<?php
	health($id, 'table');
?>
			</fieldset>
		</div>
	</body>
</html>
