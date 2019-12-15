<?php
/**
 * Contao module: Member Picture Feed Bundle
 * Copyright (c) 2008-2018 Marko Cupic
 * @package member-picture-feed-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2018
 * @link https://github.com/markocupic/member-picture-feed
 */

namespace Markocupic\MemberPictureFeedBundle\Contao\Classes;

use Contao\Database;
use Contao\Dbafs;
use Contao\DC_Table;
use Contao\File;
use Contao\Folder;

use Contao\FilesModel;
use Contao\MemberModel;
use Contao\Message;
use Contao\System;

/**
 * Class MemberPictureFeed
 * @package Markocupic\MemberPictureFeedBundle\Contao\Classes
 */
class MemberPictureFeed
{

    /**
     * @param $fileId
     * @param int $angle
     * @param string $target
     * @return bool
     * @throws \ImagickException
     */
    public static function rotateImage($fileId, int $angle = 270, string $target = ''): bool
    {
        if (!is_numeric($fileId) && $fileId < 1)
        {
            return false;
        }

        $objFiles = FilesModel::findById($fileId);
        if ($objFiles === null)
        {
            return false;
        }

        $src = $objFiles->path;

        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        if (!file_exists($rootDir . '/' . $src))
        {
            Message::addError(sprintf('File "%s" not found.', $src));
            return false;
        }

        $objFile = new File($src);
        if (!$objFile->isGdImage)
        {
            Message::addError(sprintf('File "%s" could not be rotated because it is not an image.', $src));
            return false;
        }

        $source = $rootDir . '/' . $src;
        if ($target === '')
        {
            $target = $source;
        }
        else
        {
            new Folder(dirname($target));
            $target = $rootDir . '/' . $target;
        }

        if (class_exists('Imagick') && class_exists('ImagickPixel'))
        {
            $imagick = new \Imagick();

            $imagick->readImage($source);
            $imagick->rotateImage(new \ImagickPixel('none'), $angle);
            $imagick->writeImage($target);
            $imagick->clear();
            $imagick->destroy();
            return true;
        }
        elseif (function_exists('imagerotate'))
        {
            $source = imagecreatefromjpeg($rootDir . '/' . $src);

            //rotate
            $imgTmp = imagerotate($source, $angle, 0);

            // Output
            imagejpeg($imgTmp, $target);

            imagedestroy($source);
            if (is_file($target))
            {
                return true;
            }
        }
        else
        {
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
    public function closeAccountHook($intId, $strMode, $objModule)
    {
        $objMember = MemberModel::findByPk($intId);
        if ($objMember !== null)
        {
            static::deleteAllMemberPictures($objMember);
        }
    }

    /**
     * @param DC_Table $objMember
     * @param $undoId
     */
    public function memberOndeleteCallback(DC_Table $objMember, $undoId)
    {
        if ($objMember->activeRecord->id > 0)
        {
            $oMember = MemberModel::findByPk($objMember->activeRecord->id);
            if ($oMember !== null)
            {
                static::deleteAllMemberPictures($oMember);
            }
        }
    }

    /**
     * @param $objMember
     */
    public static function deleteAllMemberPictures($objMember)
    {
        $objPictures = Database::getInstance()->prepare('SELECT * FROM tl_files WHERE isMemberPictureFeed=? AND memberPictureFeedUserId=?')->execute('1', $objMember->id);
        while ($objPictures->next())
        {
            $res = '';
            $objFile = new File($objPictures->path);
            if ($objFile !== null)
            {
                $res = $objFile->path;
                $objFile->delete();
            }
            Database::getInstance()->prepare('DELETE FROM tl_files WHERE id=?')->execute($objPictures->id);

            if ($res !== '')
            {
                Dbafs::updateFolderHashes(dirname($res));
            }
        }
    }
}
