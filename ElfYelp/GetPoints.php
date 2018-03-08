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

$dataDir = htmlspecialchars($_GET["datadir"]);

$locFile = fopen($dataDir . "/geolda_locations.txt", "r");

$locID = 0;

$geoJSON = '{ "type": "FeatureCollection", "features": [';
$lineCount = 0;

while ($locLine = fgetcsv($locFile, 0, " ")) {
  
  if ($lineCount != 0) {
    $geoJSON .= ',';
  }
  
  $locID++;
  
  $geoJSON .=  '{ "type": "Feature", "geometry": {"type": "Point", "coordinates": [';

  $lat = $locLine[0];
  $lon = $locLine[1];

  $geoJSON .= $lon . ", " . $lat . ']}, "properties": {';
  $geoJSON .= '"locationID":"' . $locID . '"}}';
  
  $lineCount++;

}

fclose($locFile);

$geoJSON .= '], "properties": {"itemName":"GeoLocations"}';
$geoJSON .= '}';

echo $geoJSON;

return;

?>
