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

var helpText = "<p><b>WitchHunter:</b><br>Selecting a story category from the list at left will produce map visualizations of the " +
               "places mentioned in those stories. Subsequently selecting a place marker on the map will display " +
               "the texts of all stories from the category that mention the selected place.</p>" +
               "<p><b>TrollFinder:</b><br>Dragging from any point on the map while holding the shift key will draw a bounding box. " +
               "Once the box is created, a spatial database query will execute, eventually returning ranked lists of " +
               "story keywords that co-occur with the names of places in the enclosed region.</p>";

function showHelp() {
  document.getElementById('infopane').innerHTML = helpText;
}

var boxLayer;
var boxMarkers = [];

function initialize() {

  var attribution = new ol.control.Attribution({
    collapsible: false
  });

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
        type: 'base',
        visible: true,
        source: new ol.source.XYZ({projection: 'EPSG:3857', tileUrlFunction: denmarkUrlFunction})
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
      center: ol.proj.transform([10.487695, 56.1], 'EPSG:4326', 'EPSG:102100'),
      zoom: 7
    })
  });

  var layerSwitcher = new ol.control.LayerSwitcher({
    tipLabel: 'Legend' // Optional label for button
  });
  map.addControl(layerSwitcher);

//    .url(tileURL("http://localhost/~broadwell/etkSpace/Documents/tiles/FugroAerial/{Z}/{X}/{Y}.jpg"));

  document.getElementById('infopane').innerHTML = helpText;

  // a DragBox interaction used to select features by drawing boxes
  var dragBox = new ol.interaction.DragBox({
    condition: ol.events.condition.shiftKeyOnly,
    style: new ol.style.Style({
      stroke: new ol.style.Stroke({
        color: [255, 0, 0, 2],
        width: 2
      })
   })
  });

  dragBox.on('boxend', function(e) {
 
    var boxGeom = dragBox.getGeometry();

    var boxExtent = boxGeom.getExtent();
    var ce = ol.extent.applyTransform(boxExtent, ol.proj.getTransform('EPSG:3857', 'EPSG:4326'));

    var polyFeature = new ol.Feature(boxGeom);

    var vectorSource = new ol.source.Vector({
      projection: 'EPSG:4326'
    });
    vectorSource.addFeature(polyFeature);
    var vectorLayer = new ol.layer.Vector({
      source: vectorSource,
      title: "TrollFinder box",
      style: new ol.style.Style({
        fill: new ol.style.Fill({
          color: 'rgba(255, 255, 255, 0.2)'
        }),
        stroke: new ol.style.Stroke({
          color: [255, 0, 0, 2],
          width: 2
        })
      })
    });
    map.addLayer(vectorLayer);
    boxLayer = vectorLayer;

    document.getElementById("infopane").innerHTML = "<p>Counting keywords in the specified region...</p>";

    queryStr = 'http://etkspace.scandinavian.ucla.edu/maps/scope/FindKeywordsInBox.php?lat1=' + ce[1] + '&lon1=' + ce[0] + '&lat2=' + ce[3] + '&lon2=' + ce[2] + '&max=10';

    $.getJSON(queryStr,
      function(result) {
        document.getElementById("infopane").innerHTML = "<p><b>Region bounding box:</b><br>Bottom left corner: " + ce[1] + "N, "  + ce[0] + "E<br>Top right corner: " + ce[3] + "N, " + ce[2] + "E</p><p>" + result.raw + "</p><p>" + result.rfipf + "</p><p>" + result.normalized;
   
        var pointsSource = new ol.source.Vector();

	pointsSource.on('addfeature', function(event) {
	  //console.log("points layer adding feature");
          var geometry = event.feature.getGeometry();
          var coords = geometry.getCoordinates();
	  //console.log(coords);
          var lonlat = ol.proj.transform(coords, 'EPSG:4326', 'EPSG:3857');
          geometry.setCoordinates(lonlat);
	  event.feature.set('geometry', geometry);
	  //console.log(lonlat);
        });

	pointsSource.addFeatures((new ol.format.GeoJSON({extractStyles: false})).readFeatures(result.points));

        //console.log(geojsonObject);

        var image = new ol.style.Circle({
	  radius: 3,
          fill: new ol.style.Fill({
            color: 'red'
          }),
          stroke: new ol.style.Stroke({color: 'black', width: 1})
        });

        var pointsLayer = new ol.layer.Vector({
          source: pointsSource,
          title: "TrollFinder places",
          style: function(feature) {
            var pointsStyle = [new ol.style.Style({
	      image: image
            })];
	    return pointsStyle;
	  }
        });
        
        for (var l=0; (l < boxMarkers.length); l++) {
          map.removeLayer(boxMarkers[l]);
        }
        boxMarkers = []
	boxMarkers.push(pointsLayer);
        map.addLayer(pointsLayer);

    });

  });

  // clear selection when drawing a new box and when clicking on the map
  dragBox.on('boxstart', function(e) {
    map.removeLayer(boxLayer);
    for (var l=0; (l < boxMarkers.length); l++) {
      map.removeLayer(boxMarkers[l]);
    }
    boxMarkers = []
    //selectedFeatures.clear();
  });

  map.addInteraction(dragBox);

  map.on('click', function(evt) {
    var feature = map.forEachFeatureAtPixel(evt.pixel,
      function(feature, layer) {
        return feature;
    });
    if (feature) {
	    
      var refStories = feature.get("stories");
      var placeInfo = feature.get("places");
      var placeNames = feature.get("placeNames");
      var keywords = feature.get("keywords");
      var indexName = feature.get("index");

      var geometry = feature.getGeometry();
      var coord = geometry.getCoordinates();
      var lonlat = ol.proj.transform(coord, 'EPSG:3857', 'EPSG:4326');
      var template = '{y}N, {x}E';
      var coordString = ol.coordinate.format(lonlat, template, 6);

      // This is only for when WitchHunter markers are clicked
      if (typeof indexName !== 'undefined') {
        map.removeLayer(boxLayer);
        for (var l=0; (l < boxMarkers.length); l++) {
          map.removeLayer(boxMarkers[l]);
        }
        boxMarkers = []

        var storiesWord = " stories ";
        var mentionWord = " mention ";
        var freq = feature.get("count");
        if (freq == 1) {
          storiesWord = " story ";
          mentionWord = " mentions ";
        }

        var blurbtext = "<p>" + freq + storiesWord + 'in the category "' + indexCategories[indexName] + '"' + mentionWord + 'this place. See below the map for their texts and metadata.</p><p><b>Place ccoordinates</b><br>' + coordString + "</p><p><b>Story ID => Place name (place ID)</b><br>" + placeInfo + "</p>";

        var infopane = document.getElementById('infopane');
        infopane.innerHTML = blurbtext; 
        document.getElementById('storytexts').innerHTML = "";
        document.getElementById('markerpane').innerHTML = "";
      }
      // This applies whether a WitchHunter or TrollFinder marker is clicked
      if (typeof refStories !== 'undefined') {

        var allStories = refStories.split(" ");
        var uniqueStories = [];

        for (var i=0; i < allStories.length; i++) {
            if ($.inArray(allStories[i], uniqueStories) == -1) {
            uniqueStories.push(allStories[i]);
          }
        }  

        var uniqueStoriesString = uniqueStories.join("-");

    //  var storyURL = "http://localhost/~broadwell/maps/witchhunter/GetStories.php?stories=" + uniqueStoriesString; 
        var storyURL = "http://etkspace.scandinavian.ucla.edu/maps/witchhunter/GetStories.php?stories=" + uniqueStoriesString;
        $.getJSON( storyURL, function(d) {
          var storyDetails = '<h3 class="sub-header">Story texts for most recently selected marker</h3>';
          for (var j = 0; j < d.stories.length; j++) {
            storyDetails += "<p><b>Story " + d.stories[j].story_id + ":</b> " + d.stories[j].pub_info + "<br>" + d.stories[j].collection_details + "<br>" + d.stories[j].places_mentioned + "<br><b>ETK index:</b> " + d.stories[j].etk_index + "<br><b>Danish publication text:</b> " + d.stories[j].danish_publication + "</p>";
          }
      
          var storytexts = document.getElementById('storytexts');
          storytexts.innerHTML = storyDetails;
        });
      }
      
      // This is only for when TrollFinder markers are clicked
      if (typeof keywords !== 'undefined') {
        var markertext = '<h3 class="sub-header">Information about the selected marker</h3><p><b>Place coordinates: </b>' + coordString + '</p><p><b>Place names: </b>' + placeNames + '</p><p><b>Keywords associated with this place: </b>' + keywords + '</p>';
        var markerpane = document.getElementById('markerpane');
        markerpane.innerHTML = markertext;
      }

    }
  });

  map.on('pointermove', function(e) {
    var pixel = map.getEventPixel(e.originalEvent);
    var hit = map.hasFeatureAtPixel(pixel);
    map.getTarget().style.cursor = hit ? 'pointer' : '';
  });

}

var itemMarkers = [];
var heatMaps = [];

var styleCache = {};
var markerStyle = function(feature) {
  var count = feature.get('count');
  var itemName = feature.get('index');
  var indexColor = indexColors[itemName];
  var height = 6 * count; // * map.getView().getZoom();
  if ((itemName in styleCache) && (height in styleCache[itemName])) {
    var style = styleCache[itemName][height];
  } else {
    styleCache[itemName] = {};
    var style = [new ol.style.Style({
      image: new ol.style.Icon(/** @type {olx.style.IconOptions} */ ({
        anchor: [.5, 0],
        anchorXUnits: 'fraction',
        anchorYUnits: 'pixels',
        anchorOrigin: 'bottom-left',
        snapToPixel: false,
        size: [5, height],
        opacity: 0.8,
        src: 'bootstrap/img/icon2.png'
     }))

    })];
    styleCache[itemName][height] = style;
  }
  return style;
};


function drawMarkers(itemName) {

//  queryStr = 'http://localhost/~broadwell/maps/witchhunter/GetIndexPoints.php?itemName=' + itemName;
  queryStr = 'http://etkspace.scandinavian.ucla.edu/maps/witchhunter/GetIndexPoints.php?itemName=' + itemName;

  var indexHeatmap = new ol.layer.Heatmap({
    source: new ol.source.Vector({
      url: queryStr,
      format: new ol.format.GeoJSON({
        extractStyles: false
      })
    }),
    blur: 15,
    radius: 7
  });

  indexHeatmap.getSource().on('addfeature', function(event) {
    var count = event.feature.get('count');
    event.feature.set('weight', 5 + count * 2);
  });

  map.addLayer(indexHeatmap);
  heatMaps[itemName] = indexHeatmap;

  var newMarkers = new ol.layer.Vector({
    title: itemName,
    source: new ol.source.Vector({
      url: queryStr,
      format: new ol.format.GeoJSON({
        extractStyles: false
      })
    }),
    style: markerStyle
  });

  map.addLayer(newMarkers);

  itemMarkers[itemName] = newMarkers;

}

function indexClicked(cbox) {
  var indexName = cbox.id;
  if (cbox.checked == true) {
  	var textColor = getContrastYIQ(indexColors[indexName]);
    $(cbox).parent().find("label").css("background-color", indexColors[indexName]);
    $(cbox).parent().find("label").css("color", textColor);
    //drawVectors(indexName);
    //loadGraphs(indexName);
    drawMarkers(indexName);
  } else {
    $(cbox).parent().find("label").css("background-color", "#f5f5f5");
    $(cbox).parent().find("label").css("color", "#333");
    document.getElementById('storytexts').innerHTML = "";
    document.getElementById('markerpane').innerHTML = "<p></p>";
    //document.getElementById('infopane').innerHTML = helpText;
    if (indexName in itemMarkers) {
      map.removeLayer(itemMarkers[indexName]);
      delete itemMarkers[indexName];
    }
    if (indexName in heatMaps) {
      map.removeLayer(heatMaps[indexName]);
    }
    //map.removeLayer(boxLayer);
  }
}

function getContrastYIQ(hexcolor){
	var r = parseInt(hexcolor.substr(1,2),16);
	var g = parseInt(hexcolor.substr(3,2),16);
	var b = parseInt(hexcolor.substr(5,2),16);
	var yiq = ((r*299)+(g*587)+(b*114))/1000;
	return (yiq >= 128) ? '#000000' : '#ffffff';
}

indexColors = [];

indexColors['ETK_churches'] = "#D8BF92";
indexColors['ETK_cunningfolk'] = "#FFFF00"; 
indexColors['ETK_deathport'] = "#F4A460"; 
indexColors['ETK_devil'] = "#FFD700"; 
indexColors['ETK_elves'] = "#FF8C00"; 
indexColors['ETK_farmtowns'] = "#FF7F50"; 
indexColors['ETK_femalerev'] = "#FF4500"; 
indexColors['ETK_forbears'] = "#FF0000"; 
indexColors['ETK_hauntrev'] = "#FF00FF"; 
indexColors['ETK_heroes'] = "#FF69B4"; 
indexColors['ETK_houses'] = "#B22222"; 
indexColors['ETK_illnesses'] = "#DDA0DD"; 
indexColors['ETK_lightport'] = "#B0C4DE"; 
indexColors['ETK_manorlords'] = "#87CEEB"; 
indexColors['ETK_ministers'] = "#ADFF2F"; 
indexColors['ETK_mound'] = "#90EE90"; 
indexColors['ETK_nisser'] = "#808000"; 
indexColors['ETK_oldagriculture'] = "#6A6ACD"; 
indexColors['ETK_outdoors'] = "#556B2F"; 
indexColors['ETK_parties'] = "#7FFF00"; 
indexColors['ETK_people'] = "#708090"; 
indexColors['ETK_placelegends'] = "#696969"; 
indexColors['ETK_plague'] = "#2F4F4F"; 
indexColors['ETK_religious'] = "#006400"; 
indexColors['ETK_revenantsland'] = "#000080"; 
indexColors['ETK_revenantsplaces'] = "#020202"; 
indexColors['ETK_robbers'] = "#800080"; 
indexColors['ETK_smallkings'] = "#DC143C"; 
indexColors['ETK_strandings'] = "#FF69B4"; 
indexColors['ETK_travmonst'] = "#778899"; 
indexColors['ETK_treasure'] = "#00FA9A"; 
indexColors['ETK_villeinage'] = "#8B0000"; 
indexColors['ETK_waterspirits'] = "#BABAB2"; 
indexColors['ETK_werenight'] = "#CD5C5C"; 
indexColors['ETK_witches'] = "#91C2A8"; 
indexColors['ETK_wivernsetc'] = "#8B4513"; 
/*
indexColors['Informant_1446'] = "#000000";
indexColors['Informant_779'] = "#000000";
indexColors['Informant_454'] = "#000000";
indexColors['Informant_1414'] = "#000000";
indexColors['Informant_1605'] = "#000000";
*/

var indexCategories = [];

indexCategories['ETK_churches'] = "Churches and their construction. Monasteries, holy springs, etc.";
indexCategories['ETK_cunningfolk'] = "Cunning men and women and their activities"; 
indexCategories['ETK_deathport'] = "Death portents"; 
indexCategories['ETK_devil'] = "About the Devil and being in league with him"; 
indexCategories['ETK_elves'] = "Elves"; 
indexCategories['ETK_farmtowns'] = "Legends about farms and towns"; 
indexCategories['ETK_femalerev'] = "Female revenants"; 
indexCategories['ETK_forbears'] = "Our forbears' way of thinking and spiritual life"; 
indexCategories['ETK_hauntrev'] = "Diverse hauntings and conjuring of revenants"; 
indexCategories['ETK_heroes'] = "Giants and their sport"; 
indexCategories['ETK_houses'] = "Houses and life in them"; 
indexCategories['ETK_illnesses'] = "Human and cattle illnesses"; 
indexCategories['ETK_lightport'] = "Lights and portents"; 
indexCategories['ETK_manorlords'] = "Manor lords, ladies, and mistresses"; 
indexCategories['ETK_ministers'] = "Ministers"; 
indexCategories['ETK_mound'] = "Mound dwellers (Hidden folk)"; 
indexCategories['ETK_nisser'] = "Household spirits"; 
indexCategories['ETK_oldagriculture'] = "Concerning earlier agricultural practices"; 
indexCategories['ETK_outdoors'] = "Outdoor life"; 
indexCategories['ETK_parties'] = "Social gatherings and parties"; 
indexCategories['ETK_people'] = "Diverse people"; 
indexCategories['ETK_placelegends'] = "Diverse place legends"; 
indexCategories['ETK_plague'] = "Plague and illnesses"; 
indexCategories['ETK_religious'] = "Religious legends"; 
indexCategories['ETK_revenantsland'] = "Revenants on ill-gotten land"; 
indexCategories['ETK_revenantsplaces'] = "Revenants in diverse places"; 
indexCategories['ETK_robbers'] = "Robbers, murderers and thieves"; 
indexCategories['ETK_smallkings'] = "Small kings and their feuds. Kings. Enemy invasions"; 
indexCategories['ETK_strandings'] = "Strandings"; 
indexCategories['ETK_travmonst'] = "Traveling monsters"; 
indexCategories['ETK_treasure'] = "Legends about treasure"; 
indexCategories['ETK_villeinage'] = "From the time of villeinage"; 
indexCategories['ETK_waterspirits'] = "Water spirits"; 
indexCategories['ETK_werenight'] = "Werewolves and nightmares"; 
indexCategories['ETK_witches'] = "Witches and their sport"; 
indexCategories['ETK_wivernsetc'] = "Wiverns and small creepy-crawlies"; 
/*
indexCategories['Informant_1446'] = "(Ane) Margrete Jensdatter"; 
indexCategories['Informant_779'] = "Jens (Bitte Jens) Kristensen"; 
indexCategories['Informant_454'] = "Jens Peter Pedersen"; 
indexCategories['Informant_1414'] = "Kirsten Marie Pedersdatter"; 
indexCategories['Informant_1605'] = "Peder Johansen";
*/ 
