<?php

declare(strict_types=1);

/*
 * This file is part of Member Picture Feed.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/member-picture-feed
 */

namespace Markocupic\MemberPictureFeed\Image;

use Contao\CoreBundle\Framework\ContaoFramework;

class ImageRotate
{
    public function __construct(
        private readonly ContaoFramework $framework,
    ) {
    }

    /**
     * @throws \ImagickException
     * @throws \Exception
     */
    public static function rotate(string $sourcePath, int $angle = 90, string|null $targetPath = null): bool
    {
        if (!is_file($sourcePath)) {
            return false;
        }

        if (!$targetPath) {
            $targetPath = $sourcePath;
        }

        if (class_exists('Imagick') && class_exists('ImagickPixel')) {
            $imagick = new \Imagick();

            $imagick->readImage($sourcePath);
            $imagick->rotateImage(new \ImagickPixel('none'), $angle);
            $imagick->writeImage($targetPath);
            $imagick->clear();
            $imagick->destroy();

            return true;
        }

        if (\function_exists('imagerotate')) {
            $source = imagecreatefromjpeg($sourcePath);

            //rotate
            $imgTmp = imagerotate($source, $angle, 0);

            // Output
            imagejpeg($imgTmp, $targetPath);

            imagedestroy($source);

            if (is_file($targetPath)) {
                return true;
            }
        }

        throw new \Exception('Could not rotate image.');
    }
}
