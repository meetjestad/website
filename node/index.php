<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
		<meta http-equiv="refresh" content="900" />
		<title>Meet je stad! Node info</title>
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
		function goToNode() {
			var id = document.getElementById("node_id").value
			window.location.replace(id)
			return false;
		}
		</script>
	</head>
	<body>
		<div style="display:table; margin:0 auto;">
			<img style="text-align:left; vertical-align:top;" src="../images/logo.png"/>
		</div>
		<div style="display: table; margin: 10px auto;">
			<h2>Info for a single measurement station</h2>
			<form onsubmit="return goToNode()">
				<label for="node_id">Measurement station number:</label>
				<input id="node_id" required="required" type="text"></input>
				<input type="submit" value="Go"></input>
			</form>
			<h2>Info for a set of measurement stations</h2>
			<a href="list.html">Can be found at list.html</a>
		</div>
	</body>
</html>
