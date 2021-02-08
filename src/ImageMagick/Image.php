<?php

namespace Webuni\ImagineExec\ImageMagick;

use Imagine\Exception\InvalidArgumentException;
use Imagine\Exception\OutOfBoundsException;
use Imagine\Exception\RuntimeException;
use Imagine\Image\AbstractImage;
use Imagine\Image\Box;
use Imagine\Image\BoxInterface;
use Imagine\Image\Fill\FillInterface;
use Imagine\Image\Fill\Gradient\Horizontal;
use Imagine\Image\Fill\Gradient\Linear;
use Imagine\Image\ImageInterface;
use Imagine\Image\Metadata\MetadataBag;
use Imagine\Image\Palette\Color\ColorInterface;
use Imagine\Image\Palette\PaletteInterface;
use Imagine\Image\PointInterface;
use Imagine\Image\ProfileInterface;
use Symfony\Component\Process\Process;

final class Image extends AbstractImage
{
    private $arguments;
    private $palette;
    private $size;

    public function __construct($arguments, PaletteInterface $palette, MetadataBag $metadata)
    {
        $this->arguments = $arguments;
        $this->palette = $palette;
        $this->metadata = $metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function copy()
    {
        return new self($this->arguments, $this->palette, clone $this->metadata);
    }

    /**
     * {@inheritdoc}
     */
    public function crop(PointInterface $start, BoxInterface $size)
    {
        if (!$start->in($this->getSize())) {
            throw new OutOfBoundsException('Crop coordinates must start at minimum 0, 0 position from top left corner, crop height and width must be positive integers and must not exceed the current image borders');
        }

        $this->arguments[] = '-crop';
        $this->arguments[] = sprintf('%dx%d+%d+%d', $size->getWidth(), $size->getHeight(), $start->getX(), $start->getY());

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function flipHorizontally()
    {
        $this->arguments[] = '-flop';

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function flipVertically()
    {
        $this->arguments[] = '-flip';

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function strip()
    {
        $this->arguments[] = '-strip';
        //$this->profile($this->palette->profile());

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function paste(ImageInterface $image, PointInterface $start, $alpha = 100)
    {
        if (!$this->getSize()->contains($image->getSize(), $start)) {
            throw new OutOfBoundsException('Cannot paste image of the given size at the specified position, as it moves outside of the current image\'s box');
        }

        $file = tempnam(sys_get_temp_dir(), 'ic');
        $image->save($file);

        $process = 'composite ';

        try {
            $this->imagick->compositeImage($image->imagick, \Imagick::COMPOSITE_DEFAULT, $start->getX(), $start->getY());
        } catch (\ImagickException $e) {
            throw new RuntimeException('Paste operation failed', $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function resize(BoxInterface $size, $filter = ImageInterface::FILTER_UNDEFINED)
    {
        $this->arguments[] = '-resize';
        $this->arguments[] = sprintf('%dx%d', $size->getWidth(), $size->getHeight());

        $filter = $this->getFilter($filter);
        if ($filter) {
            $this->arguments[] = '-filter';
            $this->arguments[] = $filter;
        }

        //$this->arguments[] = '-blur';
        //$this->arguments[] = 1;

        $this->size = new Box($size->getWidth(), $size->getHeight());

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function rotate($angle, ColorInterface $background = null)
    {
        $color = $background ? $background : $this->palette->color('fff');

        $this->arguments[] = '-rotate';
        $this->arguments[] = $angle;

        // FIX size
        /*try {
            $pixel = $this->getColor($color);

            $this->imagick->rotateimage($pixel, $angle);

            $pixel->clear();
            $pixel->destroy();
        } catch (\ImagickException $e) {
            throw new RuntimeException('Rotate operation failed', $e->getCode(), $e);
        }*/

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function save($path = null, array $options = array())
    {
        $path = null === $path ? $this->arguments['file'] : $path;
        if (null === $path) {
            throw new RuntimeException('You can omit save path only if image has been open from a file');
        }

        $arguments = $this->getArguments($options, $path);
        $arguments[] = $path;

        $process = new Process($arguments);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('Save operation failed: '.$process->getErrorOutput());
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function show($format, array $options = array())
    {
        header('Content-type: '.$this->getMimeType($format));
        echo $this->get($format, $options);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function get($format, array $options = array())
    {
        $options['format'] = $format;

        $temp = tempnam(sys_get_temp_dir(), 'im');
        $this->save($temp, $options);

        return file_get_contents($temp);
    }

    /**
     * {@inheritdoc}
     */
    public function interlace($scheme)
    {
        static $supportedInterlaceSchemes = array(
            ImageInterface::INTERLACE_NONE      => 'none',
            ImageInterface::INTERLACE_LINE      => 'line',
            ImageInterface::INTERLACE_PLANE     => 'plane',
            ImageInterface::INTERLACE_PARTITION => 'partition',
        );

        if (!array_key_exists($scheme, $supportedInterlaceSchemes)) {
            throw new InvalidArgumentException('Unsupported interlace type');
        }

        $this->arguments[] = '-interlace';
        $this->arguments[] = $supportedInterlaceSchemes[$scheme];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->get('png');
    }

    /**
     * {@inheritdoc}
     */
    public function draw()
    {
        return new Drawer($this->arguments);
    }

    /**
     * {@inheritdoc}
     */
    public function effects()
    {
        return new Effects($this->arguments);
    }

    /**
     * {@inheritdoc}
     */
    public function getSize()
    {
        if (null === $this->size) {
            $process = ProcessBuilder::create(array($this->arguments['convert'], $this->arguments['file'], '-format', '%wx%h', 'info:'))->getProcess();
            $process->run();

            preg_match('/(\d+)x(\d+)/', trim($process->getOutput()), $matches);

            $this->size = new Box($matches[1], $matches[2]);
        }

        return $this->size;
    }

    /**
     * {@inheritdoc}
     */
    public function applyMask(ImageInterface $mask)
    {
        if (!$mask instanceof self) {
            throw new InvalidArgumentException('Can only apply instances of Imagine\Imagick\Image as masks');
        }

        $size = $this->getSize();
        $maskSize = $mask->getSize();

        if ($size != $maskSize) {
            throw new InvalidArgumentException(sprintf('The given mask doesn\'t match current image\'s size, Current mask\'s dimensions are %s, while image\'s dimensions are %s', $maskSize, $size));
        }

        $mask = $mask->mask();
        $mask->imagick->negateImage(true);

        try {
            // remove transparent areas of the original from the mask
            $mask->imagick->compositeImage($this->imagick, \Imagick::COMPOSITE_DSTIN, 0, 0);
            $this->imagick->compositeImage($mask->imagick, \Imagick::COMPOSITE_COPYOPACITY, 0, 0);

            $mask->imagick->clear();
            $mask->imagick->destroy();
        } catch (\ImagickException $e) {
            throw new RuntimeException('Apply mask operation failed', $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function mask()
    {
        $mask = $this->copy();

        try {
            $mask->imagick->modulateImage(100, 0, 100);
            $mask->imagick->setImageMatte(false);
        } catch (\ImagickException $e) {
            throw new RuntimeException('Mask operation failed', $e->getCode(), $e);
        }

        return $mask;
    }

    /**
     * {@inheritdoc}
     */
    public function fill(FillInterface $fill)
    {
        try {
            if ($this->isLinearOpaque($fill)) {
                $this->applyFastLinear($fill);
            } else {
                $iterator = $this->imagick->getPixelIterator();

                foreach ($iterator as $y => $pixels) {
                    foreach ($pixels as $x => $pixel) {
                        $color = $fill->getColor(new Point($x, $y));

                        $pixel->setColor((string) $color);
                        $pixel->setColorValue(\Imagick::COLOR_ALPHA, number_format(round($color->getAlpha() / 100, 2), 1));
                    }

                    $iterator->syncIterator();
                }
            }
        } catch (\ImagickException $e) {
            throw new RuntimeException('Fill operation failed', $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function histogram()
    {
        try {
            $pixels = $this->imagick->getImageHistogram();
        } catch (\ImagickException $e) {
            throw new RuntimeException('Error while fetching histogram', $e->getCode(), $e);
        }

        $image = $this;

        return array_map(function (\ImagickPixel $pixel) use ($image) {
            return $image->pixelToColor($pixel);
        }, $pixels);
    }

    /**
     * {@inheritdoc}
     */
    public function getColorAt(PointInterface $point)
    {
        if (!$point->in($this->getSize())) {
            throw new RuntimeException(sprintf('Error getting color at point [%s,%s]. The point must be inside the image of size [%s,%s]', $point->getX(), $point->getY(), $this->getSize()->getWidth(), $this->getSize()->getHeight()));
        }

        try {
            $pixel = $this->imagick->getImagePixelColor($point->getX(), $point->getY());
        } catch (\ImagickException $e) {
            throw new RuntimeException('Error while getting image pixel color', $e->getCode(), $e);
        }

        return $this->pixelToColor($pixel);
    }

    /**
     * {@inheritdoc}
     */
    public function layers()
    {
        if (null === $this->layers) {
            $this->layers = new Layers($this, $this->palette, $this->resource);
        }

        return $this->layers;
    }

    /**
     * {@inheritdoc}
     */
    public function usePalette(PaletteInterface $palette)
    {
        if ($this->palette->name() === $palette->name()) {
            return $this;
        }

        $this->profile($palette->profile());
        $this->setColorspace($palette);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function palette()
    {
        return $this->palette;
    }

    /**
     * {@inheritdoc}
     */
    public function profile(ProfileInterface $profile)
    {
        $file = tempnam(sys_get_temp_dir(), 'ip');
        file_put_contents($file, $profile->data());

        $this->arguments[] = '-profile';
        $this->arguments[] = $file;

        return $this;
    }

    /**
     * Gets specifically formatted color string from Color instance
     *
     * @param ColorInterface $color
     *
     * @return \ImagickPixel
     */
    private function getColor(ColorInterface $color)
    {
        $pixel = new \ImagickPixel((string) $color);
        $pixel->setColorValue(\Imagick::COLOR_ALPHA, number_format(round($color->getAlpha() / 100, 2), 1));

        return $pixel;
    }

    /**
     * @param array  $options
     * @param string $path
     */
    private function getArguments(array $options, $path = null)
    {
        $arguments = $this->arguments;

        $options = $this->updateSaveOptions($options);

        if (isset($options['format'])) {
            $format = $options['format'];
        } elseif ('' !== $extension = pathinfo($path, \PATHINFO_EXTENSION)) {
            $format = $extension;
        } else {
            $format = pathinfo($this->arguments['file'], \PATHINFO_EXTENSION);
        }

        if (isset($options['animated']) && true === $options['animated']) {
            $format = isset($options['format']) ? $options['format'] : 'gif';
            $delay = isset($options['animated.delay']) ? $options['animated.delay'] : null;
            $loops = isset($options['animated.loops']) ? $options['animated.loops'] : 0;

            $options['flatten'] = false;

            $this->layers()->animate($format, $delay, $loops);
        } else {
            $this->layers()->merge();
        }

        $arguments[] = '-format';
        $arguments[] = strtolower($format);

        if (isset($options['quality'])) {
            $compression = $options['quality'];
        }

        if (isset($options['jpeg_quality']) && in_array($format, array('jpeg', 'jpg', 'pjpeg'))) {
            $compression = $options['jpeg_quality'];
        }

        if ((isset($options['png_compression_level']) || isset($options['png_compression_filter'])) && $format === 'png') {
            // first digit: compression level (default: 7)
            if (isset($options['png_compression_level'])) {
                if ($options['png_compression_level'] < 0 || $options['png_compression_level'] > 9) {
                    throw new InvalidArgumentException('png_compression_level option should be an integer from 0 to 9');
                }
                $compression = $options['png_compression_level'] * 10;
            } else {
                $compression = 70;
            }

            // second digit: compression filter (default: 5)
            if (isset($options['png_compression_filter'])) {
                if ($options['png_compression_filter'] < 0 || $options['png_compression_filter'] > 9) {
                    throw new InvalidArgumentException('png_compression_filter option should be an integer from 0 to 9');
                }
                $compression += $options['png_compression_filter'];
            } else {
                $compression += 5;
            }
        }

        if (isset($compression)) {
            $arguments[] = '-quality';
            $arguments[] = $compression;
        }

        if (isset($options['resolution-units']) && isset($options['resolution-x']) && isset($options['resolution-y'])) {
            if ($options['resolution-units'] == ImageInterface::RESOLUTION_PIXELSPERCENTIMETER) {
                $arguments[] = '-units';
                $arguments[] = 'PixelsPerCentimeter';
            } elseif ($options['resolution-units'] == ImageInterface::RESOLUTION_PIXELSPERINCH) {
                $arguments[] = '-units';
                $arguments[] = 'PixelsPerInch';
            } else {
                throw new RuntimeException('Unsupported image unit format');
            }

            $filter = ImageInterface::FILTER_UNDEFINED;
            if (!empty($options['resampling-filter'])) {
                $filter = $options['resampling-filter'];
            }

            $arguments[] = '-density';
            $arguments[] = sprintf('%dx%d', $options['resolution-x'], $options['resolution-y']);

            $arguments[] = '-resample';
            $arguments[] = sprintf('%dx%d', $options['resolution-x'], $options['resolution-y']);

            $filter = $this->getFilter($filter);
            if ($filter) {
                $arguments[] = '-filter';
                $arguments[] = $filter;
            }

            //$this->arguments[] = '-blur';
            //$this->arguments[] = 0;
        }

        if ((!isset($options['flatten']) || $options['flatten'] === true) && count($this->layers()) > 1) {
            $this->flatten();
        }

        return $arguments;
    }

    /**
     * Checks whether given $fill is linear and opaque
     *
     * @param FillInterface $fill
     *
     * @return Boolean
     */
    private function isLinearOpaque(FillInterface $fill)
    {
        return $fill instanceof Linear && $fill->getStart()->isOpaque() && $fill->getEnd()->isOpaque();
    }

    /**
     * Performs optimized gradient fill for non-opaque linear gradients
     */
    private function applyFastLinear(Linear $fill)
    {
        $gradient = new \Imagick();
        $size     = $this->getSize();
        $color    = sprintf('gradient:%s-%s', (string) $fill->getStart(), (string) $fill->getEnd());

        if ($fill instanceof Horizontal) {
            $gradient->newPseudoImage($size->getHeight(), $size->getWidth(), $color);
            $gradient->rotateImage(new \ImagickPixel(), 90);
        } else {
            $gradient->newPseudoImage($size->getWidth(), $size->getHeight(), $color);
        }

        $this->imagick->compositeImage($gradient, \Imagick::COMPOSITE_OVER, 0, 0);
        $gradient->clear();
        $gradient->destroy();
    }

    /**
     * Internal
     *
     * Get the mime type based on format.
     *
     * @param string $format
     *
     * @return string mime-type
     *
     * @throws RuntimeException
     */
    private function getMimeType($format)
    {
        static $mimeTypes = array(
            'jpeg' => 'image/jpeg',
            'jpg'  => 'image/jpeg',
            'gif'  => 'image/gif',
            'png'  => 'image/png',
            'wbmp' => 'image/vnd.wap.wbmp',
            'xbm'  => 'image/xbm',
        );

        if (!isset($mimeTypes[$format])) {
            throw new RuntimeException(sprintf('Unsupported format given. Only %s are supported, %s given', implode(", ", array_keys($mimeTypes)), $format));
        }

        return $mimeTypes[$format];
    }

    /**
     * Sets colorspace and image type, assigns the palette.
     *
     * @param PaletteInterface $palette
     *
     * @throws InvalidArgumentException
     */
    private function setColorspace(PaletteInterface $palette)
    {
        static $typeMapping = array(
            // We use Matte variants to preserve alpha
            PaletteInterface::PALETTE_CMYK      => 'TrueColorMatte',
            PaletteInterface::PALETTE_RGB       => 'TrueColorMatte',
            PaletteInterface::PALETTE_GRAYSCALE => 'GrayscaleMate',
        );

        if (!isset(static::$colorspaceMapping[$palette->name()])) {
            throw new InvalidArgumentException(sprintf('The palette %s is not supported by Imagick driver', $palette->name()));
        }

        $this->arguments[] = '-type';
        $this->arguments[] = $typeMapping[$palette->name()];

        $this->arguments[] = '-colorspace';
        $this->arguments[] = static::$colorspaceMapping[$palette->name()];
    }

    /**
     * Returns the filter if it's supported.
     *
     * @param string $filter
     *
     * @return string
     *
     * @throws InvalidArgumentException If the filter is unsupported.
     */
    private function getFilter($filter = ImageInterface::FILTER_UNDEFINED)
    {
        static $supportedFilters = array(
            ImageInterface::FILTER_UNDEFINED => '',
            ImageInterface::FILTER_BESSEL    => 'Kaiser',
            ImageInterface::FILTER_BLACKMAN  => 'Blackman',
            ImageInterface::FILTER_BOX       => 'Box',
            ImageInterface::FILTER_CATROM    => 'Catrom',
            ImageInterface::FILTER_CUBIC     => 'Cubic',
            ImageInterface::FILTER_GAUSSIAN  => 'Gaussian',
            ImageInterface::FILTER_HANNING   => 'Hanning',
            ImageInterface::FILTER_HAMMING   => 'Hamming',
            ImageInterface::FILTER_HERMITE   => 'Hermite',
            ImageInterface::FILTER_LANCZOS   => 'Lanczos',
            ImageInterface::FILTER_MITCHELL  => 'Mitchell',
            ImageInterface::FILTER_POINT     => 'Point',
            ImageInterface::FILTER_QUADRATIC => 'Quadratic',
            ImageInterface::FILTER_SINC      => 'Sinc',
            ImageInterface::FILTER_TRIANGLE  => 'Triangle',
        );

        if (!array_key_exists($filter, $supportedFilters)) {
            throw new InvalidArgumentException(sprintf(
                'The resampling filter "%s" is not supported by Imagick driver.',
                $filter
            ));
        }

        return $supportedFilters[$filter];
    }
}
