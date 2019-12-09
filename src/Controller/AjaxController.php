<?php

/**
 * Contao module: Member Picture Feed Bundle
 * Copyright (c) 2008-2018 Marko Cupic
 * @package member-picture-feed-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2018
 * @link https://github.com/markocupic/member-picture-feed
 */

namespace Markocupic\MemberPictureFeedBundle\Controller;

use Contao\Dbafs;
use Contao\Environment;
use Contao\File;
use Contao\FilesModel;
use Contao\Frontend;
use Contao\FrontendUser;
use Contao\Input;
use Contao\PageModel;
use Markocupic\MemberPictureFeedBundle\Contao\Classes\MemberPictureFeed;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class AjaxController
 * @package Markocupic\MemberPictureFeedBundle\Controller
 */
class AjaxController extends AbstractController
{

    /**
     * Handles ajax requests.
     * @Route("/_member_picture_feed_xhr/remove_image", name="member_picture_feed_xhr_remove_image", defaults={"_scope" = "frontend", "_token_check" = true})
     */
    public function removeImageAction()
    {
        $this->container->get('contao.framework')->initialize();

        // Do allow only xhr requests
        if (Environment::get('isAjaxRequest') === false)
        {
            throw new NotFoundHttpException('The route "/_member_picture_feed_xhr" is allowed to xhr requests only.');
        }

        // Ajax request: action=removeImage
        if (Input::post('fileId') != '')
        {
            $blnSuccess = 'error';
            if (null !== ($objUser = FrontendUser::getInstance()))
            {
                $objFile = FilesModel::findByPk(Input::post('fileId'));
                if ($objFile !== null)
                {
                    if ($objFile->memberPictureFeedUserId === $objUser->id)
                    {
                        $oFile = new File($objFile->path);
                        if (is_file(TL_ROOT . '/' . $objFile->path))
                        {
                            $res = $objFile->path;
                            $oFile->delete();
                            Dbafs::deleteResource($res);
                            Dbafs::updateFolderHashes(dirname($res));
                            $blnSuccess = 'success';
                        }
                    }
                }
                $arrJson = array('status' => $blnSuccess);
            }
            echo \GuzzleHttp\json_encode($arrJson);
            exit();
        }

        echo \GuzzleHttp\json_encode(array('status' => 'error'));
        exit();
    }

    /**
     * Handles ajax requests.
     * @Route("/_member_picture_feed_xhr/rotate_image", name="member_picture_feed_xhr_rotate_image", defaults={"_scope" = "frontend", "_token_check" = true})
     */
    public function rotateImageAction()
    {
        $this->container->get('contao.framework')->initialize();

        // Do allow only xhr requests
        if (Environment::get('isAjaxRequest') === false)
        {
            throw new NotFoundHttpException('The route "/_member_picture_feed_xhr" is allowed to xhr requests only.');
        }

        // Ajax request: action=rotateImage
        if (Input::post('fileId') != '')
        {
            $blnSuccess = 'error';
            if (null !== ($objUser = FrontendUser::getInstance()))
            {
                $objFile = FilesModel::findByPk(Input::post('fileId'));
                if ($objFile !== null)
                {
                    if ($objFile->memberPictureFeedUserId === $objUser->id)
                    {
                        MemberPictureFeed::rotateImage($objFile->id);
                        $blnSuccess = 'success';
                    }
                }
                $arrJson = array('status' => $blnSuccess);
            }
            echo \GuzzleHttp\json_encode($arrJson);
            exit();
        }

        echo \GuzzleHttp\json_encode(array('status' => 'error'));
        exit();
    }

    /**
     * Handles ajax requests.
     * @Route("/_member_picture_feed_xhr/get_caption", name="member_picture_feed_xhr_get_caption", defaults={"_scope" = "frontend", "_token_check" = true})
     */
    public function getCaptionAction()
    {
        $this->container->get('contao.framework')->initialize();

        // Do allow only xhr requests
        if (Environment::get('isAjaxRequest') === false)
        {
            throw new NotFoundHttpException('The route "/_member_picture_feed_xhr" is allowed to xhr requests only.');
        }

        // Ajax request: action=getCaption
        if (Input::post('pageLanguage') && Input::post('fileId') != '')
        {
            $pageLang = Input::post('pageLanguage');
            if (null !== ($objUser = FrontendUser::getInstance()))
            {
                if ($pageLang != '')
                {
                    $objFile = FilesModel::findByPk(Input::post('fileId'));
                    if ($objFile !== null)
                    {
                        if ($objFile->memberPictureFeedUserId === $objUser->id)
                        {
                            if (null !== ($objPage = PageModel::findByPk(Input::post('pageId'))))
                            {
                                // get meta data
                                $arrMeta = Frontend::getMetaData($objFile->meta, $pageLang);
                                if (empty($arrMeta) && $objPage->rootFallbackLanguage !== null)
                                {
                                    $arrMeta = Frontend::getMetaData($objFile->meta, $pageLang);
                                }

                                if (!isset($arrMeta['caption']))
                                {
                                    $caption = '';
                                }
                                else
                                {
                                    $caption = $arrMeta['caption'];
                                }

                                if (!isset($arrMeta['photographer']))
                                {
                                    $photographer = $objUser->firstname . ' ' . $objUser->lastname;
                                }
                                else
                                {
                                    $photographer = $arrMeta['photographer'];
                                    if ($photographer === '')
                                    {
                                        $photographer = $objUser->firstname . ' ' . $objUser->lastname;
                                    }
                                }
                                $response = array(
                                    'status'       => 'success',
                                    'caption'      => html_entity_decode($caption),
                                    'photographer' => $photographer,
                                );
                                echo \GuzzleHttp\json_encode($response);
                                exit();
                            }
                        }
                    }
                }
            }
            echo \GuzzleHttp\json_encode(array('status' => 'error'));
            exit();
        }

        echo \GuzzleHttp\json_encode(array('status' => 'error'));
        exit();
    }

    /**
     * Handles ajax requests.
     * @Route("/_member_picture_feed_xhr/set_caption", name="member_picture_feed_xhr_set_caption", defaults={"_scope" = "frontend", "_token_check" = true})
     */
    public function setCaptionAction()
    {
        $this->container->get('contao.framework')->initialize();

        // Do allow only xhr requests
        if (Environment::get('isAjaxRequest') === false)
        {
            throw new NotFoundHttpException('The route "/_member_picture_feed_xhr" is allowed to xhr requests only.');
        }

        // Ajax request: action=setCaption
        if (Input::post('pageLanguage') && Input::post('fileId') != '')
        {
            $objUser = FrontendUser::getInstance();
            if ($objUser === null)
            {
                echo \GuzzleHttp\json_encode(array('status' => 'error'));
                exit;
            }

            $objFile = FilesModel::findByPk(Input::post('fileId'));
            if ($objFile !== null)
            {
                if ($objFile->memberPictureFeedUserId === $objUser->id)
                {
                    // get meta data
                    $pageLang = Input::post('pageLanguage');
                    if ($pageLang != '')
                    {
                        if (!isset($arrMeta[$pageLang]))
                        {
                            $arrMeta[$pageLang] = array(
                                'title'        => '',
                                'alt'          => '',
                                'link'         => '',
                                'caption'      => '',
                                'photographer' => '',
                            );
                        }
                        $arrMeta[$pageLang]['caption'] = Input::post('caption');
                        $arrMeta[$pageLang]['photographer'] = Input::post('photographer') ?: $objUser->firstname . ' ' . $objUser->lastname;

                        $objFile->meta = serialize($arrMeta);
                        $objFile->save();
                        echo \GuzzleHttp\json_encode(array('status' => 'success'));
                        exit;
                    }
                }
            }
        }
        echo \GuzzleHttp\json_encode(array('status' => 'error'));
        exit();
    }
}
