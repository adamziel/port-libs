<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfLinkAnnotationExtractor
{
    /**
     * Native boundary for PDF page /Annots link actions.
     *
     * @return list<array{pnum: int, page_object: int, links: list<array{uri: string, rect: list<float>, annotation_object: int|null}>}>
     */
    public function extractPageLinks(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $actionReviewer = new PdfActionReviewExtractor($pdfBytes);
        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        $pages = [];

        foreach ($pageObjectNumbers as $pnum => $pageObjectNumber) {
            if (!isset($objects[$pageObjectNumber])) {
                continue;
            }

            $links = $this->linksFromPageObject($objects[$pageObjectNumber], $objects, $actionReviewer);
            if ($links === []) {
                continue;
            }

            $pages[] = [
                'pnum' => $pnum,
                'page_object' => $pageObjectNumber,
                'links' => $links,
            ];
        }

        return $pages;
    }

    /**
     * Applies extracted PDF link annotations to supplied Marker/pdftext page
     * spans by bbox intersection, preserving the original page/block shape.
     *
     * @param list<array<string, mixed>> $pages
     * @return list<array<string, mixed>>
     */
    public function applyLinksToPages(array $pages, string $pdfBytes): array
    {
        $linksByPage = [];
        foreach ($this->extractPageLinks($pdfBytes) as $pageLinks) {
            $linksByPage[$pageLinks['pnum']] = $pageLinks['links'];
        }

        $out = [];
        foreach (array_values($pages) as $index => $page) {
            if (!is_array($page)) {
                continue;
            }

            $pnum = isset($page['pnum']) ? (int) $page['pnum'] : $index;
            $links = $linksByPage[$pnum] ?? [];
            if ($links === []) {
                $out[] = $page;
                continue;
            }

            $page['links'] = $links;
            foreach (($page['blocks'] ?? []) as $blockIndex => $block) {
                if (!is_array($block)) {
                    continue;
                }
                foreach (($block['lines'] ?? []) as $lineIndex => $line) {
                    if (!is_array($line)) {
                        continue;
                    }
                    foreach (($line['spans'] ?? []) as $spanIndex => $span) {
                        if (!is_array($span)) {
                            continue;
                        }

                        $link = $this->linkForSpan($span, $links);
                        if ($link === null) {
                            continue;
                        }

                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_rect'] = $link['rect'];
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_annotation_object'] = $link['annotation_object'];
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_action_type'] = $link['action_type'];
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_safety'] = $link['safety'];
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_executes_on_import'] = false;
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_actions_review'] = $link['actions'];
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_additional_actions_review'] = $link['additional_actions'];

                        if (is_string($link['uri'] ?? null) && ($link['is_safe_uri'] ?? false) === true) {
                            $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_uri'] = $link['uri'];
                        }

                        if (array_key_exists('destination', $link)) {
                            $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_destination'] = $link['destination'];
                        }
                        if (array_key_exists('destination_page', $link)) {
                            $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_destination_page'] = $link['destination_page'];
                        }
                        if (array_key_exists('view_mode', $link)) {
                            $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_view_mode'] = $link['view_mode'];
                        }
                        if (array_key_exists('view_position', $link)) {
                            $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_view_position'] = $link['view_position'];
                        }
                        if (array_key_exists('view_parameters', $link)) {
                            $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_view_parameters'] = $link['view_parameters'];
                        }
                    }
                }
            }

            $out[] = $page;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $span
     * @param list<array<string, mixed>> $links
     * @return array<string, mixed>|null
     */
    private function linkForSpan(array $span, array $links): ?array
    {
        $bbox = $this->bbox($span['bbox'] ?? null);
        if ($bbox === null) {
            return null;
        }

        foreach ($links as $link) {
            if ($this->bboxesIntersect($bbox, $link['rect'])) {
                return $link;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function linksFromPageObject(string $pageBody, array $objects, PdfActionReviewExtractor $actionReviewer): array
    {
        $annotationBodies = $this->annotationBodiesForPage($pageBody, $objects);
        $links = [];

        foreach ($annotationBodies as $annotation) {
            $link = $this->linkFromAnnotationBody($annotation['body'], $actionReviewer, $annotation['object']);
            if ($link !== null) {
                $links[] = $link;
            }
        }

        return $links;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{body: string, object: int|null}>
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
     * @param array<int, string> $objects
     * @return list<array{body: string, object: int|null}>
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
     * @param array<int, string> $objects
     * @return list<array{body: string, object: int|null}>
     */
    private function annotationBodiesFromArray(?string $arrayBody, array $objects): array
    {
        if ($arrayBody === null) {
            return [];
        }

        $annotations = [];
        $offset = 0;
        $length = strlen($arrayBody);

        while ($offset < $length) {
            $this->skipWhitespace($arrayBody, $offset);
            if ($offset >= $length) {
                break;
            }

            if (substr($arrayBody, $offset, 2) === '<<') {
                $endOffset = null;
                $dictionary = $this->readPdfDictionaryAt($arrayBody, $offset, $endOffset);
                if ($dictionary === null || $endOffset === null) {
                    $offset++;
                    continue;
                }

                $annotations[] = ['body' => $dictionary, 'object' => null];
                $offset = $endOffset;
                continue;
            }

            if (preg_match('/\G(\d+)\s+\d+\s+R\b/s', $arrayBody, $match, 0, $offset) === 1) {
                $objectNumber = (int) $match[1];
                $dictionary = $this->dictionaryObjectBody($objects[$objectNumber] ?? '');
                if ($dictionary !== null) {
                    $annotations[] = ['body' => $dictionary, 'object' => $objectNumber];
                }
                $offset += strlen($match[0]);
                continue;
            }

            $offset++;
        }

        return $annotations;
    }

    private function skipWhitespace(string $value, int &$offset): void
    {
        while ($offset < strlen($value) && ctype_space($value[$offset])) {
            $offset++;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function linkFromAnnotationBody(string $annotationBody, PdfActionReviewExtractor $actionReviewer, ?int $annotationObject): ?array
    {
        if (preg_match('/\/Subtype\s*\/Link\b/', $annotationBody) !== 1) {
            return null;
        }

        $rect = $this->rectFromAnnotation($annotationBody);
        if ($rect === null) {
            return null;
        }

        $review = $actionReviewer->reviewAnnotationActions($annotationBody);
        $primary = $this->primaryLinkAction($review['actions']);
        if ($primary === null) {
            return null;
        }

        return $primary + [
            'rect' => $rect,
            'annotation_object' => $annotationObject,
            'actions' => $review['actions'],
            'additional_actions' => $review['additional_actions'],
            'executes_on_import' => false,
        ];
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @return array<string, mixed>|null
     */
    private function primaryLinkAction(array $actions): ?array
    {
        foreach ($actions as $action) {
            if (
                ($action['safety'] ?? null) === 'review-uri'
                || ($action['safety'] ?? null) === 'local-destination'
                || ($action['safety'] ?? null) === 'remote-document-review'
            ) {
                return $action;
            }
        }

        return null;
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

        return $this->normalizeRect(array_slice($numbers, 0, 4));
    }

    /**
     * @param list<float> $rect
     * @return list<float>
     */
    private function normalizeRect(array $rect): array
    {
        return [
            min($rect[0], $rect[2]),
            min($rect[1], $rect[3]),
            max($rect[0], $rect[2]),
            max($rect[1], $rect[3]),
        ];
    }

    /**
     * @param array<int, string> $objects
     */
    private function actionDictionaryBody(string $annotationBody, array $objects): ?string
    {
        $value = $this->valueAfterName($annotationBody, 'A');
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if (str_starts_with($value, '<<')) {
            return $this->readPdfDictionaryAt($value, 0);
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1) {
            $objectNumber = (int) $match[1];
            return isset($objects[$objectNumber]) ? $this->dictionaryObjectBody($objects[$objectNumber]) : null;
        }

        return null;
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

        $end = $offset;
        while ($end < strlen($body) && !ctype_space($body[$end]) && !str_contains('[]()<>{}/%', $body[$end])) {
            $end++;
        }
        if ($end < strlen($body) && $body[$end] === '/') {
            return substr($body, $offset, $end - $offset);
        }

        if (preg_match('/\G\d+\s+\d+\s+R\b/s', $body, $ref, 0, $offset) === 1) {
            return $ref[0];
        }

        return substr($body, $offset, max(0, $end - $offset));
    }

    private function stringAfterName(string $body, string $name): ?string
    {
        $offset = 0;
        while (preg_match('/\/' . preg_quote($name, '/') . '\b/s', $body, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $valueOffset = $match[0][1] + strlen($match[0][0]);
            while ($valueOffset < strlen($body) && ctype_space($body[$valueOffset])) {
                $valueOffset++;
            }

            if ($valueOffset >= strlen($body)) {
                return null;
            }

            if ($body[$valueOffset] === '(') {
                $endOffset = $this->skipLiteralString($body, $valueOffset);
                return $this->decodePdfStringBytes($this->decodeLiteralString(substr($body, $valueOffset + 1, $endOffset - $valueOffset - 2)));
            }

            if ($body[$valueOffset] === '<' && substr($body, $valueOffset, 2) !== '<<') {
                $endOffset = $this->skipHexString($body, $valueOffset);
                $hex = preg_replace('/\s+/', '', substr($body, $valueOffset + 1, $endOffset - $valueOffset - 2));
                if ($hex === null || $hex === '') {
                    return null;
                }
                if (strlen($hex) % 2 === 1) {
                    $hex .= '0';
                }
                $bytes = hex2bin($hex);
                return $bytes === false ? null : $this->decodePdfStringBytes($bytes);
            }

            $offset = $valueOffset;
        }

        return null;
    }

    private function isSafeUri(string $uri): bool
    {
        $trimmed = trim($uri);
        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $trimmed, $match) === 1) {
            return in_array(strtolower(rtrim($match[0], ':')), ['http', 'https', 'mailto', 'ftp'], true);
        }

        return str_starts_with($trimmed, '#') || str_starts_with($trimmed, '/') || str_starts_with($trimmed, './') || str_starts_with($trimmed, '../');
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

        return array_map('floatval', $matches[0]);
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

        return $this->normalizeRect($bbox);
    }

    /**
     * @param list<float> $left
     * @param list<float> $right
     */
    private function bboxesIntersect(array $left, array $right): bool
    {
        return min($left[2], $right[2]) - max($left[0], $right[0]) > 0.0
            && min($left[3], $right[3]) - max($left[1], $right[1]) > 0.0;
    }

    private function decodePdfStringBytes(string $bytes): string
    {
        $prefix = strtolower(bin2hex(substr($bytes, 0, 2)));
        if ($prefix === 'feff') {
            $decoded = iconv('UTF-16BE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }
        if ($prefix === 'fffe') {
            $decoded = iconv('UTF-16LE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }

        return $bytes;
    }

    private function decodeLiteralString(string $value): string
    {
        $value = preg_replace("/\\\\\r\n|\\\\\n|\\\\\r/s", '', $value) ?? $value;

        return preg_replace_callback('/\\\\([0-7]{1,3}|.)/s', static function (array $match): string {
            return match ($match[1]) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\x0c",
                '(' => '(',
                ')' => ')',
                '\\' => '\\',
                default => preg_match('/^[0-7]+$/', $match[1]) === 1 ? chr(octdec($match[1]) & 0xff) : $match[1],
            };
        }, $value) ?? $value;
    }
}
