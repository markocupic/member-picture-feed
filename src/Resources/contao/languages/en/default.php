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

// File upload form
$GLOBALS['TL_LANG']['MPFU']['memberPictureUploadLimitReached'] = 'You have already reached the upload maximum. To upload more images, you must first delete at least one of your images.';
$GLOBALS['TL_LANG']['MPFU']['memberPictureUploadLimitReachedDuringUploadProcess'] = 'The upload limit has been reached. Not all photos could be uploaded.';
$GLOBALS['TL_LANG']['MPFU']['save'] = 'Save changes';
$GLOBALS['TL_LANG']['MPFU']['memberPictureFeedFileuploadLabel'] = 'Select photos';
$GLOBALS['TL_LANG']['MPFU']['fileUploaded'] = 'Image file %s has been successfully uploaded.';
$GLOBALS['TL_LANG']['MPFU']['fileUploadedAndResized'] = 'Image file %s was successfully uploaded and scaled down to the maximum dimensions.';
$GLOBALS['TL_LANG']['MPFU']['invalidExtensionErr'] = 'The file "%s" has an illegal file extension and was therefore not uploaded. Allowed file extensions are: %s.';
$GLOBALS['TL_LANG']['MPFU']['deleteImage'] = 'Delete photo';
$GLOBALS['TL_LANG']['MPFU']['addCaption'] = 'Add a caption';
$GLOBALS['TL_LANG']['MPFU']['rotateImage'] = 'Rotate image';
$GLOBALS['TL_LANG']['MPFU']['editCaptionAndPhotographer'] = 'Edit caption and photographer\'s name';
$GLOBALS['TL_LANG']['MPFU']['close'] = 'Close';
$GLOBALS['TL_LANG']['MPFU']['caption'] = 'Caption';
$GLOBALS['TL_LANG']['MPFU']['addCaption'] = 'Add caption';
$GLOBALS['TL_LANG']['MPFU']['photographer'] = 'Photographer';
$GLOBALS['TL_LANG']['MPFU']['addPhotographer'] = 'Add photographer\'s name';

// Override fineuploader language strings
$GLOBALS['TL_LANG']['MPFU']['fineuploader.upload'] = 'Select photos';
