<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfPagePropertyExtractor
{
    /**
     * Native boundary for page-scoped /PieceInfo and tagged-PDF /UserProperties
     * review metadata. The rows are non-executing metadata only.
     *
     * @return list<array<string, mixed>>
     */
    public function extractPageReviewMetadata(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $catalog = $this->catalogObjectBody($pdfBytes, $objects);
        if ($catalog === null) {
            return [];
        }

        $pageObjectNumbers = $this->orderedPageObjectNumbers($catalog, $objects);
        if ($pageObjectNumbers === []) {
            return [];
        }

        $markInfoUserProperties = $this->markInfoUserProperties($catalog, $objects);
        $userPropertiesByPage = $markInfoUserProperties
            ? $this->structureUserPropertiesByPageObject($catalog, $objects)
            : [];

        $pages = [];
        foreach ($pageObjectNumbers as $pnum => $pageObjectNumber) {
            $pageBody = $this->dictionaryObjectBody($objects[$pageObjectNumber] ?? '');
            if ($pageBody === null) {
                continue;
            }

            $pieceInfo = $this->pieceInfoMetadata($this->dictionaryRawValue($pageBody, 'PieceInfo'), $objects);
            $userProperties = $userPropertiesByPage[$pageObjectNumber] ?? [];
            if ($pieceInfo === [] && $userProperties === []) {
                continue;
            }

            $page = [
                'pnum' => $pnum,
                'page_object' => $pageObjectNumber,
            ];

            if ($pieceInfo !== []) {
                $page['piece_info'] = $pieceInfo;
            }

            if ($userProperties !== []) {
                $page['mark_info_user_properties'] = $markInfoUserProperties;
                $page['user_properties'] = array_values($userProperties);
            }

            $pages[] = $page;
        }

        return $pages;
    }

    /**
     * @param array<int, string> $objects
     */
    private function markInfoUserProperties(string $catalog, array $objects): bool
    {
        $markInfo = $this->resolveDictionaryFromValue($this->dictionaryRawValue($catalog, 'MarkInfo'), $objects);
        if ($markInfo === null) {
            return false;
        }

        return $this->reviewValueFromRaw($this->dictionaryRawValue($markInfo['body'], 'UserProperties'), $objects) === true;
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, list<array<string, mixed>>>
     */
    private function structureUserPropertiesByPageObject(string $catalog, array $objects): array
    {
        $structTreeRoot = $this->resolveDictionaryFromValue($this->dictionaryRawValue($catalog, 'StructTreeRoot'), $objects);
        if ($structTreeRoot === null) {
            return [];
        }

        $propertiesByPage = [];
        $this->collectStructureUserProperties(
            $this->dictionaryRawValue($structTreeRoot['body'], 'K'),
            $objects,
            $propertiesByPage,
            [],
            null
        );

        return $propertiesByPage;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array<string, mixed>>> $propertiesByPage
     * @param array<int, true> $seenObjects
     */
    private function collectStructureUserProperties(
        ?string $value,
        array $objects,
        array &$propertiesByPage,
        array $seenObjects,
        ?int $inheritedPageObject
    ): void
    {
        if ($value === null) {
            return;
        }

        $resolved = trim($this->resolveRawValue($value, $objects) ?? $value);
        if ($resolved === '') {
            return;
        }

        if (str_starts_with($resolved, '[')) {
            foreach ($this->arrayItemsFromValue($resolved, $objects) as $item) {
                $this->collectStructureUserProperties($item, $objects, $propertiesByPage, $seenObjects, $inheritedPageObject);
            }
            return;
        }

        $struct = $this->resolveDictionaryFromValue($value, $objects);
        if ($struct === null) {
            return;
        }

        $objectNumber = $struct['object'];
        if ($objectNumber !== null) {
            if (isset($seenObjects[$objectNumber])) {
                return;
            }
            $seenObjects[$objectNumber] = true;
        }

        $body = $struct['body'];
        $kidValue = $this->dictionaryRawValue($body, 'K');
        $pageObject = $this->objectReferenceValueAfterName($body, 'Pg')
            ?? $this->pageObjectFromStructureKid($kidValue, $objects)
            ?? $inheritedPageObject;
        $structType = $this->dictionaryNameValue($body, 'S', $objects);
        $title = $this->dictionaryStringValue($body, 'T', $objects);

        if ($pageObject !== null) {
            foreach ($this->userPropertiesFromAttributeValue($this->dictionaryRawValue($body, 'A'), $objects, $structType, $title) as $property) {
                $propertiesByPage[$pageObject][] = $property;
            }
        }

        if ($kidValue !== null) {
            $this->collectStructureUserProperties($kidValue, $objects, $propertiesByPage, $seenObjects, $pageObject);
        }
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function userPropertiesFromAttributeValue(?string $attributeValue, array $objects, ?string $structType, ?string $title): array
    {
        $properties = [];
        foreach ($this->dictionariesFromValue($attributeValue, $objects) as $attribute) {
            $body = $attribute['body'];
            if ($this->dictionaryNameValue($body, 'O', $objects) !== 'UserProperties') {
                continue;
            }

            foreach ($this->dictionariesFromValue($this->dictionaryRawValue($body, 'P'), $objects) as $propertyDictionary) {
                $name = $this->dictionaryStringValue($propertyDictionary['body'], 'N', $objects);
                if ($name === null || $name === '') {
                    continue;
                }

                $property = [
                    'source' => 'structure_user_properties',
                    'name' => $name,
                    'hidden' => $this->reviewValueFromRaw($this->dictionaryRawValue($propertyDictionary['body'], 'H'), $objects) === true,
                ];

                if ($attribute['object'] !== null) {
                    $property['attribute_object'] = $attribute['object'];
                }
                if ($structType !== null && $structType !== '') {
                    $property['struct_type'] = $structType;
                }
                if ($title !== null && $title !== '') {
                    $property['title'] = $title;
                }

                $value = $this->reviewValueFromRaw($this->dictionaryRawValue($propertyDictionary['body'], 'V'), $objects);
                if ($value !== null) {
                    $property['value'] = $value;
                }

                $formattedValue = $this->reviewValueFromRaw($this->dictionaryRawValue($propertyDictionary['body'], 'F'), $objects);
                if ($formattedValue !== null && $formattedValue !== '') {
                    $property['formatted_value'] = $formattedValue;
                }

                $properties[] = $property;
            }
        }

        return $properties;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{body: string, object: int|null}>
     */
    private function dictionariesFromValue(?string $value, array $objects): array
    {
        if ($value === null) {
            return [];
        }

        $resolved = trim($this->resolveRawValue($value, $objects) ?? $value);
        if ($resolved === '') {
            return [];
        }

        if (str_starts_with($resolved, '[')) {
            $dictionaries = [];
            foreach ($this->arrayItemsFromValue($resolved, $objects) as $item) {
                $dictionary = $this->resolveDictionaryFromValue($item, $objects);
                if ($dictionary !== null) {
                    $dictionaries[] = $dictionary;
                }
            }

            return $dictionaries;
        }

        $dictionary = $this->resolveDictionaryFromValue($value, $objects);
        return $dictionary === null ? [] : [$dictionary];
    }

    /**
     * @param array<int, string> $objects
     */
    private function pageObjectFromStructureKid(?string $value, array $objects, int $depth = 0): ?int
    {
        if ($value === null || $depth > 5) {
            return null;
        }

        $resolved = trim($this->resolveRawValue($value, $objects) ?? $value);
        if ($resolved === '') {
            return null;
        }

        if (str_starts_with($resolved, '[')) {
            foreach ($this->arrayItemsFromValue($resolved, $objects) as $item) {
                $pageObject = $this->pageObjectFromStructureKid($item, $objects, $depth + 1);
                if ($pageObject !== null) {
                    return $pageObject;
                }
            }
            return null;
        }

        $dictionary = $this->resolveDictionaryFromValue($value, $objects);
        if ($dictionary === null) {
            return null;
        }

        return $this->objectReferenceValueAfterName($dictionary['body'], 'Pg')
            ?? $this->pageObjectFromStructureKid($this->dictionaryRawValue($dictionary['body'], 'K'), $objects, $depth + 1);
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, array<string, mixed>>
     */
    private function pieceInfoMetadata(?string $pieceInfoValue, array $objects): array
    {
        $pieceInfo = $this->resolveDictionaryFromValue($pieceInfoValue, $objects);
        if ($pieceInfo === null) {
            return [];
        }

        $metadata = [];
        foreach ($this->dictionaryEntries($pieceInfo['body']) as $application => $pieceValue) {
            $piece = $this->resolveDictionaryFromValue($pieceValue, $objects);
            if ($piece === null) {
                continue;
            }

            $entry = [];
            $lastModified = $this->dictionaryStringValue($piece['body'], 'LastModified', $objects);
            if ($lastModified !== null && $lastModified !== '') {
                $entry['last_modified'] = $lastModified;
            }

            $private = $this->resolveDictionaryFromValue($this->dictionaryRawValue($piece['body'], 'Private'), $objects);
            if ($private !== null) {
                $privateMetadata = [];
                foreach ($this->dictionaryEntries($private['body']) as $name => $privateValue) {
                    $reviewValue = $this->reviewValueFromRaw($privateValue, $objects);
                    if ($reviewValue !== null && $reviewValue !== '') {
                        $privateMetadata[$name] = $reviewValue;
                    }
                }

                if ($privateMetadata !== []) {
                    $entry['private'] = $privateMetadata;
                }
            }

            if ($entry !== []) {
                $metadata[$application] = $entry;
            }
        }

        return $metadata;
    }

    /**
     * @return array<int, string>
     */
    private function pdfObjects(string $pdfBytes): array
    {
        $matched = preg_match_all('/(\d+)\s+\d+\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER);
        if ($matched === false || $matched === 0) {
            return [];
        }

        $objects = [];
        foreach ($matches as $match) {
            $objects[(int) $match[1]] = $match[2];
        }

        return $objects;
    }

    /**
     * @param array<int, string> $objects
     */
    private function catalogObjectBody(string $pdfBytes, array $objects): ?string
    {
        $trailer = $this->trailerDictionaryBody($pdfBytes);
        if ($trailer !== null) {
            $root = $this->objectReferenceValueAfterName($trailer, 'Root');
            if ($root !== null && isset($objects[$root])) {
                return $this->dictionaryObjectBody($objects[$root]);
            }
        }

        foreach ($objects as $body) {
            if (preg_match('/\/Type\s*\/Catalog\b/s', $body) === 1) {
                return $this->dictionaryObjectBody($body);
            }
        }

        return null;
    }

    private function trailerDictionaryBody(string $pdfBytes): ?string
    {
        $body = null;
        $offset = 0;
        while (($position = strpos($pdfBytes, 'trailer', $offset)) !== false) {
            $dictionaryOffset = strpos($pdfBytes, '<<', $position);
            if ($dictionaryOffset === false) {
                break;
            }

            $candidate = $this->readPdfDictionaryAt($pdfBytes, $dictionaryOffset);
            if ($candidate !== null) {
                $body = $candidate['body'];
            }
            $offset = $position + 7;
        }

        return $body;
    }

    /**
     * @param array<int, string> $objects
     * @return list<int>
     */
    private function orderedPageObjectNumbers(string $catalog, array $objects): array
    {
        $pagesObjectNumber = $this->objectReferenceValueAfterName($catalog, 'Pages');
        if ($pagesObjectNumber !== null) {
            $pages = $this->pageObjectNumbersFromTree($pagesObjectNumber, $objects);
            if ($pages !== []) {
                return $pages;
            }
        }

        $pages = [];
        foreach ($objects as $objectNumber => $body) {
            if (preg_match('/\/Type\s*\/Page\b/s', $body) === 1) {
                $pages[] = $objectNumber;
            }
        }

        return $pages;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return list<int>
     */
    private function pageObjectNumbersFromTree(int $objectNumber, array $objects, array $seen = []): array
    {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return [];
        }

        $seen[$objectNumber] = true;
        $body = $objects[$objectNumber];
        if (preg_match('/\/Type\s*\/Page\b/s', $body) === 1) {
            return [$objectNumber];
        }

        $dictionary = $this->dictionaryObjectBody($body);
        if ($dictionary === null) {
            return [];
        }

        $kids = $this->dictionaryRawValue($dictionary, 'Kids');
        if ($kids === null) {
            return [];
        }

        $pages = [];
        foreach ($this->arrayItemsFromValue($kids, $objects) as $kidValue) {
            $childObjectNumber = $this->objectNumberFromReference($kidValue);
            if ($childObjectNumber === null) {
                continue;
            }

            foreach ($this->pageObjectNumbersFromTree($childObjectNumber, $objects, $seen) as $pageObjectNumber) {
                $pages[] = $pageObjectNumber;
            }
        }

        return $pages;
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function arrayItemsFromValue(string $value, array $objects): array
    {
        $resolved = $this->resolveRawValue($value, $objects);
        if ($resolved === null) {
            return [];
        }

        $array = $this->readPdfArrayAt(trim($resolved), 0);
        if ($array === null) {
            return [];
        }

        $items = [];
        $body = substr($array['raw'], 1, -1);
        for ($offset = 0, $length = strlen($body); $offset < $length;) {
            $offset = $this->skipWhitespace($body, $offset);
            if ($offset >= $length) {
                break;
            }

            $item = $this->readPdfValueAt($body, $offset);
            if ($item === null) {
                $offset++;
                continue;
            }

            $items[] = $item['raw'];
            $offset = $item['end'];
        }

        return $items;
    }

    /**
     * @param array<int, string> $objects
     * @return array{body: string, object: int|null}|null
     */
    private function resolveDictionaryFromValue(?string $value, array $objects): ?array
    {
        if ($value === null) {
            return null;
        }

        $objectNumber = $this->objectNumberFromReference($value);
        if ($objectNumber !== null) {
            if (!isset($objects[$objectNumber])) {
                return null;
            }

            $body = $this->dictionaryObjectBody($objects[$objectNumber]);
            return $body === null ? null : ['body' => $body, 'object' => $objectNumber];
        }

        $resolved = $this->resolveRawValue($value, $objects);
        if ($resolved === null) {
            return null;
        }

        $dictionary = $this->readPdfDictionaryAt(trim($resolved), 0);
        return $dictionary === null ? null : ['body' => $dictionary['body'], 'object' => null];
    }

    /**
     * @param array<int, string> $objects
     */
    private function resolveRawValue(string $value, array $objects): ?string
    {
        $trimmed = trim($value);
        $objectNumber = $this->objectNumberFromReference($trimmed);
        if ($objectNumber === null) {
            return $trimmed;
        }

        return $objects[$objectNumber] ?? null;
    }

    private function dictionaryObjectBody(string $objectBody): ?string
    {
        $offset = strpos($objectBody, '<<');
        if ($offset === false) {
            return null;
        }

        $dictionary = $this->readPdfDictionaryAt($objectBody, $offset);
        return $dictionary === null ? null : $dictionary['body'];
    }

    private function dictionaryRawValue(string $dictionary, string $key): ?string
    {
        if (preg_match('/\/' . preg_quote($key, '/') . '\b/s', $dictionary, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $offset = $match[0][1] + strlen($match[0][0]);
        $value = $this->readPdfValueAt($dictionary, $offset);

        return $value === null ? null : $value['raw'];
    }

    /**
     * @param array<int, string> $objects
     */
    private function dictionaryStringValue(string $dictionary, string $key, array $objects): ?string
    {
        $value = $this->dictionaryRawValue($dictionary, $key);
        return $value === null ? null : $this->stringValueFromRaw($value, $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function dictionaryNameValue(string $dictionary, string $key, array $objects): ?string
    {
        $value = $this->dictionaryRawValue($dictionary, $key);
        if ($value === null) {
            return null;
        }

        $resolved = trim($this->resolveRawValue($value, $objects) ?? $value);
        if (preg_match('/^\/([^\s\[\]()<>{}\/%]+)/', $resolved, $match) === 1) {
            return $this->decodePdfName($match[1]);
        }

        return $this->stringValueFromRaw($resolved, $objects);
    }

    private function objectReferenceValueAfterName(string $dictionary, string $key): ?int
    {
        $value = $this->dictionaryRawValue($dictionary, $key);
        return $value === null ? null : $this->objectNumberFromReference($value);
    }

    private function objectNumberFromReference(string $value): ?int
    {
        return preg_match('/^(\d+)\s+\d+\s+R\b/s', trim($value), $match) === 1 ? (int) $match[1] : null;
    }

    /**
     * @return array<string, string>
     */
    private function dictionaryEntries(string $dictionary): array
    {
        $entries = [];
        for ($offset = 0, $length = strlen($dictionary); $offset < $length;) {
            $offset = $this->skipWhitespace($dictionary, $offset);
            if ($offset >= $length) {
                break;
            }

            if (($dictionary[$offset] ?? '') !== '/') {
                $offset++;
                continue;
            }

            $remaining = substr($dictionary, $offset);
            if (preg_match('/\/([^\s\[\]()<>{}\/%]+)/A', $remaining, $match) !== 1) {
                $offset++;
                continue;
            }

            $name = $this->decodePdfName($match[1]);
            $value = $this->readPdfValueAt($dictionary, $offset + strlen($match[0]));
            if ($value === null) {
                $offset += strlen($match[0]);
                continue;
            }

            $entries[$name] = $value['raw'];
            $offset = $value['end'];
        }

        return $entries;
    }

    /**
     * @param array<int, string> $objects
     */
    private function reviewValueFromRaw(?string $value, array $objects, int $depth = 0): mixed
    {
        if ($value === null || $depth > 4) {
            return null;
        }

        $resolved = trim($this->resolveRawValue($value, $objects) ?? $value);
        if ($resolved === '') {
            return null;
        }

        if (str_starts_with($resolved, '[')) {
            $items = [];
            foreach ($this->arrayItemsFromValue($resolved, $objects) as $item) {
                $reviewValue = $this->reviewValueFromRaw($item, $objects, $depth + 1);
                if ($reviewValue !== null) {
                    $items[] = $reviewValue;
                }
            }

            return $items;
        }

        if (str_starts_with($resolved, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($resolved, 0);
            if ($dictionary === null) {
                return null;
            }

            $metadata = [];
            foreach ($this->dictionaryEntries($dictionary['body']) as $name => $entryValue) {
                $reviewValue = $this->reviewValueFromRaw($entryValue, $objects, $depth + 1);
                if ($reviewValue !== null && $reviewValue !== '') {
                    $metadata[$name] = $reviewValue;
                }
            }

            return $metadata === [] ? null : $metadata;
        }

        if ($resolved === 'true') {
            return true;
        }

        if ($resolved === 'false') {
            return false;
        }

        if ($resolved === 'null') {
            return null;
        }

        if (preg_match('/^-?\d+$/', $resolved) === 1) {
            return (int) $resolved;
        }

        if (preg_match('/^-?(?:\d+\.\d*|\d*\.\d+)$/', $resolved) === 1) {
            return (float) $resolved;
        }

        return $this->stringValueFromRaw($resolved, $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function stringValueFromRaw(string $value, array $objects): ?string
    {
        $resolved = trim($this->resolveRawValue($value, $objects) ?? $value);
        if ($resolved === '') {
            return null;
        }

        if (str_starts_with($resolved, '(')) {
            $literal = $this->readLiteralStringAt($resolved, 0);
            return $literal === null ? null : $this->decodePdfStringBytes($this->decodeLiteralEscapes($literal['body']));
        }

        if (str_starts_with($resolved, '<') && !str_starts_with($resolved, '<<')) {
            $end = strpos($resolved, '>');
            if ($end === false) {
                return null;
            }

            $hex = preg_replace('/\s+/', '', substr($resolved, 1, $end - 1));
            if ($hex === null || $hex === '' || preg_match('/^[\da-fA-F]+$/', $hex) !== 1) {
                return null;
            }
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }

            $bytes = hex2bin($hex);
            return $bytes === false ? null : $this->decodePdfStringBytes($bytes);
        }

        if (str_starts_with($resolved, '/')) {
            return $this->decodePdfName(substr($resolved, 1));
        }

        return preg_match('/^[^\s\[\]()<>{}\/%]+$/', $resolved) === 1 ? $resolved : null;
    }

    /**
     * @return array{raw: string, end: int}|null
     */
    private function readPdfValueAt(string $value, int $offset): ?array
    {
        $offset = $this->skipWhitespace($value, $offset);
        if ($offset >= strlen($value)) {
            return null;
        }

        $char = $value[$offset];
        if ($char === '[') {
            return $this->readPdfArrayAt($value, $offset);
        }

        if (substr($value, $offset, 2) === '<<') {
            $dictionary = $this->readPdfDictionaryAt($value, $offset);
            return $dictionary === null ? null : ['raw' => $dictionary['raw'], 'end' => $dictionary['end']];
        }

        if ($char === '(') {
            $literal = $this->readLiteralStringAt($value, $offset);
            return $literal === null ? null : ['raw' => substr($value, $offset, $literal['end'] - $offset), 'end' => $literal['end']];
        }

        if ($char === '<') {
            $end = $this->skipHexString($value, $offset);
            return $end === null ? null : ['raw' => substr($value, $offset, $end - $offset), 'end' => $end];
        }

        $remaining = substr($value, $offset);
        if (preg_match('/\d+\s+\d+\s+R\b/A', $remaining, $match) === 1) {
            return ['raw' => $match[0], 'end' => $offset + strlen($match[0])];
        }

        if (preg_match('/\/[^\s\[\]()<>{}\/%]+|[^\s\[\]()<>{}\/%]+/A', $remaining, $match) === 1) {
            return ['raw' => $match[0], 'end' => $offset + strlen($match[0])];
        }

        return null;
    }

    /**
     * @return array{body: string, raw: string, end: int}|null
     */
    private function readPdfDictionaryAt(string $value, int $offset): ?array
    {
        if (substr($value, $offset, 2) !== '<<') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 2;
        for ($index = $offset, $length = strlen($value); $index < $length - 1; $index++) {
            $char = $value[$index];
            if ($char === '(') {
                $literal = $this->readLiteralStringAt($value, $index);
                if ($literal === null) {
                    return null;
                }
                $index = $literal['end'] - 1;
                continue;
            }

            if ($char === '<' && substr($value, $index, 2) !== '<<') {
                $end = $this->skipHexString($value, $index);
                if ($end === null) {
                    return null;
                }
                $index = $end - 1;
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
                $end = $index + 2;
                return [
                    'body' => substr($value, $bodyStart, $index - $bodyStart),
                    'raw' => substr($value, $offset, $end - $offset),
                    'end' => $end,
                ];
            }
            $index++;
        }

        return null;
    }

    /**
     * @return array{raw: string, end: int}|null
     */
    private function readPdfArrayAt(string $value, int $offset): ?array
    {
        if (($value[$offset] ?? '') !== '[') {
            return null;
        }

        $depth = 0;
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '(') {
                $literal = $this->readLiteralStringAt($value, $index);
                if ($literal === null) {
                    return null;
                }
                $index = $literal['end'] - 1;
                continue;
            }

            if ($char === '<' && substr($value, $index, 2) === '<<') {
                $dictionary = $this->readPdfDictionaryAt($value, $index);
                if ($dictionary === null) {
                    return null;
                }
                $index = $dictionary['end'] - 1;
                continue;
            }

            if ($char === '<') {
                $end = $this->skipHexString($value, $index);
                if ($end === null) {
                    return null;
                }
                $index = $end - 1;
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
                $end = $index + 1;
                return ['raw' => substr($value, $offset, $end - $offset), 'end' => $end];
            }
        }

        return null;
    }

    /**
     * @return array{body: string, end: int}|null
     */
    private function readLiteralStringAt(string $value, int $offset): ?array
    {
        if (($value[$offset] ?? '') !== '(') {
            return null;
        }

        $depth = 0;
        $body = '';
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '\\') {
                if ($index + 1 < $length) {
                    if ($depth > 0) {
                        $body .= $char . $value[$index + 1];
                    }
                    $index++;
                }
                continue;
            }

            if ($char === '(') {
                if ($depth > 0) {
                    $body .= $char;
                }
                $depth++;
                continue;
            }

            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return ['body' => $body, 'end' => $index + 1];
                }
                $body .= $char;
                continue;
            }

            if ($depth > 0) {
                $body .= $char;
            }
        }

        return null;
    }

    private function skipHexString(string $value, int $offset): ?int
    {
        $end = strpos($value, '>', $offset + 1);
        return $end === false ? null : $end + 1;
    }

    private function skipWhitespace(string $value, int $offset): int
    {
        for ($length = strlen($value); $offset < $length;) {
            if (ctype_space($value[$offset])) {
                $offset++;
                continue;
            }

            if ($value[$offset] === '%') {
                while ($offset < $length && $value[$offset] !== "\n" && $value[$offset] !== "\r") {
                    $offset++;
                }
                continue;
            }

            break;
        }

        return $offset;
    }

    private function decodePdfName(string $name): string
    {
        return preg_replace_callback('/#([\da-fA-F]{2})/', static function (array $match): string {
            return chr(hexdec($match[1]));
        }, $name) ?? $name;
    }

    private function decodeLiteralEscapes(string $bytes): string
    {
        $decoded = '';
        for ($index = 0, $length = strlen($bytes); $index < $length; $index++) {
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

        return $decoded;
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
