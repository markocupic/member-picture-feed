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

// miscellaneous
$GLOBALS['TL_LANG']['MSC']['memberPictureUploadLimitReached'] = 'Du hast bereits das Upload Maximum erreicht. Um weitere Bilder hochzuladen, musst du zuerst mindestens ein Bild löschen.';
$GLOBALS['TL_LANG']['MSC']['memberPictureUploadLimitReachedDuringUploadProcess'] = 'Das Upload-Limit wurde erreicht. Es konnten nicht alle Fotos hochgeladen werden.';
$GLOBALS['TL_LANG']['MSC']['memberPictureFeedUploadBtnlLabel'] = 'Änderungen übernehmen';
$GLOBALS['TL_LANG']['MSC']['memberPictureFeedFileuploadLabel'] = 'Bilddatei auswählen';
$GLOBALS['TL_LANG']['MSC']['memberPictureFeedFileuploadSuccess'] = 'Bilddatei %s wurde erfolgreich hochgeladen.';

// error messages
$GLOBALS['TL_LANG']['ERR']['memberPictureFeedResizeError'] = 'Beim Versuch die Bilddatei %s zur verkleinern ist es zu einem Fehler gekommen.';

// fineuploader
$GLOBALS['TL_LANG']['MSC']['memberPictureFeed_fineuploader_upload'] = 'Hier klicken oder Dateien zum Hochladen hierhin ziehen.';

// override fineuploader language strings
$GLOBALS['TL_LANG']['MPF']['fineuploader.upload'] = 'Bilder auswählen';
