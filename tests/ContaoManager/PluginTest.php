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

namespace Markocupic\MemberPictureFeed\Tests\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\DelegatingParser;
use Markocupic\MemberPictureFeed\ContaoManager\Plugin;
use Markocupic\MemberPictureFeed\MarkocupicMemberPictureFeed;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Loader\AttributeClassLoader;
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Symfony\Component\Routing\Route;

class PluginTest extends TestCase
{
    public function testReturnsTheBundles(): void
    {
        $bundles = (new Plugin())->getBundles(new DelegatingParser());

        $this->assertCount(1, $bundles);
        $this->assertInstanceOf(BundleConfig::class, $bundles[0]);
        $this->assertSame(MarkocupicMemberPictureFeed::class, $bundles[0]->getName());
        $this->assertSame([ContaoCoreBundle::class], $bundles[0]->getLoadAfter());
    }

    public function testLoadsTheXhrRoutes(): void
    {
        $classLoader = new class() extends AttributeClassLoader {
            protected function configureRoute(Route $route, \ReflectionClass $class, \ReflectionMethod $method, object $attr): void
            {
                $route->setDefault('_controller', $class->getName().'::'.$method->getName());
            }
        };

        $resolver = new LoaderResolver([new AttributeDirectoryLoader(new FileLocator(), $classLoader)]);
        $routes = (new Plugin())->getRouteCollection($resolver, $this->createStub(KernelInterface::class));

        foreach (['remove_image', 'rotate_image', 'get_caption', 'set_caption'] as $name) {
            $this->assertNotNull($routes->get('member_picture_feed_xhr_'.$name), $name);
        }

        $this->assertSame('/_member_picture_feed_xhr/remove_image', $routes->get('member_picture_feed_xhr_remove_image')->getPath());
        $this->assertTrue($routes->get('member_picture_feed_xhr_remove_image')->getDefault('_token_check'));
    }
}
