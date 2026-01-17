<?php

declare(strict_types=1);

/*
 * This file is part of Member Picture Feed.
 *
 * Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/member-picture-feed
 */

namespace Markocupic\MemberPictureFeed\Controller\FrontendModule;

use Codefog\HasteBundle\Form\Form;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\File\Metadata;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Image\Studio\Studio;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Date;
use Contao\Dbafs;
use Contao\FilesModel;
use Contao\Folder;
use Contao\Frontend;
use Contao\FrontendUser;
use Contao\Message;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\MemberPictureFeed\Image\ImageResizer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment as EnvironmentTwig;

#[AsFrontendModule(MemberPictureFeedUploadController::TYPE, category: 'member_picture_feed', template: 'mod_memberPictureFeedUpload')]
class MemberPictureFeedUploadController extends AbstractFrontendModuleController
{
    public const string TYPE = 'memberPictureFeedUpload';

    // Adapters
    private Adapter $config;

    private Adapter $controller;

    private Adapter $date;

    private Adapter $dbafs;

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
        private readonly ImageResizer $imageResizer,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly Studio $studio,
        private readonly string $projectDir,
        private readonly string $validExtensions,
        private readonly LoggerInterface|null $logger,
    ) {
        // Load adapters
        $this->config = $this->framework->getAdapter(Config::class);
        $this->controller = $this->framework->getAdapter(Controller::class);
        $this->date = $this->framework->getAdapter(Date::class);
        $this->dbafs = $this->framework->getAdapter(Dbafs::class);
        $this->filesModel = $this->framework->getAdapter(FilesModel::class);
        $this->frontend = $this->framework->getAdapter(Frontend::class);
        $this->message = $this->framework->getAdapter(Message::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
        $this->validator = $this->framework->getAdapter(Validator::class);
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array|null $classes = null, PageModel|null $page = null): Response
    {
        $this->page = $page;

        $user = $this->tokenStorage->getToken()?->getUser();

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
    public function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        if ($this->countUserImages() < $model->memberPictureFeedUploadPictureLimit || $model->memberPictureFeedUploadPictureLimit < 1) {
            $template->set('form', $this->generateUploadForm($model, $request));
        } else {
            $this->message->addInfo($GLOBALS['TL_LANG']['MPFU']['memberPictureUploadLimitReached']);
        }

        $template->set('hasGallery', false);

        $arrPictures = [];

        $stmt = $this->connection->executeQuery(
            'SELECT * FROM tl_files WHERE isMemberPictureFeed = ? AND memberPictureFeedUserId = ? ORDER BY memberPictureFeedUploadTime DESC',
            [
                1,
                $this->user->id,
            ],
        );

        while (false !== ($rowFile = $stmt->fetchAssociative())) {
            $arrPicture = [];

            $oFile = $this->filesModel->findByUuid($rowFile['uuid']);

            if (!is_file($this->projectDir.'/'.$oFile->path)) {
                continue;
            }

            $arrSize = $this->stringUtil->deserialize($model->imgSize, true);

            // Meta tags
            $arrMeta = $this->frontend->getMetaData($oFile->meta, $this->page->language);

            foreach ($arrMeta as $k => $v) {
                // Output encoding
                $arrMeta[$k] = StringUtil::specialcharsAttribute($v);
            }

            if (empty($arrMeta) && null !== $this->page->rootFallbackLanguage) {
                $arrMeta = $this->frontend->getMetaData($oFile->meta, $this->page->rootFallbackLanguage);
            }

            // Create the figure
            $figure = $this->studio->createFigureBuilder()
                ->fromUuid($rowFile['uuid'])
                ->setSize($arrSize)
                ->setMetadata(new Metadata($arrMeta))
                ->buildIfResourceExists()
            ;

            if ($figure) {
                $template->set('hasGallery', true);

                $arrPicture['picture'] = $this->twig->render('@ContaoCore/Image/Studio/figure.html.twig', ['figure' => $figure]);
                $arrPicture['data'] = $rowFile;
                $arrPicture['data']['memberPictureFeedUploadTimeFormatted'] = $this->date->parse($this->config->get('dateFormat'), $rowFile['memberPictureFeedUploadTime']);
                $arrPicture['data']['hasCaption'] = !empty($arrMeta['caption']);

                $arrPictures[] = $arrPicture;
            }
        }

        $template->set('pictures', $arrPictures);

        $template->set('requestToken', $this->contaoCsrfTokenManager->getDefaultTokenValue());

        $template->set('page', $this->page->row());

        $template->set('messages', '');

        // Get flash bag messages
        if ($this->message->hasMessages()) {
            $template->set('messages', $this->message->generateUnwrapped());
        }

        return $template->getResponse();
    }

    /**
     * @throws Exception
     */
    protected function countUserImages(): int
    {
        return $this->connection->fetchOne(
            'SELECT COUNT(id) AS numRows FROM tl_files WHERE isMemberPictureFeed = ? AND memberPictureFeedUserId = ?',
            [
                1,
                $this->user->id,
            ],
        );
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

        $objForm = new Form(
            'form-member-picture-feed-upload',
            'POST',
        );

        $objForm->setAction($request->getUri());
        $tempUploadPath = 'system/tmp/'.uniqid('mpfu_');

        // Add some fields
        $objForm->addFormField('fileupload', [
            'label' => $GLOBALS['TL_LANG']['MPFU']['memberPictureFeedFileuploadLabel'],
            'inputType' => 'filepondUploader',
            'eval' => [
                'extensions' => $this->validExtensions,
                'multiple' => true,
                'storeFile' => true,
                'addToDbafs' => false,
                'directUpload' => false,
                'useHomeDir' => false,
                'uploadFolder' => $tempUploadPath,
                'mandatory' => true,
                'imgResizeWidth' => $this->config->get('imageWidth'), // Image will be resized server side to this width (pixels)
                'imgResizeHeight' => $this->config->get('imageHeight'), // Image will be resized server side to this height (pixels)
                // Client side image resizing
                'imgResize' => true, // Must be set to true or the other resize options will be ignored!
                'imgResizeBrowser' => true, // Allow client side image resizing! 'imgResize' must be set to "true" as well!
            ],
        ]);

        // Let's add a submit-button
        $objForm->addFormField('submit', [
            'label' => $GLOBALS['TL_LANG']['MPFU']['save'],
            'inputType' => 'submit',
        ]);

        if (!$request->request->get('FORM_SUBMIT') === $objForm->getFormId()) {
            return $objForm->generate();
        }

        $extensions = $this->stringUtil->trimSplit(',', $this->validExtensions);

        if ($objForm->validate() && $request->request->has('fileupload')) {
            $widget = $objForm->getWidget('fileupload');

            $fs = new Filesystem();

            // Configure the finder
            $finder = new Finder();

            foreach ($extensions as $ext) {
                $finder->name('*.'.$ext);
            }

            $finder->files()
                ->in(Path::join($this->projectDir, $tempUploadPath))
            ;

            $index = 0;

            $files = iterator_to_array($finder->getIterator());

            foreach ($files as $file) {
                ++$index;

                // Check if the upload limit has been reached
                if ($model->memberPictureFeedUploadPictureLimit && $this->countUserImages() >= $model->memberPictureFeedUploadPictureLimit) {
                    // Do not store uploads if the upload limit has been reached.
                    $this->message->addError($GLOBALS['TL_LANG']['MPFU']['memberPictureUploadLimitReachedDuringUploadProcess']);
                } elseif (!\in_array($file->getExtension(), $extensions, true)) {
                    // Do not store files with an invalid/not allowed extension
                    $this->message->addError(\sprintf($GLOBALS['TL_LANG']['MPFU']['invalidExtensionErr'], $file->getBasename, $extensions));
                } else {
                    $uploadTime = time() + $index;

                    // Rename file
                    $newFilename = \sprintf('%s-%s.%s', $uploadTime, $this->user->id, $file->getExtension());

                    $newPath = Path::join($this->projectDir, $objUploadFolder->path, $newFilename);

                    $fs->rename($file->getRealPath(), $newPath);

                    $this->dbafs->addResource(Path::makeRelative($newPath, $this->projectDir));
                    $this->dbafs->updateFolderHashes($objUploadFolder->path);

                    $filesModel = $this->filesModel->findByPath(Path::makeRelative($newPath, $this->projectDir));
                    $filesModel->isMemberPictureFeed = true;
                    $filesModel->memberPictureFeedUserId = $this->user->id;
                    $filesModel->memberPictureFeedUploadTime = $uploadTime;
                    $filesModel->tstamp = $uploadTime;

                    $filesModel->save();

                    // Try to resize the image
                    if ($this->resize($newPath)) {
                        $this->message->addInfo(\sprintf($GLOBALS['TL_LANG']['MPFU']['fileUploadedAndResized'], $file->getBasename()));
                    } else {
                        $this->message->addInfo(\sprintf($GLOBALS['TL_LANG']['MPFU']['fileUploaded'], $file->getBasename()));
                    }

                    // Log
                    if (null !== $this->logger) {
                        $strText = \sprintf('User with username %s has uploadad a new picture ("%s") for the member-picture-feed.', $this->user->username, $filesModel->path);
                        $this->logger->info($strText, ['contao' => new ContaoContext(__METHOD__, 'MEMBER PICTURE FEED')]);
                    }
                }
            }

            // Remove the temp folder
            $fs->remove(Path::join($this->projectDir, $tempUploadPath));

            if (!$widget->hasErrors()) {
                // Reload page
                $this->controller->reload();
            }
        }

        return $objForm->generate();
    }

    /**
     * Resize the uploaded image if necessary.
     *
     * @throws \Exception
     */
    private function resize(string $path): bool
    {
        $infoSizeCurrent = getimagesize($path);
        $resized = $this->imageResizer->resize($path, $this->config->get('imageWidth'), $this->config->get('imageHeight'));
        $infoSizeNew = getimagesize($path);

        if ($resized && $infoSizeCurrent !== $infoSizeNew) {
            // Add a flash message
            $this->message->addInfo(\sprintf($GLOBALS['TL_LANG']['MSC']['fileResized'], basename($path)));

            return true;
        }

        return false;
    }
}
