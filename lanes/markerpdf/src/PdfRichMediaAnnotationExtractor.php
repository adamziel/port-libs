<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfRichMediaAnnotationExtractor
{
    private const REVIEW_SUBTYPES = [
        '3D' => true,
        'Movie' => true,
        'RichMedia' => true,
        'Screen' => true,
        'Sound' => true,
    ];

    /**
     * Native review boundary for interactive PDF media annotations.
     *
     * The port records page-local metadata for rich media/screen annotations
     * without executing media actions, JavaScript, launch actions, or embedded
     * players.
     *
     * @return list<array{pnum: int, page_object: int, annotations: list<array<string, mixed>>}>
     */
    public function extractReviewAnnotations(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $pages = [];

        foreach ($this->orderedPageObjectNumbers($objects) as $pnum => $pageObjectNumber) {
            if (!isset($objects[$pageObjectNumber])) {
                continue;
            }

            $annotations = [];
            foreach ($this->annotationBodiesForPage($objects[$pageObjectNumber], $objects) as $annotation) {
                $review = $this->reviewAnnotationFromBody($annotation['body'], $objects, $annotation['object']);
                if ($review !== null) {
                    $annotations[] = $review;
                }
            }

            if ($annotations === []) {
                continue;
            }

            $pages[] = [
                'pnum' => $pnum,
                'page_object' => $pageObjectNumber,
                'annotations' => $annotations,
            ];
        }

        return $pages;
    }

    /**
     * @return list<array{body: string, object: int|null}>
     * @param array<int, string> $objects
     */
    private function annotationBodiesForPage(string $pageBody, array $objects): array
    {
        $annots = $this->valueAfterName($pageBody, 'Annots');
        if ($annots === null) {
            return [];
        }

        return $this->annotationBodiesFromValue($annots, $objects);
    }

    /**
     * @return list<array{body: string, object: int|null}>
     * @param array<int, string> $objects
     */
    private function annotationBodiesFromValue(string $value, array $objects): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1) {
            $objectNumber = (int) $match[1];
            if (!isset($objects[$objectNumber])) {
                return [];
            }

            $objectBody = trim($objects[$objectNumber]);
            if (str_starts_with($objectBody, '[')) {
                return $this->annotationBodiesFromArray($this->arrayBodyFromValue($objectBody), $objects);
            }

            $dictionary = $this->dictionaryObjectBody($objectBody);
            return $dictionary === null ? [] : [['body' => $dictionary, 'object' => $objectNumber]];
        }

        if (str_starts_with($value, '[')) {
            return $this->annotationBodiesFromArray($this->arrayBodyFromValue($value), $objects);
        }

        if (str_starts_with($value, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($value, 0);
            return $dictionary === null ? [] : [['body' => $dictionary, 'object' => null]];
        }

        return [];
    }

    /**
     * @return list<array{body: string, object: int|null}>
     * @param array<int, string> $objects
     */
    private function annotationBodiesFromArray(?string $arrayBody, array $objects): array
    {
        if ($arrayBody === null) {
            return [];
        }

        $annotations = [];
        foreach ($this->objectReferences($arrayBody) as $objectNumber) {
            if (!isset($objects[$objectNumber])) {
                continue;
            }

            $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
            if ($dictionary !== null) {
                $annotations[] = ['body' => $dictionary, 'object' => $objectNumber];
            }
        }

        foreach ($this->directDictionaries($arrayBody) as $dictionary) {
            $annotations[] = ['body' => $dictionary, 'object' => null];
        }

        return $annotations;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function reviewAnnotationFromBody(string $annotationBody, array $objects, ?int $annotationObject): ?array
    {
        $subtype = $this->nameValueAfterName($annotationBody, 'Subtype');
        if ($subtype === null || !isset(self::REVIEW_SUBTYPES[$subtype])) {
            return null;
        }

        $actionDictionaries = $this->actionDictionaries($annotationBody, $objects);
        $contextBodies = $this->dedupeStrings(array_merge(
            [$annotationBody],
            $actionDictionaries,
            $this->referencedDictionaryBodies($annotationBody, $objects, 3),
            ...array_map(fn (string $body): array => $this->referencedDictionaryBodies($body, $objects, 2), $actionDictionaries)
        ));
        $actionTypes = $this->dedupeStrings(array_values(array_filter(
            array_map(fn (string $body): ?string => $this->nameValueAfterName($body, 'S'), $actionDictionaries),
            static fn (?string $value): bool => $value !== null && $value !== ''
        )));
        $actionUris = $this->dedupeStrings(array_merge(
            ...array_map(fn (string $body): array => $this->stringsAfterName($body, 'URI'), $actionDictionaries)
        ));
        $assetNames = $this->assetNamesFromBodies($contextBodies);
        $fileNames = $this->fileNamesFromBodies($contextBodies);

        return [
            'subtype' => $subtype,
            'annotation_object' => $annotationObject,
            'rect' => $this->rectFromAnnotation($annotationBody),
            'title' => $this->stringAfterName($annotationBody, 'T'),
            'contents' => $this->stringAfterName($annotationBody, 'Contents'),
            'alternate_text' => $this->stringAfterName($annotationBody, 'Alt'),
            'action_types' => $actionTypes,
            'action_uris' => $actionUris,
            'asset_names' => $assetNames,
            'file_names' => $fileNames,
            'has_appearance' => $this->valueAfterName($annotationBody, 'AP') !== null,
            'has_rich_media_content' => $this->valueAfterName($annotationBody, 'RichMediaContent') !== null,
            'requires_review' => true,
            'executes_media' => false,
            'executes_javascript' => false,
        ];
    }

    /**
     * @return list<string>
     * @param array<int, string> $objects
     */
    private function actionDictionaries(string $annotationBody, array $objects): array
    {
        $actions = [];

        $action = $this->valueAfterName($annotationBody, 'A');
        if ($action !== null) {
            array_push($actions, ...$this->dictionariesFromValue($action, $objects));
        }

        $additionalActions = $this->valueAfterName($annotationBody, 'AA');
        if ($additionalActions !== null) {
            foreach ($this->dictionariesFromValue($additionalActions, $objects) as $dictionary) {
                foreach ($this->directDictionaries($dictionary) as $nestedDictionary) {
                    if ($this->nameValueAfterName($nestedDictionary, 'S') !== null) {
                        $actions[] = $nestedDictionary;
                    }
                }

                foreach ($this->referencedDictionaryBodies($dictionary, $objects, 1) as $referencedDictionary) {
                    if ($this->nameValueAfterName($referencedDictionary, 'S') !== null) {
                        $actions[] = $referencedDictionary;
                    }
                }
            }
        }

        return $this->dedupeStrings($actions);
    }

    /**
     * @return list<string>
     * @param array<int, string> $objects
     */
    private function dictionariesFromValue(string $value, array $objects): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (str_starts_with($value, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($value, 0);
            return $dictionary === null ? [] : [$dictionary];
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1) {
            $objectNumber = (int) $match[1];
            if (!isset($objects[$objectNumber])) {
                return [];
            }

            $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
            return $dictionary === null ? [] : [$dictionary];
        }

        return [];
    }

    /**
     * @return list<string>
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function referencedDictionaryBodies(string $body, array $objects, int $depth, array $seen = []): array
    {
        if ($depth <= 0) {
            return [];
        }

        $bodies = [];
        foreach ($this->objectReferences($body) as $objectNumber) {
            if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
                continue;
            }

            $seen[$objectNumber] = true;
            $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
            if ($dictionary === null) {
                continue;
            }

            $bodies[] = $dictionary;
            array_push($bodies, ...$this->referencedDictionaryBodies($dictionary, $objects, $depth - 1, $seen));
        }

        return $bodies;
    }

    /**
     * @param list<string> $bodies
     * @return list<string>
     */
    private function assetNamesFromBodies(array $bodies): array
    {
        $names = [];
        foreach ($bodies as $body) {
            $namesValue = $this->valueAfterName($body, 'Names');
            if ($namesValue === null || !str_starts_with(trim($namesValue), '[')) {
                continue;
            }

            $arrayBody = $this->arrayBodyFromValue($namesValue);
            if ($arrayBody !== null) {
                array_push($names, ...$this->stringsInValue($arrayBody));
            }
        }

        return $this->dedupeStrings($names);
    }

    /**
     * @param list<string> $bodies
     * @return list<string>
     */
    private function fileNamesFromBodies(array $bodies): array
    {
        $names = [];
        foreach ($bodies as $body) {
            array_push($names, ...$this->stringsAfterName($body, 'F'));
            array_push($names, ...$this->stringsAfterName($body, 'UF'));
        }

        return $this->dedupeStrings($names);
    }

    /**
     * @return list<float>|null
     */
    private function rectFromAnnotation(string $annotationBody): ?array
    {
        $value = $this->valueAfterName($annotationBody, 'Rect');
        if ($value === null || !str_starts_with(trim($value), '[')) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromValue($value);
        if ($arrayBody === null) {
            return null;
        }

        $numbers = $this->numbersFromPdfArray($arrayBody);
        if (count($numbers) < 4) {
            return null;
        }

        $rect = array_slice($numbers, 0, 4);
        return [
            min($rect[0], $rect[2]),
            min($rect[1], $rect[3]),
            max($rect[0], $rect[2]),
            max($rect[1], $rect[3]),
        ];
    }

    private function valueAfterName(string $body, string $name): ?string
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\b/s', $body, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $offset = $match[0][1] + strlen($match[0][0]);
        while ($offset < strlen($body) && ctype_space($body[$offset])) {
            $offset++;
        }

        if ($offset >= strlen($body)) {
            return null;
        }

        if ($body[$offset] === '[') {
            $endOffset = null;
            $this->readPdfArrayAt($body, $offset, $endOffset);
            return $endOffset === null ? null : substr($body, $offset, $endOffset - $offset);
        }

        if (substr($body, $offset, 2) === '<<') {
            $endOffset = null;
            $this->readPdfDictionaryAt($body, $offset, $endOffset);
            return $endOffset === null ? null : substr($body, $offset, $endOffset - $offset);
        }

        if ($body[$offset] === '(') {
            $endOffset = $this->skipLiteralString($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        if ($body[$offset] === '<') {
            $endOffset = $this->skipHexString($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        if (preg_match('/\G\d+\s+\d+\s+R\b/s', $body, $ref, 0, $offset) === 1) {
            return $ref[0];
        }

        $end = $offset;
        while ($end < strlen($body) && !ctype_space($body[$end]) && !str_contains('[]()<>{}/%', $body[$end])) {
            $end++;
        }
        if ($end < strlen($body) && $body[$end] === '/') {
            return substr($body, $offset, $end - $offset);
        }

        return substr($body, $offset, max(0, $end - $offset));
    }

    private function nameValueAfterName(string $body, string $name): ?string
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\s*\/([^\s\[\]()<>{}\/%]+)/s', $body, $match) !== 1) {
            return null;
        }

        return $this->decodePdfName($match[1]);
    }

    private function stringAfterName(string $body, string $name): ?string
    {
        $strings = $this->stringsAfterName($body, $name);
        return $strings[0] ?? null;
    }

    /**
     * @return list<string>
     */
    private function stringsAfterName(string $body, string $name): array
    {
        $strings = [];
        $offset = 0;
        while (preg_match('/\/' . preg_quote($name, '/') . '\b/s', $body, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $valueOffset = $match[0][1] + strlen($match[0][0]);
            while ($valueOffset < strlen($body) && ctype_space($body[$valueOffset])) {
                $valueOffset++;
            }

            $decoded = $this->stringAtOffset($body, $valueOffset);
            if ($decoded !== null) {
                $strings[] = $decoded['text'];
                $offset = $decoded['end'];
                continue;
            }

            $offset = $match[0][1] + strlen($match[0][0]);
        }

        return $strings;
    }

    /**
     * @return list<string>
     */
    private function stringsInValue(string $value): array
    {
        $strings = [];
        for ($offset = 0, $length = strlen($value); $offset < $length; $offset++) {
            $decoded = $this->stringAtOffset($value, $offset);
            if ($decoded === null) {
                continue;
            }

            $strings[] = $decoded['text'];
            $offset = $decoded['end'] - 1;
        }

        return $strings;
    }

    /**
     * @return array{text: string, end: int}|null
     */
    private function stringAtOffset(string $body, int $offset): ?array
    {
        if ($offset >= strlen($body)) {
            return null;
        }

        if ($body[$offset] === '(') {
            $endOffset = $this->skipLiteralString($body, $offset);
            return [
                'text' => $this->decodePdfStringBytes($this->decodeLiteralString(substr($body, $offset + 1, $endOffset - $offset - 2))),
                'end' => $endOffset,
            ];
        }

        if ($body[$offset] === '<' && substr($body, $offset, 2) !== '<<') {
            $endOffset = $this->skipHexString($body, $offset);
            $hex = preg_replace('/\s+/', '', substr($body, $offset + 1, $endOffset - $offset - 2));
            if ($hex === null || $hex === '') {
                return null;
            }
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }
            $bytes = hex2bin($hex);
            if ($bytes === false) {
                return null;
            }

            return [
                'text' => $this->decodePdfStringBytes($bytes),
                'end' => $endOffset,
            ];
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function pdfObjects(string $pdfBytes): array
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
     * @return list<int>
     * @param array<int, string> $objects
     */
    private function orderedPageObjectNumbers(array $objects): array
    {
        foreach ($objects as $body) {
            if (preg_match('/\/Type\s*\/Catalog\b/', $body) !== 1 || preg_match('/\/Pages\s+(\d+)\s+\d+\s+R\b/s', $body, $match) !== 1) {
                continue;
            }

            $pages = $this->pageObjectNumbersFromTree((int) $match[1], $objects);
            if ($pages !== []) {
                return $pages;
            }
        }

        $pages = [];
        foreach ($objects as $objectNumber => $body) {
            if (preg_match('/\/Type\s*\/Page\b/', $body) === 1) {
                $pages[] = $objectNumber;
            }
        }

        return $pages;
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function pageObjectNumbersFromTree(int $objectNumber, array $objects, array $seen = []): array
    {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return [];
        }

        $seen[$objectNumber] = true;
        $body = $objects[$objectNumber];
        if (preg_match('/\/Type\s*\/Page\b/', $body) === 1) {
            return [$objectNumber];
        }

        $kids = $this->valueAfterName($body, 'Kids');
        if ($kids === null || !str_starts_with(trim($kids), '[')) {
            return [];
        }

        $arrayBody = $this->arrayBodyFromValue($kids);
        if ($arrayBody === null) {
            return [];
        }

        $pages = [];
        foreach ($this->objectReferences($arrayBody) as $childObjectNumber) {
            foreach ($this->pageObjectNumbersFromTree($childObjectNumber, $objects, $seen) as $pageObjectNumber) {
                $pages[] = $pageObjectNumber;
            }
        }

        return $pages;
    }

    /**
     * @return list<int>
     */
    private function objectReferences(string $value): array
    {
        if (!preg_match_all('/(\d+)\s+\d+\s+R\b/', $value, $matches)) {
            return [];
        }

        return array_map('intval', $matches[1]);
    }

    private function dictionaryObjectBody(string $objectBody): ?string
    {
        $offset = strpos($objectBody, '<<');
        return $offset === false ? null : $this->readPdfDictionaryAt($objectBody, $offset);
    }

    /**
     * @return list<string>
     */
    private function directDictionaries(string $value): array
    {
        $dictionaries = [];
        $offset = 0;
        while (($start = strpos($value, '<<', $offset)) !== false) {
            $endOffset = null;
            $body = $this->readPdfDictionaryAt($value, $start, $endOffset);
            if ($body === null || $endOffset === null) {
                break;
            }
            $dictionaries[] = $body;
            $offset = $endOffset;
        }

        return $dictionaries;
    }

    private function arrayBodyFromValue(string $value): ?string
    {
        $offset = strpos($value, '[');
        return $offset === false ? null : $this->readPdfArrayAt($value, $offset);
    }

    private function readPdfDictionaryAt(string $value, int $offset, ?int &$endOffset = null): ?string
    {
        if (substr($value, $offset, 2) !== '<<') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 2;
        for ($index = $offset, $length = strlen($value); $index < $length - 1; $index++) {
            $char = $value[$index];
            if ($char === '(') {
                $index = $this->skipLiteralString($value, $index) - 1;
                continue;
            }
            if ($char === '<' && substr($value, $index, 2) !== '<<') {
                $index = $this->skipHexString($value, $index) - 1;
                continue;
            }

            $pair = substr($value, $index, 2);
            if ($pair === '<<') {
                $depth++;
                $index++;
                continue;
            }

            if ($pair !== '>>') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                $endOffset = $index + 2;
                return substr($value, $bodyStart, $index - $bodyStart);
            }
            $index++;
        }

        return null;
    }

    private function readPdfArrayAt(string $value, int $offset, ?int &$endOffset = null): ?string
    {
        if (($value[$offset] ?? '') !== '[') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 1;
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '(') {
                $index = $this->skipLiteralString($value, $index) - 1;
                continue;
            }
            if ($char === '<' && substr($value, $index, 2) === '<<') {
                $endDictionary = null;
                $this->readPdfDictionaryAt($value, $index, $endDictionary);
                if ($endDictionary !== null) {
                    $index = $endDictionary - 1;
                    continue;
                }
            }
            if ($char === '<') {
                $index = $this->skipHexString($value, $index) - 1;
                continue;
            }
            if ($char === '[') {
                $depth++;
                continue;
            }
            if ($char !== ']') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                $endOffset = $index + 1;
                return substr($value, $bodyStart, $index - $bodyStart);
            }
        }

        return null;
    }

    private function skipLiteralString(string $value, int $offset): int
    {
        $depth = 0;
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '\\') {
                $index++;
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char !== ')') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return $index + 1;
            }
        }

        return strlen($value);
    }

    private function skipHexString(string $value, int $offset): int
    {
        $end = strpos($value, '>', $offset + 1);
        return $end === false ? strlen($value) : $end + 1;
    }

    /**
     * @return list<float>
     */
    private function numbersFromPdfArray(string $arrayBody): array
    {
        if (!preg_match_all('/[+-]?(?:\d+(?:\.\d*)?|\.\d+)/', $arrayBody, $matches)) {
            return [];
        }

        return array_map(static fn (string $value): float => (float) $value, $matches[0]);
    }

    private function decodeLiteralString(string $value): string
    {
        $out = '';
        for ($index = 0, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char !== '\\') {
                $out .= $char;
                continue;
            }

            $index++;
            if ($index >= $length) {
                break;
            }

            $escaped = $value[$index];
            if (str_contains("nrtbf()\\", $escaped)) {
                $out .= match ($escaped) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'b' => "\x08",
                    'f' => "\f",
                    default => $escaped,
                };
                continue;
            }

            if ($escaped === "\r" || $escaped === "\n") {
                if ($escaped === "\r" && ($value[$index + 1] ?? '') === "\n") {
                    $index++;
                }
                continue;
            }

            if ($escaped >= '0' && $escaped <= '7') {
                $octal = $escaped;
                for ($extra = 0; $extra < 2 && ($value[$index + 1] ?? '') >= '0' && ($value[$index + 1] ?? '') <= '7'; $extra++) {
                    $index++;
                    $octal .= $value[$index];
                }
                $out .= chr(octdec($octal) & 0xff);
                continue;
            }

            $out .= $escaped;
        }

        return $out;
    }

    private function decodePdfStringBytes(string $bytes): string
    {
        if (str_starts_with($bytes, "\xfe\xff")) {
            $decoded = iconv('UTF-16BE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }

        if (str_starts_with($bytes, "\xff\xfe")) {
            $decoded = iconv('UTF-16LE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }

        return preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f]+/', '', $bytes) ?? $bytes;
    }

    private function decodePdfName(string $name): string
    {
        return preg_replace_callback('/#([0-9A-Fa-f]{2})/', static fn (array $match): string => chr(hexdec($match[1])), $name) ?? $name;
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private function dedupeStrings(array $values): array
    {
        $seen = [];
        $out = [];
        foreach ($values as $value) {
            if ($value === '' || isset($seen[$value])) {
                continue;
            }

            $seen[$value] = true;
            $out[] = $value;
        }

        return $out;
    }
}
