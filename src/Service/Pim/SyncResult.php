<?php

declare(strict_types=1);

namespace App\Service\Pim;

/**
 * DTO for sync operation results.
 */
class SyncResult
{
    private int $created = 0;
    private int $updated = 0;
    private int $skipped = 0;
    private int $deleted = 0;

    /** @var array<string> */
    private array $errors = [];

    public function __construct(
        int $created = 0,
        int $updated = 0,
        int $skipped = 0,
        int $deleted = 0,
        array $errors = [],
    ) {
        $this->created = $created;
        $this->updated = $updated;
        $this->skipped = $skipped;
        $this->deleted = $deleted;
        $this->errors = $errors;
    }

    public function addCreated(): void
    {
        $this->created++;
    }

    public function addUpdated(): void
    {
        $this->updated++;
    }

    public function addSkipped(): void
    {
        $this->skipped++;
    }

    public function addDeleted(): void
    {
        $this->deleted++;
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function getCreated(): int
    {
        return $this->created;
    }

    public function getUpdated(): int
    {
        return $this->updated;
    }

    public function getSkipped(): int
    {
        return $this->skipped;
    }

    public function getDeleted(): int
    {
        return $this->deleted;
    }

    public function getTotal(): int
    {
        return $this->created + $this->updated + $this->skipped;
    }

    /**
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function merge(SyncResult $other): void
    {
        $this->created += $other->getCreated();
        $this->updated += $other->getUpdated();
        $this->skipped += $other->getSkipped();
        $this->deleted += $other->getDeleted();
        $this->errors = array_merge($this->errors, $other->getErrors());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'deleted' => $this->deleted,
            'total' => $this->getTotal(),
            'errors' => $this->errors,
            'hasErrors' => $this->hasErrors(),
        ];
    }
}
