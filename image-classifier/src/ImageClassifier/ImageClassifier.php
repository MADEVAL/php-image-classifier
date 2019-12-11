<?php

namespace ImageClassifier;


use FilesystemIterator;
use ImageClassifier\Exception\ConfigException;
use ImageClassifier\Exception\ImageClassifierException;
use ImageClassifier\Helper\ImageHandler;

class ImageClassifier
{

    private $configuration;

    private $isVerbose;

    /**
     * ImageClassifier constructor.
     * @param $is_verbose
     * @throws ImageClassifierException
     */
    function __construct($is_verbose = false)
    {
        $this->isVerbose = $is_verbose;

        try {
            $this->initializeConfiguration();
        } catch (ConfigException $e) {

            if($this->isVerbose){
                echo $e->getMessage();
            }else{
                throw new ImageClassifierException("Configuration Initialization failed: ".$e->getMessage());
            }

            return;
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
    }

    /**
     * trains the model based on the provided images and labels
     * @throws ImageClassifierException
     */
    public function train(){

        //prepare the images for training
        foreach ($this->configuration['labels'] as $key => $label){

            $label = $label['label_name'];
            $path = $label['training_images_path'];

            $iterator = new FilesystemIterator($path);

            while ($iterator->valid()){

                $image_resource_path = $iterator->getFilename();

                $image_data = getimagesize($image_resource_path);

                //check file is of correct type
                if(!preg_match("/image/",$image_data['mime'])){
                    throw new ImageClassifierException("The file '".$iterator->key()."', under the label '".$label."' and path '".$path."' is not a valid image");
                }

                //check if width and height are equal
                $width = $image_data[0];
                $height = $image_data[1];

                if($width !== $height){
                    throw new ImageClassifierException("The image '".$iterator->key()."', under the label '".$label."' and path '".$path."' must have an equal width and height");
                }

                //resize the image to 150 by 150
                $image_resource = ImageHandler::resizeImage($image_resource_path,150,150,$image_data['mime']);
            }

        }
    }

    //end of class
}