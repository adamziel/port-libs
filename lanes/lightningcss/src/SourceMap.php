<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

use InvalidArgumentException;
use OutOfBoundsException;

final class SourceMap
{
    private const BASE64 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
    private const MAX_UNSIGNED_32 = 4294967295;

    private string $projectRoot;

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
     *     sourceIndex:int|null,
     *     originalLine:int|null,
     *     originalColumn:int|null,
     *     nameIndex:int|null,
     *     order:int
     * }>
     */
    private array $mappings = [];

    private int $generatedLineCount = 0;

    public function __construct(string $projectRoot = '/')
    {
        $this->projectRoot = $projectRoot;
    }

    public function addSource(string $source): int
    {
        $source = self::makeRelativePath($this->projectRoot, $source);
        if (isset($this->sourceIndexes[$source])) {
            return $this->sourceIndexes[$source];
        }

        $index = count($this->sources);
        $this->sources[] = $source;
        $this->sourceIndexes[$source] = $index;

        return $index;
    }

    /**
     * @param list<string> $sources
     * @return list<int>
     */
    public function addSources(array $sources): array
    {
        $indexes = [];
        foreach ($sources as $source) {
            if (!is_string($source)) {
                throw new InvalidArgumentException('Source map sources must be strings.');
            }

            $indexes[] = $this->addSource($source);
        }

        return $indexes;
    }

    public function getSourceIndex(string $source): ?int
    {
        $source = self::makeRelativePath($this->projectRoot, $source);

        return $this->sourceIndexes[$source] ?? null;
    }

    public function getSource(int $sourceIndex): string
    {
        if (!array_key_exists($sourceIndex, $this->sources)) {
            throw new OutOfBoundsException('Unknown source index: ' . $sourceIndex);
        }

        return $this->sources[$sourceIndex];
    }

    /**
     * @return list<string>
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    public function setSourceContent(int $sourceIndex, string $content): void
    {
        $this->assertSourceIndex($sourceIndex);
        for ($i = count($this->sourcesContent); $i < $sourceIndex; $i++) {
            $this->sourcesContent[$i] = '';
        }

        $this->sourcesContent[$sourceIndex] = $content;
    }

    public function getSourceContent(int $sourceIndex): string
    {
        if (!array_key_exists($sourceIndex, $this->sourcesContent)) {
            throw new OutOfBoundsException('Unknown source content index: ' . $sourceIndex);
        }

        return $this->sourcesContent[$sourceIndex];
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

    /**
     * @param list<string> $names
     * @return list<int>
     */
    public function addNames(array $names): array
    {
        $indexes = [];
        foreach ($names as $name) {
            if (!is_string($name)) {
                throw new InvalidArgumentException('Source map names must be strings.');
            }

            $indexes[] = $this->addName($name);
        }

        return $indexes;
    }

    public function getNameIndex(string $name): ?int
    {
        return $this->nameIndexes[$name] ?? null;
    }

    public function getName(int $nameIndex): string
    {
        if (!array_key_exists($nameIndex, $this->names)) {
            throw new OutOfBoundsException('Unknown name index: ' . $nameIndex);
        }

        return $this->names[$nameIndex];
    }

    /**
     * @return list<string>
     */
    public function getNames(): array
    {
        return $this->names;
    }

    public function addMapping(
        int $generatedLine,
        int $generatedColumn,
        int $sourceIndex,
        int $originalLine,
        int $originalColumn,
        ?string $name = null
    ): void {
        $this->addRawMapping($generatedLine, $generatedColumn, $sourceIndex, $originalLine, $originalColumn, $name);
    }

    public function addGeneratedMapping(int $generatedLine, int $generatedColumn): void
    {
        $this->addRawMapping($generatedLine, $generatedColumn, null, null, null, null);
    }

    public function addMappingWithOffset(
        int $generatedLine,
        int $generatedColumn,
        int $sourceIndex,
        int $originalLine,
        int $originalColumn,
        int $lineOffset,
        int $columnOffset,
        ?string $name = null
    ): void {
        $this->assertNonNegative($generatedLine, 'generated line');
        $this->assertNonNegative($generatedColumn, 'generated column');

        $offsetLine = $this->offsetNonNegative($generatedLine, $lineOffset, 'generated line + line offset');
        $offsetColumn = $this->offsetNonNegative($generatedColumn, $columnOffset, 'generated column + column offset');

        $this->addMapping($offsetLine, $offsetColumn, $sourceIndex, $originalLine, $originalColumn, $name);
    }

    public function addGeneratedMappingWithOffset(
        int $generatedLine,
        int $generatedColumn,
        int $lineOffset,
        int $columnOffset
    ): void {
        $this->assertNonNegative($generatedLine, 'generated line');
        $this->assertNonNegative($generatedColumn, 'generated column');

        $offsetLine = $this->offsetNonNegative($generatedLine, $lineOffset, 'generated line + line offset');
        $offsetColumn = $this->offsetNonNegative($generatedColumn, $columnOffset, 'generated column + column offset');

        $this->addGeneratedMapping($offsetLine, $offsetColumn);
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

    public function addSourceMap(SourceMap $sourceMap, int $lineOffset = 0): void
    {
        $sourceIndexes = [];
        foreach ($sourceMap->sources as $index => $source) {
            $mappedIndex = $this->addSource($source);
            $sourceIndexes[$index] = $mappedIndex;
            if (array_key_exists($index, $sourceMap->sourcesContent)) {
                $this->setSourceContent($mappedIndex, $sourceMap->sourcesContent[$index]);
            }
        }

        $nameIndexes = [];
        foreach ($sourceMap->names as $index => $name) {
            $nameIndexes[$index] = $this->addName($name);
        }

        $childMaxLine = null;
        $remappedByLine = [];
        foreach ($sourceMap->mappings as $mapping) {
            $childMaxLine = $childMaxLine === null
                ? $mapping['generatedLine']
                : max($childMaxLine, $mapping['generatedLine']);

            $generatedLine = $mapping['generatedLine'] + $lineOffset;
            if ($generatedLine < 0) {
                continue;
            }

            $nameIndex = null;
            if ($mapping['nameIndex'] !== null) {
                if (!array_key_exists($mapping['nameIndex'], $nameIndexes)) {
                    throw new InvalidArgumentException('Source map mapping references unknown name index: ' . $mapping['nameIndex']);
                }

                $nameIndex = $nameIndexes[$mapping['nameIndex']];
            }

            $sourceIndex = null;
            if ($mapping['sourceIndex'] !== null) {
                if (!array_key_exists($mapping['sourceIndex'], $sourceIndexes)) {
                    throw new InvalidArgumentException('Source map mapping references unknown source index: ' . $mapping['sourceIndex']);
                }

                $sourceIndex = $sourceIndexes[$mapping['sourceIndex']];
            }

            $remappedByLine[$generatedLine][] = [
                'generatedLine' => $generatedLine,
                'generatedColumn' => $mapping['generatedColumn'],
                'sourceIndex' => $sourceIndex,
                'originalLine' => $mapping['originalLine'],
                'originalColumn' => $mapping['originalColumn'],
                'nameIndex' => $nameIndex,
                'order' => 0,
            ];
        }

        $childLineCount = max(
            $sourceMap->generatedLineCount,
            $childMaxLine === null ? 0 : $childMaxLine + 1
        );
        if ($childLineCount === 0) {
            return;
        }

        $replaceLines = [];
        for ($line = 0; $line < $childLineCount; $line++) {
            $targetLine = $line + $lineOffset;
            if ($targetLine >= 0) {
                $replaceLines[$targetLine] = true;
            }
        }

        $updated = [];
        foreach ($this->mappings as $mapping) {
            if (!isset($replaceLines[$mapping['generatedLine']])) {
                $updated[] = $mapping;
            }
        }

        ksort($remappedByLine);
        foreach ($remappedByLine as $lineMappings) {
            foreach ($lineMappings as $mapping) {
                $updated[] = $mapping;
            }
        }

        $this->mappings = $this->renumberMappings($updated);
        $targetEndLine = $lineOffset + $childLineCount - 1;
        if ($targetEndLine >= 0) {
            $this->generatedLineCount = max($this->generatedLineCount, $targetEndLine + 1);
        }
    }

    public function extendWithSourceMap(SourceMap $originalSourceMap): void
    {
        $sourceIndexes = [];
        foreach ($originalSourceMap->sources as $index => $source) {
            $mappedIndex = $this->addSource($source);
            $sourceIndexes[$index] = $mappedIndex;
            if (array_key_exists($index, $originalSourceMap->sourcesContent)) {
                $this->setSourceContent($mappedIndex, $originalSourceMap->sourcesContent[$index]);
            }
        }

        $nameIndexes = [];
        foreach ($originalSourceMap->names as $index => $name) {
            $nameIndexes[$index] = $this->addName($name);
        }

        $updated = [];
        foreach ($this->mappings as $mapping) {
            if ($mapping['sourceIndex'] === null) {
                $updated[] = $mapping;
                continue;
            }

            $closest = $originalSourceMap->findClosestMapping($mapping['originalLine'], $mapping['originalColumn']);
            if ($closest === null || $closest['sourceIndex'] === null) {
                $mapping['sourceIndex'] = null;
                $mapping['originalLine'] = null;
                $mapping['originalColumn'] = null;
                $mapping['nameIndex'] = null;
                $updated[] = $mapping;
                continue;
            }

            if (!array_key_exists($closest['sourceIndex'], $sourceIndexes)) {
                throw new InvalidArgumentException('Source map mapping references unknown source index: ' . $closest['sourceIndex']);
            }

            $mapping['sourceIndex'] = $sourceIndexes[$closest['sourceIndex']];
            $mapping['originalLine'] = $closest['originalLine'];
            $mapping['originalColumn'] = $closest['originalColumn'];
            $mapping['nameIndex'] = null;
            if ($closest['nameIndex'] !== null) {
                if (!array_key_exists($closest['nameIndex'], $nameIndexes)) {
                    throw new InvalidArgumentException('Source map mapping references unknown name index: ' . $closest['nameIndex']);
                }

                $mapping['nameIndex'] = $nameIndexes[$closest['nameIndex']];
            }

            $updated[] = $mapping;
        }

        $this->mappings = $this->renumberMappings($updated);
    }

    public function offsetColumns(int $generatedLine, int $generatedColumn, int $generatedColumnOffset): void
    {
        $this->assertNonNegative($generatedLine, 'generated line');
        $this->assertNonNegative($generatedColumn, 'generated column');

        if ($generatedColumnOffset === 0) {
            return;
        }

        $lineExists = $generatedLine < $this->generatedLineCount;
        $lineMappings = $this->sortedLineMappingIndexes($generatedLine);
        if ($lineMappings === []) {
            if ($lineExists) {
                $this->offsetNonNegative($generatedColumn, $generatedColumnOffset, 'column + column offset');
            }

            return;
        }

        $startColumn = $this->offsetNonNegative($generatedColumn, $generatedColumnOffset, 'column + column offset');
        $shiftStart = $this->lowerBoundGeneratedColumn($lineMappings, $generatedColumn);
        $shiftIndexes = [];
        for ($i = $shiftStart; $i < count($lineMappings); $i++) {
            $shiftIndexes[$lineMappings[$i]['index']] = true;
        }

        $removeIndexes = [];
        if ($generatedColumnOffset < 0) {
            $removeStart = $this->lowerBoundGeneratedColumn($lineMappings, $startColumn);
            for ($i = $removeStart; $i < $shiftStart; $i++) {
                $removeIndexes[$lineMappings[$i]['index']] = true;
            }
        }

        $updated = [];
        foreach ($this->mappings as $index => $mapping) {
            if (isset($removeIndexes[$index])) {
                continue;
            }

            if (isset($shiftIndexes[$index])) {
                $mapping['generatedColumn'] += $generatedColumnOffset;
            }

            $updated[] = $mapping;
        }

        $this->mappings = $this->renumberMappings($updated);
    }

    public function offsetLines(int $generatedLine, int $generatedLineOffset): void
    {
        $this->assertNonNegative($generatedLine, 'generated line');

        if ($generatedLineOffset === 0 || $this->generatedLineCount === 0) {
            return;
        }

        $startLine = $this->offsetNonNegative($generatedLine, $generatedLineOffset, 'line + line offset');
        $removeStart = null;
        $removeEnd = null;
        if ($generatedLineOffset < 0) {
            $removeStart = $startLine;
            $removeEnd = $generatedLine;
        }

        $updated = [];
        foreach ($this->mappings as $mapping) {
            if ($removeStart !== null && $mapping['generatedLine'] >= $removeStart && $mapping['generatedLine'] < $removeEnd) {
                continue;
            }

            if ($mapping['generatedLine'] >= $generatedLine) {
                $mapping['generatedLine'] += $generatedLineOffset;
            }

            $updated[] = $mapping;
        }

        $this->mappings = $this->renumberMappings($updated);
        $absoluteOffset = abs($generatedLineOffset);
        if ($generatedLineOffset > 0) {
            $this->generatedLineCount = $generatedLine > $this->generatedLineCount
                ? $generatedLine + $absoluteOffset + 1
                : $this->generatedLineCount + $absoluteOffset;

            return;
        }

        if ($startLine < $this->generatedLineCount) {
            $removeEnd = min($generatedLine, $this->generatedLineCount);
            $this->generatedLineCount -= max(0, $removeEnd - $startLine);
        }
    }

    public function addEmptyMap(string $source, string $sourceContent, int $lineOffset = 0): void
    {
        $sourceIndex = $this->addSource($source);
        $this->setSourceContent($sourceIndex, $sourceContent);

        foreach ($this->sourceLines($sourceContent) as $lineNumber => $_line) {
            $generatedLine = $lineNumber + $lineOffset;
            if ($generatedLine < 0) {
                continue;
            }

            $this->addMapping($generatedLine, 0, $sourceIndex, $lineNumber, 0);
        }
    }

    /**
     * @param list<string> $sources
     * @param list<string|null> $sourcesContent
     * @param list<string> $names
     */
    public function addVlqMap(
        string $mappings,
        array $sources,
        array $sourcesContent = [],
        array $names = [],
        int $lineOffset = 0,
        int $columnOffset = 0
    ): void {
        $sourceIndexes = [];
        foreach ($sources as $source) {
            if (!is_string($source)) {
                throw new InvalidArgumentException('Source map sources must be strings.');
            }

            $sourceIndexes[] = $this->addSource($source);
        }

        foreach ($sourcesContent as $index => $content) {
            if (!array_key_exists($index, $sourceIndexes) || $content === null) {
                continue;
            }

            if (!is_string($content)) {
                throw new InvalidArgumentException('Source map sourcesContent entries must be strings or null.');
            }

            $this->setSourceContent($sourceIndexes[$index], $content);
        }

        $nameIndexes = [];
        foreach ($names as $name) {
            if (!is_string($name)) {
                throw new InvalidArgumentException('Source map names must be strings.');
            }

            $nameIndexes[] = $this->addName($name);
        }

        $generatedLine = $lineOffset;
        $generatedColumn = $columnOffset;
        $source = 0;
        $originalLine = 0;
        $originalColumn = 0;
        $name = 0;
        $length = strlen($mappings);

        for ($i = 0; $i < $length;) {
            $char = $mappings[$i];
            if ($char === ';') {
                $generatedLine++;
                $generatedColumn = $columnOffset;
                $i++;
                continue;
            }

            if ($char === ',') {
                $i++;
                continue;
            }

            $start = $i;
            while ($i < $length && $mappings[$i] !== ';' && $mappings[$i] !== ',') {
                $i++;
            }

            $values = self::decodeVlqSegment(substr($mappings, $start, $i - $start));
            if ($values === [] || count($values) === 2 || count($values) === 3 || count($values) > 5) {
                throw new InvalidArgumentException('Invalid source map segment: ' . substr($mappings, $start, $i - $start));
            }

            $generatedColumn = $this->offsetNonNegative($generatedColumn, $values[0], 'generated column + column offset');

            $sourceIndex = null;
            $mappedOriginalLine = null;
            $mappedOriginalColumn = null;
            $mappedName = null;

            if (count($values) >= 4) {
                $source = $this->offsetNonNegative($source, $values[1], 'source index');
                $originalLine = $this->offsetNonNegative($originalLine, $values[2], 'original line');
                $originalColumn = $this->offsetNonNegative($originalColumn, $values[3], 'original column');
                if (!array_key_exists($source, $sourceIndexes)) {
                    throw new OutOfBoundsException('Source map segment references unknown source index: ' . $source);
                }

                $sourceIndex = $sourceIndexes[$source];
                $mappedOriginalLine = $originalLine;
                $mappedOriginalColumn = $originalColumn;

                if (count($values) === 5) {
                    $name = $this->offsetNonNegative($name, $values[4], 'name index');
                    if (!array_key_exists($name, $nameIndexes)) {
                        throw new OutOfBoundsException('Source map segment references unknown name index: ' . $name);
                    }

                    $mappedName = $names[$name];
                }
            }

            if ($generatedLine >= 0) {
                $this->addRawMapping($generatedLine, $generatedColumn, $sourceIndex, $mappedOriginalLine, $mappedOriginalColumn, $mappedName);
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, string $projectRoot = '/'): self
    {
        if (!isset($data['mappings']) || !is_string($data['mappings'])) {
            throw new InvalidArgumentException('Source map mappings must be a string.');
        }

        $sources = self::listOfStrings($data['sources'] ?? [], 'sources');
        $rawSourcesContent = self::listOfNullableStrings($data['sourcesContent'] ?? [], 'sourcesContent');
        $sourcesContent = [];
        foreach ($sources as $index => $_source) {
            $sourcesContent[] = $rawSourcesContent[$index] ?? '';
            if ($sourcesContent[$index] === null) {
                $sourcesContent[$index] = '';
            }
        }

        $names = self::listOfStrings($data['names'] ?? [], 'names');

        $map = new self($projectRoot);
        $map->addVlqMap($data['mappings'], $sources, $sourcesContent, $names);

        return $map;
    }

    public static function fromJson(string $json, string $projectRoot = '/'): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new InvalidArgumentException('Source map JSON must decode to an object.');
        }

        return self::fromArray($data, $projectRoot);
    }

    public static function fromDataUrl(string $dataUrl, string $projectRoot = '/'): self
    {
        [$mimeType, $payload, $isBase64] = self::parseDataUrl($dataUrl);
        if (strtolower($mimeType) !== 'application/json') {
            throw new InvalidArgumentException('Source map data URL MIME type must be application/json.');
        }

        if ($isBase64) {
            $json = base64_decode($payload, true);
            if ($json === false) {
                throw new InvalidArgumentException('Source map data URL payload must be valid base64.');
            }

            return self::fromJson($json, $projectRoot);
        }

        return self::fromJson(rawurldecode($payload), $projectRoot);
    }

    /**
     * @return array{generatedLine:int,generatedColumn:int,sourceIndex:int|null,originalLine:int|null,originalColumn:int|null,nameIndex:int|null}|null
     */
    public function findClosestMapping(int $generatedLine, int $generatedColumn): ?array
    {
        $this->assertNonNegative($generatedLine, 'generated line');
        $this->assertNonNegative($generatedColumn, 'generated column');

        $lineMappings = [];
        foreach ($this->mappings as $mapping) {
            if ($mapping['generatedLine'] === $generatedLine) {
                $lineMappings[] = $mapping;
            }
        }

        if ($lineMappings === []) {
            return null;
        }

        usort(
            $lineMappings,
            static fn (array $a, array $b): int => [$a['generatedColumn'], $a['order']]
                <=> [$b['generatedColumn'], $b['order']]
        );

        $previous = null;
        foreach ($lineMappings as $mapping) {
            if ($mapping['generatedColumn'] === $generatedColumn) {
                return $this->mappingForRead($mapping);
            }

            if ($mapping['generatedColumn'] > $generatedColumn) {
                return $previous === null
                    ? $this->mappingForRead($lineMappings[0], 0)
                    : $this->mappingForRead($previous);
            }

            $previous = $mapping;
        }

        return $this->mappingForRead($lineMappings[0], 0);
    }

    /**
     * @return list<array{generatedLine:int,generatedColumn:int,sourceIndex:int|null,originalLine:int|null,originalColumn:int|null,nameIndex:int|null}>
     */
    public function getMappings(): array
    {
        $mappings = $this->mappings;
        usort(
            $mappings,
            static fn (array $a, array $b): int => [$a['generatedLine'], $a['generatedColumn'], $a['order']]
                <=> [$b['generatedLine'], $b['generatedColumn'], $b['order']]
        );

        return array_map(fn (array $mapping): array => $this->mappingForRead($mapping), $mappings);
    }

    public function writeVlq(): string
    {
        if ($this->mappings === [] && $this->generatedLineCount === 0) {
            return '';
        }

        $mappings = $this->mappings;
        usort(
            $mappings,
            static fn (array $a, array $b): int => [$a['generatedLine'], $a['generatedColumn'], $a['order']]
                <=> [$b['generatedLine'], $b['generatedColumn'], $b['order']]
        );

        $byLine = [];
        $maxLine = max(0, $this->generatedLineCount - 1);
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

                if ($mapping['sourceIndex'] === null) {
                    continue;
                }

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

    public function toDataUrl(?string $sourceRoot = null): string
    {
        return 'data:application/json;charset=utf-8;base64,' . base64_encode($this->toJson($sourceRoot));
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

    private function addRawMapping(
        int $generatedLine,
        int $generatedColumn,
        ?int $sourceIndex,
        ?int $originalLine,
        ?int $originalColumn,
        ?string $name
    ): void {
        $this->assertNonNegative($generatedLine, 'generated line');
        $this->assertNonNegative($generatedColumn, 'generated column');

        $nameIndex = null;
        if ($sourceIndex !== null) {
            if ($originalLine === null || $originalColumn === null) {
                throw new InvalidArgumentException('Original line and column are required for source-backed mappings.');
            }

            $this->assertSourceIndex($sourceIndex);
            $this->assertNonNegative($originalLine, 'original line');
            $this->assertNonNegative($originalColumn, 'original column');
            $nameIndex = $name === null ? null : $this->addName($name);
        } elseif ($originalLine !== null || $originalColumn !== null || $name !== null) {
            throw new InvalidArgumentException('Generated-only mappings cannot include original positions or names.');
        }

        $this->mappings[] = [
            'generatedLine' => $generatedLine,
            'generatedColumn' => $generatedColumn,
            'sourceIndex' => $sourceIndex,
            'originalLine' => $originalLine,
            'originalColumn' => $originalColumn,
            'nameIndex' => $nameIndex,
            'order' => count($this->mappings),
        ];
        $this->generatedLineCount = max($this->generatedLineCount, $generatedLine + 1);
    }

    private function assertNonNegative(int $value, string $label): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException(ucfirst($label) . ' must be non-negative.');
        }

        if ($value > self::MAX_UNSIGNED_32) {
            throw new InvalidArgumentException(ucfirst($label) . ' must fit in unsigned 32-bit range.');
        }
    }

    private function offsetNonNegative(int $value, int $offset, string $label): int
    {
        $offsetValue = $value + $offset;
        if ($offsetValue < 0) {
            throw new InvalidArgumentException(ucfirst($label) . ' must be non-negative.');
        }

        if ($offsetValue > self::MAX_UNSIGNED_32) {
            throw new InvalidArgumentException(ucfirst($label) . ' must fit in unsigned 32-bit range.');
        }

        return $offsetValue;
    }

    private static function makeRelativePath(string $base, string $target): string
    {
        if (str_starts_with(strtolower($target), 'file://')) {
            $target = substr($target, 7);
        }

        if (!self::isAbsolutePath($target)) {
            if (str_contains($target, ':')) {
                return $target;
            }

            return implode('/', self::chunkPath($target));
        }

        $baseParts = self::chunkPath($base);
        $targetParts = self::chunkPath($target);
        $common = 0;
        $limit = min(count($baseParts), count($targetParts));
        while ($common < $limit && $baseParts[$common] === $targetParts[$common]) {
            $common++;
        }

        return implode(
            '/',
            array_merge(
                array_fill(0, count($baseParts) - $common, '..'),
                array_slice($targetParts, $common)
            )
        );
    }

    private static function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return true;
        }

        return strlen($path) > 3
            && $path[1] === ':'
            && ($path[2] === '/' || $path[2] === '\\')
            && ctype_alpha($path[0]);
    }

    /**
     * @return list<string>
     */
    private static function chunkPath(string $path): array
    {
        $parts = preg_split('/[\/\\\\]+/', $path);
        if ($parts === false) {
            return [];
        }

        return array_values(array_filter(
            $parts,
            static fn (string $part): bool => $part !== '' && $part !== '.'
        ));
    }

    /**
     * @return list<array{index:int,column:int,order:int}>
     */
    private function sortedLineMappingIndexes(int $generatedLine): array
    {
        $lineMappings = [];
        foreach ($this->mappings as $index => $mapping) {
            if ($mapping['generatedLine'] === $generatedLine) {
                $lineMappings[] = [
                    'index' => $index,
                    'column' => $mapping['generatedColumn'],
                    'order' => $mapping['order'],
                ];
            }
        }

        usort(
            $lineMappings,
            static fn (array $a, array $b): int => [$a['column'], $a['order']]
                <=> [$b['column'], $b['order']]
        );

        return $lineMappings;
    }

    /**
     * @param list<array{index:int,column:int,order:int}> $lineMappings
     */
    private function lowerBoundGeneratedColumn(array $lineMappings, int $generatedColumn): int
    {
        $low = 0;
        $high = count($lineMappings);
        while ($low < $high) {
            $mid = intdiv($low + $high, 2);
            if ($lineMappings[$mid]['column'] < $generatedColumn) {
                $low = $mid + 1;
            } else {
                $high = $mid;
            }
        }

        return $low;
    }

    /**
     * @param list<array{
     *     generatedLine:int,
     *     generatedColumn:int,
     *     sourceIndex:int|null,
     *     originalLine:int|null,
     *     originalColumn:int|null,
     *     nameIndex:int|null,
     *     order:int
     * }> $mappings
     * @return list<array{
     *     generatedLine:int,
     *     generatedColumn:int,
     *     sourceIndex:int|null,
     *     originalLine:int|null,
     *     originalColumn:int|null,
     *     nameIndex:int|null,
     *     order:int
     * }>
     */
    private function renumberMappings(array $mappings): array
    {
        foreach ($mappings as $order => &$mapping) {
            $mapping['order'] = $order;
        }
        unset($mapping);

        return array_values($mappings);
    }

    /**
     * @return list<string>
     */
    private function sourceLines(string $sourceContent): array
    {
        if ($sourceContent === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $sourceContent);
        if ($lines === false) {
            return [];
        }

        if (preg_match('/(?:\r\n|\r|\n)$/', $sourceContent) === 1) {
            array_pop($lines);
        }

        return array_values($lines);
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function listOfStrings(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('Source map ' . $label . ' must be a list.');
        }

        $strings = [];
        foreach (array_values($value) as $entry) {
            if (!is_string($entry)) {
                throw new InvalidArgumentException('Source map ' . $label . ' entries must be strings.');
            }

            $strings[] = $entry;
        }

        return $strings;
    }

    /**
     * @param mixed $value
     * @return list<string|null>
     */
    private static function listOfNullableStrings(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('Source map ' . $label . ' must be a list.');
        }

        $strings = [];
        foreach (array_values($value) as $entry) {
            if ($entry !== null && !is_string($entry)) {
                throw new InvalidArgumentException('Source map ' . $label . ' entries must be strings or null.');
            }

            $strings[] = $entry;
        }

        return $strings;
    }

    /**
     * @return array{0:string,1:string,2:bool}
     */
    private static function parseDataUrl(string $dataUrl): array
    {
        if (!str_starts_with(strtolower($dataUrl), 'data:')) {
            throw new InvalidArgumentException('Source map data URL must use the data: scheme.');
        }

        $comma = strpos($dataUrl, ',');
        if ($comma === false) {
            throw new InvalidArgumentException('Source map data URL is missing a payload separator.');
        }

        $metadata = substr($dataUrl, 5, $comma - 5);
        $payload = substr($dataUrl, $comma + 1);
        $parts = $metadata === '' ? [] : explode(';', $metadata);
        $mimeType = $parts[0] ?? 'text/plain';
        if ($mimeType === '') {
            $mimeType = 'text/plain';
        }

        $isBase64 = false;
        foreach (array_slice($parts, 1) as $parameter) {
            if (strtolower($parameter) === 'base64') {
                $isBase64 = true;
                break;
            }
        }

        return [$mimeType, $payload, $isBase64];
    }

    /**
     * @param array{
     *     generatedLine:int,
     *     generatedColumn:int,
     *     sourceIndex:int|null,
     *     originalLine:int|null,
     *     originalColumn:int|null,
     *     nameIndex:int|null,
     *     order:int
     * } $mapping
     * @return array{generatedLine:int,generatedColumn:int,sourceIndex:int|null,originalLine:int|null,originalColumn:int|null,nameIndex:int|null}
     */
    private function mappingForRead(array $mapping, ?int $generatedColumn = null): array
    {
        return [
            'generatedLine' => $mapping['generatedLine'],
            'generatedColumn' => $generatedColumn ?? $mapping['generatedColumn'],
            'sourceIndex' => $mapping['sourceIndex'],
            'originalLine' => $mapping['originalLine'],
            'originalColumn' => $mapping['originalColumn'],
            'nameIndex' => $mapping['nameIndex'],
        ];
    }

    /**
     * @return list<string|null>
     */
    private function sourceContentsForJson(): array
    {
        return array_values($this->sourcesContent);
    }
}
