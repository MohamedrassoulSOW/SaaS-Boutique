<?php

namespace App\EntityListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;

/**
 * Sanitize free-text fields before persist to prevent stored XSS.
 */
#[AsEntityListener(event: Events::prePersist, entity: \App\Entity\Product::class)]
#[AsEntityListener(event: Events::preUpdate, entity: \App\Entity\Product::class)]
#[AsEntityListener(event: Events::prePersist, entity: \App\Entity\Customer::class)]
#[AsEntityListener(event: Events::preUpdate, entity: \App\Entity\Customer::class)]
#[AsEntityListener(event: Events::prePersist, entity: \App\Entity\Supplier::class)]
#[AsEntityListener(event: Events::preUpdate, entity: \App\Entity\Supplier::class)]
#[AsEntityListener(event: Events::prePersist, entity: \App\Entity\Category::class)]
#[AsEntityListener(event: Events::preUpdate, entity: \App\Entity\Category::class)]
#[AsEntityListener(event: Events::prePersist, entity: \App\Entity\Merchant::class)]
#[AsEntityListener(event: Events::preUpdate, entity: \App\Entity\Merchant::class)]
#[AsEntityListener(event: Events::prePersist, entity: \App\Entity\User::class)]
#[AsEntityListener(event: Events::preUpdate, entity: \App\Entity\User::class)]
class SanitizeInputListener
{
    private const STRIP_FIELDS = [
        \App\Entity\Product::class => ['name', 'reference', 'description'],
        \App\Entity\Customer::class => ['firstName', 'lastName', 'address', 'notes'],
        \App\Entity\Supplier::class => ['name', 'address', 'contact'],
        \App\Entity\Category::class => ['name'],
        \App\Entity\Merchant::class => ['companyName', 'legalForm', 'registrationNumber', 'representativeTitle', 'address', 'city', 'country'],
        \App\Entity\User::class => ['firstName', 'lastName'],
    ];

    public function prePersist(object $entity, \Doctrine\Persistence\Event\LifecycleEventArgs $event): void
    {
        $this->sanitize($entity);
    }

    public function preUpdate(object $entity, \Doctrine\Persistence\Event\LifecycleEventArgs $event): void
    {
        $this->sanitize($entity);
    }

    private function sanitize(object $entity): void
    {
        $class = get_class($entity);
        $fields = self::STRIP_FIELDS[$class] ?? [];

        foreach ($fields as $field) {
            $getter = 'get'.ucfirst($field);
            $setter = 'set'.ucfirst($field);

            if (method_exists($entity, $getter) && method_exists($entity, $setter)) {
                $value = $entity->$getter();
                if (\is_string($value)) {
                    $entity->$setter(strip_tags($value));
                }
            }
        }
    }
}
