<?php

declare(strict_types=1);

/*
 * This file is part of Member Picture Feed.
 *
 * Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/member-picture-feed
 */

use Markocupic\MemberPictureFeed\Controller\FrontendModule\MemberPictureFeedUploadController;

// Palette
$GLOBALS['TL_DCA']['tl_module']['palettes'][MemberPictureFeedUploadController::TYPE] = '{title_legend},name,headline,type;{member_picture_feed_settings},memberPictureFeedUploadFolder,imgSize,memberPictureFeedUploadPictureLimit;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';

// Fields
$GLOBALS['TL_DCA']['tl_module']['fields']['memberPictureFeedUploadFolder'] = [
    'exclude'   => true,
    'inputType' => 'fileTree',
    'eval'      => ['fieldType' => 'radio', 'filesOnly' => false, 'mandatory' => true, 'tl_class' => 'clr'],
    'sql'       => 'binary(16) NULL',
];

$GLOBALS['TL_DCA']['tl_module']['fields']['memberPictureFeedUploadPictureLimit'] = [
    'exclude'   => true,
    'default'   => 3,
    'inputType' => 'text',
    'eval'      => ['mandatory' => false, 'rgxp' => 'natural', 'tl_class' => 'w50'],
    'sql'       => "smallint(5) unsigned NOT NULL default 0",
];
