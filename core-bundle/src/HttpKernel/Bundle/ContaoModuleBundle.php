<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\HttpKernel\Bundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Webmozart\PathUtil\Path;

/**
 * Allows to register legacy Contao modules as bundle.
 */
final class ContaoModuleBundle extends Bundle
{
    /**
     * Sets the module name and application root directory.
     *
     * @throws \LogicException
     */
    public function __construct(string $name, string $projectDir)
    {
        $this->name = $name;
        $this->path = Path::join($projectDir, 'system/modules', $this->name);

        if (is_dir($this->path)) {
            return;
        }

        // Backwards compatibility, $projectDir was previously set from kernel $rootDir
        $this->path = Path::join($projectDir, '../system/modules', $this->name);

        if (!is_dir($this->path)) {
            throw new \LogicException(sprintf('The module folder "system/modules/%s" does not exist.', $this->name));
        }
    }
}
