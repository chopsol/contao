<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Routing\Page;

use Contao\PageModel;
use Doctrine\DBAL\Connection;

class PageRegistry
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var array<RouteConfig>
     */
    private $routeConfigs = [];

    /**
     * @var array<DynamicRouteInterface>
     */
    private $routeEnhancers = [];

    /**
     * @var array<ContentCompositionInterface|bool>
     */
    private $contentComposition = [];

    /**
     * @var array<string>|null
     */
    private $urlPrefixes;

    /**
     * @var array<string>|null
     */
    private $urlSuffixes;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Returns the route for a page.
     *
     * If no path is configured (is null), the route will accept
     * any parameters after the page alias (e.g. "en/page-alias/foo/bar.html").
     *
     * A route enhancer might enhance the route for a specific page.
     */
    public function getRoute(PageModel $pageModel): PageRoute
    {
        $type = $pageModel->type;
        $config = $this->routeConfigs[$type] ?? new RouteConfig();
        $defaults = $config->getDefaults();
        $requirements = $config->getRequirements();
        $path = $config->getPath();

        if (null === $path) {
            $path = '/'.($pageModel->alias ?: $pageModel->id).'{!parameters}';
            $defaults['parameters'] = '';
            $requirements['parameters'] = $pageModel->requireItem ? '/.+' : '(/.+)?';
        }

        $route = new PageRoute($pageModel, $path, $defaults, $requirements, $config->getOptions(), $config->getMethods());

        if (null !== $config->getUrlSuffix()) {
            $route->setUrlSuffix($config->getUrlSuffix());
        }

        if (!isset($this->routeEnhancers[$type])) {
            return $route;
        }

        /** @var DynamicRouteInterface $enhancer */
        $enhancer = $this->routeEnhancers[$type];
        $enhancer->configurePageRoute($route);

        return $route;
    }

    public function getPathRegex(): array
    {
        $prefixes = [];

        /** @var RouteConfig $config */
        foreach ($this->routeConfigs as $type => $config) {
            $regex = $config->getPathRegex();

            if (null !== $regex) {
                $prefixes[$type] = $regex;
            }
        }

        return $prefixes;
    }

    public function supportsContentComposition(PageModel $pageModel): bool
    {
        if (!isset($this->contentComposition[$pageModel->type])) {
            return true;
        }

        $service = $this->contentComposition[$pageModel->type];

        if ($service instanceof ContentCompositionInterface) {
            return $service->supportsContentComposition($pageModel);
        }

        return (bool) $service;
    }

    /**
     * @return array<string>
     */
    public function getUrlPrefixes(): array
    {
        $this->initializePrefixAndSuffix();

        return $this->urlPrefixes;
    }

    /**
     * @return array<string>
     */
    public function getUrlSuffixes(): array
    {
        $this->initializePrefixAndSuffix();

        return $this->urlSuffixes;
    }

    /**
     * @param ContentCompositionInterface|bool $contentComposition
     */
    public function add(string $type, RouteConfig $config, DynamicRouteInterface $routeEnhancer = null, $contentComposition = true): self
    {
        // Override existing pages with the same identifier
        $this->routeConfigs[$type] = $config;

        if (null !== $routeEnhancer) {
            $this->routeEnhancers[$type] = $routeEnhancer;
        }

        if (null !== $contentComposition) {
            $this->contentComposition[$type] = $contentComposition;
        }

        $this->urlPrefixes = $this->urlSuffixes = null;

        return $this;
    }

    public function remove(string $type): self
    {
        unset(
            $this->routeConfigs[$type],
            $this->routeEnhancers[$type],
            $this->contentComposition[$type]
        );

        $this->urlPrefixes = $this->urlSuffixes = null;

        return $this;
    }

    public function keys(): array
    {
        return array_keys($this->routeConfigs);
    }

    private function initializePrefixAndSuffix(): void
    {
        if (null !== $this->urlPrefixes || null !== $this->urlSuffixes) {
            return;
        }

        $results = $this->connection
            ->query("SELECT urlPrefix, urlSuffix FROM tl_page WHERE type='root'")
            ->fetchAll()
        ;

        $urlSuffixes = [
            array_column($results, 'urlSuffix'),
            array_filter(array_map(
                static function (RouteConfig $config) {
                    return $config->getUrlSuffix();
                },
                $this->routeConfigs
            )),
        ];

        foreach ($this->routeConfigs as $config) {
            if (null !== ($suffix = $config->getUrlSuffix())) {
                $urlSuffixes[] = [$suffix];
            }
        }

        foreach ($this->routeEnhancers as $enhancer) {
            $urlSuffixes[] = $enhancer->getUrlSuffixes();
        }

        $this->urlSuffixes = array_values(array_unique(array_merge(...$urlSuffixes)));
        $this->urlPrefixes = array_values(array_unique(array_column($results, 'urlPrefix')));
    }
}
