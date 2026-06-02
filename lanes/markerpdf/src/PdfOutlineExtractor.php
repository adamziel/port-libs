<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfOutlineExtractor
{
    /**
     * Native boundary for marker.cleaners.toc::get_pdf_toc when the PDF outline
     * uses named destinations that pypdfium would normally resolve for us.
     *
     * @return list<array{title: string, level: int, page: int, destination: string|null}>
     */
    public function getPdfToc(string $pdfBytes, int $maxDepth = 15): array
    {
        $objects = $this->parsedObjectValues($pdfBytes);
        $catalog = $this->catalogDictionary($objects);
        if ($catalog === null) {
            return [];
        }

        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        if ($pageObjectNumbers === []) {
            return [];
        }

        $pageIndexes = [];
        foreach ($pageObjectNumbers as $index => $objectNumber) {
            $pageIndexes[$objectNumber] = $index;
        }

        $outlineRoot = $this->resolveDictionary($catalog['Outlines'] ?? null, $objects);
        if ($outlineRoot === null) {
            return [];
        }

        return $this->outlineItems(
            $outlineRoot['First'] ?? null,
            $objects,
            $pageIndexes,
            $this->destinationMap($catalog, $objects),
            max(1, $maxDepth)
        );
    }

    /**
     * @return array<int, mixed>
     */
    private function parsedObjectValues(string $pdfBytes): array
    {
        $values = [];
        if (!preg_match_all('/(\d+)\s+\d+\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER)) {
            return $values;
        }

        foreach ($matches as $match) {
            $tokens = $this->tokens(trim($match[2]));
            if ($tokens === []) {
                continue;
            }

            $index = 0;
            $values[(int) $match[1]] = $this->parseValue($tokens, $index);
        }

        return $values;
    }

    /**
     * @param array<int, mixed> $objects
     * @return array<string, mixed>|null
     */
    private function catalogDictionary(array $objects): ?array
    {
        foreach ($objects as $value) {
            $dict = $this->dictionaryItems($value);
            if ($dict !== null && $this->nameValue($dict['Type'] ?? null) === 'Catalog') {
                return $dict;
            }
        }

        return null;
    }

    /**
     * @param array<int, mixed> $objects
     * @return list<int>
     */
    private function orderedPageObjectNumbers(array $objects): array
    {
        $catalog = $this->catalogDictionary($objects);
        if ($catalog !== null) {
            $pagesRoot = $this->referenceObjectNumber($catalog['Pages'] ?? null);
            if ($pagesRoot !== null) {
                $pages = $this->pageObjectNumbersFromTree($pagesRoot, $objects);
                if ($pages !== []) {
                    return $pages;
                }
            }
        }

        $pages = [];
        foreach ($objects as $objectNumber => $value) {
            $dict = $this->dictionaryItems($value);
            if ($dict !== null && $this->nameValue($dict['Type'] ?? null) === 'Page') {
                $pages[] = $objectNumber;
            }
        }

        return $pages;
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, true> $seen
     * @return list<int>
     */
    private function pageObjectNumbersFromTree(int $objectNumber, array $objects, array $seen = []): array
    {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return [];
        }
        $seen[$objectNumber] = true;

        $dict = $this->resolveDictionary($this->refValue($objectNumber), $objects);
        if ($dict === null) {
            return [];
        }

        $type = $this->nameValue($dict['Type'] ?? null);
        if ($type === 'Page') {
            return [$objectNumber];
        }

        $kids = $this->resolveArray($dict['Kids'] ?? null, $objects);
        if ($kids === null) {
            return [];
        }

        $pages = [];
        foreach ($kids as $kid) {
            $kidObjectNumber = $this->referenceObjectNumber($kid);
            if ($kidObjectNumber === null) {
                continue;
            }

            foreach ($this->pageObjectNumbersFromTree($kidObjectNumber, $objects, $seen) as $pageObjectNumber) {
                $pages[] = $pageObjectNumber;
            }
        }

        return $pages;
    }

    /**
     * @param array<string, mixed> $catalog
     * @param array<int, mixed> $objects
     * @return array<string, mixed>
     */
    private function destinationMap(array $catalog, array $objects): array
    {
        $destinations = [];

        $legacyDests = $this->resolveDictionary($catalog['Dests'] ?? null, $objects);
        if ($legacyDests !== null) {
            foreach ($legacyDests as $name => $destination) {
                $destinations[$name] = $destination;
            }
        }

        $names = $this->resolveDictionary($catalog['Names'] ?? null, $objects);
        $nameTreeRoot = $names === null ? null : $this->resolveDictionary($names['Dests'] ?? null, $objects);
        if ($nameTreeRoot !== null) {
            $this->collectNameTreeDestinations($nameTreeRoot, $objects, $destinations);
        }

        return $destinations;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, mixed> $objects
     * @param array<string, mixed> $destinations
     * @param array<int, true> $seen
     */
    private function collectNameTreeDestinations(array $node, array $objects, array &$destinations, array $seen = []): void
    {
        $names = $this->resolveArray($node['Names'] ?? null, $objects);
        if ($names !== null) {
            for ($index = 0, $count = count($names); $index + 1 < $count; $index += 2) {
                $name = $this->stringOrNameValue($names[$index]);
                if ($name !== null) {
                    $destinations[$name] = $names[$index + 1];
                }
            }
        }

        $kids = $this->resolveArray($node['Kids'] ?? null, $objects);
        if ($kids === null) {
            return;
        }

        foreach ($kids as $kid) {
            $objectNumber = $this->referenceObjectNumber($kid);
            if ($objectNumber !== null) {
                if (isset($seen[$objectNumber])) {
                    continue;
                }
                $seen[$objectNumber] = true;
            }

            $child = $this->resolveDictionary($kid, $objects);
            if ($child !== null) {
                $this->collectNameTreeDestinations($child, $objects, $destinations, $seen);
            }
        }
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param array<string, mixed> $destinations
     * @param array<int, true> $seen
     * @return list<array{title: string, level: int, page: int, destination: string|null}>
     */
    private function outlineItems(
        mixed $firstItem,
        array $objects,
        array $pageIndexes,
        array $destinations,
        int $maxDepth,
        int $level = 1,
        array $seen = []
    ): array {
        if ($level > $maxDepth) {
            return [];
        }

        $items = [];
        $current = $this->referenceObjectNumber($firstItem);
        while ($current !== null && !isset($seen[$current])) {
            $seen[$current] = true;
            $dict = $this->resolveDictionary($this->refValue($current), $objects);
            if ($dict === null) {
                break;
            }

            $title = $this->stringOrNameValue($this->resolveValue($dict['Title'] ?? null, $objects));
            $destination = $this->outlineDestination($dict, $objects);
            $page = $this->destinationPageIndex($destination['value'], $objects, $pageIndexes, $destinations);
            if ($title !== null && $page !== null) {
                $items[] = [
                    'title' => $title,
                    'level' => $level,
                    'page' => $page,
                    'destination' => $destination['name'],
                ];
            }

            if ($level < $maxDepth) {
                foreach ($this->outlineItems($dict['First'] ?? null, $objects, $pageIndexes, $destinations, $maxDepth, $level + 1, $seen) as $child) {
                    $items[] = $child;
                }
            }

            $current = $this->referenceObjectNumber($dict['Next'] ?? null);
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $outline
     * @param array<int, mixed> $objects
     * @return array{name: string|null, value: mixed}
     */
    private function outlineDestination(array $outline, array $objects): array
    {
        if (array_key_exists('Dest', $outline)) {
            return [
                'name' => $this->stringOrNameValue($outline['Dest']),
                'value' => $outline['Dest'],
            ];
        }

        $action = $this->resolveDictionary($outline['A'] ?? null, $objects);
        if ($action === null || $this->nameValue($action['S'] ?? null) !== 'GoTo' || !array_key_exists('D', $action)) {
            return ['name' => null, 'value' => null];
        }

        return [
            'name' => $this->stringOrNameValue($action['D']),
            'value' => $action['D'],
        ];
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param array<string, mixed> $destinations
     * @param array<string, true> $seenNames
     */
    private function destinationPageIndex(
        mixed $destination,
        array $objects,
        array $pageIndexes,
        array $destinations,
        array $seenNames = []
    ): ?int {
        $pageObjectNumber = $this->referenceObjectNumber($destination);
        if ($pageObjectNumber !== null && isset($pageIndexes[$pageObjectNumber])) {
            return $pageIndexes[$pageObjectNumber];
        }

        $resolved = $this->resolveValue($destination, $objects);
        $name = $this->stringOrNameValue($resolved);
        if ($name !== null) {
            if (isset($seenNames[$name]) || !array_key_exists($name, $destinations)) {
                return null;
            }
            $seenNames[$name] = true;

            return $this->destinationPageIndex($destinations[$name], $objects, $pageIndexes, $destinations, $seenNames);
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict !== null && array_key_exists('D', $dict)) {
            return $this->destinationPageIndex($dict['D'], $objects, $pageIndexes, $destinations, $seenNames);
        }

        $array = $this->arrayItems($resolved);
        if ($array === null || $array === []) {
            return null;
        }

        $first = $array[0];
        $pageObjectNumber = $this->referenceObjectNumber($first);
        if ($pageObjectNumber !== null) {
            return $pageIndexes[$pageObjectNumber] ?? null;
        }

        if (is_int($first) && $first >= 0) {
            return $first;
        }

        return $this->destinationPageIndex($first, $objects, $pageIndexes, $destinations, $seenNames);
    }

    /**
     * @return list<string>
     */
    private function tokens(string $value): array
    {
        $tokens = [];
        $length = strlen($value);
        for ($index = 0; $index < $length;) {
            $char = $value[$index];
            if (ctype_space($char)) {
                $index++;
                continue;
            }

            if ($char === '%') {
                while ($index < $length && $value[$index] !== "\n" && $value[$index] !== "\r") {
                    $index++;
                }
                continue;
            }

            $pair = substr($value, $index, 2);
            if ($pair === '<<' || $pair === '>>') {
                $tokens[] = $pair;
                $index += 2;
                continue;
            }

            if ($char === '[' || $char === ']') {
                $tokens[] = $char;
                $index++;
                continue;
            }

            if ($char === '(') {
                $tokens[] = $this->readLiteralToken($value, $index);
                continue;
            }

            if ($char === '<') {
                $tokens[] = $this->readHexToken($value, $index);
                continue;
            }

            if ($char === '/') {
                $start = $index;
                $index++;
                while ($index < $length && !$this->isDelimiter($value[$index])) {
                    $index++;
                }
                $tokens[] = substr($value, $start, $index - $start);
                continue;
            }

            $start = $index;
            while ($index < $length && !$this->isDelimiter($value[$index])) {
                $index++;
            }
            $tokens[] = substr($value, $start, $index - $start);
        }

        return array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
    }

    /**
     * @param list<string> $tokens
     */
    private function parseValue(array $tokens, int &$index): mixed
    {
        $token = $tokens[$index] ?? null;
        if ($token === null) {
            return null;
        }

        if ($token === '<<') {
            $index++;
            $items = [];
            while (($tokens[$index] ?? null) !== null && $tokens[$index] !== '>>') {
                $key = $tokens[$index] ?? '';
                $index++;
                if (!is_string($key) || !str_starts_with($key, '/')) {
                    continue;
                }

                $items[$this->decodePdfName(substr($key, 1))] = $this->parseValue($tokens, $index);
            }
            if (($tokens[$index] ?? null) === '>>') {
                $index++;
            }

            return ['pdfType' => 'dict', 'items' => $items];
        }

        if ($token === '[') {
            $index++;
            $items = [];
            while (($tokens[$index] ?? null) !== null && $tokens[$index] !== ']') {
                $items[] = $this->parseValue($tokens, $index);
            }
            if (($tokens[$index] ?? null) === ']') {
                $index++;
            }

            return ['pdfType' => 'array', 'items' => $items];
        }

        if (
            preg_match('/^[+-]?\d+$/', $token) === 1
            && preg_match('/^[+-]?\d+$/', (string) ($tokens[$index + 1] ?? '')) === 1
            && ($tokens[$index + 2] ?? null) === 'R'
        ) {
            $index += 3;

            return ['pdfType' => 'ref', 'object' => (int) $token];
        }

        $index++;
        if (str_starts_with($token, '/')) {
            return ['pdfType' => 'name', 'value' => $this->decodePdfName(substr($token, 1))];
        }

        if (str_starts_with($token, '(')) {
            return ['pdfType' => 'string', 'value' => $this->decodeLiteralString($token)];
        }

        if (str_starts_with($token, '<')) {
            return ['pdfType' => 'string', 'value' => $this->decodeHexString($token)];
        }

        if ($token === 'null') {
            return null;
        }

        if ($token === 'true' || $token === 'false') {
            return $token === 'true';
        }

        if (preg_match('/^[+-]?\d+$/', $token) === 1) {
            return (int) $token;
        }

        if (is_numeric($token)) {
            return (float) $token;
        }

        return ['pdfType' => 'keyword', 'value' => $token];
    }

    private function readLiteralToken(string $value, int &$index): string
    {
        $start = $index;
        $depth = 0;
        $length = strlen($value);
        while ($index < $length) {
            $char = $value[$index];
            if ($char === '\\') {
                $index += 2;
                continue;
            }

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $index++;
                    break;
                }
            }
            $index++;
        }

        return substr($value, $start, $index - $start);
    }

    private function readHexToken(string $value, int &$index): string
    {
        $start = $index;
        $length = strlen($value);
        $index++;
        while ($index < $length && $value[$index] !== '>') {
            $index++;
        }
        if ($index < $length) {
            $index++;
        }

        return substr($value, $start, $index - $start);
    }

    private function isDelimiter(string $char): bool
    {
        return ctype_space($char) || str_contains('[]()<>{}/%', $char);
    }

    /**
     * @param array<int, mixed> $objects
     */
    private function resolveValue(mixed $value, array $objects, int $depth = 0): mixed
    {
        $objectNumber = $this->referenceObjectNumber($value);
        if ($objectNumber === null || $depth > 20 || !array_key_exists($objectNumber, $objects)) {
            return $value;
        }

        return $this->resolveValue($objects[$objectNumber], $objects, $depth + 1);
    }

    /**
     * @param array<int, mixed> $objects
     * @return array<string, mixed>|null
     */
    private function resolveDictionary(mixed $value, array $objects): ?array
    {
        return $this->dictionaryItems($this->resolveValue($value, $objects));
    }

    /**
     * @param array<int, mixed> $objects
     * @return list<mixed>|null
     */
    private function resolveArray(mixed $value, array $objects): ?array
    {
        return $this->arrayItems($this->resolveValue($value, $objects));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dictionaryItems(mixed $value): ?array
    {
        return is_array($value) && ($value['pdfType'] ?? null) === 'dict' && is_array($value['items'] ?? null)
            ? $value['items']
            : null;
    }

    /**
     * @return list<mixed>|null
     */
    private function arrayItems(mixed $value): ?array
    {
        return is_array($value) && ($value['pdfType'] ?? null) === 'array' && is_array($value['items'] ?? null)
            ? array_values($value['items'])
            : null;
    }

    private function referenceObjectNumber(mixed $value): ?int
    {
        return is_array($value) && ($value['pdfType'] ?? null) === 'ref' && is_int($value['object'] ?? null)
            ? $value['object']
            : null;
    }

    /**
     * @return array{pdfType: string, object: int}
     */
    private function refValue(int $objectNumber): array
    {
        return ['pdfType' => 'ref', 'object' => $objectNumber];
    }

    private function nameValue(mixed $value): ?string
    {
        return is_array($value) && ($value['pdfType'] ?? null) === 'name' && is_string($value['value'] ?? null)
            ? $value['value']
            : null;
    }

    private function stringOrNameValue(mixed $value): ?string
    {
        if (is_array($value) && ($value['pdfType'] ?? null) === 'string' && is_string($value['value'] ?? null)) {
            return $value['value'];
        }

        return $this->nameValue($value);
    }

    private function decodePdfName(string $name): string
    {
        return preg_replace_callback('/#([\da-fA-F]{2})/', static function (array $match): string {
            return chr(hexdec($match[1]));
        }, $name) ?? $name;
    }

    private function decodeLiteralString(string $token): string
    {
        $bytes = substr($token, 1, -1);
        $decoded = '';
        $length = strlen($bytes);
        for ($index = 0; $index < $length; $index++) {
            $char = $bytes[$index];
            if ($char !== '\\') {
                $decoded .= $char;
                continue;
            }

            if ($index + 1 >= $length) {
                break;
            }
            $next = $bytes[++$index];
            if ($next === "\r" || $next === "\n") {
                if ($next === "\r" && ($bytes[$index + 1] ?? '') === "\n") {
                    $index++;
                }
                continue;
            }
            if (preg_match('/[0-7]/', $next) === 1) {
                $octal = $next;
                for ($count = 0; $count < 2 && preg_match('/[0-7]/', (string) ($bytes[$index + 1] ?? '')) === 1; $count++) {
                    $octal .= $bytes[++$index];
                }
                $decoded .= chr(octdec($octal) & 0xff);
                continue;
            }

            $decoded .= match ($next) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\x0c",
                default => $next,
            };
        }

        return $this->decodePdfStringBytes($decoded);
    }

    private function decodeHexString(string $token): string
    {
        $hex = preg_replace('/\s+/', '', substr($token, 1, -1));
        if ($hex === null || $hex === '' || preg_match('/^[\da-fA-F]+$/', $hex) !== 1) {
            return '';
        }
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $bytes = hex2bin($hex);
        return $bytes === false ? '' : $this->decodePdfStringBytes($bytes);
    }

    private function decodePdfStringBytes(string $bytes): string
    {
        if (str_starts_with($bytes, "\xFE\xFF")) {
            $decoded = iconv('UTF-16BE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }

        if (str_starts_with($bytes, "\xFF\xFE")) {
            $decoded = iconv('UTF-16LE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }

        return $bytes;
    }
}
