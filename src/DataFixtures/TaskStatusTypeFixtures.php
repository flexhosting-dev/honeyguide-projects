<?php

namespace App\DataFixtures;

use App\Entity\TaskStatusType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class TaskStatusTypeFixtures extends Fixture implements FixtureGroupInterface
{
    public const STATUS_OPEN = 'status-open';
    public const STATUS_IN_PROGRESS = 'status-in-progress';
    public const STATUS_COMPLETED = 'status-completed';
    public const STATUS_CANCELLED = 'status-cancelled';

    public static function getGroups(): array
    {
        return ['statuses', 'default'];
    }

    public function load(ObjectManager $manager): void
    {
        // Open - Default status for new tasks
        $open = new TaskStatusType();
        $open->setName('Open');
        $open->setSlug('open');
        $open->setParentType(TaskStatusType::PARENT_TYPE_OPEN);
        $open->setColor('#3B82F6');
        $open->setIcon('circle');
        $open->setSortOrder(0);
        $open->setIsDefault(true);
        $open->setIsSystem(true);
        $manager->persist($open);
        $this->addReference(self::STATUS_OPEN, $open);

        // In Progress
        $inProgress = new TaskStatusType();
        $inProgress->setName('In Progress');
        $inProgress->setSlug('in_progress');
        $inProgress->setParentType(TaskStatusType::PARENT_TYPE_OPEN);
        $inProgress->setColor('#F59E0B');
        $inProgress->setIcon('clock');
        $inProgress->setSortOrder(1);
        $inProgress->setIsDefault(false);
        $inProgress->setIsSystem(true);
        $manager->persist($inProgress);
        $this->addReference(self::STATUS_IN_PROGRESS, $inProgress);

        // Completed
        $completed = new TaskStatusType();
        $completed->setName('Completed');
        $completed->setSlug('completed');
        $completed->setParentType(TaskStatusType::PARENT_TYPE_CLOSED);
        $completed->setColor('#10B981');
        $completed->setIcon('check-circle');
        $completed->setSortOrder(2);
        $completed->setIsDefault(false);
        $completed->setIsSystem(true);
        $manager->persist($completed);
        $this->addReference(self::STATUS_COMPLETED, $completed);

        // Cancelled
        $cancelled = new TaskStatusType();
        $cancelled->setName('Cancelled');
        $cancelled->setSlug('cancelled');
        $cancelled->setParentType(TaskStatusType::PARENT_TYPE_CLOSED);
        $cancelled->setColor('#EF4444');
        $cancelled->setIcon('x-circle');
        $cancelled->setSortOrder(3);
        $cancelled->setIsDefault(false);
        $cancelled->setIsSystem(true);
        $manager->persist($cancelled);
        $this->addReference(self::STATUS_CANCELLED, $cancelled);

        $manager->flush();
    }
}
