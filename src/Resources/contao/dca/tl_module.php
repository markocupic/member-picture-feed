<?php
// Palette
$GLOBALS['TL_DCA']['tl_module']['palettes']['memberPictureFeedUpload'] = '{title_legend},name,headline,type;{upload_folder_settings},memberPictureFeedUploadFolder,memberPictureFeedUploadPictureLimit;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';


// Fields
$GLOBALS['TL_DCA']['tl_module']['fields']['memberPictureFeedUploadFolder'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['memberPictureFeedUploadFolder'],
    'exclude'   => true,
    'inputType' => 'fileTree',
    'eval'      => array('fieldType' => 'radio', 'filesOnly' => false, 'mandatory' => true, 'tl_class' => 'clr'),
    'sql'       => "binary(16) NULL"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['memberPictureFeedUploadPictureLimit'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['memberPictureFeedUploadPictureLimit'],
    'exclude'   => true,
    'default'   => 3,
    'inputType' => 'text',
    'eval'      => array('mandatory' => false, 'rgxp' => 'natural', 'tl_class' => 'w50'),
    'sql'       => "smallint(5) unsigned NOT NULL default '0'"
);
 