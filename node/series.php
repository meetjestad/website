<?php
	$availableSeries = array(
		"temperature" => array("color" => "fb6127", "label" => "T[&#176;C]"),
		"humidity" => array("color" => "5677fc", "label" => "Φ[%]"),
		"lux" => array("color" => "8f8fbf", "label" => "E[lx]")
	);

	$id = $_GET['id'];
	$time = $_GET['time'];
	$series = explode(',', $_GET['series']);

	foreach($series as $quantity) if (!array_key_exists($quantity, $availableSeries)) exit;

	include ("../connect.php");
	$database = Connection();

	// === build graph === //
	$WHERE = "WHERE station_id='" . $database->real_escape_string($id) . "'";
	if ($time>0) $WHERE.= " AND Unix_timestamp(timestamp)>=".(time() - 60*60*24*$time);
	$result = $database->query("SELECT station_id,timestamp,".implode(',', $series)." FROM sensors_measurement $WHERE");

	$s = array();
	foreach($series as $quantity)
		$s[$quantity] = array();

	// get data series
	$min_t = $time>0 ? time() - 60*60*24*$time : time();
	$max_t = time();
	while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
		$t = strtotime($row["timestamp"]);
		if ($time==0 && $t!=0) $min_t = min($t, $min_t);
		foreach($series as $quantity) {
			$s[$quantity][$t] = $row[$quantity];
		}
	}
	if ($time==0) $time = round((time() - $min_t)/(24*60*60));
	$width = empty($_GET['width']) ? 220 : $_GET['width'];
	$height = empty($_GET['height']) ? 120 : $_GET['height'];
	$margin = 40;

	// scale to fill y axis
	foreach($series as $quantity) {
		$min_s[$quantity] = $s[$quantity] ? min($s[$quantity]) : 0;
		$max_s[$quantity] = $s[$quantity] ? max($s[$quantity]) : 1;
		foreach($s[$quantity] as &$val) $val = ($val-$min_s[$quantity])/($max_s[$quantity]-$min_s[$quantity])*$height;
	}

	// combine series and convert to paths
	$paths = array();
	foreach($series as $quantity) {
		if (!isset($paths[$quantity])) $paths[$quantity] = '';
		foreach($s[$quantity] as $t => $y) $paths[$quantity].= ($paths[$quantity]?"L":"M").round(($width*($t-$min_t)/($max_t-$min_t))+$margin, 2).",".round($height-$y, 2)." ";
	}
	$svg = '<?xml version="1.0" encoding="utf-8" standalone="no"?>' . "\n";
	$svg.= '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">';
	$svg.= '<svg width="'.htmlspecialchars($width+$margin).'px" height="'.htmlspecialchars($height).'px" id="graph" version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.htmlspecialchars($width+$margin).' '.htmlspecialchars($height).'" preserveAspectRatio="none" style="font-family:dosis; font-size:12px;">';
	$svg.= '<defs><style type="text/css">@font-face {font-family:dosis; src:url(\'../css/fonts/Dosis-Regular.otf\');}</style></defs>';

	// draw border
	$svg.= '<rect x="'.htmlspecialchars($margin).'" y="0" width="'.htmlspecialchars($width).'" height="'.htmlspecialchars($height).'" vector-effect="non-scaling-stroke" style="fill:white;stroke:black;"></rect>';

	// draw series
	foreach($paths as $i => $path) $svg.= '<path vector-effect="non-scaling-stroke" style="fill:none;stroke:#'.htmlspecialchars($availableSeries[$i]["color"]).';" d="'.htmlspecialchars($path).'"></path>';

	// draw labels
	$row = 0;
	foreach($series as $quantity) {
		$svg.= '<text x="'.htmlspecialchars($margin-2).'" y="'.htmlspecialchars($height-(12*(count($series)-$row-1))).'" style="text-anchor:end; fill:#'.htmlspecialchars($availableSeries[$quantity]["color"]).';">'.htmlspecialchars(round($min_s[$quantity], 1)).'</text>';
		$svg.= '<text x="'.htmlspecialchars($margin-2).'" y="'.htmlspecialchars(10+12*$row).'" style="text-anchor:end; fill:#'.htmlspecialchars($availableSeries[$quantity]["color"]).';">'.htmlspecialchars(round($max_s[$quantity], 1)).'</text>';
		$row++;
	}
	$svg.= '</svg>';

	header('Content-type: image/svg+xml');
	echo $svg;
