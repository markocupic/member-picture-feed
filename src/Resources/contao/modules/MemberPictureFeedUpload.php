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

namespace Markocupic\MemberPictureFeedBundle\Contao\Modules;

use Contao\BackendTemplate;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Database;
use Contao\Dbafs;
use Contao\Environment;
use Contao\File;
use Contao\Files;
use Contao\FilesModel;
use Contao\Folder;
use Contao\Frontend;
use Contao\FrontendUser;
use Contao\Input;
use Contao\Module;
use Contao\StringUtil;
use Contao\System;
use Contao\Validator;
use Haste\Form\Form;
use Psr\Log\LogLevel;

/**
 * Class MemberPictureFeedUpload.
 */
class MemberPictureFeedUpload extends Module
{
    public const TYPE = 'memberPictureFeedUpload';

    /**
     * Template.
     *
     * @var string
     */
    protected $strTemplate = 'mod_memberPictureFeedUpload';

    /**
     * @var
     */
    protected $objUser;

    /**
     * @var
     */
    protected $hasResized;

    /**
     * @var array
     */
    protected $arrMessages = [];

    /**
     * @var string
     */
    protected $flashMessageKey = 'mod_member_picture_feed_upload';

    /**
     * Display a wildcard in the back end.
     *
     * @return string
     */
    public function generate()
    {
        if (TL_MODE === 'BE') {
            /** @var BackendTemplate|object $objTemplate */
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### '.$GLOBALS['TL_LANG']['FMD']['memberPictureFeedUpload'][0].' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id='.$this->id;

            return $objTemplate->parse();
        }

        if (FE_USER_LOGGED_IN) {
            $this->objUser = FrontendUser::getInstance();
        } else {
            return '';
        }

        return parent::generate();
    }

    /**
     * Resize an uploaded image if necessary.
     */
    public function resizeUploadedImage(string $strImage): bool
    {
        // The feature is disabled
        if (Config::get('maxImageWidth') < 1) {
            return false;
        }

        $objFile = new File($strImage);

        // Not an image
        if (!$objFile->isSvgImage && !$objFile->isGdImage) {
            return false;
        }
        $arrImageSize = $objFile->imageSize;

        // The image is too big to be handled by the GD library
        if ($objFile->isGdImage && ($arrImageSize[0] > Config::get('gdMaxImgWidth') || $arrImageSize[1] > Config::get('gdMaxImgHeight'))) {
            // Log
            $strText = 'File "'.$strImage.'" is too big to be resized automatically';
            $logger = System::getContainer()->get('monolog.logger.contao');
            $logger->log(LogLevel::INFO, $strText, ['contao' => new ContaoContext(__METHOD__, TL_FILES)]);

            // Set flash bag message
            $this->setFlashMessage($this->flashMessageKey, sprintf($GLOBALS['TL_LANG']['MSC']['fileExceeds'], $objFile->basename));

            return false;
        }

        $blnResize = false;

        // The image exceeds the maximum image width
        if ($arrImageSize[0] > Config::get('maxImageWidth')) {
            $blnResize = true;
            $intWidth = Config::get('maxImageWidth');
            $intHeight = round(Config::get('maxImageWidth') * $arrImageSize[1] / $arrImageSize[0]);
            $arrImageSize = [$intWidth, $intHeight];
        }

        // The image exceeds the maximum image height
        if ($arrImageSize[1] > Config::get('maxImageWidth')) {
            $blnResize = true;
            $intWidth = round(Config::get('maxImageWidth') * $arrImageSize[0] / $arrImageSize[1]);
            $intHeight = Config::get('maxImageWidth');
            $arrImageSize = [$intWidth, $intHeight];
        }

        // Resized successfully
        if ($blnResize) {
            System::getContainer()
                ->get('contao.image.image_factory')
                ->create(TL_ROOT.'/'.$strImage, [$arrImageSize[0], $arrImageSize[1]], TL_ROOT.'/'.$strImage)
            ;

            $this->blnHasResized = true;

            // Set flash bag message
            $this->setFlashMessage($this->flashMessageKey, sprintf($GLOBALS['TL_LANG']['MSC']['fileResized'], $objFile->basename));

            return true;
        }

        return false;
    }

    /**
     * Generate the module.
     */
    protected function compile(): void
    {
        $this->overrideLangStrings();

        $session = System::getContainer()->get('session');

        // Get flash bag messages
        if ($session->isStarted() && $this->hasFlashMessage($this->flashMessageKey)) {
            $this->arrMessages = array_merge($this->arrMessages, $this->getFlashMessage($this->flashMessageKey));
            $this->unsetFlashMessage($this->flashMessageKey);
        }

        if ($this->countUserImages() < $this->memberPictureFeedUploadPictureLimit || $this->memberPictureFeedUploadPictureLimit < 1) {
            $this->generateUploadForm();
        } else {
            $this->arrMessages[] = $GLOBALS['TL_LANG']['MSC']['memberPictureUploadLimitReached'];
        }

        $objPictures = Database::getInstance()->prepare('SELECT * FROM tl_files WHERE isMemberPictureFeed=? AND memberPictureFeedUserId=? ORDER BY memberPictureFeedUploadTime DESC')->execute('1', $this->objUser->id);

        if ($objPictures->numRows > 0) {
            $this->Template->hasPictures = true;
            $this->Template->pictures = $objPictures;
        }

        // Closure for image html
        $this->Template->getImageHtml = (
            function ($fileId) {
                $arrSize = StringUtil::deserialize($this->imgSize, true);
                $oFile = FilesModel::findByPk($fileId);

                if (null !== $oFile) {
                    // get meta data
                    global $objPage;
                    $arrMeta = Frontend::getMetaData($oFile->meta, $objPage->language);

                    if (empty($arrMeta) && null !== $objPage->rootFallbackLanguage) {
                        $arrMeta = Frontend::getMetaData($oFile->meta, $objPage->rootFallbackLanguage);
                    }

                    if ('' === $arrSize[0] && '' === $arrSize[1] && $arrSize[2] > 0) {
                        $strTag = sprintf('{{picture::%s?size=%s&rel=lightboxlb%s&alt=%s}}', $fileId, $arrSize[2], $this->id, $arrMeta['caption'] ?? '');
                    } elseif (('' !== $arrSize[0] || '' !== $arrSize[1]) && '' !== $arrSize[2]) {
                        $strTag = sprintf('{{image::%s?width=%s&height=%s&mode=%s&rel=lightboxlb%s&alt=%s}}', $fileId, $arrSize[0], $arrSize[1], $arrSize[2], $this->id, $arrMeta['caption'] ?? '');
                    } else {
                        $strTag = sprintf('{{image::%s?width=400&height=400&mode=crop&rel=lightboxlb%s&alt=%s}}', $fileId, $this->id, $arrMeta['caption'] ?? '');
                    }

                    return Controller::replaceInsertTags($strTag);
                }
            }
        );

        if (!empty($this->arrMessages)) {
            $this->Template->hasMessages = true;
            $this->Template->arrMessages = $this->arrMessages;
        }
    }

    protected function overrideLangStrings(): void
    {
        $GLOBALS['TL_LANG']['MSC']['fineuploader.upload'] = $GLOBALS['TL_LANG']['MPF']['fineuploader.upload'];
    }

    /**
     * @param $key
     */
    protected function hasFlashMessage(string $key): bool
    {
        if (isset($_SESSION[$key]) && !empty($_SESSION[$key])) {
            return true;
        }

        return false;
    }

    /**
     * @param $key
     *
     * @return mixed
     */
    protected function getFlashMessage(string $key): array
    {
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }

        return $_SESSION[$key];
    }

    /**
     * @param $key
     */
    protected function unsetFlashMessage($key): void
    {
        if (isset($_SESSION[$key])) {
            $_SESSION[$key] = null;
            unset($_SESSION[$key]);
        }
    }

    protected function countUserImages(): int
    {
        $objPicturesCount = Database::getInstance()->prepare('SELECT * FROM tl_files WHERE isMemberPictureFeed=? AND memberPictureFeedUserId=?')->execute('1', $this->objUser->id);

        return $objPicturesCount->numRows;
    }

    protected function generateUploadForm(): void
    {
        if ('' !== $this->memberPictureFeedUploadFolder) {
            if (Validator::isBinaryUuid($this->memberPictureFeedUploadFolder)) {
                $objFilesModel = FilesModel::findByUuid($this->memberPictureFeedUploadFolder);

                if (null !== $objFilesModel) {
                    $objUploadFolder = new Folder($objFilesModel->path);
                }
            }
        }

        if (null === $objUploadFolder) {
            return;
        }

        $objForm = new Form('form-member-picture-feed-upload', 'POST', static fn ($objHaste) => Input::post('FORM_SUBMIT') === $objHaste->getFormId());

        $url = Environment::get('uri');
        $objForm->setFormActionFromUri($url);

        // Add some fields
        $objForm->addFormField('fileupload', [
            'label' => $GLOBALS['TL_LANG']['MSC']['memberPictureFeedFileuploadLabel'],
            'inputType' => 'fineUploader',
            'eval' => [
                'extensions' => 'jpg,jpeg',
                'multiple' => true,
                'storeFile' => true,
                'addToDbafs' => true,
                'isGallery' => false,
                'directUpload' => false,
                'useHomeDir' => false,
                'uploadFolder' => $objUploadFolder->path,
                'mandatory' => true,
            ],
        ]);

        // Let's add  a submit button
        $objForm->addFormField('submit', [
            'label' => $GLOBALS['TL_LANG']['MSC']['memberPictureFeedUploadBtnlLabel'],
            'inputType' => 'submit',
        ]);

        // Add attributes
        $objWidgetFileupload = $objForm->getWidget('fileupload');
        $objWidgetFileupload->addAttribute('accept', '.jpg, .jpeg');
        $objWidgetFileupload->storeFile = true;

        // validate() also checks whether the form has been submitted
        if ($objForm->validate() && Input::post('FORM_SUBMIT') === $objForm->getFormId()) {
            if (!empty($_SESSION['FILES']) && \is_array($_SESSION['FILES'])) {
                foreach ($_SESSION['FILES'] as $file) {
                    $uuid = $file['uuid'];

                    if (Validator::isStringUuid($uuid)) {
                        $binUuid = StringUtil::uuidToBin($uuid);
                        $objModel = FilesModel::findByUuid($binUuid);

                        if (null !== $objModel) {
                            $objFile = new File($objModel->path);

                            //Check if upload limit is reached
                            if ($this->countUserImages() >= $this->memberPictureFeedUploadPictureLimit && $this->memberPictureFeedUploadPictureLimit > 0) {
                                Dbafs::deleteResource($objModel->path);
                                $objFile->delete();
                                Dbafs::updateFolderHashes($objUploadFolder->path);
                                $objWidgetFileupload->addError($GLOBALS['TL_LANG']['MSC']['memberPictureUploadLimitReachedDuringUploadProcess']);
                            } else {
                                // Rename file
                                $newFilename = sprintf('%s-%s.%s', time(), $this->objUser->id, $objFile->extension);
                                $newPath = \dirname($objModel->path).'/'.$newFilename;
                                Files::getInstance()->rename($objFile->path, $newPath);
                                Dbafs::addResource($newPath);
                                Dbafs::deleteResource($objModel->path);
                                Dbafs::updateFolderHashes($objUploadFolder->path);

                                $objModel = FilesModel::findByPath($newPath);
                                $objModel->isMemberPictureFeed = true;
                                $objModel->memberPictureFeedUserId = $this->objUser->id;
                                $objModel->tstamp = time();
                                $objModel->memberPictureFeedUploadTime = time();
                                $objModel->save();
                                $this->resizeUploadedImage($objModel->path);

                                // Flash message
                                $this->setFlashMessage($this->flashMessageKey, sprintf($GLOBALS['TL_LANG']['MSC']['memberPictureFeedFileuploadSuccess'], $objModel->name));

                                // Log
                                $strText = sprintf('User with username %s has uploadad a new picture ("%s") for the member-picture-feed.', $this->objUser->username, $objModel->path);
                                $logger = System::getContainer()->get('monolog.logger.contao');
                                $logger->log(LogLevel::INFO, $strText, ['contao' => new ContaoContext(__METHOD__, 'MEMBER PICTURE FEED')]);
                            }
                        }
                    }
                }
            }

            if (!$objWidgetFileupload->hasErrors()) {
                // Reload page
                $this->reload();
            }
        }

        unset($_SESSION['FILES']);

        $this->Template->objUploadForm = $objForm->generate();
    }

    /**
     * @param $key
     */
    protected function setFlashMessage(string $key, string $message = ''): void
    {
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }
        $_SESSION[$key][] = $message;
    }
}
