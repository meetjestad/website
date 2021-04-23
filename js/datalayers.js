// get last hour sensor data
var sensorHourStyleCache = {};
var sensorHourDataLayer = new ol.layer.Vector({
	title: 'sensors last hour',
	source: new ol.source.Cluster({
		distance: 40,
		source: new ol.source.Vector({
			url: dataUrl,
			defaultProjection: 'EPSG:4326',
			projection: 'EPSG:28992',
			format: new ol.format.GeoJSON()
		})
	}),
	style: function(feature, resolution) {
		var size = feature.get('features').length;
		var style = sensorHourStyleCache[size];
		if (!style) {
			var label = '';
			if (size>1) label = size.toString();
			style = [new ol.style.Style({
				image: new ol.style.Icon(({
					scale: 0.5 + Math.log(size)/10,
					anchor: [0, 1.0],
					anchorXUnits: 'fraction',
					anchorYUnits: 'fraction',
					opacity: 0.75,
					src: 'https://meetjestad.net/images/sensor.png'
				})),
				text: new ol.style.Text({
					text: label,
					offsetX: 8,
					offsetY: -8,
					fill: new ol.style.Fill({
						color: '#000'
					})
				})
			})];
			sensorHourStyleCache[size] = style;
		}
		return style;
	}
});

// get sensor data for Amersfoort suburbs
var sensorSuburbStyleCache = {};
var sensorSuburbDataLayer = new ol.layer.Vector({
	visible: false,
	title: 'sensors suburb topology',
	source: new ol.source.Cluster({
		distance: 40,
		source: new ol.source.Vector({
			url: 'https://meetjestad.net/data/sensorsSuburb_json.php',
			defaultProjection: 'EPSG:4326',
			projection: 'EPSG:28992',
			format: new ol.format.GeoJSON()
		})
	}),
	style: function(feature, resolution) {
		var size = feature.get('features').length;
		var style = sensorSuburbStyleCache[size];
		if (!style) {
			var label = '';
			if (size>1) label = size.toString();
			style = [new ol.style.Style({
				image: new ol.style.Icon(({
					scale: 0.5 + Math.log(size)/10,
					anchor: [0, 1.0],
					anchorXUnits: 'fraction',
					anchorYUnits: 'fraction',
					opacity: 0.75,
					src: 'images/sensor.png'
				})),
				text: new ol.style.Text({
					text: label,
					offsetX: 8,
					offsetY: -8,
					fill: new ol.style.Fill({
						color: '#000'
					})
				})
			})];
			sensorSuburbStyleCache[size] = style;
		}
		return style;
	}
});


// get sensor data for Bodemvocht (soil moisture)
var sensorBodemStyleCache = {};
var sensorBodemDataLayer = new ol.layer.Vector({
	visible: false,
	title: 'soil moisture sensors',
	source: new ol.source.Cluster({
		distance: 40,
		source: new ol.source.Vector({
			url: 'https://meetjestad.net/data/sensorsBodem_json.php',
			defaultProjection: 'EPSG:4326',
			projection: 'EPSG:28992',
			format: new ol.format.GeoJSON()
		})
	}),
	style: function(feature, resolution) {
		var size = feature.get('features').length;
		var style = sensorBodemStyleCache[size];
		if (!style) {
			var label = '';
			if (size>1) label = size.toString();
			style = [new ol.style.Style({
				image: new ol.style.Icon(({
					scale: 0.5 + Math.log(size)/10,
					anchor: [0, 1.0],
					anchorXUnits: 'fraction',
					anchorYUnits: 'fraction',
					opacity: 0.75,
					src: 'images/sensor.png'
				})),
				text: new ol.style.Text({
					text: label,
					offsetX: 8,
					offsetY: -8,
					fill: new ol.style.Fill({
						color: '#000'
					})
				})
			})];
			sensorBodemStyleCache[size] = style;
		}
		return style;
	}
});


// get all sensor data
var sensorAllStyleCache = {};
var sensorAllDataLayer = new ol.layer.Vector({
	visible: false,
	title: 'all sensors',
	source: new ol.source.Cluster({
		distance: 40,
		source: new ol.source.Vector({
			url: 'https://meetjestad.net/data/sensors_json.php?select=all',
			defaultProjection: 'EPSG:4326',
			projection: 'EPSG:28992',
			format: new ol.format.GeoJSON()
		})
	}),
	style: function(feature, resolution) {
		var size = feature.get('features').length;
		var style = sensorAllStyleCache[size];
		if (!style) {
			var label = '';
			if (size>1) label = size.toString();
			style = [new ol.style.Style({
				image: new ol.style.Icon(({
					scale: 0.5 + Math.log(size)/10,
					anchor: [0, 1.0],
					anchorXUnits: 'fraction',
					anchorYUnits: 'fraction',
					opacity: 0.75,
					src: 'images/sensor.png'
				})),
				text: new ol.style.Text({
					text: label,
					offsetX: 8,
					offsetY: -8,
					fill: new ol.style.Fill({
						color: '#000'
					})
				})
			})];
			sensorAllStyleCache[size] = style;
		}
		return style;
	}
});

// get observation data
var observationStyleCache = {};
var observationDataLayer = new ol.layer.Vector({
	title: 'flora',
	source: new ol.source.Cluster({
		distance: 40,
		source: new ol.source.Vector({
			url: 'https://meetjestad.net/data/observations_json.php',
			defaultProjection: 'EPSG:4326',
			projection: 'EPSG:28992',
			format: new ol.format.GeoJSON()
		})
	}),
	style: function(feature, resolution) {
		var size = feature.get('features').length;
		var style = observationStyleCache[size];
		if (!style) {
			var label = '';
			if (size>1) label = size.toString();
			style = [new ol.style.Style({
				image: new ol.style.Icon(({
					scale: 0.5 + Math.log(size)/10,
					anchor: [0, 1.0],
					anchorXUnits: 'fraction',
					anchorYUnits: 'fraction',
					opacity: 0.75,
					src: 'images/observation.png'
				})),
				text: new ol.style.Text({
					text: label,
					offsetX: 8,
					offsetY: -8,
					fill: new ol.style.Fill({
						color: '#000'
					})
				})
			})];
			observationStyleCache[size] = style;
		}
		return style;
	}
});

// get story data
var storyStyleCache = {};
var storyDataLayer = new ol.layer.Vector({
	visible: false,
	title: 'stories',
	source: new ol.source.Cluster({
		distance: 40,
		source: new ol.source.Vector({
			url: 'https://meetjestad.net/data/stories_json.php',
			defaultProjection: 'EPSG:4326',
			projection: 'EPSG:28992',
			format: new ol.format.GeoJSON()
		})
	}),
	style: function(feature, resolution) {
		var size = feature.get('features').length;
		var style = storyStyleCache[size];
		if (!style) {
			var label = '';
			if (size>1) label = size.toString();
			style = [new ol.style.Style({
				image: new ol.style.Icon(({
					scale: 0.5 + Math.log(size)/10,
					anchor: [0, 1.0],
					anchorXUnits: 'fraction',
					anchorYUnits: 'fraction',
					opacity: 0.75,
					src: 'images/story.png'
				})),
				text: new ol.style.Text({
					text: label,
					offsetX: 8,
					offsetY: -8,
					fill: new ol.style.Fill({
						color: '#000'
					})
				})
			})];
			storyStyleCache[size] = style;
		}
		return style;
	},
});

function dataPopups(evt) {
	// Hide existing popup and reset it's offset
	popup.hide();
	popup.setOffset([0, 0]);
	
	// Attempt to find a feature in one of the visible vector layers
	var feature = map.forEachFeatureAtPixel(evt.pixel, function(feature, layer) {
		return feature;
	});
	
	feature = feature.get('features')[0];
	
	if (feature) {
		var coord = feature.getGeometry().getCoordinates();
		var props = feature.getProperties();
		var info;
		switch(props.type) {
			case 'sensor':
				info = '<h2 style="margin-bottom:0px;">' + props.location + '</h2>';
				info+= '<table>';
				info+= '<tr><td>Last&nbsp;measurement</td><td>' + props.timestamp + '</td></tr>';
				info+= '<tr><td>Temperature</td><td>' + parseFloat(props.temperature).toFixed(1) + '⁰C</td></tr>';
				info+= '<tr><td>Humidity</td><td>' + parseFloat(props.humidity).toFixed(1) + '%</td></tr>';
				if (props.light>0) info+= '<tr><td>Light</td><td>' + props.light + 'lux</td></tr>';
				info+= '</table>';
				info+= '<img src="https://meetjestad.net/data/graphs.php?id=' + props.id + '&width=120&height=80"/><br/>';
				info+= '<a href="https://meetjestad.net/data/sensors_recent.php?sensor=' + props.id + '&limit=50" target="_blank">go to data</a>';
				info+= '<br/>';
				info+= '<a href="https://meetjestad.net/node/' + props.id + '" target="_blank">sensor status</a>';
				break;
			case 'observation':
				info = '<h2 style="margin-bottom:0px;">' + props.naam_nl + '</h2>';
				info+= '<i>' + props.naam_la + '</i>';
				info+= '<table>';
				info+= '<tr><td>Datum</td><td>' + props.datum + '</td></tr>';
				info+= '<tr><td>Waarneming</td><td>' + props.waarneming + '</td></tr>';
				info+= '</table>';
				info+= '<img src="https://meetjestad.net/flora/images/' + props.afbeelding + '" width="150"/><br/>';
				info+= '<i>' + props.notitie + '</i>';
				break;
			case 'story':
				info = '<h2 style="margin-bottom:0px;">' + props.title + '</h2>';
				info+= props.narrative;
				break;
			default:
		}
		// Offset the popup so it points at the middle of the marker not the tip
		popup.setOffset([10, -60]);
		popup.show(coord, info);
	}
}
