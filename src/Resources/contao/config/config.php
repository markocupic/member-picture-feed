<?php

declare(strict_types=1);

/*
 * This file is part of Member Picture Feed Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/member-picture-feed
 */

use Markocupic\MemberPictureFeedBundle\Contao\Modules\MemberPictureFeedUpload;
use Markocupic\MemberPictureFeedBundle\Contao\Classes\MemberPictureFeed;

// Frontend Modules
$GLOBALS['FE_MOD']['member_picture_feed'] = [
    'memberPictureFeedUpload' => MemberPictureFeedUpload::class,
];

// Hooks
$GLOBALS['TL_HOOKS']['closeAccount'][] = [MemberPictureFeed::class, 'closeAccountHook'];

// CSS
$GLOBALS['TL_CSS'][] = 'bundles/markocupicmemberpicturefeed/css/fineuploader.min.css|static';
