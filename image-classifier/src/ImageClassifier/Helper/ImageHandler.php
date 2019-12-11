<?php

namespace ImageClassifier\Helper;


class ImageHandler
{

    public static function resizeImage($file, $w, $h, $image_type, $crop = FALSE) {

        list($width, $height) = getimagesize($file);

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

    //end of class
}