<?php
/**
 * Created by PhpStorm.
 * User: Lenovo
 * Date: 04.10.2018
 * Time: 11:56
 */

namespace Markocupic\MemberPictureFeedBundle\Contao\Modules;

use Contao\Database;
use Contao\Dbafs;
use Contao\FilesModel;
use Contao\Folder;
use Contao\File;
use Contao\Module;
use Contao\BackendTemplate;
use Contao\FrontendUser;
use Contao\StringUtil;
use Contao\Validator;
use Patchwork\Utf8;
use Haste\Form\Form;
use Contao\Input;
use Contao\Environment;


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

        if (Environment::get('isAjaxRequest') && Input::post('xhr') == 'true')
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
            $arrJson = array('status' => $blnSuccess);
            echo \GuzzleHttp\json_encode($arrJson);
            exit();


        }
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
        }else{
            $this->Template->message = $GLOBALS['TL_LANG']['MSC']['memberPictureUploadLimitReached'];
        }

        $objPictures = Database::getInstance()->prepare('SELECT * FROM tl_files WHERE isMemberPictureFeed=? AND memberPictureFeedUserId=?')->execute('1', $this->objUser->id);
        if ($objPictures->numRows > 0)
        {
            $this->Template->hasPictures = true;
            $this->Template->pictures = $objPictures;
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
