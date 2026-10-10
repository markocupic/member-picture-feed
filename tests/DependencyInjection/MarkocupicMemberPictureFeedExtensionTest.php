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

namespace Markocupic\MemberPictureFeed\Tests\DependencyInjection;

use Markocupic\MemberPictureFeed\Controller\AjaxController;
use Markocupic\MemberPictureFeed\Controller\FrontendModule\MemberPictureFeedUploadController;
use Markocupic\MemberPictureFeed\DependencyInjection\MarkocupicMemberPictureFeedExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class MarkocupicMemberPictureFeedExtensionTest extends TestCase
{
    public function testRegistersTheServices(): void
    {
        $container = new ContainerBuilder();

        (new MarkocupicMemberPictureFeedExtension())->load([], $container);

        $this->assertTrue($container->hasDefinition(AjaxController::class));
        $this->assertTrue($container->hasDefinition(MemberPictureFeedUploadController::class));
        $this->assertSame('jpg,jpeg', $container->getParameter('markocupic_member_picture_feed.valid_extensions'));
    }

    public function testOverridesTheValidExtensions(): void
    {
        $container = new ContainerBuilder();

        (new MarkocupicMemberPictureFeedExtension())->load([['valid_extensions' => 'jpg,png']], $container);

        $this->assertSame('jpg,png', $container->getParameter('markocupic_member_picture_feed.valid_extensions'));
    }
}
