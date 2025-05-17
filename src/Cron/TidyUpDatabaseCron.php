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

namespace Markocupic\MemberPictureFeed\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

#[AsCronJob('daily')]
readonly class TidyUpDatabaseCron
{
    public function __construct(
        private Connection $connection,
        private LoggerInterface|null $contaoErrorLogger = null,
    ) {
    }

    public function __invoke(): void
    {
        $rows = $this->connection->fetchAllAssociative('
            SELECT
              t1.*
            FROM
              tl_files AS t1
              LEFT JOIN tl_member AS t2 ON t1.memberPictureFeedUserId = t2.id
            WHERE
              t1.isMemberPictureFeed = 1
            AND t2.id IS NULL
        ');

        foreach ($rows as $row) {
            $log = sprintf('Image "%s" is tagged with the "isMemberPictureFeed" flag but is not assigned to an existing frontend user.', $row['path']);
            $this?->contaoErrorLogger->error($log);
            $set = ['isMemberPictureFeed' => 0];
            $this->connection->update('tl_files', $set, ['id' => $row['id']]);
        }
    }
}
