<?php

namespace App\DataFixtures;

use App\Entity\TaskStatusType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class TaskStatusTypeFixtures extends Fixture implements FixtureGroupInterface
{
    public const STATUS_TODO = 'status-todo';
    public const STATUS_IN_PROGRESS = 'status-in-progress';
    public const STATUS_COMPLETED = 'status-completed';

    public static function getGroups(): array
    {
        return ['statuses', 'default'];
    }

    public function load(ObjectManager $manager): void
    {
        // To Do - Default status for new tasks (grey)
        $todo = new TaskStatusType();
        $todo->setName('To Do');
        $todo->setSlug('todo');
        $todo->setParentType(TaskStatusType::PARENT_TYPE_OPEN);
        $todo->setColor('#6B7280');
        $todo->setIcon('circle');
        $todo->setSortOrder(0);
        $todo->setIsDefault(true);
        $todo->setIsSystem(true);
        $manager->persist($todo);
        $this->addReference(self::STATUS_TODO, $todo);

        // In Progress
        $inProgress = new TaskStatusType();
        $inProgress->setName('In Progress');
        $inProgress->setSlug('in_progress');
        $inProgress->setParentType(TaskStatusType::PARENT_TYPE_OPEN);
        $inProgress->setColor('#3B82F6');
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

        $manager->flush();
    }
}
