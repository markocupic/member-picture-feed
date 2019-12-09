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
     * @param $id
     * @param int $angle
     * @return bool
     */
    public static function rotateImage($id, $angle = 90)
    {
        System::getContainer()->get('contao.framework')->initialize();
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        $objFiles = FilesModel::findById($id);
        if ($objFiles === null)
        {
            return false;
        }

        $src = $objFiles->path;

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

        if (!function_exists('imagerotate'))
        {
            Message::addError(sprintf('PHP function "%s" is not installed.', 'imagerotate'));
            return false;
        }

        $source = imagecreatefromjpeg($rootDir . '/' . $src);

        //rotate
        $imgTmp = imagerotate($source, $angle, 0);

        // Output
        imagejpeg($imgTmp, $rootDir . '/' . $src);

        imagedestroy($source);
        return true;
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
