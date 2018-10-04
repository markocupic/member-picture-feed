<?php
// Palette
$GLOBALS['TL_DCA']['tl_module']['palettes']['memberPictureFeedUpload'] = '{title_legend},name,headline,type;{upload_folder_settings},memberPictureFeedUploadFolder;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';


// Fields
$GLOBALS['TL_DCA']['tl_module']['fields']['memberPictureFeedUploadFolder'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['memberPictureFeedUploadFolder'],
    'exclude'   => true,
    'inputType' => 'fileTree',
    'eval'      => array('fieldType' => 'radio', 'filesOnly' => false, 'mandatory' => true, 'tl_class' => 'clr'),
    'sql'       => "binary(16) NULL"
);
 