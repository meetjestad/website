<?
	error_reporting(E_ALL & ~E_NOTICE);

	require('../connect.php');
	$database = Connection();

	$result = $database->query("SELECT DATE(timestamp) as date, COUNT(timestamp) as count FROM slam_measurement GROUP BY DATE(timestamp)");
	$slamIndex = '<select id="slamindex" onchange="selectSlam(this.value);">';
	$slamIndex.= '<option disabled="disabled" selected="selected"">kies een datum</option>';
	while($row = $result->fetch_array(MYSQLI_ASSOC)) {
		$date = $row['date'];
		$count = $row['count'];
		$slamIndex.= '<option value="'.htmlspecialchars($date).'">'.htmlspecialchars("$date ($count measurements)").'</option>';
	}
	$slamIndex.= '</select>';
?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="chrome=1">
		<meta name="viewport" content="initial-scale=1.0, user-scalable=no, width=device-width">
		<title>Cityslam</title>
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/openlayers/4.6.5/ol.css" type="text/css">
		<script src="https://cdnjs.cloudflare.com/ajax/libs/openlayers/4.6.5/ol.js"></script>
		<style type="text/css">
			html, body {
				margin: 0;
				padding: 0;
				width: 100%;
				height: 100%;
				font-family: Dosis;
				font-size: 12pt;
			}
			#map {
/*				cursor: crosshair;*/
				margin: 0;
				padding: 0;
				width: 100%;
				height: 100%;
			}
			#controls {
				position:absolute;
				top:0px;
				left:100px;
				width:600px;
				border:1px solid black;
				background-color:#eee;
				opacity:0.8;
				padding:4px;
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
	</head>
	<body id="body">
		<div id="map"></div>
		<div id="controls">
			<table>
				<tr>
					<th>Meet je stad cityslam</th>
					<td><? echo $slamIndex; ?></td>
					<td id="download"></td>
				</tr>
			</table>
			<div style="clear:left; float:left;" id="Tmin"></div>
			<div style="float:right;" id="Tmax"></div>
			<canvas style="width:100%; height:10px;" id="tscale"/>
		</div>
		<script>
			"use strict";

			var data = null;
			var canvas;
			var context;
			var canvasOrigin;
			var delta;
			var width;
			var height;
			var bbox;
			var timer = null;
			var map;

			// code to rescale map and ahn overlay when zooming or changing window size
			function redisplay() {
				// clear canvas
				if (context) {
					var imgData = context.getImageData(0,0,width,height);
					for(var i=0;i<imgData.length;i++) imgData[i] = 0;
					context.putImageData(imgData,0,0);
					map.renderSync();
				}
				// draw heatmap
				if (data!=null) drawHeatmap();
			}

			var canvasFunction = function(extent, resolution, pixelRatio, size, projection) {
				canvas = document.createElement('canvas');
				context = canvas.getContext('2d');
				width = Math.round(size[0]), height = Math.round(size[1]);
				canvas.setAttribute('width', width);
				canvas.setAttribute('height', height);

				// Canvas extent is different than map extent, so compute delta between
				// left-top of map and canvas extent.
				var mapExtent = map.getView().calculateExtent(map.getSize())
				var mapOrigin = map.getPixelFromCoordinate([mapExtent[0], mapExtent[3]]);
				canvasOrigin = map.getPixelFromCoordinate([extent[0], extent[3]]);
				delta = [mapOrigin[0]-canvasOrigin[0], mapOrigin[1]-canvasOrigin[1]]

				return canvas;
			};

			function hslToRgb(h, s, l) {
				var r, g, b;
				if (s == 0) {
					r = g = b = l; // achromatic
				}
				else {
					var hue2rgb = function hue2rgb(p, q, t){
						if(t < 0) t += 1;
						if(t > 1) t -= 1;
						if(t < 1/6) return p + (q - p) * 6 * t;
						if(t < 1/2) return q;
						if(t < 2/3) return p + (q - p) * (2/3 - t) * 6;
						return p;
					}
					var q = l < 0.5 ? l * (1 + s) : l + s - l * s;
					var p = 2 * l - q;
					r = hue2rgb(p, q, h + 1/3);
					g = hue2rgb(p, q, h);
					b = hue2rgb(p, q, h - 1/3);
				}
				return [ r * 255, g * 255, b * 255 ];
			}
			function rgbToHsl(r, g, b) {
				r /= 255, g /= 255, b /= 255;
				var max = Math.max(r, g, b), min = Math.min(r, g, b);
				var h, s, l = (max + min) / 2;
				if (max == min) {
					h = s = 0; // achromatic
				} else {
					var d = max - min;
					s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
					switch (max) {
						case r: h = (g - b) / d + (g < b ? 6 : 0); break;
						case g: h = (b - r) / d + 2; break;
						case b: h = (r - g) / d + 4; break;
					}
					h /= 6;
				}
				return [ h, s, l ];
			}

			Math.radians = function(degrees) {
				return degrees * Math.PI / 180;
			};

			// http://www.movable-type.co.uk/scripts/latlong.html
			function distance(pos1, pos2) {
				var R = 6371e3; // metres
				var phi1 = Math.radians(pos1[1]);
				var phi2 = Math.radians(pos2[1]);
				var dphi = Math.radians(pos2[1]-pos1[1]);
				var dlambda = Math.radians(pos2[0]-pos1[0]);

				var a = Math.sin(dphi/2) * Math.sin(dphi/2) +
						Math.cos(phi1) * Math.cos(phi2) *
						Math.sin(dlambda/2) * Math.sin(dlambda/2);
				var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));

				return R * c;
			}

			function dist(x1, y1, x2, y2) {
				var dx = x2 - x1;
				var dy = y2 - y1;
				return Math.sqrt(dx*dx+dy*dy);
			}
			function drawHeatmap() {
				var features = data.features;
				var imgData = context.getImageData(0,0,width,height);

				// clear canvas
				var A = [];
				var B = [];
				var C = [];
				var T = [];
				for(var i=0;i<width*height;i++) {
					imgData[4*i+0] = 0;
					imgData[4*i+1] = 0;
					imgData[4*i+2] = 0;
					imgData[4*i+3] = 0;
					A[i] = 0.0;
					B[i] = 0.0;
					C[i] = -1;
				}

				// determine radius
 				var r = 25;

				// calculate inverse distance weighting factors, see http://gisgeography.com/inverse-distance-weighting-idw-interpolation/
				for(var f=0;f<features.length;f++) {
					var sensorPos = ol.proj.transform(features[f].geometry.coordinates, 'EPSG:4326', 'EPSG:900913');
					var sensorPix = map.getPixelFromCoordinate(sensorPos);
					var sensorX = Math.round(sensorPix[0] + delta[0]);
					var sensorY = Math.round(sensorPix[1] + delta[1]);
					for (var y=Math.max(0, sensorY-r); y<Math.min(height, sensorY+r); y++) {
						for (var x=Math.max(0, sensorX-r); x<Math.min(width, sensorX+r); x++) {
							var i = y*width + x;
							var d = dist(x, y, sensorX, sensorY);
							if (d < 1.0) d = 1.0;
							if (d < r) {
								A[i]+= features[f].properties.temperature / d;
								B[i]+= 1.0 / d;
								C[i] = C[i] > -1 ? Math.min(C[i], d) : d;
							}
						}
					}
				}

				// calculate weighted temperatures and determine interpolated min and max temperatures
				var Tmin = -9999;
				var Tmax = -9999;
				for (var i=0; i<width*height; i++) if (C[i] > -1) {
					T[i] = A[i]/B[i];
					Tmin = Tmin > -9999 ? Math.min(Tmin, T[i]) : T[i];
					Tmax = Tmax > -9999 ? Math.max(Tmax, T[i]) : T[i];
				}
				Tmin = Math.round(100*Tmin)/100;
				Tmax = Math.round(100*Tmax)/100;

				// draw map
				for (var i=0; i<width*height; i++) if (C[i] > -1) {
					var rgb = hslToRgb(0.7*(1.0-(T[i]-Tmin)/(Tmax-Tmin)), 1.0, 0.5);
					var a = Math.round(255*(1.0 - C[i]/r));
					if (a>imgData.data[4*i+3]) {
						imgData.data[4*i+0] = rgb[0];
						imgData.data[4*i+1] = rgb[1];
						imgData.data[4*i+2] = rgb[2];
						imgData.data[4*i+3] = a;
					}
				}
				context.putImageData(imgData,0,0);
				map.renderSync();

				// draw temperature scale
				var Tcanvas = document.getElementById('tscale');
				var Tcontext = Tcanvas.getContext('2d');
				var Timg = Tcontext.getImageData(0,0,Tcanvas.width,Tcanvas.height)
				for (var x=0;x<Tcanvas.width;x++) for (var y=0;y<Tcanvas.height;y++) {
					var i = 4*(y*Tcanvas.width+x);
					var T = 0.7*(1.0-1.0*x/Tcanvas.width);
					var rgb = hslToRgb(T, 1.0, 0.5);
					Timg.data[i+0] = rgb[0];
					Timg.data[i+1] = rgb[1];
					Timg.data[i+2] = rgb[2];
					Timg.data[i+3] = 255;
				}
				Tcontext.putImageData(Timg,0,0);
				document.getElementById('Tmin').innerHTML = Tmin + '°C';
				document.getElementById('Tmax').innerHTML = Tmax + '°C';
			}

			var heatmap = new ol.source.ImageCanvas({
				canvasFunction: canvasFunction
			});

			map = new ol.Map({
				target: 'map',
				renderer: 'canvas', // Force the renderer to be used
				layers: [
					new ol.layer.Tile({
						source: new ol.source.OSM()
					}),
					new ol.layer.Image({
						source: heatmap
					})
				],
				view: new ol.View({
					center: ol.proj.transform([5.3900, 52.1730], 'EPSG:4326', 'EPSG:900913'),
					zoom: 13
				})
			});

			redisplay();

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

			function selectSlam(id, reload = false) {
				if (!reload && context) heatmap.refresh();

				var xhttp = new XMLHttpRequest();
				if (data==null) document.getElementById('download').innerHTML = 'data ophalen...';
//				xhttp.open('GET', 'slamdata_json.php?date='+id, true);
				xhttp.open('GET', 'slamdata_json.php?date='+id+'&calibrate', true);
				xhttp.responseType = 'json';
				xhttp.onload = function() {
					if (xhttp.status == 200) {
						data = xhttp.response;

						document.getElementById('download').innerHTML = '<input type="button" onclick="window.open(\'http://www.meetjestad.net/data/slamdata.php?date=' + id + '\', \'_blank\');" value="ga naar data"/>';

						if (!reload) {
							var minLon, minLat, maxLon, maxLat;
							for(var i=0;i<data.features.length;i++) {
								var coord = data.features[i].geometry.coordinates;
								minLon = minLon ? Math.min(coord[0], minLon) : coord[0];
								minLat = minLat ? Math.min(coord[1], minLat) : coord[1];
								maxLon = maxLon ? Math.max(coord[0], maxLon) : coord[0];
								maxLat = maxLat ? Math.max(coord[1], maxLat) : coord[1];
							}
							var coordMin = ol.proj.fromLonLat([minLon, minLat], 'EPSG:900913');
							var coordMax = ol.proj.fromLonLat([maxLon, maxLat], 'EPSG:900913');
							var extent=[coordMin[0], coordMin[1], coordMax[0], coordMax[1]];
							map.getView().fit(extent, map.getSize());
							heatmap.changed();
							map.renderSync();
						}
						drawHeatmap();

						if (timer) clearInterval(timer);
						timer = setInterval(function(){selectSlam(id, true);}, 10000);
					}
				};
				xhttp.send();
			}
		</script>
	</body>
</html>
