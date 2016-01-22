<?php

// A term who's phrase will be sent to Nominatim
class Term {
    public $position = -1;
    public $numWords = 0;
    public $phrase = "";
    public $isNoun = false;
    public $isLocation = false;
    public $isAfterPreposition = false;
    public $isPostcode = false;
    public $postcodeCountry = "";
    
    function __construct($phrase, $pos) {
        $this->position = $pos;
        $this->phrase = $phrase;
        $this->numWords = count(explode(' ', $phrase));
    }
}

// holds all the terms
class TermArray {
    
    public $terms = [];
    public $results = [];
    
    function addTerm($phrase, $position) {
        $newTerm = new Term($phrase, $position);
        $this->terms[$phrase][$position] = $newTerm;
        return $newTerm;
    }
    
    function removeTerm($phrase, $position) {
        unset($this->terms[$phrase][$position]);
        if (count($this->terms[$phrase]) == 0) {
            unset($this->terms[$phrase]);
            if (isset($results[$phrase])) {
                unset($results[$phrase]);
            }
        }
    }
    
    function removePhrase($phrase) {
        // removes all occurences of a particular phrase
        foreach ($this->terms[$phrase] as $position => $p) {
            $this->removeTerm($phrase, $position);
        }
    }
    
    function getAllTerms() {
        $rval = [];
        foreach ($this->terms as $arrayOfTermsWithSamePhrase) {
            $rval = array_merge($rval, $arrayOfTermsWithSamePhrase);
        }
        return $rval;
    }
    
    // if a term has no search results from Nominatim, then we delete it
    function removeTermsWithoutSearchResults() {
        foreach ($this->results as $phrase => $phraseResults) {
            if (count($phraseResults) < 1) {
                foreach ($this->terms[$phrase] as $position => $term) {
                    $this->removeTerm($phrase, $position);
                }
            }
        }
    }
    
    function getAllPhrases() {
        return array_keys($this->terms);
    }
    
    // add Nominatim search results
    function setResults($phrase, $results) {
        $this->results[$phrase] = $results;
    }
    
    // counts how often $phrase is found as a noun
    function countNounOccurences($phrase) {
        $rval = 0;
        foreach ($this->terms[$phrase] as $term) {
            if ($term->isNoun) {
                $rval++;
            }
        }
        return $rval;
    }
    
    function countNouns() {
        $rval = 0;
        foreach ($this->terms as $arrayOfTermsWithSamePhrase) {
            foreach ($arrayOfTermsWithSamePhrase as $term) {
                if ($term->isNoun) {
                    $rval++;
                }
            }
        }
        return $rval;
    }
    
    function countNounsAfterPrepositions() {
        $rval = 0;
        foreach ($this->terms as $arrayOfTermsWithSamePhrase) {
            foreach ($arrayOfTermsWithSamePhrase as $term) {
                if ($term->isNoun and $term->isAfterPreposition) {
                    $rval++;
                }
            }
        }
        return $rval;
    }
    
    // counts how often $phrase is seen as a location
    function countLocationOccurences($phrase) {
        $rval = 0;
        foreach ($this->terms[$phrase] as $term) {
            if ($term->isLocation) {
                $rval++;
            }
        }
        return $rval;
    }
    
    function countLocations() {
        $rval = 0;
        foreach ($this->terms as $arrayOfTermsWithSamePhrase) {
            foreach ($arrayOfTermsWithSamePhrase as $term) {
                if ($term->isLocation) {
                    $rval++;
                }
            }
        }
        return $rval;
    }
    
    function countLocationsAfterPrepositions() {
        $rval = 0;
        foreach ($this->terms as $arrayOfTermsWithSamePhrase) {
            foreach ($arrayOfTermsWithSamePhrase as $term) {
                if ($term->isLocation and $term->isAfterPreposition) {
                    $rval++;
                }
            }
        }
        return $rval;
    }
    
    // counts how often $phrase is after a preposition
    function countAfterPrepositionOccurences($phrase) {
        $rval = 0;
        foreach ($this->terms[$phrase] as $term) {
            if ($term->isAfterPreposition) {
                $rval++;
            }
        }
        return $rval;
    }
    
    // remove terms that were only classified as nouns
    // if keepAfterPrepositions is true, nouns that occur after prepositions
    //  will not be removed
    function removeNouns($keepAfterPrepositions = false) {
        foreach ($this->terms as $phrase => $termsWithPhrase) {
            foreach ($termsWithPhrase as $position => $term) {
                if (! $term->isLocation) {
                    if (! ($keepAfterPrepositions and $term->isAfterPreposition)) {
                        $this->removeTerm($phrase, $position);
                    }
                }
            }
        }
    }
    
    function PrintAllResults($tagger) {
        foreach ($this->terms as $phrase => $arrayOfTermsWithSamePhrase) {
            $phraseResults = $this->results[$phrase];
            // each term should have the same search results
            echo ($phrase . ": recognized " . $this->countNounOccurences($phrase) .
                  " time(s) as a noun/adjective/number, " . $this->countLocationOccurences($phrase) .
                  " time(s) as a location, and occured " . $this->countAfterPrepositionOccurences($phrase) .
                  " time(s) after a preposition.<br>");
            echo "&nbsp&nbspNominatim results:<br>";
            if ($phraseResults) {
                foreach ($phraseResults as $result) {
                    echo $tagger->NominatimResultToString($result, "&nbsp&nbsp&nbsp&nbsp");
                }
            } else {
                echo "&nbsp&nbsp&nbsp&nbspNo results found for this word";
            }
            echo "<br>";
        }
    }

    // find groups of intersecting terms for the weight calculation
    // returns an array where the key is the starting index and the value
    //   is the length of the group of words
    function findGroups() {
        $rval = [];
        // this needs to be done twice to work properly
        for ($repeat = 0; $repeat < 2; $repeat++) {
            foreach ($this->terms as $arrayOfTermsWithSamePhrase) {
                foreach ($arrayOfTermsWithSamePhrase as $position => $term) {
                    if ($term->numWords > 1) {
                        if ((!(isset($rval[$position]))) or ($rval[$position] < $term->numWords)) {
                            $rval[$position] = $term->numWords;
                        }
                        for ($i = 1; $i < $term->numWords; $i++) {
                            if (isset($rval[$term->position + $i])) {
                                if ($rval[$position + $i] > ($term->numWords - $i)) {
                                    $rval[$position] = ($i + $rval[$position + $i]);
                                }
                                unset($rval[$position + $i]);
                            }
                        }
                    }
                }
            }
        }
        return $rval;
    }

    // returns an array. The length of the array is the number of interpretations,
    // each element in the array gives the number of terms in the interpretation
    function weightHelper($terms) {
        $terms = array_values($terms); // reset keys in array
        switch (count($terms)) {
            case 0:
                return [0];
            case 1:
                return [1];
            default:
                $longestTermIndex = 0;
                foreach ($terms as $index => $term) {
                    if (($term->numWords) > ($terms[$longestTermIndex]->numWords)) {
                        $longestTermIndex = $index;
                    }
                }
                $longTerm = $terms[$longestTermIndex];
                $longTermStart = $longTerm->position;
                $longTermEnd = ($longTerm->position + $longTerm->numWords - 1);
                unset($terms[$longestTermIndex]); // we recursively look at cases with and without the longest term
                $termsAfterChoosingLongTerm = $terms;
                foreach ($termsAfterChoosingLongTerm as $index => $term) {
                    if (!(($term->position > $longTermEnd) or (($term->position + $term->numWords - 1) < $longTermStart))) {
                        unset($termsAfterChoosingLongTerm[$index]);
                    }
                }
                $rval = [];
                $withResults = $this->weightHelper($termsAfterChoosingLongTerm);
                foreach ($withResults as $termsInInterpretation) {
                    $rval[] = ($termsInInterpretation + 1);
                }
                if (count($termsAfterChoosingLongTerm) != count($terms)) {
                    // if we didn't find any overlapping terms, we don't need to check the "without" results
                    $withoutResults = $this->weightHelper($terms);
                    foreach ($withoutResults as $termsInInterpretation) {
                        $rval[] = $termsInInterpretation;
                    }
                }
                return $rval;
        }
    }
    
    // calculates weights for the given terms (or all terms if none are given)
    // If you are comparing this to the paper, these weights are W^{t_1}_{t_2}
    //   where t_1 is not in the same group as t_2.
    function getWeights($terms = []) {
        if (! $terms) {
            $terms = $this->getAllTerms();
        }
        $groups = $this->findGroups();
        $groupTerms = [];
        $weights = [];
        foreach ($groups as $startPosition => $length) {
            $groupTerms[$startPosition] = [];
        }
        // find which group each term belongs to:
        foreach ($terms as $term) {
            $foundGroup = false;
            if ($term->position >= 0) { // negative positions are used for terms we don't know the position of
                foreach ($groups as $startPosition => $length) {
                    if (($startPosition <= $term->position) and ($term->position < ($startPosition + $length))) {
                        $groupTerms[$startPosition][] = $term;
                        $foundGroup = true;
                        break;
                    }
                }
            }
            if (! $foundGroup) {
                $weights[$term->phrase][$term->position] = 1.0;
            }
        }
        
        foreach ($groupTerms as $startPosition => $termsInGroup) {
            $interpretationCount = count($this->weightHelper($termsInGroup));
            foreach ($termsInGroup as $termWeAreCalculating) {
                $remainingTerms = $termsInGroup;
                $termStart = $termWeAreCalculating->position;
                $termEnd = ($termWeAreCalculating->position + $termWeAreCalculating->numWords - 1);
                foreach ($remainingTerms as $index => $otherTerm) {
                    if (!(($otherTerm->position > $termEnd) or (($otherTerm->position + $otherTerm->numWords - 1) < $termStart))) {
                        unset($remainingTerms[$index]);
                    }
                }
                $helperResult = $this->weightHelper($remainingTerms);
                $weightSum = 0.0;
                foreach ($helperResult as $numberOfTermsInInterpretation) {
                    $weightSum += (1.0 / (1.0 + $numberOfTermsInInterpretation));
                }
                $weights[$termWeAreCalculating->phrase][$termWeAreCalculating->position] = ($weightSum / $interpretationCount);
            }
        }
        return $weights;
    }
    
    // If you are comparing this to the paper, these weights are W^{t_1}_{t_2}
    //   where t_1 is in the same group as t_2.
    function getAlternateWeights() {
        $groups = $this->findGroups();
        $groupTerms = [];
        $alternateWeights = [];
        foreach ($groups as $startPosition => $length) {
            $groupTerms[$startPosition] = [];
        }
        // find which group each term belongs to:
        foreach ($this->terms as $arrayOfTermsWithSamePhrase) {
            foreach ($arrayOfTermsWithSamePhrase as $position => $term) {
                if ($position >= 0) { // negative positions are used for terms we don't know the position of
                    foreach ($groups as $startPosition => $length) {
                        if (($startPosition <= $position) and ($position < ($startPosition + $length))) {
                            $groupTerms[$startPosition][] = $term;
                            break;
                        }
                    }
                }
            }
        }
        foreach ($groupTerms as $startPosition => $termsInGroup) {
            foreach ($termsInGroup as $termWeAreCalculating) {
                $remainingTerms = $termsInGroup;
                $termStart = $termWeAreCalculating->position;
                $termEnd = ($termWeAreCalculating->position + $termWeAreCalculating->numWords - 1);
                $alternateWeights[$termWeAreCalculating->phrase][$termWeAreCalculating->position][$termWeAreCalculating->phrase][$termWeAreCalculating->position] = 1.0;
                foreach ($remainingTerms as $index => $otherTerm) {
                    if (!(($otherTerm->position > $termEnd) or (($otherTerm->position + $otherTerm->numWords - 1) < $termStart))) {
                        if (($termWeAreCalculating->phrase != $otherTerm->phrase) or 
                            ($termWeAreCalculating->position != $otherTerm->position)) {
                            $alternateWeights[$termWeAreCalculating->phrase][$termWeAreCalculating->position][$otherTerm->phrase][$otherTerm->position] = 0.0;
                        }
                        unset($remainingTerms[$index]);
                    }
                }
                if ($remainingTerms) {
                    $remainingWeights = $this->getWeights($remainingTerms);
                    foreach ($remainingWeights as $otherPhrase => $weightsWithSamePhrase) {
                        foreach ($weightsWithSamePhrase as $otherPosition => $otherWeight) {
                            $alternateWeights[$termWeAreCalculating->phrase][$termWeAreCalculating->position][$otherPhrase][$otherPosition] = $otherWeight;
                        }
                    }
                }
            }
        }
        return $alternateWeights;
    }
}
