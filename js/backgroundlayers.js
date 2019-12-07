proj4.defs("EPSG:28992","+proj=sterea +lat_0=52.15616055555555 +lon_0=5.38763888888889 +k=0.9999079 +x_0=155022 +y_0=463015 +ellps=bessel +towgs84=565.417,50.3319,465.552,-0.398957,0.343988,-1.8774,4.0725 +units=m +no_defs");

var projection = ol.proj.get('EPSG:28992');
var projectionExtent = [-285401.92,22598.08,595401.92,903402.0];
var size = ol.extent.getWidth(projectionExtent) / 256;

// generate resolutions and matrixIds arrays for PDOK WMTS
var resolutions = [3440.64, 1720.32, 860.16, 430.08, 215.04, 107.52, 53.76, 26.88, 13.44, 6.72, 3.36, 1.68, 0.84, 0.42]
var matrixIds = new Array(14);
for (var z = 0; z < 15; ++z) matrixIds[z] = 'EPSG:28992:' + z;


var url = 'https://geodata.nationaalgeoregister.nl/luchtfoto/rgb/wmts/Actueel_ortho25/EPSG:28992/';
var tileUrlFunction = function(tileCoord, pixelRatio, projection) {
	var zxy = tileCoord;
	if (zxy[1] < 0 || zxy[2] < 0) return "";
	return url +
		zxy[0].toString()+'/'+ zxy[1].toString() +'/'+
		((1 << zxy[0]) - zxy[2] - 1).toString() +'.png';
};

luchtfoto = new ol.layer.Tile({
	title: 'Aerial',
	type: 'base',
	visible: false,
	source: new ol.source.TileImage({
		attributions: [
			new ol.Attribution({
				html: 'Kaartgegevens: <a href="http://creativecommons.org/licenses/by-nc/3.0/nl/">CC-BY-NC</a> <a href="http://www.pdok.nl">PDOK</a>.'
			})
		],
		projection: 'EPSG:28992',
		tileGrid: new ol.tilegrid.TileGrid({
			origin: [-285401.92,22598.08],
			resolutions: resolutions
		}),
		tileUrlFunction: tileUrlFunction
	}),
});

var topografie = new ol.layer.Tile({
	title: 'Topography',
	type: 'base',
	visible: true,
	source: new ol.source.OSM()
	//~ source: new ol.source.WMTS({
		//~ url: 'http://geodata.nationaalgeoregister.nl/tiles/service/wmts/brtachtergrondkaart',
		//~ layer: 'brtachtergrondkaart',
		//~ attributions: [
			//~ new ol.Attribution({
				//~ html: 'Kaartgegevens: <a href="https://creativecommons.org/licenses/by-sa/4.0/deed.nl">CC-BY-SA</a> <a href="http://www.osm.org">OSM</a> & <a href="http://www.kadaster.nl">Kadaster</a>.'
			//~ })
		//~ ],
		//~ projection: projection,
		//~ matrixSet: 'EPSG:28992',
		//~ format: 'image/png',
		//~ tileGrid: new ol.tilegrid.WMTS({
			//~ origin: ol.extent.getTopLeft(projectionExtent),
			//~ resolutions: resolutions,
			//~ matrixIds: matrixIds
		//~ })
	//~ })
});

var ahn = new ol.layer.Tile({
	title: 'AHN',
	type: 'base',
	visible: false,
	source: new ol.source.WMTS({
		url: 'http://geodata.nationaalgeoregister.nl/tiles/service/wmts/ahn2',
		layer: 'ahn2_05m_ruw',
//		url: 'http://geodata.nationaalgeoregister.nl/tiles/service/wmts/ahn3',
//		layer: 'ahn3_05m_dtm',
		attributions: [
			new ol.Attribution({
				html: 'Kaartgegevens: <a href="http://creativecommons.org/publicdomain/zero/1.0/deed.nl">CC-0</a> <a href="www.ahn.nl">AHN</a>.'
			})
		],
		projection: projection,
		matrixSet: 'EPSG:28992',
		format: 'image/png',
		tileGrid: new ol.tilegrid.WMTS({
			origin: ol.extent.getTopLeft(projectionExtent),
			resolutions: resolutions,
			matrixIds: matrixIds
		})
	}),
});
