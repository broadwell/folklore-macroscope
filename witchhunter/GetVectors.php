<?php

ini_set("auto_detect_line_endings", true);
header('Access-Control-Allow-Origin: *');

require "spatial_geometry.php";

$itemType = "index";

$itemVal = htmlspecialchars($_GET["itemName"]);
//$itemVal = "ETK_churches";
if (preg_match('/^ETK/', $itemVal)) {
  $itemName = str_replace("ETK_", "", $itemVal);
} else if (preg_match('/^Informant/', $itemVal)) {
  $itemName = str_replace("Informant_", "", $itemVal);
  $itemType = "informant";
}

# Connect to the database
$dblink = mysql_connect("localhost:3306", "dfl", "Dfl#123!");
@mysql_select_db("dfl2db", $dblink);
mysql_set_charset("UTF8", $dblink);

$abbrev = $itemVal;

$outfile = fopen('direction_files/' . $abbrev . '_polarcoords.dat', 'w');
$cartfile = fopen('direction_files/' . $abbrev . '_cartcoords.dat', 'w');
$cart2file = fopen('direction_files/' . $abbrev . '_cartcoords2.dat', 'w');
$freqfile = fopen('direction_files/' . $abbrev . '_polarfreqs.dat', 'w');
$distfile = fopen('direction_files/' . $abbrev . '_distances.dat', 'w');
$normfile = fopen('direction_files/' . $abbrev . '_normdist.dat', 'w');
$binfile = fopen('direction_files/' . $abbrev . '_binhist.dat', 'w');
$histfile = fopen('direction_files/' . $abbrev . '_hist.dat', 'w');

$DistanceValues = array();
$DistanceBins = array();
$BearingDistMatrix = array();
$CartDistMatrix = array();

$geoJSON = '{ "type": "FeatureCollection", "features": [';
$lineCount = 0;

if ($itemType == "index") {

  $ETKIndexQuery = 'SELECT * FROM etk_index_merged WHERE abbrev="' . $itemName . '"';
  $ETKIndexResult = mysql_query($ETKIndexQuery) or die ("Index query failed " . mysql_error() . "\n");
  $ETKIndexRow = mysql_fetch_assoc($ETKIndexResult);
  $ETKIndexID = $ETKIndexRow['etk_merged_id'];

  $DirectionQuery = "SELECT * FROM etk_merged_to_pref WHERE etk_index_merged=" . $ETKIndexID;

} else if ($itemType == "informant") {

  $DirectionQuery = "SELECT * FROM informant_to_pref WHERE informant_id=" . $itemName;

}

$DirectionResult = mysql_query($DirectionQuery) or die("direction query failed " . mysql_error() . "\n");
while ($DirectionRow = mysql_fetch_assoc($DirectionResult)) {
  $freq = $DirectionRow['frequency'];
  $bearing = $DirectionRow['bearing'];
  $dist = $DirectionRow['distance']+0;

  if ($dist == -1) $dist = 0;

  for ($i=0;$i<$freq;$i++) {
    $DistanceValues[] = $dist;
  }

//  incrementHash(roundDown($dist, 5)/5 . "", $DistanceBins, $freq);
  incrementHash(round($dist, 0) . "", $DistanceBins, $freq);
//  fwrite($normfile, "put " . $dist . " into bin " . roundDown($dist, 5)/5 . "\n");

  if ($dist > 0) {
    matrixIncrement($dist . "", round($bearing, 0), $BearingDistMatrix, $freq);
    matrixIncrement($dist . "", round($bearing, 0), $CartDistMatrix, $freq);
  } else {
    matrixIncrement($dist . "", 0, $CartDistMatrix, $freq);
  }

  if ($dist > 0) {
    if ($lineCount != 0) {
      $geoJSON .= ', ';
    }
    
    $geoJSON .=  '{ "type": "Feature", "geometry": {"type": "LineString", "coordinates": [ ';
    $geoJSON .= '[' . $DirectionRow['coll_lon'] . ', ' . $DirectionRow['coll_lat'] . '], ';
    $geoJSON .= '[' . $DirectionRow['mentioned_lon'] . ', ' . $DirectionRow['mentioned_lat'] . ']';
    $geoJSON .= ' ] }, "properties": {';
    $geoJSON .= '"id": ' . $lineCount . ', ';
    $geoJSON .= '"frequency": ' . $DirectionRow['frequency'] . ', ';
    $geoJSON .= '"bearing": ' . $DirectionRow['bearing'] . ', ';
    $geoJSON .= '"distance": ' . $DirectionRow['distance'] . ', ';
    $geoJSON .= '"stories": "' . $DirectionRow['story_ids'] . '", ';
    $geoJSON .= '"placepairs": "' . $DirectionRow['place_pairs'] . '"} }';
    
    $geoJSON .= ', { "type": "Feature", "geometry": {"type": "Point", "coordinates": [' . $DirectionRow['mentioned_lon'] . ', ' . $DirectionRow['mentioned_lat'] . ']}, "properties": {"frequency": ' . $DirectionRow['frequency'] . ', "bearing": ' . $DirectionRow['bearing'] . ', "distance": ' . $DirectionRow['distance'] . ', "id" : ' . $lineCount . ' } }';

    $lineCount++;
  }
  
}
$geoJSON .= ']}';

$NormBins = array();
//$maxFreq = max(array_values($DistanceBins));
/*fwrite($normfile, '0');
if (!isset($DistanceBins[0])) {
  $thisFreq = 0;
} else { 
  $thisFreq = $DistanceBins[0];
}

$NormBins[0] = $thisFreq / $maxFreq;
 */
//for ($i=1; $i<max(array_keys($DistanceBins)); $i++) {

ksort($DistanceBins);
foreach ($DistanceBins as $dist=>$thisFreq) {

//  fwrite($normfile, ' ' . $i);
//  fwrite($normfile, ' ' . $dist);
/*
  if (!isset($DistanceBins[($i-1)])) {
    $prevFreq = 0;
  } else {
    $prevFreq = $DistanceBins[($i-1)];
  } 
  
  if (!isset($DistanceBins[$i])) {
    $thisFreq = 0;
  } else { 
    $thisFreq = $DistanceBins[$i];
  }
 */
//  $thisDeriv = (($prevFreq - $thisFreq) / $maxFreq) / 5;

  // Actually it's just a normalized histogram for now
  $thisDeriv = $thisFreq;
//  $NormBins[$i] = $thisDeriv;
  $NormBins[$dist] = $thisDeriv;
//  fwrite($normfile, $dist . '=>' . $thisDeriv . "\n");

}
//fwrite($normfile, "\n");
/*
$distanceKeys = implode(" ", array_values($DistanceBins));
fwrite($derivfile, $distanceKeys);
fwrite($derivfile, "\n");
$distanceValues = implode(" ", array_values($DistanceBins));
fwrite($derivfile, $distanceValues);
fwrite($derivfile, "\n");
 */
//fwrite($normfile, ' ' . $dist);
ksort($NormBins);
$distanceKeys = implode(" ", array_keys($NormBins));
fwrite($normfile, $distanceKeys);
fwrite($normfile, "\n");
$derivValues = implode(" ", array_values($NormBins));
fwrite($normfile, $derivValues);
fwrite($normfile, "\n");
fclose($normfile);

$distanceValuesString = implode(" ", $DistanceValues);
fwrite($distfile, $distanceValuesString);
fwrite($distfile, "\n");
fclose($distfile);

$xArray = array();
$yArray = array();
$zArray = array();

$DataMatrix = array();
for ($b=0;$b<360;$b++) {
  $DataMatrix[$b] = 0;
}
$BinDataMatrix = array();
for ($b=0;$b<360;$b+=30) {
  $BinDataMatrix[$b] = 0;
}
ksort($BearingDistMatrix);
foreach($BearingDistMatrix as $distance=>$BearingFreqs) {
  for ($b=0;$b<=359;$b++) {
    if (isset($BearingFreqs[$b])) {
      $BinDataMatrix[intval($b/30)*30] += $BearingFreqs[$b];
      $DataMatrix[$b] += $BearingFreqs[$b];
      $xArray[] = $b;
      $yArray[] = $distance;
      $zArray[] = $BearingFreqs[$b];
    }
  }
}

fwrite($histfile, "0");
for ($i=1;$i<360;$i++) {
  fwrite($histfile, " " . ($i));
}
fwrite($histfile, "\n");
fwrite($histfile, $DataMatrix[0]);
for ($b=1;$b<360;$b++) {
  fwrite($histfile, " " . $DataMatrix[$b]);
}
fwrite($histfile, "\n");

fwrite($binfile, "15");
for ($i=30;$i<360;$i+=30) {
  fwrite($binfile, " " . ($i+15));
}
fwrite($binfile, "\n");
fwrite($binfile, $BinDataMatrix[0]);
for ($b=30;$b<360;$b+=30) {
  fwrite($binfile, " " . $BinDataMatrix[$b]);
}
fwrite($binfile, "\n");

$degsSeen = array();

fwrite($outfile, $xArray[0]);
$degsSeen[$xArray[0]] = 1;
for ($i=1;$i<count($xArray);$i++) {
  $degsSeen[$xArray[$i]] = 1;
  fwrite($outfile, " " . $xArray[$i]);
}
for ($i=0;$i<=359;$i++) {
  if (!isset($degsSeen[$i])) {
    fwrite($outfile, " " . $i);
  }
}
fwrite($outfile, "\n");
fwrite($outfile, $yArray[0]);
for ($i=1;$i<count($yArray);$i++) {
  fwrite($outfile, " " . $yArray[$i]);
}
for ($i=0;$i<=359;$i++) {
  if (!isset($degsSeen[$i])) {
    fwrite($outfile, " 0");
  }
}
fwrite($outfile, "\n");
fwrite($outfile, $zArray[0]);
for ($i=1;$i<count($zArray);$i++) {
  fwrite($outfile, " " . $zArray[$i]);
}
for ($i=0;$i<=359;$i++) {
  if (!isset($degsSeen[$i])) {
    fwrite($outfile, " 0");
  }
}
fwrite($outfile, "\n");

$degsSeen = array();

fwrite($freqfile, $xArray[0]);
for ($j=1;$j<$zArray[0];$j++) {
  fwrite($freqfile, " " . $xArray[0]);
}
$degsSeen[$xArray[0]] = 1;
for ($i=1;$i<count($xArray);$i++) {
  $degsSeen[$xArray[$i]] = 1;
  for ($j=0;$j<$zArray[$i];$j++) {
    fwrite($freqfile, " " . $xArray[$i]);
  }
}
for ($i=0;$i<=359;$i++) {
  if (!isset($degsSeen[$i])) {
    fwrite($freqfile, " " . $i);
  }
}
fwrite($freqfile, "\n");

fwrite($freqfile, $yArray[0]);
for ($j=1;$j<$zArray[0];$j++) {
  fwrite($freqfile, " " . $yArray[0]);
}
for ($i=1;$i<count($yArray);$i++) {
  for ($j=0;$j<$zArray[$i];$j++) {
    fwrite($freqfile, " " . $yArray[$i]);
  }
}
for ($i=0;$i<=359;$i++) {
  if (!isset($degsSeen[$i])) {
    fwrite($freqfile, " 0");
  }
}
fwrite($freqfile, "\n");

fwrite($freqfile, "1");
for ($j=1;$j<$zArray[0];$j++) {
  fwrite($freqfile, " 1");
}
for ($i=1;$i<count($zArray);$i++) {
  for ($j=0;$j<$zArray[$i];$j++) {
    fwrite($freqfile, " 1");
  }
}
for ($i=0;$i<=359;$i++) {
  if (!isset($degsSeen[$i])) {
    fwrite($freqfile, " 0");
  }
}
fwrite($freqfile, "\n");

$cartYCoords = array();

$cartCoords = logPolar2cart($yArray[0], $xArray[0], true);
$cartYCoords[0] = $cartCoords['y'];
fwrite($cartfile, $cartCoords['x']);
for ($j=1;$j<$zArray[0];$j++) {
  fwrite($cartfile, " " . $cartCoords['x']);
}
for ($i=1;$i<count($xArray);$i++) {
  $cartCoords = logPolar2cart($yArray[$i], $xArray[$i], true);
  $cartYCoords[$i] = $cartCoords['y'];
  for ($j=0;$j<$zArray[$i];$j++) {
    fwrite($cartfile, " " . $cartCoords['x']);
  }
}
fwrite($cartfile, "\n");

fwrite($cartfile, $cartYCoords[0]);
for ($j=1;$j<$zArray[0];$j++) {
  fwrite($cartfile, " " . $cartYCoords[0]);
}
for ($i=1;$i<count($yArray);$i++) {
  for ($j=0;$j<$zArray[$i];$j++) {
    fwrite($cartfile, " " . $cartYCoords[$i]);
  }
}
fwrite($cartfile, "\n");

fwrite($cartfile, "1");
for ($j=1;$j<$zArray[0];$j++) {
  fwrite($cartfile, " 1");
}
for ($i=1;$i<count($zArray);$i++) {
  for ($j=0;$j<$zArray[$i];$j++) {
    fwrite($cartfile, " 1");
  }
}
fwrite($cartfile, "\n");


$xArray = array();
$yArray = array();
$zArray = array();

ksort($CartDistMatrix);
foreach($CartDistMatrix as $distance=>$BearingFreqs) {
  for ($b=0;$b<=359;$b++) {
    if (isset($BearingFreqs[$b])) {
      $xArray[] = $b;
      $yArray[] = $distance;
      $zArray[] = $BearingFreqs[$b];
    }
  }
}

$cartYCoords = array();

$cartCoords = logPolar2cart($yArray[0], $xArray[0], true);
$cartYCoords[0] = $cartCoords['y'];
fwrite($cart2file, $cartCoords['x']);
for ($j=1;$j<$zArray[0];$j++) {
  fwrite($cart2file, " " . $cartCoords['x']);
}
for ($i=1;$i<count($xArray);$i++) {
  $cartCoords = logPolar2cart($yArray[$i], $xArray[$i], true);
  $cartYCoords[$i] = $cartCoords['y'];
  for ($j=0;$j<$zArray[$i];$j++) {
    fwrite($cart2file, " " . $cartCoords['x']);
  }
}
fwrite($cart2file, "\n");

fwrite($cart2file, $cartYCoords[0]);
for ($j=1;$j<$zArray[0];$j++) {
  fwrite($cart2file, " " . $cartYCoords[0]);
}
for ($i=1;$i<count($yArray);$i++) {
  for ($j=0;$j<$zArray[$i];$j++) {
    fwrite($cart2file, " " . $cartYCoords[$i]);
  }
}
fwrite($cart2file, "\n");

fwrite($cart2file, "1");
for ($j=1;$j<$zArray[0];$j++) {
  fwrite($cart2file, " 1");
}
for ($i=1;$i<count($zArray);$i++) {
  for ($j=0;$j<$zArray[$i];$j++) {
    fwrite($cart2file, " 1");
  }
}
fwrite($cart2file, "\n");

//$numPoints = count($zArray);

fclose($cartfile);
fclose($cart2file);
fclose($freqfile);
fclose($outfile);
fclose($histfile);
fclose($binfile);

exec("/usr/bin/Rscript DirectionHistograms.R " . $abbrev);

//$inputFile = fopen("direction_vectors.csv", "r");
//$inputFile = fopen("ETKvectors/" . $indexName . ".csv", "r");

echo $geoJSON;

return;

?>
