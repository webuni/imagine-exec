<?php

namespace Webuni\ImagineExec\ImageMagick;

use Imagine\Image\AbstractFont;
use Imagine\Image\Box;
use Imagine\Image\Palette\Color\ColorInterface;

class Font extends AbstractFont
{
    /**
     * @var \Imagick
     */
    private $imagick;

    /**
     * @param \Imagick       $imagick
     * @param string         $file
     * @param integer        $size
     * @param ColorInterface $color
     */
    public function __construct($arguments, $file, $size, ColorInterface $color)
    {
        parent::__construct($file, $size, $color);
    }

    /**
     * {@inheritdoc}
     */
    public function box($string, $angle = 0)
    {
        $text = new \ImagickDraw();

        $text->setFont($this->file);

        /**
         * @see http://www.php.net/manual/en/imagick.queryfontmetrics.php#101027
         *
         * ensure font resolution is the same as GD's hard-coded 96
         */
        if (version_compare(phpversion("imagick"), "3.0.2", ">=")) {
            $text->setResolution(96, 96);
            $text->setFontSize($this->size);
        } else {
            $text->setFontSize((int) ($this->size * (96 / 72)));
        }

        $info = $this->imagick->queryFontMetrics($text, $string);

        $box = new Box($info['textWidth'], $info['textHeight']);

        return $box;
    }
}
