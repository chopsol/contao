<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Routing;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

class UrlGenerator implements UrlGeneratorInterface
{
    /**
     * @var UrlGeneratorInterface
     */
    private $router;

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var bool
     */
    private $prependLocale;

    /**
     * @internal Do not inherit from this class; decorate the "contao.routing.url_generator" service instead
     */
    public function __construct(UrlGeneratorInterface $router, ContaoFramework $framework, bool $prependLocale)
    {
        trigger_deprecation('contao/core-bundle', '4.10', 'Using the "Contao\CoreBundle\Routing\UrlGenerator" class has been deprecated and will no longer work in Contao 5.0. Use the Symfony router instead.', E_USER_DEPRECATED);

        $this->router = $router;
        $this->framework = $framework;
        $this->prependLocale = $prependLocale;
    }

    public function setContext(RequestContext $context): void
    {
        $this->router->setContext($context);
    }

    public function getContext(): RequestContext
    {
        return $this->router->getContext();
    }

    public function generate($name, $parameters = [], $referenceType = self::ABSOLUTE_PATH): ?string
    {
        $this->framework->initialize();

        if (!\is_array($parameters)) {
            $parameters = [];
        }

        $context = $this->getContext();

        // Store the original request context
        $host = $context->getHost();
        $scheme = $context->getScheme();
        $httpPort = $context->getHttpPort();
        $httpsPort = $context->getHttpsPort();

        $this->prepareLocale($parameters);
        $this->prepareAlias($name, $parameters);
        $this->prepareDomain($context, $parameters, $referenceType);

        unset($parameters['auto_item']);

        $url = $this->router->generate(
            'index' === $name ? 'contao_index' : 'contao_frontend',
            $parameters,
            $referenceType
        );

        // Reset the request context
        $context->setHost($host);
        $context->setScheme($scheme);
        $context->setHttpPort($httpPort);
        $context->setHttpsPort($httpsPort);

        return $url;
    }

    /**
     * Removes the locale parameter if it is disabled.
     */
    private function prepareLocale(array &$parameters): void
    {
        if (!$this->prependLocale && \array_key_exists('_locale', $parameters)) {
            unset($parameters['_locale']);
        }
    }

    /**
     * Adds the parameters to the alias.
     *
     * @throws MissingMandatoryParametersException
     */
    private function prepareAlias(string $alias, array &$parameters): void
    {
        if ('index' === $alias) {
            return;
        }

        $hasAutoItem = false;
        $autoItems = $this->getAutoItems($parameters);

        /** @var Config $config */
        $config = $this->framework->getAdapter(Config::class);

        $parameters['alias'] = preg_replace_callback(
            '/{([^}]+)}/',
            static function (array $matches) use ($alias, &$parameters, $autoItems, &$hasAutoItem, $config): string {
                $param = $matches[1];

                if (!isset($parameters[$param])) {
                    throw new MissingMandatoryParametersException(sprintf('Parameters "%s" is missing to generate a URL for "%s"', $param, $alias));
                }

                $value = $parameters[$param];
                unset($parameters[$param]);

                if ($hasAutoItem || !$config->get('useAutoItem') || !\in_array($param, $autoItems, true)) {
                    return $param.'/'.$value;
                }

                $hasAutoItem = true;

                return $value;
            },
            $alias
        );
    }

    /**
     * Forces the router to add the host if necessary.
     */
    private function prepareDomain(RequestContext $context, array &$parameters, int &$referenceType): void
    {
        if (isset($parameters['_ssl'])) {
            $context->setScheme(true === $parameters['_ssl'] ? 'https' : 'http');
        }

        if (isset($parameters['_domain']) && '' !== $parameters['_domain']) {
            $this->addHostToContext($context, $parameters, $referenceType);
        }

        unset($parameters['_domain'], $parameters['_ssl']);
    }

    /**
     * Sets the context from the domain.
     */
    private function addHostToContext(RequestContext $context, array $parameters, int &$referenceType): void
    {
        /**
         * @var string   $host
         * @var int|null $port
         */
        [$host, $port] = $this->getHostAndPort($parameters['_domain']);

        if ($context->getHost() === $host) {
            return;
        }

        $context->setHost($host);
        $referenceType = UrlGeneratorInterface::ABSOLUTE_URL;

        if (!$port) {
            return;
        }

        if (isset($parameters['_ssl']) && true === $parameters['_ssl']) {
            $context->setHttpsPort($port);
        } else {
            $context->setHttpPort($port);
        }
    }

    /**
     * Extracts host and port from the domain.
     *
     * @return array<string|int|null>
     */
    private function getHostAndPort(string $domain): array
    {
        if (false !== strpos($domain, ':')) {
            [$host, $port] = explode(':', $domain, 2);

            return [$host, (int) $port];
        }

        return [$domain, null];
    }

    /**
     * Returns the auto_item key from the parameters or the global array.
     *
     * @return array<string>
     */
    private function getAutoItems(array $parameters): array
    {
        if (isset($parameters['auto_item'])) {
            return [$parameters['auto_item']];
        }

        if (isset($GLOBALS['TL_AUTO_ITEM']) && \is_array($GLOBALS['TL_AUTO_ITEM'])) {
            return $GLOBALS['TL_AUTO_ITEM'];
        }

        return [];
    }
}
