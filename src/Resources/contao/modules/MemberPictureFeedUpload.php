<?php
/**
 * Created by PhpStorm.
 * User: Lenovo
 * Date: 04.10.2018
 * Time: 11:56
 */

namespace Markocupic\MemberPictureFeedBundle\Contao\Modules;

use Contao\Controller;
use Contao\Database;
use Contao\Dbafs;
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
use Symfony\Component\HttpFoundation\JsonResponse;


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
        //$this->handleAjaxRequest();

        if (Environment::get('isAjaxRequest'))
        {
            $this->handleAjaxRequest();
            return '';
        }


        return parent::generate();
    }

    protected function handleAjaxRequest()
    {
        if (Input::post('action') === 'removeImage')
        {
            $blnSuccess = 'false';
            $objFiles = FilesModel::findByPk(Input::post('fileId'));
            if ($objFiles !== null)
            {
                if ($objFiles->memberPictureFeedUserId === $this->objUser->id)
                {
                    $oFile = new File($objFiles->path);
                    if (is_file(TL_ROOT . '/' . $objFiles->path))
                    {
                        $res = $objFiles->path;
                        $oFile->delete();
                        Dbafs::deleteResource($res);
                        Dbafs::updateFolderHashes(dirname($res));
                        $blnSuccess = 'true';
                    }
                }
            }
            $arrJson = array('status' => $blnSuccess);
            echo \GuzzleHttp\json_encode($arrJson);
            exit();
        }


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


        if (Input::post('action') === 'setCaption' && Input::post('fileId') != '')
        {
            $objUser = FrontendUser::getInstance();
            if ($objUser === null)
            {
                $response = new JsonResponse(array('status' => 'error'));
                return $response->send();
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
                    $response = new JsonResponse(array(
                        'status' => 'success',
                    ));
                    return $response->send();
                }
            }
        }
        $response = new JsonResponse(array('status' => 'error'));
        return $response->send();
    }


    /**
     * Generate the module
     */
    protected function compile()
    {
 
        $objPicturesCount = Database::getInstance()->prepare('SELECT * FROM tl_files WHERE isMemberPictureFeed=? AND memberPictureFeedUserId=?')->execute('1', $this->objUser->id);
        if ($objPicturesCount->numRows < $this->memberPictureFeedUploadPictureLimit || $this->memberPictureFeedUploadPictureLimit < 1)
        {
            $this->generateUploadForm();
        }
        else
        {
            $this->Template->message = $GLOBALS['TL_LANG']['MSC']['memberPictureUploadLimitReached'];
        }

        $objPictures = Database::getInstance()->prepare('SELECT * FROM tl_files WHERE isMemberPictureFeed=? AND memberPictureFeedUserId=?')->execute('1', $this->objUser->id);
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


        $objForm->addFormField('fileupload', array(
            'label'     => $GLOBALS['TL_LANG']['MSC']['activateMemberAccount_sacMemberId'],
            'inputType' => 'upload',
            'eval'      => array('extensions' => 'jpg,jpeg', 'uploadFolder' => $objUploadFolder->uuid, 'mandatory' => true),
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => $GLOBALS['TL_LANG']['MSC']['activateMemberAccount_startActivationProcess'],
            'inputType' => 'submit',
        ));

        // Automatically add the FORM_SUBMIT and REQUEST_TOKEN hidden fields.
        // DO NOT use this method with generate() as the "form" template provides those fields by default.
        $objForm->addContaoHiddenFields();


        $objWidget = $objForm->getWidget('fileupload');
        $objWidget->addAttribute('accept', '.jpg, .jpeg');
        //die(print_r($objWidget,true));
        $objWidget->uploadFolder = $this->memberPictureFeedUploadFolder;
        $objWidget->storeFile = true;

        if (!empty($_FILES['fileupload']))
        {
            $objFile = new File($_FILES['fileupload']['name']);
            $_FILES['fileupload']['name'] = sprintf('%s-%s.%s', $this->objUser->id, time(), $objFile->extension);

        }


        // validate() also checks whether the form has been submitted
        if ($objForm->validate())
        {
            if (!empty($_SESSION['FILES']['fileupload']) && is_array($_SESSION['FILES']['fileupload']))
            {
                $uuid = $_SESSION['FILES']['fileupload']['uuid'];
                if (Validator::isStringUuid($uuid))
                {
                    $binUuid = StringUtil::uuidToBin($uuid);
                    $objModel = FilesModel::findByUuid($binUuid);

                    // Save to tl_files
                    if ($objModel !== null)
                    {
                        $objModel->isMemberPictureFeed = true;
                        $objModel->memberPictureFeedUserId = $this->objUser->id;
                        $objModel->save();
                        $this->reload();
                    }
                }
            }


        }

        $this->Template->objUploadForm = $objForm->generate();


    }


}
