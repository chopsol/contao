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

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\PageModel;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\Routing\Route;

class PageRoute extends Route implements RouteObjectInterface
{
    /**
     * @var PageModel
     */
    private $pageModel;

    /**
     * @var string
     */
    private $urlPrefix;

    /**
     * @var string
     */
    private $urlSuffix;

    /**
     * The referenced content object.
     */
    private $content;

    /**
     * @param string|array<string> $methods
     */
    public function __construct(PageModel $pageModel, string $path = '', array $defaults = [], array $requirements = [], array $options = [], $methods = [])
    {
        $pageModel->loadDetails();

        $defaults = array_merge(
            [
                '_token_check' => true,
                '_controller' => 'Contao\FrontendIndex:renderPage',
                '_scope' => ContaoCoreBundle::SCOPE_FRONTEND,
                '_locale' => $pageModel->rootLanguage,
                '_format' => 'html',
            ],
            $defaults
        );

        $defaults['pageModel'] = $pageModel;

        if (!isset($options['utf8'])) {
            $options['utf8'] = true;
        }

        if ('' === $path) {
            $path = '/'.($pageModel->alias ?: $pageModel->id);
        } elseif (0 !== strncmp($path, '/', 1)) {
            $path = '/'.($pageModel->alias ?: $pageModel->id).'/'.$path;
        }

        parent::__construct(
            $path,
            $defaults,
            $requirements,
            $options,
            $pageModel->domain,
            $pageModel->rootUseSSL ? 'https' : null,
            $methods
        );

        $this->pageModel = $pageModel;
        $this->urlPrefix = $pageModel->urlPrefix;
        $this->urlSuffix = $pageModel->urlSuffix;
    }

    public function getPageModel(): PageModel
    {
        return $this->pageModel;
    }

    public function getPath(): string
    {
        $path = parent::getPath();

        if ('' !== $this->getUrlPrefix()) {
            $path = '/'.$this->getUrlPrefix().$path;
        }

        return $path.$this->getUrlSuffix();
    }

    public function getUrlPrefix(): string
    {
        return $this->urlPrefix;
    }

    public function setUrlPrefix(string $urlPrefix): self
    {
        $this->urlPrefix = $urlPrefix;

        return $this;
    }

    public function getUrlSuffix(): string
    {
        return $this->urlSuffix;
    }

    public function setUrlSuffix(string $urlSuffix): self
    {
        $this->urlSuffix = $urlSuffix;

        return $this;
    }

    /**
     * Sets the object this URL points to.
     */
    public function setContent($object): self
    {
        $this->content = $object;

        return $this;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function getRouteKey(): string
    {
        return 'tl_page.'.$this->pageModel->id;
    }
}
