<?php

// Ruben's class for connecting to another website:
include 'Connectivity.php';

// Other classes used in steps of the geotagger:
include 'Disambiguate.php';
include 'TextParser.php';

class Geotagger {

    public $termArray = null; // array of Term objects
    public $times = []; // keeps track of how long different parts of the program take
    public $lastQueryTime = 0.0;

    const NOMINATIM_URL_BASE = 'http://nominatim.openstreetmap.org';
    
    // Set to true if querying the Nominatim web service to limit to 1
    // query per second. Set to false if you are using another web service
    // that doesn't have usage limits.
    const LIMIT_QUERIES = true;
    
    // Nominatim suggests providing an email. If you make too many requests they will contact this email
    // instead of blocking your IP address
    const NOMINATIM_EMAIL = "";

    function QueryNominatim($query, $countryCodes = [], $searchLimit = "10", $boundingBox = []) {
        $curlString = self::NOMINATIM_URL_BASE . '/search?format=json';
        $curlString .= '&limit=' . $searchLimit;
        $curlString .= '&email=' . self::NOMINATIM_EMAIL;
        $curlString .= '&addressdetails=1';
        if ($countryCodes) {
            $curlString .= "&countrycodes=" . implode(',', $countryCodes);
        }
        if ($boundingBox) {
            $curlString .= "&viewbox=" . $boundingBox["west"];
            $curlString .= "," . $boundingBox["north"];
            $curlString .= "," . $boundingBox["east"];
            $curlString .= "," . $boundingBox["south"];
            $curlString .= "&bounded=1";
        }
        $curlString .= '&q=' . $query;
        if (printOutput()) {
            echo "<br>Executing query: " . $curlString . "<br>";
        }
        if (self::LIMIT_QUERIES) {
            while (($this->lastQueryTime + 1.0) > microtime(true)) {
                time_nanosleep(0,10000);
            }
        }
        $this->lastQueryTime = microtime(true);
        return json_decode(Connectivity::runCurl($curlString), true);
    }

    function NominatimResultToString($nom_result, $lineprefix = "") {
        return ($lineprefix . "Name: " . $nom_result["display_name"] . "<br>" .
                $lineprefix . "&nbsp&nbspCountry: " . $nom_result["address"]["country_code"] . "<br>" .
                $lineprefix . "&nbsp&nbspLat: " . $nom_result["lat"] . "<br>" .
                $lineprefix . "&nbsp&nbspLon: " . $nom_result["lon"] . "<br>" .
                $lineprefix . "&nbsp&nbspClass: " . $nom_result["class"] . "<br>" .
                $lineprefix . "&nbsp&nbspType: " . $nom_result["type"] . "<br>" .
                $lineprefix . "&nbsp&nbspImportance: " . $nom_result["importance"] . "<br>");
    }

    // Queries Nominatim for all the words that have been added
    function SearchAll($countries = [], $searchLimit = "10", $boundingBox = []) {
        $phrases = $this->termArray->getAllPhrases();
        foreach ($phrases as $phrase) {
            $results = $this->QueryNominatim($phrase, $countries, $searchLimit, $boundingBox);
            $this->termArray->setResults($phrase, $results);
        }
    }

    function printTimes() {
        echo "<br>Time to complete various steps in the algorithm:<br>";
        foreach ($this->times as $description => $timeTaken) {
            echo "&nbsp&nbsp{$description}: " . round($timeTaken,2) . "<br>";
        }
    }
    
    function run($text, $boundingBox = []) {
        $overallStartTime = microtime(true);
        $startTime = microtime(true);
        $this->parser = new TextParser();
        $this->times["Create TextParser"] = (microtime(true) - $startTime);
        $startTime = microtime(true);
        try {
            $full_results = $this->QueryNominatim($text, [], "10", $boundingBox);
            if (printOutput()) {
                echo "Query results for sending the entire text to Nominatim:<br>";
                if ($full_results) {
                    foreach ($full_results as $full_result) {
                        echo $this->NominatimResultToString($full_result, "&nbsp&nbsp&nbsp&nbsp");
                    }
                } else {
                    echo "&nbsp&nbsp&nbsp&nbspNo results found.<br>";
                }
                echo "<br>";
            }
        } catch (Exception $e) {
            echo "Error: unable to get results from Nominatim for the entire text.";
        }
        $this->times["Try sending full text to Nominatim"] = (microtime(true) - $startTime);
        $startTime = microtime(true);
        $this->termArray = $this->parser->run($text);
        $this->times["Run text through TextParser"] = (microtime(true) - $startTime);
        foreach ($this->parser->times as $parserDescription => $parserTime) {
            $this->times["TextParser: " . $parserDescription] = $parserTime;
        }
        $startTime = microtime(true);
        $this->SearchAll([], "10", $boundingBox);
        $this->times["Send all terms to Nominatim"] = (microtime(true) - $startTime);
        $startTime = microtime(true);
        $this->termArray->removeTermsWithoutSearchResults();
        $this->times["Discard terms that don't have search results"] = (microtime(true) - $startTime);
        $startTime = microtime(true);
        $this->disambiguator = new WeightedInverseFrequency1PhaseDisambiguator($this->termArray);
        $this->times["Create disambiguator"] = (microtime(true) - $startTime);
        $startTime = microtime(true);
        $disambiguatedTermArray = $this->disambiguator->Disambiguate();
        $this->times["Run disambiguator"] = (microtime(true) - $startTime);
        if (printOutput()) {
            echo "<br>";
            $disambiguatedTermArray->PrintAllResults($this);
        }
        $this->times["TOTAL"] = (microtime(true) - $overallStartTime);
        return $disambiguatedTermArray;
    }

    // returns an array of Nominatim results, in sorted order
    function SimpleRun($text, $boundingBox = []) {
        $this->parser = new TextParser();
        $this->termArray = $this->parser->run($text);
        $this->SearchAll([], "10", $boundingBox);
        $this->termArray->removeTermsWithoutSearchResults();
        $disambiguator = new WeightedInverseFrequency1PhaseDisambiguator($this->termArray);
        $disambiguator->Disambiguate();
        $sortRval = $disambiguator->SortResults();
        return $sortRval["results"];
    }

    function runWithAllDisambiguators($text) {
        $startTime = microtime(true);
        $this->parser = new TextParser();
        $this->termArray = $this->parser->run($text);
        $ParserTime = (microtime(true) - $startTime);
        if ($this->parser->termTypeUsed == "None") {
            $rval = [];
            $rval["N/A"]["NominatimTime"] = "N/A";
            $rval["N/A"]["ParserTime"] = $ParserTime;
            $rval["N/A"]["TermType"] = $this->parser->termTypeUsed;
            $rval["N/A"]["TermArray"] = true;
            $rval["N/A"]["DisambiguationTime"] = "N/A";
            $rval["N/A"]["SortedResults"] = [];
            $rval["N/A"]["Scores"] = [];
            return $rval;
        }
        if (! $this->termArray) {
            return false;
        }
        $startTime = microtime(true);
        $this->SearchAll();
        $NominatimTime = (microtime(true) - $startTime);
        $this->termArray->removeTermsWithoutSearchResults();
        if (! $this->termArray->terms) {
            $rval = [];
            $rval["N/A"]["NominatimTime"] = $NominatimTime;
            $rval["N/A"]["ParserTime"] = $ParserTime;
            $rval["N/A"]["TermType"] = $this->parser->termTypeUsed;
            $rval["N/A"]["TermArray"] = true;
            $rval["N/A"]["DisambiguationTime"] = "N/A";
            $rval["N/A"]["SortedResults"] = [];
            $rval["N/A"]["Scores"] = [];
            echo "Error: no search results were found for chosen terms.";
            return $rval;
        }

        $disambigators = [
            "Inverse" => (new InverseDisambiguator($this->termArray)),
            "WeightedInverse" => (new WeightedInverseDisambiguator($this->termArray)),
            "WeightedNormalizedInverse" => (new WeightedNormalizedInverseDisambiguator($this->termArray)),
            "InverseFrequency" => (new InverseFrequencyDisambiguator($this->termArray)),
            "WeightedInverseFrequency" => (new WeightedInverseFrequencyDisambiguator($this->termArray)),
            "WeightedNormalizedInverseFrequency" => (new WeightedNormalizedInverseFrequencyDisambiguator($this->termArray)),
            "WeightedDistance" => (new WeightedDistanceDisambiguator($this->termArray)),
            "TotalDistance" => (new TotalDistanceDisambiguator($this->termArray)),
            "Inverse1Phase" => (new Inverse1PhaseDisambiguator($this->termArray)),
            "WeightedInverse1Phase" => (new WeightedInverse1PhaseDisambiguator($this->termArray)),
            "WeightedNormalizedInverse1Phase" => (new WeightedNormalizedInverse1PhaseDisambiguator($this->termArray)),
            "InverseFrequency1Phase" => (new InverseFrequency1PhaseDisambiguator($this->termArray)),
            "WeightedInverseFrequency1Phase" => (new WeightedInverseFrequency1PhaseDisambiguator($this->termArray)),
            "WeightedNormalizedInverseFrequency1Phase" => (new WeightedNormalizedInverseFrequency1PhaseDisambiguator($this->termArray)),
            "WeightedDistance1Phase" => (new WeightedDistance1PhaseDisambiguator($this->termArray)),
            "TotalDistance1Phase" => (new TotalDistance1PhaseDisambiguator($this->termArray))
        ];
        $rval = [];
        foreach ($disambigators as $disambiguatorName => $disambiguator) {
            $rval[$disambiguatorName]["NominatimTime"] = $NominatimTime;
            $rval[$disambiguatorName]["ParserTime"] = $ParserTime;
            $rval[$disambiguatorName]["TermType"] = $this->parser->termTypeUsed;
            $startTime = microtime(true);
            $rval[$disambiguatorName]["TermArray"] = $disambiguator->Disambiguate(100);
            if (! $rval[$disambiguatorName]["TermArray"]) {
                // the disambiguator timed out in this case
                echo "Warning: disambiguator {$disambiguatorName} timed out.\n";
                $rval[$disambiguatorName]["DisambiguationTime"] = ">100";
            } else {
                $sortRval = $disambiguator->SortResults(true);
                $rval[$disambiguatorName]["DisambiguationTime"] = (microtime(true) - $startTime);
                $rval[$disambiguatorName]["SortedResults"] = $sortRval["results"];
                $rval[$disambiguatorName]["Scores"] = $sortRval["scores"];
            }
        }
        return $rval;
    }

}
