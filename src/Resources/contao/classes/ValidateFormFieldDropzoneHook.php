<?php

namespace Markocupic\FormFileuploadDropzone\Classes;

use Contao\Environment;

/**
 * Class ValidateFormFieldHook
 * @package Markocupic\FormFileuploadDropzone\Classes
 */
class ValidateFormFieldDropzoneHook
{
    /**
     * @param $objWidget
     * @param $formId
     * @param $arrData
     * @param $objForm
     * @return mixed
     */
    public function validateFormFieldDropzoneHook($objWidget)
    {
        mail('m.cupic@gmx.ch', $objWidget->type, print_r($objWidget,true));
        if ($objWidget->type === 'uploadDropzone')
        {
            if (Environment::get('isAjaxRequest') && $objWidget->hasErrors())
            {
                // Send response for Dropzone
                $json = array(
                    'status'   => 'error',
                    'errorMsg' => $objWidget->getErrorsAsString(' ')
                );
                echo \GuzzleHttp\json_encode($json);
                exit;
            }
        }


        return $objWidget;
    }
}