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
    protected $objUploadForm;

    /**
     * @var
     */
    protected $hasResized;

    /**
     * @var array
     */
    protected $arrMessages = array();


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

        // Handle ajax requests
        if ((!is_array($_FILES) || empty($_FILES)) && Environment::get('isAjaxRequest'))
        {
            $this->handleAjaxRequest();
            exit();
        }

        return parent::generate();
    }


    /**
     * Generate the module
     */
    protected function compile()
    {

        $session = System::getContainer()->get('session');
        $flashBag = $session->getFlashBag();

        // Get flash bag messages
        if ($session->isStarted() && $flashBag->has('mod_member_picture_feed_upload'))
        {
            $this->arrMessages = array_merge($this->arrMessages, $flashBag->get('mod_member_picture_feed_upload'));
        }

        if ($this->countUserImages() < $this->memberPictureFeedUploadPictureLimit || $this->memberPictureFeedUploadPictureLimit < 1)
        {
            $this->generateUploadForm();
        }
        else
        {
            $this->arrMessages[] = $GLOBALS['TL_LANG']['MSC']['memberPictureUploadLimitReached'];
        }

        $objPictures = Database::getInstance()->prepare('SELECT * FROM tl_files WHERE isMemberPictureFeed=? AND memberPictureFeedUserId=? ORDER BY name')->execute('1', $this->objUser->id);
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
            'inputType' => 'fineUploaderMemberPictureFeed',
            'eval'      => array('extensions' => 'jpg,jpeg', 'storeFile' => true, 'addToDbafs' => true, 'isGallery' => false, 'directUpload' => false, 'multiple' => true, 'useHomeDir' => false, 'uploadFolder' => $objUploadFolder->path, 'mandatory' => false),
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
                                $objFile->delete();
                                $objWidgetFileupload->addError($GLOBALS['TL_LANG']['MSC']['memberPictureUploadLimitReachedDuringUploadProcess']);
                            }
                            else
                            {
                                // Rename file
                                $newFilename = sprintf('%s-%s.%s', $this->objUser->id, time(), $objFile->extension);
                                $newPath = dirname($objModel->path) . '/' . $newFilename;
                                Files::getInstance()->rename($objFile->path, $newPath);
                                Dbafs::addResource($newPath);

                                $objModel = FilesModel::findByPath($newPath);
                                $objModel->isMemberPictureFeed = true;
                                $objModel->memberPictureFeedUserId = $this->objUser->id;
                                $objModel->tstamp = time();
                                $objModel->save();
                                $this->resizeUploadedImage($objModel->path);

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
     * @return $this
     */
    protected function handleAjaxRequest()
    {
        // Ajax request: action=removeImage
        if (Input::post('action') === 'removeImage' && Input::post('fileId') != '')
        {
            $blnSuccess = 'error';
            $objFile = FilesModel::findByPk(Input::post('fileId'));
            if ($objFile !== null)
            {
                if ($objFile->memberPictureFeedUserId === $this->objUser->id)
                {
                    $oFile = new File($objFile->path);
                    if (is_file(TL_ROOT . '/' . $objFile->path))
                    {
                        $res = $objFile->path;
                        $oFile->delete();
                        Dbafs::deleteResource($res);
                        Dbafs::updateFolderHashes(dirname($res));
                        $blnSuccess = 'success';
                    }
                }
            }
            $arrJson = array('status' => $blnSuccess);
            echo \GuzzleHttp\json_encode($arrJson);
            exit();
        }

        // Ajax request: action=rotateImage
        if (Input::post('action') === 'rotateImage' && Input::post('fileId') != '')
        {
            $blnSuccess = 'error';
            $objFile = FilesModel::findByPk(Input::post('fileId'));
            if ($objFile !== null)
            {
                if ($objFile->memberPictureFeedUserId === $this->objUser->id)
                {
                    $this->rotateImage($objFile->id);
                    $blnSuccess = 'success';
                }
            }
            $arrJson = array('status' => $blnSuccess);
            echo \GuzzleHttp\json_encode($arrJson);
            exit();
        }


        // Ajax request: action=getCaption
        if (Input::post('action') === 'getCaption' && Input::post('fileId') != '')
        {

            $objFile = FilesModel::findByPk(Input::post('fileId'));
            if ($objFile !== null)
            {
                if ($objFile->memberPictureFeedUserId === $this->objUser->id)
                {

                    // get meta data
                    global $objPage;
                    $arrMeta = Frontend::getMetaData($objFile->meta, $objPage->language);
                    if (empty($arrMeta) && $objPage->rootFallbackLanguage !== null)
                    {
                        $arrMeta = Frontend::getMetaData($objFile->meta, $objPage->rootFallbackLanguage);
                    }

                    if (!isset($arrMeta['caption']))
                    {
                        $caption = '';
                    }
                    else
                    {
                        $caption = $arrMeta['caption'];
                    }

                    if (!isset($arrMeta['photographer']))
                    {
                        $photographer = $this->objUser->firstname . ' ' . $this->objUser->lastname;
                    }
                    else
                    {
                        $photographer = $arrMeta['photographer'];
                        if ($photographer === '')
                        {
                            $photographer = $this->objUser->firstname . ' ' . $this->objUser->lastname;
                        }
                    }
                    $response = array(
                        'status'       => 'success',
                        'caption'      => html_entity_decode($caption),
                        'photographer' => $photographer,
                    );
                    echo \GuzzleHttp\json_encode($response);
                    exit();
                }
            }
            echo \GuzzleHttp\json_encode(array('status' => 'error'));
            exit();
        }

        // Ajax request: action=setCaption
        if (Input::post('action') === 'setCaption' && Input::post('fileId') != '')
        {
            $objUser = FrontendUser::getInstance();
            if ($objUser === null)
            {
                echo \GuzzleHttp\json_encode(array('status' => 'error'));
                exit;
            }

            $objFile = FilesModel::findByPk(Input::post('fileId'));
            if ($objFile !== null)
            {
                if ($objFile->memberPictureFeedUserId === $this->objUser->id)
                {
                    // get meta data
                    global $objPage;
                    if (!isset($arrMeta[$objPage->language]))
                    {
                        $arrMeta[$objPage->language] = array(
                            'title'        => '',
                            'alt'          => '',
                            'link'         => '',
                            'caption'      => '',
                            'photographer' => '',
                        );
                    }
                    $arrMeta[$objPage->language]['caption'] = Input::post('caption');
                    $arrMeta[$objPage->language]['photographer'] = Input::post('photographer') ?: $objUser->firstname . ' ' . $objUser->lastname;

                    $objFile->meta = serialize($arrMeta);
                    $objFile->save();
                    echo \GuzzleHttp\json_encode(array('status' => 'success'));
                    exit;
                }
            }
        }
        echo \GuzzleHttp\json_encode(array('status' => 'error'));
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
            $session = System::getContainer()->get('session');
            $flashBag = $session->getFlashBag();
            $flashBag->set('mod_member_picture_feed_upload', sprintf($GLOBALS['TL_LANG']['MSC']['fileExceeds'], $objFile->basename));
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
            $session = System::getContainer()->get('session');
            $flashBag = $session->getFlashBag();
            $flashBag->set('mod_member_picture_feed_upload', sprintf($GLOBALS['TL_LANG']['MSC']['fileResized'], $objFile->basename));
            return true;
        }

        return false;
    }

    /**
     * Rotate an image clockwise by 90°
     * @param $id
     * @return bool
     * @throws \Exception
     */
    protected function rotateImage($id)
    {
        $angle = 90;

        $objFiles = FilesModel::findById($id);
        if ($objFiles === null)
        {
            return false;
        }

        $src = $objFiles->path;

        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        if (!file_exists($rootDir . '/' . $src))
        {
            Message::addError(sprintf('File "%s" not found.', $src));
            return false;
        }

        $objFile = new File($src);
        if (!$objFile->isGdImage)
        {
            Message::addError(sprintf('File "%s" could not be rotated because it is not an image.', $src));
            return false;
        }

        if (!function_exists('imagerotate'))
        {
            Message::addError(sprintf('PHP function "%s" is not installed.', 'imagerotate'));
            return false;
        }

        $source = imagecreatefromjpeg($rootDir . '/' . $src);

        //rotate
        $imgTmp = imagerotate($source, $angle, 0);

        // Output
        imagejpeg($imgTmp, $rootDir . '/' . $src);

        imagedestroy($source);
        return true;
    }

}
