<?php

/*
 * This file is part of the badge-poser package.
 *
 * (c) PUGX <http://pugx.github.io/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PUGX\BadgeBundle\Service;

use Symfony\Bridge\Monolog\Logger;
use Imagine\Image\Color;
use Imagine\Image\ImagineInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\Point;
use InvalidArgumentException;

/**
 * Class ImageCreator
 *
 * @author Giulio De Donato <liuggio@gmail.com>
 * @author Leonardo Proietti <leonardo.proietti@gmail.com>
 */
class ImageCreator implements ImageCreatorInterface
{
    private $logger;
    private $imagine;
    private $normalizer;
    protected $dispatcher;
    protected $imageNames = array('empty' => 'empty.png', 'downloads' => 'downloads.png', 'stable' => 'stable.png', 'unstable' => 'unstable.png', 'error' => 'error.png');
    protected $imagePath;
    protected $fontPath;
    protected $defaultFont;
    protected $defaultImage;

    /**
     * Class constructor.
     *
     * @param Logger           $logger
     * @param ImagineInterface $imagine
     * @param string           $fontPath
     * @param string           $imagePath
     * @param string           $defaultFont
     * @param null             $defaultImage
     */
    public function __construct(Logger $logger, ImagineInterface $imagine, $fontPath, $imagePath, $defaultFont = 'Monaco.ttf', $defaultImage = null)
    {
        $this->logger = $logger;
        $this->imagine = $imagine;
        $this->fontPath = $fontPath;
        $this->imagePath = $imagePath;

        if (!$defaultImage) {
            $this->defaultImage = $this->imageNames['empty'];
        }
        $this->defaultFont = $defaultFont;
    }

    /**
     * Stream the output.
     *
     * @param ImageInterface $image
     *
     * @return Boolean
     */
    public function streamRawImageData(ImageInterface $image)
    {
        return $image->show('png');
    }

    /**
     * Add a shadowed text to an Image.
     *
     * @param ImageInterface $image      image
     * @param string         $text       text
     * @param int            $x          x
     * @param int            $y          y
     * @param float          $size       size
     * @param string         $font       font
     * @param Boolean        $withShadow cast shadow
     * @param int            $angle      angle
     * @param int            $padding    padding
     *
     * @return ImageInterface
     * @throws \UnexpectedValueException
     */
    private function addShadowedText(ImageInterface $image, $text, $x = 3, $y = null, $size = 8.5, $font = null, $withShadow = true, $angle = 0, $padding = 2)
    {
        if (null === $font) {
            $font = $this->fontPath . DIRECTORY_SEPARATOR . $this->defaultFont;
        }

        $white = $this->imagine->font($font, $size, new Color('ffffff'));
        $black = $this->imagine->font($font, $size, new Color('000000'));

        if (null === $y) {
            // vertically centering textbox
            $y = ($image->getSize()->getHeight()+$padding - $white->box($text)->getHeight()) / 2;
        }
        $space = $image->getSize()->getWidth() - 1 - $x;
        $newX = (($space - $white->box($text)->getWidth()) / 2);

        // if it doesn't fit recall with lower font size
        if ($newX < 1 && $size > 5) {
            $size -= 0.5;

            return $this->addShadowedText($image, $text, $x, $y, $size, $font, $withShadow, $angle);
        }

        $newX += $x;

        try {
            if ($withShadow) {
                $image
                    ->draw()
                    ->text($text, $black, new Point($newX, $y + 1));
            }
        } catch (\Imagine\Exception\RuntimeException $e) {
            throw new \UnexpectedValueException('Impossible to add shadow text to the image with imagettftext.', $e);
        }

        try {
            $image
                ->draw()
                ->text($text, $white, new Point($newX, $y));
        } catch (\Imagine\Exception\RuntimeException $e) {
            throw new \UnexpectedValueException('Impossible to add text to the image with imagettftext.', $e);
        }

        return $image;
    }

    /**
     * Create the image resource, with Blending and Alpha.
     *
     * @param string $imagePath
     *
     * @return ImageInterface
     */
    private function createImage($imagePath)
    {
        return $this->imagine->open($imagePath);
    }

    /**
     * Create the 'downloads' image with the standard Font and download image template.
     *
     * @param string $value
     *
     * @return ImageInterface
     */
    public function createDownloadsImage($value)
    {
        $imagePath = $this->imagePath . DIRECTORY_SEPARATOR . $this->imageNames['downloads'];
        $image = $this->createImage($imagePath);
        $value = $this->normalizer->normalize($value);

        return $this->addShadowedText($image, $value, 64, null, 8, $this->fontPath . DIRECTORY_SEPARATOR . 'DroidSans.ttf');
    }

    /**
     * Create the 'stable' image with the standard Font and stable image template.
     *
     * @param string $value
     *
     * @return ImageInterface
     */
    public function createStableImage($value)
    {
        $imagePath = $this->imagePath . DIRECTORY_SEPARATOR . $this->imageNames['stable'];
        $image = $this->createImage($imagePath);

        return $this->addShadowedText($image, $value, 51);
    }

    /**
     * Create the 'stable:no release' image with the standard Font and stable image template.
     *
     * @param string $value
     *
     * @return ImageInterface
     */
    public function createStableNoImage($value)
    {
        $imagePath = $this->imagePath . DIRECTORY_SEPARATOR . $this->imageNames['stable'];
        $image = $this->createImage($imagePath);

        return $this->addShadowedText($image, $value, 51, null, 8, $this->fontPath . DIRECTORY_SEPARATOR . 'DroidSans.ttf');
    }

    /**
     * Create the 'stable' image with the standard Font and unstable image template.
     *
     * @param string $value
     *
     * @return ImageInterface
     */
    public function createUnstableImage($value = '@dev')
    {
        $imagePath = $this->imagePath . DIRECTORY_SEPARATOR . $this->imageNames['unstable'];
        $image = $this->createImage($imagePath);

        return $this->addShadowedText($image, $value, 51, null, 8);
    }

    /**
     * Create the 'error' image
     *
     * @param string $value
     *
     * @return ImageInterface
     */
    public function createErrorImage($value)
    {
        $imagePath = $this->imagePath . DIRECTORY_SEPARATOR . $this->imageNames['error'];
        $image = $this->createImage($imagePath);

        return $this->addShadowedText($image, $value, 51, null, 8, $this->fontPath . DIRECTORY_SEPARATOR . 'DroidSans.ttf');
    }
}
