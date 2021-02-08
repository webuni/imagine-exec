<?php

namespace Webuni\ImagineExec\ImageMagick;

use Imagine\Exception\InvalidArgumentException;
use Imagine\Exception\NotSupportedException;
use Imagine\Exception\RuntimeException;
use Imagine\Factory\ClassFactoryInterface;
use Imagine\File\LoaderInterface;
use Imagine\Image\AbstractImagine;
use Imagine\Image\BoxInterface;
use Imagine\Image\Metadata\MetadataBag;
use Imagine\Image\Palette\CMYK;
use Imagine\Image\Palette\Color\ColorInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Palette\Grayscale;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class Imagine extends AbstractImagine
{
    /** @var string */
    private $convertBin;

    /** @var string */
    private $identifyBin;

    /** @var Filesystem */
    private $fs;

    /**
     * @throws RuntimeException
     */
    public function __construct(string $convertBin = '/usr/bin/convert', string $identifyBin = '/usr/bin/identify', ClassFactoryInterface $classFactory = null)
    {
        $this->convertBin = $convertBin;
        $version = $this->getVersion();

        if (version_compare('6.2.9', $version) > 0) {
            throw new RuntimeException(sprintf('ImageMagick version 6.2.9 or higher is required, %s provided', $version));
        }

        $this->identifyBin = $identifyBin;
        $this->setClassFactory($classFactory ?? new ClassFactory());
        $this->fs = new Filesystem();
    }

    /**
     * {@inheritdoc}
     */
    public function open($path)
    {
        $loader = $path instanceof LoaderInterface ? $path : $this->getClassFactory()->createFileLoader($path);
        $path = $loader->getPath();

        try {
            if (!$loader->isLocalFile()) {
                $path = $this->fs->tempnam(sys_get_temp_dir(), 'im');
                $this->fs->dumpFile($path, $loader->getData());
            }

            $arguments = ['convert' => $this->convertBin, 'file' => $path];
            $image = $this->getClassFactory()->createImage(ClassFactory::HANDLE_IMAGE_MAGICK, $arguments, $this->createPalette($path), $this->getMetadataReader()->readFile($loader));
        } catch (\ImagickException $e) {
            throw new RuntimeException(sprintf('Unable to open image %s', $path), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function create(BoxInterface $size, ColorInterface $color = null)
    {
        $width  = $size->getWidth();
        $height = $size->getHeight();

        $palette = null !== $color ? $color->getPalette() : new RGB();
        $color = null !== $color ? $color : $palette->color('fff');

        try {
            $pixel = new \ImagickPixel((string) $color);
            $pixel->setColorValue(\Imagick::COLOR_ALPHA, number_format(round($color->getAlpha() / 100, 2), 1));

            $imagick = new \Imagick();
            $imagick->newImage($width, $height, $pixel);
            $imagick->setImageMatte(true);
            $imagick->setImageBackgroundColor($pixel);

            if (version_compare('6.3.1', $this->getVersion($imagick)) < 0) {
                $imagick->setImageOpacity($pixel->getColorValue(\Imagick::COLOR_ALPHA));
            }

            $pixel->clear();
            $pixel->destroy();

            return $this->getClassFactory()->createImage(ClassFactory::HANDLE_IMAGE_MAGICK, $imagick, $palette, new MetadataBag());
        } catch (\ImagickException $e) {
            throw new RuntimeException('Could not create empty image', $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function load($string)
    {
        $file = tempnam(sys_get_temp_dir(), 'im');
        file_put_contents($file, $string);

        $arguments = ['convert' => $this->convertBin, 'file' => $file];

        return $this->getClassFactory()->createImage(ClassFactory::HANDLE_IMAGE_MAGICK, $arguments, $this->createPalette($imagick), $this->getMetadataReader()->readData($string));
    }

    /**
     * {@inheritdoc}
     */
    public function read($resource)
    {
        if (!is_resource($resource)) {
            throw new InvalidArgumentException('Variable does not contain a stream resource');
        }

        $content = stream_get_contents($resource);

        if (false === $content) {
            throw new InvalidArgumentException('Cannot read resource content');
        }

        $file = tempnam(sys_get_temp_dir(), 'im');
        file_put_contents($file, $content);

        return $this->getClassFactory()->createImage(ClassFactoryInterface::HANDLE_IMAGICK, $imagick, $this->createPalette($imagick), $this->getMetadataReader()->readData($content, $resource));
        return new Image(array('convert' => $this->convertBin, 'file' => $file), $this->createPalette($file), $this->getMetadataReader()->readStream($resource));
    }

    /**
     * {@inheritdoc}
     */
    public function font($file, $size, ColorInterface $color)
    {
        return $this->getClassFactory()->createFont(ClassFactory::HANDLE_IMAGE_MAGICK, $file, $size, $color);
    }

    /**
     * Returns the palette corresponding to an \Imagick resource colorspace
     *
     * @param string $path
     *
     * @return CMYK|Grayscale|RGB
     *
     * @throws NotSupportedException
     */
    private function createPalette($path)
    {
        $process = new Process([$this->identifyBin, '-format', '%[colorspace]', $path]);
        $process->run();

        switch (trim($process->getOutput())) {
            case 'RGB':
            case 'sRGB':
                return new RGB();
            case 'CMYK':
                return new CMYK();
            case 'Gray':
                return new Grayscale();
            default:
                throw new NotSupportedException(sprintf('Colorspace "%s" is not supported, only RGB and CMYK colorspace are curently supported', trim($process->getOutput())));
        }
    }

    /**
     * Returns ImageMagick version
     *
     * @return string
     */
    private function getVersion()
    {
        $process = new Process([$this->convertBin, '--version']);
        $process->run();
        $output = $process->getOutput();

        if (!preg_match('/ImageMagick ([^\s]+)/', $output, $match)) {
            throw new RuntimeException('ImageMagick is not installed');
        }

        return $match[1];
    }
}
