<?php

/*
 * This file is part of the Zephir.
 *
 * (c) Zephir Team <team@zephir-lang.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zephir\Providers;

use League\Container\Container;
use Psr\Container\ContainerInterface;
use Zephir\BaseBackend;
use Zephir\Compiler;
use Zephir\Di\ServiceProviderInterface;
use Zephir\Parser\Manager;

/**
 * Zephir\Providers\CompilerProvider
 */
final class CompilerProvider implements ServiceProviderInterface
{
    /**
     * {@inheritdoc}
     *
     * @param Container|ContainerInterface $container
     * @return void
     */
    public function register(ContainerInterface $container)
    {
        $service = function () use ($container) {
            $compiler = new Compiler(
                $container->get('config'),
                $container->get('logger'),
                $container->get(BaseBackend::class),
                $container->get(Manager::class)
            );

            $compiler->setContainer($container);

            return $compiler;
        };

        $container->share('compiler', $service);
    }
}