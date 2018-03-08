<?php

/* This is an interactive script to find keywords that co-occur with places located
 * within a given distance (currently measured in degrees) from a given location. The
 * keywords are listed according to a set of ranking algorithms that attempt to
 * quantify how "relevant" a keyword is to the region; typically only the X most
 * "relevant" keywords are listed in the results.
 */

header('Access-Control-Allow-Origin: *');

function addToHash($key, $val, &$hash) {
  if (array_key_exists($key, $hash)) {
    $hash[$key][] = $val;
  } else {
    $hash[$key] = array($val);
  }
}

# Connect the database
$dblink = mysql_connect("localhost:3306", "dfl", "Dfl#123!");
@mysql_select_db("dfl2db", $dblink);

mysql_set_charset("UTF8", $dblink);

//$bob = array('outcome' => 'SUCCESS');

//$bob = array('lat' => htmlspecialchars($_GET["lat"]), 'lon' => htmlspecialchars($_GET["lon"]) );
//echo json_encode($bob);

/*
if (($argc < 3) || ($argv[1] == "-h") || ($argv[1] == "--help")) {
  echo "USAGE: FindNearbyKeywords LATITUDE LONGITUDE DISTANCE\n";
  return;
}
 */

$latitude = htmlspecialchars($_GET["lat"]);
$longitude = htmlspecialchars($_GET["lon"]);
$distance = htmlspecialchars($_GET["dist"]);
$maxKeywords = htmlspecialchars($_GET["max"]);
$MAX_DISPLAY_KEYWORDS = $maxKeywords;

/* For testing */
/*
$latitude = 56.3836;
$longitude = 9.244995;
$distance = .1;
$maxKeywords = 10;
$MAX_DISPLAY_KEYWORDS = $maxKeywords;
 */

$returnVals = array();
$returnVals['points'] = array();

$AllPlacesByID = array();

$geoUpdateQuery = "SET @center = GeomFromText('POINT(" . $longitude . " " . $latitude . ")')";
mysql_query($geoUpdateQuery) or die("Geo query 1 failed " . mysql_error() . "\n");
$geoUpdateQuery = "SET @radius = " . $distance;
mysql_query($geoUpdateQuery) or die("Geo query 2 failed " . mysql_error() . "\n");
$geoUpdateQuery = "SET @double_radius = " . $distance*2;
mysql_query($geoUpdateQuery) or die("Geo query 3 failed " . mysql_error() . "\n");
  
$geoUpdateQuery = "SET @bbox = CONCAT('POLYGON((', X(@center) - @double_radius, ' ', Y(@center) - @radius, ',', X(@center) + @double_radius, ' ', Y(@center) - @radius, ',', X(@center) + @double_radius, ' ', Y(@center) + @radius, ',', X(@center) - @double_radius, ' ', Y(@center) + @radius, ',', X(@center) - @double_radius, ' ', Y(@center) - @radius, '))')";
mysql_query($geoUpdateQuery) or die("Geo query 3 failed " . mysql_error() . "\n");

//echo "Beginning georegion query\n";

//$geoRegionQuery = 'SELECT distinct place_id, name, AsText(coords), lon, lat FROM geo_places WHERE Intersects( coords, GeomFromText(@bbox) ) AND SQRT(POW( ABS( X(coords) - X(@center)), 2) + POW( ABS(Y(coords) - Y(@center)), 2)) < @double_radius';
$geoRegionQuery = 'SELECT distinct geo_places.place_id, geo_places.name, AsText(coords), lon, lat FROM geo_places, story_to_place_mentioned WHERE story_to_place_mentioned.place_id=geo_places.place_id AND MBRContains(GeomFromText(@bbox),coords)';
$geoRegionResult = mysql_query($geoRegionQuery) or die("Geo query 4 failed " . mysql_error() . "\n");

//echo "Georegion query completed\n";

$placeCount = 0;

$inKeywords = array();
$keywordIDtoKeyword = array();

$placeCoordsToInfo = array();

$regionalCooccurrences = 0;

//echo "processing geoRow info\n";

while($geoRow = mysql_fetch_assoc($geoRegionResult)) {
  $placeCount++;

  $placeID = $geoRow['place_id'];  
  $placeCoords = $geoRow['lat'] . "|" . $geoRow['lon'];

  if (!isset($placeCoordsToInfo[$placeCoords])) {
    $placeCoordsToInfo[$placeCoords] = array();
  }
  
  if (!isset($placeCoordsToInfo[$placeCoords]['places'])) {
    $placeCoordsToInfo[$placeCoords]['places'] = $geoRow['name'];
  } else {
    $placeCoordsToInfo[$placeCoords]['places'] .= ", " . $geoRow['name'];
  }

//  echo $placeCount . ": " . $geoRow['name'] . " (#" . $placeID . "): " . $geoRow['lat'] . " N, " . $geoRow['lon'] . " E\n";

//  $keywordsQuery = 'SELECT DISTINCT lemmatized_keywords.keyword_id, lemmatized_keywords.keyword FROM lemmatized_keywords, story_to_place_mentioned, story_to_lemmatized_keyword WHERE lemmatized_keywords.keyword_id=story_to_lemmatized_keyword.keyword_id AND story_to_lemmatized_keyword.story_id=story_to_place_mentioned.story_id AND story_to_place_mentioned.place_id=' .  $placeID;
  $keywordsQuery = 'SELECT DISTINCT etk_index_merged.etk_merged_id, etk_index_merged.heading_danish FROM etk_index_merged, story_to_place_mentioned, story_to_etk_index_merged WHERE etk_index_merged.etk_merged_id=story_to_etk_index_merged.etk_index_id AND story_to_etk_index_merged.story_id=story_to_place_mentioned.story_id AND story_to_place_mentioned.place_id=' .  $placeID;
  $keywordsResult = mysql_query($keywordsQuery) or die("Keywords query failed " . mysql_error() . "\n");
  
  while ($keywordsRow = mysql_fetch_assoc($keywordsResult)) {
    
    if (!isset($placeCoordsToInfo[$placeCoords]['keywords'])) {
      $placeCoordsToInfo[$placeCoords]['keywords'] = $keywordsRow['heading_danish'];
    } else {
      $placeCoordsToInfo[$placeCoords]['keywords'] .= ", " . $keywordsRow['heading_danish'];
    }

    if (!isset($inKeywords[$keywordsRow['etk_merged_id']])) {
      $inKeywords[$keywordsRow['etk_mergedid']] = 1;
      $keywordIDtoKeyword[$keywordsRow['etk_merged_id']] = $keywordsRow['heading_danish'];
      $regionalCooccurrences++;
    } else {
      $inKeywords[$keywordsRow['etk_merged_id']]++;
      $regionalCooccurrences++;
    }
//    echo "for " . $keywordsRow['etk_index_id'] . ", cooccurrences is " . $inKeywords[$keywordsRow['etk_index_id']] . "\n";
  }
}

//echo "Completed processing geoRows\n";

foreach ($placeCoordsToInfo as $placeCoords => $infoArray) {

  $placeCoordsArray = explode('|', $placeCoords);

  $returnVals['points'][] = array('lat' => $placeCoordsArray[0],'lon' => $placeCoordsArray[1], 'keywords' => $infoArray['keywords'], 'places' => $infoArray['places']);

}

//echo "MOST COMMON KEYWORDS IN THIS REGION (# stories):\n";

$returnVals['raw'] = "<p><b>Raw:</b><br>";

arsort($inKeywords);
$i=0;
foreach ($inKeywords as $keywordID => $total) {
  //  echo $keywordIDtoKeyword[$keywordID] . " (#" . $keywordID . "): " . $total ."\n";
  $returnVals['raw'] .= $i+1 . ". " . $keywordIDtoKeyword[$keywordID] . " (#" . $keywordID . "): " . $total ."<br>";
  if (++$i >= $MAX_DISPLAY_KEYWORDS) {
    break;
  }
}

//echo "Beginning total places query\n";

$totalPlacesQuery = "SELECT COUNT(DISTINCT place_id) AS place_count FROM story_to_place_mentioned";
$totalPlacesResult = mysql_query($totalPlacesQuery) or die("Total places query failed " . mysql_error() . "\n");
if ($totalRow = mysql_fetch_assoc($totalPlacesResult)) {
  $totalPlacesMentioned = $totalRow['place_count'];
}

//echo "Processed total places query\n";

//echo "Total places mentioned is " . $totalPlacesMentioned . "\n";

$RFIPF = array(); // Region Frequency - Inverse Place Frequency

foreach ($inKeywords as $keywordID => $total) {

  $globalKeywordQuery = "SELECT place_cooccurrences FROM etk_index_merged WHERE etk_merged_id=".$keywordID;
  $globalKeywordResult = mysql_query($globalKeywordQuery) or die("Global keyword query failed " . mysql_error() . "\n");
  if ($globalKeywordRow = mysql_fetch_assoc($globalKeywordResult)) {
    $globalKeywordCount = $globalKeywordRow['place_cooccurrences'];
    $globalKeywords[$keywordID] = $globalKeywordCount;

    $rfipf = ($total/$regionalCooccurrences) * log($totalPlacesMentioned / $globalKeywordCount);

    $RFIPF[$keywordID] = $rfipf;
  }
}

arsort($RFIPF);

$returnVals['rfipf'] = "<p><b>RF-IPF:</b><br>";

//echo "KEYWORDS BY RF-IPF (there are " . $totalPlacesMentioned . " total places mentioned)\n";
$i=0;
foreach ($RFIPF as $keywordID => $rfidf) {
//  echo $keywordIDtoKeyword[$keywordID] . " (#" . $keywordID . "): " . $rfidf . " (" . $inKeywords[$keywordID] . " times in region, " . $globalKeywords[$keywordID] . " times globally)\n";
  $returnVals['rfipf'] .= $i+1 . ". " . $keywordIDtoKeyword[$keywordID] . " (#" . $keywordID . "): " . round($rfidf, 6) . " (" . $inKeywords[$keywordID] . " times in region, " . $globalKeywords[$keywordID] . " times globally)<br>";
  if (++$i >= $MAX_DISPLAY_KEYWORDS) {
    break;
  }
}

// One more: rank keywords by the ratio:
// # place co-occurences in this region /
// # place co-occurrences globally
// (slightly different results than TF-IDF)

$normalizedCounts = array();

foreach ($inKeywords as $keywordID => $total) {
  $normalizedCounts[$keywordID] = $total / $globalKeywords[$keywordID];
}
arsort($normalizedCounts);
//echo "NORMALIZED KEYWORD RANKINGS:\n";
$returnVals['normalized'] = "<p><b>Normalized:</b><br>";
$i=0;
foreach ($normalizedCounts as $keywordID => $norm) {
//  echo $keywordIDtoKeyword[$keywordID] . " (#" . $keywordID . "): " . $norm . " (" . $inKeywords[$keywordID] . " times in region, " . $globalKeywords[$keywordID] . " times globally)\n";
  $returnVals['normalized'] .= $i+1 . ". " . $keywordIDtoKeyword[$keywordID] . " (#" . $keywordID . "): " . round($norm, 2) . " (" . $inKeywords[$keywordID] . " times in region, " . $globalKeywords[$keywordID] . " times globally)<br>";
  if (++$i >= $MAX_DISPLAY_KEYWORDS) {
    break;
  }
}

echo json_encode($returnVals);
return;
