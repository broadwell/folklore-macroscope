var runningOnETK = 1;

function padWithZeroes(inputVal, desiredLength) {

  var inputString = inputVal + "";

  while (inputString.length < desiredLength) {
    inputString = "0" + inputString;
  }
  return inputString;
}

function tileURL(template) {

  return function(c) {

    return template.replace(/{(.)}/g, function(s, v) {
//        console.log("Z: " + c.zoom + ", X: " + c.row + ", Y: " + c.column);
      switch(v) {
        case "Z": return "L" + padWithZeroes(c.zoom.toString(10),2);
        case "X": return "R" + padWithZeroes(c.row.toString(16),8);
        case "Y": return "C" + padWithZeroes(c.column.toString(16),8);
      }
      return v;
    });
  };
}

function zeroFill (number, width) {
  width -= number.toString().length;
  if (width > 0) {
    return new Array( width + (/\./.test( number ) ? 2 : 1) ).join( '0' ) + number;
  }
  return number + ""; // always return a string
};

function esriUrlFunction (baseUrl, fileType) {
  return function(tileCoord) {
//          console.log("tileCoord[0] is " + tileCoord[0] + " tileCoord[1] is " + tileCoord[1] + " tileCoord[2] is " + tileCoord[2]);
    var L = zeroFill(tileCoord[0],2);
    var C = zeroFill(tileCoord[1].toString(16),8);
    var R = zeroFill(tileCoord[2].toString(16),8);
    var newUrl = baseUrl + "/L" + L + "/R" + R + "/C" + C + "." + fileType;
//          console.log(newUrl);
    return newUrl;
  };
};

if (runningOnETK) {
  var denmarkUrlFunction = esriUrlFunction('http://etkspace.scandinavian.ucla.edu/tiles/Denmark', 'png');
  var osmUrlFunction = esriUrlFunction('http://etkspace.scandinavian.ucla.edu/tiles/OSM', 'png');
  var aerialUrlFunction = esriUrlFunction('http://etkspace.scandinavian.ucla.edu/tiles/FugroAerial', 'jpg');
} else {
  var denmarkUrlFunction = esriUrlFunction('http://localhost/~broadwell/maps/tiles/Denmark', 'png');
  var osmUrlFunction = esriUrlFunction('http://localhost/~broadwell/maps/tiles/OSM', 'png');
  var aerialUrlFunction = esriUrlFunction('http://localhost/~broadwell/maps/tiles/FugroAerial', 'jpg');
}

var map;
var historicalTiles;
var aerialTiles;
var showStoryTexts = 1;
var showPlots = 1;
var pointsMarkers = [];

//var helpText = "<p><b>ElfYelp:</b></p>
var helpText = '<p><img src="images/ElfYelp.png" width="300px" alt="ElfYelp logo"></p>';

helpText += "<p>Red dots represent places mentioned in stories in the Tang " +
            "Kristensen collection. A darker shade of red means more stories mention that place.</p>" +
            "<p>Yellow circles are representative regions of the geo-topics calculated for the " +
            "collection. Click on any yellow circle for a summary of the topics in that area, " +
            "and also to highlight other representative areas with similar topic mixtures, " +
            "if they exist.</p>";

function showHelp() {
  document.getElementById('infopane').innerHTML = helpText;
}

function initialize() {

  var topicWords = {};
  var topicLabels = {};

  var highlightedFeature;
  var emphasizedFeatureIDs = []; // For pointing out related regions

  var regionFeatures = {}; // Region features by internal (JSON) ID

  var attribution = new ol.control.Attribution({
    collapsible: false
  });

  // Terrible hack to get all 200 DK lowboards maps to display
//  var DK_lowboards_array = Array(200).fill().map((x,i)=>i);
  var DK_lowboards_array = Array.apply(null, {length: 200}).map(Number.call, Number);
  var DK_lowboards_layers = DK_lowboards_array.join(',');

  map = new ol.Map({
    target: document.getElementById('map'),
    controls: ol.control.defaults({attribution: false}).extend([
      attribution,
      new ol.control.ScaleLine(),
      new ol.control.MousePosition({
        projection: 'EPSG:4326'
      }),
      new ol.control.Attribution /** @type {olx.control.AttributionOptions} */ ({
        collapsible: false
      })
    ]),
//    attributions: [
//      new ol.Attribution({
//        html: '© ' + '<a href="http://fugro.openstreetmap.dk">Fugro Aerial Mapping A/S</a>'
//      }),
//      ol.source.OSM.ATTRIBUTION // Should be wwww.openstreetmap.org/copyright
//    ],
    layers: [
/*          new ol.layer.Tile({
            title: 'World streets',
            type: 'base',
            visible: true,
            source: new ol.source.XYZ({projection: 'EPSG:102100', url: 'http://marinus.library.ucla.edu:6080/arcgis/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}'})
          }), */
      new ol.layer.Tile({
        title: 'OSM Basemap',
        visible: true,
        source: new ol.source.XYZ({
	        projection: 'EPSG:3857', 
          tileUrlFunction: osmUrlFunction,
          attributions: [
	          new ol.Attribution({
              html: '© ' + '<a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            })
	        ]
        })
      }),
      new ol.layer.Tile({
        title: 'Denmark',
//        type: 'base',
        visible: true,
        source: new ol.source.XYZ({projection: 'EPSG:3857', tileUrlFunction: denmarkUrlFunction})
      }),
/*      new ol.layer.Tile({
        title: 'DK_lowboards',
        visible: true,
        minResolution: .1,
        maxResolution: 10,
        source: new ol.source.TileWMS({
          url: 'http://marinus.library.ucla.edu:6080/arcgis/services/Scandinavian_hGIS/DK_lowboards/MapServer/WMSServer',
          params: {'LAYERS': DK_lowboards_layers, 'TILED': false, 'FORMAT': 'image/png'}
        })
      }),*/
      new ol.layer.Tile({
        title: 'DK_lowboards',
        visible: true,
        minResolution: .1,
        maxResolution: 10,
        source: new ol.source.TileArcGISRest({
          projection: 'EPSG:3857',
//          tileSize: 4096,
          url: 'http://marinus.library.ucla.edu:6080/arcgis/rest/services/Scandinavian_hGIS/DK_lowboards/MapServer',
//          tilePixelRatio: .0625,
          params: {'LAYERS': DK_lowboards_layers, 'TILED': false, 'FORMAT': 'image/png32', 'ANTIALIASING': 'on'}
        })
      }),
      new ol.layer.Tile({
        title: 'Aerial map',
        visible: false,
        source: new ol.source.XYZ({
          projection: 'EPSG:3857', 
          tileUrlFunction: aerialUrlFunction,
          attributions: [
            new ol.Attribution({
              html: '© ' + '<a href="http://fugro.openstreetmap.dk">Fugro Aerial Mapping A/S</a>'
            })
          ]
        })
      })
    ],
    view: new ol.View({
      center: ol.proj.transform([11.55, 56.25], 'EPSG:4326', 'EPSG:102100'),
      zoom: 7
    })
  });

  var layerSwitcher = new ol.control.LayerSwitcher({
    tipLabel: 'Legend' // Optional label for button
  });
  map.addControl(layerSwitcher);

  showHelp();

  var basicMarker = new ol.style.Circle({
    radius: 2,
    fill: new ol.style.Fill({
      color: 'rgba(128, 24, 24, 0.1)'
    })
    //stroke: new ol.style.Stroke({color: 'black', width: 1})
  });

  var centroidMarker = new ol.style.Circle({
    radius: 9,
    fill: new ol.style.Fill({
      color: 'rgba(255, 153, 0, 0.5)'
    })
    //stroke: new ol.style.Stroke({color: 'rgba(0, 0, 0, 0.8)', width: 1})
  });
          

  var centroidHighlight = new ol.style.Circle({
    radius: 15,
    fill: new ol.style.Fill({
      color: 'rgba(255, 100, 0, 0.9)'
    }),
    stroke: new ol.style.Stroke({color: 'rgba(0, 0, 0, 1)', width: 2})
  });

  var textFill = new ol.style.Fill({
    color: '#fff'
  });
  
  var textStroke = new ol.style.Stroke({
    color: 'rgba(0, 0, 0, 0.6)',
    width: 3
  });

  if (runningOnETK)
    pointsStr = 'http://etkspace.scandinavian.ucla.edu/maps/ElfYelp/GetPoints.php?datadir=' + dataDir;
  else
    pointsStr = 'http://localhost/~broadwell/maps/ElfYelp/GetPoints.php?datadir=' + dataDir;

  var storyMarkers = new ol.layer.Vector({
    title: "ElfYelp places",
    source: new ol.source.Vector({
      url: pointsStr,
      format: new ol.format.GeoJSON({
        extractStyles: false
      })
    }),
    style: function(feature) {
      var pointsStyle = [new ol.style.Style({
        image: basicMarker
      })];
      return pointsStyle;
    }
  });

  map.addLayer(storyMarkers);

  if (runningOnETK)
    centroidsStr = 'http://etkspace.scandinavian.ucla.edu/maps/ElfYelp/GetElfData.php?datadir=' + dataDir;
  else
    centroidsStr = 'http://localhost/~broadwell/maps/ElfYelp/GetElfData.php?datadir=' + dataDir;

  var centroidMarkers = new ol.layer.Vector({
    title: "ElfYelp geo-topic centers",
    source: new ol.source.Vector({
      url: centroidsStr,
      format: new ol.format.GeoJSON({
        extractStyles: false
      })
    }),
    style: function(feature) {
      var pointsStyle = [new ol.style.Style({
        image: centroidMarker
      })];
      return pointsStyle;
    }
  });

  map.addLayer(centroidMarkers);

  centroidMarkers.on('postcompose', function() {

    var centroidFeatures = centroidMarkers.getSource().getFeatures();
    for (var r=0; (r < centroidFeatures.length); r++) {
      var rID = centroidFeatures[r].get('regionID');
      regionFeatures[rID] = centroidFeatures[r];
    }

  });

  map.on('click', function(evt) {
    var feature = map.forEachFeatureAtPixel(evt.pixel,
      function(feature, layer) {
        return feature;
    });
    if (feature) {

      var regionID = feature.get("regionID");
      var keywords = feature.get("keywords");
      var placeNames = feature.get("placeNames");
      var stories = feature.get("stories");
      //console.log("region " + regionID + " clicked");
        
      var geometry = feature.getGeometry();
      var coord = geometry.getCoordinates();
      var lonlat = ol.proj.transform(coord, 'EPSG:3857', 'EPSG:4326');
      var template = '{y}N, {x}E';
      var coordString = ol.coordinate.format(lonlat, template, 6);

      if (typeof regionID !== 'undefined') {
          
        for (var l=0; (l < pointsMarkers.length); l++) { 
          map.removeLayer(pointsMarkers[l]);
        }
        pointsMarkers = [];
         
        if (typeof highlightedFeature !== 'undefined') {
          highlightedFeature.setStyle(function(feature) {
            var pointsStyle = [new ol.style.Style({
              image: centroidMarker
            })];
            return pointsStyle;
          });
        }

        //console.log("There are " + emphasizedFeatureIDs.length + " emphasized features");
        for (var e=0; (e < emphasizedFeatureIDs.length); e++) {
          var eID = emphasizedFeatureIDs[e];
          eFeature = regionFeatures[eID];
          eFeature.setStyle(function(feature) {
            var pointsStyle = [new ol.style.Style({
              image: centroidMarker
            })];
            return pointsStyle;
          });
        }
        emphasizedFeatureIDs = [];

        feature.setStyle(function(feature) {
          var pointsStyle = [new ol.style.Style({
            image: centroidHighlight
          })];
          return pointsStyle;
        });

        highlightedFeature = feature;
 
        var blurbtext = "<b>Region " + regionID + "</b>, centered at " + coordString + "<br>";

        var regionTopics = feature.get("topics");
        var similarRegions = feature.get("similarRegions");
        blurbtext += "<br><b>Regions with similar topic mixtures:</b><br>";

        for (var r=0; (r < similarRegions.length); r++) {
          var rID = similarRegions[r]['regionID'];
          var sScore = similarRegions[r]['similarityScore'];
//          console.log("similar region with ID " + rID + " is " + sScore + " similar");

          if (sScore < .01)
            break;
          if (r > 10)
            break;

          relatedFeature = regionFeatures[rID];
            
          if (sScore >= .1) {
            emphOpacity = '0.8';
          } else if ((sScore < .1) && (sScore >= .075)) {
            emphOpacity = '0.5';
          } else {
            emphOpacity = '0.3';
          }

          relatedFeature.set("emphOpacity", emphOpacity);
 /* 
          var centroidEmphasis = new ol.style.Circle({
            radius: 13,
            fill: new ol.style.Fill({
              color: 'rgba(255, 215, 0,' + emphOpacity + ')'
            }),
            stroke: new ol.style.Stroke({color: 'rgba(0, 0, 0, 0.9)', width: 1})
          });*/
          
          function highlightStyle(feature) {
            var eOpacity = this.get("emphOpacity");
            var style = [new ol.style.Style({
              image: new ol.style.Circle({
                radius: 13,
                fill: new ol.style.Fill({
                  color: 'rgba(255, 215, 0,' + eOpacity + ')'
                }),
                stroke: new ol.style.Stroke({color: 'rgba(0, 0, 0, ' + eOpacity + ')', width: 1})
              }),
              text: new ol.style.Text({
                text: this.get("regionID"),
                fill: textFill,
                stroke: textStroke
              })
            })];
            return style;
          }
          relatedFeature.setStyle(highlightStyle);
/*
          relatedFeature.setStyle(function(feature) {
            console.log(feature.getProperties);
            var pointsStyle = [new ol.style.Style({
              image: centroidEmphasis,
              text: new ol.style.Text({
                text: rID,
                fill: textFill,
                stroke: textStroke
              })
            })];
            return pointsStyle;
          });
*/

          emphasizedFeatureIDs.push(rID);

          //console.log(centroidSource);
          //console.log(centroidSource.getFeatures());
          //centroidMarkers.getSource().getFeatureByID();
          blurbtext += "Region " + similarRegions[r]['regionID'] + ", Hellinger distance: " + similarRegions[r]['similarityScore'] + "<br>";
        }

        blurbtext += "<br><b>Geo-topics around this area with prominent labels:</b>";
        for (var t=0; (t < regionTopics.length); t++) {
          var regionTopicID = regionTopics[t]["topicID"];
          var regionTopicLabel = topicLabels[regionTopicID];
          blurbtext += "<br><b>" + regionTopicLabel + " (" +
          regionTopics[t]["topicPercent"] + "%):</b><br>";
          for (var w=0; ((w < 15) && (w < topicWords[regionTopicID].length)); w++) {
            blurbtext += topicWords[regionTopicID][w]["word"] + " (" +
                         topicWords[regionTopicID][w]["wordPercent"] + ")<br>";
          }
        }
      
        var infopane = document.getElementById('infopane');
        infopane.innerHTML = blurbtext; 

//        var ce = highlightedFeature.getGeometry().getCoordinates();
        var fc = highlightedFeature.getGeometry().getCoordinates();
        var featureCircle = new ol.geom.Circle(fc, 15000);
        var featureExtent = featureCircle.getExtent();
        var ce = ol.extent.applyTransform(featureExtent, ol.proj.getTransform('EPSG:3857', 'EPSG:4326'));

        document.getElementById("storyinfo").innerHTML = "<p><b>Counting keywords in the selected region...</b></p>";

        if (runningOnETK)
          var areaQuery = 'http://etkspace.scandinavian.ucla.edu/maps/scope/FindKeywordsInBox.php?lat1=' + ce[1] + '&lon1=' + ce[0] + '&lat2=' + ce[3] + '&lon2=' + ce[2] + '&max=10';
        else
          var areaQuery = 'http://localhost/~broadwell/maps/scope/FindKeywordsInBox.php?lat1=' + ce[1] + '&lon1=' + ce[0] + '&lat2=' + ce[3] + '&lon2=' + ce[2] + '&max=10';


        $.getJSON(areaQuery, function(result) {
          document.getElementById("storyinfo").innerHTML = '<h3 class="sub-header">Information about the selected region</h3><p><b>Region bounding box:</b><br>Bottom left corner: ' + ce[1] + "N, "  + ce[0] + "E<br>Top right corner: " + ce[3] + "N, " + ce[2] + "E</p><p>" + result.raw + "</p><p>" + result.rfipf + "</p><p>" + result.normalized;

          var pointsSource = new ol.source.Vector();

          pointsSource.on('addfeature', function(event) {
            var geometry = event.feature.getGeometry();
            var coords = geometry.getCoordinates();
  
            var lonlat = ol.proj.transform(coords, 'EPSG:4326', 'EPSG:3857');
            geometry.setCoordinates(lonlat);
            event.feature.set('geometry', geometry);
          });

          pointsSource.addFeatures((new ol.format.GeoJSON({extractStyles: false})).readFeatures(result.points));

          var placeDot = new ol.style.Circle({
            radius: 3,
            fill: new ol.style.Fill({
              color: 'red'
            }),
            stroke: new ol.style.Stroke({color: 'black', width: 1})
          });

          var pointsLayer = new ol.layer.Vector({
            source: pointsSource,
            title: "Story places",
            style: function(feature) {
              var pointsStyle = [new ol.style.Style({
                image: placeDot 
              })];
            return pointsStyle;
            }
          });

          for (var l=0; (l < pointsMarkers.length); l++) { 
            map.removeLayer(pointsMarkers[l]);
          }
          pointsMarkers = [];
          map.addLayer(pointsLayer);
          pointsMarkers.push(pointsLayer);

        });
      }
      // This is only for when TrollFinder markers are clicked
      if (typeof stories !== 'undefined') {

        var allStories = stories.split(" ");
        var uniqueStories = [];

        for (var i=0; i < allStories.length; i++) {
          if ($.inArray(allStories[i], uniqueStories) == -1) {
            uniqueStories.push(allStories[i]);
          }
        }

        var uniqueStoriesString = uniqueStories.join("-");

        var storyURL = "http://etkspace.scandinavian.ucla.edu/maps/witchhunter/GetStories.php?stories=" + uniqueStoriesString;
        $.getJSON( storyURL, function(d) {
          var storyDetails = '<h3 class="sub-header">Story relations for the selected place</h3><p><b>Place coordinates: </b>' + coordString + '</p><p><b>Place names: </b>' + placeNames + '</p>';
          for (var j = 0; j < d.stories.length; j++) {
            storyDetails += "<p><b>Story " + d.stories[j].story_id + ":</b> " + d.stories[j].pub_info + "<br>" + d.stories[j].collection_details + "<br>" + d.stories[j].places_mentioned + "<br><b>ETK index:</b> " + d.stories[j].etk_index + "<br><b>Danish publication text:</b> " + d.stories[j].danish_publication + "</p>";
          }
        //var markertext = '<h3 class="sub-header">Story relations for the selected place</h3><p><b>Place coordinates: </b>' + coordString + '</p><p><b>Place names: </b>' + placeNames + '</p>';

          var storytexts = document.getElementById('storyinfo');
          storyinfo.innerHTML = storyDetails;
        });
      }
    }
  });

  map.on('pointermove', function(e) {
    var pixel = map.getEventPixel(e.originalEvent);
    var hit = map.hasFeatureAtPixel(pixel);
    map.getTarget().style.cursor = hit ? 'pointer' : '';
  });

  /* Do this last, so it loads in "the background" */ 

  if (runningOnETK)
    var wordsURL = "http://etkspace.scandinavian.ucla.edu/maps/ElfYelp/GetTopicWords.php?datadir=" + dataDir; 
  else
    var wordsURL = "http://localhost/~broadwell/maps/ElfYelp/GetTopicWords.php?datadir=" + dataDir; 

  $.getJSON( wordsURL, function(d) {
    for (var j = 0; j < d["topicWords"].length; j++) {
      var tid = d["topicWords"][j]["topicID"];
      var tlabel = d["topicWords"][j]["topicLabel"];
      topicLabels[tid] = tlabel;
      topicWords[tid] = d["topicWords"][j]["words"];
    }
  });

}
