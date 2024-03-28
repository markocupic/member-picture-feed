<?php

declare(strict_types=1);

/*
 * This file is part of Member Picture Feed.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/member-picture-feed
 */

// Fields
$GLOBALS['TL_DCA']['tl_files']['fields']['isMemberPictureFeed'] = [
    'sql' => ['type' => 'boolean', 'default' => false],
];

$GLOBALS['TL_DCA']['tl_files']['fields']['memberPictureFeedUserId'] = [
    'sql' => "int(10) unsigned NOT NULL default 0",
];

$GLOBALS['TL_DCA']['tl_files']['fields']['memberPictureFeedUploadTime'] = [
    'sql' => "int(10) unsigned NOT NULL default 0",
];
