<?php

declare(strict_types=1);

/*
 * This file is part of Member Picture Feed.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
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

#[AsFrontendModule(MemberPictureFeedUploadController::TYPE, category: 'member_picture_feed')]
class MemberPictureFeedUploadController extends AbstractFrontendModuleController
{
    public const string TYPE = 'memberPictureFeedUpload';

    public const string UPLOAD_WIDGET_NAME = 'fileupload';

    // Adapters
    private Adapter $config;

    private Adapter $controller;

    private Adapter $date;

    private Adapter $dbafs;

    private Adapter $filesModel;

    private Adapter $frontend;

    private Adapter $message;

    private Adapter $stringUtil;

    private FrontendUser|null $user = null;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
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
    }

    /**
     * @throws \Exception
     */
    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        // The module is only available to logged in members
        if (!$user instanceof FrontendUser) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $this->user = $user;

        if ($this->countUserImages() < $model->memberPictureFeedUploadPictureLimit || $model->memberPictureFeedUploadPictureLimit < 1) {
            $tempUploadPath = 'system/tmp/'.uniqid('mpfu_');

            // Create the temp upload folder
            if (!empty($_POST[self::UPLOAD_WIDGET_NAME]) && !$request->headers->has('fileponditemid')) {
                $fs = new Filesystem();
                $fs->mkdir($this->projectDir.'/'.$tempUploadPath);
            }

            $form = $this->buildForm($request, $tempUploadPath);

            if ($form->validate()) {
                $this->handleUpload($form, $model, $tempUploadPath);
            }

            $template->set('form', $form->generate());
        } else {
            $this->message->addInfo($GLOBALS['TL_LANG']['MPFU']['memberPictureUploadLimitReached']);
        }

        $pictures = $this->buildGallery($model, $request->attributes->get('pageModel'));
        $template->set('hasGallery', !empty($pictures));
        $template->set('pictures', $pictures);

        $template->set('page', $request->attributes->get('pageModel')->row());

        // Get flash bag messages
        if ($this->message->hasMessages()) {
            $template->set('messages', $this->message->generateUnwrapped());
        }

        return $template->getResponse();
    }

    protected function buildGallery(ModuleModel $model, PageModel $page): array
    {
        $pictures = [];

        $rows = $this->connection->fetchAllAssociative(
            '
            SELECT * FROM tl_files
                WHERE
             isMemberPictureFeed = ?
                AND
             memberPictureFeedUserId = ?
                ORDER BY
             memberPictureFeedUploadTime DESC
             ',
            [
                1,
                $this->user->id,
            ],
        );

        foreach ($rows as $rowFile) {
            $picture = [];

            $oFile = $this->filesModel->findByUuid($rowFile['uuid']);

            if (null === $oFile || !is_file($this->projectDir.'/'.$oFile->path)) {
                continue;
            }

            $size = $this->stringUtil->deserialize($model->imgSize, true);

            // Meta tags
            $meta = $this->frontend->getMetaData($oFile->meta, $page->language);

            if (empty($meta) && null !== $page->rootFallbackLanguage) {
                $meta = $this->frontend->getMetaData($oFile->meta, $page->rootFallbackLanguage);
            }

            // Output encoding
            foreach ($meta as $k => $v) {
                $meta[$k] = $this->stringUtil->specialcharsAttribute($v);
            }

            // Create the figure
            $figure = $this->studio->createFigureBuilder()
                ->fromUuid($rowFile['uuid'])
                ->setSize($size)
                ->setMetadata(new Metadata($meta))
                ->buildIfResourceExists()
            ;

            if ($figure) {
                $picture['figure'] = $figure;
                $picture['data'] = $rowFile;
                $picture['data']['memberPictureFeedUploadTimeFormatted'] = $this->date->parse($this->config->get('dateFormat'), $rowFile['memberPictureFeedUploadTime']);
                $picture['data']['hasCaption'] = !empty($meta['caption']);

                $pictures[] = $picture;
            }
        }

        return $pictures;
    }

    /**
     * @throws Exception
     */
    protected function countUserImages(): int
    {
        return (int) $this->connection->fetchOne(
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
    protected function buildForm(Request $request, string $tempUploadPath): Form
    {
        $form = new Form(
            'form-member-picture-feed-upload',
            'POST',
        );

        $form->setAction($request->getUri());

        // Add the file upload field
        $form->addFormField(self::UPLOAD_WIDGET_NAME, [
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
                'imgResizeWidth' => $this->config->get('imageWidth'),
                'imgResizeHeight' => $this->config->get('imageHeight'),
                // Client side image resizing
                'imgResize' => true, // Must be set to true or the other resize options will be ignored!
                'imgResizeBrowser' => true, // Allow client side image resizing! 'imgResize' must be set to "true" as well!
            ],
        ]);

        // Add the submit-button
        $form->addFormField('submit', [
            'label' => $GLOBALS['TL_LANG']['MPFU']['save'],
            'inputType' => 'submit',
        ]);

        return $form;
    }

    protected function handleUpload(Form $form, ModuleModel $model, $tempUploadPath): void
    {
        $filesModel = $this->filesModel->findByUuid($model->memberPictureFeedUploadFolder);

        if (null === $filesModel) {
            throw new \Exception('Image upload directory not set or not found. Please check your module configuration.');
        }

        $uploadFolder = new Folder($filesModel->path);

        $extensions = $this->stringUtil->trimSplit(',', $this->validExtensions);

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
                $this->message->addError(\sprintf($GLOBALS['TL_LANG']['MPFU']['invalidExtensionErr'], $file->getBasename(), implode(', ', $extensions)));
            } else {
                $uploadTime = time() + $index;

                // Rename file
                $newFilename = \sprintf('%s-%s.%s', $uploadTime, $this->user->id, $file->getExtension());
                $newPath = Path::join($this->projectDir, $uploadFolder->path, $newFilename);
                $fs->rename($file->getRealPath(), $newPath);

                // Add the file to the database
                $this->dbafs->addResource(Path::makeRelative($newPath, $this->projectDir));
                $this->dbafs->updateFolderHashes($uploadFolder->path);

                // Add data to files model
                $filesModel = $this->filesModel->findByPath(Path::makeRelative($newPath, $this->projectDir));
                $filesModel->isMemberPictureFeed = true;
                $filesModel->memberPictureFeedUserId = $this->user->id;
                $filesModel->memberPictureFeedUploadTime = $uploadTime;
                $filesModel->tstamp = $uploadTime;
                $filesModel->save();

                // Try to resize the image
                if ($this->resize($newPath)) {
                    $imageSize = getimagesize($newPath);

                    $this->message->addInfo(\sprintf($GLOBALS['TL_LANG']['MPFU']['fileUploadedAndResized'], $file->getBasename(), $imageSize[0], $imageSize[1]));
                } else {
                    $this->message->addInfo(\sprintf($GLOBALS['TL_LANG']['MPFU']['fileUploaded'], $file->getBasename()));
                }

                // Contao system log
                if (null !== $this->logger) {
                    $text = \sprintf('User with username %s has uploadad a new picture ("%s") for the member-picture-feed.', $this->user->username, $filesModel->path);
                    $this->logger->info($text, ['contao' => new ContaoContext(__METHOD__, 'MEMBER PICTURE FEED')]);
                }
            }
        }

        // Remove the temp folder
        $fs->remove(Path::join($this->projectDir, $tempUploadPath));

        if (!$form->getWidget(self::UPLOAD_WIDGET_NAME)->hasErrors()) {
            // Reload the page
            $this->controller->reload();
        }
    }

    protected function resize(string $path): bool
    {
        $infoSizeCurrent = getimagesize($path);
        $resized = $this->imageResizer->resize($path, $this->config->get('imageWidth'), $this->config->get('imageHeight'));
        $infoSizeNew = getimagesize($path);

        if ($resized && $infoSizeCurrent !== $infoSizeNew) {
            return true;
        }

        return false;
    }
}
