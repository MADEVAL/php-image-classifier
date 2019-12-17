<?php

$iterator = new FilesystemIterator("../image-classifier/assets/training_images_dir/cat");

$iterator_counter = 0;

while($iterator->valid()) {
    echo $iterator->getPathname() . "\n";

    $iterator_counter++;

    echo "Processed ".$iterator_counter."\n";//.iterator_count($iterator)." images \n\n";
    $iterator->next();
}