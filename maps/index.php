<?php
	// === get page === //
	$path = substr($_SERVER["PHP_SELF"], 0, strpos($_SERVER["PHP_SELF"], 'index.php'));
	// Use REDIRECT_URL when set, since when we are mod_rewritten, this
	// corresponds to the url that would have corresponded to the rewritten
	// request (which then matches the prefix of PHP_SELF).
	$uri = isset($_SERVER["REDIRECT_URL"]) ? $_SERVER["REDIRECT_URL"] : $_SERVER["REQUEST_URI"];
	$query = explode('/', substr(strtok($uri, '?'), strlen($path)));
//	$query = strpos(substr($_SERVER["REQUEST_URI"], strlen($_path)), '?');


	$lang = isset($_GET['lang']) ? $_GET['lang'] : 'nl';

	// get experiment id from query
	if (file_exists('../sensorsets.json')) $sensorsets = json_decode(file_get_contents('../sensorsets.json'), true);
	else $sensorsets = array();

	$id = $query[0];
	if ($id) {
		$ids = preg_replace_callback('/(\d+)-(\d+)/', function($m) {
			return implode(',', range($m[1], $m[2]));
		}, urldecode($sensorsets[$id]['ids']));
		$dataSelection = 'ids='.$ids;
	}
	else $dataSelection = 'select=all';

//~ echo $query[0].'<br/>';
//~ echo $dataSelection;
//~ exit;
	//~ $dataSelection = '';
	//~ if (isset($_GET['layer'])) {
		//~ $layer = $_GET['layer'];
		//~ switch($layer) {
			//~ case 'hittekaart':
				//~ if (isset($_GET['start']) && $_GET['start']) $dataSelection.= ($dataSelection?'&':'?').'start='.urldecode($_GET['start']);
				//~ if (isset($_GET['end']) && $_GET['end']) $dataSelection.= ($dataSelection?'&':'?').'end='.urldecode($_GET['end']);
				//~ if(isset($_GET['ids']) && $_GET['ids']) $dataSelection.= ($dataSelection?'&':'?').'ids='.preg_replace_callback('/(\d+)-(\d+)/', function($m) { return implode(',', range($m[1], $m[2]));}, urldecode($_GET['ids']));
				//~ break;
		//~ }
	//~ }
?>
<!DOCTYPE html>
<html>
	<head>
<!--
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="chrome=1">
-->
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<meta name="viewport" content="initial-scale=1.0, user-scalable=no, width=device-width">
		<title>Meet je stad!</title>
		<link rel="icon" href="favicon.ico" type="image/x-icon">

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
		<link rel="stylesheet" type="text/css" href="../css/meetjestad.css" media="all" />
		<script>
			var dataUrl = 'https://meetjestad.net/data/sensors_json.php?<?=$dataSelection?>';
		</script>
		<script src="../js/mapfunctions.js"></script>
		<script src="../js/backgroundlayers.js"></script>
		<script src="../js/datalayers.js"></script>
		<script src="../js/maplayers.js"></script>
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
<?php if (!array_key_exists('nohome', $_GET)) { ?>
		<a id="homelink" class="menuDefault absolute" href="/">&lt;HOME</a>
<?php } ?>
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

			map = new ol.Map({
				target: 'map',
				renderer: 'canvas', // Force the renderer to be used
				layers: [
					topografie,
					new ol.layer.Group({
						title: 'Kaarten',
						layers: [
							hittekaart
						]
					}),
					sensorHourDataLayer
				],
				view: new ol.View({
					center: ol.proj.transform([5.3900, 52.1730], 'EPSG:4326', 'EPSG:900913'),
					zoom: 13
				})
			});

			// add layer switcher
			var layerSwitcher = new ol.control.LayerSwitcher();
			map.addControl(layerSwitcher);

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

			function toggleMenu() {
				menu = document.getElementById('menu');
				if (menu.style.display == 'block') menu.style.display = 'none';
				else menu.style.display = 'block';
			}

			// get data
			var xhttp = new XMLHttpRequest();
			xhttp.open('GET', dataUrl, true);
			xhttp.responseType = 'json';
			xhttp.onload = function() {
				if (xhttp.status == 200) {
					var data = xhttp.response;
					focusMapOnData(map, data);
				}
			};
			xhttp.send();
		</script>
	</body>
</html>
