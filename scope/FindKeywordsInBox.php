<?php

/* This is an interactive script to find keywords that co-occur with places located
 * within a given extent The keywords are listed according to a set of ranking 
 * algorithms that attempt to quantify how "relevant" a keyword is to the region; 
 * typically only the X most "relevant" keywords are listed in the results.
 */

header('Access-Control-Allow-Origin: *');

# Connect to the database
$dblink = mysql_connect("localhost:3306", "dfl", "Dfl#123!");
@mysql_select_db("dfl2db", $dblink);

mysql_set_charset("UTF8", $dblink);

$lat1 = htmlspecialchars($_GET["lat1"]);
$lon1 = htmlspecialchars($_GET["lon1"]);
$lat2 = htmlspecialchars($_GET["lat2"]);
$lon2 = htmlspecialchars($_GET["lon2"]);
$maxKeywords = htmlspecialchars($_GET["max"]);
$MAX_DISPLAY_KEYWORDS = $maxKeywords;

/* For testing */

//[8.726635429687498, 56.94834216574617, 9.133129570312498, 57.23484505260032]
/*
$lat1 = 56.94834216574617;
$lon1 = 8.726635429687498;
$lat2 = 57.23484505260032;
$lon2 = 9.133129570312498;
$maxKeywords = 10;
$MAX_DISPLAY_KEYWORDS = $maxKeywords;
*/
$returnVals = array();
$returnVals['points'] = array("type" => "FeatureCollection", "features" => array());

$AllPlacesByID = array();
$geoUpdateQuery = "SET @bbox='POLYGON((";
$geoUpdateQuery .= $lon1 . " " . $lat1 . ", ";
$geoUpdateQuery .= $lon2 . " " . $lat1 . ", ";
$geoUpdateQuery .= $lon2 . " " . $lat2 . ", ";
$geoUpdateQuery .= $lon1 . " " . $lat2 . ", ";
$geoUpdateQuery .= $lon1 . " " . $lat1 . "))'";
//echo $geoUpdateQuery . "\n";
mysql_query($geoUpdateQuery) or die("Geo query 3 failed " . mysql_error() . "\n");

/*
$bbox = "'POLGYON((";
$bbox .= $lon1 . " " . $lat1 . ", ";
$bbox .= $lon2 . " " . $lat1 . ", ";
$bbox .= $lon2 . " " . $lat2 . ", ";
$bbox .= $lon1 . " " . $lat2 . ", ";
$bbox .= $lon1 . " " . $lat1 . "))'";
*/

$geoRegionQuery = 'SELECT distinct geo_places.place_id, geo_places.name, AsText(coords), lon, lat FROM geo_places, story_to_place_mentioned WHERE story_to_place_mentioned.place_id=geo_places.place_id AND MBRContains(GeomFromText(@bbox), coords)';

//echo $geoRegionQuery . "\n";
$geoRegionResult = mysql_query($geoRegionQuery) or die("Geo query failed " . mysql_error() . "\n");

//echo "Georegion query completed\n";

$placeCount = 0;

$inKeywords = array();
$keywordIDtoKeyword = array();

$placeCoordsToInfo = array();

$regionalCooccurrences = 0;

$placesMentionedToStories = array();

//echo "processing geoRow info\n";

while($geoRow = mysql_fetch_assoc($geoRegionResult)) {

  $placeCount++;

  $placeID = $geoRow['place_id'];
  $placeName = $geoRow['name'];
  $matchedPlaces[$placeID] = 1;
  $placeCoords = $geoRow['lat'] . "|" . $geoRow['lon'];

  if (!isset($placeCoordsToInfo[$placeCoords])) {
    $placeCoordsToInfo[$placeCoords] = array();
  }
  
  if (!isset($placeCoordsToInfo[$placeCoords]['placeNames'])) {
    $placeCoordsToInfo[$placeCoords]['placeNames'] = array($placeName); 
  } else {
    if (!in_array($placeName, $placeCoordsToInfo[$placeCoords]['placeNames'])) {
      $placeCoordsToInfo[$placeCoords]['placeNames'][] = $placeName;
    }
  }
  
  if (!isset($placeCoordsToInfo[$placeCoords]['placeIDs'])) {
    $placeCoordsToInfo[$placeCoords]['placeIDs'] = array($placeID); 
  } else {
    if (!in_array($placeID, $placeCoordsToInfo[$placeCoords]['placeIDs'])) {
      $placeCoordsToInfo[$placeCoords]['placeIDs'][] = $placeID;
    }
  }
  
  //echo $placeCount . ": " . $geoRow['name'] . " (#" . $placeID . "): " . $geoRow['lat'] . " N, " . $geoRow['lon'] . " E\n";

  $storiesQuery = 'SELECT DISTINCT story_id from story_to_place_mentioned WHERE place_id=' . $placeID;
  $storiesResult = mysql_query($storiesQuery) or die("Stories query failed " . mysql_error() . "\n");
  
  while ($storiesRow = mysql_fetch_assoc($storiesResult)) {
    $storyID = $storiesRow['story_id'];
    
//    if (!in_array($storyID, $placesMentionedToStories[$placeID])) {
//      $placesMentionedToStories[$placeCID][] = $storyID;
//    }
    if (!isset($placeCoordsToInfo[$placeCoords]['stories'])) {
      $placeCoordsToInfo[$placeCoords]['stories'] = array($storyID);
    } else {
      if (!in_array($storyID, $placeCoordsToInfo[$placeCoords]['stories'])) {
        $placeCoordsToInfo[$placeCoords]['stories'][] = $storyID;
      }
    }

  }

  $keywordsQuery = 'SELECT DISTINCT lemmatized_keywords.lemmatized_keyword_id, lemmatized_keywords.keyword FROM lemmatized_keywords, story_to_place_mentioned, story_to_lemmatized_keyword WHERE lemmatized_keywords.lemmatized_keyword_id=story_to_lemmatized_keyword.lemmatized_keyword_id AND story_to_lemmatized_keyword.story_id=story_to_place_mentioned.story_id AND story_to_place_mentioned.place_id=' .  $placeID;
  $keywordsResult = mysql_query($keywordsQuery) or die("Keywords query failed " . mysql_error() . "\n");
  
  while ($keywordsRow = mysql_fetch_assoc($keywordsResult)) {
    
    if (!isset($placeCoordsToInfo[$placeCoords]['keywords'])) {
      $placeCoordsToInfo[$placeCoords]['keywords'] = $keywordsRow['keyword'];
    } else {
      $placeCoordsToInfo[$placeCoords]['keywords'] .= ", " . $keywordsRow['keyword'];
    }

    if (!isset($inKeywords[$keywordsRow['lemmatized_keyword_id']])) {
      $inKeywords[$keywordsRow['lemmatized_keyword_id']] = 1;
      $keywordIDtoKeyword[$keywordsRow['lemmatized_keyword_id']] = $keywordsRow['keyword'];
      $regionalCooccurrences++;
    } else {
      $inKeywords[$keywordsRow['lemmatized_keyword_id']]++;
      $regionalCooccurrences++;
    }
  }
}

//echo "Completed processing geoRows\n";

foreach ($placeCoordsToInfo as $placeCoords => $infoArray) {

  $placeCoordsArray = explode('|', $placeCoords);

  $returnVals['points']['features'][] = array("type" => "Feature", "geometry" => array("type" => "Point", "coordinates" => array((float)$placeCoordsArray[1], (float)$placeCoordsArray[0])), "properties" => array('keywords' => $infoArray['keywords'], 'placeNames' => implode(", ", $infoArray['placeNames']), 'placeIDs' => implode(", ", $infoArray['placeIDs']), 'stories' => implode(" ", $infoArray['stories'])));
//  $returnVals['points']['features'][] = array("type" => "Feature", "geometry" => array("type" => "Point", "coordinates" => array((int)$placeCoordsArray[1], (int)$placeCoordsArray[0])));

}

//echo "MOST COMMON KEYWORDS IN THIS REGION (# stories):\n";

$returnVals['raw'] = "<p><b>Raw keyword counts:</b><br>";

arsort($inKeywords);
$i=0;
foreach ($inKeywords as $keywordID => $total) {
  //echo $keywordIDtoKeyword[$keywordID] . " (#" . $keywordID . "): " . $total ."\n";
  $returnVals['raw'] .= $i+1 . ". " . $keywordIDtoKeyword[$keywordID] . ": " . $total ." times in region<br>";
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

  $globalKeywordQuery = "SELECT place_cooccurrences FROM lemmatized_keywords WHERE lemmatized_keyword_id=".$keywordID;
  $globalKeywordResult = mysql_query($globalKeywordQuery) or die("Global keyword query failed " . mysql_error() . "\n");
  if ($globalKeywordRow = mysql_fetch_assoc($globalKeywordResult)) {
    $globalKeywordCount = $globalKeywordRow['place_cooccurrences'];
    $globalKeywords[$keywordID] = $globalKeywordCount;

    $rfipf = ($total/$regionalCooccurrences) * log($totalPlacesMentioned / $globalKeywordCount);

    $RFIPF[$keywordID] = $rfipf;
  }
}

arsort($RFIPF);

$returnVals['rfipf'] = "<p><b>RF-IPF scores:</b><br>";

//echo "KEYWORDS BY RF-IPF (there are " . $totalPlacesMentioned . " total places mentioned)\n";
$i=0;
foreach ($RFIPF as $keywordID => $rfidf) {
  //echo $keywordIDtoKeyword[$keywordID] . " (#" . $keywordID . "): " . $rfidf . " (" . $inKeywords[$keywordID] . " times in region, " . $globalKeywords[$keywordID] . " times globally)\n";
  $returnVals['rfipf'] .= $i+1 . ". " . $keywordIDtoKeyword[$keywordID] . ": " . round($rfidf, 6) . " (" . $inKeywords[$keywordID] . " times in region, " . $globalKeywords[$keywordID] . " times globally)<br>";
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
$returnVals['normalized'] = "<p><b>Normalized by global frequency:</b><br>";
$i=0;
foreach ($normalizedCounts as $keywordID => $norm) {
  //echo $keywordIDtoKeyword[$keywordID] . " (#" . $keywordID . "): " . $norm . " (" . $inKeywords[$keywordID] . " times in region, " . $globalKeywords[$keywordID] . " times globally)\n";
  $returnVals['normalized'] .= $i+1 . ". " . $keywordIDtoKeyword[$keywordID] . ": " . round($norm, 2) . " (" . $inKeywords[$keywordID] . " times in region, " . $globalKeywords[$keywordID] . " times globally)<br>";
  if (++$i >= $MAX_DISPLAY_KEYWORDS) {
    break;
  }
}

echo json_encode($returnVals);
return;
