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

namespace Markocupic\FormFileuploadDropzone\Classes;

use Contao\Environment;
use function GuzzleHttp\json_encode;

/**
 * Class ValidateFormFieldHook.
 */
class ValidateFormFieldDropzoneHook
{
    /**
     * @param $objWidget
     * @param $formId
     * @param $arrData
     * @param $objForm
     *
     * @return mixed
     */
    public function validateFormFieldDropzoneHook($objWidget)
    {
        mail('m.cupic@gmx.ch', $objWidget->type, print_r($objWidget, true));

        if ('uploadDropzone' === $objWidget->type) {
            if (Environment::get('isAjaxRequest') && $objWidget->hasErrors()) {
                // Send response for Dropzone
                $json = [
                    'status' => 'error',
                    'errorMsg' => $objWidget->getErrorsAsString(' '),
                ];
                echo json_encode($json);
                exit;
            }
        }

        return $objWidget;
    }
}
