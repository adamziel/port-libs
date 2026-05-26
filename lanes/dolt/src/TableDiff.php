<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class TableDiff
{
    /**
     * @param list<array<string, scalar|null>> $oldRows
     * @param list<array<string, scalar|null>> $newRows
     * @return array{added:list<array<string, scalar|null>>, removed:list<array<string, scalar|null>>, modified:list<array{old:array<string, scalar|null>, new:array<string, scalar|null>}>}
     */
    public function diff(array $oldRows, array $newRows, string $primaryKey): array
    {
        $old = $this->index($oldRows, $primaryKey);
        $new = $this->index($newRows, $primaryKey);
        $added = [];
        $removed = [];
        $modified = [];

        foreach ($new as $key => $row) {
            if (!array_key_exists($key, $old)) {
                $added[] = $row;
            } elseif ($old[$key] !== $row) {
                $modified[] = ['old' => $old[$key], 'new' => $row];
            }
        }
        foreach ($old as $key => $row) {
            if (!array_key_exists($key, $new)) {
                $removed[] = $row;
            }
        }

        return ['added' => $added, 'removed' => $removed, 'modified' => $modified];
    }

    /**
     * @param list<array<string, scalar|null>> $rows
     * @return array<string, array<string, scalar|null>>
     */
    private function index(array $rows, string $primaryKey): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            if (!array_key_exists($primaryKey, $row)) {
                throw new \InvalidArgumentException("Row is missing primary key: {$primaryKey}");
            }
            $indexed[(string) $row[$primaryKey]] = $row;
        }

        return $indexed;
    }
}

