<?php
	// === get page === //
	$path = substr($_SERVER["PHP_SELF"], 0, strpos($_SERVER["PHP_SELF"], 'index.php'));
	// Use REDIRECT_URL when set, since when we are mod_rewritten, this
	// corresponds to the url that would have corresponded to the rewritten
	// request (which then matches the prefix of PHP_SELF).
	$uri = isset($_SERVER["REDIRECT_URL"]) ? $_SERVER["REDIRECT_URL"] : $_SERVER["REQUEST_URI"];
	$query = explode('/', substr(strtok($uri, '?'), strlen($path)));
?>
<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<meta name="viewport" content="initial-scale=1.0, user-scalable=no, width=device-width">
		<title>Meet je stad!</title>
<!--		<link rel="icon" href="favicon.ico" type="image/x-icon">-->

		<!-- Load OpenLayers and plugins: popup, layerswitcher -->
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/openlayers/4.6.5/ol.css" type="text/css">
		<script src="https://cdnjs.cloudflare.com/ajax/libs/openlayers/4.6.5/ol.js"></script>

		<script src="https://cdnjs.cloudflare.com/ajax/libs/proj4js/2.4.3/proj4.js" type="text/javascript"></script>
		<script src="https://epsg.io/28992-1753.js"></script>

		<link rel="stylesheet" href="https://rawgit.com/walkermatt/ol3-popup/master/src/ol3-popup.css" type="text/css">
		<script src="https://rawgit.com/walkermatt/ol3-popup/master/src/ol3-popup.js"></script>

		<link rel="stylesheet" href="https://cdn.jsdelivr.net/openlayers.layerswitcher/1.1.0/ol3-layerswitcher.css" type="text/css">
		<script src="https://cdn.jsdelivr.net/openlayers.layerswitcher/1.1.0/ol3-layerswitcher.js"></script>

		<!-- Load Meetjestad javascript and stylesheet -->
		<link rel="stylesheet" type="text/css" href="meetjestad.css" media="all" />
	</head>
	<body id="body">
		<!--[if lt IE 10]>
			<p class="browsehappy">You are using an <strong>outdated</strong> browser. Please <a href="https://browsehappy.com/">upgrade your browser</a> to improve your experience.</p>
		<![endif]-->
		<div id="map">
			<div id="popup" class="ol-popup">
				<a href="#" id="popup-closer" class="ol-popup-closer"></a>
				<div id="popup-content"></div>
			</div>
		</div>
<?php if (!array_key_exists('nologo', $_GET)) { ?>
		<div id="logo">
			<img src="../images/logo.png" width="100%"/>
		</div>
<?php } ?>
		<div id="menuIcon">
			<img src="../images/menu.png" onclick="toggleMenu();"/>
		</div>
		<div id="legend">
		</div>
		<script>
			"use strict";

			var data = null;
			var canvas;
			var context;
			var canvasOrigin;
			var delta;
			var scale;
			var width;
			var height;
			var bbox;
			var timer = null;
			var map;
			var autozoom = true;

			// code to rescale map and ahn overlay when zooming or changing window size
			function redisplay() {
				// clear canvas
				if (context) {
					var imgData = context.getImageData(0,0,width,height);
					for(var i=0;i<imgData.length;i++) imgData[i] = 0;
					context.putImageData(imgData,0,0);
					map.renderSync();
				}
			}

var repairDataLayer = new ol.layer.Vector({
	title: 'repair',
	source: new ol.source.Vector({
		url: '../data/repair_json.php',
		defaultProjection: 'EPSG:4326',
		projection: 'EPSG:28992',
		format: new ol.format.GeoJSON()
	}),
	style: function(feature, resolution) {
		var style = [new ol.style.Style({
			image: new ol.style.Circle({
				radius: 10,
				fill: new ol.style.Fill({color: feature.getProperties().alivelight}),
//				stroke: new ol.style.Stroke({color: feature.get('properties').humiditylight, lineDash: [.1, 5], width: 3})
			})
		})];
		if (feature.getProperties().idletime) return style;
	}
});

function dataPopups(evt) {
	// Hide existing popup and reset it's offset
	popup.hide();
	popup.setOffset([0, 0]);
	
	// Attempt to find a feature in one of the visible vector layers
	var feature = map.forEachFeatureAtPixel(evt.pixel, function(feature, layer) {
		return feature;
	});
	
//	feature = feature.get('features')[0];
	
	if (feature) {
		var coord = feature.getGeometry().getCoordinates();
		var props = feature.getProperties();
		var info;
		info = '<h2 style="margin-bottom:0px;">' + props.location + '</h2>';
		info+= '<table>';
		info+= '<tr><td>Idle time</td><td>' + props.idletime + '</td></tr>';
/*
		echo '<table>';
			echo '<tr><th colspan="3">Alive</th></tr>';
			echo '<tr><td style="color:'.htmlspecialchars($alivelight).';">●</td><td>'.($idletime?'Offline since':'Online').'</td><td>'.($idletime?htmlspecialchars($idletime):'Seen last hour').'</td></tr>';
			echo '<tr><th colspan="3">Battery</th></tr>';
			echo '<tr><td style="color:'.htmlspecialchars($supplylight).';">●</td><td>Voltage</td><td>'.htmlspecialchars($supply).'V</td></tr>';
			echo '<tr><th colspan="3">Radio</th></tr>';
			if ($radiosuccess) echo '<tr><td style="color:'.htmlspecialchars($radiolight).';">●</td><td>Delivery</td><td>'.htmlspecialchars(round(100.0*$radiosuccess)).' % of last '.htmlspecialchars($radiocount).' packets</td></tr>';
			echo '<tr><th colspan="3">Sensors</th></tr>';
			echo '<tr><td style="color:'.htmlspecialchars($gpslight).';">●</td><td>GPS</td><td>'.htmlspecialchars(round(100.0*$perchasgps)).' % present in last '.htmlspecialchars($gpscount).' packets</td></tr>';
			echo '<tr><td style="color:'.htmlspecialchars($humiditylight).';">●</td><td>Humidity</td><td>'.htmlspecialchars(round(100.0*$percinvalidhum)).' % invalid Φ (&lt;10% or &gt;100%)</td></tr>';
			echo '<tr><td></td><td></td><td>'.round(100.0*htmlspecialchars($percinvaliddhum)).' % invalid ΔΦ (=0 or &gt;50%)</td></tr>';
			echo '<tr><td></td><td></td><td>R <sub>TΦ</sub> = '.htmlspecialchars($Rtmphum).'</td></tr>';
			echo '</table>';


		info+= '<tr><td>Last&nbsp;measurement</td><td>' + props.timestamp + '</td></tr>';
		info+= '<tr><td>Temperature</td><td>' + parseFloat(props.temperature).toFixed(1) + '⁰C</td></tr>';
		info+= '<tr><td>Humidity</td><td>' + parseFloat(props.humidity).toFixed(1) + '%</td></tr>';
		if (props.light>0) info+= '<tr><td>Light</td><td>' + props.light + 'lux</td></tr>';
*/		info+= '</table>';
//		info+= '<img src="https://meetjestad.net/data/graphs.php?id=' + props.id + '&width=120&height=80"/><br/>';
//		info+= '<a href="https://meetjestad.net/data/sensors_recent.php?sensor=' + props.id + '&limit=50" target="_blank">go to data</a>';
//		info+= '<br/>';
//		info+= '<a href="https://meetjestad.net/node/' + props.id + '" target="_blank">sensor status</a>';
		// Offset the popup so it points at the middle of the marker not the tip
		popup.setOffset([10, -60]);
		popup.show(coord, info);
	}
}

			map = new ol.Map({
				target: 'map',
				renderer: 'canvas', // Force the renderer to be used
				layers: [
					new ol.layer.Tile({
						title: 'Topography',
						type: 'base',
						visible: true,
						source: new ol.source.OSM()
					}),
					repairDataLayer
				],
				view: new ol.View({
					center: ol.proj.transform([5.3900, 52.1730], 'EPSG:4326', 'EPSG:900913'),
					zoom: 13
				})
			});

//			// add layer switcher
//			var layerSwitcher = new ol.control.LayerSwitcher();
//			map.addControl(layerSwitcher);

			// add data popup routines
			var popup = new ol.Overlay.Popup();
			map.addOverlay(popup);
			map.on('singleclick', dataPopups);

			// callback routines for zoom and drag operations
			map.getView().on('propertychange', function(e) {
				switch (e.key) {
					case 'zoom':
					case 'center':
					case 'resolution':
						redisplay();
						break;
				}
			});
			window.addEventListener("resize", redisplay);

/*			// get data
			var xhttp = new XMLHttpRequest();
			xhttp.open('GET', '../data/repair_json.php', true);
			xhttp.responseType = 'json';
			xhttp.onload = function() {
				if (xhttp.status == 200) {
					var data = xhttp.response;


//					focusMapOnData(map, data);
				}
			};
			xhttp.send();
*/		</script>
	</body>
</html>
