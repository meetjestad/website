function focusMapOnData(map, data) {
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
//	heatmap.changed();
	map.renderSync();
}

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
}

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

function drawHeatmap(context, map, data, radius = 100, legendId) {
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
	
	// determine scale
	var p1 = ol.proj.transform(map.getCoordinateFromPixel([0,0]), 'EPSG:900913', 'EPSG:4326');
	var p2 = ol.proj.transform(map.getCoordinateFromPixel([100,100]), 'EPSG:900913', 'EPSG:4326');
	var r = Math.round(radius*Math.sqrt(100*100)/distance(p1, p2));
	
	// calculate inverse distance weighting factors, see http://gisgeography.com/inverse-distance-weighting-idw-interpolation/
	for(var f=0;f<features.length;f++) {
		var sensorPos = ol.proj.transform(features[f].geometry.coordinates, 'EPSG:4326', 'EPSG:900913');
		var sensorPix = map.getPixelFromCoordinate(sensorPos);
		var sensorX = Math.round(scale*(sensorPix[0] + delta[0]));
		var sensorY = Math.round(scale*(sensorPix[1] + delta[1]));
		for (var y=Math.max(0, sensorY-r); y<Math.min(height, sensorY+r); y++) {
			for (var x=Math.max(0, sensorX-r); x<Math.min(width, sensorX+r); x++) {
				var i = y*width + x;
				var d = dist(x, y, sensorX, sensorY);
				if (d < 1.0) d = 1.0;
				if (d < r) {
					A[i]+= features[f].properties.temperature / Math.pow(d, 10);
					B[i]+= 1.0 / Math.pow(d, 10);
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
	Tmin = Math.round(100*Tmin)/100.0;
	Tmax = Math.round(100*Tmax)/100.0;
	
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
	
	if (legendId) {
		// draw temperature scale
		var legendElem = document.getElementById(legendId);
		legendElem.innerHTML = '<div style="display:inline-block;" id="Tmax"></div><br/><div style="display:inline-block; padding-left:10px;"><canvas style="width:10px; height:200px;" id="Tscale"/></div><br/><div style="display:inline-block;" id="Tmin"></div>';
		var Tcanvas = document.getElementById('Tscale');
		
		var Tcontext = Tcanvas.getContext('2d');
		var Timg = Tcontext.getImageData(0,0,Tcanvas.width,Tcanvas.height)
		for (var x=0;x<Tcanvas.width;x++) for (var y=0;y<Tcanvas.height;y++) {
			var i = 4*(y*Tcanvas.width+x);
			var T = 0.7*y/Tcanvas.height;
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
}
