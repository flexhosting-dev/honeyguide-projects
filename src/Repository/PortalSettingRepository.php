<?php

namespace App\Repository;

use App\Entity\PortalSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PortalSetting>
 */
class PortalSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PortalSetting::class);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $setting = $this->findOneBy(['settingKey' => $key]);
        return $setting?->getValue() ?? $default;
    }

    public function set(string $key, ?string $value): PortalSetting
    {
        $setting = $this->findOneBy(['settingKey' => $key]);

        if (!$setting) {
            $setting = new PortalSetting();
            $setting->setSettingKey($key);
        }

        $setting->setValue($value);

        $this->getEntityManager()->persist($setting);
        $this->getEntityManager()->flush();

        return $setting;
    }
}
