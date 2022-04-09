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

namespace Markocupic\MemberPictureFeed\Controller;

use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Dbafs;
use Contao\Environment;
use Contao\File;
use Contao\FilesModel;
use Contao\Frontend;
use Contao\FrontendUser;
use Contao\Input;
use Contao\PageModel;
use Markocupic\MemberPictureFeed\Contao\Classes\MemberPictureFeed;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class AjaxController.
 */
class AjaxController extends AbstractController
{
    private ContaoFramework $framework;

    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    /**
     * Handles ajax requests.
     *
     * @Route("/_member_picture_feed_xhr/remove_image", name="member_picture_feed_xhr_remove_image", defaults={"_scope" = "frontend", "_token_check" = true})
     */
    public function removeImageAction(): void
    {
        $this->framework->initialize(true);

        // Do allow only xhr requests
        if (false === Environment::get('isAjaxRequest')) {
            throw new NotFoundHttpException('The route "/_member_picture_feed_xhr" is allowed to xhr requests only.');
        }

        // Ajax request: action=removeImage
        if ('' !== Input::post('fileId')) {
            $blnSuccess = 'error';

            if (null !== ($objUser = FrontendUser::getInstance())) {
                $objFile = FilesModel::findByPk(Input::post('fileId'));

                if (null !== $objFile) {
                    if ($objFile->memberPictureFeedUserId === $objUser->id) {
                        $oFile = new File($objFile->path);

                        if (is_file(TL_ROOT.'/'.$objFile->path)) {
                            $res = $objFile->path;
                            $oFile->delete();
                            Dbafs::deleteResource($res);
                            Dbafs::updateFolderHashes(\dirname($res));
                            $blnSuccess = 'success';
                        }
                    }
                }
                $arrJson = ['status' => $blnSuccess];
            }

            throw new ResponseException(new JsonResponse($arrJson));
        }

        throw new ResponseException(new JsonResponse(['status' => 'error']));
    }

    /**
     * Handles ajax requests.
     *
     * @Route("/_member_picture_feed_xhr/rotate_image", name="member_picture_feed_xhr_rotate_image", defaults={"_scope" = "frontend", "_token_check" = true})
     */
    public function rotateImageAction(): void
    {
        $this->container->get('contao.framework')->initialize();

        // Do allow only xhr requests
        if (false === Environment::get('isAjaxRequest')) {
            throw new NotFoundHttpException('The route "/_member_picture_feed_xhr" is allowed to xhr requests only.');
        }

        // Ajax request: action=rotateImage
        if ('' !== Input::post('fileId')) {
            $blnSuccess = 'error';

            if (null !== ($objUser = FrontendUser::getInstance())) {
                $objFile = FilesModel::findByPk(Input::post('fileId'));

                if (null !== $objFile) {
                    if ($objFile->memberPictureFeedUserId === $objUser->id) {
                        MemberPictureFeed::rotateImage($objFile->id);
                        $blnSuccess = 'success';
                    }
                }
                $arrJson = ['status' => $blnSuccess];
            }

            throw new ResponseException(new JsonResponse($arrJson));
        }

        throw new ResponseException(new JsonResponse(['status' => 'error']));
    }

    /**
     * Handles ajax requests.
     *
     * @Route("/_member_picture_feed_xhr/get_caption", name="member_picture_feed_xhr_get_caption", defaults={"_scope" = "frontend", "_token_check" = true})
     */
    public function getCaptionAction(): void
    {
        $this->container->get('contao.framework')->initialize();

        // Do allow only xhr requests
        if (false === Environment::get('isAjaxRequest')) {
            throw new NotFoundHttpException('The route "/_member_picture_feed_xhr" is allowed to xhr requests only.');
        }

        // Ajax request: action=getCaption
        if (Input::post('pageLanguage') && '' !== Input::post('fileId')) {
            $pageLang = Input::post('pageLanguage');

            if (null !== ($objUser = FrontendUser::getInstance())) {
                if ('' !== $pageLang) {
                    $objFile = FilesModel::findByPk(Input::post('fileId'));

                    if (null !== $objFile) {
                        if ($objFile->memberPictureFeedUserId === $objUser->id) {
                            if (null !== ($objPage = PageModel::findByPk(Input::post('pageId')))) {
                                // get meta data
                                $arrMeta = Frontend::getMetaData($objFile->meta, $pageLang);

                                if (empty($arrMeta) && null !== $objPage->rootFallbackLanguage) {
                                    $arrMeta = Frontend::getMetaData($objFile->meta, $pageLang);
                                }

                                if (!isset($arrMeta['caption'])) {
                                    $caption = '';
                                } else {
                                    $caption = $arrMeta['caption'];
                                }

                                if (!isset($arrMeta['photographer'])) {
                                    $photographer = $objUser->firstname.' '.$objUser->lastname;
                                } else {
                                    $photographer = $arrMeta['photographer'];

                                    if ('' === $photographer) {
                                        $photographer = $objUser->firstname.' '.$objUser->lastname;
                                    }
                                }
                                $arrJson = [
                                    'status' => 'success',
                                    'caption' => html_entity_decode($caption),
                                    'photographer' => $photographer,
                                ];

                                throw new ResponseException(new JsonResponse($arrJson));
                            }
                        }
                    }
                }
            }
        }

        throw new ResponseException(new JsonResponse(['status' => 'error']));
    }

    /**
     * Handles ajax requests.
     *
     * @Route("/_member_picture_feed_xhr/set_caption", name="member_picture_feed_xhr_set_caption", defaults={"_scope" = "frontend", "_token_check" = true})
     */
    public function setCaptionAction(): void
    {
        $this->container->get('contao.framework')->initialize();

        // Do allow only xhr requests
        if (false === Environment::get('isAjaxRequest')) {
            throw new NotFoundHttpException('The route "/_member_picture_feed_xhr" is allowed to xhr requests only.');
        }

        // Ajax request: action=setCaption
        if (Input::post('pageLanguage') && '' !== Input::post('fileId')) {
            $objUser = FrontendUser::getInstance();

            if (null === $objUser) {
                throw new ResponseException(new JsonResponse(['status' => 'error']));
            }

            $objFile = FilesModel::findByPk(Input::post('fileId'));

            if (null !== $objFile) {
                if ($objFile->memberPictureFeedUserId === $objUser->id) {
                    // get meta data
                    $pageLang = Input::post('pageLanguage');

                    if ('' !== $pageLang) {
                        if (!isset($arrMeta[$pageLang])) {
                            $arrMeta[$pageLang] = [
                                'title' => '',
                                'alt' => '',
                                'link' => '',
                                'caption' => '',
                                'photographer' => '',
                            ];
                        }
                        $arrMeta[$pageLang]['caption'] = Input::post('caption');
                        $arrMeta[$pageLang]['photographer'] = Input::post('photographer') ?: $objUser->firstname.' '.$objUser->lastname;

                        $objFile->meta = serialize($arrMeta);
                        $objFile->save();

                        throw new ResponseException(new JsonResponse(['status' => 'success']));
                    }
                }
            }
        }

        throw new ResponseException(new JsonResponse(['status' => 'error']));
    }
}
