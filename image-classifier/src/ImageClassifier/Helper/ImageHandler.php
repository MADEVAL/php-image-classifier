<?php

namespace ImageClassifier\Helper;

use ImageClassifier\Exception\ImageHandlerException;

class ImageHandler
{
    private $isVerbose;

    function __construct($is_verbose = false)
    {
        $this->isVerbose = $is_verbose;
    }

    /**
     * resizes images
     * @param $file
     * @param $w
     * @param $h
     * @param bool $crop
     * @return bool|resource
     */
    public function resizeImage($file, $w, $h, $crop = FALSE) {

        $image_data = getimagesize($file);

        $image_type = $image_data['mime'];

        $width = $image_data[0];
        $height = $image_data[1];

        $r = $width / $height;

        if ($crop) {

            if ($width > $height) {
                $width = ceil($width-($width*abs($r-$w/$h)));
            } else {
                $height = ceil($height-($height*abs($r-$w/$h)));
            }
            $new_width = $w;
            $new_height = $h;
        } else {

            if ($w/$h > $r) {
                $new_width = $h*$r;
                $new_height = $h;
            } else {
                $new_height = $w/$r;
                $new_width = $w;
            }
        }

        switch ($image_type) {
            case 'image/png':
                $src = imagecreatefrompng($file);
                break;
            case 'image/jpeg':
                $src = imagecreatefromjpeg($file);
                break;
            default:
                return false;
                break;
        }

        $dst = imagecreatetruecolor($new_width, $new_height);
        imagealphablending($dst,false);
        imagesavealpha($dst,true);
        imagealphablending($src,true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        return $dst;

        //end of resizeImage function
    }

    /**
     * performs max pooling for the specified image
     * @param $image_resource
     * @return resource
     * @throws ImageHandlerException
     */
    public function maxPooling_2d($image_resource){

        $size = imagesx($image_resource);

        if($size < 8){
            throw new ImageHandlerException("Cannot perform 2d max pool on image less than 8x8 pixels in size");
        }

        $new_size = floor($size / 4);

        $new_image_resource = imagecreatetruecolor($new_size,$new_size);
        imagealphablending($new_image_resource,false);
        imagesavealpha($new_image_resource,true);

        $l_pixel_matrix = [];

        //read all pixel values within the large strides
        for ($l_y_stride = 0; $l_y_stride < $new_size; $l_y_stride+=4){

            for ($l_x_stride = 0; $l_x_stride < $new_size; $l_x_stride+=4){

                $s_pixel_matrix = [];

                //get coordinates of the four small strides
                $s_stride_coordinates = array(
                    [$l_y_stride, $l_x_stride],
                    [$l_y_stride, $l_x_stride + 2],
                    [$l_y_stride + 2, $l_x_stride],
                    [$l_y_stride + 2, $l_x_stride + 2]
                );

                foreach ($s_stride_coordinates as $s_coordinate){

                    for($s_y = $s_coordinate[0]; $s_y < $s_coordinate[0] + 2; $s_y++){

                        for($s_x = $s_coordinate[1]; $s_x < $s_coordinate[1] + 2; $s_x++){
                            $s_pixel_matrix[$s_y][$s_x] = imagecolorat($image_resource,$s_x,$s_y);
                        }
                    }
                }

                $l_pixel_matrix[$l_y_stride][$l_x_stride] = $s_pixel_matrix;

                //end of $l_x_stride for loop
            }

            //end of $l_y_stride for loop
        }

        //pool the pixels in each large stride
        foreach ($l_pixel_matrix as $key_y => $l_stride_row){

            foreach ($l_stride_row as $key_x => $l_stride){

                //get coordinates of the four small strides
                $s_stride_coordinates = array(
                    [$key_y, $key_x],
                    [$key_y, $key_x + 2],
                    [$key_y + 2, $key_x],
                    [$key_y + 2, $key_x + 2]
                );

                //compute new small strides coordinates
                $new_s_stride_coordinates = array(
                    [$key_y/2, $key_x/2],
                    [$key_y/2, ($key_x + 2)/2],
                    [($key_y + 2)/2, $key_x/2],
                    [($key_y + 2)/2, ($key_x + 2)/2]
                );

                //get pixel values for each of the small strides
                foreach ($s_stride_coordinates as $key => $s_coordinate){

                    $s_stride_pixels = [];

                    for($s_y = $s_coordinate[0]; $s_y < $s_coordinate[0] + 2; $s_y++){

                        for($s_x = $s_coordinate[1]; $s_x < $s_coordinate[1] + 2; $s_x++){
                            $s_stride_pixels[] = $l_stride[$s_y][$s_x];
                        }
                    }

                    //get max value in the array
                    $max_pixel_value = max($s_stride_pixels);

                    //reassign the max_pixel_value to finish pooling
                    $new_global_y = $new_s_stride_coordinates[$key][0];
                    $new_global_x = $new_s_stride_coordinates[$key][1];

                    imagesetpixel($new_image_resource,$new_global_x,$new_global_y,$max_pixel_value);

                }

                //end of $l_stride_row foreach
            }

            //end of $l_pixel_matrix foreach
        }

        return $new_image_resource;
    }

    /**
     * returns an images pixel values in a 1-dimensional array
     * @param $image_resource
     * @return array
     * @throws ImageHandlerException
     */
    public function getPixelValues_1d($image_resource){

        $size = imagesx($image_resource);

        if($size < 1){
            throw new ImageHandlerException("The image resource has no pixels");
        }

        //read the pixel values
        $pixels = [];

        for ($y = 0; $y < $size; $y++){

            for ($x = 0; $x < $size; $x++){
                $pixels[] = imagecolorat($image_resource,$x,$y);
            }
        }

        return $pixels;
    }

    //end of class
}