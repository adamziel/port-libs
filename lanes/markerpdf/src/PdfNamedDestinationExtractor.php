<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class PdfNamedDestinationExtractor
{
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
     * @return array<int, array{generation: int, body: string}>
     */
    private function pdfObjects(string $pdfBytes): array
    {
        $pdfBytes = $this->bytesThroughCurrentEof($pdfBytes);
        if (!preg_match_all('/(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $objects = [];
        foreach ($matches as $match) {
            $objects[(int) $match[1]] = [
                'generation' => (int) $match[2],
                'body' => $match[3],
            ];
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
     * @param array<int, array{generation: int, body: string}> $objects
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
     * @param array<int, array{generation: int, body: string}> $objects
     * @param array<int, mixed> $cache
     * @return array<int, int>
     */
    private function pageIndexesByObjectId(array $objects, array &$cache, array $catalog): array
    {
        $pages = [];
        $pagesObjectId = $this->validRefObjectId($catalog['Pages'] ?? null, $objects);
        if ($pagesObjectId !== null) {
            $pages = $this->collectPageObjectIds($pagesObjectId, $objects, $cache);
        }

        if ($pages === []) {
            foreach (array_keys($objects) as $objectId) {
                $dictionary = $this->objectDictionary($objectId, $objects, $cache);
                if ($dictionary !== null && $this->nameValue($dictionary['Type'] ?? null) === 'Page') {
                    $pages[] = $objectId;
                }
            }
        }

        $indexes = [];
        foreach (array_values($pages) as $index => $objectId) {
            $indexes[$objectId] = $index;
        }

        return $indexes;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param array<int, mixed> $cache
     * @param list<int> $seen
     * @return list<int>
     */
    private function collectPageObjectIds(int $objectId, array $objects, array &$cache, array $seen = []): array
    {
        if (in_array($objectId, $seen, true) || !isset($objects[$objectId])) {
            return [];
        }

        $seen[] = $objectId;
        $dictionary = $this->objectDictionary($objectId, $objects, $cache);
        if ($dictionary === null) {
            return [];
        }

        $type = $this->nameValue($dictionary['Type'] ?? null);
        if ($type === 'Page') {
            return [$objectId];
        }
        if ($type !== 'Pages') {
            return [];
        }

        $pages = [];
        foreach ($this->arrayRefs($dictionary['Kids'] ?? null, $objects) as $kidId) {
            foreach ($this->collectPageObjectIds($kidId, $objects, $cache, $seen) as $pageId) {
                $pages[] = $pageId;
            }
        }

        return $pages;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param array<int, mixed> $cache
     * @return array<string, mixed>|null
     */
    private function objectDictionary(int $objectId, array $objects, array &$cache): ?array
    {
        $value = $this->objectValue($objectId, $objects, $cache);
        $resolved = $this->resolve($value, $objects, $cache);

        return $this->isDictionary($resolved) ? $resolved : null;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param array<int, mixed> $cache
     */
    private function objectValue(int $objectId, array $objects, array &$cache): mixed
    {
        if (!isset($objects[$objectId])) {
            return null;
        }
        if (array_key_exists($objectId, $cache)) {
            return $cache[$objectId];
        }

        $tokens = $this->tokens($objects[$objectId]['body']);
        $index = 0;
        $cache[$objectId] = $this->parseValue($tokens, $index);

        return $cache[$objectId];
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param array<int, mixed> $cache
     * @param list<int> $seen
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
        if (in_array($objectId, $seen, true)) {
            return null;
        }

        $seen[] = $objectId;
        return $this->resolve($this->objectValue($objectId, $objects, $cache), $objects, $cache, $seen);
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
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
        $names = $dictionary['Names'] ?? null;
        if (is_array($names) && array_is_list($names)) {
            for ($index = 0; $index + 1 < count($names); $index += 2) {
                $name = $this->destinationNameValue($names[$index], $objects, $cache);
                if ($name === null || $name === '' || !$this->nameTreeNameWithinLimits($name, $limits)) {
                    continue;
                }

                $entries[] = [
                    'name' => $name,
                    'value' => $names[$index + 1],
                ];
            }
        }

        foreach ($this->arrayValues($dictionary['Kids'] ?? null) as $kid) {
            foreach ($this->collectNameTreeEntries($kid, $objects, $cache, $seenObjects, $limits, $depth + 1) as $entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, array{generation: int, body: string}> $objects
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
     * @param array<int, array{generation: int, body: string}> $objects
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
     * @param array<int, int> $pageIndexes
     * @param array<int, array{generation: int, body: string}> $objects
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
            $pageIndex = $pageIndexes[$pageObjectId] ?? null;
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
     * @param array<int, array{generation: int, body: string}> $objects
     * @param array<int, mixed> $cache
     */
    private function destinationNameValue(mixed $value, array $objects, array &$cache): ?string
    {
        return $this->destinationName($this->resolve($value, $objects, $cache));
    }

    /**
     * @return list<int>
     */
    private function arrayRefs(mixed $value, array $objects): array
    {
        $refs = [];
        foreach ($this->arrayValues($value) as $entry) {
            $objectId = $this->validRefObjectId($entry, $objects);
            if ($objectId !== null) {
                $refs[] = $objectId;
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

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     */
    private function validRefObjectId(mixed $value, array $objects): ?int
    {
        $objectId = $this->refObjectId($value);
        if ($objectId === null || !isset($objects[$objectId])) {
            return null;
        }

        return $objects[$objectId]['generation'] === $this->refGeneration($value)
            ? $objectId
            : null;
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

        return $bytes;
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
