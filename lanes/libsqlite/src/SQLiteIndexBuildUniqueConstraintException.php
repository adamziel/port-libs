<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteIndexBuildUniqueConstraintException extends \RuntimeException
{
    /**
     * @param array<string,mixed> $plan
     */
    public function __construct(string $message, private readonly array $plan)
    {
        parent::__construct($message);
    }

    /**
     * @return array<string,mixed>
     */
    public function plan(): array
    {
        return $this->plan;
    }
}
