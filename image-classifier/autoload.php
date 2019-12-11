<?php
$dir_path =  __DIR__;
$dir_path = str_replace("\\","/",$dir_path);

require_once $dir_path."/src/ImageClassifier/ImageClassifier.php";

require_once $dir_path."/src/ImageClassifier/Helper/ImageHandler.php";

require_once $dir_path."/src/ImageClassifier/Exception/ConfigException.php";
require_once $dir_path."/src/ImageClassifier/Exception/ImageClassifierException.php";