<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 22.09.2018
 * Time: 10:56
 */

$GLOBALS['TL_HOOKS']['parseTemplate'][] =  array('Markocupic\NavPageContainer\Contao\Classes\ParseTemplate', 'parseTemplateHook');