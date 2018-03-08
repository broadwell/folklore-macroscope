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

$centroidsFile = fopen("ETKdata/RegionCenters.txt", "r");

$regionIDtoCoords = array();

$regionTopicPercent = array();

$latLine = fgetcsv($centroidsFile, 0, "\t");
$lonLine = fgetcsv($centroidsFile, 0, "\t");

for ($i=0; $i<count($latLine); $i++) {
//for ($i=0; $i<1; $i++)
  $regionIDtoCoords[$i+1] = array($latLine[$i], $lonLine[$i]);
  $regionTopicPercent[$i+1] = array();
}

fclose($centroidsFile);

$wordsFile = fopen("ETKdata/geolda_tag_freqs.txt", "r");

$wordIDtoWord = array();

$wordID = 0;
while ($wordLine = fgetcsv($wordsFile, 0, " ")) {
  $wordID++;
  $wordIDtoWord[$wordID] = implode(" ", array_slice($wordLine, 0, -1));
  // Do anything with the global frequency value?
}

fclose($wordsFile);

$topicsToRegionsFile = fopen("ETKdata/TopicsToRegions.txt", "r");

$topicWordPercent = array();

$topicID = 0;
while ($topicRegionLine = fgetcsv($topicsToRegionsFile, 0, "\t")) {
  $topicID++;
  $topicWordPercent[$topicID] = array();
  $regionID = 0;
  foreach ($topicRegionLine as $regionFraction) {
    $regionID++;
    $regionTopicPercent[$regionID][$topicID] = round((float)$regionFraction * 100, 2);
  }
}

fclose($topicsToRegionsFile);

$wordsToTopicsFile = fopen("ETKdata/WordsToTopics.txt", "r");

$wordID = 0;
while ($wordTopicLine = fgetcsv($wordsToTopicsFile, 0, "\t")) {
  $wordID++;
  $topicID = 0;
  foreach ($wordTopicLine as $wordTopicFraction) {
    $topicID++;
    $topicWordPercent[$topicID][$wordID] = round((float)$wordTopicFraction * 100, 2);
  }

}
fclose($wordsToTopicsFile);

$geoJSON = '{ "type": "FeatureCollection", "features": [';
$lineCount = 0;

foreach ($regionIDtoCoords as $regionID => $regionCoords) {
//  echo "REGION " . $regionID . " CENTERED AT " . $regionCoords[0] . "N, " . $regionCoords[1] . "E:\n";
  
  if ($lineCount != 0) {
    $geoJSON .= ',';
  }
  
  $geoJSON .=  '{ "type": "Feature", "geometry": {"type": "Point", "coordinates": [';
  $geoJSON .= $regionCoords[1] . ", " . $regionCoords[0] . ']}, "properties": {';

  $geoJSON .= '"regionID":"' . $regionID . '", ';

  $geoJSON .= '"topics": [';

  $topicsJSON = array();

  $regionTopicPercentages = $regionTopicPercent[$regionID];
  arsort($regionTopicPercentages);

  foreach($regionTopicPercentages as $topicID => $topicPercent) {

    if ($topicPercent == 0)
      continue;

//    echo "TOPIC " . $topicID . " " . $topicPercent . ":\n";

    $wordsJSON = array();

    $topicWordPercentages = $topicWordPercent[$topicID];
    arsort($topicWordPercentages);

    foreach ($topicWordPercentages as $wordID => $wordPercent) {
      if ($wordPercent == 0)
        continue;
      
      //      echo $wordIDtoWord[$wordID] . " (" . $wordPercent . "), ";

      $wordsJSON[] = '{"word":' . json_encode($wordIDtoWord[$wordID]) . ', "wordPercent":' . json_encode($wordPercent . "%") . '}';

    }
    $wordsJSONString = implode(",", $wordsJSON);
    $topicsJSON[] = '{"topicID":"' . $topicID . '", "topicPercent":' . json_encode($topicPercent . "%") .', "topicWords": [' . $wordsJSONString . ']}';
//    $topicsJSON[] = '{"topicID":"' . $topicID . '", "topicPercent":' . json_encode($topicPercent) .'}';
  }

  $topicsJSONString = implode(",", $topicsJSON);

  $geoJSON .= $topicsJSONString . ']}}';

  $lineCount++;

}
$geoJSON .= '], "properties": {';
/*
foreach ($wordIDtoWord as $wordID => $word) {
  $geoJSON .= '"index' . $wordID . '":' . json_encode($word) . ', ';
} 
 */
$geoJSON .= '"itemName":"GeoLDA"}}';

echo $geoJSON;

// Connect to the database
/*
$dblink = mysql_connect("localhost:3306", "dfl", "Dfl#123!");
@mysql_select_db("dfl2db", $dblink);
mysql_set_charset("UTF8", $dblink);
 */

return;

?>
