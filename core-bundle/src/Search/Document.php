<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Search;

use Nyholm\Psr7\Uri;
use Psr\Http\Message\UriInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Document
{
    /**
     * @var UriInterface
     */
    private $uri;

    /**
     * @var int
     */
    private $statusCode;

    /**
     * The key is the header name in lowercase letters and the value is again
     * an array of header values.
     *
     * @var array<string,array>
     */
    private $headers;

    /**
     * @var string
     */
    private $body;

    /**
     * @var Crawler
     */
    private $crawler;

    /**
     * @var array|null
     */
    private $jsonLds;

    public function __construct(UriInterface $uri, int $statusCode, array $headers = [], string $body = '')
    {
        $this->uri = $uri;
        $this->statusCode = $statusCode;
        $this->headers = array_change_key_case($headers);
        $this->body = $body;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getContentCrawler(): Crawler
    {
        if (null === $this->crawler) {
            $this->crawler = new Crawler($this->body);
        }

        return $this->crawler;
    }

    /**
     * Extracts all <script type="application/ld+json"> script tags and returns their contents as a JSON decoded
     * array. Optionally allows to restrict it to a given context and type.
     */
    public function extractJsonLdScripts(string $context = '', string $type = ''): array
    {
        if (null !== $this->jsonLds) {
            return $this->filterJsonLd($this->jsonLds, $context, $type);
        }

        $this->jsonLds = [];

        if ('' === $this->body) {
            return $this->jsonLds;
        }

        $this->jsonLds = $this->getContentCrawler()
            ->filterXPath('descendant-or-self::script[@type = "application/ld+json"]')
            ->each(
                static function (Crawler $node) {
                    $data = json_decode($node->text(), true);

                    if (JSON_ERROR_NONE !== json_last_error()) {
                        return null;
                    }

                    return $data;
                }
            )
        ;

        // Filter invalid (null) values
        $this->jsonLds = array_filter($this->jsonLds);

        return $this->filterJsonLd($this->jsonLds, $context, $type);
    }

    public static function createFromRequestResponse(Request $request, Response $response): self
    {
        return new self(
            new Uri($request->getUri()),
            $response->getStatusCode(),
            $response->headers->all(),
            (string) $response->getContent()
        );
    }

    private function filterJsonLd(array $jsonLds, string $context = '', string $type = ''): array
    {
        $matching = [];

        foreach ($jsonLds as $data) {
            $data = $this->expandJsonLdContexts($data);

            if ('' !== $type && (!isset($data['@type']) || $data['@type'] !== $context.$type)) {
                continue;
            }

            if (\count($filtered = $this->filterJsonLdContexts($data, [$context]))) {
                $matching[] = $filtered;
            }
        }

        return $matching;
    }

    private function expandJsonLdContexts(array $data): array
    {
        if (empty($data['@context'])) {
            return $data;
        }

        if (\is_string($data['@context'])) {
            foreach ($data as $key => $value) {
                if ('@type' === $key) {
                    $data[$key] = $data['@context'].$value;
                    continue;
                }

                if ('@' !== $key[0]) {
                    unset($data[$key]);
                    $data[$data['@context'].$key] = $value;
                }
            }

            return $data;
        }

        if (\is_array($data['@context'])) {
            foreach ($data['@context'] as $prefix => $context) {
                if (isset($data['@type']) && 0 === strncmp($data['@type'], $prefix.':', \strlen($prefix) + 1)) {
                    $data['@type'] = $context.substr($data['@type'], \strlen($prefix) + 1);
                }

                foreach ($data as $key => $value) {
                    if (0 === strncmp($prefix.':', $key, \strlen($prefix) + 1)) {
                        unset($data[$key]);
                        $data[$context.substr($key, \strlen($prefix) + 1)] = $value;
                    }
                }
            }

            return $data;
        }

        throw new \RuntimeException('Unable to expand JSON-LD data');
    }

    private function filterJsonLdContexts(array $data, array $contexts): array
    {
        $newData = [];
        $found = false;

        foreach ($data as $key => $value) {
            foreach ($contexts as $context) {
                if ('@type' === $key) {
                    $newData[$key] = $value;

                    if (0 === strncmp($value, $context, \strlen($context))) {
                        $newData[$key] = substr($value, \strlen($context));
                        $found = true;
                        break;
                    }
                }

                if (0 === strncmp($context, $key, \strlen($context))) {
                    $newData[substr($key, \strlen($context))] = $value;
                    $found = true;
                    break;
                }
            }
        }

        return $found ? $newData : [];
    }
}
