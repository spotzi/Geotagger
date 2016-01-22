<?php

include 'Terms.php';

function arrayToString($a) {
    return "[" . implode(",", $a) . "]";
}

echo "Test 1: empty<br>Expected: [0]<br>Actual: ";
$termArray = new TermArray();
$weightHelperResult = $termArray->weightHelper($termArray->getAllTerms());
echo arrayToString($weightHelperResult);
echo "<br>Weights:<br>";
var_dump($termArray->getWeights());
echo "<br><br>";


echo "Test 2: New<br>Expected: [1]<br>Actual: ";
$termArray = new TermArray();
$termArray->addTerm("New", 0);
$weightHelperResult = $termArray->weightHelper($termArray->getAllTerms());
echo arrayToString($weightHelperResult);
echo "<br>Weights:<br>";
var_dump($termArray->getWeights());
echo "<br><br>";

echo "Test 3: New, York<br>Expected: [2]<br>Actual: ";
$termArray = new TermArray();
$termArray->addTerm("New", 0);
$termArray->addTerm("York", 1);
$weightHelperResult = $termArray->weightHelper($termArray->getAllTerms());
echo arrayToString($weightHelperResult);
echo "<br>Weights:<br>";
var_dump($termArray->getWeights());
echo "<br><br>";

echo "Test 4: New, New York<br>Expected: [1,1]<br>Actual: ";
$termArray = new TermArray();
$termArray->addTerm("New", 0);
$termArray->addTerm("New York", 0);
$weightHelperResult = $termArray->weightHelper($termArray->getAllTerms());
echo arrayToString($weightHelperResult);
echo "<br>Weights:<br>";
var_dump($termArray->getWeights());
echo "<br><br>";

echo "Test 5: York, New York<br>Expected: [1,1]<br>Actual: ";
$termArray = new TermArray();
$termArray->addTerm("York", 1);
$termArray->addTerm("New York", 0);
$weightHelperResult = $termArray->weightHelper($termArray->getAllTerms());
echo arrayToString($weightHelperResult);
echo "<br>Weights:<br>";
var_dump($termArray->getWeights());
echo "<br><br>";

echo "Test 6: New, York, New York<br>Expected: [2,1]<br>Actual: ";
$termArray = new TermArray();
$termArray->addTerm("New", 0);
$termArray->addTerm("York", 1);
$termArray->addTerm("New York", 0);
$weightHelperResult = $termArray->weightHelper($termArray->getAllTerms());
echo arrayToString($weightHelperResult);
echo "<br>Weights:<br>";
var_dump($termArray->getWeights());
echo "<br><br>";

echo "Test 7: New, York, City<br>Expected: [3]<br>Actual: ";
$termArray = new TermArray();
$termArray->addTerm("New", 0);
$termArray->addTerm("York", 1);
$termArray->addTerm("City", 2);
$weightHelperResult = $termArray->weightHelper($termArray->getAllTerms());
echo arrayToString($weightHelperResult);
echo "<br>Weights:<br>";
var_dump($termArray->getWeights());
echo "<br><br>";

echo "Test 8: New, York, City, New York, York City, New York City<br>Expected: [3,2,2,1]<br>Actual: ";
$termArray = new TermArray();
$termArray->addTerm("New", 0);
$termArray->addTerm("York", 1);
$termArray->addTerm("City", 2);
$termArray->addTerm("New York", 0);
$termArray->addTerm("York City", 1);
$termArray->addTerm("New York City", 0);
$weightHelperResult = $termArray->weightHelper($termArray->getAllTerms());
echo arrayToString($weightHelperResult);
echo "<br>Weights:<br>";
var_dump($termArray->getWeights());
echo "<br>Alternate weights:<br>";
var_dump($termArray->getAlternateWeights());
echo "<br><br>";

echo "Test 9: New, York, City, New York, York City<br>Expected: [3,2,2]<br>Actual: ";
$termArray = new TermArray();
$termArray->addTerm("New", 0);
$termArray->addTerm("York", 1);
$termArray->addTerm("City", 2);
$termArray->addTerm("New York", 0);
$termArray->addTerm("York City", 1);
$weightHelperResult = $termArray->weightHelper($termArray->getAllTerms());
echo arrayToString($weightHelperResult);
//var_dump($termArray->findGroups());
echo "<br>Weights:<br>";
var_dump($termArray->getWeights());
echo "<br>Alternate weights:<br>";
var_dump($termArray->getAlternateWeights());
echo "<br><br>";

echo "Test 10: The, New, York, City, New York, York City, New York City, The New, The New York, The New York City<br>Expected: <br>Actual: ";
$termArray = new TermArray();
$termArray->addTerm("The", 0);
$termArray->addTerm("New", 1);
$termArray->addTerm("York", 2);
$termArray->addTerm("City", 3);
$termArray->addTerm("The New", 0);
$termArray->addTerm("New York", 1);
$termArray->addTerm("York City", 2);
$termArray->addTerm("New York City", 1);
$termArray->addTerm("The New York", 0);
$termArray->addTerm("The New York City", 0);
$weightHelperResult = $termArray->weightHelper($termArray->getAllTerms());
echo arrayToString($weightHelperResult);
echo "<br>Weights:<br>";
var_dump($termArray->getWeights());
echo "<br><br>";
