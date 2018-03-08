<?php

ini_set("auto_detect_line_endings", true);
header('Access-Control-Allow-Origin: *');

function incrementHash($key, &$hash, $val=1) {
  if (array_key_exists($key, $hash)) {
    $hash[$key] += $val;
  } else {
    $hash[$key] = $val;
  }
}

function addToHash($key, $val, &$hash) {
  if (array_key_exists($key, $hash)) {
    $hash[$key][] = $val;
  } else {
    $hash[$key] = array($val);
  }
}

$itemType = "index";

$itemVal = htmlspecialchars($_GET["itemName"]);
if (preg_match('/^ETK/', $itemVal)) {
  $itemName = str_replace("ETK_", "", $itemVal);
} else if (preg_match('/^Informant/', $itemVal)) {
  $itemName = str_replace("Informant_", "", $itemVal);
  $itemType = "informant";
}

//$indexName = "ETK_churches";

# Connect to the database
$dblink = mysql_connect("localhost:3306", "dfl", "Dfl#123!");
@mysql_select_db("dfl2db", $dblink);
mysql_set_charset("UTF8", $dblink);

$PlaceFrequency = array();
$PlaceCoords = array();
$PlaceStories = array();
$ReferenceDetails = array();

if ($itemType == "index") {
  $ETKIndexQuery = 'SELECT * FROM etk_index_merged WHERE abbrev="' . $itemName . '"';
  $ETKIndexResult = mysql_query($ETKIndexQuery) or die ("Index query failed " . mysql_error() . "\n");
  $ETKIndexRow = mysql_fetch_assoc($ETKIndexResult);
  $ETKIndexID = $ETKIndexRow['etk_index_id'];

  $PlacesQuery = "SELECT * FROM story_to_place_mentioned, story_to_mimno_etk_index, geo_places WHERE story_to_mimno_etk_index.etk_index_id=" . $ETKIndexID . " AND story_to_place_mentioned.story_id=story_to_mimno_etk_index.story_id AND story_to_place_mentioned.place_id=geo_places.place_id";

} else if ($itemType == "informant") {

  $PlacesQuery = "SELECT * FROM story_to_place_mentioned, story_to_informant, geo_places WHERE story_to_informant.informant_id=" . $itemName . " AND story_to_place_mentioned.story_id=story_to_informant.story_id AND story_to_place_mentioned.place_id=geo_places.place_id";

}

$PlacesResult = mysql_query($PlacesQuery) or die ("Places query failed " . mysql_error() . "\n");
while ($PlaceRow = mysql_fetch_assoc($PlacesResult)) {

  $physPlaceID = $PlaceRow['physical_place_id'];
  $storyID = $PlaceRow['story_id'];
  $referenceString = $storyID . "=>" . $PlaceRow['name'] . " (" . $PlaceRow['place_id'] . ")";

  if (!isset($PlaceCoords[$physPlaceID]))
    $PlaceCoords[$physPlaceID] = array($PlaceRow['lon'], $PlaceRow['lat']);

  incrementHash($physPlaceID, $PlaceFrequency);
  addToHash($physPlaceID, $storyID, $PlaceStories);
  addToHash($physPlaceID, $referenceString, $ReferenceDetails);

}

//$inputFile = fopen("ETKvalues/" . $itemName . "_vals.csv", "r");

$geoJSON = '{ "type": "FeatureCollection", "features": [';
/*
    { "type": "Feature",
      "geometry": {"type": "Point", "coordinates": [102.0, 0.5]},
      "properties": {"prop0": "value0"}
    },
 */
$lineCount = 0;

foreach ($PlaceFrequency as $physPlaceID=>$freq) {
//while ($data = fgetcsv($inputFile))
/*
  if ($data[0] == "0") {
    continue;
  }
  if ($data[0] == "freq") {
    continue;
  }
 */
  if ($lineCount != 0) {
    $geoJSON .= ',';
  }

  $geoJSON .=  '{ "type": "Feature", "geometry": {"type": "Point", "coordinates": [';
//  $geoJSON .= $data[1] . ', ' . $data[2] . ']}, "properties": {';
  $geoJSON .= $PlaceCoords[$physPlaceID][0] . ', ' . $PlaceCoords[$physPlaceID][1] . ']}, "properties": {';
  $geoJSON .= '"count": "' . $freq . '", ';
  $geoJSON .= '"stories": "' . implode(" ", $PlaceStories[$physPlaceID]) . '", ';
  $geoJSON .= '"places": "' . implode("<br>", $ReferenceDetails[$physPlaceID]) . '"}}'; 

  $lineCount++;

}

$geoJSON .= '], "properties": {"itemName": "' . $itemVal . '"}';
$geoJSON .= '}';

echo $geoJSON;

//fclose($inputFile);

return;

?>
