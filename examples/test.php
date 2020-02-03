<?php

use ImageClassifier\Exception\ImageClassifierException;
use ImageClassifier\ImageClassifier;

require_once "../image-classifier/autoload.php";

try {

    $classifier = new ImageClassifier(true);
    $classifier->train();

    echo "Your prediction is '".$classifier->classify("./c2.jpg")."'";

} catch (ImageClassifierException $e) {
    echo $e->getMessage();
}