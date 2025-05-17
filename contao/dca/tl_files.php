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

use Contao\CoreBundle\DataContainer\PaletteManipulator;

// Subpalettes
$GLOBALS['TL_DCA']['tl_files']['subpalettes']['isMemberPictureFeed'] = 'memberPictureFeedUserId,memberPictureFeedUploadTime';

// Selectors
$GLOBALS['TL_DCA']['tl_files']['palettes']['__selector__'][] = 'isMemberPictureFeed';

PaletteManipulator::create()
    ->addLegend('member_picture_feed_legend')
    ->addField('isMemberPictureFeed', 'meta', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_files');

// Fields
$GLOBALS['TL_DCA']['tl_files']['fields']['isMemberPictureFeed'] = [
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'checkbox',
    'eval'      => ['submitOnChange' => true],
    'sql'       => ['type' => 'boolean', 'default' => true],
];

$GLOBALS['TL_DCA']['tl_files']['fields']['memberPictureFeedUserId'] = [
    'exclude'   => true,
    'search'    => true,
    'sorting'   => true,
    'filter'    => true,
    'inputType' => 'text',
    'eval'      => ['tl_class' => 'w50'],
    'sql'       => "int(10) unsigned NOT NULL default 0",
];

$GLOBALS['TL_DCA']['tl_files']['fields']['memberPictureFeedUploadTime'] = [
    'exclude'   => true,
    'search'    => true,
    'sorting'   => true,
    'filter'    => true,
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
    'sql'       => "int(10) unsigned NOT NULL default 0",
];
