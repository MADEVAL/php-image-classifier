<?php

use ImageClassifier\Exception\ImageClassifierException;
use ImageClassifier\ImageClassifier;

require_once "./image-classifier/autoload.php";

try {

    $classifier = new ImageClassifier(true);
    $classifier->train();
    $classifier->classify();

} catch (ImageClassifierException $e) {
    echo $e->getMessage();
}