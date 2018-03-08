<?php 
ini_set("auto_detect_line_endings", true);
header('Access-Control-Allow-Origin: *');

$debug = false;
$totalTopicWords = 30;

function dprint($instring) {
  global $debug;
  if ($debug == true)
    echo $instring;
}

if ($debug == false) {
  $dataDir = htmlspecialchars($_GET["datadir"]);
} else {
  $dataDir = "ETKdata";
  //$dataDir = "KeywordData";
}

// XXX Add a "word_aliases" file (to map secondary indices to their
// primary index + secondary index titles
$wordAlias = array();
if (file_exists($dataDir . "/word_aliases.txt")) {
  $wordAliasData = file($dataDir . "/word_aliases.txt", FILE_IGNORE_NEW_LINES);
  foreach ($wordAliasData as $wordAliasLine) {
    $waa = explode("\t", $wordAliasLine);
    $wordAlias[$waa[0]] =  $waa[1];
  }
}

dprint("checking topic labels file\n");

// XXX Similarly, add a "topic_labels" file here
$topicLabel = array();
if (file_exists($dataDir . "/topic_labels.txt")) {
  dprint("found topic labels file\n");
  $topicLabelData = file($dataDir . "/topic_labels.txt", FILE_IGNORE_NEW_LINES);
  foreach ($topicLabelData as $topicLabelLine) {
    $tla = explode(" ", $topicLabelLine);
    $topicLabel[$tla[0]] = implode(" ", array_slice($tla, 1));
    dprint("label for topic " . $tla[0] . " is " . $topicLabel[$tla[0]] . "\n");
  }
}

$wordsFile = fopen($dataDir . "/geolda_tag_freqs.txt", "r");

$wordIDtoWord = array();

$wordID = 0;
while ($wordLine = fgetcsv($wordsFile, 0, " ")) {
  $thisWord = implode(" ", array_slice($wordLine, 0, -1));
  if (isset($wordAlias[$thisWord]))
    $thisWord = $wordAlias[$thisWord];
  $wordID++;
  $wordIDtoWord[$wordID] = $thisWord;
  // Do anything with the global frequency value?
}

fclose($wordsFile);

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

dprint("GEO-TOPICS:\n");

$json = '{ "topicWords": [';

$jsonTopics = array();

foreach ($topicWordPercent as $topicID => $topicWordPercentages) {

  if (isset($topicLabel[$topicID]))
    $topicName = $topicLabel[$topicID];
  else
    $topicName = $topicID;

  $topicJSON = '{"topicID": "' . $topicID . '", "topicLabel": ' . json_encode($topicName) . ', "words": [';

  dprint("TOPIC " . $topicID . "\n");

  $wordsJSON = array();

  arsort($topicWordPercentages);

  $topicWordCount = 0;

  foreach ($topicWordPercentages as $wordID => $wordPercent) {
    if ($wordPercent == 0)
      continue;
      
    $topicWordCount++;

    $wordsJSON[] = '{"word": ' . json_encode($wordIDtoWord[$wordID]) . ', "wordPercent": ' . json_encode($wordPercent . "%") . '}';
    
    if ($topicWordCount <= $totalTopicWords) 
      dprint("\t" . $wordIDtoWord[$wordID] . ": " . $wordPercent . "%\n");

  }
  $wordsJSONString = implode(",", $wordsJSON);
  
  $topicJSON .= $wordsJSONString . ']}';

  $jsonTopics[] = $topicJSON;

}

$topicJSONString = implode(",", $jsonTopics);

$json .= $topicJSONString . ']}';
//, {"properties": {"itemName": "wordsInTopics"}}';
//$json .= '}';
/*
$ob = json_decode($json);
if($ob === null) {
  exit;
}
 */
if ($debug == false)
  echo $json;

return;

?>
