<?php

ini_set("auto_detect_line_endings", true);
header('Access-Control-Allow-Origin: *');

# Connect to the database
$dblink = mysql_connect("localhost:3306", "dfl", "Dfl#123!");
@mysql_select_db("dfl2db", $dblink);
mysql_set_charset("UTF8", $dblink);

function addToHash($key, $val, &$hash) {
  if (array_key_exists($key, $hash)) {
    $hash[$key][] = $val;
  } else {
    $hash[$key] = array($val);
  }
}

$storiesString = htmlspecialchars($_GET["stories"]);
//$vectorName = "ETK_churches";
$storiesArray = split("-", $storiesString);

$jsonOut = array();
$jsonOut['stories'] = array();

foreach ($storiesArray as $storyID) {

  $storyQuery = "SELECT story_pub_info.pub_info, story_pub_info.story_id, story_pub_info.danish_publication, etk_index_merged.heading_danish FROM story_pub_info, story_to_etk_index_merged, etk_index_merged WHERE story_pub_info.story_id=story_to_etk_index_merged.story_id AND story_to_etk_index_merged.etk_index_id=etk_index_merged.etk_merged_id AND story_pub_info.story_id=" . $storyID;
  $storyResult = mysql_query($storyQuery) or die("Story query failed " . mysql_error() . "\n");
  $storyRow = mysql_fetch_assoc($storyResult);

  $danishText = $storyRow['danish_publication'];

  $collectionDetailsArray = array();

  $collectionQuery = "SELECT informants.informant_id AS informant_id, informants.name AS informant_name, IFNULL(story_to_informant.place_id, 'NULL') as place_collected FROM story_to_informant, informants WHERE story_to_informant.informant_id=informants.informant_id AND story_to_informant.story_id=" . $storyID;
  $collectionResult = mysql_query($collectionQuery) or die("Collection query failed " . mysql_error() . "\n");
  while ($collectionRow = mysql_fetch_assoc($collectionResult)) {
    $collectionPlaceString = "none";
    if ($collectionRow['place_collected'] != "NULL") {
      $placeQuery = "SELECT name, lon, lat FROM geo_places WHERE place_id=" . $collectionRow['place_collected'];
      $placeResult = mysql_query($placeQuery) or die("Place query failed " . mysql_error() . "\n");
      if ($placeRow = mysql_fetch_assoc($placeResult)) {
        $collectionPlaceString = $collectionRow['place_collected'] . ' (' . $placeRow['name'] . ', ' . $placeRow['lat'] . 'N, ' . $placeRow['lon'] . 'E)' ;
      }
    }
    $collectionDetailsArray[] = '<b> Informant:</b> ' . $collectionRow['informant_id'] . ' (' . $collectionRow['informant_name'] . '), <b>collection place:</b> ' . $collectionPlaceString;
  }
  
  $collectionDetails = implode("<br>", $collectionDetailsArray);

  $mentionedDetailsArray = array();

  $mentionedQuery = "SELECT geo_places.place_id, geo_places.name, geo_places.lon, geo_places.lat FROM story_to_place_mentioned, geo_places WHERE story_to_place_mentioned.place_id=geo_places.place_id AND story_to_place_mentioned.story_id=" . $storyID;
  $mentionedResult = mysql_query($mentionedQuery) or die("Mentioned query failed " . mysql_error() . "\n");
  while ($mentionedRow = mysql_fetch_assoc($mentionedResult)) {
    $placeName = $mentionedRow['name'];
    $danishText = str_replace($placeName, '<b>'.$placeName.'</b>', $danishText);
    $mentionedDetailsArray[] = '<b>Place mentioned:</b> ' . $mentionedRow['place_id'] . ' (' . $mentionedRow['name'] . ', ' . $mentionedRow['lat'] . 'N, ' . $mentionedRow['lon'] . 'E)';
  }
  
  $mentionedDetails = implode("<br>", $mentionedDetailsArray);

  $storyData = array();

  $storyData['story_id'] = $storyID;
  $storyData['pub_info'] = $storyRow['pub_info'];
  $storyData['etk_index'] = $storyRow['heading_danish'];
  $storyData['danish_publication'] = $danishText;
  $storyData['collection_details'] = $collectionDetails;
  $storyData['places_mentioned'] = $mentionedDetails;
  
  $jsonOut['stories'][] = $storyData;

}

echo json_encode($jsonOut);

return;

?>
