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

namespace Markocupic\MemberPictureFeed\Contao\Classes;

use Contao\Database;
use Contao\Dbafs;
use Contao\DC_Table;
use Contao\File;
use Contao\FilesModel;
use Contao\Folder;
use Contao\MemberModel;
use Contao\Message;
use Contao\System;

/**
 * Class MemberPictureFeed.
 */
class MemberPictureFeed
{
    /**
     * @param $fileId
     *
     * @throws \ImagickException
     */
    public static function rotateImage($fileId, int $angle = 270, string $target = ''): bool
    {
        if (!is_numeric($fileId) && $fileId < 1) {
            return false;
        }

        $objFiles = FilesModel::findById($fileId);

        if (null === $objFiles) {
            return false;
        }

        $src = $objFiles->path;

        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        if (!file_exists($rootDir.'/'.$src)) {
            Message::addError(sprintf('File "%s" not found.', $src));

            return false;
        }

        $objFile = new File($src);

        if (!$objFile->isGdImage) {
            Message::addError(sprintf('File "%s" could not be rotated because it is not an image.', $src));

            return false;
        }

        $source = $rootDir.'/'.$src;

        if ('' === $target) {
            $target = $source;
        } else {
            new Folder(\dirname($target));
            $target = $rootDir.'/'.$target;
        }

        if (class_exists('Imagick') && class_exists('ImagickPixel')) {
            $imagick = new \Imagick();

            $imagick->readImage($source);
            $imagick->rotateImage(new \ImagickPixel('none'), $angle);
            $imagick->writeImage($target);
            $imagick->clear();
            $imagick->destroy();

            return true;
        }

        if (\function_exists('imagerotate')) {
            $source = imagecreatefromjpeg($rootDir.'/'.$src);

            //rotate
            $imgTmp = imagerotate($source, $angle, 0);

            // Output
            imagejpeg($imgTmp, $target);

            imagedestroy($source);

            if (is_file($target)) {
                return true;
            }
        } else {
            Message::addError(sprintf('Please install class "%s" or php function "%s" for rotating images.', 'Imagick', 'imagerotate'));

            return false;
        }

        return false;
    }

    /**
     * @param $intId
     * @param $strMode
     * @param $objModule
     */
    public function closeAccountHook($intId, $strMode, $objModule): void
    {
        $objMember = MemberModel::findByPk($intId);

        if (null !== $objMember) {
            static::deleteAllMemberPictures($objMember);
        }
    }

    /**
     * @param $undoId
     */
    public function memberOndeleteCallback(DC_Table $objMember, $undoId): void
    {
        if ($objMember->activeRecord->id > 0) {
            $oMember = MemberModel::findByPk($objMember->activeRecord->id);

            if (null !== $oMember) {
                static::deleteAllMemberPictures($oMember);
            }
        }
    }

    /**
     * @param $objMember
     */
    public static function deleteAllMemberPictures($objMember): void
    {
        $objPictures = Database::getInstance()->prepare('SELECT * FROM tl_files WHERE isMemberPictureFeed=? AND memberPictureFeedUserId=?')->execute('1', $objMember->id);

        while ($objPictures->next()) {
            $res = '';
            $objFile = new File($objPictures->path);

            if (null !== $objFile) {
                $res = $objFile->path;
                $objFile->delete();
            }
            Database::getInstance()->prepare('DELETE FROM tl_files WHERE id=?')->execute($objPictures->id);

            if ('' !== $res) {
                Dbafs::updateFolderHashes(\dirname($res));
            }
        }
    }
}
