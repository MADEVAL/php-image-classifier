<?php

namespace ImageClassifier;

use FilesystemIterator;
use ImageClassifier\Exception\ConfigException;
use ImageClassifier\Exception\ImageClassifierException;
use ImageClassifier\Exception\ImageHandlerException;
use ImageClassifier\Helper\ImageHandler;
use Phpml\Classification\KNearestNeighbors;
use Phpml\Classification\SVC;

class ImageClassifier
{

    const CONVOLUTION_N_MAX_POOL_LOOP_COUNT = 2;

    private $configuration;

    private $imageHandler;

    private $classifier;

    private $isVerbose;

    private $isModelTrained;

    /**
     * ImageClassifier constructor.
     * @param $is_verbose
     * @throws ImageClassifierException
     */
    function __construct($is_verbose = false)
    {
        //set if the classifier should echo out its output
        $this->isVerbose = $is_verbose;

        //set the below value to false initially, until the model is trained
        $this->isModelTrained = false;

        //initialize image handler
        $this->imageHandler = new ImageHandler($is_verbose);

        //initialize classifier
        $this->classifier = new KNearestNeighbors();

        //initialize configuration
        try {
            $this->initializeConfiguration();
        } catch (ConfigException $e) {
            throw new ImageClassifierException("Configuration Initialization failed: ".$e->getMessage());
        }

    }

    /**
     * initializes the configuration class variable
     * @throws ConfigException
     */
    private function initializeConfiguration(){

        //read json config file
        $dir_path = __DIR__;
        $dir_path = str_replace("\\","/",$dir_path);
        $config_data = file_get_contents($dir_path."/config.json");

        $this->configuration = json_decode($config_data,true);

        ///check if configuration is valid

        // check if configuration has the 'labels' field
        if(!isset($this->configuration['labels'])){
            throw new ConfigException("Field 'labels' is missing in the config.json file");
        }

        //check that configuration has at least two labels set
        $labels = $this->configuration['labels'];

        if(count($labels) < 2){
            throw new ConfigException("The config.json file has less than two labels, this is not acceptable");
        }

        //check that the set 'training_images_path' and 'label_name' fields are valid, and that they are not empty
        foreach ($labels as $key => $label){

            //check if 'training_images_path' field is set
            if(!isset($label['training_images_path'])){
                throw new ConfigException("The 'training_images_path' field at label '".$key."' has not been set");
            }

            //check if 'label_name' field is set
            if(!isset($label['label_name'])){
                throw new ConfigException("The 'label_name' field at label '".$key."' has not been set");
            }

            $label_name = $label['label_name'];
            $path = $label['training_images_path'];

            $path = $dir_path."/".$path;

            //check that label_name is not empty
            if(empty($label_name)){
                throw new ConfigException("The 'label_name' field at label '".$key."' seems to be empty");
            }

            //check if path is valid
            if(!is_dir($path)){
                throw new ConfigException("Invalid 'training_images_path' dir path at label '".$key."'");
            }

            //check if directory is empty
            $iterator = new FilesystemIterator($path);

            if(!$iterator->valid()){
                throw new ConfigException("The 'training_images_path' value at label '".$key."' seems to point to an empty directory: ".$path);
            }

            //reassign the new validated path
            $this->configuration['labels'][$key]['training_images_path'] = $path;
        }

        if($this->isVerbose){
            echo "Configuration initialization done! \n\n";
        }
    }

    /**
     * prepares training images
     * @throws ImageClassifierException
     */
    private function prepareTrainingImages(){

        if($this->isVerbose){
            echo "Preparing images... \n\n";
        }

        //define array for storing the training pixels
        $training_pixels = [];

        //define convolution matrix
        $convolution_matrix = array(
            [-1, 0, 1],
            [-2, 0, 2],
            [-1, 0, 1]
        );

        //prepare the images for training
        foreach ($this->configuration['labels'] as $key => $label){

            $label_name = $label['label_name'];
            $path = $label['training_images_path'];

            if($this->isVerbose){
                echo "Processing '".$label_name."' images \n\n";
            }

            $iterator = new FilesystemIterator($path);

            $iterator_counter = 0;
            $iterator_size = iterator_count($iterator);
            $iterator->seek(0);

            while ($iterator->valid()){

                $image_resource_path = $iterator->getPathname();

                $image_data = getimagesize($image_resource_path);

                //check file is of correct type
                if($image_data['mime'] !== "image/png" && $image_data['mime'] !== 'image/jpeg'){
                    throw new ImageClassifierException("The file '".$iterator->key()."', under the label '".$label_name."' and path '".$path."' is not a valid image. Use (.png or .jpg images)");
                }

                //check if width and height are equal
                $width = $image_data[0];
                $height = $image_data[1];

                if($width !== $height){
                    throw new ImageClassifierException("The image '".$iterator->key()."', under the label '".$label_name."' and path '".$path."' must have an equal width and height");
                }

                //resize the image to 150 by 150
                $image_resource =  $this->imageHandler->resizeImage($image_resource_path,150,150);

                //convolution and max pooling at least three times
                for ($i = 0; $i < self::CONVOLUTION_N_MAX_POOL_LOOP_COUNT; $i++){

                    imageconvolution($image_resource,$convolution_matrix,1,127);

                    try {
                        $image_resource = $this->imageHandler->maxPooling_2d($image_resource);
                    } catch (ImageHandlerException $e) {
                        throw new ImageClassifierException("An error occurred during pooling: ".$e->getMessage());
                    }
                }

                //get pixel values of the processed image in 1-dimensional array
                try {
                    $pixels_1d = $this->imageHandler->getPixelValues_1d($image_resource);
                } catch (ImageHandlerException $e) {
                    throw new ImageClassifierException("An error occurred while retrieving pixel values: ".$e->getMessage());
                }

                //add the retrieved pixels array into the training array with its appropriate label
                $training_entry = [];
                $training_entry[] = $label_name;
                $training_entry[] = $pixels_1d;

                $training_pixels[] = $training_entry;

                $iterator_counter++;

                if($this->isVerbose){

                    if($iterator_counter === $iterator_size){
                        echo "Processed ".$iterator_counter."/".$iterator_size." images \n\n";
                    }else{
                        echo "Processed ".$iterator_counter."/".$iterator_size." images \n";
                    }

                }

                $iterator->next();

                //end of file iterator
            }

            //end of labels foreach
        }

        return $training_pixels;
    }

    /**
     * trains the model based on the provided images and labels
     * @throws ImageClassifierException
     */
    public function train(){

        if($this->isVerbose){
            echo "Began training \n\n";
        }

        //prepare training images
        $training_pixels = $this->prepareTrainingImages();

        if(count($training_pixels) < 1){
            throw new ImageClassifierException("The training_pixels array returned empty");
        }

        $samples = [];
        $labels = [];

        //insert the data into the training model
        foreach ($training_pixels as $key => $training_entry){
            $samples[] = $training_entry[1];
            $labels[] = $training_entry[0];
        }

        $this->classifier->train($samples,$labels);

        $this->isModelTrained = true;

    }

    /**
     * classifies the specified image
     * @throws ImageClassifierException
     */
    public function classify($image_path){

        //check if the model has been trained
        if(!$this->isModelTrained){
            throw new ImageClassifierException("The model has not been trained");
        }

        //get image data
        $image_data = getimagesize($image_path);

        //check file is of correct type
        if($image_data['mime'] !== "image/png" && $image_data['mime'] !== 'image/jpeg'){
            throw new ImageClassifierException("The file '".$image_path."', is not a valid image. Use (.png or .jpg images)");
        }

        //check if the width and height are equal
        $width = $image_data[0];
        $height = $image_data[1];

        if($width !== $height){
            throw new ImageClassifierException("The image '".$image_path."', must have an equal width and height");
        }

        //resize the image to 150 by 150
        $image_resource =  $this->imageHandler->resizeImage($image_path,150,150);

        //convolution and max pooling at least three times
        //define convolution matrix
        $convolution_matrix = array(
            [-1, 0, 1],
            [-2, 0, 2],
            [-1, 0, 1]
        );

        for ($i = 0; $i < self::CONVOLUTION_N_MAX_POOL_LOOP_COUNT; $i++){

            imageconvolution($image_resource,$convolution_matrix,1,127);

            try {
                $image_resource = $this->imageHandler->maxPooling_2d($image_resource);
            } catch (ImageHandlerException $e) {
                throw new ImageClassifierException("An error occurred during pooling: ".$e->getMessage());
            }
        }

        //get pixel values of the processed image in 1-dimensional array
        try {
            $pixels_1d = $this->imageHandler->getPixelValues_1d($image_resource);
        } catch (ImageHandlerException $e) {
            throw new ImageClassifierException("An error occurred while retrieving pixel values: ".$e->getMessage());
        }

        //classify the image
        return $this->classifier->predict($pixels_1d);

    }

    //end of class
}