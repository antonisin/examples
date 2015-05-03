<?php

/**
 * @author    Antonisin Max <antonisin.maxim@gmail.com>
 * @copyright 2015
 */

namespace FW\AirBundle\Helper;

/**
 * Class AirHelper
 *
 * @package FW\AirBundle\Helper
 */
class ImageHelper
{
    /**
     * @var mixed
     * @access private
     */
    private $image;

    /**
     * @var string
     * @access public
     */
    private $imageType;


    /**
     * Load Image File and set parameters
     *
     * @access public
     *
     * @param string $fileName FileName string
     * @return ImageHelper $this
     */
    public function load($fileName)
    {
        $this->setImageType(getimagesize($fileName)[2]);
        $this->setImage($fileName, $this->getImageType());

        return $this;
    }

    /**
     * Scale Image
     *
     * @access public
     *
     * @param integer $scale
     */
    public function scale($scale)
    {
        $width = $this->getWidth() * $scale / 100;
        $height = $this->getheight() * $scale / 100;
        $this->resize($width, $height);
    }

    /**
     * Smart Scale By width
     *
     * @access public
     * @param integer $maxWidth Image Maximum Width
     * @return ImageHelper $this
     */
    public function smartScale($maxWidth)
    {
        $rap = $this->getWidth() / $this->getHeight();

        if ($this->getWidth() >= $maxWidth) {
            $height = $maxWidth / $rap;
            $this->resize($maxWidth, $height);
        } else {
            $this->resize($this->getWidth(), $this->getHeight());
        }

        return $this;
    }

    /**
     * Resize Image
     *
     * @access private
     *
     * @param integer $width
     * @param integer $height
     */
    private function resize($width, $height)
    {
        $newImage = imagecreatetruecolor($width, $height);
        imagecopyresampled($newImage, $this->image, 0, 0, 0, 0, $width, $height, $this->getWidth(), $this->getHeight());
        $this->setImage($newImage);
    }

    /**
     * Save Image from resource to file
     *
     * @access public
     * @param string $path File path to save
     */
    public function save($path)
    {
        switch($this->getImageType()) {
            case IMAGETYPE_JPEG:
                imagejpeg($this->getImage(), $path, 100);
                break;
            case IMAGETYPE_PNG:
                imagepng($this->getImage(), $path);
                break;
            case IMAGETYPE_GIF:
                imagegif($this->getImage(), $path);
                break;
        }
    }

    /**
     * Return Image Width
     *
     * @access private
     * @return int
     */
    private function getWidth()
    {
        return imagesx($this->getImage());
    }

    /**
     * Return Image Height
     *
     * @access public
     * @return int
     */
    private function getHeight()
    {
        return imagesy($this->getImage());
    }

    /**
     * Set Image
     *
     * @param  mixed  $file Image File Name
     * @param  string $type Image File type
     *
     * @access private
     */
    private function setImage($file, $type = null)
    {
        if ($type == IMAGETYPE_JPEG) {
            $this->image = imagecreatefromjpeg($file);
        } elseif ($type == IMAGETYPE_GIF) {
            $this->image = imagecreatefromgif($file);
        } elseif ($type == IMAGETYPE_PNG) {
            $this->image = imagecreatefrompng($file);
        } elseif (empty($type)) {
            $this->image = $file;
        }
    }

    /**
     * Return Image
     *
     * @access public
     * @return mixed
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * Set Image File Type
     *
     * @access public
     * @param string $string
     */
    private function setImageType($string)
    {
        $this->imageType = $string;
    }

    /**
     * Return Image File Type
     *
     * @access public
     * @return string
     */
    private function getImageType()
    {
        return $this->imageType;
    }
}