<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Cron;

class CronJob
{
    /**
     * @var object
     */
    private $service;

    /**
     * @var string
     */
    private $method;

    /**
     * @var string
     */
    private $interval;

    /**
     * @var string
     */
    private $name;

    public function __construct(object $service, string $interval, string $method = null)
    {
        $this->service = $service;
        $this->method = $method;
        $this->interval = $interval;
        $this->name = \get_class($service);

        if (!\is_callable($service)) {
            if (null === $this->method) {
                throw new \InvalidArgumentException('Service must be a callable when no method name is defined');
            }

            $this->name .= '::'.$method;
        }
    }

    public function __invoke(string $scope): void
    {
        if (\is_callable($this->service)) {
            ($this->service)($scope);
        } else {
            $this->service->{$this->method}($scope);
        }
    }

    public function getService(): object
    {
        return $this->service;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getInterval(): string
    {
        return $this->interval;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
