<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Routing\Matcher;

use Contao\PageModel;
use Symfony\Cmf\Component\Routing\NestedMatcher\RouteFilterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;

/**
 * Removes fallback routes if the accepted languages do not match (see #430).
 */
class LanguageFilter implements RouteFilterInterface
{
    /**
     * @internal Do not inherit from this class; decorate the "contao.routing.language_filter" service instead
     */
    public function __construct()
    {
    }

    public function filter(RouteCollection $collection, Request $request): RouteCollection
    {
        $languages = $request->getLanguages();

        foreach ($collection->all() as $name => $route) {
            /** @var PageModel $pageModel */
            $pageModel = $route->getDefault('pageModel');

            if (!$pageModel instanceof PageModel) {
                continue;
            }

            if ('.fallback' !== substr($name, -9) && ('' !== $pageModel->urlPrefix || '.root' !== substr($name, -5))) {
                continue;
            }

            if (
                $pageModel->rootIsFallback
                || \in_array(str_replace('-', '_', $pageModel->rootLanguage), $languages, true)
                || preg_grep('/'.preg_quote($pageModel->rootLanguage, '/').'_[A-Z]{2}/', $languages)
            ) {
                continue;
            }

            $collection->remove($name);
        }

        return $collection;
    }
}
