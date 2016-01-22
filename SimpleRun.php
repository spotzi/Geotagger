<?php

/* This is a simple script to show how to run the geotagger.
 *
 * It is run from the command line,. The first command line argument
 * is the text you wish to locate.
 *
 * The optional arguments after are a range of latitude and longitude
 * coordinates to limit the results. The coordinates must be given in
 * the order: West, North, East, South
 *
 * Usage: php -f SimpleRun.php "<text>" [<west> <north> <east> <south>]
 *
 * Examples:
 * php -f SimpleRun.php "I am in Waterloo."
 * php -f SimpleRun.php "I am in London." -82.0 43.5 -81.0 42.5
*/

//**********************************************************************

// These are just some functions that need to be defined for the program
// to run.

// These two are needed for Connectivity.php
function debugMode() { return true; }
function debugPrint($mailMsg) { echo "<br>" . $mailMsg . "<br>"; }

// make this return true if you want the html output from my program
function printOutput() { return false; }

//**********************************************************************

// Here is where the magic happens

include "Geotagger.php";

// As I make the code more efficient and readable I might change how
// this works, but for now this is up to date.

$text = $argv[1]; // get the text from the command line
$boundingBox = [];
if (count($argv) > 2) {
    $boundingBox["west"] = $argv[2];
    $boundingBox["north"] = $argv[3];
    $boundingBox["east"] = $argv[4];
    $boundingBox["south"] = $argv[5];
}

$tagger = new Geotagger();
$results = $tagger->SimpleRun($text, $boundingBox);

// $results is now an array of search results from Nominatim, sorted in
// order by score from the disambiguator

if ($results) {
    // print out the first result:
    echo ("Best Result: " . $results[0]["display_name"] . "\n");
    echo ("Coordinates: " . $results[0]["lat"] . "," . $results[0]["lon"] . "\n");
} else {
    echo "No results found.\n";
}
