<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

use InvalidArgumentException;
use OutOfBoundsException;

final class SourceMap
{
    private const BASE64 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';

    /** @var list<string> */
    private array $sources = [];

    /** @var array<string, int> */
    private array $sourceIndexes = [];

    /** @var array<int, string> */
    private array $sourcesContent = [];

    /** @var list<string> */
    private array $names = [];

    /** @var array<string, int> */
    private array $nameIndexes = [];

    /**
     * @var list<array{
     *     generatedLine:int,
     *     generatedColumn:int,
     *     sourceIndex:int,
     *     originalLine:int,
     *     originalColumn:int,
     *     nameIndex:int|null,
     *     order:int
     * }>
     */
    private array $mappings = [];

    public function addSource(string $source): int
    {
        if (isset($this->sourceIndexes[$source])) {
            return $this->sourceIndexes[$source];
        }

        $index = count($this->sources);
        $this->sources[] = $source;
        $this->sourceIndexes[$source] = $index;

        return $index;
    }

    public function setSourceContent(int $sourceIndex, string $content): void
    {
        $this->assertSourceIndex($sourceIndex);
        $this->sourcesContent[$sourceIndex] = $content;
    }

    public function addName(string $name): int
    {
        if (isset($this->nameIndexes[$name])) {
            return $this->nameIndexes[$name];
        }

        $index = count($this->names);
        $this->names[] = $name;
        $this->nameIndexes[$name] = $index;

        return $index;
    }

    public function addMapping(
        int $generatedLine,
        int $generatedColumn,
        int $sourceIndex,
        int $originalLine,
        int $originalColumn,
        ?string $name = null
    ): void {
        $this->assertNonNegative($generatedLine, 'generated line');
        $this->assertNonNegative($generatedColumn, 'generated column');
        $this->assertSourceIndex($sourceIndex);
        $this->assertNonNegative($originalLine, 'original line');
        $this->assertNonNegative($originalColumn, 'original column');

        $this->mappings[] = [
            'generatedLine' => $generatedLine,
            'generatedColumn' => $generatedColumn,
            'sourceIndex' => $sourceIndex,
            'originalLine' => $originalLine,
            'originalColumn' => $originalColumn,
            'nameIndex' => $name === null ? null : $this->addName($name),
            'order' => count($this->mappings),
        ];
    }

    public function addPrinterMapping(
        int $generatedLine,
        int $generatedColumn,
        int $sourceIndex,
        int $sourceLine,
        int $sourceColumnOneBased,
        ?string $name = null
    ): void {
        if ($sourceColumnOneBased < 1) {
            throw new InvalidArgumentException('Source column must be one-based.');
        }

        $this->addMapping($generatedLine, $generatedColumn, $sourceIndex, $sourceLine, $sourceColumnOneBased - 1, $name);
    }

    public function writeVlq(): string
    {
        if ($this->mappings === []) {
            return '';
        }

        $mappings = $this->mappings;
        usort(
            $mappings,
            static fn (array $a, array $b): int => [$a['generatedLine'], $a['generatedColumn'], $a['order']]
                <=> [$b['generatedLine'], $b['generatedColumn'], $b['order']]
        );

        $byLine = [];
        $maxLine = 0;
        foreach ($mappings as $mapping) {
            $byLine[$mapping['generatedLine']][] = $mapping;
            $maxLine = max($maxLine, $mapping['generatedLine']);
        }

        $output = '';
        $previousSource = 0;
        $previousOriginalLine = 0;
        $previousOriginalColumn = 0;
        $previousName = 0;

        for ($line = 0; $line <= $maxLine; $line++) {
            if ($line > 0) {
                $output .= ';';
            }

            $previousGeneratedColumn = 0;
            $segments = $byLine[$line] ?? [];
            foreach ($segments as $index => $mapping) {
                if ($index > 0) {
                    $output .= ',';
                }

                $output .= self::encodeVlq($mapping['generatedColumn'] - $previousGeneratedColumn);
                $previousGeneratedColumn = $mapping['generatedColumn'];

                $output .= self::encodeVlq($mapping['sourceIndex'] - $previousSource);
                $previousSource = $mapping['sourceIndex'];

                $output .= self::encodeVlq($mapping['originalLine'] - $previousOriginalLine);
                $previousOriginalLine = $mapping['originalLine'];

                $output .= self::encodeVlq($mapping['originalColumn'] - $previousOriginalColumn);
                $previousOriginalColumn = $mapping['originalColumn'];

                if ($mapping['nameIndex'] !== null) {
                    $output .= self::encodeVlq($mapping['nameIndex'] - $previousName);
                    $previousName = $mapping['nameIndex'];
                }
            }
        }

        return $output;
    }

    /**
     * @return array{version:int,sourceRoot?:string|null,mappings:string,sources:list<string>,sourcesContent:list<string|null>,names:list<string>}
     */
    public function toArray(?string $sourceRoot = null, bool $includeSourceRoot = true): array
    {
        $data = [
            'version' => 3,
            'mappings' => $this->writeVlq(),
            'sources' => $this->sources,
            'sourcesContent' => $this->sourceContentsForJson(),
            'names' => $this->names,
        ];

        if ($includeSourceRoot) {
            $data = ['version' => 3, 'sourceRoot' => $sourceRoot] + array_slice($data, 1, null, true);
        }

        return $data;
    }

    public function toJson(?string $sourceRoot = null, bool $includeSourceRoot = true): string
    {
        return json_encode($this->toArray($sourceRoot, $includeSourceRoot), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<array{generatedLine:int,generatedColumn:int,sourceIndex:int|null,originalLine:int|null,originalColumn:int|null,nameIndex:int|null}>
     */
    public static function decodeVlq(string $mappings): array
    {
        $decoded = [];
        $previousSource = 0;
        $previousOriginalLine = 0;
        $previousOriginalColumn = 0;
        $previousName = 0;

        foreach (explode(';', $mappings) as $generatedLine => $line) {
            if ($line === '') {
                continue;
            }

            $generatedColumn = 0;
            foreach (explode(',', $line) as $segment) {
                if ($segment === '') {
                    continue;
                }

                $values = self::decodeVlqSegment($segment);
                if ($values === [] || count($values) === 2 || count($values) === 3 || count($values) > 5) {
                    throw new InvalidArgumentException('Invalid source map segment: ' . $segment);
                }

                $generatedColumn += $values[0];
                $entry = [
                    'generatedLine' => $generatedLine,
                    'generatedColumn' => $generatedColumn,
                    'sourceIndex' => null,
                    'originalLine' => null,
                    'originalColumn' => null,
                    'nameIndex' => null,
                ];

                if (count($values) >= 4) {
                    $previousSource += $values[1];
                    $previousOriginalLine += $values[2];
                    $previousOriginalColumn += $values[3];
                    $entry['sourceIndex'] = $previousSource;
                    $entry['originalLine'] = $previousOriginalLine;
                    $entry['originalColumn'] = $previousOriginalColumn;
                }

                if (count($values) === 5) {
                    $previousName += $values[4];
                    $entry['nameIndex'] = $previousName;
                }

                $decoded[] = $entry;
            }
        }

        return $decoded;
    }

    private static function encodeVlq(int $value): string
    {
        $vlq = $value < 0 ? ((-$value) << 1) + 1 : $value << 1;
        $encoded = '';

        do {
            $digit = $vlq & 31;
            $vlq >>= 5;
            if ($vlq > 0) {
                $digit |= 32;
            }
            $encoded .= self::BASE64[$digit];
        } while ($vlq > 0);

        return $encoded;
    }

    /**
     * @return list<int>
     */
    private static function decodeVlqSegment(string $segment): array
    {
        $values = [];
        $value = 0;
        $shift = 0;
        $length = strlen($segment);

        for ($i = 0; $i < $length; $i++) {
            $digit = strpos(self::BASE64, $segment[$i]);
            if ($digit === false) {
                throw new InvalidArgumentException('Invalid base64 VLQ character: ' . $segment[$i]);
            }

            $continuation = ($digit & 32) !== 0;
            $digit &= 31;
            $value += $digit << $shift;

            if ($continuation) {
                $shift += 5;
                continue;
            }

            $negative = ($value & 1) === 1;
            $decoded = $value >> 1;
            $values[] = $negative ? -$decoded : $decoded;
            $value = 0;
            $shift = 0;
        }

        if ($shift !== 0) {
            throw new InvalidArgumentException('Unterminated base64 VLQ segment: ' . $segment);
        }

        return $values;
    }

    private function assertSourceIndex(int $sourceIndex): void
    {
        if (!array_key_exists($sourceIndex, $this->sources)) {
            throw new OutOfBoundsException('Unknown source index: ' . $sourceIndex);
        }
    }

    private function assertNonNegative(int $value, string $label): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException(ucfirst($label) . ' must be non-negative.');
        }
    }

    /**
     * @return list<string|null>
     */
    private function sourceContentsForJson(): array
    {
        $contents = [];
        $count = count($this->sources);
        for ($i = 0; $i < $count; $i++) {
            $contents[] = $this->sourcesContent[$i] ?? null;
        }

        return $contents;
    }
}
