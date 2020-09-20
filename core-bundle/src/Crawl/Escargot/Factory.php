<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Crawl\Escargot;

use Contao\CoreBundle\Crawl\Escargot\Subscriber\EscargotSubscriberInterface;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use Nyholm\Psr7\Uri;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Terminal42\Escargot\BaseUriCollection;
use Terminal42\Escargot\Escargot;
use Terminal42\Escargot\Exception\InvalidJobIdException;
use Terminal42\Escargot\Queue\DoctrineQueue;
use Terminal42\Escargot\Queue\InMemoryQueue;
use Terminal42\Escargot\Queue\LazyQueue;
use Terminal42\Escargot\Queue\QueueInterface;
use Terminal42\Escargot\Subscriber\HtmlCrawlerSubscriber;
use Terminal42\Escargot\Subscriber\RobotsSubscriber;

class Factory
{
    public const USER_AGENT = 'contao/crawler';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var array<string>
     */
    private $additionalUris;

    /**
     * @var array
     */
    private $defaultHttpClientOptions;

    /**
     * @var array<EscargotSubscriberInterface>
     */
    private $subscribers = [];

    public function __construct(Connection $connection, ContaoFramework $framework, array $additionalUris = [], array $defaultHttpClientOptions = [])
    {
        $this->connection = $connection;
        $this->framework = $framework;
        $this->additionalUris = $additionalUris;
        $this->defaultHttpClientOptions = $defaultHttpClientOptions;
    }

    public function addSubscriber(EscargotSubscriberInterface $subscriber): self
    {
        $this->subscribers[] = $subscriber;

        return $this;
    }

    /**
     * @return array<EscargotSubscriberInterface>
     */
    public function getSubscribers(array $selectedSubscribers = []): array
    {
        if (0 === \count($selectedSubscribers)) {
            return $this->subscribers;
        }

        return array_filter(
            $this->subscribers,
            static function (EscargotSubscriberInterface $subscriber) use ($selectedSubscribers): bool {
                return \in_array($subscriber->getName(), $selectedSubscribers, true);
            }
        );
    }

    /**
     * @return array<string>
     */
    public function getSubscriberNames(): array
    {
        return array_map(
            static function (EscargotSubscriberInterface $subscriber): string {
                return $subscriber->getName();
            },
            $this->subscribers
        );
    }

    public function createLazyQueue(): LazyQueue
    {
        return new LazyQueue(
            new InMemoryQueue(),
            new DoctrineQueue(
                $this->connection,
                static function (): string {
                    return Uuid::uuid4()->toString();
                },
                'tl_crawl_queue'
            )
        );
    }

    public function getDefaultHttpClientOptions(): array
    {
        return $this->defaultHttpClientOptions;
    }

    public function getCrawlUriCollection(): BaseUriCollection
    {
        return $this->getRootPageUriCollection()->mergeWith($this->getAdditionalCrawlUriCollection());
    }

    public function getAdditionalCrawlUriCollection(): BaseUriCollection
    {
        $collection = new BaseUriCollection();

        foreach ($this->additionalUris as $additionalUri) {
            $collection->add(new Uri($additionalUri));
        }

        return $collection;
    }

    public function getRootPageUriCollection(): BaseUriCollection
    {
        $this->framework->initialize();

        $collection = new BaseUriCollection();

        /** @var PageModel $pageModel */
        $pageModel = $this->framework->getAdapter(PageModel::class);
        $rootPages = $pageModel->findPublishedRootPages();

        if (null === $rootPages) {
            return $collection;
        }

        foreach ($rootPages as $rootPage) {
            $collection->add(new Uri($rootPage->getAbsoluteUrl()));
        }

        return $collection;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function create(BaseUriCollection $baseUris, QueueInterface $queue, array $selectedSubscribers, array $clientOptions = []): Escargot
    {
        $escargot = Escargot::create($baseUris, $queue)
            ->withHttpClient($this->createHttpClient($clientOptions))
            ->withUserAgent(self::USER_AGENT)
        ;

        $this->registerDefaultSubscribers($escargot);
        $this->registerSubscribers($escargot, $this->validateSubscribers($selectedSubscribers));

        return $escargot;
    }

    /**
     * @throws \InvalidArgumentException
     * @throws InvalidJobIdException
     */
    public function createFromJobId(string $jobId, QueueInterface $queue, array $selectedSubscribers, array $clientOptions = []): Escargot
    {
        $escargot = Escargot::createFromJobId($jobId, $queue)
            ->withHttpClient($this->createHttpClient($clientOptions))
            ->withUserAgent(self::USER_AGENT)
        ;

        $this->registerDefaultSubscribers($escargot);
        $this->registerSubscribers($escargot, $this->validateSubscribers($selectedSubscribers));

        return $escargot;
    }

    private function createHttpClient(array $options = []): HttpClientInterface
    {
        return HttpClient::create(
            array_merge_recursive(
                [
                    'headers' => ['accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'],
                    'max_duration' => 10, // Ignore requests that take longer than 10 seconds
                ],
                array_merge_recursive($this->getDefaultHttpClientOptions(), $options)
            )
        );
    }

    private function registerDefaultSubscribers(Escargot $escargot): void
    {
        $escargot->addSubscriber(new RobotsSubscriber());
        $escargot->addSubscriber(new HtmlCrawlerSubscriber());
    }

    private function registerSubscribers(Escargot $escargot, array $selectedSubscribers): void
    {
        foreach ($this->subscribers as $subscriber) {
            if (\in_array($subscriber->getName(), $selectedSubscribers, true)) {
                $escargot->addSubscriber($subscriber);
            }
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function validateSubscribers(array $selectedSubscribers): array
    {
        $msg = sprintf(
            'You have to specify at least one valid subscriber name. Valid subscribers are: %s',
            implode(', ', $this->getSubscriberNames())
        );

        if (0 === \count($selectedSubscribers)) {
            throw new \InvalidArgumentException($msg);
        }

        $selectedSubscribers = array_intersect($this->getSubscriberNames(), $selectedSubscribers);

        if (0 === \count($selectedSubscribers)) {
            throw new \InvalidArgumentException($msg);
        }

        return $selectedSubscribers;
    }
}
