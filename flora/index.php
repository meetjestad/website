<?php
	// Ophalen van de flora
	include ("../connect.php");
	$database = Connection();
	
	// verwerk observatie
	if (isset($_POST['id'])) {
		$result = $database->query("INSERT INTO `flora_observaties` (`soort_id`,`waarneming_id`,`datum`,`locatie`,`omschrijving`) VALUES ('".$_POST['id']."','".$_POST['waarneming']."','".date("Y-m-d",strtotime($_POST['datum']))."','".$_POST['lon'].",".$_POST['lat']."','".$_POST['omschrijving']."')");
		if (!$result) $html.= $database->error.PHP_EOL;
		
		$result = $database->query("SELECT naam_nl,naam_la,afbeelding,omschrijving,waarnemingen FROM flora WHERE id=".$_POST['id']);
		$table = $result->fetch_array(MYSQLI_ASSOC);
		$waarnemingen = json_decode($table["waarnemingen"]);
		$html.= 'De volgende observatie is toegevoegd aan Meet je stad:<br/>'.PHP_EOL;
		$html.= '<table>'.PHP_EOL;
		$html.= '<tr><th>Datum</th><td>'.date("Y-m-d",strtotime($_POST['datum'])).'</td></tr>'.PHP_EOL;
		$html.= '<tr><th>Latitude</th><td>'.$_POST['lat'].'</td></tr>'.PHP_EOL;
		$html.= '<tr><th>Longitude</th><td>'.$_POST['lon'].'</td></tr>'.PHP_EOL;
		$html.= '<tr><th>Soort</th><td><b>'.$table['naam_nl'].'</b> <i>'.$table['naam_la'].'</i></td></tr>'.PHP_EOL;
		$html.= '<tr><th>Waarneming</th><td>'.$waarnemingen[$_POST['waarneming']].'</td></tr>'.PHP_EOL;
		$html.= '<tr><th>Opmerkingen</th><td>'.$_POST['omschrijving'].'</td></tr>'.PHP_EOL;
		$html.= '</table>'.PHP_EOL;
		$html.= 'Bedankt voor je observatie!<br/>'.PHP_EOL;
		$html.= '<input type="button" value="ga naar kaart" onclick="window.location=\'index.php\';" />'.PHP_EOL;
		$html.= '<input type="button" value="nieuwe observatie" onclick="window.location=\'\';" />'.PHP_EOL;
	}
	
	// vul observatie in
	else if (isset($_GET['id'])) {
		$result = $database->query("SELECT naam_nl,naam_la,afbeelding,omschrijving,waarnemingen FROM flora WHERE id=".$_GET['id']);
		$table = $result->fetch_array(MYSQLI_ASSOC);
		$waarnemingen = json_decode($table["waarnemingen"]);
		$html.= '<form name="floraObservationForm" method="POST" action="">'.PHP_EOL;
		$html.= '<input type="hidden" name="id" value="'.$_GET['id'].'"/>'.PHP_EOL;
		
		$html.= '<div class="observatie">'.PHP_EOL;
		$html.= '<b>'.$table["naam_nl"].'</b> <i>'.$table["naam_la"].'</i><br/>'.PHP_EOL;
		$html.= '<img class="afbeelding" src="images/'.$table["afbeelding"].'" /><br/>'.PHP_EOL;
		$html.= $table["omschrijving"].PHP_EOL;
		$html.= '</div>'.PHP_EOL;
		
		$html.= '<div class="observatie">'.PHP_EOL;
		$html.= 'Vul het formulier in en klik op \'verzend\'.<br/>'.PHP_EOL;
		
		$html.= '<b>soort waarneming</b> <select name="waarneming">'.PHP_EOL;
		$html.= '<option selected="true" disabled="disabled">kies een waarneming...</option>'.PHP_EOL;
		foreach($waarnemingen as $id => $waarneming) $html.= '<option value="'.$id.'">'.$waarneming.'</option>'.PHP_EOL;
		$html.= '</select><br/>'.PHP_EOL;
		$html.= '<b>datum waarneming</b> <input type="date" id="datum" name="datum" size="20" placeholder="DD/MM/JJJJ" class="form-control" /><br/>'.PHP_EOL;
		$html.= '<b>locatie waarneming</b> (sleep de punaise naar de juiste plek)<br/><div id="map"> </div>'.PHP_EOL;
		$html.= '<input type="hidden" id="lat" name="lat"/>'.PHP_EOL;
		$html.= '<input type="hidden" id="lon" name="lon"/>'.PHP_EOL;
		$html.= '<b>omschrijving locatie</b><br/><textarea name="omschrijving" placeholder="bijv. op balkon zuidzijde" id="omschrijving"></textarea><br/>'.PHP_EOL;
		$html.= '<input type="button" value="terug" onclick="window.location=\'\';" />'.PHP_EOL;
		$html.= '<input type="button" value="verzend" onclick="validateFloraObservation();"/>'.PHP_EOL;
		$html.= '</div>'.PHP_EOL;
		
		$html.= '</form>'.PHP_EOL;
		
		$html.= '<script>getLocation();</script>'.PHP_EOL;
	}
	
	// toon uitleg
	else if (isset($_GET['info'])) {
		$html = '<div>'.file_get_contents('pages/flora_uitleg').'</div>';
	}
	
	// toon lijst
	else {
		$result = $database->query("SELECT id,naam_nl,naam_la,afbeelding,omschrijving,waarnemingen FROM flora ORDER BY naam_la ASC");
		$html = '<div>
			De Floragroep van Meet je stad! richt zich op het waarnemen van de jaarlijks terugkerende verschijnselen in de natuur, de fenologie. Fenologie is de leer van de betrekkingen tussen het weer en het leven in de natuur. Natuurlijke processen zoals de ontwikkeling van bladeren, de start van bloei, het vrijkomen van zaden en vruchten, de verkleuring van blad en bladval vinden elk jaar in een vaste periode plaats. Alles wordt sterk beïnvloed door temperatuur, neerslag en daglengte. Door klimaatverandering verandert de “natuurkalender” van veel plantensoorten zichtbaar.<br/>
			<br/>
			Hieronder kunt u door op een foto te klikken uw waarnemingen met ons delen.<br/>
			Of <a href="?info">lees meer over de regels voor het waarnemen en definities van fenofasen van planten</a>.<br/><br/>
		</div>'.PHP_EOL;
		
		while($table = $result->fetch_array(MYSQLI_ASSOC)) {
			$html.= '<div class="soort">'.PHP_EOL;
			$html.= '<a href="?id='.$table["id"].'">'.PHP_EOL;
			if ($table["afbeelding"]) $html.= '<img class="listimage" src="images/'.$table["afbeelding"].'" /><br/>'.PHP_EOL;
			$html.= '<b>'.$table["naam_nl"].'</b><br/>'.PHP_EOL;
			$html.= '<i>'.$table["naam_la"].'</i>'.PHP_EOL;
			$html.= '</a>'.PHP_EOL;
			$html.= '</div>'.PHP_EOL;
		}
	}
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Meet je Stad</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<meta name="viewport" content="initial-scale=1.0, user-scalable=no, width=device-width">
		<link rel="stylesheet" href="../css/meetjestad.css">
		<style>
			body {
				font-family: Dosis;
				font-size: 12pt;
				width: 840px;
				padding: 5px;
				margin: 0px;
				box-sizing: border-box;
				text-align: justify;
			}
			div {
				text-align: left;
			}
			table {
				margin: 0px;
			}
			th {
				text-align: left;
			}
			.soort {
				display: inline-block;
				height: 250px;
				width: 200px;
				border: solid grey 1px;
				margin-bottom: 5px;
			}
			.soort a {
				font-weight: normal;
			}
			.observatie {
				 display:inline-block;
				 width:370px;
				 vertical-align:top;
			}
			.afbeelding {
				border: solid black 1px;
			}
			#omschrijving {
				width:360px;
				height:120px;
			}
			.listimage {
				width: 200px;
				height: 200px;
 			}
 			#map {
				width: 360px;
				height: 240px;
			}
			.nav {
				text-align: center;
				line-height: 80%px;
				padding-left: 0.5em;
				padding-right: 0.5em;
				margin: 5px;
				border-radius: 2px;
				cursor: pointer;
				font-size: 15pt;
				background-color: #0000bf;
				text-decoration: none;
				color: #cfcf00;
				font-weight: bold;
				border: 1px solid #00008f;
				letter-spacing: 0.1em;
			}
			#floralogo {
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
			@media only screen and (max-device-width: 961px) {
				body {
					width: 100%;
				}
				.observatie {
					display: block;
					width: 100%;
					margin-bottom: 10px;
				}
				.afbeelding {
					width: 100%;
				}
				#omschrijving {
					width: 100%;
					height: 180px;
				}
				#map {
					width: 100%;
				}
				#logo {
					width: 60%;
				}
			}
		</style>
		<script>
			function showhide(id) {
				state = document.getElementById(id).style.display == 'block';
				document.getElementById(id).style.display = state ? 'none' : 'block';
				document.getElementById('ctrl' + id).innerHTML = state ? '+' : '-';
			}
		</script>
		<script type="text/javascript">
			var datefield=document.createElement("input")
			datefield.setAttribute("type", "date")
			if (datefield.type!="date") { //if browser doesn't support input type="date", load files for jQuery UI Date Picker
				document.write('<link href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css" rel="stylesheet" type="text/css" />\n')
				document.write('<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js"><\/script>\n')
				document.write('<script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js"><\/script>\n') 
			}
		</script>
		
		<script>
			if (datefield.type!="date") { //if browser doesn't support input type="date", initialize date picker widget:
				jQuery(function($) { //on document.ready
					$('#datum').datepicker({
						dateFormat: 'dd-mm-yy',
						monthNames: ['Januari','Februari','Maart','April','Mei','Juni','Juli','Augustus','September','Oktober','November','December'],
						dayNames: ['Zondag', 'Maandag', 'Dinsdag', 'Woensdag', 'Dondersdag', 'Vrijdag','Zaterdag'],
						dayNamesMin: ['Zo', 'Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za']
					});
				})
			}
		</script>
		
		<script type="text/javascript" src="http://maplib.khtml.org/khtml.maplib/khtml_all.js"></script>
		<script>
			var lon = undefined;
			var lat = undefined;
			var zoom = 15;
			var marker;
			function getLocation() {
				// try to get location from device gps
				if (navigator.geolocation) navigator.geolocation.getCurrentPosition(getpos);
				// start from city centre if no usable location was obtained
				if (lat == undefined || lon == undefined) {
					lat = 52.15616055555555;
					lon = 5.38763888888889;
					zoom = 13;
				}
				
				// get map object and zoom to chosen location
				var map = khtml.maplib.Map(document.getElementById("map"));
				map.centerAndZoom(new khtml.maplib.LatLng(lat, lon), zoom);
				var zoominger=new khtml.maplib.ui.Zoombar();
				map.addOverlay(zoominger);
				
				// add draggable marker
				marker = new khtml.maplib.overlay.Marker({
					position: new khtml.maplib.LatLng(lat, lon),
					map: map,
					draggable: true,
					title: "observation"
				});
			}
			function getpos(position) {
				lon = position.coords.longitude;
				lat = position.coords.latitude;
			}
			function validateFloraObservation() {
				var error = '';
				if (document.forms["floraObservationForm"]["waarneming"].selectedIndex==0) error+= 'selecteer waarneming\n';
				if (!document.forms["floraObservationForm"]["datum"].value) error+= 'selecteer datum\n';
				var coords = marker.getPosition();
				if (coords.latitude==52.15616055555555 || coords.longitude==5.38763888888889) error+= 'selecteer positie\n';
				else {
					document.getElementById('lat').value = coords.latitude;
					document.getElementById('lon').value = coords.longitude;
				}
				if (error) alert(error);
				else document.forms["floraObservationForm"].submit();
			}
		</script>
	</head>
	<body>
			<?php if (!array_key_exists('nohome', $_GET)) { ?>
			<a id="homelink" class="menuDefault" href="/">&lt;HOME</a><br/>
			<?php } ?>
			<img id="floralogo" src="../images/logo_flora.png">
		<?=$html?>
	</body>
</html>
