<?php

namespace App\DTO;

use App\Entity\TaskStatusType;
use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use Symfony\Component\HttpFoundation\Request;

class TaskFilterDTO
{
    /**
     * @param TaskStatusType[] $statuses Status types for filtering
     * @param string[] $statusSlugs Status slugs for filtering (used when status types aren't loaded)
     * @param TaskPriority[] $priorities
     * @param string[] $assigneeIds
     * @param string[] $milestoneIds
     * @param string[] $projectIds
     */
    public function __construct(
        public readonly array $statuses = [],
        public readonly array $statusSlugs = [],
        public readonly array $priorities = [],
        public readonly array $assigneeIds = [],
        public readonly array $milestoneIds = [],
        public readonly ?string $dueFilter = null,
        public readonly ?string $dueDateFrom = null,
        public readonly ?string $dueDateTo = null,
        public readonly ?string $search = null,
        public readonly array $projectIds = [],
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        // Parse status slugs from request
        $statusSlugs = [];
        $statusParam = $request->query->get('status', '');
        if ($statusParam) {
            $statusSlugs = array_filter(array_map('trim', explode(',', $statusParam)));
        }

        $priorities = [];
        $priorityParam = $request->query->get('priority', '');
        if ($priorityParam) {
            $priorityValues = explode(',', $priorityParam);
            foreach ($priorityValues as $value) {
                $priority = TaskPriority::tryFrom($value);
                if ($priority) {
                    $priorities[] = $priority;
                }
            }
        }

        $assigneeIds = [];
        $assigneeParam = $request->query->get('assignee', '');
        if ($assigneeParam) {
            $assigneeIds = array_filter(explode(',', $assigneeParam));
        }

        $milestoneIds = [];
        $milestoneParam = $request->query->get('milestone', '');
        if ($milestoneParam) {
            $milestoneIds = array_filter(explode(',', $milestoneParam));
        }

        $projectIds = [];
        $projectParam = $request->query->get('project', '');
        if ($projectParam) {
            $projectIds = array_filter(explode(',', $projectParam));
        }

        $dueFilter = $request->query->get('due');
        $dueDateFrom = null;
        $dueDateTo = null;

        if ($dueFilter === 'custom') {
            $dueDateFrom = $request->query->get('due_from');
            $dueDateTo = $request->query->get('due_to');
        }

        $search = $request->query->get('search');

        return new self(
            statuses: [],
            statusSlugs: $statusSlugs,
            priorities: $priorities,
            assigneeIds: $assigneeIds,
            milestoneIds: $milestoneIds,
            dueFilter: $dueFilter,
            dueDateFrom: $dueDateFrom,
            dueDateTo: $dueDateTo,
            search: $search ?: null,
            projectIds: $projectIds,
        );
    }

    public function hasActiveFilters(): bool
    {
        return !empty($this->statuses)
            || !empty($this->statusSlugs)
            || !empty($this->priorities)
            || !empty($this->assigneeIds)
            || !empty($this->milestoneIds)
            || $this->dueFilter !== null
            || $this->search !== null
            || !empty($this->projectIds);
    }

    public function getActiveFilterCount(): int
    {
        $count = 0;
        if (!empty($this->statuses) || !empty($this->statusSlugs)) $count++;
        if (!empty($this->priorities)) $count++;
        if (!empty($this->assigneeIds)) $count++;
        if (!empty($this->milestoneIds)) $count++;
        if ($this->dueFilter !== null) $count++;
        if ($this->search !== null) $count++;
        if (!empty($this->projectIds)) $count++;
        return $count;
    }

    /**
     * Get the effective status slugs (from either statuses or statusSlugs).
     *
     * @return string[]
     */
    public function getEffectiveStatusSlugs(): array
    {
        if (!empty($this->statuses)) {
            return array_map(fn(TaskStatusType $s) => $s->getSlug(), $this->statuses);
        }
        return $this->statusSlugs;
    }

    public function toQueryParams(): array
    {
        $params = [];

        $statusSlugs = $this->getEffectiveStatusSlugs();
        if (!empty($statusSlugs)) {
            $params['status'] = implode(',', $statusSlugs);
        }
        if (!empty($this->priorities)) {
            $params['priority'] = implode(',', array_map(fn($p) => $p->value, $this->priorities));
        }
        if (!empty($this->assigneeIds)) {
            $params['assignee'] = implode(',', $this->assigneeIds);
        }
        if (!empty($this->milestoneIds)) {
            $params['milestone'] = implode(',', $this->milestoneIds);
        }
        if ($this->dueFilter) {
            $params['due'] = $this->dueFilter;
            if ($this->dueFilter === 'custom') {
                if ($this->dueDateFrom) $params['due_from'] = $this->dueDateFrom;
                if ($this->dueDateTo) $params['due_to'] = $this->dueDateTo;
            }
        }
        if ($this->search) {
            $params['search'] = $this->search;
        }
        if (!empty($this->projectIds)) {
            $params['project'] = implode(',', $this->projectIds);
        }

        return $params;
    }
}
