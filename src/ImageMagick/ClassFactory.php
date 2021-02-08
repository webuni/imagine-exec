<?php

namespace Webuni\ImagineExec\ImageMagick;

use Imagine\Factory\ClassFactory as BaseClassFactory;
use Imagine\Image\ImageInterface;
use Imagine\Image\Metadata\MetadataBag;
use Imagine\Image\Palette\Color\ColorInterface;
use Imagine\Image\Palette\PaletteInterface;

class ClassFactory extends BaseClassFactory
{
    const HANDLE_IMAGE_MAGICK = 'imagemagick';

    public function createFont($handle, $file, $size, ColorInterface $color)
    {
        if ($handle === self::HANDLE_IMAGE_MAGICK) {
            return $this->finalize(new Font(new \Imagick(), $file, $size, $color));
        }

        return parent::createFont($handle, $file, $size, $color);
    }

    public function createLayers($handle, ImageInterface $image, $initialKey = null)
    {
        if ($handle === self::HANDLE_IMAGE_MAGICK) {
            return $this->finalize(new Layers($image, $image->palette(), $image->getImagick(), (int) $initialKey));
        }

        return parent::createLayers($handle, $image, $initialKey);
    }

    public function createImage($handle, $resource, PaletteInterface $palette, MetadataBag $metadata)
    {
        if ($handle === self::HANDLE_IMAGE_MAGICK) {
            return $this->finalize(new Image($resource, $palette, $metadata));
        }

        return parent::createImage($handle, $resource, $palette, $metadata);
    }

    public function createDrawer($handle, $resource)
    {
        if ($handle === self::HANDLE_IMAGE_MAGICK) {
            return $this->finalize(new Drawer($resource));
        }

        return parent::createDrawer($handle, $resource);
    }

    public function createEffects($handle, $resource)
    {
        if ($handle === self::HANDLE_IMAGE_MAGICK) {
            return $this->finalize(new Effects($resource));
        }

        return parent::createEffects($handle, $resource);
    }
}
