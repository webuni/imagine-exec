<?php

namespace Webuni\ImagineExec\ImageMagick;

use Imagine\Effects\EffectsInterface;
use Imagine\Exception\InvalidArgumentException;
use Imagine\Exception\NotSupportedException;
use Imagine\Image\Palette\Color\ColorInterface;
use Imagine\Image\Palette\Color\RGB;
use Imagine\Utils\Matrix;
use Symfony\Component\Process\ProcessBuilder;

class Effects implements EffectsInterface
{
    private $builder;

    public function __construct(ProcessBuilder $builder)
    {
        $this->builder = $builder;
    }

    /**
     * {@inheritdoc}
     */
    public function gamma($correction)
    {
        $this->builder->add('+gamma');
        $this->builder->add($correction);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function negative()
    {
        $this->builder->add('-negate');

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function grayscale()
    {
        $this->builder->add('-colorspace');
        $this->builder->add('Gray');

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function colorize(ColorInterface $color)
    {
        if (!$color instanceof RGB) {
            throw new NotSupportedException('Colorize with non-rgb color is not supported');
        }

        $this->builder->add('-colorize');
        $this->builder->add((string) $color);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function sharpen()
    {
        $this->builder->add('-sharpen');
        $this->builder->add('2x1');

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function blur($sigma = 1)
    {
        $this->builder->add('-gaussian-blur');
        $this->builder->add('0x'.$sigma);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function brightness($brightness)
    {
        $brightness = (int) round($brightness);
        if ($brightness < -100 || $brightness > 100) {
            throw new InvalidArgumentException(sprintf('The %1$s argument can range from %2$d to %3$d, but you specified %4$d.', '$brightness', -100, 100, $brightness));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function convolve(Matrix $matrix)
    {
        if ($matrix->getWidth() !== 3 || $matrix->getHeight() !== 3) {
            throw new InvalidArgumentException(sprintf('A convolution matrix must be 3x3 (%dx%d provided).', $matrix->getWidth(), $matrix->getHeight()));
        }
    }
}
