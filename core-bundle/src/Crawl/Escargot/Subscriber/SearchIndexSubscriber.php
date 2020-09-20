<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Crawl\Escargot\Subscriber;

use Contao\CoreBundle\Search\Document;
use Contao\CoreBundle\Search\Indexer\IndexerException;
use Contao\CoreBundle\Search\Indexer\IndexerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminal42\Escargot\CrawlUri;
use Terminal42\Escargot\EscargotAwareInterface;
use Terminal42\Escargot\EscargotAwareTrait;
use Terminal42\Escargot\Subscriber\ExceptionSubscriberInterface;
use Terminal42\Escargot\Subscriber\HtmlCrawlerSubscriber;
use Terminal42\Escargot\Subscriber\RobotsSubscriber;
use Terminal42\Escargot\Subscriber\SubscriberInterface;
use Terminal42\Escargot\Subscriber\Util;
use Terminal42\Escargot\SubscriberLoggerTrait;

class SearchIndexSubscriber implements EscargotSubscriberInterface, EscargotAwareInterface, ExceptionSubscriberInterface, LoggerAwareInterface
{
    use EscargotAwareTrait;
    use LoggerAwareTrait;
    use SubscriberLoggerTrait;

    /**
     * @var IndexerInterface
     */
    private $indexer;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var array
     */
    private $stats = ['ok' => 0, 'warning' => 0, 'error' => 0];

    public function __construct(IndexerInterface $indexer, TranslatorInterface $translator)
    {
        $this->indexer = $indexer;
        $this->translator = $translator;
    }

    public function getName(): string
    {
        return 'search-index';
    }

    public function shouldRequest(CrawlUri $crawlUri): string
    {
        // Check the original crawlUri to see if that one contained nofollow information
        if (
            null !== $crawlUri->getFoundOn()
            && ($originalCrawlUri = $this->escargot->getCrawlUri($crawlUri->getFoundOn()))
            && $originalCrawlUri->hasTag(RobotsSubscriber::TAG_NOFOLLOW)
        ) {
            $this->logWithCrawlUri(
                $crawlUri,
                LogLevel::DEBUG,
                'Do not request because when the crawl URI was found, the robots information disallowed following this URI.'
            );

            return SubscriberInterface::DECISION_NEGATIVE;
        }

        // Skip rel="nofollow" links
        if ($crawlUri->hasTag(HtmlCrawlerSubscriber::TAG_REL_NOFOLLOW)) {
            $this->logWithCrawlUri(
                $crawlUri,
                LogLevel::DEBUG,
                'Do not request because when the crawl URI was found, the "rel" attribute contained "nofollow".'
            );

            return SubscriberInterface::DECISION_NEGATIVE;
        }

        // Skip the links that have the "type" attribute set and it's not text/html
        if ($crawlUri->hasTag(HtmlCrawlerSubscriber::TAG_NO_TEXT_HTML_TYPE)) {
            $this->logWithCrawlUri(
                $crawlUri,
                LogLevel::DEBUG,
                'Do not request because when the crawl URI was found, the "type" attribute was present and did not contain "text/html".'
            );

            return SubscriberInterface::DECISION_NEGATIVE;
        }

        // Skip links that do not belong to our base URI collection
        if (!$this->escargot->getBaseUris()->containsHost($crawlUri->getUri()->getHost())) {
            $this->logWithCrawlUri(
                $crawlUri,
                LogLevel::DEBUG,
                'Did not index because it was not part of the base URI collection.'
            );

            return SubscriberInterface::DECISION_NEGATIVE;
        }

        // Let's go otherwise!
        return SubscriberInterface::DECISION_POSITIVE;
    }

    public function needsContent(CrawlUri $crawlUri, ResponseInterface $response, ChunkInterface $chunk): string
    {
        $statusCode = $response->getStatusCode();

        // We only care about successful responses
        if ($statusCode < 200 || $statusCode >= 300) {
            $this->logWithCrawlUri(
                $crawlUri,
                LogLevel::DEBUG,
                sprintf(
                    'Did not index because according to the HTTP status code the response was not successful (%s).',
                    $response->getStatusCode()
                )
            );

            return SubscriberInterface::DECISION_NEGATIVE;
        }

        // No HTML, no index
        if (!Util::isOfContentType($response, 'text/html')) {
            $this->logWithCrawlUri(
                $crawlUri,
                LogLevel::DEBUG,
                'Did not index because the response did not contain a "text/html" Content-Type header.'
            );

            return SubscriberInterface::DECISION_NEGATIVE;
        }

        // Let's go otherwise!
        return SubscriberInterface::DECISION_POSITIVE;
    }

    public function onLastChunk(CrawlUri $crawlUri, ResponseInterface $response, ChunkInterface $chunk): void
    {
        $document = new Document(
            $crawlUri->getUri(),
            $response->getStatusCode(),
            $response->getHeaders(),
            $response->getContent()
        );

        try {
            $this->indexer->index($document);
            ++$this->stats['ok'];

            $this->logWithCrawlUri(
                $crawlUri,
                LogLevel::INFO,
                'Forwarded to the search indexer. Was indexed successfully.'
            );
        } catch (IndexerException $e) {
            if ($e->isOnlyWarning()) {
                ++$this->stats['warning'];
            } else {
                ++$this->stats['error'];
            }

            $this->logWithCrawlUri(
                $crawlUri,
                LogLevel::DEBUG,
                sprintf('Forwarded to the search indexer. Did not index because of the following reason: %s', $e->getMessage())
            );
        }
    }

    public function getResult(SubscriberResult $previousResult = null): SubscriberResult
    {
        $stats = $this->stats;

        if (null !== $previousResult) {
            $stats['ok'] += $previousResult->getInfo('stats')['ok'];
            $stats['warning'] += $previousResult->getInfo('stats')['warning'];
            $stats['error'] += $previousResult->getInfo('stats')['error'];
        }

        $result = new SubscriberResult(
            0 === $stats['error'],
            $this->translator->trans('CRAWL.searchIndex.summary', [$stats['ok'], $stats['error']], 'contao_default')
        );

        if (0 !== $stats['warning']) {
            $result->setWarning($this->translator->trans('CRAWL.searchIndex.warning', [$stats['warning']], 'contao_default'));
        }

        $result->addInfo('stats', $stats);

        return $result;
    }

    public function onTransportException(CrawlUri $crawlUri, TransportExceptionInterface $exception, ResponseInterface $response): void
    {
        $this->logError($crawlUri, 'Could not request properly: '.$exception->getMessage());
    }

    public function onHttpException(CrawlUri $crawlUri, HttpExceptionInterface $exception, ResponseInterface $response, ChunkInterface $chunk): void
    {
        $this->logError($crawlUri, 'HTTP Status Code: '.$response->getStatusCode());
    }

    private function logError(CrawlUri $crawlUri, string $message): void
    {
        ++$this->stats['error'];

        $this->logWithCrawlUri($crawlUri, LogLevel::ERROR, sprintf('Broken link! %s.', $message));
    }
}
