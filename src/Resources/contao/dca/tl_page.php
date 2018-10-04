<?php

// Manipulate palettes
Contao\CoreBundle\DataContainer\PaletteManipulator::create()
    ->addField(array('isPageContainer'), 'expert_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_PREPEND)
    ->applyToPalette('default', 'tl_page')
    ->applyToPalette('regular', 'tl_page')
    ->applyToPalette('forward', 'tl_page')
    ->applyToPalette('redirect', 'tl_page');

// Fields
$GLOBALS['TL_DCA']['tl_page']['fields']['isPageContainer'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_page']['isPageContainer'],
    'exclude'   => true,
    'search'    => true,
    'sorting'   => true,
    'filter'    => true,
    'inputType' => 'checkbox',
    'eval'      => array('mandatory' => false, 'tl_class' => 'clr'),
    'sql'       => "varchar(1) NOT NULL default ''",
);