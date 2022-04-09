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

namespace Markocupic\MemberPictureFeed\DataContainer;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\Dbafs;
use Contao\File;
use Contao\MemberModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class Member
{
    private ContaoFramework $framework;
    private Connection $connection;

    // Adapters
    private Adapter $memberModel;
    private Adapter $dbafs;

    public function __construct(ContaoFramework $framework, Connection $connection)
    {
        $this->framework = $framework;
        $this->connection = $connection;

        // Adapters
        $this->memberModel = $this->framework->getAdapter(MemberModel::class);
        $this->dbafs = $this->framework->getAdapter(Dbafs::class);
    }

    /**
     * @throws Exception
     *
     * @Callback(table="tl_member", target="config.ondelete")
     */
    public function ondeleteCallbackListener(DataContainer $dc, int $undoId): void
    {
        if (!$dc->id) {
            return;
        }

        $member = $this->memberModel->findByPk($dc->id);

        if (null !== $member) {
            $this->deleteAllMemberPictures($member);
        }
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function deleteAllMemberPictures(MemberModel $member): void
    {
        $stmt = $this->connection->executeQuery('SELECT * FROM tl_files WHERE isMemberPictureFeed = ? AND memberPictureFeedUserId = ?', ['1', $member->id]);

        while (false !== ($rowFile = $stmt->fetchAssociative())) {
            $file = new File($rowFile['path']);

            $res = $file->path;
            $file->delete();

            $this->connection->delete('tl_files', ['id' => $rowFile['id']]);

            if ('' !== $res) {
                $this->dbafs->updateFolderHashes(\dirname($res));
            }
        }
    }
}
