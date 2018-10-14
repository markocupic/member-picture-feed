<?php
/**
 * Contao module: Member Picture Feed Bundle
 * Copyright (c) 2008-2018 Marko Cupic
 * @package member-picture-feed-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2018
 * @link https://github.com/markocupic/member-picture-feed
 */

// Fields
$GLOBALS['TL_DCA']['tl_files']['fields']['isMemberPictureFeed'] = array(
    'sql' => "char(1) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_files']['fields']['memberPictureFeedUserId'] = array(
    'sql' => "int(10) unsigned NOT NULL default '0'"
);

$GLOBALS['TL_DCA']['tl_files']['fields']['memberPictureFeedUploadTime'] = array(
    'sql' => "int(10) unsigned NOT NULL default '0'"
);
 