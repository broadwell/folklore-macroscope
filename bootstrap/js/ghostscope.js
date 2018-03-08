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

var map, po, svg;
var markers = [];
var historicalTiles;
var aerialTiles;
var showStoryTexts = 1;
var showPlots = 1;

var helpText = "<p><b>GhostScope:</b><br>Selecting a story category from the list at left will display aggregated visualizations of the " +
               "distances, bearings, and frequencies of the place references each storyteller made when telling such a story.</p><p>" +
	       'The visualizations thus place all storytellers at the origin point and attempt to map out the combined "conceptual geography" ' +
               "of the selected story cateogry.</p>" +
               "<p><b>TreasureX:</b><br>Graphics also will appear on the map when a category is selected, indicating the actual sources and " +
	       "targets of place references (arrows) and the proportion of references to each place (bars) in stories that belong to " +
	       "the selected category.</p><p>Subsequently selecting a map marker will display related data and story texts below the map.</p>";

function showHelp() {
  document.getElementById("infopane").innerHTML = helpText;
}

function initialize() {

  po = org.polymaps;  
  
  svg = n$("#map_canvas").add("svg:svg");
/*
  div = document.getElementById("map_canvas");
  g = div.appendChild(po.svg("svg"));
*/ 
  map = po.map()
//    .container(g)
    .container($n(svg))
//    .center({lat: 55.08676, lon: 10.687695})
    .center({lat: 56, lon: 10.49})
    .zoomRange([3, 12])
    .zoom(7)
    .add(po.interact());

//  map.add(po.image().url(tileURL("http://localhost/~broadwell/etkSpace/Documents/tiles/OSM/{Z}/{X}/{Y}.png")));
  map.add(po.image().url(tileURL("http://etkspace.scandinavian.ucla.edu/tiles/OSM/{Z}/{X}/{Y}.png")));

  historicalTiles = po.image()
    .id("historicalMap")
//    .url(tileURL("http://localhost/~broadwell/etkSpace/Documents/tiles/Denmark/{Z}/{X}/{Y}.png"));
    .url(tileURL("http://etkspace.scandinavian.ucla.edu/tiles/Denmark/{Z}/{X}/{Y}.png"));
  map.add(historicalTiles);

  aerialTiles = po.image()
    .id("aerialMap")
//    .url(tileURL("http://localhost/~broadwell/etkSpace/Documents/tiles/FugroAerial/{Z}/{X}/{Y}.jpg"));
      .url(tileURL("http://etkspace.scandinavian.ucla.edu/tiles/FugroAerial/{Z}/{X}/{Y}.jpg"));
//  map.add(aerialTiles);
//  var at = document.getElementById("aerialMap");
//  at.setAttribute("opacity", 0);

  compass = po.compass();
  compass.pan("none");

  map.add(compass);

  document.getElementById('infopane').innerHTML = helpText;

  map.on("mouseout", function() { console.log(map.center()); });

}

function selectIndex(indexName) {

  if (document.getElementById(indexName).checked == false) {
    document.getElementById(indexName).click();
  } else {
    getPoints(false, indexName);
    document.getElementById(indexName).checked = false;
  }
}

itemMarkers = [];
itemVectors = [];

function drawMarkers(itemName) {

//  queryStr = 'http://localhost/~broadwell/maps/witchhunter/GetIndexPoints.php?itemName=' + itemName;
  queryStr = 'http://etkspace.scandinavian.ucla.edu/maps/witchhunter/GetIndexPoints.php?itemName=' + itemName;
  
    var newMarkers = po.geoJson()
      .url(queryStr)
      .id(itemName)
      .clip(false)
      .on("load", function(e) {

  for (var i = 0; i < e.features.length; i++) {

  var f = e.features[i],
      c = f.element,
      count = 1,
      refStories = "",
      placeNames = "";
      
    if (e.features[i].data.geometry) {
      var markerLon = parseFloat(e.features[i].data.geometry.coordinates[0]);
      var markerLat = parseFloat(e.features[i].data.geometry.coordinates[1]);
    }
      
    if (e.features[i].data.properties) {
      count = parseFloat(e.features[i].data.properties.count);
      placeNames = e.features[i].data.properties.places;
      refStories = e.features[i].data.properties.stories;
    } else if (c.attr("count")) {
      count = c.attr("count");
    }

    ncount = count / 20;

    var r = ncount * 100 * Math.pow(2, e.tile.zoom - 8);
/*
  svg.add("svg:radialGradient")
     .attr("id", "gradient" + itemName)
        .add("svg:stop")
          .attr("offset", "0%")
          .attr("stop-color", indexColors[itemName])
          .parent()
             .add("svg:stop")
                  .attr("offset", "100%")
                  .attr("stop-color", "#888888")
                  .attr("stop-opacity", 0);

  d = f.element = po.svg("circle");

  d.setAttribute("r", r);
  d.setAttribute("count", ncount);
  d.setAttribute("opacity", .9);
  d.setAttribute("fill", "url(#gradient"+itemName+")");
  d.setAttribute("transform", c.getAttribute("transform"));

  c.parentNode.replaceChild(d, c);
  */

    g = f.element = po.svg("rect");

    g.setAttribute("width", 4);
    g.setAttribute("height", r);
    g.setAttribute("x", -2);
    g.setAttribute("y", -r);
    g.setAttribute("title", count);
    g.setAttribute("transform", c.getAttribute("transform"));
    g.setAttribute("fill", indexColors[itemName]);
    g.setAttribute("stroke", "black");
    g.setAttribute("lat", markerLat);
    g.setAttribute("lon", markerLon);
    g.setAttribute("freq", count);
    g.setAttribute("index", itemName);
    g.setAttribute("places", placeNames);
    g.setAttribute("stories", refStories);
    g.setAttribute("vector-effect", "non-scaling-stroke");
    g.addEventListener("mouseover", function(evt){  
                        markerMouse(this, evt, "black", 1);  
                       }, false); 
    g.addEventListener("mouseout", function(evt){  
                        markerMouse(this, evt, indexColors[itemName], 1);
                       }, false); 
    g.addEventListener("mousedown", function(evt){  
                        markerClick(this, evt);  
                      }, false); 

    //c.parentNode.appendChild(g);
    c.parentNode.replaceChild(g, c);

  }
  });       

  map.add(newMarkers);
  itemMarkers[itemName] = newMarkers;

}

function lineClick(f, evt) {

  var storiesWord = " stories ";
  var mentionWord = " mention ";
  var freq = f.getAttribute("freq");
  if (freq == 1) {
    storiesWord = " story ";
    mentionWord = " mentions ";
  }

  var refInfo = "";
  var refStories = f.getAttribute("stories").split(" ");
  var refPlacePairs = f.getAttribute("placepairs").split(" ");

  var uniqueStories = [];

  for (var i=0; i < refStories.length; i++) {
    if ($.inArray(refStories[i], uniqueStories) == -1) {
      uniqueStories.push(refStories[i]);
    }
    refInfo += "<br>" + refStories[i] + ": " + refPlacePairs[i];
  }
  
 if (showStoryTexts == 1) {
  var uniqueStoriesString = uniqueStories.join("-");

//  var storyURL = "http://localhost/~broadwell/maps/witchhunter/GetStories.php?stories=" + uniqueStoriesString; 
  var storyURL = "http://etkspace.scandinavian.ucla.edu/maps/witchhunter/GetStories.php?stories=" + uniqueStoriesString;
  $.getJSON( storyURL, function(d) {
    var storyDetails = '<h3 class="sub-header">Story texts for most recently selected marker</h2>';
    for (var j = 0; j < d.stories.length; j++) {
    storyDetails += "<p><b>Story " + d.stories[j].story_id + ":</b> " + d.stories[j].pub_info + "<br>" + d.stories[j].collection_details + "<br>" + d.stories[j].places_mentioned + "<br><b>ETK index:</b> " + d.stories[j].etk_index + "<br><b>Danish publication text:</b> " + d.stories[j].danish_publication + "</p>";
    }
      
    var storytexts = document.getElementById('storytexts');
    storytexts.innerHTML = storyDetails;
  });

 }

  var blurbtext = '<h3 class="sub-header">Place information</h3>' + "<p>" + freq + storiesWord + 'in the category "' + indexCategories[f.getAttribute("index")] + '" collected at ' + f.getAttribute("sourceCoords") + mentionWord + ' place(s) at ' + f.getAttribute("destCoords") + "<br>Bearing: " + f.getAttribute("bearing") + "&deg;<br>Distance: " + f.getAttribute("distance") + " km" + "<br><b>Story: Collection=>Mentioned</b>" + refInfo + "</p>";
  
  //var blurb = "<div class='info_blurb'>" + blurbtext + "</div>";

  var storyinfo = document.getElementById('storyinfo');
  /*infowin.style.width = "250px";
  infowin.style.maxHeight = "200px";
  infowin.style.overflow = "auto";
  infowin.style.left = evt.clientX + "px";
  infowin.style.top = evt.clientY + "px";
  infowin.style.position = 'absolute';
  infowin.style.display = 'block';
  infowin.style.border = '1px solid';*/
                      
  storyinfo.innerHTML = blurbtext; 
  
  /*infowin.addEventListener("mousedown", function(evt){  
                            infowin.innerHTML = "";
                            infowin.style.border = '';
                      }, false); */

}

function markerClick(f, evt) {

  var storiesWord = " stories ";
  var mentionWord = " mention ";
  var freq = f.getAttribute("freq");
  if (freq == 1) {
    storiesWord = " story ";
    mentionWord = " mentions ";
  }

 if (showStoryTexts == 1) {
  var refStories = f.getAttribute("stories").split(" ");
  var uniqueStories = [];
  
  for (var i=0; i < refStories.length; i++) {
    if ($.inArray(refStories[i], uniqueStories) == -1) {
      uniqueStories.push(refStories[i]);
    }
  }
  
  var uniqueStoriesString = uniqueStories.join("-");

//  var storyURL = "http://localhost/~broadwell/maps/witchhunter/GetStories.php?stories=" + uniqueStoriesString; 
  var storyURL = "http://etkspace.scandinavian.ucla.edu/maps/witchhunter/GetStories.php?stories=" + uniqueStoriesString;
  $.getJSON( storyURL, function(d) {
    var storyDetails = '<h3 class="sub-header">Story texts for most recently selected marker</h2>';
    for (var j = 0; j < d.stories.length; j++) {
    storyDetails += "<p><b>Story " + d.stories[j].story_id + ":</b> " + d.stories[j].pub_info + "<br>" + d.stories[j].collection_details + "<br>" + d.stories[j].places_mentioned + "<br><b>ETK index:</b> " + d.stories[j].etk_index + "<br><b>Danish publication text:</b> " + d.stories[j].danish_publication + "</p>";
    }
      
    var storytexts = document.getElementById('storytexts');
    storytexts.innerHTML = storyDetails;
  });
 }

  var blurbtext = '<h3 class="sub-header">Place information</h3>' + "<p>" + freq + storiesWord + 'in the category "' + indexCategories[f.getAttribute("index")] + '"' + mentionWord + 'this place.</p><p><b>Place ccoordinates</b><br>' + f.getAttribute("lat") + "N, " + f.getAttribute("lon") + "E</p><p><b>Story ID => Place name (place ID)</b><br>" + f.getAttribute("places") + "</p>";
  //var blurb = "<div class='info_blurb'>" + blurbtext + "</div>";

  var storyinfo = document.getElementById('storyinfo');
  /*infowin.style.width = "250px";
  infowin.style.maxHeight = "200px";
  infowin.style.overflow = "auto";
  infowin.style.left = evt.clientX + "px";
  infowin.style.top = evt.clientY + "px";
  infowin.style.position = 'absolute';
  infowin.style.display = 'block';
  infowin.style.border = '1px solid';*/
                      
  storyinfo.innerHTML = blurbtext; 
  /*
  infowin.addEventListener("mousedown", function(evt){  
                            infowin.innerHTML = "";
                            infowin.style.border = '';
                      }, false); */
}

function arrowMouse(f, evt, fillcolor, opacity){
  //  var featName = f.getAttribute("feat_name");
  var vectorID = f.getAttribute("index") + "_vector_" + parseInt(f.getAttribute("vectorID"));
  var vector = document.getElementById(vectorID);
  vector.setAttribute("opacity", opacity);
  vector.setAttribute("stroke", fillcolor);
  f.setAttribute("fill", fillcolor);
  f.setAttribute("opacity", opacity);
}

function arrowClick(f, evt){
  lineClick(f, evt);
}

function markerMouse(f, evt, fillcolor, opacity){
  //  var featName = f.getAttribute("feat_name");
  f.setAttribute("fill", fillcolor);
  f.setAttribute("opacity", opacity);
}

function lineMouse(f, evt, linecolor, opacity){
  //  var featName = f.getAttribute("feat_name");
  var arrowID = f.getAttribute("index") + "_arrow_" + parseInt(f.getAttribute("vectorID"));
  //console.log("looking for arrowID: " + arrowID);
  var arrow = document.getElementById(arrowID);
  arrow.setAttribute("opacity", opacity);
  arrow.setAttribute("fill", linecolor);
  f.setAttribute("opacity", opacity);
  f.setAttribute("stroke", linecolor);
}

var vectorData = {};

function drawVectors(itemName) {
  
//  queryStr = 'http://localhost/~broadwell/maps/witchhunter/GetVectors.php?itemName=' + itemName;
  queryStr = 'http://etkspace.scandinavian.ucla.edu/maps/witchhunter/GetVectors.php?itemName=' + itemName;

  var newVectors = po.geoJson()
    .url(queryStr)
    .id(itemName)
    .clip(false)
    .on("load", function(e) {

    for (var i = 0; i < e.features.length; i++) {

    if (e.features[i].data.geometry.type == "LineString") {

      var x1 = e.features[i].data.geometry.coordinates[0][0];
      var y1 = e.features[i].data.geometry.coordinates[0][1];
      var x2 = e.features[i].data.geometry.coordinates[1][0];
      var y2 = e.features[i].data.geometry.coordinates[1][1];
      var freq = e.features[i].data.properties["frequency"];
      var bearing = e.features[i].data.properties["bearing"];
      var distance = e.features[i].data.properties["distance"];
      var vectorID = e.features[i].data.properties["id"];
      var refStories = e.features[i].data.properties["stories"];
      var refPlacePairs = e.features[i].data.properties["placepairs"];

      vectorData[itemName] = {};
      vectorData[itemName][vectorID] = {};
      vectorData[itemName][vectorID]["stories"] = refStories;
      vectorData[itemName][vectorID]["placepairs"] = refPlacePairs;
      vectorData[itemName][vectorID]["sourceCoords"] = y1 + "N, " + x1 + "E";
      vectorData[itemName][vectorID]["destCoords"] = y2 + "N, " + x2 + "E";

      var f = e.features[i],
      c = f.element;

      c.setAttribute("stroke-width", freq + "px");
      c.setAttribute("opacity", .8);
      c.setAttribute("sourceCoords", y1 + "N, " + x1 + "E");
      c.setAttribute("destCoords", y2 + "N, " + x2 + "E");
      c.setAttribute("freq", freq);
      c.setAttribute("bearing", bearing);
      c.setAttribute("distance", distance);
      c.setAttribute("stories", refStories);
      c.setAttribute("placepairs", refPlacePairs);
      c.setAttribute("index", itemName);
      c.setAttribute("vectorID", vectorID);
      c.setAttribute("id", itemName + "_vector_" + vectorID);
      c.setAttribute("vector-effect", "non-scaling-stroke");
      c.setAttribute("stroke", indexColors[itemName]);
      c.addEventListener("mouseover", function(evt){  
                        lineMouse(this, evt, "black", 1);  
                       }, false); 
      c.addEventListener("mouseout", function(evt){  
                        lineMouse(this, evt, indexColors[itemName], .8);
                       }, false); 
      c.addEventListener("mousedown", function(evt){  
                        lineClick(this, evt);
                       }, false);

    } else if (e.features[i].data.geometry.type == "Point") {

      var x = e.features[i].data.geometry.coordinates[0];
      var y = e.features[i].data.geometry.coordinates[1];
      var freq = e.features[i].data.properties["frequency"];
      var bearing = e.features[i].data.properties["bearing"];
      var distance = e.features[i].data.properties["distance"];
      var vectorID = e.features[i].data.properties["id"];
      var refStories = vectorData[itemName][vectorID]["stories"];
      var refPlacePairs = vectorData[itemName][vectorID]["placepairs"];
      var sourceCoords = vectorData[itemName][vectorID]["sourceCoords"];
      var destCoords = vectorData[itemName][vectorID]["destCoords"];

      var f = e.features[i],
      c = f.element;
      g = f.element = po.svg("g");

      var m = icons.marker();
      m.setAttribute("id", itemName + "_arrow_" + vectorID);
      g.appendChild(m);
      g.setAttribute("index", itemName);
      g.setAttribute("vectorID", vectorID);
      g.setAttribute("id", itemName + "_arrow_" + vectorID);
      g.setAttribute("opacity", 0.8);
      g.setAttribute("freq", freq);
      g.setAttribute("bearing", bearing);
      g.setAttribute("distance", distance);
      g.setAttribute("stories", refStories);
      g.setAttribute("placepairs", refPlacePairs);
      g.setAttribute("sourceCoords", sourceCoords);
      g.setAttribute("destCoords", destCoords);
      g.setAttribute("fill", indexColors[itemName]);
      g.setAttribute("stroke-width", "2px");
      g.setAttribute("stroke", "black");
      g.setAttribute("id", i);
      var scaleFactor = 0.6 + 0.6 * freq / 10;
      if (bearing > 180) {
        bearing = bearing - 360;
      }
      g.setAttribute("transform", c.getAttribute("transform") + ", rotate(" + bearing + ", 0, 0), scale(" + scaleFactor + "), translate(-16,-18)");
      g.addEventListener("mouseover", function(evt){  
                        arrowMouse(this, evt, "black", 1);  
                       }, false); 
      g.addEventListener("mouseout", function(evt){  
                        arrowMouse(this, evt, indexColors[itemName], 0.8);
                       }, false);
      g.addEventListener("mousedown", function(evt){
                        arrowClick(this, evt);
                       }, false);
      c.parentNode.replaceChild(g, c);

    }

  }

  });

  map.add(newVectors);
  itemVectors[itemName] = newVectors;  

//  return newVectors;

}

function pollImage(imageURL) {

  var checkImage = function() {
    $.ajax({
      type: 'HEAD',
      url: imageURL,
      success: function (data) {
        console.log("Found image " + imageURL);
        imgHTML = document.getElementById("imagearea").innerHTML;
        imgHTML += '<img src="'+imageURL+'" ' + imgDims + '>';
        document.getElementById("imagearea").innerHTML = imgHTML;
        clearInterval(interval);
      },
      error: function (result) {
        console.log("Couldn't load image " + imageURL);
      }
//      complete: function() { pollImage(imageURL); }
    });
  };
  var interval = setInterval(checkImage, 2000); 
  checkImage();
};

/*
var timerForLoadingResult=setInterval(checkServerForFile,4000);
  
function checkServerForFile(url) { 
  $.ajax({
    type: "POST",
    cache: false,
    url: url,
    success: function (result) {
      if (result) { //do something
        clearInterval(timerForLoadingResult); // clear timer
      }
   ; }
  });
}
*/
function loadGraphs(indexName) {

  imagename = indexName;
  var imgprefix = "http://etkspace.scandinavian.ucla.edu/maps/witchhunter/direction_graphs/" + imagename;
  var imgDims = 'width="400px" height="400px"';
  var imgpath = "";
  var imgHTML = "<p><b>Storytellers' collective mental geography of this topic:</b></p>";
  
  //document.getElementById("imagearea").innerHTML = imgHTML;

  //var imgpath = imgprefix + "_distances.png";
  //imgHTML += '<img src="'+imgpath+'" ' + imgDims + '>';
  
  imgpath = imgprefix + "_cart.png";
  imgHTML += '<img src="'+imgpath+'" ' + imgDims + '>';
  
  imgpath = imgprefix + "_binhist.png";
  imgHTML += '<img src="'+imgpath+'" ' + imgDims + '>';
  
  imgpath = imgprefix + "_bearings.png";
  imgHTML += '<img src="'+imgpath+'" ' + imgDims + '>';
  
  imgpath = imgprefix + "_logplot.png";
  imgHTML += '<img src="'+imgpath+'" ' + imgDims + '>';
  
  imgpath = imgprefix + "_normdist.png";
  imgHTML += '<img src="'+imgpath+'" ' + imgDims + '>';
/*
  imgpath = imgprefix + "_cart.png";
  pollImage(imgpath);
  
  imgpath = imgprefix + "_cart2.png";
  pollImage(imgpath);
  */
  document.getElementById("infopane").innerHTML = imgHTML;
  
}

function indexClicked(cbox) {
  var indexName = cbox.id;
  if (cbox.checked == true) {
  	var textColor = getContrastYIQ(indexColors[indexName]);
    $(cbox).parent().find("label").css("background-color", indexColors[indexName]);
    $(cbox).parent().find("label").css("color", textColor);
    drawVectors(indexName);
    loadGraphs(indexName);
    drawMarkers(indexName);
  } else {
  	$(cbox).parent().find("label").css("background-color", "#f5f5f5");
    $(cbox).parent().find("label").css("color", "#333");
    document.getElementById('storytexts').innerHTML = "<p></p>";
    document.getElementById('storyinfo').innerHTML = "<p></p>";
    document.getElementById('infopane').innerHTML = helpText;
    if (indexName in itemMarkers) {
      map.remove(itemMarkers[indexName]);
      delete itemMarkers[indexName];
    }
    if (indexName in itemVectors) {
      map.remove(itemVectors[indexName]);
      delete itemVectors[indexName];
    }
    delete vectorData[indexName];
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
