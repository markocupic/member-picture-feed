<?php
/**
 * Contao module: Member Picture Feed Bundle
 * Copyright (c) 2008-2018 Marko Cupic
 * @package member-picture-feed-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2018
 * @link https://github.com/markocupic/member-picture-feed
 */

// Frontend Modules
$GLOBALS['FE_MOD']['member_picture_feed'] = array(
    'memberPictureFeedUpload' => 'Markocupic\MemberPictureFeedBundle\Contao\Modules\MemberPictureFeedUpload',
);

// Hooks
$GLOBALS['TL_HOOKS']['closeAccount'][] = array('Markocupic\MemberPictureFeedBundle\Contao\Classes\MemberPictureFeed', 'closeAccountHook');


// Front end form fields
$GLOBALS['TL_FFL']['uploadDropzone'] = 'FormFileUploadDropzone';

