<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Exception;

class DuplicateAliasException extends \RuntimeException
{
    /**
     * @var string
     */
    private $url;

    public function __construct(string $url, string $message = '', int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->url = $url;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
