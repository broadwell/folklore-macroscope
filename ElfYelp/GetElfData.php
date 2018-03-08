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
//$dataDir = "KeywordData";
//$dataDir = "ETKdata";

$centroidsFile = fopen($dataDir . "/RegionCenters.txt", "r");

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

$wordsFile = fopen($dataDir . "/geolda_tag_freqs.txt", "r");

$wordIDtoWord = array();

$wordID = 0;
while ($wordLine = fgetcsv($wordsFile, 0, " ")) {
  $wordID++;
  $wordIDtoWord[$wordID] = implode(" ", array_slice($wordLine, 0, -1));
  // Do anything with the global frequency value?
}

fclose($wordsFile);

$topicsToRegionsFile = fopen($dataDir . "/TopicsToRegions.txt", "r");

$topicWordPercent = array();

$GlobalTopicPrevalence = array();

$topicID = 0;
while ($topicRegionLine = fgetcsv($topicsToRegionsFile, 0, "\t")) {
  $topicID++;
  $topicWordPercent[$topicID] = array();
  $GlobalTopicPrevalence[$topicID] = 0;
  $regionID = 0;
  foreach ($topicRegionLine as $regionFraction) {
    $regionID++;
    $GlobalTopicPrevalence[$topicID] += round((float)$regionFraction * 100, 2);
    $regionTopicPercent[$regionID][$topicID] = round((float)$regionFraction * 100, 2);
  }
}

fclose($topicsToRegionsFile);

/* Compute region similarities based on shared topics */

$MostSimilarRegions = array();
$HellingerDistances = array();

function HellingerDist($regionI, $regionJ) {
  global $regionTopicPercent;  

  $runningSum = 0;

  foreach ($regionTopicPercent[$regionI] as $topicID => $topicIpct) {
    $topicJpct = $regionTopicPercent[$regionJ][$topicID];
    $runningSum += pow(sqrt($topicIpct / 100) - sqrt($topicJpct / 100), 2);
  }

  return $runningSum / 2;

}

foreach ($regionTopicPercent as $regionID => $TopicData) {
  $RegionSimilarities = array();
  $HellingerDists = array();

  foreach ($TopicData as $topicID => $topicPercentage) {
    if ($topicPercentage == 0)
      continue;
    foreach ($regionTopicPercent as $otherRegionID => $OtherTopicData) {
      if ($otherRegionID == $regionID)
        continue;

      $HellingerDists[$otherRegionID] = HellingerDist($regionID, $otherRegionID);
/*
      $regionDenominator = 0;
      $regionDenominatorA = 0;
      $regionDenominatorB = 0;
      $RegionSimilarities[$otherRegionID] = 0;
   
      foreach ($OtherTopicData as $otherTopicID => $otherTopicPercentage) {
        if ($topicID == $otherTopicID)
          
          $RegionSimilarities[$otherRegionID] += $topicPercentage * $otherTopicPercentage;
        
          $regionDenominatorA += $topicPercentage * $topicPercentage;
          $regionDenominatorB += $otherTopicPercentage * $otherTopicPercentage;
        
      }
      if (($regionDenominatorA == 0) || ($regionDenominatorB == 0))
        $RegionSimilarities[$otherRegionID] = 0;
      else
        $RegionSimilarities[$otherRegionID] = $RegionSimilarities[$otherRegionID] / (sqrt($regionDenominatorA) * sqrt($regionDenominatorB));
*/
    }
  }

  arsort($RegionSimilarities);
  arsort($HellingerDists);
  $MostSimilarRegions[$regionID] = array();
  $HellingerDistances[$regionID] = array();

  $simCount = 0;
  foreach ($HellingerDists as $similarRegionID => $hDist) {
    if ($simCount++ >= 20)
      break;
    $HellingerDistances[$regionID][$similarRegionID] = $hDist;
  }

  $simCount = 0;
  foreach ($RegionSimilarities as $similarRegionID => $simScore) {
    if ($simCount++ >= 20)
      break;
    if ($simScore == 0)
      break;
//    echo "for " . $regionID . ", similarity to " . $similarRegionID . " is " . $simScore . "\n";
    $MostSimilarRegions[$regionID][$similarRegionID] = $simScore;
  }

}

$wordsToTopicsFile = fopen($dataDir . "/WordsToTopics.txt", "r");

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
/*
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
 */
//    $topicsJSON[] = '{"topicID":"' . $topicID . '", "topicPercent":' . json_encode($topicPercent . "%") .', "topicWords": [' . $wordsJSONString . ']}';
    $topicsJSON[] = '{"topicID":"' . $topicID . '", "topicPercent":' . json_encode($topicPercent) .'}';
  }

  $topicsJSONString = implode(",", $topicsJSON);

  $geoJSON .= $topicsJSONString . '], ';

  //$similarRegions = $MostSimilarRegions[$regionID];
  //arsort($similarRegions);
  $similarRegions = $HellingerDistances[$regionID];
  asort($similarRegions);

  $similarJSON = array();

  $geoJSON .= '"similarRegions": [';

  foreach ($similarRegions as $similarRegionID => $similarityScore) {

    $similarJSON[] = '{"regionID": "' . $similarRegionID . '", "similarityScore": ' . $similarityScore . '}';

  }
  $similarJSONString = implode(",", $similarJSON);

  $geoJSON .= $similarJSONString . ']}}';

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
