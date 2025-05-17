<?php

declare(strict_types=1);

/*
 * This file is part of Member Picture Feed.
 *
 * (c) Marko Cupic 2025 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/member-picture-feed
 */

namespace Markocupic\MemberPictureFeed\Controller;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Dbafs;
use Contao\File;
use Contao\FilesModel;
use Contao\Frontend;
use Contao\FrontendUser;
use Contao\ManagerBundle\HttpKernel\ContaoKernel;
use Contao\PageModel;
use Markocupic\MemberPictureFeed\Image\ImageRotate;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class AjaxController extends AbstractController
{
    // Adapters
    private Adapter $dbafs;
    private Adapter $filesModel;
    private Adapter $frontend;
    private Adapter $pageModel;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ContaoKernel $kernel,
        private readonly ImageRotate $imageRotate,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly string $projectDir,
    ) {
        // Adapters
        $this->dbafs = $this->framework->getAdapter(Dbafs::class);
        $this->filesModel = $this->framework->getAdapter(FilesModel::class);
        $this->frontend = $this->framework->getAdapter(Frontend::class);
        $this->pageModel = $this->framework->getAdapter(PageModel::class);
    }

    /**
     * Delete image.
     *
     * @throws \Exception
     */
    #[Route('/_member_picture_feed_xhr/remove_image', name: 'member_picture_feed_xhr_remove_image', defaults: ['_scope' => 'frontend', '_token_check' => true])]
    public function removeImageAction(): JsonResponse
    {
        $this->framework->initialize(true);

        $request = $this->requestStack->getCurrentRequest();

        // Do allow only xhr requests
        if (!$request->isXmlHttpRequest()) {
            throw new NotFoundHttpException('The route "/_member_picture_feed_xhr" is allowed to xhr requests only.');
        }

        $objUser = $this->security->getUser();

        if (!$objUser instanceof FrontendUser) {
            throw new \Exception('Not authorized. Please log in as a Contao frontend user.', Response::HTTP_UNAUTHORIZED);
        }

        // Ajax request: action=removeImage
        if ($request->request->has('fileId')) {
            $objFile = $this->filesModel->findByPk($request->request->get('fileId'));

            if (null !== $objFile) {
                if ($objFile->memberPictureFeedUserId === $objUser->id) {
                    $oFile = new File($objFile->path);

                    if (is_file($this->projectDir.'/'.$objFile->path)) {
                        $res = $objFile->path;
                        $oFile->delete();
                        $this->dbafs->deleteResource($res);
                        $this->dbafs->updateFolderHashes(\dirname($res));

                        $arrJson = [
                            'status' => 'success',
                            'message' => $this->kernel->isDebug() ? '[Member Picture Feed]: Successfully deleted image.' : null,
                        ];

                        return new JsonResponse($arrJson);
                    }
                }
            }
        }

        $arrJson = [
            'status' => 'error',
            'message' => $this->kernel->isDebug() ? '[Member Picture Feed]: Removing image failed.' : null,
        ];

        return new JsonResponse($arrJson);
    }

    /**
     * Rotate image.
     *
     * @throws \ImagickException
     * @throws \Exception
     */
    #[Route('/_member_picture_feed_xhr/rotate_image', name: 'member_picture_feed_xhr_rotate_image', defaults: ['_scope' => 'frontend', '_token_check' => true])]
    public function rotateImageAction(): JsonResponse
    {
        $this->framework->initialize();

        $request = $this->requestStack->getCurrentRequest();

        // Do allow only xhr requests
        if (!$request->isXmlHttpRequest()) {
            throw new NotFoundHttpException('The route "/_member_picture_feed_xhr" is allowed to xhr requests only.');
        }

        // Check has logged in
        $objUser = $this->security->getUser();

        if (!$objUser instanceof FrontendUser) {
            throw new \Exception('Not authorized. Please log in as a Contao frontend user.', Response::HTTP_UNAUTHORIZED);
        }

        if ($request->request->has('fileId')) {
            $objFile = $this->filesModel->findByPk($request->request->get('fileId'));

            if (null !== $objFile) {
                if ((int) $objFile->memberPictureFeedUserId === (int) $objUser->id) {
                    $path = $this->projectDir.'/'.$objFile->path;

                    if ($this->imageRotate->rotate($path)) {
                        $arrJson = [
                            'status' => 'success',
                            'message' => $this->kernel->isDebug() ? '[Member Picture Feed]: Successfully rotated image.' : null,
                        ];

                        return new JsonResponse($arrJson);
                    }
                }
            }
        }

        $arrJson = [
            'status' => 'error',
            'message' => $this->kernel->isDebug() ? '[Member Picture Feed]: Rotate image failed.' : null,
        ];

        return new JsonResponse($arrJson);
    }

    /**
     * Send caption.
     **
     * @throws \Exception
     */
    #[Route('/_member_picture_feed_xhr/get_image_data', name: 'member_picture_feed_xhr_get_caption', defaults: ['_scope' => 'frontend', '_token_check' => true])]
    public function getCaptionAction(): Response
    {
        $this->framework->initialize();

        $request = $this->requestStack->getCurrentRequest();

        // Do allow only xhr requests
        if (!$request->isXmlHttpRequest()) {
            throw new NotFoundHttpException('The route "/_member_picture_feed_xhr" is allowed to xhr requests only.');
        }

        // Check has logged in
        $objUser = $this->security->getUser();

        if (!$objUser instanceof FrontendUser) {
            throw new \Exception('Not authorized. Please log in as a Contao frontend user.', Response::HTTP_UNAUTHORIZED);
        }

        if ($request->request->has('pageLanguage') && $request->request->has('fileId')) {
            $pageLang = $request->request->get('pageLanguage');

            $objFile = $this->filesModel->findByPk($request->request->get('fileId'));

            if (null !== $objFile) {
                if ($objFile->memberPictureFeedUserId === $objUser->id) {
                    if (null !== ($objPage = $this->pageModel->findByPk($request->request->get('pageId')))) {
                        // get meta data
                        $arrMeta = $this->frontend->getMetaData($objFile->meta, $pageLang);

                        if (empty($arrMeta) && null !== $objPage->rootFallbackLanguage) {
                            $arrMeta = $this->frontend->getMetaData($objFile->meta, $pageLang);
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
                            'caption' => html_entity_decode((string) $caption),
                            'photographer' => html_entity_decode((string) $photographer),
                            'message' => $this->kernel->isDebug() ? '[Member Picture Feed]: Get image data.' : null,
                        ];

                        return new JsonResponse($arrJson);
                    }
                }
            }
        }

        $arrJson = [
            'status' => 'error',
            'message' => $this->kernel->isDebug() ? '[Member Picture Feed]: Get image data failed.' : null,
        ];

        return new JsonResponse($arrJson);
    }

    /**
     * Set caption.
     *
     * @throws \Exception
     */
    #[Route('/_member_picture_feed_xhr/set_caption', name: 'member_picture_feed_xhr_set_caption', defaults: ['_scope' => 'frontend', '_token_check' => true])]
    public function setCaptionAction(): JsonResponse
    {
        $this->framework->initialize();

        $request = $this->requestStack->getCurrentRequest();

        // Do allow only xhr requests
        if (!$request->isXmlHttpRequest()) {
            throw new NotFoundHttpException('The route "/_member_picture_feed_xhr" is allowed to xhr requests only.');
        }

        // Check has logged in
        $objUser = $this->security->getUser();

        if (!$objUser instanceof FrontendUser) {
            throw new \Exception('Not authorized. Please log in as a Contao frontend user.', Response::HTTP_UNAUTHORIZED);
        }

        if ($request->request->has('pageLanguage') && $request->request->has('fileId')) {
            $objFile = $this->filesModel->findByPk($request->request->get('fileId'));

            if (null !== $objFile) {
                if ($objFile->memberPictureFeedUserId === $objUser->id) {
                    // get meta data
                    $pageLang = $request->request->get('pageLanguage');

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
                        $arrMeta[$pageLang]['caption'] = $request->request->get('caption');
                        $arrMeta[$pageLang]['photographer'] = $request->request->get('photographer') ?: $objUser->firstname.' '.$objUser->lastname;
                        $objFile->meta = serialize($arrMeta);
                        $objFile->save();

                        $arrJson = [
                            'status' => 'success',
                            'message' => $this->kernel->isDebug() ? '[Member Picture Feed]: Successfully saved image caption and photographer name.' : null,
                        ];

                        return new JsonResponse($arrJson);
                    }
                }
            }
        }

        $arrJson = [
            'status' => 'error',
            'message' => $this->kernel->isDebug() ? '[Member Picture Feed]: Error while trying to save image caption and photographer name.' : null,
        ];

        return new JsonResponse($arrJson);
    }
}
