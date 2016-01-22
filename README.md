# Geotagger
This project computes a coordinate from user input.

## Software Requirements

This software has only been tested on Linux.

To run it, you need the following installed:

* php5-cli (`sudo apt-get install php5-cli`)
* php5-curl (`sudo apt-get install php5-curl`)
* Java 1.8 or later

Everything else you need should be included in this repository.

## Running

There are two ways you can try this program. The simplest is from the command line.

### From the command line

1. `cd` into the root directory of this project. (The same directory that contains this README file.)
2. Run `php -f SimpleRun.php "<text>"`, replacing `<text>` with the text you want to locate. (But keep the `"` around the text!)
3. Output will be printed to the command line.
4. If you want to try another text, repeat step 2.

### From a browser

This still needs some command line work to get it set up.

1. From the command line, `cd` into the root directory of this project. (The same directory that contains this README file.)
2. From the command line, enter `php -S localhost:8000`. This sets up a local server so you can view the program from a browser.
3. From your internet browser, go to `http://localhost:8000/GeotaggerHome.php`.
4. Type in your text to the Text field of the webpage.
5. Click Submit.
6. You will be brought to another page which gives the full output of my progam. (There are more details than you probably need.) The final results should be at the bottom of the page.
7. If you want to try another text, repeat steps 3-5.

## Code Overview

This section describes what each file or folder in this repository is used for.

* __PHP-Stanford-NLP__ folder: A PHP wrapper for the Stanford software from <https://github.com/agentile/PHP-Stanford-NLP>.
* __stanford-ner-2015-04-20__ folder: The Stanford Named Entity Recognizer (NER) from <http://nlp.stanford.edu/software/CRF-NER.shtml>.
* __stanford-postagger-2015-04-20__ folder: The Stanford Part of Speech tagger (POS) from <http://nlp.stanford.edu/software/tagger.shtml>.
* __ActionPage__: Landing page when using the geotagger from a browser.
* __CompareDisambiguators__: Code that is currently being used to run tests.
* __Connectivity.php__: Code from Spotzi to help with the connection to Nominatim.
* __Date.php__: Code from Spotzi that is required by ErrorHandler.php.
* __Disambiguate.php__: Code to disambiguate between search results.
* __ErrorHandler.php__: Code from Spotzi that is required by Connectivity.php.
* __Geotagger.php__: The main file that sends text to each of the other pieces.
* __GeotaggerHome.php__: A web page for running the geotagger from a brower.
* __SimpleRun.php__: This provides a simple example for how to run the program.
* __Terms.php__: Code to handle phrases in the text that are being searched, along with metadata about them.
* __TestTerms.php__: Some unit tests for Terms.php.
* __TextParser__: Finds potential locations in the text using the Stanford software.
