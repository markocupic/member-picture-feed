<?php
/**
 * Contao module: Member Picture Feed Bundle
 * Copyright (c) 2008-2018 Marko Cupic
 * @package member-picture-feed-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2018
 * @link https://github.com/markocupic/member-picture-feed
 */

$GLOBALS['TL_DCA']['tl_member']['config']['ondelete_callback'][] = array('Markocupic\MemberPictureFeedBundle\Contao\Classes\MemberPictureFeed', 'memberOndeleteCallback');
