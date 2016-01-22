<?php

function debugMode() {
    return true;
}
function debugPrint($mailMsg) {
    echo "<br>" . $mailMsg . "<br>";
}

function printOutput() {
    return false;
}

// calculates great circle distance
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $d = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon1 - $lon2));
    return (acos($d) * 6371.0);
}

include "Geotagger.php";

if (count($argv) != 3) {
    echo "Error: wrong number of command line arguments.";
    exit(1);
}

$inputFileName = $argv[1];
$outputFileName = $argv[2];

$inputFile = fopen($inputFileName, "r");
$outputFile = fopen($outputFileName, "w");
fwrite($outputFile, "id\ttype\tDisambiguator\tTermType\tActualLat\tActualLon\t");
fwrite($outputFile, "ParserTime\tNominatimTime\tDisambiguationTime\t");
fwrite($outputFile, "PhraseCount\tBestCalculatedLat\tBestCalculatedLon\t");
fwrite($outputFile, "DistanceToResult1\tDistanceToResult2\tDistanceToResult3\tDistanceToResult4\tDistanceToResult5\t");
fwrite($outputFile, "ScoreOfResult1\tScoreOfResult2\tScoreOfResult3\tScoreOfResult4\tScoreOfResult5\n");


while (!feof($inputFile)) {
    $tagger = new Geotagger();
    
    $line = str_replace("\r", "", str_replace("\n", "", fgets($inputFile)));
    if (strlen($line) < 1) {
        continue;
    }
    $lineSplit = explode("\t", $line);
    if (count($lineSplit) != 5) {
        echo ("Error: input file should have 5 tab-delimited columns. Found a line with " . count($lineSplit) . "\n");
        echo ("Offending line: " . $line);
        exit(2);
    }
    $id = $lineSplit[0];
    $text = $lineSplit[1];
    $type = $lineSplit[2];
    $realLat = $lineSplit[3];
    $realLon = $lineSplit[4];

    echo ("Processing page with id " . $id . "\n");
    $disambigatedTermArrays = $tagger->runWithAllDisambiguators($text);
    if (! $disambigatedTermArrays) {
        fwrite($outputFile, $id . "\t");
        fwrite($outputFile, $type . "\t");
        fwrite($outputFile, "\t\t");
        fwrite($outputFile, $realLat . "\t" . $realLon . "\t");
        fwrite($outputFile, "ERROR\t");
        fwrite($outputFile, "\t\t\t\t\t\t\t\t\t\t\t\t\t\t\n");
        continue;
    }

    foreach ($disambigatedTermArrays as $disambiguatorName => $disambiguatorRVal) {
        fwrite($outputFile, $id . "\t");
        fwrite($outputFile, $type . "\t");
        fwrite($outputFile, $disambiguatorName . "\t");
        fwrite($outputFile, $disambiguatorRVal["TermType"] . "\t");
        fwrite($outputFile, $realLat . "\t" . $realLon . "\t");
        fwrite($outputFile, $disambiguatorRVal["ParserTime"] . "\t");
        fwrite($outputFile, $disambiguatorRVal["NominatimTime"] . "\t");
        fwrite($outputFile, $disambiguatorRVal["DisambiguationTime"] . "\t");
        if ($disambiguatorRVal["TermArray"]) {
            // the TermArray is false if the disambiguator timed out
            $results = $disambiguatorRVal["SortedResults"];
            $scores = $disambiguatorRVal["Scores"];
            $phraseCount = count($results);
            fwrite($outputFile, $phraseCount . "\t");
            if ($results) {
                $calcLat = $results[0]["lat"];
                $calcLon = $results[0]["lon"];
                fwrite($outputFile, $calcLat . "\t" . $calcLon);
            } else {
                fwrite($outputFile, "N/A\tN/A");
            }
            for ($i = 0; $i < min($phraseCount, 5); $i++) {
                fwrite($outputFile, "\t" . calculateDistance($realLat, $realLon, $results[$i]["lat"], $results[$i]["lon"]));
            }
            for ($i = $phraseCount; $i < 5; $i++) {
                fwrite($outputFile, "\t");
            }
            for ($i = 0; $i < min($phraseCount, 5); $i++) {
                fwrite($outputFile, "\t" . $scores[$i]);
            }
            for ($i = $phraseCount; $i < 5; $i++) {
                fwrite($outputFile, "\t");
            }
        }
        fwrite($outputFile, "\n");
    }
}

fclose($inputFile);
fclose($outputFile);

