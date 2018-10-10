<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao;

/**
 * Class FormFileUploadDropzone
 *
 * @property boolean $mandatory
 * @property integer $maxlength
 * @property integer $fSize
 * @property string  $extensions
 * @property string  $uploadFolder
 * @property boolean $doNotOverwrite
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class FormFileUploadDropzone extends \FormFileUpload
{

    /**
     * Template
     *
     * @var string
     */
    protected $strTemplate = 'form_upload_dropzone';

    /**
     * The CSS class prefix
     *
     * @var string
     */
    protected $strPrefix = 'widget widget-upload-dropzone';

}
