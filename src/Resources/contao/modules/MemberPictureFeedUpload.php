<?php
/**
 * Contao module: Member Picture Feed Bundle
 * Copyright (c) 2008-2018 Marko Cupic
 * @package member-picture-feed-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2018
 * @link https://github.com/markocupic/member-picture-feed
 */

namespace Markocupic\MemberPictureFeedBundle\Contao\Modules;

use Contao\Controller;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Database;
use Contao\Dbafs;
use Contao\Files;
use Contao\FilesModel;
use Contao\Folder;
use Contao\File;
use Contao\Frontend;
use Contao\Module;
use Contao\BackendTemplate;
use Contao\FrontendUser;
use Contao\StringUtil;
use Contao\Validator;
use Patchwork\Utf8;
use Haste\Form\Form;
use Contao\Input;
use Contao\Environment;
use Contao\System;
use Contao\Config;
use Psr\Log\LogLevel;


/**
 * Class MemberPictureFeedUpload
 * @package Markocupic\MemberPictureFeedBundle\Contao\Modules
 */
class MemberPictureFeedUpload extends Module
{

    /**
     * Template
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
    protected $arrMessages = array();

    /**
     * @var string
     */
    protected $flashMessageKey = 'mod_member_picture_feed_upload';

    /**
     * Display a wildcard in the back end
     *
     * @return string
     */
    public function generate()
    {


        if (TL_MODE == 'BE')
        {
            /** @var BackendTemplate|object $objTemplate */
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['memberPictureFeedUpload'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        if (FE_USER_LOGGED_IN)
        {
            $this->objUser = FrontendUser::getInstance();
        }
        else
        {
            return '';
        }



        return parent::generate();
    }


    /**
     * Generate the module
     */
    protected function compile()
    {


        $session = System::getContainer()->get('session');

        // Get flash bag messages
        if ($session->isStarted() && $this->hasFlashMessage($this->flashMessageKey))
        {
            $this->arrMessages = array_merge($this->arrMessages, $this->getFlashMessage($this->flashMessageKey));
            $this->unsetFlashMessage($this->flashMessageKey);
        }

        if ($this->countUserImages() < $this->memberPictureFeedUploadPictureLimit || $this->memberPictureFeedUploadPictureLimit < 1)
        {
            $this->generateUploadForm();
        }
        else
        {
            $this->arrMessages[] = $GLOBALS['TL_LANG']['MSC']['memberPictureUploadLimitReached'];
        }

        $objPictures = Database::getInstance()->prepare('SELECT * FROM tl_files WHERE isMemberPictureFeed=? AND memberPictureFeedUserId=? ORDER BY memberPictureFeedUploadTime DESC')->execute('1', $this->objUser->id);
        if ($objPictures->numRows > 0)
        {
            $this->Template->hasPictures = true;
            $this->Template->pictures = $objPictures;
        }

        // Closure for image html
        $this->Template->getImageHtml = (function ($fileId) {
            $arrSize = StringUtil::deserialize($this->imgSize, true);
            $oFile = FilesModel::findByPk($fileId);
            if ($oFile !== null)
            {
                // get meta data
                global $objPage;
                $arrMeta = Frontend::getMetaData($oFile->meta, $objPage->language);
                if (empty($arrMeta) && $objPage->rootFallbackLanguage !== null)
                {
                    $arrMeta = Frontend::getMetaData($oFile->meta, $objPage->rootFallbackLanguage);
                }

                if ($arrSize[0] == '' && $arrSize[1] == '' && $arrSize[2] > 0)
                {
                    $strTag = sprintf('{{picture::%s?size=%s&rel=lightboxlb%s&alt=%s}}', $fileId, $arrSize[2], $this->id, $arrMeta['caption']);
                }
                elseif (($arrSize[0] != '' || $arrSize[1] != '') && $arrSize[2] != '')
                {
                    $strTag = sprintf('{{image::%s?width=%s&height=%s&mode=%s&rel=lightboxlb%s&alt=%s}}', $fileId, $arrSize[0], $arrSize[1], $arrSize[2], $this->id, $arrMeta['caption']);
                }
                else
                {
                    $strTag = sprintf('{{image::%s?width=400&height=400&mode=crop&rel=lightboxlb%s&alt=%s}}', $fileId, $this->id, $arrMeta['caption']);
                }

                return Controller::replaceInsertTags($strTag);
            }
        });

        if (!empty($this->arrMessages))
        {
            $this->Template->hasMessages = true;
            $this->Template->arrMessages = $this->arrMessages;
        }
    }


    /**
     * @return null
     */
    protected function generateUploadForm()
    {

        if ($this->memberPictureFeedUploadFolder != '')
        {
            if (Validator::isBinaryUuid($this->memberPictureFeedUploadFolder))
            {
                $objFilesModel = FilesModel::findByUuid($this->memberPictureFeedUploadFolder);
                if ($objFilesModel !== null)
                {
                    $objUploadFolder = new Folder($objFilesModel->path);
                }
            }
        }

        if ($objUploadFolder === null)
        {
            return;
        }


        $objForm = new Form('form-member-picture-feed-upload', 'POST', function ($objHaste) {
            return Input::post('FORM_SUBMIT') === $objHaste->getFormId();
        });


        $url = Environment::get('uri');
        $objForm->setFormActionFromUri($url);

        // Add some fields
        $objForm->addFormField('fileupload', array(
            'label'     => $GLOBALS['TL_LANG']['MSC']['memberPictureFeedFileuploadLabel'],
            'inputType' => 'fineUploader',
            'eval'      => array('extensions'   => 'jpg,jpeg',
                                 'storeFile'    => true,
                                 'addToDbafs'   => true,
                                 'isGallery'    => false,
                                 'directUpload' => false,
                                 'multiple'     => true,
                                 'useHomeDir'   => false,
                                 'uploadFolder' => $objUploadFolder->path,
                                 'mandatory'    => true
            ),
        ));


        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => $GLOBALS['TL_LANG']['MSC']['memberPictureFeedUploadBtnlLabel'],
            'inputType' => 'submit',
        ));


        // Add attributes
        $objWidgetFileupload = $objForm->getWidget('fileupload');
        $objWidgetFileupload->addAttribute('accept', '.jpg, .jpeg');
        $objWidgetFileupload->storeFile = true;

        // Overwrite uploader template
        if ($this->memberPictureFeedUploadCustomUploaderTpl !== '')
        {
            $objWidgetFileupload->template = $this->memberPictureFeedUploadCustomUploaderTpl;
        }


        // validate() also checks whether the form has been submitted
        if ($objForm->validate() && Input::post('FORM_SUBMIT') === $objForm->getFormId())
        {
            if (is_array($_SESSION['FILES']) && !empty($_SESSION['FILES']))
            {
                foreach ($_SESSION['FILES'] as $k => $file)
                {
                    $uuid = $file['uuid'];
                    if (Validator::isStringUuid($uuid))
                    {
                        $binUuid = StringUtil::uuidToBin($uuid);
                        $objModel = FilesModel::findByUuid($binUuid);

                        if ($objModel !== null)
                        {
                            $objFile = new File($objModel->path);

                            //Check if upload limit is reached
                            if ($this->countUserImages() >= $this->memberPictureFeedUploadPictureLimit && $this->memberPictureFeedUploadPictureLimit > 0)
                            {
                                Dbafs::deleteResource($objModel->path);
                                $objFile->delete();
                                Dbafs::updateFolderHashes($objUploadFolder->path);
                                $objWidgetFileupload->addError($GLOBALS['TL_LANG']['MSC']['memberPictureUploadLimitReachedDuringUploadProcess']);
                            }
                            else
                            {
                                // Rename file
                                $newFilename = sprintf('%s-%s.%s', time(), $this->objUser->id, $objFile->extension);
                                $newPath = dirname($objModel->path) . '/' . $newFilename;
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
                                $logger->log(LogLevel::INFO, $strText, array('contao' => new ContaoContext(__METHOD__, 'MEMBER PICTURE FEED')));
                            }
                        }
                    }
                }
            }

            if (!$objWidgetFileupload->hasErrors())
            {
                // Reload page
                $this->reload();
            }
        }

        unset($_SESSION['FILES']);

        $this->Template->objUploadForm = $objForm->generate();
    }

    /**
     * @return int
     */
    protected function countUserImages()
    {
        $objPicturesCount = Database::getInstance()->prepare('SELECT * FROM tl_files WHERE isMemberPictureFeed=? AND memberPictureFeedUserId=?')->execute('1', $this->objUser->id);
        return $objPicturesCount->numRows;
    }



    /**
     * Resize an uploaded image if necessary
     *
     * @param string $strImage
     *
     * @return boolean
     */
    public function resizeUploadedImage($strImage)
    {
        // The feature is disabled
        if (Config::get('maxImageWidth') < 1)
        {
            return false;
        }

        $objFile = new File($strImage);

        // Not an image
        if (!$objFile->isSvgImage && !$objFile->isGdImage)
        {
            return false;
        }
        $arrImageSize = $objFile->imageSize;

        // The image is too big to be handled by the GD library
        if ($objFile->isGdImage && ($arrImageSize[0] > Config::get('gdMaxImgWidth') || $arrImageSize[1] > Config::get('gdMaxImgHeight')))
        {
            // Log
            $strText = 'File "' . $strImage . '" is too big to be resized automatically';
            $logger = System::getContainer()->get('monolog.logger.contao');
            $logger->log(LogLevel::INFO, $strText, array('contao' => new ContaoContext(__METHOD__, TL_FILES)));

            // Set flash bag message
            $this->setFlashMessage($this->flashMessageKey, sprintf($GLOBALS['TL_LANG']['MSC']['fileExceeds'], $objFile->basename));
            return false;
        }

        $blnResize = false;

        // The image exceeds the maximum image width
        if ($arrImageSize[0] > Config::get('maxImageWidth'))
        {
            $blnResize = true;
            $intWidth = Config::get('maxImageWidth');
            $intHeight = round(Config::get('maxImageWidth') * $arrImageSize[1] / $arrImageSize[0]);
            $arrImageSize = array($intWidth, $intHeight);
        }

        // The image exceeds the maximum image height
        if ($arrImageSize[1] > Config::get('maxImageWidth'))
        {
            $blnResize = true;
            $intWidth = round(Config::get('maxImageWidth') * $arrImageSize[0] / $arrImageSize[1]);
            $intHeight = Config::get('maxImageWidth');
            $arrImageSize = array($intWidth, $intHeight);
        }

        // Resized successfully
        if ($blnResize)
        {
            System::getContainer()
                ->get('contao.image.image_factory')
                ->create(TL_ROOT . '/' . $strImage, array($arrImageSize[0], $arrImageSize[1]), TL_ROOT . '/' . $strImage);

            $this->blnHasResized = true;

            // Set flash bag message
            $this->setFlashMessage($this->flashMessageKey, sprintf($GLOBALS['TL_LANG']['MSC']['fileResized'], $objFile->basename));
            return true;
        }

        return false;
    }


    /**
     * @param $key
     * @param string $message
     */
    protected function setFlashMessage($key, $message = '')
    {
        if (!isset($_SESSION[$key]))
        {
            $_SESSION[$key] = array();

        }
        $_SESSION[$key][] = $message;
    }

    /**
     * @param $key
     * @return mixed
     */
    protected function getFlashMessage($key)
    {
        if (!isset($_SESSION[$key]))
        {
            $_SESSION[$key] = array();

        }
        return $_SESSION[$key];
    }

    /**
     * @param $key
     */
    protected function unsetFlashMessage($key)
    {
        if (isset($_SESSION[$key]))
        {
            $_SESSION[$key] = null;
            unset($_SESSION[$key]);
        }
    }

    /**
     * @param $key
     * @return bool
     */
    protected function hasFlashMessage($key)
    {
        if (isset($_SESSION[$key]) && !empty($_SESSION[$key]))
        {
            return true;
        }
        return false;
    }

}
