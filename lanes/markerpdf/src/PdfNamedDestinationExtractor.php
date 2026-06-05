<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class PdfNamedDestinationExtractor
{
    private const PDF_DOC_ENCODING_OVERRIDES = [
        0x18 => 0x02d8,
        0x19 => 0x02c7,
        0x1a => 0x02c6,
        0x1b => 0x02d9,
        0x1c => 0x02dd,
        0x1d => 0x02db,
        0x1e => 0x02da,
        0x1f => 0x02dc,
        0x7f => 0xfffd,
        0x80 => 0x2022,
        0x81 => 0x2020,
        0x82 => 0x2021,
        0x83 => 0x2026,
        0x84 => 0x2014,
        0x85 => 0x2013,
        0x86 => 0x0192,
        0x87 => 0x2044,
        0x88 => 0x2039,
        0x89 => 0x203a,
        0x8a => 0x2212,
        0x8b => 0x2030,
        0x8c => 0x201e,
        0x8d => 0x201c,
        0x8e => 0x201d,
        0x8f => 0x2018,
        0x90 => 0x2019,
        0x91 => 0x201a,
        0x92 => 0x2122,
        0x93 => 0xfb01,
        0x94 => 0xfb02,
        0x95 => 0x0141,
        0x96 => 0x0152,
        0x97 => 0x0160,
        0x98 => 0x0178,
        0x99 => 0x017d,
        0x9a => 0x0131,
        0x9b => 0x0142,
        0x9c => 0x0153,
        0x9d => 0x0161,
        0x9e => 0x017e,
        0x9f => 0xfffd,
        0xa0 => 0x20ac,
    ];

    /**
     * Native boundary for catalog-level PDF named destinations.
     *
     * @return list<array{name: string, page: int|null, page_object_id: int|null, fit: string, coordinates: array<string, float|null>, source: string}>
     */
    public function extractNamedDestinations(string $pdfBytes): array
    {
        $this->assertPdfBytes($pdfBytes);

        $objects = $this->pdfObjects($pdfBytes);
        if ($objects === []) {
            return [];
        }

        $cache = [];
        $catalog = $this->catalogDictionary($objects, $cache);
        if ($catalog === null) {
            return [];
        }

        $pageIndexes = $this->pageIndexesByObjectId($objects, $cache, $catalog);
        $destinations = [];
        $seenNames = [];

        $namesDictionary = $this->resolve($catalog['Names'] ?? null, $objects, $cache);
        if ($this->isDictionary($namesDictionary) && array_key_exists('Dests', $namesDictionary)) {
            foreach ($this->collectNameTreeEntries($namesDictionary['Dests'], $objects, $cache) as $entry) {
                $destination = $this->normalizeDestination(
                    $entry['name'],
                    $entry['value'],
                    'names-tree',
                    $pageIndexes,
                    $objects,
                    $cache
                );
                if ($destination === null || isset($seenNames[$destination['name']])) {
                    continue;
                }

                $destinations[] = $destination;
                $seenNames[$destination['name']] = true;
            }
        }

        $legacyDests = $this->resolve($catalog['Dests'] ?? null, $objects, $cache);
        if ($this->isDictionary($legacyDests)) {
            foreach ($legacyDests as $name => $value) {
                $destination = $this->normalizeDestination(
                    (string) $name,
                    $value,
                    'legacy-dests',
                    $pageIndexes,
                    $objects,
                    $cache
                );
                if ($destination === null || isset($seenNames[$destination['name']])) {
                    continue;
                }

                $destinations[] = $destination;
                $seenNames[$destination['name']] = true;
            }
        }

        return $destinations;
    }

    /**
     * @return array<int, array{generation: int, body: string, generations: array<int, string>}>
     */
    private function pdfObjects(string $pdfBytes): array
    {
        $pdfBytes = $this->bytesThroughCurrentEof($pdfBytes);
        if (!preg_match_all('/(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $objects = [];
        foreach ($matches as $match) {
            $objectId = (int) $match[1];
            $generation = (int) $match[2];
            $body = $match[3];

            $objects[$objectId]['generation'] = $generation;
            $objects[$objectId]['body'] = $body;
            $objects[$objectId]['generations'][$generation] = $body;
        }
        ksort($objects, SORT_NUMERIC);

        return $objects;
    }

    private function bytesThroughCurrentEof(string $pdfBytes): string
    {
        if (preg_match_all('/\bstartxref\s+\d+/s', $pdfBytes, $matches, PREG_OFFSET_CAPTURE) >= 1) {
            $latest = end($matches[0]);
            if (is_array($latest)) {
                $eofOffset = strpos($pdfBytes, '%%EOF', $latest[1]);
                if ($eofOffset !== false) {
                    return substr($pdfBytes, 0, $eofOffset + strlen('%%EOF'));
                }

                return $pdfBytes;
            }
        }

        $eofOffset = strrpos($pdfBytes, '%%EOF');
        if ($eofOffset !== false) {
            return substr($pdfBytes, 0, $eofOffset + strlen('%%EOF'));
        }

        return $pdfBytes;
    }

    /**
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array<int, mixed> $cache
     * @return array<string, mixed>|null
     */
    private function catalogDictionary(array $objects, array &$cache): ?array
    {
        foreach (array_keys($objects) as $objectId) {
            $dictionary = $this->objectDictionary($objectId, $objects, $cache);
            if ($dictionary !== null && $this->nameValue($dictionary['Type'] ?? null) === 'Catalog') {
                return $dictionary;
            }
        }

        return null;
    }

    /**
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array<int, mixed> $cache
     * @return array<string, int>
     */
    private function pageIndexesByObjectId(array $objects, array &$cache, array $catalog): array
    {
        $pages = [];
        $pagesRef = $catalog['Pages'] ?? null;
        $pagesObjectId = $this->validRefObjectId($pagesRef, $objects);
        if ($pagesObjectId !== null) {
            $pages = $this->collectPageObjectIds(
                $pagesObjectId,
                $objects,
                $cache,
                $this->refGeneration($pagesRef)
            );
        }

        if ($pages === []) {
            foreach (array_keys($objects) as $objectId) {
                $dictionary = $this->objectDictionary($objectId, $objects, $cache);
                if ($dictionary !== null && $this->nameValue($dictionary['Type'] ?? null) === 'Page') {
                    $pages[] = [
                        'object_id' => $objectId,
                        'generation' => $objects[$objectId]['generation'],
                    ];
                }
            }
        }

        $indexes = [];
        foreach (array_values($pages) as $index => $pageRef) {
            $indexes[$this->objectGenerationKey($pageRef['object_id'], $pageRef['generation'])] = $index;
        }

        return $indexes;
    }

    /**
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array<int, mixed> $cache
     * @param array<string, true> $seen
     * @return list<array{object_id: int, generation: int}>
     */
    private function collectPageObjectIds(
        int $objectId,
        array $objects,
        array &$cache,
        ?int $generation = null,
        array $seen = []
    ): array
    {
        if (!isset($objects[$objectId])) {
            return [];
        }

        $effectiveGeneration = $generation ?? $objects[$objectId]['generation'];
        $seenKey = $objectId . ':' . $effectiveGeneration;
        if (isset($seen[$seenKey])) {
            return [];
        }

        $seen[$seenKey] = true;
        $dictionary = $this->objectDictionary($objectId, $objects, $cache, $effectiveGeneration);
        if ($dictionary === null) {
            return [];
        }

        $type = $this->nameValue($dictionary['Type'] ?? null);
        if ($type === 'Page') {
            return [[
                'object_id' => $objectId,
                'generation' => $effectiveGeneration,
            ]];
        }
        if ($type !== 'Pages') {
            return [];
        }

        $pages = [];
        foreach ($this->arrayRefsWithGenerations($dictionary['Kids'] ?? null, $objects) as $kidRef) {
            foreach ($this->collectPageObjectIds($kidRef['object_id'], $objects, $cache, $kidRef['generation'], $seen) as $pageRef) {
                $pages[] = $pageRef;
            }
        }

        return $pages;
    }

    /**
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array<int, mixed> $cache
     * @return array<string, mixed>|null
     */
    private function objectDictionary(int $objectId, array $objects, array &$cache, ?int $generation = null): ?array
    {
        $value = $this->objectValue($objectId, $objects, $cache, $generation);
        $resolved = $this->resolve($value, $objects, $cache);

        return $this->isDictionary($resolved) ? $resolved : null;
    }

    /**
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array<int, mixed> $cache
     */
    private function objectValue(int $objectId, array $objects, array &$cache, ?int $generation = null): mixed
    {
        if (!isset($objects[$objectId])) {
            return null;
        }

        $effectiveGeneration = $generation ?? $objects[$objectId]['generation'];
        $body = $this->objectBody($objectId, $objects, $effectiveGeneration);
        if ($body === null) {
            return null;
        }

        $cacheKey = $objectId . ':' . $effectiveGeneration;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $tokens = $this->tokens($body);
        $index = 0;
        $cache[$cacheKey] = $this->parseValue($tokens, $index);

        return $cache[$cacheKey];
    }

    /**
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array<int, mixed> $cache
     * @param list<string> $seen
     */
    private function resolve(mixed $value, array $objects, array &$cache, array $seen = []): mixed
    {
        $objectId = $this->validRefObjectId($value, $objects);
        if ($this->isRefValue($value) && $objectId === null) {
            return null;
        }
        if ($objectId === null) {
            return $value;
        }

        $generation = $this->refGeneration($value);
        $seenKey = $objectId . ':' . $generation;
        if (in_array($seenKey, $seen, true)) {
            return null;
        }

        $seen[] = $seenKey;
        return $this->resolve($this->objectValue($objectId, $objects, $cache, $generation), $objects, $cache, $seen);
    }

    /**
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array<int, mixed> $cache
     * @param list<int> $seenObjects
     * @param array{lower: string, upper: string}|null $inheritedLimits
     * @return list<array{name: string, value: mixed}>
     */
    private function collectNameTreeEntries(
        mixed $node,
        array $objects,
        array &$cache,
        array $seenObjects = [],
        ?array $inheritedLimits = null,
        int $depth = 0
    ): array {
        if ($depth > 20) {
            return [];
        }

        $nodeObjectId = $this->validRefObjectId($node, $objects);
        if ($this->isRefValue($node) && $nodeObjectId === null) {
            return [];
        }
        if ($nodeObjectId !== null) {
            if (in_array($nodeObjectId, $seenObjects, true)) {
                return [];
            }
            $seenObjects[] = $nodeObjectId;
        }

        $dictionary = $this->resolve($node, $objects, $cache);
        if (!$this->isDictionary($dictionary)) {
            return [];
        }

        $entries = [];
        $limits = $this->nameTreeEffectiveLimits($dictionary, $objects, $cache, $inheritedLimits);
        $childLimits = $limits;
        $names = $dictionary['Names'] ?? null;
        if (is_array($names) && array_is_list($names)) {
            $entryLimits = $this->nameTreeLimitsMatchAnyPairKey($names, $objects, $cache, $limits)
                ? $limits
                : $inheritedLimits;
            $childLimits = $entryLimits;

            for ($index = 0; $index + 1 < count($names); $index += 2) {
                $name = $this->destinationNameValue($names[$index], $objects, $cache);
                if ($name === null || $name === '' || !$this->nameTreeNameWithinLimits($name, $entryLimits)) {
                    continue;
                }

                $entries[] = [
                    'name' => $name,
                    'value' => $names[$index + 1],
                ];
            }
        }

        foreach ($this->arrayValues($dictionary['Kids'] ?? null) as $kid) {
            foreach ($this->collectNameTreeEntries($kid, $objects, $cache, $seenObjects, $childLimits, $depth + 1) as $entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array<int, mixed> $cache
     * @param array{lower: string, upper: string}|null $inheritedLimits
     * @return array{lower: string, upper: string}|null
     */
    private function nameTreeEffectiveLimits(array $node, array $objects, array &$cache, ?array $inheritedLimits): ?array
    {
        $nodeLimits = $this->nameTreeNodeLimits($node, $objects, $cache);
        if ($nodeLimits === null) {
            return $inheritedLimits;
        }
        if ($inheritedLimits === null) {
            return $nodeLimits;
        }

        return [
            'lower' => strcmp($nodeLimits['lower'], $inheritedLimits['lower']) < 0
                ? $inheritedLimits['lower']
                : $nodeLimits['lower'],
            'upper' => strcmp($nodeLimits['upper'], $inheritedLimits['upper']) > 0
                ? $inheritedLimits['upper']
                : $nodeLimits['upper'],
        ];
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array<int, mixed> $cache
     * @return array{lower: string, upper: string}|null
     */
    private function nameTreeNodeLimits(array $node, array $objects, array &$cache): ?array
    {
        $limits = $this->arrayValues($node['Limits'] ?? null);
        if (count($limits) < 2) {
            return null;
        }

        $lower = $this->destinationNameValue($limits[0], $objects, $cache);
        $upper = $this->destinationNameValue($limits[1], $objects, $cache);
        if ($lower === null || $upper === null || $lower === '' || $upper === '') {
            return null;
        }

        return [
            'lower' => $lower,
            'upper' => $upper,
        ];
    }

    /**
     * @param array{lower: string, upper: string}|null $limits
     */
    private function nameTreeNameWithinLimits(string $name, ?array $limits): bool
    {
        if ($limits === null) {
            return true;
        }

        return strcmp($limits['lower'], $limits['upper']) <= 0
            && strcmp($name, $limits['lower']) >= 0
            && strcmp($name, $limits['upper']) <= 0;
    }

    /**
     * @param list<mixed> $items
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array<int, mixed> $cache
     * @param array{lower: string, upper: string}|null $limits
     */
    private function nameTreeLimitsMatchAnyPairKey(array $items, array $objects, array &$cache, ?array $limits): bool
    {
        if ($limits === null || $items === []) {
            return true;
        }

        for ($index = 0, $count = count($items); $index + 1 < $count; $index += 2) {
            $name = $this->destinationNameValue($items[$index], $objects, $cache);
            if ($name !== null && $this->nameTreeNameWithinLimits($name, $limits)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, int> $pageIndexes
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array<int, mixed> $cache
     * @return array{name: string, page: int|null, page_object_id: int|null, fit: string, coordinates: array<string, float|null>, source: string}|null
     */
    private function normalizeDestination(
        string $name,
        mixed $value,
        string $source,
        array $pageIndexes,
        array $objects,
        array &$cache
    ): ?array {
        $destination = $this->resolve($value, $objects, $cache);
        if ($this->isDictionary($destination) && array_key_exists('D', $destination)) {
            $destination = $this->resolve($destination['D'], $objects, $cache);
        }

        if (!is_array($destination) || !array_is_list($destination) || count($destination) < 2) {
            return null;
        }

        $pageOperand = $destination[0] ?? null;
        $pageObjectId = $this->validRefObjectId($pageOperand, $objects);
        $pageIndex = null;
        if ($pageObjectId !== null) {
            $pageIndex = $pageIndexes[$this->objectGenerationKey($pageObjectId, $this->refGeneration($pageOperand))] ?? null;
            if ($pageIndex === null) {
                return null;
            }
        } elseif ($this->isRefValue($pageOperand)) {
            return null;
        } elseif (is_int($pageOperand)) {
            $pageIndex = $pageOperand >= 0 ? $pageOperand : null;
        }

        $fit = $this->nameValue($destination[1]);
        if ($fit === null || $fit === '') {
            return null;
        }

        return [
            'name' => $name,
            'page' => $pageIndex,
            'page_object_id' => $pageObjectId,
            'fit' => $fit,
            'coordinates' => $this->destinationCoordinates($fit, $destination),
            'source' => $source,
        ];
    }

    /**
     * @param list<mixed> $destination
     * @return array<string, float|null>
     */
    private function destinationCoordinates(string $fit, array $destination): array
    {
        return match ($fit) {
            'XYZ' => [
                'left' => $this->nullableNumber($destination[2] ?? null),
                'top' => $this->nullableNumber($destination[3] ?? null),
                'zoom' => $this->nullableNumber($destination[4] ?? null),
            ],
            'FitH', 'FitBH' => [
                'top' => $this->nullableNumber($destination[2] ?? null),
            ],
            'FitV', 'FitBV' => [
                'left' => $this->nullableNumber($destination[2] ?? null),
            ],
            'FitR' => [
                'left' => $this->nullableNumber($destination[2] ?? null),
                'bottom' => $this->nullableNumber($destination[3] ?? null),
                'right' => $this->nullableNumber($destination[4] ?? null),
                'top' => $this->nullableNumber($destination[5] ?? null),
            ],
            default => [],
        };
    }

    private function nullableNumber(mixed $value): ?float
    {
        return is_int($value) || is_float($value) ? (float) $value : null;
    }

    private function destinationName(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        return $this->nameValue($value);
    }

    /**
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array<int, mixed> $cache
     */
    private function destinationNameValue(mixed $value, array $objects, array &$cache): ?string
    {
        return $this->destinationName($this->resolve($value, $objects, $cache));
    }

    /**
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @return list<array{object_id: int, generation: int}>
     */
    private function arrayRefsWithGenerations(mixed $value, array $objects): array
    {
        $refs = [];
        foreach ($this->arrayValues($value) as $entry) {
            $objectId = $this->validRefObjectId($entry, $objects);
            if ($objectId !== null) {
                $refs[] = [
                    'object_id' => $objectId,
                    'generation' => $this->refGeneration($entry),
                ];
            }
        }

        return $refs;
    }

    /**
     * @return list<mixed>
     */
    private function arrayValues(mixed $value): array
    {
        return is_array($value) && array_is_list($value) ? $value : [];
    }

    private function refObjectId(mixed $value): ?int
    {
        return is_array($value) && isset($value['__pdf_ref']) && is_int($value['__pdf_ref'])
            ? $value['__pdf_ref']
            : null;
    }

    private function refGeneration(mixed $value): int
    {
        return is_array($value) && isset($value['__pdf_generation']) && is_int($value['__pdf_generation'])
            ? $value['__pdf_generation']
            : 0;
    }

    private function objectGenerationKey(int $objectId, int $generation): string
    {
        return $objectId . ':' . $generation;
    }

    /**
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     */
    private function validRefObjectId(mixed $value, array $objects): ?int
    {
        $objectId = $this->refObjectId($value);
        if ($objectId === null || !isset($objects[$objectId])) {
            return null;
        }

        return $this->objectBody($objectId, $objects, $this->refGeneration($value)) !== null
            ? $objectId
            : null;
    }

    /**
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     */
    private function objectBody(int $objectId, array $objects, ?int $generation = null): ?string
    {
        if (!isset($objects[$objectId])) {
            return null;
        }
        if ($generation === null) {
            return $objects[$objectId]['body'];
        }

        return $objects[$objectId]['generations'][$generation] ?? null;
    }

    private function isRefValue(mixed $value): bool
    {
        return $this->refObjectId($value) !== null;
    }

    private function nameValue(mixed $value): ?string
    {
        return is_array($value) && array_key_exists('__pdf_name', $value)
            ? (string) $value['__pdf_name']
            : null;
    }

    private function isDictionary(mixed $value): bool
    {
        return is_array($value)
            && !array_is_list($value)
            && !array_key_exists('__pdf_ref', $value)
            && !array_key_exists('__pdf_name', $value);
    }

    /**
     * @return list<array{type: string, value?: mixed}>
     */
    private function tokens(string $source): array
    {
        $tokens = [];
        $length = strlen($source);
        for ($offset = 0; $offset < $length;) {
            $char = $source[$offset];
            if (ctype_space($char)) {
                $offset++;
                continue;
            }
            if ($char === '%') {
                while ($offset < $length && !in_array($source[$offset], ["\r", "\n"], true)) {
                    $offset++;
                }
                continue;
            }
            if (substr($source, $offset, 2) === '<<') {
                $tokens[] = ['type' => 'dict-start'];
                $offset += 2;
                continue;
            }
            if (substr($source, $offset, 2) === '>>') {
                $tokens[] = ['type' => 'dict-end'];
                $offset += 2;
                continue;
            }
            if ($char === '[') {
                $tokens[] = ['type' => 'array-start'];
                $offset++;
                continue;
            }
            if ($char === ']') {
                $tokens[] = ['type' => 'array-end'];
                $offset++;
                continue;
            }
            if ($char === '/') {
                [$name, $offset] = $this->readName($source, $offset + 1);
                $tokens[] = ['type' => 'name', 'value' => $this->decodePdfName($name)];
                continue;
            }
            if ($char === '(') {
                [$string, $offset] = $this->readLiteralString($source, $offset + 1);
                $tokens[] = ['type' => 'string', 'value' => $this->decodeTextString($string)];
                continue;
            }
            if ($char === '<') {
                [$string, $offset] = $this->readHexString($source, $offset + 1);
                $tokens[] = ['type' => 'string', 'value' => $this->decodeTextString($string)];
                continue;
            }
            if (preg_match('/[+-]?(?:\d+\.\d*|\.\d+|\d+)/A', substr($source, $offset), $match) === 1) {
                $raw = $match[0];
                $tokens[] = [
                    'type' => 'number',
                    'value' => str_contains($raw, '.') ? (float) $raw : (int) $raw,
                ];
                $offset += strlen($raw);
                continue;
            }

            [$word, $offset] = $this->readKeyword($source, $offset);
            if ($word !== '') {
                $tokens[] = ['type' => 'keyword', 'value' => $word];
                continue;
            }

            $offset++;
        }

        return $tokens;
    }

    /**
     * @param list<array{type: string, value?: mixed}> $tokens
     */
    private function parseValue(array $tokens, int &$index): mixed
    {
        if (!isset($tokens[$index])) {
            return null;
        }

        $token = $tokens[$index];
        if ($token['type'] === 'number'
            && ($tokens[$index + 1]['type'] ?? null) === 'number'
            && ($tokens[$index + 2]['type'] ?? null) === 'keyword'
            && ($tokens[$index + 2]['value'] ?? null) === 'R'
            && is_int($token['value'])
            && is_int($tokens[$index + 1]['value'] ?? null)
        ) {
            $objectId = $token['value'];
            $generation = $tokens[$index + 1]['value'];
            $index += 3;

            return ['__pdf_ref' => $objectId, '__pdf_generation' => $generation];
        }

        $index++;

        return match ($token['type']) {
            'dict-start' => $this->parseDictionary($tokens, $index),
            'array-start' => $this->parseArray($tokens, $index),
            'name' => ['__pdf_name' => (string) $token['value']],
            'string', 'number' => $token['value'],
            'keyword' => $this->keywordValue((string) $token['value']),
            default => null,
        };
    }

    /**
     * @param list<array{type: string, value?: mixed}> $tokens
     * @return array<string, mixed>
     */
    private function parseDictionary(array $tokens, int &$index): array
    {
        $dictionary = [];
        while (isset($tokens[$index]) && $tokens[$index]['type'] !== 'dict-end') {
            $key = $tokens[$index];
            $index++;
            if ($key['type'] !== 'name') {
                continue;
            }

            $dictionary[(string) $key['value']] = $this->parseValue($tokens, $index);
        }

        if (($tokens[$index]['type'] ?? null) === 'dict-end') {
            $index++;
        }

        return $dictionary;
    }

    /**
     * @param list<array{type: string, value?: mixed}> $tokens
     * @return list<mixed>
     */
    private function parseArray(array $tokens, int &$index): array
    {
        $array = [];
        while (isset($tokens[$index]) && $tokens[$index]['type'] !== 'array-end') {
            $array[] = $this->parseValue($tokens, $index);
        }

        if (($tokens[$index]['type'] ?? null) === 'array-end') {
            $index++;
        }

        return $array;
    }

    private function keywordValue(string $keyword): mixed
    {
        return match ($keyword) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => $keyword,
        };
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function readName(string $source, int $offset): array
    {
        $start = $offset;
        $length = strlen($source);
        while ($offset < $length && !$this->isDelimiter($source[$offset])) {
            $offset++;
        }

        return [substr($source, $start, $offset - $start), $offset];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function readKeyword(string $source, int $offset): array
    {
        $start = $offset;
        $length = strlen($source);
        while ($offset < $length && !$this->isDelimiter($source[$offset])) {
            $offset++;
        }

        return [substr($source, $start, $offset - $start), $offset];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function readHexString(string $source, int $offset): array
    {
        $end = strpos($source, '>', $offset);
        if ($end === false) {
            return ['', strlen($source)];
        }

        $hex = preg_replace('/\s+/', '', substr($source, $offset, $end - $offset));
        if ($hex === null || preg_match('/^[\da-fA-F]*$/', $hex) !== 1) {
            return ['', $end + 1];
        }
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $bytes = hex2bin($hex);
        return [$bytes === false ? '' : $bytes, $end + 1];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function readLiteralString(string $source, int $offset): array
    {
        $out = '';
        $depth = 1;
        $length = strlen($source);

        while ($offset < $length && $depth > 0) {
            $char = $source[$offset];
            if ($char === '\\') {
                $offset++;
                if ($offset >= $length) {
                    break;
                }
                [$decoded, $offset] = $this->readEscapedLiteralByte($source, $offset);
                $out .= $decoded;
                continue;
            }

            if ($char === '(') {
                $depth++;
                $out .= $char;
                $offset++;
                continue;
            }

            if ($char === ')') {
                $depth--;
                $offset++;
                if ($depth > 0) {
                    $out .= $char;
                }
                continue;
            }

            $out .= $char;
            $offset++;
        }

        return [$out, $offset];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function readEscapedLiteralByte(string $source, int $offset): array
    {
        $char = $source[$offset];
        $nextOffset = $offset + 1;

        if ($char === "\r" || $char === "\n") {
            if ($char === "\r" && ($source[$nextOffset] ?? '') === "\n") {
                $nextOffset++;
            }

            return ['', $nextOffset];
        }

        if (preg_match('/[0-7]/', $char) === 1) {
            $octal = $char;
            while (strlen($octal) < 3 && isset($source[$nextOffset]) && preg_match('/[0-7]/', $source[$nextOffset]) === 1) {
                $octal .= $source[$nextOffset];
                $nextOffset++;
            }

            return [chr(octdec($octal) & 0xff), $nextOffset];
        }

        return [
            match ($char) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\b",
                'f' => "\f",
                default => $char,
            },
            $nextOffset,
        ];
    }

    private function decodePdfName(string $name): string
    {
        return preg_replace_callback(
            '/#([\da-fA-F]{2})/',
            static fn (array $match): string => chr(hexdec($match[1])),
            $name
        ) ?? $name;
    }

    private function decodeTextString(string $bytes): string
    {
        if (str_starts_with($bytes, "\xfe\xff")) {
            $decoded = iconv('UTF-16BE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }
        if (str_starts_with($bytes, "\xff\xfe")) {
            $decoded = iconv('UTF-16LE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }

        return $this->decodePdfDocEncoding($bytes);
    }

    private function decodePdfDocEncoding(string $bytes): string
    {
        $decoded = '';
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            $codepoint = self::PDF_DOC_ENCODING_OVERRIDES[$byte] ?? $byte;
            $char = mb_chr($codepoint, 'UTF-8');
            if ($char !== false) {
                $decoded .= $char;
            }
        }

        return $decoded;
    }

    private function isDelimiter(string $char): bool
    {
        return ctype_space($char) || str_contains('()<>[]{}/%', $char);
    }

    private function assertPdfBytes(string $pdfBytes): void
    {
        if (!str_starts_with(ltrim($pdfBytes), '%PDF-')) {
            throw new InvalidArgumentException('Named destination extraction requires PDF bytes.');
        }
    }
}
