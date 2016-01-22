<?php

//$text = htmlspecialchars($_POST['text']);
$text = $_POST['text'];
$boundingBox = [];
foreach (["west", "north", "east", "south"] as $direction){
    if ($_POST[$direction]) {
        $boundingBox[$direction] = $_POST[$direction];
    }
}
if (count($boundingBox) < 4) {
    if ($boundingBox) {
        echo "Error: not all arguments specified for bounding box, so ignoring bounds";
    }
    $boundingBox = [];
}

include "Geotagger.php";

function debugMode() {
    return true;
}

function debugPrint($mailMsg) {
    echo "<br>" . $mailMsg . "<br>";
}

function printOutput() {
    return true;
}

set_time_limit(0);

$tagger = new Geotagger();

echo "Input text: " . $text . "<br><br>";

$tagger->run($text, $boundingBox);
$tagger->printTimes();
