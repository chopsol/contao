<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Repository;

use Contao\CoreBundle\Entity\CronJob;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @internal
 *
 * @method object|null findOneByName(string $name)
 */
class CronJobRepository extends ServiceEntityRepository
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CronJob::class);

        $this->connection = $registry->getConnection();
    }

    public function lockTable(): void
    {
        $table = $this->getClassMetadata()->getTableName();

        $this->connection->exec("LOCK TABLES $table WRITE, $table AS t0 WRITE, $table AS t0_ WRITE");
    }

    public function unlockTable(): void
    {
        $this->connection->exec('UNLOCK TABLES');
    }
}
