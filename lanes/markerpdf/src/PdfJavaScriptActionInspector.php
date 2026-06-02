<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfJavaScriptActionInspector
{
    /**
     * Reviews document JavaScript actions without executing them.
     *
     * @return array{has_javascript: bool, executes_javascript: false, action_count: int, actions: list<array<string, mixed>>}
     */
    public function reviewDocumentActions(string $pdfBytes, int $previewBytes = 160): array
    {
        $objects = $this->parsedObjectValues($pdfBytes);
        $rawObjects = $this->rawObjects($pdfBytes);
        $catalog = $this->catalogDictionary($objects);
        if ($catalog === null) {
            return [
                'has_javascript' => false,
                'executes_javascript' => false,
                'action_count' => 0,
                'actions' => [],
            ];
        }

        $actions = [];
        $seen = [];

        $this->inspectNameTreeActions($catalog, $objects, $rawObjects, $actions, $seen, $previewBytes);

        if (array_key_exists('OpenAction', $catalog)) {
            $this->inspectActionValue(
                $catalog['OpenAction'],
                $objects,
                $rawObjects,
                ['source' => 'catalog_open_action'],
                $actions,
                $seen,
                $previewBytes
            );
        }

        $this->inspectAdditionalActions($catalog['AA'] ?? null, $objects, $rawObjects, [
            'source' => 'catalog_additional_action',
        ], $actions, $seen, $previewBytes);

        foreach ($this->orderedPageObjectNumbers($objects) as $pageIndex => $pageObjectNumber) {
            $page = $this->resolveDictionary($this->refValue($pageObjectNumber), $objects);
            if ($page === null) {
                continue;
            }

            $this->inspectAdditionalActions($page['AA'] ?? null, $objects, $rawObjects, [
                'source' => 'page_additional_action',
                'page' => $pageIndex,
                'page_object' => $pageObjectNumber,
            ], $actions, $seen, $previewBytes);

            foreach ($this->annotationDictionaries($page['Annots'] ?? null, $objects) as $annotation) {
                $annotationContext = [
                    'source' => 'annotation_action',
                    'page' => $pageIndex,
                    'page_object' => $pageObjectNumber,
                    'annotation_object' => $annotation['object'],
                ];
                $this->inspectActionValue($annotation['dictionary']['A'] ?? null, $objects, $rawObjects, $annotationContext, $actions, $seen, $previewBytes);

                $this->inspectAdditionalActions($annotation['dictionary']['AA'] ?? null, $objects, $rawObjects, [
                    'source' => 'annotation_additional_action',
                    'page' => $pageIndex,
                    'page_object' => $pageObjectNumber,
                    'annotation_object' => $annotation['object'],
                ], $actions, $seen, $previewBytes);
            }
        }

        return [
            'has_javascript' => $actions !== [],
            'executes_javascript' => false,
            'action_count' => count($actions),
            'actions' => $actions,
        ];
    }

    /**
     * @param array<string, mixed> $catalog
     * @param array<int, mixed> $objects
     * @param array<int, string> $rawObjects
     * @param list<array<string, mixed>> $actions
     * @param array<string, true> $seen
     */
    private function inspectNameTreeActions(
        array $catalog,
        array $objects,
        array $rawObjects,
        array &$actions,
        array &$seen,
        int $previewBytes
    ): void {
        $names = $this->resolveDictionary($catalog['Names'] ?? null, $objects);
        if ($names === null) {
            return;
        }

        $javascript = $this->resolveDictionary($names['JavaScript'] ?? null, $objects);
        if ($javascript === null) {
            return;
        }

        $this->collectNameTreeJavaScriptActions($javascript, $objects, $rawObjects, $actions, $seen, $previewBytes);
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, mixed> $objects
     * @param array<int, string> $rawObjects
     * @param list<array<string, mixed>> $actions
     * @param array<string, true> $seenActions
     * @param array<int, true> $seenNodes
     */
    private function collectNameTreeJavaScriptActions(
        array $node,
        array $objects,
        array $rawObjects,
        array &$actions,
        array &$seenActions,
        int $previewBytes,
        array $seenNodes = []
    ): void {
        $names = $this->resolveArray($node['Names'] ?? null, $objects);
        if ($names !== null) {
            for ($index = 0, $count = count($names); $index + 1 < $count; $index += 2) {
                $name = $this->stringOrNameValue($this->resolveValue($names[$index], $objects));
                if ($name === null || $name === '') {
                    continue;
                }

                $this->inspectActionValue(
                    $names[$index + 1],
                    $objects,
                    $rawObjects,
                    ['source' => 'document_name_tree', 'name' => $name],
                    $actions,
                    $seenActions,
                    $previewBytes
                );
            }
        }

        $kids = $this->resolveArray($node['Kids'] ?? null, $objects);
        if ($kids === null) {
            return;
        }

        foreach ($kids as $kid) {
            $objectNumber = $this->referenceObjectNumber($kid);
            if ($objectNumber !== null) {
                if (isset($seenNodes[$objectNumber])) {
                    continue;
                }
                $seenNodes[$objectNumber] = true;
            }

            $child = $this->resolveDictionary($kid, $objects);
            if ($child !== null) {
                $this->collectNameTreeJavaScriptActions($child, $objects, $rawObjects, $actions, $seenActions, $previewBytes, $seenNodes);
            }
        }
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, string> $rawObjects
     * @param array<string, mixed> $context
     * @param list<array<string, mixed>> $actions
     * @param array<string, true> $seen
     */
    private function inspectAdditionalActions(
        mixed $value,
        array $objects,
        array $rawObjects,
        array $context,
        array &$actions,
        array &$seen,
        int $previewBytes
    ): void {
        $dict = $this->resolveDictionary($value, $objects);
        if ($dict === null) {
            return;
        }

        foreach ($dict as $event => $actionValue) {
            $this->inspectActionValue(
                $actionValue,
                $objects,
                $rawObjects,
                $context + ['event' => $event],
                $actions,
                $seen,
                $previewBytes
            );
        }
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, string> $rawObjects
     * @param array<string, mixed> $context
     * @param list<array<string, mixed>> $actions
     * @param array<string, true> $seen
     */
    private function inspectActionValue(
        mixed $value,
        array $objects,
        array $rawObjects,
        array $context,
        array &$actions,
        array &$seen,
        int $previewBytes,
        int $depth = 0
    ): void {
        if ($value === null || $depth > 20) {
            return;
        }

        $array = $this->arrayItems($this->resolveValue($value, $objects));
        if ($array !== null) {
            foreach ($array as $item) {
                $this->inspectActionValue($item, $objects, $rawObjects, $context, $actions, $seen, $previewBytes, $depth + 1);
            }

            return;
        }

        $actionObject = $this->referenceObjectNumber($value);
        $dict = $this->resolveDictionary($value, $objects);
        if ($dict === null) {
            return;
        }

        $identity = $this->actionIdentity($value, $dict, $context, $objects);
        if (isset($seen[$identity])) {
            return;
        }
        $seen[$identity] = true;

        if ($this->nameValue($dict['S'] ?? null) === 'JavaScript') {
            $record = $context;
            if ($actionObject !== null) {
                $record['action_object'] = $actionObject;
            }

            $script = array_key_exists('JS', $dict) ? $this->scriptPayload($dict['JS'], $objects, $rawObjects, $previewBytes) : null;
            if ($script === null) {
                $record['script_missing'] = true;
            } else {
                $record += $script;
            }

            $actions[] = $record;
        }

        if (array_key_exists('Next', $dict)) {
            $nextContext = $context;
            $nextContext['chain_index'] = (int) ($context['chain_index'] ?? 0) + 1;
            $this->inspectActionValue($dict['Next'], $objects, $rawObjects, $nextContext, $actions, $seen, $previewBytes, $depth + 1);
        }
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, string> $rawObjects
     * @return array<string, mixed>|null
     */
    private function scriptPayload(mixed $value, array $objects, array $rawObjects, int $previewBytes): ?array
    {
        $scriptObject = $this->referenceObjectNumber($value);
        $script = $scriptObject !== null ? $this->streamPayload($scriptObject, $objects, $rawObjects) : null;
        $script ??= $this->stringOrNameValue($this->resolveValue($value, $objects));
        if ($script === null) {
            return null;
        }

        $preview = $this->scriptPreview($script, $previewBytes);
        $payload = [
            'script_preview' => $preview['preview'],
            'script_sha256' => hash('sha256', $script),
            'script_bytes' => strlen($script),
            'script_truncated' => $preview['truncated'],
        ];

        if ($scriptObject !== null) {
            $payload['script_object'] = $scriptObject;
        }

        return $payload;
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, string> $rawObjects
     */
    private function streamPayload(int $objectNumber, array $objects, array $rawObjects): ?string
    {
        $body = $rawObjects[$objectNumber] ?? null;
        if ($body === null || preg_match('/<<(.*?)>>\s*stream\r?\n?(.*?)\r?\n?endstream/s', $body, $match) !== 1) {
            return null;
        }

        $stream = $match[2];
        foreach ($this->streamFilters($match[1], $objects) as $filter) {
            $decoded = match ($filter) {
                'FlateDecode', 'Fl' => $this->decodeFlateStream($stream),
                'ASCIIHexDecode', 'AHx' => $this->decodeAsciiHexStream($stream),
                default => $stream,
            };
            if ($decoded === null) {
                return null;
            }
            $stream = $decoded;
        }

        return $this->decodePdfStringBytes($stream);
    }

    /**
     * @param array<int, mixed> $objects
     * @return list<array{dictionary: array<string, mixed>, object: int|null}>
     */
    private function annotationDictionaries(mixed $value, array $objects): array
    {
        $resolved = $this->resolveValue($value, $objects);
        $items = $this->arrayItems($resolved);
        if ($items === null) {
            $items = [$resolved];
        }

        $annotations = [];
        foreach ($items as $item) {
            $objectNumber = $this->referenceObjectNumber($item);
            $dict = $this->resolveDictionary($item, $objects);
            if ($dict !== null) {
                $annotations[] = ['dictionary' => $dict, 'object' => $objectNumber];
            }
        }

        return $annotations;
    }

    /**
     * @return array{preview: string, truncated: bool}
     */
    private function scriptPreview(string $script, int $previewBytes): array
    {
        $normalized = trim(preg_replace('/[\x00-\x1f\x7f]+/', ' ', $script) ?? $script);
        $limit = max(16, $previewBytes);
        if (strlen($normalized) <= $limit) {
            return ['preview' => $normalized, 'truncated' => false];
        }

        return ['preview' => substr($normalized, 0, $limit) . '...', 'truncated' => true];
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $dict
     * @param array<int, mixed> $objects
     */
    private function actionIdentity(mixed $value, array $dict, array $context, array $objects): string
    {
        $objectNumber = $this->referenceObjectNumber($value);
        $material = $objectNumber === null ? $dict : ['object' => $objectNumber];

        return hash('sha256', serialize([$context, $material]));
    }

    /**
     * @return array<int, mixed>
     */
    private function parsedObjectValues(string $pdfBytes): array
    {
        $values = [];
        foreach ($this->rawObjects($pdfBytes) as $objectNumber => $body) {
            $tokens = $this->tokens(trim($body));
            if ($tokens === []) {
                continue;
            }

            $index = 0;
            $values[$objectNumber] = $this->parseValue($tokens, $index);
        }

        return $values;
    }

    /**
     * @return array<int, string>
     */
    private function rawObjects(string $pdfBytes): array
    {
        $objects = [];
        if (!preg_match_all('/(\d+)\s+\d+\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER)) {
            return $objects;
        }

        foreach ($matches as $match) {
            $objects[(int) $match[1]] = $match[2];
        }

        return $objects;
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

        if ($this->nameValue($dict['Type'] ?? null) === 'Page') {
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

    /**
     * @param array<int, mixed> $objects
     * @return list<string>
     */
    private function streamFilters(string $dict, array $objects): array
    {
        if (!preg_match('/\/Filter\s*(?:\[(.*?)\]|\/([^\s\[\]()<>{}\/%]+)|(\d+)\s+\d+\s+R\b)/s', $dict, $match)) {
            return [];
        }

        if (($match[1] ?? '') !== '') {
            return $this->filterNamesFromValue($match[1], $objects);
        }

        if (($match[2] ?? '') !== '') {
            return [$this->decodePdfName($match[2])];
        }

        $objectNumber = isset($match[3]) ? (int) $match[3] : 0;
        return $objectNumber > 0 && isset($objects[$objectNumber])
            ? $this->filterNamesFromValue($this->stringOrNameValue($objects[$objectNumber]) ?? '', $objects)
            : [];
    }

    /**
     * @param array<int, mixed> $objects
     * @return list<string>
     */
    private function filterNamesFromValue(string $value, array $objects): array
    {
        preg_match_all('/\/([^\s\[\]()<>{}\/%]+)|(\d+)\s+\d+\s+R\b/', $value, $matches, PREG_SET_ORDER);
        $filters = [];
        foreach ($matches as $match) {
            if (($match[1] ?? '') !== '') {
                $filters[] = $this->decodePdfName($match[1]);
                continue;
            }

            $objectNumber = isset($match[2]) ? (int) $match[2] : 0;
            if ($objectNumber > 0 && isset($objects[$objectNumber])) {
                $value = $this->stringOrNameValue($objects[$objectNumber]);
                if ($value !== null) {
                    foreach ($this->filterNamesFromValue($value, $objects) as $filter) {
                        $filters[] = $filter;
                    }
                }
            }
        }

        return $filters;
    }

    private function decodeAsciiHexStream(string $stream): ?string
    {
        $hex = preg_replace('/[^0-9a-fA-F]/', '', strstr($stream, '>', true) ?: $stream);
        if ($hex === null) {
            return null;
        }
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $decoded = hex2bin($hex);
        return $decoded === false ? null : $decoded;
    }

    private function decodeFlateStream(string $stream): ?string
    {
        $decoded = @gzuncompress($stream);
        if ($decoded === false) {
            $decoded = @gzinflate($stream);
        }

        return $decoded === false ? null : $decoded;
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
