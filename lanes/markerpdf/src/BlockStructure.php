<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class BlockStructure
{
    /**
     * Native boundary for marker.schema.block::bbox_from_lines.
     *
     * @param list<array<string, mixed>> $lines
     * @return list<float>
     */
    public function bboxFromLines(array $lines): array
    {
        $boxes = [];
        foreach ($lines as $line) {
            $bbox = $this->bbox($line['bbox'] ?? null);
            if ($bbox === null) {
                continue;
            }
            $boxes[] = $bbox;
        }

        if ($boxes === []) {
            throw new InvalidArgumentException('bbox_from_lines requires at least one line bbox.');
        }

        return [
            min(array_column($boxes, 0)),
            min(array_column($boxes, 1)),
            max(array_column($boxes, 2)),
            max(array_column($boxes, 3)),
        ];
    }

    /**
     * Native boundary for marker.schema.block::split_block_lines.
     *
     * @param array<string, mixed> $block
     * @return list<array<string, mixed>>
     */
    public function splitBlockLines(array $block, int $splitLineIndex): array
    {
        $lines = array_values(array_filter(
            $block['lines'] ?? [],
            static fn (mixed $line): bool => is_array($line)
        ));

        if ($splitLineIndex >= count($lines) || $splitLineIndex === 0) {
            return [$block];
        }
        if ($splitLineIndex < 0) {
            throw new InvalidArgumentException('split_block_lines expects a non-negative split index.');
        }

        $firstLines = array_slice($lines, 0, $splitLineIndex);
        $secondLines = array_slice($lines, $splitLineIndex);
        $pnum = (int) ($block['pnum'] ?? 0);

        return [
            [
                'lines' => $firstLines,
                'bbox' => $this->bboxFromLines($firstLines),
                'pnum' => $pnum,
            ],
            [
                'lines' => $secondLines,
                'bbox' => $this->bboxFromLines($secondLines),
                'pnum' => $pnum,
            ],
        ];
    }

    /**
     * Native boundary for marker.schema.block.Block::get_min_line_start.
     *
     * @param array<string, mixed> $block
     */
    public function getMinLineStart(array $block): ?float
    {
        $starts = [];
        foreach (($block['lines'] ?? []) as $line) {
            if (!is_array($line)) {
                continue;
            }
            $spans = $line['spans'] ?? [];
            if (!is_array($spans) || $spans === [] || !is_array($spans[0] ?? null)) {
                continue;
            }

            $bbox = $this->bbox($spans[0]['bbox'] ?? null);
            if ($bbox !== null) {
                $starts[] = $bbox[0];
            }
        }

        return $starts === [] ? null : min($starts);
    }

    /**
     * @return list<float>|null
     */
    private function bbox(mixed $value): ?array
    {
        if (!is_array($value) || count($value) !== 4) {
            return null;
        }

        $bbox = [];
        foreach (array_values($value) as $part) {
            if (!is_int($part) && !is_float($part)) {
                return null;
            }
            $bbox[] = (float) $part;
        }

        return $bbox;
    }
}
