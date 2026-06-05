<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class PdfNamedDestinationExtractor
{
    private const VALID_DESTINATION_VIEW_NAMES = [
        'Fit' => true,
        'FitB' => true,
        'FitBH' => true,
        'FitBV' => true,
        'FitH' => true,
        'FitR' => true,
        'FitV' => true,
        'XYZ' => true,
    ];

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
        $catalog = $this->catalogDictionary($pdfBytes, $objects, $cache);
        if ($catalog === null) {
            return [];
        }

        $pageIndexes = $this->pageIndexesByObjectId($objects, $cache, $catalog);
        $destinations = [];
        $seenNames = [];

        $namesDictionary = $this->resolve($catalog['Names'] ?? null, $objects, $cache);
        if ($this->isDictionary($namesDictionary) && array_key_exists('Dests', $namesDictionary)) {
            $nameTreeDestinations = [];
            foreach ($this->collectNameTreeEntries($namesDictionary['Dests'], $objects, $cache) as $entry) {
                $destination = $this->normalizeDestination(
                    $entry['name'],
                    $entry['value'],
                    'names-tree',
                    $pageIndexes,
                    $objects,
                    $cache
                );
                if ($destination === null) {
                    continue;
                }

                $nameTreeDestinations[$destination['name']] = $destination;
            }

            foreach ($nameTreeDestinations as $destination) {
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
        $definitions = $this->pdfObjectDefinitions($pdfBytes);
        if ($definitions === []) {
            return [];
        }

        $objects = $this->objectsFromDefinitions($definitions);
        $xrefStreamEntries = $this->xrefStreamEntriesFromLatestStartxref($pdfBytes, $definitions);
        if ($xrefStreamEntries !== []) {
            $selectedObjects = $this->objectsFromXrefStreamEntries($definitions, $xrefStreamEntries);
            if ($selectedObjects !== []) {
                $selectedObjects = $this->withCompressedObjectStreamObjects($selectedObjects, $xrefStreamEntries);
                foreach ($definitions as $definition) {
                    if (array_key_exists($definition['object_id'], $xrefStreamEntries)) {
                        continue;
                    }

                    $this->addObjectDefinition($selectedObjects, $definition);
                }
                ksort($selectedObjects, SORT_NUMERIC);

                return $selectedObjects;
            }
        }

        $xrefEntries = $this->classicXrefEntriesFromLatestStartxref($pdfBytes);
        if ($xrefEntries === []) {
            return $objects;
        }

        $selectedObjects = $this->objectsFromClassicXrefEntries($definitions, $xrefEntries);
        if ($selectedObjects === []) {
            return $objects;
        }

        foreach ($definitions as $definition) {
            if (array_key_exists($definition['object_id'], $xrefEntries)) {
                continue;
            }

            $this->addObjectDefinition($selectedObjects, $definition);
        }
        ksort($selectedObjects, SORT_NUMERIC);

        return $selectedObjects;
    }

    /**
     * @param list<array{object_id: int, generation: int, body: string, offset: int}> $definitions
     * @return array<int, array{type: int, generation?: int, offset?: int, object_stream?: int, index?: int, index_is_explicit?: bool}>
     */
    private function xrefStreamEntriesFromLatestStartxref(string $pdfBytes, array $definitions): array
    {
        $offset = $this->latestStartxrefOffset($pdfBytes);
        if ($offset === null) {
            return [];
        }

        return $this->xrefStreamEntriesFromOffsetChain($offset, $definitions);
    }

    /**
     * @param list<array{object_id: int, generation: int, body: string, offset: int}> $definitions
     * @param list<int> $seenOffsets
     * @return array<int, array{type: int, generation?: int, offset?: int, object_stream?: int, index?: int, index_is_explicit?: bool}>
     */
    private function xrefStreamEntriesFromOffsetChain(int $offset, array $definitions, array $seenOffsets = []): array
    {
        if ($offset < 0 || in_array($offset, $seenOffsets, true)) {
            return [];
        }

        $seenOffsets[] = $offset;
        $section = $this->xrefStreamSectionAtOffset($offset, $definitions);
        if ($section === null) {
            return [];
        }

        $entries = $this->xrefStreamEntriesFromSection($section);
        $previousOffset = $this->xrefPreviousOffset($section['dictionary']);
        if ($previousOffset !== null) {
            foreach ($this->xrefStreamEntriesFromOffsetChain($previousOffset, $definitions, $seenOffsets) as $objectId => $entry) {
                if (!array_key_exists($objectId, $entries)) {
                    $entries[$objectId] = $entry;
                }
            }
        }
        ksort($entries, SORT_NUMERIC);

        return $entries;
    }

    /**
     * @param list<array{object_id: int, generation: int, body: string, offset: int}> $definitions
     * @return array{dictionary: array<string, mixed>, body: string}|null
     */
    private function xrefStreamSectionAtOffset(int $offset, array $definitions): ?array
    {
        foreach ($definitions as $definition) {
            if ($definition['offset'] !== $offset) {
                continue;
            }

            $tokens = $this->tokens($definition['body']);
            $index = 0;
            $dictionary = $this->parseValue($tokens, $index);
            if (!$this->isDictionary($dictionary) || $this->nameValue($dictionary['Type'] ?? null) !== 'XRef') {
                return null;
            }

            return [
                'dictionary' => $dictionary,
                'body' => $definition['body'],
            ];
        }

        return null;
    }

    /**
     * @param array{dictionary: array<string, mixed>, body: string} $section
     * @return array<int, array{type: int, generation?: int, offset?: int, object_stream?: int, index?: int, index_is_explicit?: bool}>
     */
    private function xrefStreamEntriesFromSection(array $section): array
    {
        $dictionary = $section['dictionary'];
        $decoded = $this->decodedStreamBytes($section['body'], $dictionary, [], []);
        if ($decoded === null) {
            return [];
        }

        $widths = $this->xrefStreamFieldWidths($dictionary['W'] ?? null);
        if ($widths === null) {
            return [];
        }

        $entryWidth = array_sum($widths);
        if ($entryWidth <= 0) {
            return [];
        }

        $decodedEntryCount = intdiv(strlen($decoded), $entryWidth);
        $entries = [];
        $fieldOffset = 0;
        foreach ($this->xrefStreamIndexRanges($dictionary, $decodedEntryCount) as $range) {
            for ($row = 0; $row < $range['count'] && $fieldOffset + $entryWidth <= strlen($decoded); $row++) {
                $objectId = $range['first'] + $row;
                $type = $widths[0] === 0 ? 1 : $this->xrefStreamFieldValue($decoded, $fieldOffset, $widths[0]);
                $fieldTwo = $this->xrefStreamFieldValue($decoded, $fieldOffset, $widths[1]);
                $fieldThree = $this->xrefStreamFieldValue($decoded, $fieldOffset, $widths[2]);

                if ($type === 1) {
                    $entries[$objectId] = [
                        'type' => 1,
                        'offset' => $fieldTwo,
                        'generation' => $fieldThree,
                    ];
                    continue;
                }

                if ($type === 2 && $fieldTwo > 0) {
                    $entries[$objectId] = [
                        'type' => 2,
                        'object_stream' => $fieldTwo,
                        'index' => $fieldThree,
                        'index_is_explicit' => $widths[2] > 0,
                    ];
                    continue;
                }

                $entries[$objectId] = [
                    'type' => $type,
                    'generation' => $fieldThree,
                    'offset' => $fieldTwo,
                ];
            }
        }
        ksort($entries, SORT_NUMERIC);

        return $entries;
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private function xrefStreamFieldWidths(mixed $value): ?array
    {
        $items = $this->arrayValues($value);
        if (count($items) < 3) {
            return null;
        }

        $widths = [];
        foreach (array_slice($items, 0, 3) as $item) {
            if (!is_int($item) || $item < 0) {
                return null;
            }
            $widths[] = $item;
        }

        return [$widths[0], $widths[1], $widths[2]];
    }

    /**
     * @param array<string, mixed> $dictionary
     * @return list<array{first: int, count: int}>
     */
    private function xrefStreamIndexRanges(array $dictionary, int $decodedEntryCount): array
    {
        $index = $this->arrayValues($dictionary['Index'] ?? null);
        if ($index === []) {
            $size = $this->integerValue($dictionary['Size'] ?? null);

            return [[
                'first' => 0,
                'count' => $size === null ? $decodedEntryCount : min($size, $decodedEntryCount),
            ]];
        }

        $ranges = [];
        $consumed = 0;
        for ($offset = 0, $count = count($index); $offset + 1 < $count; $offset += 2) {
            if (!is_int($index[$offset]) || !is_int($index[$offset + 1]) || $index[$offset + 1] < 0) {
                continue;
            }

            $rowCount = min($index[$offset + 1], max(0, $decodedEntryCount - $consumed));
            $ranges[] = [
                'first' => $index[$offset],
                'count' => $rowCount,
            ];
            $consumed += $rowCount;
        }

        return $ranges;
    }

    private function xrefStreamFieldValue(string $bytes, int &$offset, int $width): int
    {
        $value = 0;
        for ($index = 0; $index < $width; $index++) {
            $value = ($value << 8) + ord($bytes[$offset] ?? "\0");
            $offset++;
        }

        return $value;
    }

    /**
     * @param list<array{object_id: int, generation: int, body: string, offset: int}> $definitions
     * @param array<int, array{type: int, generation?: int, offset?: int, object_stream?: int, index?: int, index_is_explicit?: bool}> $xrefEntries
     * @return array<int, array{generation: int, body: string, generations: array<int, string>}>
     */
    private function objectsFromXrefStreamEntries(array $definitions, array $xrefEntries): array
    {
        $definitionsByOffset = [];
        foreach ($definitions as $definition) {
            $definitionsByOffset[$definition['offset']] = $definition;
        }

        $objects = [];
        foreach ($xrefEntries as $objectId => $entry) {
            if (($entry['type'] ?? null) !== 1 || !isset($entry['offset'])) {
                continue;
            }

            $definition = $definitionsByOffset[$entry['offset']] ?? null;
            if ($definition === null
                || $definition['object_id'] !== $objectId
                || $definition['generation'] !== ($entry['generation'] ?? 0)
            ) {
                continue;
            }

            $this->addObjectDefinition($objects, $definition);
        }
        ksort($objects, SORT_NUMERIC);

        return $objects;
    }

    /**
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array<int, array{type: int, generation?: int, offset?: int, object_stream?: int, index?: int, index_is_explicit?: bool}> $xrefEntries
     * @return array<int, array{generation: int, body: string, generations: array<int, string>}>
     */
    private function withCompressedObjectStreamObjects(array $objects, array $xrefEntries): array
    {
        $expanded = $objects;
        $cache = [];
        for ($pass = 0; $pass < 4; $pass++) {
            $added = false;
            foreach ($xrefEntries as $objectId => $entry) {
                if (($entry['type'] ?? null) !== 2 || isset($expanded[$objectId])) {
                    continue;
                }

                $body = $this->objectStreamMemberBody($expanded, $entry, (int) $objectId, $cache);
                if ($body === null) {
                    continue;
                }

                $tokens = $this->tokens($body);
                $index = 0;
                if ($this->parseValue($tokens, $index) === null) {
                    continue;
                }
                if ($this->streamBytesFromBody($body, [], $cache) !== null) {
                    continue;
                }

                $this->addObjectDefinition($expanded, [
                    'object_id' => (int) $objectId,
                    'generation' => 0,
                    'body' => $body,
                    'offset' => -1,
                ]);
                $cache = [];
                $added = true;
            }

            if (!$added) {
                break;
            }
        }
        ksort($expanded, SORT_NUMERIC);

        return $expanded;
    }

    /**
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array{type: int, object_stream?: int, index?: int, index_is_explicit?: bool} $xrefEntry
     * @param array<int, mixed> $cache
     */
    private function objectStreamMemberBody(array $objects, array $xrefEntry, int $requestedObjectId, array &$cache): ?string
    {
        $objectStreamId = $xrefEntry['object_stream'] ?? null;
        if (!is_int($objectStreamId) || !isset($objects[$objectStreamId])) {
            return null;
        }

        $dictionary = $this->objectDictionary($objectStreamId, $objects, $cache);
        if ($dictionary === null || $this->nameValue($dictionary['Type'] ?? null) !== 'ObjStm') {
            return null;
        }

        $declaredCount = $this->integerValue($this->resolve($dictionary['N'] ?? null, $objects, $cache));
        $firstOffset = $this->integerValue($this->resolve($dictionary['First'] ?? null, $objects, $cache));
        if ($declaredCount === null || $declaredCount < 1 || $firstOffset === null || $firstOffset < 0) {
            return null;
        }

        $decoded = $this->decodedStreamBytes($objects[$objectStreamId]['body'], $dictionary, $objects, $cache);
        if ($decoded === null || $firstOffset > strlen($decoded)) {
            return null;
        }

        $members = $this->objectStreamHeaderMembers(substr($decoded, 0, $firstOffset), $declaredCount);
        if ($members === []) {
            return null;
        }

        $memberIndex = $this->objectStreamSelectedMemberIndex($members, $xrefEntry, $requestedObjectId);
        if ($memberIndex === null) {
            return null;
        }

        $data = substr($decoded, $firstOffset);
        $start = $members[$memberIndex]['offset'];
        if ($start < 0 || $start >= strlen($data)) {
            return null;
        }

        $end = strlen($data);
        foreach ($members as $index => $member) {
            if ($index === $memberIndex || $member['offset'] <= $start) {
                continue;
            }
            $end = min($end, $member['offset']);
        }
        if ($end <= $start) {
            return null;
        }

        return trim(substr($data, $start, $end - $start));
    }

    /**
     * @return list<array{object_id: int, offset: int}>
     */
    private function objectStreamHeaderMembers(string $header, int $declaredCount): array
    {
        if (preg_match_all('/\d+/', $header, $matches) < 1) {
            return [];
        }

        $members = [];
        $tokens = $matches[0];
        for ($index = 0, $count = count($tokens); $index + 1 < $count && count($members) < $declaredCount; $index += 2) {
            $members[] = [
                'object_id' => (int) $tokens[$index],
                'offset' => (int) $tokens[$index + 1],
            ];
        }

        return $members;
    }

    /**
     * @param list<array{object_id: int, offset: int}> $members
     * @param array{type: int, index?: int, index_is_explicit?: bool} $xrefEntry
     */
    private function objectStreamSelectedMemberIndex(array $members, array $xrefEntry, int $requestedObjectId): ?int
    {
        $requestedIndex = $xrefEntry['index'] ?? null;
        if (is_int($requestedIndex) && ($xrefEntry['index_is_explicit'] ?? true) === true) {
            if (!isset($members[$requestedIndex]) || $members[$requestedIndex]['object_id'] !== $requestedObjectId) {
                return null;
            }

            return $requestedIndex;
        }

        foreach ($members as $index => $member) {
            if ($member['object_id'] === $requestedObjectId) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return list<array{object_id: int, generation: int, body: string, offset: int}>
     */
    private function pdfObjectDefinitions(string $pdfBytes): array
    {
        if (!preg_match_all('/(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $definitions = [];
        foreach ($matches as $match) {
            $definitions[] = [
                'object_id' => (int) $match[1][0],
                'generation' => (int) $match[2][0],
                'body' => $match[3][0],
                'offset' => (int) $match[0][1],
            ];
        }

        return $definitions;
    }

    /**
     * @param list<array{object_id: int, generation: int, body: string, offset: int}> $definitions
     * @return array<int, array{generation: int, body: string, generations: array<int, string>}>
     */
    private function objectsFromDefinitions(array $definitions): array
    {
        $objects = [];
        foreach ($definitions as $definition) {
            $this->addObjectDefinition($objects, $definition);
        }
        ksort($objects, SORT_NUMERIC);

        return $objects;
    }

    /**
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array{object_id: int, generation: int, body: string, offset: int} $definition
     */
    private function addObjectDefinition(array &$objects, array $definition): void
    {
        $objectId = $definition['object_id'];
        $generation = $definition['generation'];
        $body = $definition['body'];

        $objects[$objectId]['generation'] = $generation;
        $objects[$objectId]['body'] = $body;
        $objects[$objectId]['generations'][$generation] = $body;
    }

    /**
     * @param list<array{object_id: int, generation: int, body: string, offset: int}> $definitions
     * @param array<int, array{offset: int, generation: int, state: string}> $xrefEntries
     * @return array<int, array{generation: int, body: string, generations: array<int, string>}>
     */
    private function objectsFromClassicXrefEntries(array $definitions, array $xrefEntries): array
    {
        $definitionsByOffset = [];
        foreach ($definitions as $definition) {
            $definitionsByOffset[$definition['offset']] = $definition;
        }

        $objects = [];
        foreach ($xrefEntries as $objectId => $entry) {
            if ($entry['state'] !== 'n') {
                continue;
            }

            $definition = $definitionsByOffset[$entry['offset']] ?? null;
            if ($definition === null
                || $definition['object_id'] !== $objectId
                || $definition['generation'] !== $entry['generation']
            ) {
                continue;
            }

            $this->addObjectDefinition($objects, $definition);
        }
        ksort($objects, SORT_NUMERIC);

        return $objects;
    }

    /**
     * @return array<int, array{offset: int, generation: int, state: string}>
     */
    private function classicXrefEntriesFromLatestStartxref(string $pdfBytes): array
    {
        $offset = $this->latestStartxrefOffset($pdfBytes);
        if ($offset === null) {
            return [];
        }

        return $this->classicXrefEntriesFromOffsetChain($pdfBytes, $offset);
    }

    /**
     * @param list<int> $seenOffsets
     * @return array<int, array{offset: int, generation: int, state: string}>
     */
    private function classicXrefEntriesFromOffsetChain(string $pdfBytes, int $offset, array $seenOffsets = []): array
    {
        if ($offset < 0 || $offset >= strlen($pdfBytes) || in_array($offset, $seenOffsets, true)) {
            return [];
        }

        $seenOffsets[] = $offset;
        $section = $this->classicXrefSectionAtOffset($pdfBytes, $offset);
        if ($section === null) {
            return [];
        }

        $entries = $section['entries'];
        $previousOffset = $section['previous_offset'];
        if ($previousOffset !== null) {
            foreach ($this->classicXrefEntriesFromOffsetChain($pdfBytes, $previousOffset, $seenOffsets) as $objectId => $entry) {
                if (!array_key_exists($objectId, $entries)) {
                    $entries[$objectId] = $entry;
                }
            }
        }
        ksort($entries, SORT_NUMERIC);

        return $entries;
    }

    /**
     * @return array{entries: array<int, array{offset: int, generation: int, state: string}>, previous_offset: int|null}|null
     */
    private function classicXrefSectionAtOffset(string $pdfBytes, int $offset): ?array
    {
        if (substr($pdfBytes, $offset, 4) !== 'xref') {
            return null;
        }

        $lines = preg_split('/\r\n|\r|\n/', substr($pdfBytes, $offset));
        if (!is_array($lines) || trim($lines[0] ?? '') !== 'xref') {
            return null;
        }

        $entries = [];
        $index = 1;
        $count = count($lines);
        while ($index < $count) {
            $line = trim($lines[$index]);
            $index++;
            if ($line === '' || str_starts_with($line, '%')) {
                continue;
            }
            if ($line === 'trailer') {
                $index--;
                break;
            }
            if (preg_match('/^(\d+)\s+(\d+)$/', $line, $subsection) !== 1) {
                continue;
            }

            $startObject = (int) $subsection[1];
            $rowCount = (int) $subsection[2];
            $rowsRead = 0;
            while ($index < $count && $rowsRead < $rowCount) {
                $row = trim($lines[$index]);
                $index++;
                if ($row === '' || str_starts_with($row, '%')) {
                    continue;
                }
                if (preg_match('/^(\d{10})\s+(\d{5})\s+([nf])\b/', $row, $xrefRow) !== 1) {
                    continue;
                }

                $entries[$startObject + $rowsRead] = [
                    'offset' => (int) $xrefRow[1],
                    'generation' => (int) $xrefRow[2],
                    'state' => $xrefRow[3],
                ];
                $rowsRead++;
            }
        }

        $trailerSource = implode("\n", array_slice($lines, $index));
        $trailer = $this->trailerDictionaryFromXrefSection($trailerSource);
        $previousOffset = $this->xrefPreviousOffset($trailer);

        return [
            'entries' => $entries,
            'previous_offset' => $previousOffset,
        ];
    }

    /**
     * @param array<string, mixed>|null $trailer
     */
    private function xrefPreviousOffset(?array $trailer): ?int
    {
        $previous = $trailer['Prev'] ?? null;
        if (!is_int($previous) && !is_float($previous)) {
            return null;
        }
        if ($previous < 0) {
            return null;
        }

        return (int) $previous;
    }

    private function bytesThroughCurrentEof(string $pdfBytes): string
    {
        if (preg_match_all('/\bstartxref\s+[+-]?\d+/s', $pdfBytes, $matches, PREG_OFFSET_CAPTURE) >= 1) {
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
    private function catalogDictionary(string $pdfBytes, array $objects, array &$cache): ?array
    {
        $rootRef = $this->currentTrailerRootRef($pdfBytes);
        $rootObjectId = $this->validRefObjectId($rootRef, $objects);
        if ($rootObjectId !== null) {
            $dictionary = $this->objectDictionary($rootObjectId, $objects, $cache, $this->refGeneration($rootRef));
            if ($dictionary !== null && $this->nameValue($dictionary['Type'] ?? null) === 'Catalog') {
                return $dictionary;
            }
        }

        foreach (array_keys($objects) as $objectId) {
            $dictionary = $this->objectDictionary($objectId, $objects, $cache);
            if ($dictionary !== null && $this->nameValue($dictionary['Type'] ?? null) === 'Catalog') {
                return $dictionary;
            }
        }

        return null;
    }

    private function currentTrailerRootRef(string $pdfBytes): mixed
    {
        $pdfBytes = $this->bytesThroughCurrentEof($pdfBytes);
        $xrefOffset = $this->latestStartxrefOffset($pdfBytes);
        if ($xrefOffset === null || $xrefOffset < 0 || $xrefOffset >= strlen($pdfBytes)) {
            return null;
        }

        $section = substr($pdfBytes, $xrefOffset);
        if (str_starts_with(ltrim($section), 'xref')) {
            $dictionary = $this->trailerDictionaryFromXrefSection($section);

            return $dictionary['Root'] ?? null;
        }

        $dictionary = $this->firstDictionaryFromTokens($section);
        if (!$this->isDictionary($dictionary) || $this->nameValue($dictionary['Type'] ?? null) !== 'XRef') {
            return null;
        }

        return $dictionary['Root'] ?? null;
    }

    private function latestStartxrefOffset(string $pdfBytes): ?int
    {
        if (preg_match_all('/\bstartxref\s+([+-]?\d+)/s', $pdfBytes, $matches) < 1) {
            return null;
        }

        $offset = end($matches[1]);

        return is_string($offset) ? (int) $offset : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function trailerDictionaryFromXrefSection(string $section): ?array
    {
        $tokens = $this->tokens($section);
        for ($index = 0, $count = count($tokens); $index + 1 < $count; $index++) {
            if (($tokens[$index]['type'] ?? null) !== 'keyword' || ($tokens[$index]['value'] ?? null) !== 'trailer') {
                continue;
            }

            $valueIndex = $index + 1;
            $dictionary = $this->parseValue($tokens, $valueIndex);

            return $this->isDictionary($dictionary) ? $dictionary : null;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function firstDictionaryFromTokens(string $source): ?array
    {
        $tokens = $this->tokens($source);
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            if (($tokens[$index]['type'] ?? null) !== 'dict-start') {
                continue;
            }

            $dictionary = $this->parseValue($tokens, $index);

            return $this->isDictionary($dictionary) ? $dictionary : null;
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
     * @param list<string> $seenObjects
     * @param array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}|null $inheritedLimits
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
            $seenKey = $this->objectGenerationKey($nodeObjectId, $this->refGeneration($node));
            if (in_array($seenKey, $seenObjects, true)) {
                return [];
            }
            $seenObjects[] = $seenKey;
        }

        $dictionary = $this->resolve($node, $objects, $cache);
        if (!$this->isDictionary($dictionary)) {
            return [];
        }

        $entries = [];
        $limits = $this->nameTreeEffectiveLimits($dictionary, $objects, $cache, $inheritedLimits);
        $kids = $this->arrayValues($this->resolve($dictionary['Kids'] ?? null, $objects, $cache));
        $names = $this->resolve($dictionary['Names'] ?? null, $objects, $cache);
        if ($kids === [] && is_array($names) && array_is_list($names)) {
            $entryLimits = $this->nameTreeLimitsMatchAnyPairKey($names, $objects, $cache, $limits)
                ? $limits
                : $inheritedLimits;

            for ($index = 0; $index + 1 < count($names); $index += 2) {
                $name = $this->destinationNameDetails($names[$index], $objects, $cache);
                if ($name === null || $name['text'] === '' || !$this->nameTreeNameWithinLimits($name['text'], $entryLimits, $name['bytes'])) {
                    continue;
                }

                $entries[] = [
                    'name' => $name['text'],
                    'value' => $names[$index + 1],
                ];
            }
        }

        foreach ($kids as $kid) {
            if ($this->validRefObjectId($kid, $objects) === null) {
                continue;
            }

            foreach ($this->collectNameTreeEntries($kid, $objects, $cache, $seenObjects, $limits, $depth + 1) as $entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array<int, mixed> $cache
     * @param array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}|null $inheritedLimits
     * @return array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}|null
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

        $lower = strcmp($nodeLimits['lower_bytes'], $inheritedLimits['lower_bytes']) < 0
            ? ['text' => $inheritedLimits['lower'], 'bytes' => $inheritedLimits['lower_bytes']]
            : ['text' => $nodeLimits['lower'], 'bytes' => $nodeLimits['lower_bytes']];
        $upper = strcmp($nodeLimits['upper_bytes'], $inheritedLimits['upper_bytes']) > 0
            ? ['text' => $inheritedLimits['upper'], 'bytes' => $inheritedLimits['upper_bytes']]
            : ['text' => $nodeLimits['upper'], 'bytes' => $nodeLimits['upper_bytes']];
        if (strcmp($lower['bytes'], $upper['bytes']) > 0) {
            return $inheritedLimits;
        }

        return [
            'lower' => $lower['text'],
            'upper' => $upper['text'],
            'lower_bytes' => $lower['bytes'],
            'upper_bytes' => $upper['bytes'],
        ];
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array<int, mixed> $cache
     * @return array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}|null
     */
    private function nameTreeNodeLimits(array $node, array $objects, array &$cache): ?array
    {
        $limits = $this->arrayValues($this->resolve($node['Limits'] ?? null, $objects, $cache));
        if (count($limits) < 2) {
            return null;
        }

        $lower = $this->destinationNameDetails($limits[0], $objects, $cache);
        $upper = $this->destinationNameDetails($limits[1], $objects, $cache);
        if ($lower === null || $upper === null || $lower['text'] === '' || $upper['text'] === '') {
            return null;
        }
        if (strcmp($lower['bytes'], $upper['bytes']) > 0) {
            return null;
        }

        return [
            'lower' => $lower['text'],
            'upper' => $upper['text'],
            'lower_bytes' => $lower['bytes'],
            'upper_bytes' => $upper['bytes'],
        ];
    }

    /**
     * @param array{lower: string, upper: string, lower_bytes?: string, upper_bytes?: string}|null $limits
     */
    private function nameTreeNameWithinLimits(string $name, ?array $limits, ?string $nameBytes = null): bool
    {
        if ($limits === null) {
            return true;
        }

        $candidate = $nameBytes ?? $name;
        $lower = $limits['lower_bytes'] ?? $limits['lower'];
        $upper = $limits['upper_bytes'] ?? $limits['upper'];

        return strcmp($lower, $upper) <= 0
            && strcmp($candidate, $lower) >= 0
            && strcmp($candidate, $upper) <= 0;
    }

    /**
     * @param list<mixed> $items
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array<int, mixed> $cache
     * @param array{lower: string, upper: string, lower_bytes?: string, upper_bytes?: string}|null $limits
     */
    private function nameTreeLimitsMatchAnyPairKey(array $items, array $objects, array &$cache, ?array $limits): bool
    {
        if ($limits === null || $items === []) {
            return true;
        }

        for ($index = 0, $count = count($items); $index + 1 < $count; $index += 2) {
            $name = $this->destinationNameDetails($items[$index], $objects, $cache);
            if ($name !== null && $this->nameTreeNameWithinLimits($name['text'], $limits, $name['bytes'])) {
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
        $destinationValue = $value;
        $destination = $this->resolve($destinationValue, $objects, $cache);
        if ($this->isDictionary($destination) && array_key_exists('D', $destination)) {
            if (array_key_exists('S', $destination)) {
                $actionType = $this->nameValue($this->resolve($destination['S'], $objects, $cache));
                if ($actionType !== 'GoTo') {
                    return null;
                }
            }

            $destinationValue = $destination['D'];
            $destination = $this->resolve($destinationValue, $objects, $cache);
        }

        $pageOnly = $this->pageOnlyDestinationDetails($destinationValue, $pageIndexes, $objects, $cache);
        if ($pageOnly !== null) {
            return [
                'name' => $name,
                'page' => $pageOnly['page'],
                'page_object_id' => $pageOnly['page_object_id'],
                'fit' => 'Fit',
                'coordinates' => [],
                'source' => $source,
            ];
        }

        if (!is_array($destination) || !array_is_list($destination) || count($destination) < 2) {
            return null;
        }

        $pageDetails = $this->pageOnlyDestinationDetails($destination[0] ?? null, $pageIndexes, $objects, $cache);
        if ($pageDetails === null) {
            return null;
        }

        $fit = $this->nameValue($this->resolve($destination[1] ?? null, $objects, $cache));
        if ($fit === null || !isset(self::VALID_DESTINATION_VIEW_NAMES[$fit])) {
            return null;
        }
        if (!$this->destinationViewCoordinateOperandsAreValid($fit, $destination, $objects, $cache)) {
            return null;
        }

        return [
            'name' => $name,
            'page' => $pageDetails['page'],
            'page_object_id' => $pageDetails['page_object_id'],
            'fit' => $fit,
            'coordinates' => $this->destinationCoordinates($fit, $destination, $objects, $cache),
            'source' => $source,
        ];
    }

    /**
     * @param array<string, int> $pageIndexes
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array<int, mixed> $cache
     * @param list<string> $seen
     * @return array{page: int, page_object_id: int|null}|null
     */
    private function pageOnlyDestinationDetails(
        mixed $value,
        array $pageIndexes,
        array $objects,
        array &$cache,
        array $seen = []
    ): ?array {
        $pageObjectId = $this->validRefObjectId($value, $objects);
        if ($pageObjectId !== null) {
            $generation = $this->refGeneration($value);
            $key = $this->objectGenerationKey($pageObjectId, $generation);
            if (isset($pageIndexes[$key])) {
                return [
                    'page' => $pageIndexes[$key],
                    'page_object_id' => $pageObjectId,
                ];
            }

            if (in_array($key, $seen, true)) {
                return null;
            }

            $seen[] = $key;
            $objectValue = $this->objectValue($pageObjectId, $objects, $cache, $generation);
            if ($this->isRefValue($objectValue) || is_int($objectValue)) {
                return $this->pageOnlyDestinationDetails($objectValue, $pageIndexes, $objects, $cache, $seen);
            }

            return null;
        }

        if ($this->isRefValue($value)) {
            return null;
        }

        $resolved = $this->resolve($value, $objects, $cache);
        if (!is_int($resolved) || $resolved < 0 || $resolved >= count($pageIndexes)) {
            return null;
        }

        return [
            'page' => $resolved,
            'page_object_id' => null,
        ];
    }

    /**
     * @param list<mixed> $destination
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array<int, mixed> $cache
     */
    private function destinationViewCoordinateOperandsAreValid(string $fit, array $destination, array $objects, array &$cache): bool
    {
        $requiredOperands = match ($fit) {
            'XYZ' => [2 => true, 3 => true, 4 => true],
            'FitH', 'FitBH', 'FitV', 'FitBV' => [2 => true],
            'FitR' => [2 => true, 3 => true, 4 => true, 5 => true],
            default => [],
        };

        foreach ($requiredOperands as $index => $allowsNull) {
            if (!array_key_exists($index, $destination)) {
                return false;
            }

            $value = $destination[$index];
            if ($this->isRefValue($value) && $this->validRefObjectId($value, $objects) === null) {
                return false;
            }

            $resolved = $this->resolve($value, $objects, $cache);
            if ($resolved === null) {
                if ($allowsNull) {
                    continue;
                }

                return false;
            }

            if (!is_int($resolved) && !is_float($resolved)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<mixed> $destination
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array<int, mixed> $cache
     * @return array<string, float|null>
     */
    private function destinationCoordinates(string $fit, array $destination, array $objects, array &$cache): array
    {
        return match ($fit) {
            'XYZ' => [
                'left' => $this->destinationCoordinate($destination, 2, $objects, $cache),
                'top' => $this->destinationCoordinate($destination, 3, $objects, $cache),
                'zoom' => $this->destinationCoordinate($destination, 4, $objects, $cache),
            ],
            'FitH', 'FitBH' => [
                'top' => $this->destinationCoordinate($destination, 2, $objects, $cache),
            ],
            'FitV', 'FitBV' => [
                'left' => $this->destinationCoordinate($destination, 2, $objects, $cache),
            ],
            'FitR' => [
                'left' => $this->destinationCoordinate($destination, 2, $objects, $cache),
                'bottom' => $this->destinationCoordinate($destination, 3, $objects, $cache),
                'right' => $this->destinationCoordinate($destination, 4, $objects, $cache),
                'top' => $this->destinationCoordinate($destination, 5, $objects, $cache),
            ],
            default => [],
        };
    }

    /**
     * @param list<mixed> $destination
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array<int, mixed> $cache
     */
    private function destinationCoordinate(array $destination, int $index, array $objects, array &$cache): ?float
    {
        return $this->nullableNumber($this->resolve($destination[$index] ?? null, $objects, $cache));
    }

    private function nullableNumber(mixed $value): ?float
    {
        return is_int($value) || is_float($value) ? (float) $value : null;
    }

    private function integerValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_float($value) ? (int) $value : null;
    }

    /**
     * @param array<string, mixed> $dictionary
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array<int, mixed> $cache
     */
    private function decodedStreamBytes(string $body, array $dictionary, array $objects, array $cache): ?string
    {
        $decoded = $this->streamBytesFromBody($body, $dictionary, $objects, $cache);
        if ($decoded === null) {
            return null;
        }

        foreach ($this->filterNames($dictionary['Filter'] ?? null, $objects, $cache) as $filter) {
            $decodedFilter = match ($filter) {
                'FlateDecode', 'Fl' => $this->decodeFlateStream($decoded),
                'ASCIIHexDecode', 'AHx' => $this->decodeAsciiHexStream($decoded),
                default => null,
            };
            if ($decodedFilter === null) {
                return null;
            }
            $decoded = $decodedFilter;
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $dictionary
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array<int, mixed> $cache
     */
    private function streamBytesFromBody(string $body, array $dictionary = [], array $objects = [], array $cache = []): ?string
    {
        if ($dictionary === []) {
            $tokens = $this->tokens($body);
            $index = 0;
            $value = $this->parseValue($tokens, $index);
            if (!$this->isDictionary($value)) {
                return null;
            }
            $dictionary = $value;
        }

        $streamOffset = $this->streamKeywordOffsetAfterDictionary($body);
        if ($streamOffset === false) {
            return null;
        }

        $start = $streamOffset + strlen('stream');
        if (substr($body, $start, 2) === "\r\n") {
            $start += 2;
        } elseif (($body[$start] ?? '') === "\n" || ($body[$start] ?? '') === "\r") {
            $start++;
        }

        $end = strpos($body, 'endstream', $start);
        if ($end === false || $end < $start) {
            return null;
        }

        $stream = substr($body, $start, $end - $start);
        $localCache = $cache;
        $length = $this->integerValue($this->resolve($dictionary['Length'] ?? null, $objects, $localCache));
        if ($length !== null && $length >= 0 && $length <= strlen($stream)) {
            return substr($stream, 0, $length);
        }

        return preg_replace("/\r\n$|\n$|\r$/", '', $stream) ?? $stream;
    }

    private function streamKeywordOffsetAfterDictionary(string $body): int|false
    {
        $dictionaryEnd = $this->topLevelDictionaryEndOffset($body);
        if ($dictionaryEnd === null) {
            return strpos($body, 'stream');
        }

        $offset = $this->skipPdfWhitespaceAndComments($body, $dictionaryEnd);
        if ($this->pdfKeywordAt($body, $offset, 'stream')) {
            return $offset;
        }

        while (($candidate = strpos($body, 'stream', $offset)) !== false) {
            if ($this->pdfKeywordAt($body, $candidate, 'stream')) {
                return $candidate;
            }

            $offset = $candidate + strlen('stream');
        }

        return false;
    }

    private function topLevelDictionaryEndOffset(string $body): ?int
    {
        $length = strlen($body);
        $offset = $this->skipPdfWhitespaceAndComments($body, 0);
        if (substr($body, $offset, 2) !== '<<') {
            return null;
        }

        $depth = 0;
        while ($offset < $length) {
            $char = $body[$offset];

            if ($char === '%') {
                $offset = $this->skipPdfComment($body, $offset);
                continue;
            }

            if ($char === '(') {
                $offset = $this->literalStringEndOffset($body, $offset + 1);
                continue;
            }

            if ($char === '<' && substr($body, $offset, 2) !== '<<') {
                $end = strpos($body, '>', $offset + 1);
                $offset = $end === false ? $length : $end + 1;
                continue;
            }

            if (substr($body, $offset, 2) === '<<') {
                $depth++;
                $offset += 2;
                continue;
            }

            if (substr($body, $offset, 2) === '>>') {
                $depth--;
                $offset += 2;
                if ($depth <= 0) {
                    return $offset;
                }
                continue;
            }

            $offset++;
        }

        return null;
    }

    private function literalStringEndOffset(string $body, int $offset): int
    {
        $depth = 1;
        $length = strlen($body);

        while ($offset < $length && $depth > 0) {
            $char = $body[$offset];
            if ($char === '\\') {
                $offset += 2;
                continue;
            }

            if ($char === '(') {
                $depth++;
                $offset++;
                continue;
            }

            if ($char === ')') {
                $depth--;
                $offset++;
                continue;
            }

            $offset++;
        }

        return $offset;
    }

    private function skipPdfWhitespaceAndComments(string $body, int $offset): int
    {
        $length = strlen($body);
        while ($offset < $length) {
            $char = $body[$offset];
            if (ctype_space($char)) {
                $offset++;
                continue;
            }

            if ($char === '%') {
                $offset = $this->skipPdfComment($body, $offset);
                continue;
            }

            break;
        }

        return $offset;
    }

    private function skipPdfComment(string $body, int $offset): int
    {
        $length = strlen($body);
        while ($offset < $length && !in_array($body[$offset], ["\r", "\n"], true)) {
            $offset++;
        }

        return $offset;
    }

    private function pdfKeywordAt(string $body, int $offset, string $keyword): bool
    {
        if (substr($body, $offset, strlen($keyword)) !== $keyword) {
            return false;
        }

        $before = $offset === 0 ? '' : $body[$offset - 1];
        $after = $body[$offset + strlen($keyword)] ?? '';

        return ($before === '' || $this->isDelimiter($before))
            && ($after === '' || $this->isDelimiter($after));
    }

    /**
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array<int, mixed> $cache
     * @return list<string>
     */
    private function filterNames(mixed $value, array $objects, array $cache): array
    {
        $localCache = $cache;
        $resolved = $this->resolve($value, $objects, $localCache);
        $name = $this->nameValue($resolved);
        if ($name !== null) {
            return [$name];
        }

        $filters = [];
        foreach ($this->arrayValues($resolved) as $filter) {
            $localCache = $cache;
            $filterName = $this->nameValue($this->resolve($filter, $objects, $localCache));
            if ($filterName !== null) {
                $filters[] = $filterName;
            }
        }

        return $filters;
    }

    private function decodeFlateStream(string $bytes): ?string
    {
        $decoded = @gzuncompress($bytes);
        if ($decoded === false) {
            $decoded = @gzinflate($bytes);
        }
        if ($decoded === false) {
            $decoded = @gzdecode($bytes);
        }

        return $decoded === false ? null : $decoded;
    }

    private function decodeAsciiHexStream(string $bytes): ?string
    {
        $body = strstr($bytes, '>', true);
        if ($body === false) {
            $body = $bytes;
        }

        $hex = preg_replace('/\s+/', '', $body);
        if ($hex === null || preg_match('/^[\da-fA-F]*$/', $hex) !== 1) {
            return null;
        }
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $decoded = hex2bin($hex);

        return $decoded === false ? null : $decoded;
    }

    /**
     * @param array<int, array{generation: int, body: string, generations: array<int, string>}> $objects
     * @param array<int, mixed> $cache
     * @return array{text: string, bytes: string}|null
     */
    private function destinationNameDetails(mixed $value, array $objects, array &$cache): ?array
    {
        $resolved = $this->resolve($value, $objects, $cache);

        return $this->pdfStringDetails($resolved);
    }

    /**
     * @return array{text: string, bytes: string}|null
     */
    private function pdfStringDetails(mixed $value): ?array
    {
        if (is_string($value)) {
            return [
                'text' => $value,
                'bytes' => $value,
            ];
        }

        if (is_array($value)
            && array_key_exists('__pdf_string', $value)
            && is_string($value['__pdf_string'])
            && array_key_exists('__pdf_bytes', $value)
            && is_string($value['__pdf_bytes'])
        ) {
            return [
                'text' => $value['__pdf_string'],
                'bytes' => $value['__pdf_bytes'],
            ];
        }

        return null;
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
            && !array_key_exists('__pdf_name', $value)
            && !array_key_exists('__pdf_string', $value);
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
                $tokens[] = ['type' => 'string', 'value' => $this->decodeTextString($string), 'bytes' => $string];
                continue;
            }
            if ($char === '<') {
                [$string, $offset] = $this->readHexString($source, $offset + 1);
                $tokens[] = ['type' => 'string', 'value' => $this->decodeTextString($string), 'bytes' => $string];
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
            'string' => [
                '__pdf_string' => (string) $token['value'],
                '__pdf_bytes' => is_string($token['bytes'] ?? null) ? $token['bytes'] : (string) $token['value'],
            ],
            'number' => $token['value'],
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
