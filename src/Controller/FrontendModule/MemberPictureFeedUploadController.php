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

namespace Markocupic\MemberPictureFeed\Controller\FrontendModule;

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\File\Metadata;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Image\ImageFactory;
use Contao\CoreBundle\Image\Studio\Studio;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\Date;
use Contao\Dbafs;
use Contao\File;
use Contao\Files;
use Contao\FilesModel;
use Contao\Folder;
use Contao\Frontend;
use Contao\FrontendUser;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\Template;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Haste\Form\Form;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Twig\Environment as EnvironmentTwig;

/**
 * @FrontendModule(MemberPictureFeedUploadController::TYPE, category="member_picture_feed")
 */
class MemberPictureFeedUploadController extends AbstractFrontendModuleController
{
    public const TYPE = 'memberPictureFeedUpload';

    private const FLASH_MESSAGE_KEY = 'mod_member_picture_feed_upload';

    private ContaoFramework $framework;
    private Connection $connection;
    private Security $security;
    private InsertTagParser $insertTagParser;
    private Studio $studio;
    private ImageFactory $contaoImageFactory;
    private EnvironmentTwig $twig;
    private ContaoCsrfTokenManager $contaoCsrfTokenManager;
    private string $csrfTokenName;
    private string $projectDir;
    private ?LoggerInterface $logger;

    // Adapters
    private Adapter $config;
    private Adapter $controller;
    private Adapter $date;
    private Adapter $dbafs;
    private Adapter $files;
    private Adapter $filesModel;
    private Adapter $frontend;
    private Adapter $stringUtil;
    private Adapter $validator;

    private ?PageModel $page;
    private ?FrontendUser $user;

    private array $messages = [];

    public function __construct(ContaoFramework $framework, Connection $connection, Security $security, InsertTagParser $insertTagParser, Studio $studio, ImageFactory $contaoImageFactory, EnvironmentTwig $twig, ContaoCsrfTokenManager $contaoCsrfTokenManager, string $csrfTokenName, string $projectDir, LoggerInterface $logger = null)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->security = $security;
        $this->insertTagParser = $insertTagParser;
        $this->studio = $studio;
        $this->contaoImageFactory = $contaoImageFactory;
        $this->twig = $twig;
        $this->contaoCsrfTokenManager = $contaoCsrfTokenManager;
        $this->csrfTokenName = $csrfTokenName;
        $this->projectDir = $projectDir;
        $this->logger = $logger;

        // Load adapters
        $this->config = $this->framework->getAdapter(Config::class);
        $this->controller = $this->framework->getAdapter(Controller::class);
        $this->date = $this->framework->getAdapter(Date::class);
        $this->dbafs = $this->framework->getAdapter(Dbafs::class);
        $this->files = $this->framework->getAdapter(Files::class);
        $this->filesModel = $this->framework->getAdapter(FilesModel::class);
        $this->frontend = $this->framework->getAdapter(Frontend::class);
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

        // Get flash bag messages
        if ($this->hasFlashMessage(self::FLASH_MESSAGE_KEY)) {
            $this->messages = array_merge($this->messages, $this->getFlashMessage(self::FLASH_MESSAGE_KEY));
            $this->unsetFlashMessage(self::FLASH_MESSAGE_KEY);
        }

        if ($this->countUserImages() < $model->memberPictureFeedUploadPictureLimit || $model->memberPictureFeedUploadPictureLimit < 1) {
            $template->form = $this->generateUploadForm($model, $request);
        } else {
            $this->messages[] = $GLOBALS['TL_LANG']['MSC']['memberPictureUploadLimitReached'];
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

        $template->hasMessages = false;

        if (!empty($this->messages)) {
            $template->hasMessages = true;
            $template->messages = $this->messages;
        }

        return $template->getResponse();
    }

    protected function overrideLangStrings(): void
    {
        $GLOBALS['TL_LANG']['MSC']['fineuploader.upload'] = $GLOBALS['TL_LANG']['MPF']['fineuploader.upload'];
    }

    protected function hasFlashMessage(string $key): bool
    {
        if (isset($_SESSION[$key]) && !empty($_SESSION[$key])) {
            return true;
        }

        return false;
    }

    protected function getFlashMessage(string $key): array
    {
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }

        return $_SESSION[$key];
    }

    /**
     * @param $key
     */
    protected function unsetFlashMessage($key): void
    {
        if (isset($_SESSION[$key])) {
            $_SESSION[$key] = null;
            unset($_SESSION[$key]);
        }
    }

    /**
     * @return int
     * @throws \Doctrine\DBAL\Exception
     */
    protected function countUserImages(): int
    {
        return $this->connection->fetchOne('SELECT COUNT(id) AS numRows FROM tl_files WHERE isMemberPictureFeed = ? AND memberPictureFeedUserId = ?', ['1', $this->user->id]);
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
            'label' => $GLOBALS['TL_LANG']['MSC']['memberPictureFeedFileuploadLabel'],
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
            'label' => $GLOBALS['TL_LANG']['MSC']['memberPictureFeedUploadBtnlLabel'],
            'inputType' => 'submit',
        ]);

        // Add attributes
        $objWidgetFileupload = $objForm->getWidget('fileupload');
        $objWidgetFileupload->addAttribute('accept', '.jpg, .jpeg');
        $objWidgetFileupload->storeFile = true;

        // validate() also checks whether the form has been submitted
        if ($objForm->validate() && $request->request->get('FORM_SUBMIT') === $objForm->getFormId()) {
            if (!empty($_SESSION['FILES']) && \is_array($_SESSION['FILES'])) {
                $index = 0;

                foreach ($_SESSION['FILES'] as $file) {
                    ++$index;
                    $uuid = $file['uuid'];

                    if ($this->validator->isStringUuid($uuid)) {
                        $binUuid = $this->stringUtil->uuidToBin($uuid);
                        $objModel = $this->filesModel->findByUuid($binUuid);

                        if (null !== $objModel) {
                            $objFile = new File($objModel->path);

                            // Check if upload limit is reached
                            if ($this->countUserImages() >= $model->memberPictureFeedUploadPictureLimit && $model->memberPictureFeedUploadPictureLimit > 0) {
                                $this->dbafs->deleteResource($objModel->path);
                                $objFile->delete();
                                $this->dbafs->updateFolderHashes($objUploadFolder->path);
                                $objWidgetFileupload->addError($GLOBALS['TL_LANG']['MSC']['memberPictureUploadLimitReachedDuringUploadProcess']);
                            } else {
                                // Rename file
                                $time = time() + $index;
                                $newFilename = sprintf('%s-%s.%s', $time, $this->user->id, $objFile->extension);
                                $newPath = \dirname($objModel->path).'/'.$newFilename;
                                $this->files->getInstance()->rename($objFile->path, $newPath);
                                $this->dbafs->addResource($newPath);
                                $this->dbafs->deleteResource($objModel->path);
                                $this->dbafs->updateFolderHashes($objUploadFolder->path);

                                $objModel = $this->filesModel->findByPath($newPath);
                                $objModel->isMemberPictureFeed = true;
                                $objModel->memberPictureFeedUserId = $this->user->id;
                                $objModel->tstamp = $time;
                                $objModel->memberPictureFeedUploadTime = $time;
                                $objModel->save();

                                // Try to resize image
                                if ($this->resizeUploadedImage($objModel->path)) {
                                    $this->setFlashMessage(self::FLASH_MESSAGE_KEY, sprintf($GLOBALS['TL_LANG']['ERR']['fileUploadedAndResized'], $objModel->name));
                                } else {
                                    $this->setFlashMessage(self::FLASH_MESSAGE_KEY, sprintf($GLOBALS['TL_LANG']['MSC']['fileUploaded'], $objModel->name));
                                }

                                // Log
                                if (null !== $this->logger) {
                                    $strText = sprintf('User with username %s has uploadad a new picture ("%s") for the member-picture-feed.', $this->user->username, $objModel->path);
                                    $this->logger->info($strText, ['contao' => new ContaoContext(__METHOD__, 'MEMBER PICTURE FEED')]);
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

        unset($_SESSION['FILES']);

        return $objForm->generate();
    }

    protected function setFlashMessage(string $key, string $message = ''): void
    {
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }
        $_SESSION[$key][] = $message;
    }

    /**
     * Resize an uploaded image if necessary.
     *
     * @throws \Exception
     */
    private function resizeUploadedImage(string $strImage): bool
    {
        // The feature is disabled
        if ($this->config->get('maxImageWidth') < 1) {
            return false;
        }

        $objFile = new File($strImage);

        // Not an image
        if (!$objFile->isSvgImage && !$objFile->isGdImage) {
            return false;
        }

        $arrImageSize = $objFile->imageSize;

        // The image is too big to be handled by the GD library
        if ($objFile->isGdImage && ($arrImageSize[0] > $this->config->get('gdMaxImgWidth') || $arrImageSize[1] > $this->config->get('gdMaxImgHeight'))) {
            // Log
            if (null !== $this->logger) {
                $strText = 'File "'.$strImage.'" is too big to be resized automatically';
                $this->logger->info($strText, ['contao' => new ContaoContext(__METHOD__, TL_FILES)]);
            }

            // Set flash bag message
            $this->setFlashMessage(self::FLASH_MESSAGE_KEY, sprintf($GLOBALS['TL_LANG']['MSC']['fileExceeds'], $objFile->basename));

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
            $this->contaoImageFactory->create($this->projectDir.'/'.$strImage, [$arrImageSize[0], $arrImageSize[1]], $this->projectDir.'/'.$strImage);

            // Set flash bag message
            $this->setFlashMessage(self::FLASH_MESSAGE_KEY, sprintf($GLOBALS['TL_LANG']['MSC']['fileResized'], $objFile->basename));

            return true;
        }

        return false;
    }
}
