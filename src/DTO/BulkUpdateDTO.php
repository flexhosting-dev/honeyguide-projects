<?php

namespace App\DTO;

class BulkUpdateDTO
{
    /**
     * @var string[]
     */
    public array $taskIds = [];

    public ?string $status = null;

    public ?string $priority = null;

    public ?string $milestone = null;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->taskIds = $data['taskIds'] ?? [];
        $dto->status = $data['updates']['status'] ?? null;
        $dto->priority = $data['updates']['priority'] ?? null;
        $dto->milestone = $data['updates']['milestone'] ?? null;

        return $dto;
    }

    public function hasUpdates(): bool
    {
        return $this->status !== null || $this->priority !== null || $this->milestone !== null;
    }
}
