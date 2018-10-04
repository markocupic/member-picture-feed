<?php

// Fields
$GLOBALS['TL_DCA']['tl_files']['fields']['isMemberPictureFeed'] = array(
    'sql' => "char(1) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_files']['fields']['memberPictureFeedUserId'] = array(
    'sql' => "int(10) unsigned NOT NULL default '0'"
);
 