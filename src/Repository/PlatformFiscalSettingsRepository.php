<?php

namespace App\Repository;

use App\Entity\PlatformFiscalSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlatformFiscalSettings>
 */
class PlatformFiscalSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlatformFiscalSettings::class);
    }
}
