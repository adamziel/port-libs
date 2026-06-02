<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfEmbeddedFileExtractor
{
    /**
     * Native boundary for catalog /Names /EmbeddedFiles and /AF attachment lookup.
     *
     * @return list<array<string, mixed>>
     */
    public function extractEmbeddedFiles(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $catalog = $this->catalogObjectBody($pdfBytes, $objects);
        if ($catalog === null) {
            return [];
        }

        $portfolioMetadata = $this->collectionMetadata($this->dictionaryRawValue($catalog, 'Collection'), $objects);
        $catalogPieceInfo = $this->pieceInfoMetadata($this->dictionaryRawValue($catalog, 'PieceInfo'), $objects);
        $files = [];
        $names = $this->resolveDictionaryFromValue($this->dictionaryRawValue($catalog, 'Names'), $objects);
        if ($names !== null) {
            $embeddedFiles = $this->resolveDictionaryFromValue($this->dictionaryRawValue($names['body'], 'EmbeddedFiles'), $objects);
            if ($embeddedFiles !== null) {
                $this->collectNameTreeFiles($embeddedFiles['body'], $objects, $files, $portfolioMetadata, $catalogPieceInfo);
            }
        }

        $this->collectAssociatedFiles($this->dictionaryRawValue($catalog, 'AF'), $objects, $files, $portfolioMetadata, $catalogPieceInfo);

        return $this->dedupeEmbeddedFiles($files);
    }

    /**
     * @param array<int, string> $objects
     * @param list<array<string, mixed>> $files
     * @param array<string, mixed> $portfolioMetadata
     * @param array<string, mixed> $catalogPieceInfo
     * @param array<int, true> $seen
     */
    private function collectNameTreeFiles(
        string $nodeBody,
        array $objects,
        array &$files,
        array $portfolioMetadata = [],
        array $catalogPieceInfo = [],
        array $seen = []
    ): void
    {
        $namesValue = $this->dictionaryRawValue($nodeBody, 'Names');
        if ($namesValue !== null) {
            $names = $this->arrayItemsFromValue($namesValue, $objects);
            for ($index = 0, $count = count($names); $index + 1 < $count; $index += 2) {
                $name = $this->stringValueFromRaw($names[$index], $objects);
                if ($name === null || $name === '') {
                    continue;
                }

                $file = $this->embeddedFileFromFileSpecValue(
                    $names[$index + 1],
                    $name,
                    $objects,
                    'catalog_names_embedded_files',
                    $portfolioMetadata,
                    $catalogPieceInfo
                );
                if ($file !== null) {
                    $files[] = $file;
                }
            }
        }

        $kidsValue = $this->dictionaryRawValue($nodeBody, 'Kids');
        if ($kidsValue === null) {
            return;
        }

        foreach ($this->arrayItemsFromValue($kidsValue, $objects) as $kidValue) {
            $objectNumber = $this->objectNumberFromReference($kidValue);
            if ($objectNumber !== null) {
                if (isset($seen[$objectNumber])) {
                    continue;
                }
                $seen[$objectNumber] = true;
            }

            $kid = $this->resolveDictionaryFromValue($kidValue, $objects);
            if ($kid !== null) {
                $this->collectNameTreeFiles($kid['body'], $objects, $files, $portfolioMetadata, $catalogPieceInfo, $seen);
            }
        }
    }

    /**
     * @param array<int, string> $objects
     * @param list<array<string, mixed>> $files
     * @param array<string, mixed> $portfolioMetadata
     * @param array<string, mixed> $catalogPieceInfo
     */
    private function collectAssociatedFiles(
        ?string $arrayValue,
        array $objects,
        array &$files,
        array $portfolioMetadata = [],
        array $catalogPieceInfo = []
    ): void
    {
        if ($arrayValue === null) {
            return;
        }

        foreach ($this->arrayItemsFromValue($arrayValue, $objects) as $index => $fileSpecValue) {
            $file = $this->embeddedFileFromFileSpecValue(
                $fileSpecValue,
                null,
                $objects,
                'catalog_associated_files',
                $portfolioMetadata,
                $catalogPieceInfo
            );
            if ($file === null) {
                continue;
            }

            $file['associated_file'] = true;
            $file['associated_file_index'] = $index;
            $files[] = $file;
        }
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     * @param array<string, mixed> $portfolioMetadata
     * @param array<string, mixed> $catalogPieceInfo
     */
    private function embeddedFileFromFileSpecValue(
        string $value,
        ?string $name,
        array $objects,
        string $source,
        array $portfolioMetadata = [],
        array $catalogPieceInfo = []
    ): ?array
    {
        $fileSpec = $this->resolveDictionaryFromValue($value, $objects);
        if ($fileSpec === null) {
            return null;
        }

        $body = $fileSpec['body'];
        $ef = $this->resolveDictionaryFromValue($this->dictionaryRawValue($body, 'EF'), $objects);
        if ($ef === null) {
            return null;
        }

        $unicodeFilename = $this->dictionaryStringValue($body, 'UF', $objects);
        $filename = $unicodeFilename
            ?? $this->firstDictionaryString($body, ['F', 'DOS', 'Unix', 'Mac'], $objects)
            ?? $name
            ?? 'embedded-file';
        $attachmentName = ($name !== null && $name !== '') ? $name : $filename;

        foreach ($this->embeddedFileKeys($unicodeFilename !== null) as $efKey) {
            $streamValue = $this->dictionaryRawValue($ef['body'], $efKey);
            if ($streamValue === null) {
                continue;
            }

            $stream = $this->embeddedFileStreamFromValue($streamValue, $objects);
            if ($stream === null) {
                continue;
            }

            $file = [
                'source' => $source,
                'name' => $attachmentName,
                'filename' => $filename,
                'content' => $stream['content'],
                'size' => strlen($stream['content']),
                'content_sha256' => hash('sha256', $stream['content']),
                'ef_key' => $efKey,
                'file_spec_object' => $fileSpec['object'],
                'embedded_file_object' => $stream['object'],
            ];

            if ($unicodeFilename !== null && $unicodeFilename !== '') {
                $file['unicode_filename'] = $unicodeFilename;
            }

            foreach ([
                'description' => $this->dictionaryStringValue($body, 'Desc', $objects),
                'relationship' => $this->dictionaryNameValue($body, 'AFRelationship', $objects),
                'mime_type' => $this->dictionaryNameValue($stream['dictionary'], 'Subtype', $objects),
            ] as $key => $metadataValue) {
                if (is_string($metadataValue) && $metadataValue !== '') {
                    $file[$key] = $metadataValue;
                }
            }

            if ($stream['filters'] !== []) {
                $file['filters'] = $stream['filters'];
            }

            foreach ($this->embeddedFileParams($stream['dictionary'], $objects, $stream['content']) as $key => $metadataValue) {
                $file[$key] = $metadataValue;
            }

            if ($portfolioMetadata !== []) {
                $file['portfolio'] = $portfolioMetadata;
            }

            $portfolioItem = $this->collectionItemMetadata($this->dictionaryRawValue($body, 'CI'), $objects);
            if ($portfolioItem !== []) {
                $file['portfolio_item'] = $portfolioItem;
            }

            $pieceInfo = $this->pieceInfoMetadata($this->dictionaryRawValue($body, 'PieceInfo'), $objects);
            if ($pieceInfo !== []) {
                $file['piece_info'] = $pieceInfo;
            }

            if ($catalogPieceInfo !== []) {
                $file['catalog_piece_info'] = $catalogPieceInfo;
            }

            return $file;
        }

        return null;
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
    private function embeddedFileStreamFromValue(string $value, array $objects): ?array
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
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function collectionMetadata(?string $collectionValue, array $objects): array
    {
        $collection = $this->resolveDictionaryFromValue($collectionValue, $objects);
        if ($collection === null) {
            return [];
        }

        $body = $collection['body'];
        $entries = $this->dictionaryEntries($body);
        $metadata = ['source' => 'catalog_collection'];
        foreach ([
            'type' => $this->dictionaryNameValue($body, 'Type', $objects),
            'view' => $this->dictionaryNameValue($body, 'View', $objects),
            'default_document' => $this->reviewValueFromRaw($entries['D'] ?? null, $objects),
        ] as $key => $value) {
            if ($value !== null && $value !== '') {
                $metadata[$key] = $value;
            }
        }

        $schema = $this->collectionSchemaMetadata($this->dictionaryRawValue($body, 'Schema'), $objects);
        if ($schema !== []) {
            $metadata['schema'] = $schema;
        }

        $sort = $this->collectionSortMetadata($this->dictionaryRawValue($body, 'Sort'), $objects);
        if ($sort !== []) {
            $metadata['sort'] = $sort;
        }

        if ($this->dictionaryRawValue($body, 'Folders') !== null) {
            $metadata['has_folders'] = true;
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, array<string, mixed>>
     */
    private function collectionSchemaMetadata(?string $schemaValue, array $objects): array
    {
        $schema = $this->resolveDictionaryFromValue($schemaValue, $objects);
        if ($schema === null) {
            return [];
        }

        $fields = [];
        foreach ($this->dictionaryEntries($schema['body']) as $name => $fieldValue) {
            $fieldDictionary = $this->resolveDictionaryFromValue($fieldValue, $objects);
            if ($fieldDictionary === null) {
                continue;
            }

            $fieldBody = $fieldDictionary['body'];
            $field = [];
            foreach ([
                'subtype' => $this->dictionaryNameValue($fieldBody, 'Subtype', $objects),
                'label' => $this->dictionaryStringValue($fieldBody, 'N', $objects),
                'order' => $this->dictionaryIntegerValue($fieldBody, 'O'),
                'visible' => $this->reviewValueFromRaw($this->dictionaryRawValue($fieldBody, 'V'), $objects),
                'editable' => $this->reviewValueFromRaw($this->dictionaryRawValue($fieldBody, 'E'), $objects),
            ] as $key => $value) {
                if ($value !== null && $value !== '') {
                    $field[$key] = $value;
                }
            }

            if ($field !== []) {
                $fields[$name] = $field;
            }
        }

        return $fields;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function collectionSortMetadata(?string $sortValue, array $objects): array
    {
        $sort = $this->resolveDictionaryFromValue($sortValue, $objects);
        if ($sort === null) {
            return [];
        }

        $metadata = [];
        $keys = $this->reviewListFromRaw($this->dictionaryRawValue($sort['body'], 'S'), $objects);
        if ($keys !== []) {
            $metadata['keys'] = $keys;
        }

        $ascending = $this->reviewListFromRaw($this->dictionaryRawValue($sort['body'], 'A'), $objects);
        if ($ascending !== []) {
            $metadata['ascending'] = $ascending;
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function collectionItemMetadata(?string $collectionItemValue, array $objects): array
    {
        $collectionItem = $this->resolveDictionaryFromValue($collectionItemValue, $objects);
        if ($collectionItem === null) {
            return [];
        }

        $metadata = [];
        foreach ($this->dictionaryEntries($collectionItem['body']) as $name => $value) {
            if ($name === 'Type') {
                continue;
            }

            $subitem = $this->collectionSubitemMetadata($value, $objects);
            if ($subitem !== null) {
                $metadata[$name] = $subitem;
                continue;
            }

            $reviewValue = $this->reviewValueFromRaw($value, $objects);
            if ($reviewValue !== null && $reviewValue !== '') {
                $metadata[$name] = $reviewValue;
            }
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function collectionSubitemMetadata(string $value, array $objects): ?array
    {
        $subitem = $this->resolveDictionaryFromValue($value, $objects);
        if ($subitem === null) {
            return null;
        }

        $body = $subitem['body'];
        if ($this->dictionaryRawValue($body, 'D') === null && $this->dictionaryRawValue($body, 'P') === null) {
            return null;
        }

        $metadata = [];
        $value = $this->reviewValueFromRaw($this->dictionaryRawValue($body, 'D'), $objects);
        if ($value !== null && $value !== '') {
            $metadata['value'] = $value;
        }

        $prefix = $this->reviewValueFromRaw($this->dictionaryRawValue($body, 'P'), $objects);
        if ($prefix !== null && $prefix !== '') {
            $metadata['prefix'] = $prefix;
        }

        return $metadata === [] ? null : $metadata;
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

            foreach ($this->pieceInfoPrivateMetadata($this->dictionaryRawValue($piece['body'], 'Private'), $objects) as $key => $value) {
                $entry[$key] = $value;
            }

            if ($entry !== []) {
                $metadata[$application] = $entry;
            }
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function pieceInfoPrivateMetadata(?string $privateValue, array $objects): array
    {
        if ($privateValue === null) {
            return [];
        }

        $streamMetadata = $this->pieceInfoPrivateStreamMetadata($privateValue, $objects);
        if ($streamMetadata !== null) {
            return ['private_stream' => $streamMetadata];
        }

        $private = $this->resolveDictionaryFromValue($privateValue, $objects);
        if ($private !== null) {
            $privateMetadata = [];
            foreach ($this->dictionaryEntries($private['body']) as $name => $value) {
                $reviewValue = $this->reviewValueFromRaw($value, $objects);
                if ($reviewValue !== null && $reviewValue !== '') {
                    $privateMetadata[$name] = $reviewValue;
                }
            }

            return $privateMetadata === [] ? [] : ['private' => $privateMetadata];
        }

        $reviewValue = $this->reviewValueFromRaw($privateValue, $objects);
        return ($reviewValue === null || $reviewValue === '') ? [] : ['private' => $reviewValue];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function pieceInfoPrivateStreamMetadata(string $value, array $objects): ?array
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

        $metadata = [
            'size' => strlen($stream['content']),
            'content_sha256' => hash('sha256', $stream['content']),
        ];

        if ($objectNumber !== null) {
            $metadata['object'] = $objectNumber;
        }

        $declaredLength = $this->dictionaryIntegerValue($stream['dictionary'], 'Length');
        if ($declaredLength !== null) {
            $metadata['declared_length'] = $declaredLength;
        }

        $mimeType = $this->dictionaryNameValue($stream['dictionary'], 'Subtype', $objects);
        if ($mimeType !== null && $mimeType !== '') {
            $metadata['mime_type'] = $mimeType;
        }

        if ($stream['filters'] !== []) {
            $metadata['filters'] = $stream['filters'];
        }

        foreach ($this->embeddedFileParams($stream['dictionary'], $objects, $stream['content']) as $key => $metadataValue) {
            $metadata[$key] = $metadataValue;
        }

        return $metadata;
    }

    /**
     * @param list<array<string, mixed>> $files
     * @return list<array<string, mixed>>
     */
    private function dedupeEmbeddedFiles(array $files): array
    {
        $seen = [];
        $deduped = [];

        foreach ($files as $file) {
            $key = ($file['embedded_file_object'] ?? 'direct') . ':' . ($file['name'] ?? '') . ':' . ($file['filename'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduped[] = $file;
        }

        return $deduped;
    }

    /**
     * @return array<int, string>
     */
    private function pdfObjects(string $pdfBytes): array
    {
        $objects = [];
        $offset = 0;
        while (preg_match('/(\d+)\s+\d+\s+obj\b/s', $pdfBytes, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $bodyStart = $match[0][1] + strlen($match[0][0]);
            $bodyEnd = $this->pdfObjectEndOffset($pdfBytes, $bodyStart);
            if ($bodyEnd === null) {
                break;
            }

            $objects[(int) $match[1][0]] = substr($pdfBytes, $bodyStart, $bodyEnd - $bodyStart);
            $offset = $bodyEnd + strlen('endobj');
        }

        return $objects;
    }

    private function pdfObjectEndOffset(string $pdfBytes, int $offset): ?int
    {
        for ($index = $offset, $length = strlen($pdfBytes); $index < $length;) {
            $char = $pdfBytes[$index];
            if ($char === '%') {
                while ($index < $length && $pdfBytes[$index] !== "\n" && $pdfBytes[$index] !== "\r") {
                    $index++;
                }
                continue;
            }

            if ($char === '(') {
                $literal = $this->readLiteralStringAt($pdfBytes, $index);
                if ($literal !== null) {
                    $index = $literal['end'];
                    continue;
                }
            }

            if (substr($pdfBytes, $index, 2) === '<<') {
                $dictionary = $this->readPdfDictionaryAt($pdfBytes, $index);
                if ($dictionary !== null) {
                    $index = $dictionary['end'];
                    continue;
                }
            }

            if ($char === '[') {
                $array = $this->readPdfArrayAt($pdfBytes, $index);
                if ($array !== null) {
                    $index = $array['end'];
                    continue;
                }
            }

            if ($char === '<') {
                $end = $this->skipHexString($pdfBytes, $index);
                if ($end !== null) {
                    $index = $end;
                    continue;
                }
            }

            if ($this->pdfKeywordAt($pdfBytes, $index, 'stream')) {
                $streamStart = $index + strlen('stream');
                if (substr($pdfBytes, $streamStart, 2) === "\r\n") {
                    $streamStart += 2;
                } elseif (($pdfBytes[$streamStart] ?? '') === "\n" || ($pdfBytes[$streamStart] ?? '') === "\r") {
                    $streamStart++;
                }

                $streamEnd = strpos($pdfBytes, 'endstream', $streamStart);
                if ($streamEnd !== false) {
                    $index = $streamEnd + strlen('endstream');
                    continue;
                }
            }

            if ($this->pdfKeywordAt($pdfBytes, $index, 'endobj')) {
                return $index;
            }

            $index++;
        }

        return null;
    }

    private function pdfKeywordAt(string $value, int $offset, string $keyword): bool
    {
        if (substr($value, $offset, strlen($keyword)) !== $keyword) {
            return false;
        }

        if ($offset > 0) {
            $before = $value[$offset - 1];
            if ($before === '/' || (!ctype_space($before) && !str_contains('[]()<>{}%', $before))) {
                return false;
            }
        }

        $afterOffset = $offset + strlen($keyword);
        if ($afterOffset >= strlen($value)) {
            return true;
        }

        $after = $value[$afterOffset];
        return ctype_space($after) || str_contains('[]()<>{}/%', $after);
    }

    /**
     * @param array<int, string> $objects
     */
    private function catalogObjectBody(string $pdfBytes, array $objects): ?string
    {
        $trailer = $this->trailerDictionaryBody($pdfBytes);
        if ($trailer !== null && preg_match('/\/Root\s+(\d+)\s+\d+\s+R\b/s', $trailer, $match) === 1) {
            $objectNumber = (int) $match[1];
            if (isset($objects[$objectNumber])) {
                return $this->dictionaryObjectBody($objects[$objectNumber]);
            }
        }

        foreach ($objects as $body) {
            if (preg_match('/\/Type\s*\/Catalog\b/', $body) === 1) {
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
     * @return list<string>
     */
    private function arrayItemsFromValue(string $value, array $objects): array
    {
        $resolved = $this->resolveRawValue($value, $objects);
        if ($resolved === null) {
            return [];
        }

        $array = $this->readPdfArrayAt($resolved, 0);
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

        $dictionary = $this->readPdfDictionaryAt($resolved, 0);
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

    /**
     * @return array{dictionary: string, content: string, filters: list<string>}|null
     * @param array<int, string> $objects
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
     * @return list<string>
     * @param array<int, string> $objects
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

        $resolved = $this->resolveRawValue($value, $objects) ?? $value;
        $resolved = trim($resolved);
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
    private function stringValueFromRaw(string $value, array $objects): ?string
    {
        $resolved = $this->resolveRawValue($value, $objects) ?? $value;
        $resolved = trim($resolved);
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
        $resolved = $this->resolveRawValue($value, $objects) ?? $value;
        $resolved = trim($resolved);
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
            return $dictionary === null ? null : ['raw' => '<<' . $dictionary['body'] . '>>', 'end' => $dictionary['end']];
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

    private function dictionaryObjectBody(string $objectBody): ?string
    {
        $offset = strpos($objectBody, '<<');
        if ($offset === false) {
            return null;
        }

        $dictionary = $this->readPdfDictionaryAt($objectBody, $offset);
        return $dictionary === null ? null : $dictionary['body'];
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
     * @return list<mixed>
     */
    private function reviewListFromRaw(?string $value, array $objects): array
    {
        if ($value === null) {
            return [];
        }

        $resolved = trim($this->resolveRawValue($value, $objects) ?? $value);
        if ($resolved === '') {
            return [];
        }

        if (!str_starts_with($resolved, '[')) {
            $scalar = $this->reviewValueFromRaw($resolved, $objects);
            return $scalar === null ? [] : [$scalar];
        }

        $items = [];
        foreach ($this->arrayItemsFromValue($resolved, $objects) as $item) {
            $reviewValue = $this->reviewValueFromRaw($item, $objects);
            if ($reviewValue !== null) {
                $items[] = $reviewValue;
            }
        }

        return $items;
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
