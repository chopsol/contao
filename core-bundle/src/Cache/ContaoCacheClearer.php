<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Cache;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Webmozart\PathUtil\Path;

class ContaoCacheClearer implements CacheClearerInterface
{
    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @internal Do not inherit from this class; decorate the "contao.cache.clear_internal" service instead
     */
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function clear($cacheDir): void
    {
        $this->filesystem->remove(Path::join($cacheDir, 'contao/config'));
        $this->filesystem->remove(Path::join($cacheDir, 'contao/dca'));
        $this->filesystem->remove(Path::join($cacheDir, 'contao/languages'));
        $this->filesystem->remove(Path::join($cacheDir, 'contao/sql'));
    }
}
