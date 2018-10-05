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
use Contao\DC_Table;
use Contao\File;
use Contao\MemberModel;


/**
 * Class MemberPictureFeed
 * @package Markocupic\MemberPictureFeedBundle\Contao\Classes
 */
class MemberPictureFeed
{
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
            static::deleteMemberPictures($objMember);
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
                static::deleteMemberPictures($oMember);
            }
        }
    }

    /**
     * @param $objMember
     */
    public static function deleteMemberPictures($objMember)
    {
        $objPictures = Database::getInstance()->prepare('SELECT * FROM tl_files WHERE isMemberPictureFeed=? AND memberPictureFeedUserId=?')->execute('1', $objMember->id);
        while ($objPictures->next())
        {
            $objFile = new File($objPictures->path);
            if ($objFile !== null)
            {
                $objFile->delete();
            }
            Database::getInstance()->prepare('DELETE FROM tl_files WHERE id=?')->execute($objPictures->id);
        }
    }
}
