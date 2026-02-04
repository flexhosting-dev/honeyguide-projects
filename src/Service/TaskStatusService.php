<?php

namespace App\Service;

use App\Entity\TaskStatusType;
use App\Repository\TaskStatusTypeRepository;

class TaskStatusService
{
    public function __construct(
        private readonly TaskStatusTypeRepository $statusRepository,
    ) {
    }

    /**
     * @return TaskStatusType[]
     */
    public function getAllStatuses(): array
    {
        return $this->statusRepository->findAllOrdered();
    }

    /**
     * @return array{open: TaskStatusType[], closed: TaskStatusType[]}
     */
    public function getStatusesGroupedByType(): array
    {
        return $this->statusRepository->findGroupedByType();
    }

    public function findBySlug(string $slug): ?TaskStatusType
    {
        return $this->statusRepository->findBySlug($slug);
    }

    public function getDefaultOpenStatus(): ?TaskStatusType
    {
        return $this->statusRepository->findDefaultOpen();
    }

    public function getDefaultClosedStatus(): ?TaskStatusType
    {
        return $this->statusRepository->findDefaultClosed();
    }

    /**
     * Returns status data formatted for frontend (JSON/Vue props).
     *
     * @return array<int, array{
     *     id: string,
     *     slug: string,
     *     name: string,
     *     label: string,
     *     value: string,
     *     parentType: string,
     *     color: string,
     *     isOpen: bool,
     *     isClosed: bool,
     *     sortOrder: int,
     *     isDefault: bool,
     *     isSystem: bool
     * }>
     */
    public function getStatusesForFrontend(): array
    {
        $statuses = $this->getAllStatuses();
        $result = [];

        foreach ($statuses as $status) {
            $result[] = [
                'id' => $status->getId()->toString(),
                'slug' => $status->getSlug(),
                'name' => $status->getName(),
                'label' => $status->getName(),
                'value' => $status->getSlug(),
                'parentType' => $status->getParentType(),
                'color' => $status->getColor(),
                'isOpen' => $status->isOpen(),
                'isClosed' => $status->isClosed(),
                'sortOrder' => $status->getSortOrder(),
                'isDefault' => $status->isDefault(),
                'isSystem' => $status->isSystem(),
            ];
        }

        return $result;
    }

    /**
     * Returns status columns data formatted for KanbanBoard Vue component.
     *
     * @return array<int, array{
     *     value: string,
     *     label: string,
     *     color: string,
     *     badgeClass: string,
     *     bgClass: string,
     *     parentType: string
     * }>
     */
    public function getStatusColumnsForKanban(): array
    {
        $statuses = $this->getAllStatuses();
        $result = [];

        foreach ($statuses as $status) {
            $result[] = [
                'value' => $status->getSlug(),
                'label' => $status->getName(),
                'color' => $status->getColor(),
                'badgeClass' => $this->getBadgeClassForColor($status->getColor()),
                'bgClass' => $this->getBgClassForColor($status->getColor()),
                'parentType' => $status->getParentType(),
            ];
        }

        return $result;
    }

    private function getBadgeClassForColor(string $color): string
    {
        // Map colors to badge classes using CSS custom properties approach
        return 'kb-badge-custom';
    }

    private function getBgClassForColor(string $color): string
    {
        return 'kb-bg-custom';
    }

    /**
     * Find statuses by their slugs.
     *
     * @param string[] $slugs
     * @return TaskStatusType[]
     */
    public function findBySlugs(array $slugs): array
    {
        if (empty($slugs)) {
            return [];
        }

        $statuses = [];
        foreach ($slugs as $slug) {
            $status = $this->findBySlug($slug);
            if ($status !== null) {
                $statuses[] = $status;
            }
        }

        return $statuses;
    }

    public function countTasksWithStatus(TaskStatusType $status): int
    {
        return $this->statusRepository->countTasksWithStatus($status);
    }

    public function getMaxSortOrder(): int
    {
        return $this->statusRepository->getMaxSortOrder();
    }
}
