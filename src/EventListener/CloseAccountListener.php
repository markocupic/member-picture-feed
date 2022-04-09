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

namespace Markocupic\MemberPictureFeed\EventListener;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\MemberModel;
use Contao\Module;
use Doctrine\DBAL\Exception;
use Markocupic\MemberPictureFeed\DataContainer\Member;

/**
 * @Hook("closeAccount")
 */
class CloseAccountListener
{
    private ContaoFramework $framework;
    private Member $member;

    // Adapters
    private Adapter $memberModel;

    public function __construct(ContaoFramework $framework, Member $member)
    {
        $this->framework = $framework;
        $this->member = $member;

        // Adapters
        $this->memberModel = $this->framework->getAdapter(MemberModel::class);
    }

    /**
     * @throws Exception
     */
    public function __invoke(int $userId, string $mode, Module $module): void
    {
        $member = $this->memberModel->findByPk($userId);

        if (null !== $member) {
            $this->member->deleteAllMemberPictures($member);
        }
    }
}
