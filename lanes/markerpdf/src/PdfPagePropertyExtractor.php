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

        $markInfo = $this->markInfoMetadata($catalog, $objects);
        $markInfoUserProperties = ($markInfo['user_properties'] ?? false) === true;
        $userPropertiesByPage = $markInfoUserProperties
            ? $this->structureUserPropertiesByPageObject($catalog, $objects)
            : [];
        $pagePresentationsByObject = $this->pagePresentationMetadataByPageObject($pdfBytes);

        $pages = [];
        foreach ($pageObjectNumbers as $pnum => $pageObjectNumber) {
            $pageBody = $this->dictionaryObjectBody($objects[$pageObjectNumber] ?? '');
            if ($pageBody === null) {
                continue;
            }

            $pieceInfo = $this->pieceInfoMetadata($this->dictionaryRawValue($pageBody, 'PieceInfo'), $objects);
            $associatedFiles = $this->pageAssociatedFilesMetadata($this->dictionaryRawValue($pageBody, 'AF'), $objects);
            $userProperties = $userPropertiesByPage[$pageObjectNumber] ?? [];
            if ($pieceInfo === [] && $associatedFiles === [] && $userProperties === []) {
                continue;
            }
            $pagePresentation = $pagePresentationsByObject[$pageObjectNumber] ?? null;

            $page = [
                'pnum' => $pnum,
                'page_object' => $pageObjectNumber,
            ];

            if ($markInfo !== []) {
                $page['mark_info'] = $markInfo;
            }

            if ($pieceInfo !== []) {
                $page['piece_info'] = $pieceInfo;
            }

            if ($associatedFiles !== []) {
                $page['page_associated_files'] = $associatedFiles;
            }

            if ($pagePresentation !== null) {
                $page['page_presentation'] = $pagePresentation;
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
     * @return array<int, array<string, mixed>>
     */
    private function pagePresentationMetadataByPageObject(string $pdfBytes): array
    {
        $presentations = [];
        foreach ((new PdfOutlineExtractor())->getPageTransitionActionMetadata($pdfBytes) as $presentation) {
            $pageObject = $presentation['page_object'] ?? null;
            if (is_int($pageObject)) {
                $presentations[$pageObject] = $presentation;
            }
        }

        return $presentations;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function markInfoMetadata(string $catalog, array $objects): array
    {
        $markInfo = $this->resolveDictionaryFromValue($this->dictionaryRawValue($catalog, 'MarkInfo'), $objects);
        if ($markInfo === null) {
            return [];
        }

        $metadata = ['source' => 'catalog_mark_info'];
        foreach ([
            'marked' => 'Marked',
            'user_properties' => 'UserProperties',
            'suspects' => 'Suspects',
        ] as $metadataKey => $pdfKey) {
            $value = $this->reviewValueFromRaw($this->dictionaryRawValue($markInfo['body'], $pdfKey), $objects);
            if (is_bool($value)) {
                $metadata[$metadataKey] = $value;
            }
        }

        return count($metadata) > 1 ? $metadata : [];
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
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function pageAssociatedFilesMetadata(?string $arrayValue, array $objects): array
    {
        if ($arrayValue === null) {
            return [];
        }

        $files = [];
        foreach ($this->arrayItemsFromValue($arrayValue, $objects) as $index => $fileSpecValue) {
            $file = $this->fileSpecReviewMetadata($fileSpecValue, null, $objects);
            if ($file === null) {
                continue;
            }

            $file = [
                'source' => 'page_associated_files',
                'associated_file' => true,
                'associated_file_index' => $index,
            ] + $file;
            $files[] = $file;
        }

        return $files;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function fileSpecReviewMetadata(string $value, ?string $name, array $objects): ?array
    {
        $fileSpec = $this->resolveDictionaryFromValue($value, $objects);
        if ($fileSpec === null) {
            return null;
        }

        $body = $fileSpec['body'];
        $unicodeFilename = $this->dictionaryStringValue($body, 'UF', $objects);
        $filename = $unicodeFilename
            ?? $this->firstDictionaryString($body, ['F', 'DOS', 'Unix', 'Mac'], $objects)
            ?? $name
            ?? 'associated-file';
        $attachmentName = ($name !== null && $name !== '') ? $name : $filename;

        $file = [
            'name' => $attachmentName,
            'filename' => $filename,
            'file_spec_object' => $fileSpec['object'],
        ];

        if ($unicodeFilename !== null && $unicodeFilename !== '') {
            $file['unicode_filename'] = $unicodeFilename;
        }

        foreach ([
            'description' => $this->dictionaryStringValue($body, 'Desc', $objects),
            'relationship' => $this->dictionaryNameValue($body, 'AFRelationship', $objects),
        ] as $key => $metadataValue) {
            if (is_string($metadataValue) && $metadataValue !== '') {
                $file[$key] = $metadataValue;
            }
        }

        $ef = $this->resolveDictionaryFromValue($this->dictionaryRawValue($body, 'EF'), $objects);
        if ($ef === null) {
            return $file;
        }

        foreach ($this->embeddedFileKeys($unicodeFilename !== null) as $efKey) {
            $streamValue = $this->dictionaryRawValue($ef['body'], $efKey);
            if ($streamValue === null) {
                continue;
            }

            $stream = $this->embeddedFileStreamMetadata($streamValue, $objects);
            if ($stream === null) {
                continue;
            }

            $file['ef_key'] = $efKey;
            $file['embedded_file_object'] = $stream['object'];
            $file['size'] = strlen($stream['content']);
            $file['content_sha256'] = hash('sha256', $stream['content']);

            $mimeType = $this->dictionaryNameValue($stream['dictionary'], 'Subtype', $objects);
            if ($mimeType !== null && $mimeType !== '') {
                $file['mime_type'] = $mimeType;
            }

            if ($stream['filters'] !== []) {
                $file['filters'] = $stream['filters'];
            }

            foreach ($this->embeddedFileParams($stream['dictionary'], $objects, $stream['content']) as $key => $metadataValue) {
                $file[$key] = $metadataValue;
            }

            break;
        }

        return $file;
    }

    /**
     * Prefer the Unicode file stream when the FileSpec advertises /UF.
     *
     * @return list<string>
     */
    private function embeddedFileKeys(bool $hasUnicodeFilename): array
    {
        return $hasUnicodeFilename
            ? ['UF', 'F', 'DOS', 'Unix', 'Mac']
            : ['F', 'UF', 'DOS', 'Unix', 'Mac'];
    }

    /**
     * @param array<int, string> $objects
     * @return array{object: int|null, dictionary: string, content: string, filters: list<string>}|null
     */
    private function embeddedFileStreamMetadata(string $value, array $objects): ?array
    {
        $objectNumber = $this->objectNumberFromReference($value);
        $body = $objectNumber !== null ? ($objects[$objectNumber] ?? null) : trim($value);
        if ($body === null || $body === '') {
            return null;
        }

        $stream = $this->decodeStreamObject($body, $objects);
        if ($stream === null) {
            return null;
        }

        return [
            'object' => $objectNumber,
            'dictionary' => $stream['dictionary'],
            'content' => $stream['content'],
            'filters' => $stream['filters'],
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array{dictionary: string, content: string, filters: list<string>}|null
     */
    private function decodeStreamObject(string $objectBody, array $objects): ?array
    {
        if (!preg_match('/<<(.*?)>>\s*stream\r?\n?(.*?)\r?\n?endstream/s', $objectBody, $match)) {
            return null;
        }

        $dictionary = $match[1];
        $stream = $match[2];
        $filters = $this->streamFilters($dictionary, $objects);
        foreach ($filters as $filter) {
            $decoded = match ($filter) {
                'ASCIIHexDecode', 'AHx' => $this->decodeAsciiHexStream($stream),
                'FlateDecode', 'Fl' => $this->decodeFlateStream($stream),
                default => $stream,
            };
            if ($decoded === null) {
                return null;
            }
            $stream = $decoded;
        }

        return [
            'dictionary' => $dictionary,
            'content' => $stream,
            'filters' => $filters,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function streamFilters(string $dictionary, array $objects): array
    {
        $value = $this->dictionaryRawValue($dictionary, 'Filter');
        if ($value === null) {
            return [];
        }

        $resolved = $this->resolveRawValue($value, $objects) ?? $value;
        preg_match_all('/\/([^\s\[\]()<>{}\/%]+)/', $resolved, $matches);

        return array_map(fn (string $name): string => $this->decodePdfName($name), $matches[1] ?? []);
    }

    private function decodeAsciiHexStream(string $stream): ?string
    {
        $body = strstr($stream, '>', true);
        if ($body === false) {
            $body = $stream;
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

    private function decodeFlateStream(string $stream): ?string
    {
        $inflated = @gzuncompress($stream);
        if ($inflated === false) {
            $inflated = @gzinflate($stream);
        }
        if ($inflated === false) {
            $inflated = @gzdecode($stream);
        }

        return $inflated === false ? null : $inflated;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function embeddedFileParams(string $streamDictionary, array $objects, string $content): array
    {
        $params = $this->resolveDictionaryFromValue($this->dictionaryRawValue($streamDictionary, 'Params'), $objects);
        if ($params === null) {
            return [];
        }

        $metadata = [];
        $size = $this->dictionaryIntegerValue($params['body'], 'Size');
        if ($size !== null) {
            $metadata['declared_size'] = $size;
        }

        $checksum = $this->dictionaryChecksumValue($params['body'], 'CheckSum', $objects);
        if ($checksum !== null && $checksum !== '') {
            $metadata['checksum'] = $checksum;
            $metadata['checksum_algorithm'] = 'md5';
            $metadata['computed_checksum'] = hash('md5', $content);
            $metadata['checksum_matches'] = hash_equals($metadata['computed_checksum'], $checksum);
        }

        $createdAt = $this->dictionaryStringValue($params['body'], 'CreationDate', $objects);
        if ($createdAt !== null && $createdAt !== '') {
            $metadata['created_at'] = $createdAt;
        }

        $modifiedAt = $this->dictionaryStringValue($params['body'], 'ModDate', $objects);
        if ($modifiedAt !== null && $modifiedAt !== '') {
            $metadata['modified_at'] = $modifiedAt;
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
     * @param list<string> $keys
     * @param array<int, string> $objects
     */
    private function firstDictionaryString(string $dictionary, array $keys, array $objects): ?string
    {
        foreach ($keys as $key) {
            $value = $this->dictionaryStringValue($dictionary, $key, $objects);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
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

    private function dictionaryIntegerValue(string $dictionary, string $key): ?int
    {
        $value = $this->dictionaryRawValue($dictionary, $key);
        if ($value === null || preg_match('/^-?\d+$/', trim($value)) !== 1) {
            return null;
        }

        return (int) trim($value);
    }

    /**
     * @param array<int, string> $objects
     */
    private function dictionaryChecksumValue(string $dictionary, string $key, array $objects): ?string
    {
        $value = $this->dictionaryRawValue($dictionary, $key);
        if ($value === null) {
            return null;
        }

        $bytes = $this->byteStringValueFromRaw($value, $objects);
        if ($bytes === null) {
            return null;
        }

        if (strlen($bytes) === 32 && preg_match('/^[\da-fA-F]{32}$/', $bytes) === 1) {
            return strtolower($bytes);
        }

        return bin2hex($bytes);
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
     * PDF byte strings such as embedded-file /CheckSum must remain binary-safe.
     *
     * @param array<int, string> $objects
     */
    private function byteStringValueFromRaw(string $value, array $objects): ?string
    {
        $resolved = trim($this->resolveRawValue($value, $objects) ?? $value);
        if ($resolved === '') {
            return null;
        }

        if (str_starts_with($resolved, '(')) {
            $literal = $this->readLiteralStringAt($resolved, 0);
            return $literal === null ? null : $this->decodeLiteralEscapes($literal['body']);
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
            return $bytes === false ? null : $bytes;
        }

        return null;
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
