<?php

declare(strict_types=1);

/*
 * This file is part of Member Picture Feed.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/member-picture-feed
 */

namespace Markocupic\MemberPictureFeed\Twig;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\MemberModel;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigTemplateManager extends AbstractExtension
{
    private ContaoFramework $framework;

    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('mpf_getFilesModelFromPath', [$this, 'getFilesModelFromPath']),
            new TwigFunction('mpf_getOwnerFromPath', [$this, 'getOwnerFromPath']),
        ];
    }

    public function getFilesModelFromPath(string $path): FilesModel|null
    {
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        return $filesModelAdapter->findByPath($path);
    }

    public function getOwnerFromPath(string $path): MemberModel|null
    {
        if (null === ($objFiles = $this->getFilesModelFromPath($path))) {
            return null;
        }

        if (isset($objFiles->memberPictureFeedUserId)) {
            $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

            if (null !== ($user = $memberModelAdapter->findByPk($objFiles->memberPictureFeedUserId))) {
                return $user;
            }
        }

        return null;
    }
}
