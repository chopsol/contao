<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Util;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class SimpleTokenExpressionLanguage extends ExpressionLanguage
{
    public function __construct(CacheItemPoolInterface $cache = null, \IteratorAggregate $taggedProviders = null)
    {
        $providers = null !== $taggedProviders ? iterator_to_array($taggedProviders->getIterator()) : [];

        parent::__construct($cache, $providers);

        // Disable `constant()` function for security reasons.
        $this->register(
            'constant',
            static function () {
                return "throw new \\InvalidArgumentException('Cannot use the constant() function in the expression for security reasons.');";
            },
            static function (): void {
                throw new \InvalidArgumentException('Cannot use the constant() function in the expression for security reasons.');
            }
        );
    }
}
