<?php

include 'PHP-Stanford-NLP/autoload.php';
include 'Terms.php';

// This class takes text and produces an array of terms
class TextParser {

    public $times = []; // keeps track of how long different parts of the program take

    function construct() {
        $this->termTypeUsed = "";
    }

    function run($text) {

        // The following appear in some Wikipedia texts and mess up the parsing:
        $textsToRemove = ["<br>","</table>","</dl>","</ref>",
                          "<ns>","</ns>","<id>","</id>","<small>",
                          "<revision>","<comment>","</comment>",
                          "<model>","</model>","<parentid>","</parentid>"];

        $text = str_replace($textsToRemove, "", $text);

        $currentDir = getcwd(); // needed for absolute paths to Stanford models
        
        $termArray = new TermArray();
        
        $startTime = microtime(true);

        if (! function_exists("splitIntoWords")) {
            // The POS and NER taggers need an array of arrays, where each sentence is
            // it's own array.
            function splitIntoWords($sentence) {
                return explode(' ', $sentence);
            }
        }

        $text_arrays = array_map("splitIntoWords", explode('.', $text));
        
        // Send the text to the POS tagger:
        $pos = new \StanfordNLP\POSTagger(
          $currentDir . '/stanford-postagger-2015-04-20/models/english-left3words-distsim.tagger',
          $currentDir . '/stanford-postagger-2015-04-20/stanford-postagger.jar'
        );
        $startTime = microtime(true);
        $resultPOS = $pos->batchTag($text_arrays)[0];
        $this->times["Run the POS tagger"] = (microtime(true) - $startTime);
        if (printOutput()) {
            echo "<br>POS results:<br>";
            var_dump($resultPOS);
            echo "<br>";
        }
        if (! $resultPOS) {
            echo "<br>ERROR: POS tagging failed<br>";
            return false;
        }

        // Send the text to the NER:
        $ner = new \StanfordNLP\NERTagger(
          $currentDir . '/stanford-ner-2015-04-20/classifiers/english.all.3class.distsim.crf.ser.gz',
          $currentDir . '/stanford-ner-2015-04-20/stanford-ner.jar'
        );
        $startTime = microtime(true);
        $resultNER = $ner->batchTag($text_arrays)[0];
        $this->times["Run the NER tagger"] = (microtime(true) - $startTime);
        if (printOutput()) {
            echo "<br>NER results:<br>";
            var_dump($resultNER);
            echo "<br><br>";
        }
        if (! $resultNER) {
            echo "<br>ERROR: NER tagging failed<br>";
            return false;
        }

        // Later code assumes that $resultPOS and $resultNER are indexed identically.
        // I have only seen these errors returned when the text contains something like
        // "<br>" which is handled differently by each tagger
        if (count($resultPOS) != count($resultNER)) {
            echo "<br>ERROR: POS and NER tagging are not indexed the same!<br>";
            return false;
        }
        $words = [];
        for ($i = 0, $size = count($resultNER); $i < $size; $i++) {
            if ($resultPOS[$i][0] != $resultNER[$i][0]) {
                echo "<br>ERROR: POS and NER tagging are not indexed the same!<br>";
                return false;
            }
            $words[$i] = $resultNER[$i][0];
        }
        
        // The next bunch of code loops through the text to find all terms
        $startTime = microtime(true);
        $currentStreak = 0;
        $streakContainsLocation = false;
        $streakContainsNoun = false;
        $isAfterPreposition = false;
        $isAfterConjunction = false;
        for ($i = 0, $size = count($resultPOS); $i < $size; $i++) {
            $isNoun = (strncmp($resultPOS[$i][1],"NN",2) == 0);
            $isAdjectiveOrNumber = ((strcmp($resultPOS[$i][1],"CD") == 0) or  // number (so we can see an address)
                                    (strcmp($resultPOS[$i][1],"JJ") == 0));   // adjective (so "first avenue" would catch the first)
            $isLocation = (strcmp($resultNER[$i][1],"LOCATION") == 0);
            if ($isNoun or $isAdjectiveOrNumber or $isLocation) {
                $currentStreak++;
                if ($isLocation) {
                    $streakContainsLocation = true;
                }
                if ($isNoun) {
                    $streakContainsNoun = true;
                }
            } else {
                $streakContainsLocation = false;
                $streakContainsNoun = false;
                $currentStreak = 0;
                $isAfterConjunction = (strcmp($resultPOS[$i][1],"CC") == 0);
                if (! $isAfterConjunction) {
                    // reset $isAfterPreposition only if this is not after a conjuction
                    // that way a text like "near Waterloo and Guelph" will tag both Waterloo
                    // and Guelph as after a preposition
                    $isAfterPreposition = ((strcmp($resultPOS[$i][1],"IN") == 0) or (strcmp($resultPOS[$i][1],"TO") == 0));
                    if (strcmp($resultPOS[$i][0],"for") == 0) { // TODO: make this case insensitive?
                        $isAfterPreposition = false;
                    }
                }
            }
            if ($streakContainsLocation or $streakContainsNoun) {
                $phrase = $resultPOS[$i][0];
                $subStreakContainsNoun = $isNoun;
                $subStreakContainsLocation = $isLocation;
                if ($isNoun or $isLocation) {
                    $newTerm = $termArray->addTerm($phrase, $i);
                    $newTerm->isNoun = $isNoun;
                    $newTerm->isLocation = $isLocation;
                    $newTerm->isAfterPreposition = $isAfterPreposition;
                }
                
                for ($j = 1; $j < $currentStreak; $j++) {
                    $phrase = $resultPOS[$i-$j][0] . ' ' . $phrase;
                    $subStreakContainsNoun = ($subStreakContainsNoun or (strncmp($resultPOS[$i-$j][1],"NN",2) == 0));
                    $subStreakContainsLocation = ($subStreakContainsLocation or (strcmp($resultNER[$i-$j][1],"LOCATION") == 0));
                    if ($subStreakContainsLocation or $subStreakContainsNoun) {
                        $newTerm = $termArray->addTerm($phrase, ($i - $j));
                        $newTerm->isNoun = $subStreakContainsNoun;
                        $newTerm->isLocation = $subStreakContainsLocation;
                        $newTerm->isAfterPreposition = $isAfterPreposition;
                    }
                }
            }
        }
        $this->times["Loop through text to find locations"] = (microtime(true) - $startTime);

        // Now we remove some terms from the array:
        $startTime = microtime(true);
        if ($termArray->terms) {
            if ($termArray->countLocations() > 0) {
                if (printOutput()) {
                    echo "<br>This text contains words tagged as locations, so we will only consider those words.<br>";
                }
                $termArray->removeNouns(false);
                $this->termTypeUsed = "Locations";
            } else {
                if (printOutput()) {
                    echo "<br>This text does not contain words tagged as locations, so we must only use nouns.<br>";
                }
                if ($termArray->countNounsAfterPrepositions() > 0) {
                    if (printOutput()) {
                        echo "Some nouns occured after prepositions, so we will only use those.<br>";
                    }
                    $termArray->removeNouns(true);
                    $this->termTypeUsed = "NounsAfterPrep";
                } else {
                    $this->termTypeUsed = "Nouns";
                }
            }
        } else {
            echo "Warning: no nouns or locations found in text.";
            $this->termTypeUsed = "None";
        }
        $this->times["Filter terms that are found"] = (microtime(true) - $startTime);

        // the rest of this code deals with postal codes
        $startTime = microtime(true);
        $CanadaPostCodes = [];
        $USZipCodes = [];
        $DutchPostCodes = [];
        preg_match_all('/\b[a-zA-Z][0-9][a-zA-Z][\s]?[0-9][a-zA-Z][0-9]\b/', $text, $CanadaPostCodes);
        preg_match_all('/\b[0-9]{5}([\s\-][0-9]{4})?\b/', $text, $USZipCodes);
        preg_match_all('/\b[0-9]{4}[\s]?[a-zA-Z]{2}\b/', $text, $DutchPostCodes);
        $this->times["Find postal codes in the text"] = (microtime(true) - $startTime);
        $startTime = microtime(true);
        foreach ($CanadaPostCodes[0] as $postcode) {
            if (isset($termArray->terms[$postcode])) {
                // if the postcode already got in another way, we don't add it again
                foreach ($termArray->terms[$postcode] as $term) {
                    $term->isPostcode = true;
                    $term->postcodeCountry = "ca";
                }
            } else {
                $positions = [-10];
                $postcodeWords = explode(" ", $postcode);
                // all post codes have 1 or 2 words
                $firstWordPositions = array_keys($words, $postcodeWords[0]);
                if (count($postcodeWords) == 1) {
                    $positions = $firstWordPositions;
                } else {
                    foreach ($firstWordPositions as $firstWordPosition) {
                        if ($words[$firstWordPosition + 1] == $postcodeWords[1]) {
                            if ($positions == [-10]) {
                                $positions = [$firstWordPosition];
                            } else {
                                $postions[] = $firstWordPosition;
                            }
                        }
                    }
                }
                foreach ($positions as $postcodePosition) {
                    $newTerm = $termArray->addTerm($postcode, $postcodePosition);
                    $newTerm->isPostcode = true;
                    $newTerm->postcodeCountry = "ca";
                }
            }
        }
        foreach ($USZipCodes[0] as $postcode) {
            if (isset($termArray->terms[$postcode])) {
                // if the postcode already got in another way, we don't add it again
                foreach ($termArray->terms[$postcode] as $term) {
                    $term->isPostcode = true;
                    $term->postcodeCountry = "us";
                }
            } else {
                $positions = [-10];
                $postcodeWords = explode(" ", $postcode);
                // all post codes have 1 or 2 words
                $firstWordPositions = array_keys($words, $postcodeWords[0]);
                if (count($postcodeWords) == 1) {
                    $positions = $firstWordPositions;
                } else {
                    foreach ($firstWordPositions as $firstWordPosition) {
                        if ($words[$firstWordPosition + 1] == $postcodeWords[1]) {
                            if ($positions == [-10]) {
                                $positions = [$firstWordPosition];
                            } else {
                                $postions[] = $firstWordPosition;
                            }
                        }
                    }
                }
                foreach ($positions as $postcodePosition) {
                    $newTerm = $termArray->addTerm($postcode, $postcodePosition);
                    $newTerm->isPostcode = true;
                    $newTerm->postcodeCountry = "us";
                }
            }
        }
        foreach ($DutchPostCodes[0] as $postcode) {
            if (isset($termArray->terms[$postcode])) {
                // if the postcode already got in another way, we don't add it again
                foreach ($termArray->terms[$postcode] as $term) {
                    $term->isPostcode = true;
                    $term->postcodeCountry = "nl";
                }
            } else {
                $positions = [-10];
                $postcodeWords = explode(" ", $postcode);
                // all post codes have 1 or 2 words
                $firstWordPositions = array_keys($words, $postcodeWords[0]);
                if (count($postcodeWords) == 1) {
                    $positions = $firstWordPositions;
                } else {
                    foreach ($firstWordPositions as $firstWordPosition) {
                        if ($words[$firstWordPosition + 1] == $postcodeWords[1]) {
                            if ($positions == [-10]) {
                                $positions = [$firstWordPosition];
                            } else {
                                $postions[] = $firstWordPosition;
                            }
                        }
                    }
                }
                foreach ($positions as $postcodePosition) {
                    $newTerm = $termArray->addTerm($postcode, $postcodePosition);
                    $newTerm->isPostcode = true;
                    $newTerm->postcodeCountry = "nl";
                }
            }
        }
        $this->times["Update metadata for post codes"] = (microtime(true) - $startTime);
        return $termArray;
    }
}
