<?php

declare(strict_types=1);

/*
 * This file is part of Member Picture Feed.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/member-picture-feed
 */

namespace Markocupic\MemberPictureFeed\Controller\FrontendModule;

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\File\Metadata;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Image\ImageFactory;
use Contao\CoreBundle\Image\Studio\Studio;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Date;
use Contao\Dbafs;
use Contao\File;
use Contao\Files;
use Contao\FilesModel;
use Contao\Folder;
use Contao\Frontend;
use Contao\FrontendUser;
use Contao\Message;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\Template;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Haste\Form\Form;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Twig\Environment as EnvironmentTwig;

#[AsFrontendModule(MemberPictureFeedUploadController::TYPE, category:'member_picture_feed', template:'mod_memberPictureFeedUpload')]
class MemberPictureFeedUploadController extends AbstractFrontendModuleController
{
    public const TYPE = 'memberPictureFeedUpload';

    private const FLASH_MESSAGE_KEY = 'mod_member_picture_feed_upload';

    // Adapters
    private Adapter $config;
    private Adapter $controller;
    private Adapter $date;
    private Adapter $dbafs;
    private Adapter $files;
    private Adapter $filesModel;
    private Adapter $frontend;
    private Adapter $message;
    private Adapter $stringUtil;
    private Adapter $validator;

    private PageModel|null $page;
    private FrontendUser|null $user;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly ContaoCsrfTokenManager $contaoCsrfTokenManager,
        private readonly EnvironmentTwig $twig,
        private readonly ImageFactory $contaoImageFactory,
        private readonly Security $security,
        private readonly Studio $studio,
        private readonly string $csrfTokenName,
        private readonly string $projectDir,
        private readonly string $validExtensions,
        private readonly LoggerInterface|null $logger,
    ) {
        // Load adapters
        $this->config = $this->framework->getAdapter(Config::class);
        $this->controller = $this->framework->getAdapter(Controller::class);
        $this->date = $this->framework->getAdapter(Date::class);
        $this->dbafs = $this->framework->getAdapter(Dbafs::class);
        $this->files = $this->framework->getAdapter(Files::class);
        $this->filesModel = $this->framework->getAdapter(FilesModel::class);
        $this->frontend = $this->framework->getAdapter(Frontend::class);
        $this->message = $this->framework->getAdapter(Message::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
        $this->validator = $this->framework->getAdapter(Validator::class);
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        $this->page = $page;

        $user = $this->security->getUser();

        if ($user instanceof FrontendUser) {
            $this->user = $user;
        } else {
            return new Response('', Response::HTTP_NO_CONTENT);
        }
        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * @throws \Exception
     */
    public function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicmemberpicturefeed/js/fineuploader.js|async';

        $this->overrideLangStrings();

        if ($this->countUserImages() < $model->memberPictureFeedUploadPictureLimit || $model->memberPictureFeedUploadPictureLimit < 1) {
            $template->form = $this->generateUploadForm($model, $request);
        } else {
            $this->message->addInfo($GLOBALS['TL_LANG']['MPFU']['memberPictureUploadLimitReached']);
        }

        $template->hasGallery = false;

        $arrPictures = [];

        $stmt = $this->connection->executeQuery('SELECT * FROM tl_files WHERE isMemberPictureFeed = ? AND memberPictureFeedUserId = ? ORDER BY memberPictureFeedUploadTime DESC', ['1', $this->user->id]);

        while (false !== ($rowFile = $stmt->fetchAssociative())) {
            $arrPicture = [];

            $oFile = $this->filesModel->findByUuid($rowFile['uuid']);

            if (!is_file($this->projectDir.'/'.$oFile->path)) {
                continue;
            }

            $arrSize = $this->stringUtil->deserialize($model->imgSize, true);

            $arrMeta = $this->frontend->getMetaData($oFile->meta, $this->page->language);

            if (empty($arrMeta) && null !== $this->page->rootFallbackLanguage) {
                $arrMeta = $this->frontend->getMetaData($oFile->meta, $this->page->rootFallbackLanguage);
            }

            // Create figure
            $figure = $this->studio->createFigureBuilder()
                ->fromUuid($rowFile['uuid'])
                ->setSize($arrSize)
                ->setMetadata(new Metadata($arrMeta))
                ->buildIfResourceExists()
                ;

            if ($figure) {
                $template->hasGallery = true;

                $arrPicture['picture'] = $this->twig->render('@ContaoCore/Image/Studio/figure.html.twig', ['figure' => $figure]);
                $arrPicture['data'] = $rowFile;
                $arrPicture['data']['memberPictureFeedUploadTimeFormatted'] = $this->date->parse($this->config->get('dateFormat'), $rowFile['memberPictureFeedUploadTime']);

                $arrPictures[] = $arrPicture;
            }
        }

        $template->pictures = $arrPictures;

        $template->requestToken = $this->contaoCsrfTokenManager->getToken($this->csrfTokenName)->getValue();

        $template->page = $this->page->row();

        $template->messages = '';

        // Get flash bag messages
        if ($this->message->hasMessages()) {
            $template->messages = $this->message->generateUnwrapped();
        }

        return $template->getResponse();
    }

    protected function overrideLangStrings(): void
    {
        $GLOBALS['TL_LANG']['MSC']['fineuploader.upload'] = $GLOBALS['TL_LANG']['MPFU']['fineuploader.upload'];
    }

    /**
     * @throws Exception
     */
    protected function countUserImages(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(id) AS numRows FROM tl_files WHERE isMemberPictureFeed = ? AND memberPictureFeedUserId = ?', ['2', $this->user->id]);
    }

    /**
     * @throws \Exception
     */
    protected function generateUploadForm(ModuleModel $model, Request $request): string
    {
        $objUploadFolder = null;

        if ('' !== $model->memberPictureFeedUploadFolder) {
            if ($this->validator->isBinaryUuid($model->memberPictureFeedUploadFolder)) {
                $objFilesModel = $this->filesModel->findByUuid($model->memberPictureFeedUploadFolder);

                if (null !== $objFilesModel) {
                    $objUploadFolder = new Folder($objFilesModel->path);
                }
            }
        }

        if (null === $objUploadFolder) {
            throw new \Exception('Image upload directory not set or not found. Please check your module configuration.');
        }

        $objForm = new Form('form-member-picture-feed-upload', 'POST', static fn ($objHaste) => $request->request->get('FORM_SUBMIT') === $objHaste->getFormId());

        $objForm->setFormActionFromUri($request->getUri());

        // Add some fields
        $objForm->addFormField('fileupload', [
            'label' => $GLOBALS['TL_LANG']['MPFU']['memberPictureFeedFileuploadLabel'],
            'inputType' => 'fineUploader',
            'eval' => [
                'extensions' => 'jpg,jpeg',
                'multiple' => true,
                'storeFile' => true,
                'addToDbafs' => true,
                'isGallery' => false,
                'directUpload' => false,
                'useHomeDir' => false,
                'uploadFolder' => $objUploadFolder->path,
                'mandatory' => true,
            ],
        ]);

        // Let's add  a submit button
        $objForm->addFormField('submit', [
            'label' => $GLOBALS['TL_LANG']['MPFU']['save'],
            'inputType' => 'submit',
        ]);

        $arrValidExtensions = $this->stringUtil->trimSplit(',', $this->validExtensions);
        $strValidExtensions = implode(', ', array_map(static fn ($ext) => '.'.$ext, $arrValidExtensions));

        // Add attributes
        $objWidgetFileupload = $objForm->getWidget('fileupload');
        $objWidgetFileupload->addAttribute('accept', $strValidExtensions);
        $objWidgetFileupload->storeFile = true;

        if ($request->request->get('FORM_SUBMIT') === $objForm->getFormId()) {
            // validate() also checks whether the form has been submitted
            if ($objForm->validate()) {
                if ($request->request->has('fileupload')) {
                    $arrFiles = explode(',', $request->request->get('fileupload'));

                    if (!empty($arrFiles)) {
                        $index = 0;

                        foreach ($arrFiles as $file) {
                            $blnAllow = true;

                            $objFile = new File($objUploadFolder->path.'/'.basename($file));

                            if ($objFile->isImage) {
                                if (null !== ($filesModel = $objFile->getModel())) {
                                    ++$index;
                                    // Check if upload limit has been reached
                                    if ($this->countUserImages() >= $model->memberPictureFeedUploadPictureLimit && $model->memberPictureFeedUploadPictureLimit > 0) {
                                        // Do not store uploads if upload limit has been reached.
                                        $blnAllow = false;
                                        $this->message->addError($GLOBALS['TL_LANG']['MPFU']['memberPictureUploadLimitReachedDuringUploadProcess']);
                                    } elseif (!\in_array($filesModel->extension, $arrValidExtensions, true)) {
                                        // Do not store files with an invalid/not allowed extension
                                        $blnAllow = false;
                                        $this->message->addError(sprintf($GLOBALS['TL_LANG']['MPFU']['invalidExtensionErr'], $filesModel->name, $strValidExtensions));
                                    } else {
                                        $uploadTime = time() + $index;

                                        $file = new File($filesModel->path);

                                        // Rename file
                                        $newFilename = sprintf('%s-%s.%s', $uploadTime, $this->user->id, $file->extension);
                                        $newPath = \dirname($filesModel->path).'/'.$newFilename;
                                        $this->files->getInstance()->rename($file->path, $newPath);

                                        $this->dbafs->addResource($newPath);
                                        $this->dbafs->deleteResource($filesModel->path);
                                        $this->dbafs->updateFolderHashes($objUploadFolder->path);

                                        $filesModel = $this->filesModel->findByPath($newPath);
                                        $filesModel->isMemberPictureFeed = true;
                                        $filesModel->memberPictureFeedUserId = $this->user->id;
                                        $filesModel->memberPictureFeedUploadTime = $uploadTime;
                                        $filesModel->tstamp = $uploadTime;

                                        $filesModel->save();

                                        // Try to resize the image
                                        if ($this->resizeUploadedImage($filesModel->path)) {
                                            $this->message->addInfo(sprintf($GLOBALS['TL_LANG']['MPFU']['fileUploadedAndResized'], $file->name));
                                        } else {
                                            $this->message->addInfo(sprintf($GLOBALS['TL_LANG']['MPFU']['fileUploaded'], $file->name));
                                        }

                                        // Log
                                        if (null !== $this->logger) {
                                            $strText = sprintf('User with username %s has uploadad a new picture ("%s") for the member-picture-feed.', $this->user->username, $filesModel->path);
                                            $this->logger->info($strText, ['contao' => new ContaoContext(__METHOD__, 'MEMBER PICTURE FEED')]);
                                        }
                                    }

                                    if (!$blnAllow) {
                                        $file = new File($filesModel->path);
                                        $this->dbafs->deleteResource($filesModel->path);
                                        $file->delete();
                                        $this->dbafs->updateFolderHashes($objUploadFolder->path);
                                    }
                                }
                            }
                        }
                    }
                }

                if (!$objWidgetFileupload->hasErrors()) {
                    // Reload page
                    $this->controller->reload();
                }
            }
        }

        return $objForm->generate();
    }

    /**
     * Resize the uploaded image if necessary.
     *
     * @throws \Exception
     */
    private function resizeUploadedImage(string $imgPath): bool
    {
        // Return false if the resize feature is disabled
        if ($this->config->get('maxImageWidth') < 1) {
            return false;
        }

        $objFile = new File($imgPath);

        // Return false if the file is not an image.
        if (!$objFile->isSvgImage && !$objFile->isGdImage) {
            return false;
        }

        $arrImageSize = $objFile->imageSize;

        // The image is too big to be handled by the GD library
        if ($objFile->isGdImage && ($arrImageSize[0] > $this->config->get('gdMaxImgWidth') || $arrImageSize[1] > $this->config->get('gdMaxImgHeight'))) {
            // Log
            if (null !== $this->logger) {
                $strText = 'File "'.$imgPath.'" is too big to be resized automatically';
                $this->logger->info($strText, ['contao' => new ContaoContext(__METHOD__, TL_FILES)]);
            }

            // Set flash bag message
            $this->message->addInfo(sprintf($GLOBALS['TL_LANG']['MSC']['fileExceeds'], $objFile->basename));

            return false;
        }

        $blnResize = false;

        // The image exceeds the maximum image width
        if ($arrImageSize[0] > $this->config->get('maxImageWidth')) {
            $blnResize = true;
            $intWidth = $this->config->get('maxImageWidth');
            $intHeight = round($this->config->get('maxImageWidth') * $arrImageSize[1] / $arrImageSize[0]);
            $arrImageSize = [$intWidth, $intHeight];
        }

        // The image exceeds the maximum image height
        if ($arrImageSize[1] > $this->config->get('maxImageWidth')) {
            $blnResize = true;
            $intWidth = round($this->config->get('maxImageWidth') * $arrImageSize[0] / $arrImageSize[1]);
            $intHeight = $this->config->get('maxImageWidth');
            $arrImageSize = [$intWidth, $intHeight];
        }

        // Resized successfully
        if ($blnResize) {
            $this->contaoImageFactory->create($this->projectDir.'/'.$imgPath, [$arrImageSize[0], $arrImageSize[1]], $this->projectDir.'/'.$imgPath);

            // Add flash message
            $this->message->addInfo(sprintf($GLOBALS['TL_LANG']['MSC']['fileResized'], $objFile->basename));

            return true;
        }

        return false;
    }
}
