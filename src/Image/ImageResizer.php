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

use Contao\CoreBundle\Image\ImageFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class ImageResizer
{
    public function __construct(
        #[Autowire(service: 'contao.image.factory')]
        private ImageFactory $imageFactory,
        private LoggerInterface|null $contaoErrorLogger,
    ) {
    }

    public function resize(string $path, int $maxWidth = 0, int $maxHeight = 0, string $mode = 'proportional'): bool
    {
        $maxWidth = abs($maxWidth);
        $maxHeight = abs($maxHeight);

        if (!$this->isGdImage($path) && !$this->isSvgImage($path)) {
            return false;
        }

        if (!$maxWidth && !$maxHeight) {
            return false;
        }

        if (!$maxWidth && $maxHeight) {
            $maxWidth = $maxHeight;
        }

        if (!$maxHeight && $maxWidth) {
            $maxHeight = $maxWidth;
        }

        try {
            $this->imageFactory->create($path, [$maxWidth, $maxHeight, $mode], $path);
        } catch (\Exception $e) {
            $this->contaoErrorLogger?->error(\sprintf('Image resizing failed for "%s". Error: %s', $path, $e->getMessage()));

            return false;
        }

        return true;
    }

    private function isGdImage(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return \in_array($extension, ['gif', 'jpg', 'jpeg', 'png', 'webp', 'avif', 'heic', 'jxl'], true);
    }

    private function isSvgImage(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return \in_array($extension, ['svg', 'svgz'], true);
    }
}
