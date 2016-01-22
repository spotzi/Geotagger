<?php

abstract class Disambiguator {
    // base class for disambiguation
    
    public $scores = [];
    public $weights = [];
    public $alternateWeights = [];
    public $distance = [];
    public $minDistance = [];
    public $termArray = null;
    
    // $termArray is a TermArray object from Terms.php
    function __construct($termArray) {
        $this->termArray = $termArray;
        if (count($termArray->getAllPhrases()) > 1) {
            $this->UpdateWeights();
            $this->UpdateDistanceMatrix();
            $this->UpdateMinDistances();
        }
    }
    
    // Calculates great circle distance on a sphere the size of the Earth
    // Returned distance is in kilometres.
    function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $d = (sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +
              cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon1 - $lon2)));
        return (acos($d) * 6371.0);
    }
    
    // calculates the distance between Nominatim results $r1 and $r2
    function calculateDistanceResults($r1, $r2) {
        return $this->calculateDistance($r1["lat"], $r1["lon"], $r2["lat"], $r2["lon"]);
    }
    
    function UpdateWeights() {
        $this->weights = $this->termArray->getWeights();
        $this->alternateWeights = $this->termArray->getAlternateWeights();
    }

    function UpdateDistanceMatrix() {
        // calculates the distance between each result
        $this->distance = [];
        $phrases = $this->termArray->getAllPhrases();
        $results = $this->termArray->results;
        foreach ($phrases as $rowPhrase) { // each row of the matrix
            foreach ($phrases as $colPhrase) { // each column of the matrix
                if (strcmp($rowPhrase, $colPhrase) > 0) {
                    foreach ($results[$rowPhrase] as $rowResultIndex => $rowResult) {
                        foreach ($results[$colPhrase] as $colResultIndex => $colResult) {
                            // distances are symmetric, so only calculate once then copy:
                            $this->distance[$rowPhrase][$colPhrase][$rowResultIndex][$colResultIndex] = $this->calculateDistanceResults($rowResult, $colResult);
                            $this->distance[$colPhrase][$rowPhrase][$colResultIndex][$rowResultIndex] = $this->distance[$rowPhrase][$colPhrase][$rowResultIndex][$colResultIndex];
                        }
                    }
                }
            }
        }
    }
    
    function UpdateMinDistances() {
        // finds the two results with the shortest distance between them
        // assumes that $this->distance is updated
        $this->minDistances = [];
        $phrases = $this->termArray->getAllPhrases();
        foreach ($phrases as $rowPhrase) {
            foreach ($phrases as $colPhrase) {
                if (strcmp($rowPhrase, $colPhrase) > 0) {
                    $minVal = 100000.0;
                    foreach ($this->termArray->results[$rowPhrase] as $rowResultIndex => $rowResult) {
                        foreach ($this->termArray->results[$colPhrase] as $colResultIndex => $colResult) {
                            if ($minVal > $this->distance[$rowPhrase][$colPhrase][$rowResultIndex][$colResultIndex]) {
                                $minVal = $this->distance[$rowPhrase][$colPhrase][$rowResultIndex][$colResultIndex];
                            }
                        }
                    }
                    $this->minDistances[$rowPhrase][$colPhrase] = $minVal;
                    $this->minDistances[$colPhrase][$rowPhrase] = $minVal;
                }
            }
        }
    }
    
    // override this in derived classes to try different scoring functions
    function CalculateScore($rowTerm, $rowResultIndex, $rowResult) {
        return 0.0;
    }
    
    // recalculate scores for every result
    function UpdateScores() {
        $this->scores = [];
        foreach ($this->termArray->getAllTerms() as $rowTerm) {
            foreach ($this->termArray->results[$rowTerm->phrase] as $rowResultIndex => $rowResult) {
                $this->scores[$rowTerm->phrase][$rowTerm->position][$rowResultIndex] = $this->CalculateScore($rowTerm, $rowResultIndex, $rowResult);
            }
        }
    }

    // finds the maximum of a 3D array
    function Max3D($a) {
        $rval = null;
        foreach ($a as $b) {
            foreach ($b as $c) {
                foreach ($c as $d) {
                    if ($rval) {
                        if ($rval < $d) {
                            $rval = $d;
                        }
                    } else {
                        // this is the first value we find
                        $rval = $d;
                    }
                }
            }
        }
        return $rval;
    }
    
    // Prints a table in HTML
    function PrintScoreTable() {
        echo "<table border=\"1\"><tr><th colspan=\"2\" rowSpan=\"2\"></th>";
        $terms = $this->termArray->getAllTerms();
        $results = $this->termArray->results;
        foreach ($terms as $term) {
            echo "<th colspan=\"" . count($results[$term->phrase]) . "\">" . $term->phrase . " @ " . $term->position . "</th>";
        }
        echo "<th rowspan=\"2\">Score</th>";
        echo "</tr>";
        
        echo "<tr>";
        foreach ($terms as $term) {
            foreach ($results[$term->phrase] as $result) {
                echo "<td>" . $result["display_name"] . " @ " . $result["lat"] . "," . $result["lon"] . "</td>";
            }
        }
        echo "</tr>";
        //$this->UpdateMinDistances();
        $maxScore = $this->Max3D($this->scores);
        foreach ($terms as $rowTerm) {
            echo "<tr><th rowspan=\"" . count($results[$rowTerm->phrase]) . "\">" . $rowTerm->phrase . " @ " . $rowTerm->position . "</th>";
            foreach ($results[$rowTerm->phrase] as $rowResultIndex => $rowResult) {
                echo "<td>" . $rowResult["display_name"] . " @ " . $rowResult["lat"] . "," . $rowResult["lon"] . "</td>";
                foreach ($terms as $colTerm) {
                    foreach ($results[$colTerm->phrase] as $colResultIndex => $colResult) {
                        if (! isset($this->distance[$rowTerm->phrase][$colTerm->phrase][$rowResultIndex][$colResultIndex])) {
                            echo "<td style=\"text-align:center\">X</td>";
                        } else {
                            echo "<td style=\"text-align:center\">";
                            $isMax = ($this->distance[$rowTerm->phrase][$colTerm->phrase][$rowResultIndex][$colResultIndex] == $this->minDistances[$rowTerm->phrase][$colTerm->phrase]);
                            if ($isMax) {
                                echo "<b>";
                            }
                            echo round($this->distance[$rowTerm->phrase][$colTerm->phrase][$rowResultIndex][$colResultIndex],1);
                            if ($isMax) {
                                echo "</b>";
                            }
                            echo "</td>";
                        }
                    }
                }
                if ($this->scores[$rowTerm->phrase][$rowTerm->position][$rowResultIndex] == $maxScore) {
                    echo "<td style=\"text-align:center\"><b>" . round($this->scores[$rowTerm->phrase][$rowTerm->position][$rowResultIndex], 4) . "</b></td>";
                } else {
                    echo "<td style=\"text-align:center\">" . round($this->scores[$rowTerm->phrase][$rowTerm->position][$rowResultIndex], 4) . "</td>";
                }
                echo "</tr><tr>";
            }
            echo "</tr>";
        }
        echo "<tr><th colspan=\"2\">Score</th>";
        foreach ($terms as $colTerm) {
            foreach ($results[$colTerm->phrase] as $colResultIndex => $colResult) {
                if ($this->scores[$colTerm->phrase][$colTerm->position][$colResultIndex] == $maxScore) {
                    echo "<td style=\"text-align:center\"><b>" . round($this->scores[$colTerm->phrase][$colTerm->position][$colResultIndex], 4) . "</b></td>";
                } else {
                    echo "<td style=\"text-align:center\">" . round($this->scores[$colTerm->phrase][$colTerm->position][$colResultIndex], 4) . "</td>";
                }
            }
        }
        echo "<td></td></tr>";
        echo "</tr>";
        echo "</table>";
    }
    
    // This is for the step where we decide between "college" and "georgian college", for example
    function ChoosePhrase($phrase, $position) {
        if (isset($this->alternateWeights[$phrase][$position])) {
            foreach ($this->alternateWeights[$phrase][$position] as $otherPhrase => $otherPositionsAndWeights) {
                foreach ($otherPositionsAndWeights as $otherPosition => $otherWeight) {
                    if ($otherWeight == 0.0) {
                        // remove this term because it conflicts with the chosen phrase
                        $this->termArray->removeTerm($otherPhrase, $otherPosition);
                    }
                }
            }
        }
    }
    
    function ChooseResult($phrase, $resultIndex) {
        $this->termArray->setResults($phrase, [$resultIndex => $this->termArray->results[$phrase][$resultIndex]]);
    }
    
    // Searches $scores for the highest score.
    // Returns an array with the phrase, position in the text, and index
    //   of the result with the highest score.
    function FindBestPhrase($scores) {
        $bestScore = -1.0;
        $bestPhrase = '';
        $bestPosition = -1;
        $bestResultIndex = -1;
        foreach ($scores as $phrase => $scores1) {
                foreach ($scores1 as $position => $scores2) {
                    foreach ($scores2 as $resultIndex => $score) {
                        if (($bestPhrase == '') or
                            ($score > $bestScore) or
                            (($score == $bestScore) and // tie break with importance:
                             ($this->termArray->results[$phrase][$resultIndex]["importance"] > $this->termArray->results[$bestPhrase][$bestResultIndex]["importance"]))) {
                            $bestScore = $score;
                            $bestPhrase = $phrase;
                            $bestPosition = $position;
                            $bestResultIndex = $resultIndex;
                        }
                    }
                }
        }
        return [$bestPhrase, $bestPosition, $bestResultIndex];
    }
    
    // Return the result with the highest score
    function BestResult() {
        $this->UpdateScores();
        $bestScore = -1.0;
        $bestPhrase = '';
        $bestPosition = -1;
        $bestResultIndex = -1;
        foreach ($this->scores as $phrase => $scores1) {
            foreach ($scores1 as $position => $scores2) {
                foreach ($scores2 as $resultIndex => $score) {
                    if (($bestPhrase == '') or
                        ($score > $bestScore) or
                        (($score == $bestScore) and // tie break with importance:
                         ($this->termArray->results[$phrase][$resultIndex]["importance"] > $this->termArray->results[$bestPhrase][$bestResultIndex]["importance"]))) {
                        $bestScore = $score;
                        $bestPhrase = $phrase;
                        $bestPosition = $position;
                        $bestResultIndex = $resultIndex;
                    }
                }
            }
        }
        return $this->termArray->results[$bestPhrase][$bestResultIndex];
    }

    // Sorts results after disambiguation is complete
    // Use with $importance set to true to sort by the Nominatim importance
    //   field. Otherwise it will sort by score.
    function SortResults($importance = false) {
        $this->UpdateScores();
        $phraseScore = [];
        foreach ($this->scores as $phrase => $scores1) {
            foreach ($scores1 as $position => $scores2) {
                // $scores2 is an array of resultIndex => score, but there
                // should only be one result for each phrase and position
                $score = reset($scores2); // get the first score
                if (isset($phraseScore[$phrase])) {
                    $phraseScore[$phrase] = max($score, $phraseScore[$phrase]);
                } else {
                    $phraseScore[$phrase] = $score;
                }
            }
        }
        $phraseImportance = [];
        foreach ($this->scores as $phrase => $scores1) {
            $result = reset($this->termArray->results[$phrase]);
            $phraseImportance[$phrase] = [$result["importance"], $phraseScore[$phrase]]; // append score to use as a tie-breaker
            $phraseScore[$phrase] = [$phraseScore[$phrase], $result["importance"]]; // append importance to use as a tie-breaker
        }
        $rval = [];
        $rval["results"] = []; // results in sorted order
        $rval["scores"] = []; // the actual scores
        if ($importance) {
            arsort($phraseImportance);
            foreach ($phraseImportance as $phrase => $importanceScore) {
                $rval["results"][] = reset($this->termArray->results[$phrase]);
                $rval["scores"][] = $importanceScore[1];
            }
        } else {
            arsort($phraseScore);
            foreach ($phraseScore as $phrase => $scoreImportance) {
                $rval["results"][] = reset($this->termArray->results[$phrase]);
                $rval["scores"][] = $scoreImportance[0];
            }
        }
        return $rval;
    }

    // checks if we have one result for each phrase
    protected function doneDisambiguation() {
        $phrases = $this->termArray->getAllPhrases();
        foreach ($phrases as $phrase) {
            if (count($this->termArray->results[$phrase]) > 1) {
                return false;
            }
        }
        return true;
    }
    
    // checks if any terms conflict
    protected function donePhase1() {
        $terms = $this->termArray->getAllTerms();
        foreach ($terms as $term) {
            if ($this->weights[$term->phrase][$term->position] != 1.0) {
                return false;
            }
        }
        return true;
    }

    // First resolves conflicting terms, then chooses results
    function TwoPhaseDisambiguate($timeLimit = 0) {
        $startTime = time();
        $phrases = $this->termArray->getAllPhrases();
        if (count($phrases) == 0) {
            echo "Error: no terms available ";
            return false;
        }
        if (count($phrases) == 1) {
            // choose the first result, since there's no disambiguation
            // we can do anyways
            $this->ChooseResult($phrases[0], 0);
            return $this->termArray;
        }
        $this->UpdateWeights();
        if (printOutput()) {
            echo "<br>Weights:<br>";
            var_dump($this->weights);
            echo "<br>Alternate weights:<br>";
            var_dump($this->alternateWeights);
            echo "<br>";
        }
        while (! $this->doneDisambiguation()) {
            if (($timeLimit > 0) and ((time() - $startTime) > $timeLimit)) {
                return false;
            }
            $this->UpdateScores();
            if (printOutput()) {
                $this->PrintScoreTable();
            }
            if (! $this->donePhase1()) {
                if (printOutput()) {
                    echo "<br>Running phase 1<br>";
                }
                $scoresToUse = [];
                foreach ($this->termArray->getAllTerms() as $term) {
                    if ($this->weights[$term->phrase][$term->position] != 1.0) {
                        foreach ($this->termArray->results[$term->phrase] as $resultIndex => $result) {
                            $scoresToUse[$term->phrase][$term->position][$resultIndex] = $this->scores[$term->phrase][$term->position][$resultIndex];
                        }
                    }
                }
                $r = $this->FindBestPhrase($scoresToUse);
                if (printOutput()) {
                    echo "The ambiguous phrase with the best score is {$r[0]} at position {$r[1]}<br><br>";
                }
                $this->ChoosePhrase($r[0], $r[1]);
                $this->UpdateWeights();
            } else {
                if (printOutput()) {
                    echo "<br>Running phase 2<br>";
                }
                $scoresToUse = [];
                foreach ($this->termArray->getAllTerms() as $term) {
                    if (count($this->termArray->results[$term->phrase]) > 1) {
                        foreach ($this->termArray->results[$term->phrase] as $resultIndex => $result) {
                            $scoresToUse[$term->phrase][$term->position][$resultIndex] = $this->scores[$term->phrase][$term->position][$resultIndex];
                        }
                    }
                }
                $r = $this->FindBestPhrase($scoresToUse);
                if (printOutput()) {
                    echo "The result with the best score is result {$r[2]} for phrase {$r[0]}<br><br>";
                }
                $this->ChooseResult($r[0], $r[2]);
            }
        }
        if (printOutput()) {
            echo "<br>Final score table:<br>";
            $this->UpdateScores();
            $this->PrintScoreTable();
        }
        return $this->termArray;
    }

    function OnePhaseDisambiguate($timeLimit = 0) {
        $startTime = time();
        $phrases = $this->termArray->getAllPhrases();
        if (count($phrases) == 0) {
            echo "Error: no terms available ";
            return false;
        }
        if (count($phrases) == 1) {
            // choose the first result, since there's no disambiguation
            // we can do anyways
            $this->ChooseResult($phrases[0], 0);
            return $this->termArray;
        }
        if (printOutput()) {
            echo "<br>Weights:<br>";
            var_dump($this->weights);
            echo "<br>Alternate weights:<br>";
            var_dump($this->alternateWeights);
            echo "<br>";
        }
        while ((! $this->doneDisambiguation()) or (! $this->donePhase1())) {
            if (($timeLimit > 0) and ((time() - $startTime) > $timeLimit)) {
                return false;
            }
            $this->UpdateWeights();
            $this->UpdateScores();
            if (printOutput()) {
                $this->PrintScoreTable();
                echo "<br>Running the only phase of the 1 phase disambiguator<br>";
            }
            $scoresToUse = [];
            foreach ($this->termArray->getAllTerms() as $term) {
                if ((count($this->termArray->results[$term->phrase]) > 1) or ($this->weights[$term->phrase][$term->position] != 1.0)) {
                    foreach ($this->termArray->results[$term->phrase] as $resultIndex => $result) {
                        $scoresToUse[$term->phrase][$term->position][$resultIndex] = $this->scores[$term->phrase][$term->position][$resultIndex];
                    }
                }
            }
            $r = $this->FindBestPhrase($scoresToUse);
            if (printOutput()) {
                echo "The result with the best score is result {$r[2]} for phrase {$r[0]} at postition {$r[1]}<br><br>";
            }
            $this->ChoosePhrase($r[0], $r[1]);
            $this->ChooseResult($r[0], $r[2]);
        }
        if (printOutput()) {
            echo "<br>Final score table:<br>";
            $this->UpdateScores();
            $this->PrintScoreTable();
        }
        return $this->termArray;
    }

    // can override this in sub classes
    function Disambiguate($timeLimit = 0) {
        //return $this->OnePhaseDisambiguate();
        return $this->TwoPhaseDisambiguate($timeLimit);
    }
}

class InverseDisambiguator extends Disambiguator {
    function CalculateScore($rowTerm, $rowResultIndex, $rowResult) {
        $score = 0.0;
        foreach ($this->termArray->getAllTerms() as $colTerm) {
            if ($rowTerm->phrase != $colTerm->phrase) {
                if (!((isset($this->alternateWeights[$rowTerm->phrase][$rowTerm->position][$colTerm->phrase][$colTerm->position]) and
                       ($this->alternateWeights[$rowTerm->phrase][$rowTerm->position][$colTerm->phrase][$colTerm->position] == 0.0)) or 
                      (isset($this->alternateWeights[$colTerm->phrase][$colTerm->position][$rowTerm->phrase][$rowTerm->position]) and
                       ($this->alternateWeights[$colTerm->phrase][$colTerm->position][$rowTerm->phrase][$rowTerm->position] == 0.0)))) {
                    $minVal = 100000.0;
                    foreach ($this->termArray->results[$colTerm->phrase] as $colResultIndex => $colResult) {
                        $dist = $this->distance[$rowTerm->phrase][$colTerm->phrase][$rowResultIndex][$colResultIndex];
                        if ($dist < $minVal) {
                            $minVal = $dist;
                        }
                    }
                    if ($minVal < 0.001) {
                        // prevent division by zero errors
                        $minVal = 0.001;
                    }
                    $score += (1.0 / $minVal);
                }
            }
        }
        return $score;
    }
}


class WeightedInverseDisambiguator extends Disambiguator {
    function CalculateScore($rowTerm, $rowResultIndex, $rowResult) {
        $score = 0.0;
        foreach ($this->termArray->getAllTerms() as $colTerm) {
            if ($rowTerm->phrase != $colTerm->phrase) {
                if (!((isset($this->alternateWeights[$rowTerm->phrase][$rowTerm->position][$colTerm->phrase][$colTerm->position]) and
                       ($this->alternateWeights[$rowTerm->phrase][$rowTerm->position][$colTerm->phrase][$colTerm->position] == 0.0)) or 
                      (isset($this->alternateWeights[$colTerm->phrase][$colTerm->position][$rowTerm->phrase][$rowTerm->position]) and
                       ($this->alternateWeights[$colTerm->phrase][$colTerm->position][$rowTerm->phrase][$rowTerm->position] == 0.0)))) {
                    $minVal = 100000.0;
                    foreach ($this->termArray->results[$colTerm->phrase] as $colResultIndex => $colResult) {
                        $dist = $this->distance[$rowTerm->phrase][$colTerm->phrase][$rowResultIndex][$colResultIndex];
                        if ($dist < $minVal) {
                            $minVal = $dist;
                        }
                    }
                    if ($minVal < 0.001) {
                        // prevent division by zero errors
                        $minVal = 0.001;
                    }
                    if (isset($this->alternateWeights[$rowTerm->phrase][$rowTerm->position][$colTerm->phrase][$colTerm->position])) {
                        $score += (1.0 / $minVal * $this->alternateWeights[$rowTerm->phrase][$rowTerm->position][$colTerm->phrase][$colTerm->position]);
                    } else {
                        $score += (1.0 / $minVal * $this->weights[$colTerm->phrase][$colTerm->position]);
                    }
                }
            }
        }
        return $score;
    }
}

class WeightedNormalizedInverseDisambiguator extends Disambiguator {
    function CalculateScore($rowTerm, $rowResultIndex, $rowResult) {
        $score = 0.0;
        $totalWeight = 0.0;
        foreach ($this->termArray->getAllTerms() as $colTerm) {
            if ($rowTerm->phrase != $colTerm->phrase) {
                if (!((isset($this->alternateWeights[$rowTerm->phrase][$rowTerm->position][$colTerm->phrase][$colTerm->position]) and
                       ($this->alternateWeights[$rowTerm->phrase][$rowTerm->position][$colTerm->phrase][$colTerm->position] == 0.0)) or 
                      (isset($this->alternateWeights[$colTerm->phrase][$colTerm->position][$rowTerm->phrase][$rowTerm->position]) and
                       ($this->alternateWeights[$colTerm->phrase][$colTerm->position][$rowTerm->phrase][$rowTerm->position] == 0.0)))) {
                    $minVal = 100000.0;
                    foreach ($this->termArray->results[$colTerm->phrase] as $colResultIndex => $colResult) {
                        $dist = $this->distance[$rowTerm->phrase][$colTerm->phrase][$rowResultIndex][$colResultIndex];
                        if ($dist < $minVal) {
                            $minVal = $dist;
                        }
                    }
                    if ($minVal < 0.001) {
                        // prevent division by zero errors
                        $minVal = 0.001;
                    }
                    if (isset($this->alternateWeights[$rowTerm->phrase][$rowTerm->position][$colTerm->phrase][$colTerm->position])) {
                        $score += (max($this->minDistances[$rowTerm->phrase][$colTerm->phrase], 0.001)
                                   / $minVal * $this->alternateWeights[$rowTerm->phrase][$rowTerm->position][$colTerm->phrase][$colTerm->position]);
                        $totalWeight += $this->alternateWeights[$rowTerm->phrase][$rowTerm->position][$colTerm->phrase][$colTerm->position];
                    } else {
                        $score += (max($this->minDistances[$rowTerm->phrase][$colTerm->phrase], 0.001)
                                   / $minVal * $this->weights[$colTerm->phrase][$colTerm->position]);
                        $totalWeight += $this->weights[$colTerm->phrase][$colTerm->position];
                    }
                }
            }
        }
        if ($totalWeight == 0.0) {
            // in this case there is only one unique phrase
            return 0.0;
        }
        return ($score / $totalWeight);
    }
}

class InverseFrequencyDisambiguator extends InverseDisambiguator {
    function CalculateScore($rowTerm, $rowResultIndex, $rowResult) {
        return (parent::CalculateScore($rowTerm, $rowResultIndex, $rowResult) * count($this->termArray->terms[$rowTerm->phrase]));
    }
}

class WeightedInverseFrequencyDisambiguator extends WeightedInverseDisambiguator {
    function CalculateScore($rowTerm, $rowResultIndex, $rowResult) {
        $totalWeight = array_sum($this->weights[$rowTerm->phrase]);
        if (isset($this->alternateWeights[$rowTerm->phrase][$rowTerm->position][$rowTerm->phrase])) {
            foreach ($this->alternateWeights[$rowTerm->phrase][$rowTerm->position][$rowTerm->phrase] as $colPosition => $altWeight) {
                $totalWeight -= $this->weights[$rowTerm->phrase][$colPosition];
                $totalWeight += $altWeight;
            }
        }
        return (parent::CalculateScore($rowTerm, $rowResultIndex, $rowResult) * $totalWeight);
    }
}

class WeightedNormalizedInverseFrequencyDisambiguator extends WeightedNormalizedInverseDisambiguator {
    function CalculateScore($rowTerm, $rowResultIndex, $rowResult) {
        $totalWeight = array_sum($this->weights[$rowTerm->phrase]);
        if (isset($this->alternateWeights[$rowTerm->phrase][$rowTerm->position][$rowTerm->phrase])) {
            foreach ($this->alternateWeights[$rowTerm->phrase][$rowTerm->position][$rowTerm->phrase] as $colPosition => $altWeight) {
                $totalWeight -= $this->weights[$rowTerm->phrase][$colPosition];
                $totalWeight += $altWeight;
            }
        }
        return (parent::CalculateScore($rowTerm, $rowResultIndex, $rowResult) * $totalWeight);
    }
}

class WeightedDistanceDisambiguator extends Disambiguator {
    function CalculateScore($rowTerm, $rowResultIndex, $rowResult) {
        $score = 0.0;
        foreach ($this->termArray->getAllTerms() as $colTerm) {
            if ($rowTerm->phrase != $colTerm->phrase) {
                if (!((isset($this->alternateWeights[$rowTerm->phrase][$rowTerm->position][$colTerm->phrase][$colTerm->position]) and
                       ($this->alternateWeights[$rowTerm->phrase][$rowTerm->position][$colTerm->phrase][$colTerm->position] == 0.0)) or 
                      (isset($this->alternateWeights[$colTerm->phrase][$colTerm->position][$rowTerm->phrase][$rowTerm->position]) and
                       ($this->alternateWeights[$colTerm->phrase][$colTerm->position][$rowTerm->phrase][$rowTerm->position] == 0.0)))) {
                    $minVal = 100000.0;
                    foreach ($this->termArray->results[$colTerm->phrase] as $colResultIndex => $colResult) {
                        $dist = $this->distance[$rowTerm->phrase][$colTerm->phrase][$rowResultIndex][$colResultIndex];
                        if ($dist < $minVal) {
                            $minVal = $dist;
                        }
                    }
                    if (isset($this->alternateWeights[$rowTerm->phrase][$rowTerm->position][$colTerm->phrase][$colTerm->position])) {
                        $score -= ( $minVal * $this->alternateWeights[$rowTerm->phrase][$rowTerm->position][$colTerm->phrase][$colTerm->position]);
                    } else {
                        $score -= ( $minVal * $this->weights[$colTerm->phrase][$colTerm->position]);
                    }
                }
            }
        }
        return $score;
    }
}

class TotalDistanceDisambiguator extends Disambiguator {
    function CalculateScore($rowTerm, $rowResultIndex, $rowResult) {
        $score = 0.0;
        foreach ($this->termArray->getAllTerms() as $colTerm) {
            if ($rowTerm->phrase != $colTerm->phrase) {
                if (!((isset($this->alternateWeights[$rowTerm->phrase][$rowTerm->position][$colTerm->phrase][$colTerm->position]) and
                       ($this->alternateWeights[$rowTerm->phrase][$rowTerm->position][$colTerm->phrase][$colTerm->position] == 0.0)) or 
                      (isset($this->alternateWeights[$colTerm->phrase][$colTerm->position][$rowTerm->phrase][$rowTerm->position]) and
                       ($this->alternateWeights[$colTerm->phrase][$colTerm->position][$rowTerm->phrase][$rowTerm->position] == 0.0)))) {
                    $minVal = 100000.0;
                    foreach ($this->termArray->results[$colTerm->phrase] as $colResultIndex => $colResult) {
                        $dist = $this->distance[$rowTerm->phrase][$colTerm->phrase][$rowResultIndex][$colResultIndex];
                        if ($dist < $minVal) {
                            $minVal = $dist;
                        }
                    }
                    $score -= $minVal;
                }
            }
        }
        return $score;
    }
}

class Inverse1PhaseDisambiguator extends InverseDisambiguator {
    function Disambiguate($timeLimit = 0) {
        return $this->OnePhaseDisambiguate($timeLimit);
    }
}


class WeightedInverse1PhaseDisambiguator extends WeightedInverseDisambiguator {
    function Disambiguate($timeLimit = 0) {
        return $this->OnePhaseDisambiguate($timeLimit);
    }
}

class WeightedNormalizedInverse1PhaseDisambiguator extends WeightedNormalizedInverseDisambiguator {
    function Disambiguate($timeLimit = 0) {
        return $this->OnePhaseDisambiguate($timeLimit);
    }
}

class InverseFrequency1PhaseDisambiguator extends InverseFrequencyDisambiguator {
    function Disambiguate($timeLimit = 0) {
        return $this->OnePhaseDisambiguate($timeLimit);
    }
}

class WeightedInverseFrequency1PhaseDisambiguator extends WeightedInverseFrequencyDisambiguator {
    function Disambiguate($timeLimit = 0) {
        return $this->OnePhaseDisambiguate($timeLimit);
    }
}

class WeightedNormalizedInverseFrequency1PhaseDisambiguator extends WeightedNormalizedInverseFrequencyDisambiguator {
    function Disambiguate($timeLimit = 0) {
        return $this->OnePhaseDisambiguate($timeLimit);
    }
}

class WeightedDistance1PhaseDisambiguator extends WeightedDistanceDisambiguator {
    function Disambiguate($timeLimit = 0) {
        return $this->OnePhaseDisambiguate($timeLimit);
    }
}

class TotalDistance1PhaseDisambiguator extends TotalDistanceDisambiguator {
    function Disambiguate($timeLimit = 0) {
        return $this->OnePhaseDisambiguate($timeLimit);
    }
}
