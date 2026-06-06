<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfEmbeddedFileExtractor
{
    private const ASSOCIATED_FILE_RELATIONSHIP_ROLES = [
        'Source' => 'original_source',
        'Data' => 'base_data_for_visual_presentation',
        'Alternative' => 'alternative_representation',
        'Supplement' => 'supplemental_representation',
        'EncryptedPayload' => 'encrypted_payload',
        'FormData' => 'form_data',
        'Schema' => 'schema_definition',
        'Unspecified' => 'unspecified',
    ];

    private const EMBEDDED_FILE_FALLBACK_KEYS = ['UF', 'F', 'Unix', 'Mac', 'DOS'];

    private const FILE_SPEC_ATTACHMENT_BOUNDARY_KEYS = ['F', 'UF', 'DOS', 'Unix', 'Mac', 'EF'];

    private const EMBEDDED_FILE_REFERENCE_BOUNDARY_KEYS = ['F', 'UF', 'DOS', 'Unix', 'Mac'];

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

    /** @var array<int, int> */
    private array $objectGenerations = [];

    /**
     * Native boundary for catalog /Names /EmbeddedFiles and /AF attachment lookup.
     *
     * @return list<array<string, mixed>>
     */
    public function extractEmbeddedFiles(string $pdfBytes): array
    {
        $pdfBytes = $this->bytesThroughTerminalEof($pdfBytes);
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
     * @param array{lower: string, upper: string}|null $inheritedLimits
     */
    private function collectNameTreeFiles(
        string $nodeBody,
        array $objects,
        array &$files,
        array $portfolioMetadata = [],
        array $catalogPieceInfo = [],
        array $seen = [],
        ?array $inheritedLimits = null,
        int $depth = 0
    ): void
    {
        if ($depth > 20) {
            return;
        }

        $limits = $this->nameTreeEffectiveLimits($nodeBody, $objects, $inheritedLimits);
        $childLimits = $limits;
        $namesValue = $this->dictionaryRawValue($nodeBody, 'Names');
        if ($namesValue !== null) {
            $names = $this->arrayItemsFromValue($namesValue, $objects);
            $entryLimits = $this->nameTreeLimitsMatchAnyPairKey($names, $objects, $limits)
                ? $limits
                : $inheritedLimits;
            $childLimits = $entryLimits;
            for ($index = 0, $count = count($names); $index + 1 < $count; $index += 2) {
                $name = $this->stringValueFromRaw($names[$index], $objects);
                if ($name === null || $name === '' || !$this->nameTreeNameWithinLimits($name, $entryLimits)) {
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
                $this->collectNameTreeFiles($kid['body'], $objects, $files, $portfolioMetadata, $catalogPieceInfo, $seen, $childLimits, $depth + 1);
            }
        }
    }

    /**
     * @param array<int, string> $objects
     * @param array{lower: string, upper: string}|null $inheritedLimits
     * @return array{lower: string, upper: string}|null
     */
    private function nameTreeEffectiveLimits(string $nodeBody, array $objects, ?array $inheritedLimits): ?array
    {
        $nodeLimits = $this->nameTreeNodeLimits($nodeBody, $objects);
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
     * @param array<int, string> $objects
     * @return array{lower: string, upper: string}|null
     */
    private function nameTreeNodeLimits(string $nodeBody, array $objects): ?array
    {
        $limitsValue = $this->dictionaryRawValue($nodeBody, 'Limits');
        if ($limitsValue === null) {
            return null;
        }

        $items = $this->arrayItemsFromValue($limitsValue, $objects);
        if (count($items) < 2) {
            return null;
        }

        $lower = $this->stringValueFromRaw($items[0], $objects);
        $upper = $this->stringValueFromRaw($items[1], $objects);
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
     * @param list<string> $items
     * @param array<int, string> $objects
     * @param array{lower: string, upper: string}|null $limits
     */
    private function nameTreeLimitsMatchAnyPairKey(array $items, array $objects, ?array $limits): bool
    {
        if ($limits === null || $items === []) {
            return true;
        }

        for ($index = 0, $count = count($items); $index + 1 < $count; $index += 2) {
            $name = $this->stringValueFromRaw($items[$index], $objects);
            if ($name !== null && $this->nameTreeNameWithinLimits($name, $limits)) {
                return true;
            }
        }

        return false;
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
        if ($this->dictionaryHasDuplicateKeys($body, self::FILE_SPEC_ATTACHMENT_BOUNDARY_KEYS)) {
            return null;
        }

        $ef = $this->resolveDictionaryFromValue($this->dictionaryRawValue($body, 'EF'), $objects);
        if ($ef === null) {
            return null;
        }
        if ($this->dictionaryHasDuplicateKeys($ef['body'], self::EMBEDDED_FILE_REFERENCE_BOUNDARY_KEYS)) {
            return null;
        }

        [$filename, $filenameSource] = $this->fileSpecFilenameWithSource($body, $objects, $name);
        $attachmentName = ($name !== null && $name !== '') ? $name : $filename;
        $filenameReview = $this->filenamePathReview($filename);

        foreach ($this->embeddedFileKeys($filenameSource) as $efKey) {
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
                'filename_source' => $filenameSource,
                'content' => $stream['content'],
                'size' => strlen($stream['content']),
                'content_sha256' => hash('sha256', $stream['content']),
                'ef_key' => $efKey,
                'file_spec_object' => $fileSpec['object'],
                'embedded_file_object' => $stream['object'],
            ];
            foreach ($filenameReview as $key => $metadataValue) {
                $file[$key] = $metadataValue;
            }

            if ($filenameSource === 'UF' && $filename !== '') {
                $file['unicode_filename'] = $filename;
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

            foreach ($this->fileSpecMetadata($body, $objects) as $key => $metadataValue) {
                $file[$key] = $metadataValue;
            }

            if ($stream['filters'] !== []) {
                $file['filters'] = $stream['filters'];
            }

            foreach ($this->embeddedFileStreamMetadata($stream['dictionary'], $objects, $stream['content']) as $key => $metadataValue) {
                $file[$key] = $metadataValue;
            }

            foreach ($this->embeddedFileParams($stream['dictionary'], $objects, $stream['content']) as $key => $metadataValue) {
                $file[$key] = $metadataValue;
            }

            if ($portfolioMetadata !== []) {
                $file['portfolio'] = $portfolioMetadata;
            }

            $portfolioItemValue = $this->dictionaryRawValue($body, 'CI');
            $portfolioItem = $this->collectionItemMetadata($portfolioItemValue, $objects);
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

            $metadataReview = $this->reviewValueFromRaw($this->dictionaryRawValue($body, 'Metadata'), $objects);
            if (is_array($metadataReview) && $metadataReview !== []) {
                $file['metadata_review'] = $metadataReview;
            }

            $outputIntentsReview = $this->reviewValueFromRaw($this->dictionaryRawValue($body, 'OutputIntents'), $objects);
            if (is_array($outputIntentsReview) && $outputIntentsReview !== []) {
                $file['output_intents_review'] = $outputIntentsReview;
            }

            $relatedFiles = $this->relatedFileReviewRows($this->dictionaryRawValue($body, 'RF'), $objects);
            if ($relatedFiles !== []) {
                $file['related_file_count'] = count($relatedFiles);
                $file['related_files'] = $relatedFiles;
            }

            $portfolioFieldValues = $this->collectionFieldValueReview($portfolioMetadata, $portfolioItemValue, $objects, $file);
            if ($portfolioFieldValues !== []) {
                $file['portfolio_field_values'] = $portfolioFieldValues;
            }

            $provenance = $this->portfolioFileSpecProvenanceReview(
                $file,
                $body,
                $portfolioMetadata,
                $catalogPieceInfo,
                $objects
            );
            if ($provenance !== []) {
                $file['provenance_review'] = $provenance;
            }

            $associatedProvenance = $this->associatedFileProvenanceReview($file);
            if ($associatedProvenance !== []) {
                if (!isset($file['provenance_review'])) {
                    $file['provenance_review'] = $associatedProvenance;
                } else {
                    $file['associated_file_provenance_review'] = $associatedProvenance;
                }
            }

            return $file;
        }

        return null;
    }

    /**
     * Portfolio attachment metadata is review state for WordPress import. Keep
     * FileSpec-local XMP, PieceInfo private streams, and OutputIntent ICC bytes
     * hashed and typed instead of exposing those payloads as document metadata.
     *
     * @param array<string, mixed> $file
     * @param array<string, mixed> $portfolioMetadata
     * @param array<string, mixed> $catalogPieceInfo
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function portfolioFileSpecProvenanceReview(
        array $file,
        string $fileSpecBody,
        array $portfolioMetadata,
        array $catalogPieceInfo,
        array $objects
    ): array {
        $sources = [];
        $hasPortfolioMetadata = false;
        $metadata = [
            'source' => 'portfolio_filespec_provenance',
            'review_only' => true,
            'metadata_payloads_included' => false,
            'payload_content_returned' => array_key_exists('content', $file),
        ];

        $relationship = $file['relationship'] ?? null;
        if (is_string($relationship) && $relationship !== '') {
            $metadata['relationship'] = $relationship;
            $metadata['relationship_role'] = self::ASSOCIATED_FILE_RELATIONSHIP_ROLES[$relationship] ?? 'unrecognized';
            $metadata['relationship_status'] = array_key_exists($relationship, self::ASSOCIATED_FILE_RELATIONSHIP_ROLES)
                ? 'standard_pdf_associated_file_relationship'
                : 'unrecognized_pdf_associated_file_relationship';
            $sources[] = 'filespec_afrelationship';
        }

        $payload = $this->embeddedPayloadProvenance($file);
        if ($payload !== []) {
            $metadata['payload'] = $payload;
            $sources[] = 'embedded_file_payload';
        }

        $portfolio = $this->portfolioCollectionProvenance($portfolioMetadata);
        if ($portfolio !== []) {
            $metadata['portfolio'] = $portfolio;
            $sources[] = 'catalog_collection';
            $hasPortfolioMetadata = true;
        }

        $portfolioFields = $this->portfolioFieldProvenance($file['portfolio_field_values'] ?? null);
        if ($portfolioFields !== []) {
            $metadata['portfolio_fields'] = $portfolioFields;
            $sources[] = 'filespec_collection_item';
            $hasPortfolioMetadata = true;
        }

        $catalogPieceInfoSummary = $this->pieceInfoSummaryFromMetadata($catalogPieceInfo);
        if ($catalogPieceInfoSummary !== []) {
            $metadata['catalog_piece_info'] = $catalogPieceInfoSummary;
            $sources[] = 'catalog_pieceinfo';
            $hasPortfolioMetadata = true;
        }

        $xmpMetadata = $this->metadataStreamProvenance(
            $this->dictionaryRawValue($fileSpecBody, 'Metadata'),
            $objects
        );
        if ($xmpMetadata !== []) {
            $metadata['xmp_metadata'] = $xmpMetadata;
            $sources[] = 'filespec_metadata_stream';
            $hasPortfolioMetadata = true;
        }

        $pieceInfo = $this->pieceInfoProvenance(
            $this->dictionaryRawValue($fileSpecBody, 'PieceInfo'),
            $objects
        );
        if ($pieceInfo !== []) {
            $metadata['piece_info'] = $pieceInfo;
            $sources[] = 'filespec_pieceinfo';
            $hasPortfolioMetadata = true;
        }

        $outputIntents = $this->outputIntentProvenance(
            $this->dictionaryRawValue($fileSpecBody, 'OutputIntents'),
            $objects
        );
        if ($outputIntents !== []) {
            $metadata['pdfa_output_intents'] = $outputIntents;
            $sources[] = 'filespec_output_intents';
            $hasPortfolioMetadata = true;
        }

        if (!$hasPortfolioMetadata || $sources === []) {
            return [];
        }

        $metadata['sources'] = $sources;

        return $metadata;
    }

    /**
     * @param array<string, mixed> $file
     * @return array<string, mixed>
     */
    private function embeddedPayloadProvenance(array $file): array
    {
        $payload = [];
        foreach (['filename', 'mime_type', 'content_sha256'] as $key) {
            if (isset($file[$key]) && is_string($file[$key]) && $file[$key] !== '') {
                $payload[$key === 'content_sha256' ? 'sha256' : $key] = $file[$key];
            }
        }

        if (isset($file['size']) && is_int($file['size'])) {
            $payload['bytes'] = $file['size'];
        }
        if (isset($file['declared_size']) && is_int($file['declared_size'])) {
            $payload['declared_size'] = $file['declared_size'];
            if (isset($payload['bytes'])) {
                $payload['size_matches_declared'] = $payload['bytes'] === $payload['declared_size'];
            }
        }
        if (isset($file['decoded_length']) && is_int($file['decoded_length'])) {
            $payload['decoded_length'] = $file['decoded_length'];
            if (array_key_exists('decoded_length_matches', $file)) {
                $payload['decoded_length_matches'] = $file['decoded_length_matches'];
            }
        }

        foreach (['checksum_algorithm', 'checksum', 'computed_checksum', 'checksum_matches'] as $key) {
            if (array_key_exists($key, $file)) {
                $payload[$key] = $file[$key];
            }
        }

        return $payload;
    }

    /**
     * FileSpec /RF related files extend a primary /EF entry. They are
     * attachment-local review rows, not visible document text or promoted
     * metadata payloads.
     *
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function relatedFileReviewRows(?string $relatedFilesValue, array $objects): array
    {
        $relatedFiles = $this->resolveDictionaryFromValue($relatedFilesValue, $objects);
        if ($relatedFiles === null) {
            return [];
        }

        $rows = [];
        foreach ($this->dictionaryEntries($relatedFiles['body']) as $rfKey => $streamValues) {
            if (!in_array($rfKey, ['F', 'UF', 'DOS', 'Unix', 'Mac'], true)) {
                continue;
            }

            $items = $this->arrayItemsFromValue($streamValues, $objects);
            if ($items === []) {
                $items = [trim($streamValues)];
            }

            $relatedFileIndex = 0;
            for ($index = 0, $count = count($items); $index < $count; $index++) {
                $relatedFilename = $this->stringValueFromRaw($items[$index], $objects);
                if ($relatedFilename !== null && $relatedFilename !== '' && $index + 1 < $count) {
                    $stream = $this->embeddedFileStreamFromValue($items[$index + 1], $objects);
                    if ($stream !== null) {
                        $rows[] = $this->relatedFileReviewRow(
                            $rfKey,
                            $relatedFileIndex,
                            $stream,
                            $objects,
                            $relatedFilename
                        );
                        $relatedFileIndex++;
                        $index++;
                        continue;
                    }
                }

                $stream = $this->embeddedFileStreamFromValue($items[$index], $objects);
                if ($stream === null) {
                    continue;
                }

                $rows[] = $this->relatedFileReviewRow($rfKey, $relatedFileIndex, $stream, $objects);
                $relatedFileIndex++;
            }
        }

        return $rows;
    }

    /**
     * @param array{object: int|null, dictionary: string, content: string, filters: list<string>} $stream
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function relatedFileReviewRow(
        string $rfKey,
        int $relatedFileIndex,
        array $stream,
        array $objects,
        ?string $relatedFilename = null
    ): array
    {
        $row = [
            'source' => 'filespec_related_files',
            'rf_key' => $rfKey,
            'related_file_index' => $relatedFileIndex,
            'embedded_file_object' => $stream['object'],
            'size' => strlen($stream['content']),
            'content_sha256' => hash('sha256', $stream['content']),
            'payload_included' => false,
        ];

        if ($relatedFilename !== null && $relatedFilename !== '') {
            $row['related_filename'] = $relatedFilename;
            $row['related_filename_source'] = 'rf_name_pair';
            foreach ($this->relatedFilenamePathReview($relatedFilename) as $key => $metadataValue) {
                $row[$key] = $metadataValue;
            }
        }

        $mimeType = $this->dictionaryNameValue($stream['dictionary'], 'Subtype', $objects);
        if ($mimeType !== null && $mimeType !== '') {
            $row['mime_type'] = $mimeType;
        }

        if ($stream['filters'] !== []) {
            $row['filters'] = $stream['filters'];
        }

        foreach ($this->embeddedFileStreamMetadata($stream['dictionary'], $objects, $stream['content']) as $key => $metadataValue) {
            $row[$key] = $metadataValue;
        }

        foreach ($this->embeddedFileParams($stream['dictionary'], $objects, $stream['content']) as $key => $metadataValue) {
            $row[$key] = $metadataValue;
        }

        return $row;
    }

    /**
     * @param mixed $relatedFiles
     * @return array<string, mixed>
     */
    private function associatedFileRelatedFilesProvenance(mixed $relatedFiles): array
    {
        if (!is_array($relatedFiles) || $relatedFiles === []) {
            return [];
        }

        $keys = [];
        $objectNumbers = [];
        $hashes = [];
        $mimeTypes = [];
        foreach ($relatedFiles as $relatedFile) {
            if (!is_array($relatedFile)) {
                continue;
            }

            $key = $relatedFile['rf_key'] ?? null;
            if (is_string($key) && $key !== '') {
                $keys[] = $key;
            }

            $objectNumber = $relatedFile['embedded_file_object'] ?? null;
            if (is_int($objectNumber)) {
                $objectNumbers[] = $objectNumber;
            }

            $hash = $relatedFile['content_sha256'] ?? null;
            if (is_string($hash) && $hash !== '') {
                $hashes[] = $hash;
            }

            $mimeType = $relatedFile['mime_type'] ?? null;
            if (is_string($mimeType) && $mimeType !== '') {
                $mimeTypes[] = $mimeType;
            }
        }

        if ($keys === [] && $objectNumbers === [] && $hashes === [] && $mimeTypes === []) {
            return [];
        }

        return [
            'source' => 'filespec_related_files',
            'review_only' => true,
            'payload_included' => false,
            'count' => count($relatedFiles),
            'rf_keys' => $this->uniqueStrings($keys),
            'embedded_file_objects' => array_values(array_unique($objectNumbers)),
            'mime_types' => $this->uniqueStrings($mimeTypes),
            'sha256' => $this->uniqueStrings($hashes),
        ];
    }

    /**
     * Associated FileSpec relationship/checksum state is review metadata for
     * importers. The payload bytes may be returned by this low-level extractor,
     * but this summary only carries hashes, sizes, and checksum match state.
     *
     * @param array<string, mixed> $file
     * @return array<string, mixed>
     */
    private function associatedFileProvenanceReview(array $file): array
    {
        $source = $file['source'] ?? null;
        $relationship = $file['relationship'] ?? null;
        $isAssociated = ($file['associated_file'] ?? false) === true
            || (is_string($source) && str_contains($source, 'associated'))
            || (is_string($relationship) && $relationship !== '');
        if (!$isAssociated) {
            return [];
        }

        $sources = [];
        $metadata = [
            'source' => 'associated_file_provenance',
            'review_only' => true,
            'payload_included' => false,
            'payload_content_returned' => array_key_exists('content', $file),
        ];

        if (is_string($relationship) && $relationship !== '') {
            $metadata['relationship'] = $relationship;
            $metadata['relationship_role'] = self::ASSOCIATED_FILE_RELATIONSHIP_ROLES[$relationship] ?? 'unrecognized';
            $metadata['relationship_status'] = array_key_exists($relationship, self::ASSOCIATED_FILE_RELATIONSHIP_ROLES)
                ? 'standard_pdf_associated_file_relationship'
                : 'unrecognized_pdf_associated_file_relationship';
            $sources[] = 'filespec_afrelationship';
        } else {
            $metadata['relationship_status'] = 'missing_pdf_associated_file_relationship';
        }

        $payload = $this->embeddedPayloadProvenance($file);
        if ($payload !== []) {
            $metadata['payload'] = $payload;
            $sources[] = 'embedded_file_payload_hash';
            if (array_key_exists('checksum', $payload) || array_key_exists('computed_checksum', $payload)) {
                $sources[] = 'embedded_file_params_checksum';
            }
        }

        $relatedFiles = $this->associatedFileRelatedFilesProvenance($file['related_files'] ?? null);
        if ($relatedFiles !== []) {
            $metadata['related_files'] = $relatedFiles;
            $sources[] = 'filespec_related_files';
        }

        if ($sources === []) {
            return [];
        }

        $metadata['sources'] = $sources;

        return $metadata;
    }

    /**
     * @param array<string, mixed> $portfolioMetadata
     * @return array<string, mixed>
     */
    private function portfolioCollectionProvenance(array $portfolioMetadata): array
    {
        if ($portfolioMetadata === []) {
            return [];
        }

        $metadata = ['source' => 'catalog_collection'];
        foreach (['type', 'view', 'default_document'] as $key) {
            if (isset($portfolioMetadata[$key]) && is_string($portfolioMetadata[$key]) && $portfolioMetadata[$key] !== '') {
                $metadata[$key] = $portfolioMetadata[$key];
            }
        }

        $schema = $portfolioMetadata['schema'] ?? null;
        if (is_array($schema) && $schema !== []) {
            $metadata['schema_fields'] = array_keys($schema);
            $metadata['schema_field_count'] = count($schema);
        }

        $sort = $portfolioMetadata['sort'] ?? null;
        if (is_array($sort)) {
            $keys = $sort['keys'] ?? null;
            if (is_array($keys) && $keys !== []) {
                $metadata['sort_keys'] = array_values($keys);
            }

            $ascending = $sort['ascending'] ?? null;
            if (is_array($ascending) && $ascending !== []) {
                $metadata['sort_ascending'] = array_values($ascending);
            }
        }

        return $metadata;
    }

    /**
     * @param mixed $portfolioFieldValues
     * @return array<string, mixed>
     */
    private function portfolioFieldProvenance(mixed $portfolioFieldValues): array
    {
        if (!is_array($portfolioFieldValues) || $portfolioFieldValues === []) {
            return [];
        }

        return [
            'source' => 'filespec_collection_item',
            'field_count' => count($portfolioFieldValues),
            'field_names' => array_keys($portfolioFieldValues),
            'values' => $portfolioFieldValues,
        ];
    }

    /**
     * @param array<string, mixed> $pieceInfo
     * @return array<string, mixed>
     */
    private function pieceInfoSummaryFromMetadata(array $pieceInfo): array
    {
        if ($pieceInfo === []) {
            return [];
        }

        return [
            'source' => 'pieceinfo_review',
            'count' => count($pieceInfo),
            'applications' => array_keys($pieceInfo),
            'entries' => $pieceInfo,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function metadataStreamProvenance(?string $value, array $objects): array
    {
        if ($value === null) {
            return [];
        }

        $objectNumber = $this->objectNumberFromReference($value);
        $body = $objectNumber === null
            ? trim($this->resolveRawValue($value, $objects) ?? $value)
            : $this->objectBodyFromReferenceValue($value, $objects);
        if ($body === null || $body === '') {
            return [];
        }

        $stream = $this->decodeStreamObject($body, $objects);
        if ($stream === null) {
            return [];
        }

        $metadata = [
            'object_number' => $objectNumber,
            'bytes' => strlen($stream['content']),
            'sha256' => hash('sha256', $stream['content']),
            'payload_included' => false,
        ];

        foreach ([
            'type' => $this->dictionaryNameValue($stream['dictionary'], 'Type', $objects),
            'subtype' => $this->dictionaryNameValue($stream['dictionary'], 'Subtype', $objects),
        ] as $key => $value) {
            if (is_string($value) && $value !== '') {
                $metadata[$key] = $value;
            }
        }

        if ($stream['filters'] !== []) {
            $metadata['filters'] = $stream['filters'];
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function pieceInfoProvenance(?string $pieceInfoValue, array $objects): array
    {
        $pieceInfo = $this->resolveDictionaryFromValue($pieceInfoValue, $objects);
        if ($pieceInfo === null) {
            return [];
        }

        $entries = [];
        foreach ($this->dictionaryEntries($pieceInfo['body']) as $application => $pieceValue) {
            $piece = $this->resolveDictionaryFromValue($pieceValue, $objects);
            if ($piece === null) {
                continue;
            }

            $entry = ['application' => $application];
            $lastModified = $this->dictionaryStringValue($piece['body'], 'LastModified', $objects);
            if ($lastModified !== null && $lastModified !== '') {
                $entry['last_modified'] = $lastModified;
            }

            $private = $this->pieceInfoPrivateProvenance($this->dictionaryRawValue($piece['body'], 'Private'), $objects);
            foreach ($private as $key => $value) {
                $entry[$key] = $value;
            }

            if (count($entry) > 1) {
                $entries[] = $entry;
            }
        }

        if ($entries === []) {
            return [];
        }

        return [
            'source' => 'filespec_pieceinfo_provenance',
            'review_only' => true,
            'payload_included' => false,
            'count' => count($entries),
            'applications' => $this->uniqueStrings(array_map(
                static fn (array $entry): string => (string) $entry['application'],
                $entries
            )),
            'entries' => $entries,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function pieceInfoPrivateProvenance(?string $privateValue, array $objects): array
    {
        if ($privateValue === null) {
            return [];
        }

        $stream = $this->pieceInfoPrivateStreamMetadata($privateValue, $objects);
        if ($stream !== null) {
            return [
                'private_keys' => ['Private'],
                'private_streams' => [['key' => 'Private'] + $stream],
            ];
        }

        $private = $this->resolveDictionaryFromValue($privateValue, $objects);
        if ($private === null) {
            $reviewValue = $this->reviewValueFromRaw($privateValue, $objects);
            return ($reviewValue === null || $reviewValue === '') ? [] : ['private_value' => $reviewValue];
        }

        $keys = [];
        $metadataStreams = [];
        $privateStreams = [];
        $outputIntents = [];
        foreach ($this->dictionaryEntries($private['body']) as $key => $value) {
            $keys[] = $key;
            if ($key === 'Metadata') {
                $metadata = $this->metadataStreamProvenance($value, $objects);
                if ($metadata !== []) {
                    $metadataStreams[] = $metadata;
                }
                continue;
            }

            if ($key === 'OutputIntents') {
                $outputIntent = $this->outputIntentProvenance($value, $objects);
                if ($outputIntent !== []) {
                    $outputIntents = $outputIntent;
                }
                continue;
            }

            $stream = $this->pieceInfoPrivateStreamMetadata($value, $objects);
            if ($stream !== null) {
                $privateStreams[] = ['key' => $key] + $stream;
            }
        }

        $metadata = [];
        if ($keys !== []) {
            $metadata['private_keys'] = $keys;
        }
        if ($metadataStreams !== []) {
            $metadata['metadata_streams'] = $metadataStreams;
        }
        if ($privateStreams !== []) {
            $metadata['private_streams'] = $privateStreams;
        }
        if ($outputIntents !== []) {
            $metadata['output_intents'] = $outputIntents;
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function outputIntentProvenance(?string $value, array $objects): array
    {
        if ($value === null) {
            return [];
        }

        $intents = [];
        foreach ($this->outputIntentDictionariesFromValue($value, $objects) as $dictionary) {
            $intent = $this->outputIntentProvenanceFromDictionary($dictionary, $objects);
            if ($intent !== null) {
                $intents[] = $intent;
            }
        }

        if ($intents === []) {
            return [];
        }

        $pdfa = $this->pdfaOutputIntentSummary($intents);

        return [
            'count' => count($intents),
            'has_pdfa_output_intent' => $pdfa !== null,
            'output_condition_identifiers' => $pdfa['output_condition_identifiers'] ?? [],
            'profile_sha256' => $pdfa['profile_sha256'] ?? [],
            'intents' => $intents,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function outputIntentDictionariesFromValue(string $value, array $objects): array
    {
        $resolved = trim($this->resolveRawValue($value, $objects) ?? $value);
        if ($resolved === '') {
            return [];
        }

        if (str_starts_with($resolved, '[')) {
            $dictionaries = [];
            foreach ($this->arrayItemsFromValue($resolved, $objects) as $item) {
                foreach ($this->outputIntentDictionariesFromValue($item, $objects) as $dictionary) {
                    $dictionaries[] = $dictionary;
                }
            }

            return $dictionaries;
        }

        if (str_starts_with($resolved, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($resolved, 0);
            return $dictionary === null ? [] : [$dictionary['body']];
        }

        $dictionary = $this->dictionaryObjectBody($resolved);

        return $dictionary === null ? [] : [$dictionary];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function outputIntentProvenanceFromDictionary(string $dictionary, array $objects): ?array
    {
        $subtype = $this->dictionaryNameValue($dictionary, 'S', $objects);
        $identifier = $this->dictionaryStringValue($dictionary, 'OutputConditionIdentifier', $objects);
        $condition = $this->dictionaryStringValue($dictionary, 'OutputCondition', $objects);
        $registryName = $this->dictionaryStringValue($dictionary, 'RegistryName', $objects);
        $info = $this->dictionaryStringValue($dictionary, 'Info', $objects);
        $type = $this->dictionaryNameValue($dictionary, 'Type', $objects);

        if ($subtype === null && $identifier === null && $condition === null && $info === null) {
            return null;
        }

        $intent = [
            'is_pdfa' => $subtype === 'GTS_PDFA1',
        ];

        foreach ([
            'type' => $type,
            'subtype' => $subtype,
            'output_condition_identifier' => $identifier,
            'output_condition' => $condition,
            'registry_name' => $registryName,
            'info' => $info,
        ] as $key => $value) {
            if (is_string($value) && $value !== '') {
                $intent[$key] = $value;
            }
        }

        $profile = $this->outputProfileMetadata($dictionary, $objects);
        if ($profile !== null) {
            $intent['dest_output_profile'] = $profile;
        }

        return $intent;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function outputProfileMetadata(string $dictionary, array $objects): ?array
    {
        $value = $this->dictionaryRawValue($dictionary, 'DestOutputProfile');
        if ($value === null) {
            return null;
        }

        $objectNumber = $this->objectNumberFromReference($value);
        $body = $objectNumber === null ? null : $this->objectBodyFromReferenceValue($value, $objects);
        if ($body === null) {
            return null;
        }

        $stream = $this->decodeStreamObject($body, $objects);
        if ($stream === null) {
            return null;
        }

        $profile = [
            'object_number' => $objectNumber,
            'bytes' => strlen($stream['content']),
            'sha256' => hash('sha256', $stream['content']),
        ];

        $components = $this->dictionaryIntegerValue($stream['dictionary'], 'N');
        if ($components !== null) {
            $profile['color_components'] = $components;
        }

        $alternate = $this->dictionaryNameValue($stream['dictionary'], 'Alternate', $objects);
        if ($alternate !== null && $alternate !== '') {
            $profile['alternate_color_space'] = $alternate;
        }

        if ($stream['filters'] !== []) {
            $profile['filters'] = $stream['filters'];
        }

        return $profile;
    }

    /**
     * @param list<array<string, mixed>> $outputIntents
     * @return array{has_output_intent: bool, output_condition_identifiers: list<string>, profile_sha256: list<string>}|null
     */
    private function pdfaOutputIntentSummary(array $outputIntents): ?array
    {
        $identifiers = [];
        $hashes = [];
        foreach ($outputIntents as $intent) {
            if (($intent['subtype'] ?? null) !== 'GTS_PDFA1') {
                continue;
            }

            if (isset($intent['output_condition_identifier']) && is_string($intent['output_condition_identifier'])) {
                $identifiers[] = $intent['output_condition_identifier'];
            }

            $profile = $intent['dest_output_profile'] ?? null;
            if (is_array($profile) && isset($profile['sha256']) && is_string($profile['sha256'])) {
                $hashes[] = $profile['sha256'];
            }
        }

        if ($identifiers === [] && $hashes === []) {
            return null;
        }

        return [
            'has_output_intent' => true,
            'output_condition_identifiers' => $this->uniqueStrings($identifiers),
            'profile_sha256' => $this->uniqueStrings($hashes),
        ];
    }

    /**
     * Prefer the embedded stream whose key matches the selected FileSpec
     * filename entry, then fall back to the historical cross-platform order.
     *
     * @return list<string>
     */
    private function embeddedFileKeys(string $filenameSource): array
    {
        if (in_array($filenameSource, self::EMBEDDED_FILE_FALLBACK_KEYS, true)) {
            return array_values(array_unique([$filenameSource, ...self::EMBEDDED_FILE_FALLBACK_KEYS]));
        }

        return self::EMBEDDED_FILE_FALLBACK_KEYS;
    }

    /**
     * @param array<int, string> $objects
     * @return array{0: string, 1: string}
     */
    private function fileSpecFilenameWithSource(string $fileSpecBody, array $objects, ?string $name): array
    {
        foreach (['UF', 'F', 'DOS', 'Unix', 'Mac'] as $key) {
            $filename = $this->dictionaryStringValue($fileSpecBody, $key, $objects);
            if ($filename !== null && $filename !== '') {
                return [$filename, $key];
            }
        }

        if ($name !== null && $name !== '') {
            return [$name, 'name_tree_key'];
        }

        return ['embedded-file', 'generated'];
    }

    /**
     * FileSpec-local metadata is review-only state for attachment import. Keep
     * identifiers as binary-safe hex and never promote them into visible text.
     *
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function fileSpecMetadata(string $fileSpecBody, array $objects): array
    {
        $metadata = [];

        $fileSystem = $this->dictionaryNameValue($fileSpecBody, 'FS', $objects);
        if ($fileSystem !== null && $fileSystem !== '') {
            $metadata['file_system'] = $fileSystem;
            $metadata['file_system_status'] = $this->fileSpecFileSystemStatus($fileSystem);
        }

        $identifier = $this->fileSpecIdentifierReview($this->dictionaryRawValue($fileSpecBody, 'ID'), $objects);
        if ($identifier !== []) {
            $metadata['file_identifier'] = $identifier;
        }

        $volatile = $this->reviewValueFromRaw($this->dictionaryRawValue($fileSpecBody, 'V'), $objects);
        if (is_bool($volatile)) {
            $metadata['volatile'] = $volatile;
            $metadata['volatile_status'] = $volatile
                ? 'volatile_file_spec_review'
                : 'stable_file_spec_review';
        }

        return $metadata;
    }

    private function fileSpecFileSystemStatus(string $fileSystem): string
    {
        return match ($fileSystem) {
            'URL' => 'external_url_file_system_review_only',
            'DOS', 'Mac', 'Unix' => 'platform_file_system_review',
            default => 'custom_file_system_review_only',
        };
    }

    /**
     * FileSpec names are document-provided metadata and can be platform paths
     * or URL-like values. Keep the original filename intact while also exposing
     * a basename-only candidate for WordPress storage/review decisions.
     *
     * @return array<string, mixed>
     */
    private function filenamePathReview(string $filename): array
    {
        $normalized = str_replace('\\', '/', $filename);
        $isWindowsDrivePath = preg_match('/^[A-Za-z]:\//', $normalized) === 1;
        $urlScheme = null;
        $path = $normalized;
        if (!$isWindowsDrivePath && preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):\/\//', $filename, $match) === 1) {
            $urlScheme = strtolower($match[1]);
            $parsedPath = parse_url($filename, PHP_URL_PATH);
            $path = is_string($parsedPath) ? str_replace('\\', '/', $parsedPath) : '';
        }

        $segments = array_values(array_filter(
            explode('/', $path),
            static fn (string $segment): bool => $segment !== ''
        ));
        $leaf = $segments === [] ? $filename : (string) end($segments);
        if ($leaf === '' || $leaf === '.' || $leaf === '..') {
            $leaf = 'attachment';
        }

        $hasParentSegment = in_array('..', $segments, true);
        $hasPathSegments = $urlScheme !== null
            || $isWindowsDrivePath
            || str_starts_with($path, '/')
            || str_contains($normalized, '/')
            || str_contains($filename, '\\')
            || $hasParentSegment
            || in_array('.', $segments, true);

        $review = [
            'filename_leaf' => $leaf,
            'filename_storage_name' => $this->safeFilenameStorageName($leaf),
            'filename_path_status' => $urlScheme !== null
                ? 'url_path_review_only'
                : ($isWindowsDrivePath || str_starts_with($path, '/')
                    ? 'absolute_path_review_only'
                    : ($hasPathSegments ? 'relative_path_segments_review_only' : 'basename_only')),
            'filename_has_path_segments' => $hasPathSegments,
        ];

        if ($hasParentSegment) {
            $review['filename_contains_parent_segment'] = true;
        }
        if ($isWindowsDrivePath || str_starts_with($path, '/')) {
            $review['filename_absolute_path'] = true;
        }
        if ($urlScheme !== null) {
            $review['filename_url_scheme'] = $urlScheme;
        }

        return $review;
    }

    /**
     * @return array<string, mixed>
     */
    private function relatedFilenamePathReview(string $filename): array
    {
        $review = [];
        foreach ($this->filenamePathReview($filename) as $key => $value) {
            $review['related_' . $key] = $value;
        }

        return $review;
    }

    private function safeFilenameStorageName(string $leaf): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($leaf));
        $safe = is_string($safe) ? trim($safe, ".-_\t\n\r\0\x0B") : '';

        return $safe === '' ? 'attachment' : $safe;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function fileSpecIdentifierReview(?string $value, array $objects): array
    {
        if ($value === null) {
            return [];
        }

        $identifiers = [];
        foreach ($this->arrayItemsFromValue($value, $objects) as $item) {
            $bytes = $this->byteStringValueFromRaw($item, $objects);
            if ($bytes !== null && $bytes !== '') {
                $identifiers[] = strtolower(bin2hex($bytes));
            }
        }

        if ($identifiers === []) {
            return [];
        }

        $review = [
            'identifier_count' => count($identifiers),
            'identifiers_hex' => $identifiers,
            'identifier_status' => count($identifiers) >= 2
                ? 'complete_file_identifier_pair'
                : 'partial_file_identifier_pair',
        ];

        if (isset($identifiers[0])) {
            $review['permanent_id_hex'] = $identifiers[0];
        }
        if (isset($identifiers[1])) {
            $review['changing_id_hex'] = $identifiers[1];
        }

        return $review;
    }

    /**
     * @param array<int, string> $objects
     * @return array{object: int|null, dictionary: string, content: string, filters: list<string>}|null
     */
    private function embeddedFileStreamFromValue(string $value, array $objects): ?array
    {
        $objectNumber = $this->objectNumberFromReference($value);
        $body = $objectNumber !== null ? $this->objectBodyFromReferenceValue($value, $objects) : trim($value);
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
    private function embeddedFileStreamMetadata(string $streamDictionary, array $objects, string $content): array
    {
        $metadata = [];
        $decodedLength = $this->dictionaryIntegerValue($streamDictionary, 'DL', $objects);
        if ($decodedLength !== null) {
            $metadata['decoded_length'] = $decodedLength;
            $metadata['decoded_length_matches'] = $decodedLength === strlen($content);
        }

        return $metadata;
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

        $metadata = $this->embeddedFileParamsScalarMetadata($params['body'], $objects, $content);
        $macFileInfo = $this->embeddedFileMacInfoReview($this->dictionaryRawValue($params['body'], 'Mac'), $objects);
        if ($macFileInfo !== []) {
            $metadata['mac_file_info'] = $macFileInfo;
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function embeddedFileParamsScalarMetadata(string $paramsBody, array $objects, string $content): array
    {
        $metadata = [];
        $size = $this->dictionaryIntegerValue($paramsBody, 'Size', $objects);
        if ($size !== null) {
            $metadata['declared_size'] = $size;
        }

        $checksum = $this->dictionaryChecksumValue($paramsBody, 'CheckSum', $objects);
        if ($checksum !== null && $checksum !== '') {
            $metadata['checksum'] = $checksum;
            $metadata['checksum_algorithm'] = 'md5';
            $metadata['computed_checksum'] = hash('md5', $content);
            $metadata['checksum_matches'] = hash_equals($metadata['computed_checksum'], $checksum);
        }

        $createdAt = $this->dictionaryStringValue($paramsBody, 'CreationDate', $objects);
        if ($createdAt !== null && $createdAt !== '') {
            $metadata['created_at'] = $createdAt;
            $createdAtUtc = PdfDateTimeNormalizer::toUtc($createdAt);
            if ($createdAtUtc !== null) {
                $metadata['created_at_utc'] = $createdAtUtc;
            }
        }

        $modifiedAt = $this->dictionaryStringValue($paramsBody, 'ModDate', $objects);
        if ($modifiedAt !== null && $modifiedAt !== '') {
            $metadata['modified_at'] = $modifiedAt;
            $modifiedAtUtc = PdfDateTimeNormalizer::toUtc($modifiedAt);
            if ($modifiedAtUtc !== null) {
                $metadata['modified_at_utc'] = $modifiedAtUtc;
            }
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function embeddedFileMacInfoReview(?string $macValue, array $objects): array
    {
        $mac = $this->resolveDictionaryFromValue($macValue, $objects);
        if ($mac === null) {
            return [];
        }

        $review = [
            'source' => 'embedded_file_params_mac',
            'review_only' => true,
            'payload_bytes_included' => false,
        ];

        $fileType = $this->macOsFourCharacterCodeReview(
            $this->dictionaryRawValue($mac['body'], 'Subtype'),
            $objects
        );
        if ($fileType !== null) {
            $review['file_type'] = $fileType;
        }

        $creator = $this->macOsFourCharacterCodeReview(
            $this->dictionaryRawValue($mac['body'], 'Creator'),
            $objects
        );
        if ($creator !== null) {
            $review['creator'] = $creator;
        }

        $resourceFork = $this->embeddedFileMacResourceForkReview(
            $this->dictionaryRawValue($mac['body'], 'ResFork'),
            $objects
        );
        if ($resourceFork !== []) {
            $review['has_resource_fork'] = true;
            $review['resource_fork'] = $resourceFork;
        }

        return count($review) > 3 ? $review : [];
    }

    /**
     * @param array<int, string> $objects
     * @return array{integer: int, hex: string, four_char_code?: string}|null
     */
    private function macOsFourCharacterCodeReview(?string $value, array $objects): ?array
    {
        if ($value === null) {
            return null;
        }

        $resolved = trim($this->resolveRawValue($value, $objects) ?? $value);
        if (preg_match('/^\d+$/', $resolved) !== 1) {
            return null;
        }

        $integer = (int) $resolved;
        if ($integer < 0 || $integer > 0xffffffff) {
            return null;
        }

        $bytes = pack('N', $integer);
        $review = [
            'integer' => $integer,
            'hex' => strtolower(sprintf('%08x', $integer)),
        ];

        if (preg_match('/^[\x20-\x7e]{4}$/', $bytes) === 1) {
            $review['four_char_code'] = $bytes;
        }

        return $review;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function embeddedFileMacResourceForkReview(?string $streamValue, array $objects): array
    {
        if ($streamValue === null) {
            return [];
        }

        $stream = $this->embeddedFileStreamFromValue($streamValue, $objects);
        if ($stream === null) {
            return [];
        }

        $metadata = [
            'source' => 'embedded_file_params_mac_resource_fork',
            'embedded_file_object' => $stream['object'],
            'size' => strlen($stream['content']),
            'content_sha256' => hash('sha256', $stream['content']),
            'payload_included' => false,
        ];

        $mimeType = $this->dictionaryNameValue($stream['dictionary'], 'Subtype', $objects);
        if ($mimeType !== null && $mimeType !== '') {
            $metadata['mime_type'] = $mimeType;
        }
        if ($stream['filters'] !== []) {
            $metadata['filters'] = $stream['filters'];
        }

        foreach ($this->embeddedFileStreamMetadata($stream['dictionary'], $objects, $stream['content']) as $key => $metadataValue) {
            $metadata[$key] = $metadataValue;
        }

        $params = $this->resolveDictionaryFromValue($this->dictionaryRawValue($stream['dictionary'], 'Params'), $objects);
        if ($params !== null) {
            foreach ($this->embeddedFileParamsScalarMetadata($params['body'], $objects, $stream['content']) as $key => $metadataValue) {
                $metadata[$key] = $metadataValue;
            }
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

        $folders = $this->collectionFolderMetadata($this->dictionaryRawValue($body, 'Folders'), $objects);
        if ($folders !== []) {
            $metadata['has_folders'] = true;
            $metadata['folder_count'] = count($folders);
            $metadata['folders'] = $folders;
        } elseif ($this->dictionaryRawValue($body, 'Folders') !== null) {
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
     * @return list<array<string, mixed>>
     */
    private function collectionFolderMetadata(?string $folderValue, array $objects): array
    {
        if ($folderValue === null) {
            return [];
        }

        $rows = [];
        $this->collectCollectionFolderMetadata($folderValue, $objects, $rows);

        return $rows;
    }

    /**
     * @param array<int, string> $objects
     * @param list<array<string, mixed>> $rows
     * @param array<int, true> $seen
     */
    private function collectCollectionFolderMetadata(
        string $folderValue,
        array $objects,
        array &$rows,
        array $seen = [],
        int $depth = 0,
        int $siblingIndex = 0,
        ?int $parentObjectId = null
    ): void {
        if ($depth > 20) {
            return;
        }

        $folderObjectId = $this->objectNumberFromReference($folderValue);
        if ($folderObjectId !== null) {
            if (isset($seen[$folderObjectId])) {
                return;
            }
            $seen[$folderObjectId] = true;
        }

        $folder = $this->resolveDictionaryFromValue($folderValue, $objects);
        if ($folder === null) {
            return;
        }

        $body = $folder['body'];
        $row = [
            'source' => 'collection_folder_tree',
            'review_only' => true,
            'payload_bytes_included' => false,
            'depth' => $depth,
            'sibling_index' => $siblingIndex,
        ];

        if ($folder['object'] !== null) {
            $row['folder_object_id'] = $folder['object'];
        }
        if ($parentObjectId !== null) {
            $row['parent_folder_object_id'] = $parentObjectId;
        }

        foreach ([
            'type' => $this->dictionaryNameValue($body, 'Type', $objects),
            'name' => $this->reviewValueFromRaw($this->dictionaryRawValue($body, 'Name'), $objects),
            'description' => $this->reviewValueFromRaw($this->dictionaryRawValue($body, 'Desc'), $objects),
            'folder_id' => $this->reviewValueFromRaw($this->dictionaryRawValue($body, 'ID'), $objects),
            'created_at' => $this->reviewValueFromRaw($this->dictionaryRawValue($body, 'CreationDate'), $objects),
            'modified_at' => $this->reviewValueFromRaw($this->dictionaryRawValue($body, 'ModDate'), $objects),
        ] as $key => $value) {
            if ($value !== null && $value !== '') {
                $row[$key] = $value;
            }
        }

        $files = $this->collectionFolderFileMetadata($this->dictionaryRawValue($body, 'F'), $objects);
        if ($files !== []) {
            $row['file_count'] = count($files);
            $row['files'] = $files;
        }

        $childValue = $this->dictionaryRawValue($body, 'Child');
        $childObjectId = $childValue === null ? null : $this->objectNumberFromReference($childValue);
        if ($childObjectId !== null) {
            $row['child_folder_object_id'] = $childObjectId;
        }
        $nextValue = $this->dictionaryRawValue($body, 'Next');
        $nextObjectId = $nextValue === null ? null : $this->objectNumberFromReference($nextValue);
        if ($nextObjectId !== null) {
            $row['next_folder_object_id'] = $nextObjectId;
        }

        $rows[] = $row;

        if ($childValue !== null) {
            $this->collectCollectionFolderMetadata($childValue, $objects, $rows, $seen, $depth + 1, 0, $folder['object']);
        }
        if ($nextValue !== null) {
            $this->collectCollectionFolderMetadata($nextValue, $objects, $rows, $seen, $depth, $siblingIndex + 1, $parentObjectId);
        }
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function collectionFolderFileMetadata(?string $filesValue, array $objects): array
    {
        if ($filesValue === null) {
            return [];
        }

        $items = $this->arrayItemsFromValue($filesValue, $objects);
        if ($items === []) {
            $items = [$filesValue];
        }

        $rows = [];
        foreach ($items as $index => $item) {
            $fileSpec = $this->resolveDictionaryFromValue($item, $objects);
            $row = [
                'source' => 'collection_folder_file_reference',
                'file_index' => $index,
                'review_only' => true,
                'payload_bytes_included' => false,
            ];

            $fileSpecObjectId = $this->objectNumberFromReference($item);
            if ($fileSpecObjectId !== null) {
                $row['file_spec_object_id'] = $fileSpecObjectId;
            }

            if ($fileSpec !== null) {
                [$filename, $filenameSource] = $this->fileSpecFilenameWithSource($fileSpec['body'], $objects, null);
                if ($filename !== '') {
                    $row['filename'] = $filename;
                    $row['filename_source'] = $filenameSource;
                    foreach ($this->filenamePathReview($filename) as $key => $metadataValue) {
                        $row[$key] = $metadataValue;
                    }
                }

                foreach ([
                    'description' => $this->dictionaryStringValue($fileSpec['body'], 'Desc', $objects),
                    'relationship' => $this->dictionaryNameValue($fileSpec['body'], 'AFRelationship', $objects),
                ] as $key => $value) {
                    if ($value !== null && $value !== '') {
                        $row[$key] = $value;
                    }
                }
            } else {
                $filename = $this->reviewValueFromRaw($item, $objects);
                if (!is_string($filename) || $filename === '') {
                    continue;
                }
                $row['filename'] = $filename;
                $row['filename_source'] = 'collection_folder_file_name';
                foreach ($this->filenamePathReview($filename) as $key => $metadataValue) {
                    $row[$key] = $metadataValue;
                }
            }

            if (count($row) > 4) {
                $rows[] = $row;
            }
        }

        return $rows;
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
     * @param array<string, mixed> $portfolioMetadata
     * @param array<int, string> $objects
     * @param array<string, mixed> $file
     * @return array<string, array<string, mixed>>
     */
    private function collectionFieldValueReview(
        array $portfolioMetadata,
        ?string $collectionItemValue,
        array $objects,
        array $file
    ): array
    {
        $schema = $portfolioMetadata['schema'] ?? null;
        if (!is_array($schema) || $schema === []) {
            return [];
        }

        $collectionItem = $this->resolveDictionaryFromValue($collectionItemValue, $objects);
        $collectionItemEntries = $collectionItem === null ? [] : $this->dictionaryEntries($collectionItem['body']);
        $metadata = [];
        foreach ($schema as $fieldName => $fieldSchema) {
            if (!is_string($fieldName) || !is_array($fieldSchema)) {
                continue;
            }

            $subtype = $fieldSchema['subtype'] ?? null;
            $value = null;
            if (isset($collectionItemEntries[$fieldName])) {
                $value = $this->collectionItemFieldValueReview($collectionItemEntries[$fieldName], $objects);
            } elseif (is_string($subtype)) {
                $value = $this->collectionFileRelatedFieldValueReview($subtype, $file);
            }

            if ($value === null) {
                continue;
            }

            $field = [];
            foreach (['subtype', 'label', 'order', 'visible', 'editable'] as $schemaKey) {
                if (array_key_exists($schemaKey, $fieldSchema)) {
                    $field[$schemaKey] = $fieldSchema[$schemaKey];
                }
            }

            $valueType = is_string($subtype) ? $this->collectionFieldValueType($subtype) : null;
            if ($valueType !== null) {
                $field['value_type'] = $valueType;
            }

            foreach ($value as $key => $entryValue) {
                $field[$key] = $entryValue;
            }

            $metadata[$fieldName] = $field;
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function collectionItemFieldValueReview(string $value, array $objects): ?array
    {
        $subitem = $this->resolveDictionaryFromValue($value, $objects);
        if ($subitem !== null) {
            $body = $subitem['body'];
            if ($this->dictionaryRawValue($body, 'D') !== null || $this->dictionaryRawValue($body, 'P') !== null) {
                $metadata = ['source' => 'collection_subitem'];

                $type = $this->dictionaryNameValue($body, 'Type', $objects);
                if ($type !== null && $type !== '') {
                    $metadata['subitem_type'] = $type;
                }

                $data = $this->reviewValueFromRaw($this->dictionaryRawValue($body, 'D'), $objects);
                if ($data !== null && $data !== '') {
                    $metadata['value'] = $data;
                }

                $prefix = $this->reviewValueFromRaw($this->dictionaryRawValue($body, 'P'), $objects);
                if ($prefix !== null && $prefix !== '') {
                    $metadata['prefix'] = $prefix;
                }

                $displayValue = $this->collectionDisplayValue($metadata['value'] ?? null, $metadata['prefix'] ?? null);
                if ($displayValue !== null && $displayValue !== '') {
                    $metadata['display_value'] = $displayValue;
                }

                return array_key_exists('value', $metadata) || array_key_exists('prefix', $metadata) ? $metadata : null;
            }
        }

        $reviewValue = $this->reviewValueFromRaw($value, $objects);
        if ($reviewValue === null || $reviewValue === '') {
            return null;
        }

        $metadata = [
            'source' => 'collection_item',
            'value' => $reviewValue,
        ];

        $displayValue = $this->collectionDisplayValue($reviewValue);
        if ($displayValue !== null && $displayValue !== '') {
            $metadata['display_value'] = $displayValue;
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $file
     * @return array<string, mixed>|null
     */
    private function collectionFileRelatedFieldValueReview(string $subtype, array $file): ?array
    {
        $source = null;
        $value = match ($subtype) {
            'F' => $file['unicode_filename'] ?? $file['filename'] ?? null,
            'Desc' => $file['description'] ?? null,
            'ModDate' => $file['modified_at'] ?? null,
            'CreationDate' => $file['created_at'] ?? null,
            'Size' => $file['declared_size'] ?? null,
            default => null,
        };

        if ($subtype === 'F' || $subtype === 'Desc') {
            $source = 'file_spec';
        } elseif ($subtype === 'ModDate' || $subtype === 'CreationDate' || $subtype === 'Size') {
            $source = 'embedded_file_params';
        }

        if ($source === null || $value === null || $value === '') {
            return null;
        }

        $metadata = [
            'source' => $source,
            'value' => $value,
        ];

        $displayValue = $this->collectionDisplayValue($value);
        if ($displayValue !== null && $displayValue !== '') {
            $metadata['display_value'] = $displayValue;
        }

        return $metadata;
    }

    private function collectionFieldValueType(string $subtype): ?string
    {
        return match ($subtype) {
            'S', 'F', 'Desc' => 'text',
            'D', 'ModDate', 'CreationDate' => 'date',
            'N', 'Size' => 'number',
            default => null,
        };
    }

    private function collectionDisplayValue(mixed $value, mixed $prefix = null): ?string
    {
        $displayValue = $this->collectionScalarDisplayValue($value);
        if ($displayValue === null) {
            return null;
        }

        $displayPrefix = $this->collectionScalarDisplayValue($prefix);
        return ($displayPrefix ?? '') . $displayValue;
    }

    private function collectionScalarDisplayValue(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return null;
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
        $body = $objectNumber !== null ? $this->objectBodyFromReferenceValue($value, $objects) : trim($value);
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
        $seenNamedStreams = [];
        $deduped = [];

        foreach ($files as $file) {
            $fileSpecObject = $file['file_spec_object'] ?? null;
            $embeddedFileObject = $file['embedded_file_object'] ?? null;
            $filenameSource = $file['filename_source'] ?? null;
            $streamKey = is_int($embeddedFileObject) ? 'stream:' . $embeddedFileObject : null;
            if ($filenameSource === 'generated' && $streamKey !== null && isset($seenNamedStreams[$streamKey])) {
                continue;
            }

            $key = is_int($fileSpecObject) && is_int($embeddedFileObject)
                ? 'objects:' . $fileSpecObject . ':' . $embeddedFileObject
                : 'values:' . ($embeddedFileObject ?? 'direct') . ':' . ($file['name'] ?? '') . ':' . ($file['filename'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            if ($streamKey !== null && is_string($filenameSource) && $filenameSource !== 'generated') {
                $seenNamedStreams[$streamKey] = true;
            }
            $deduped[] = $file;
        }

        return $deduped;
    }

    /**
     * @return array<int, string>
     */
    private function pdfObjects(string $pdfBytes): array
    {
        $definitions = $this->directObjectDefinitions($pdfBytes);
        if ($definitions === []) {
            return [];
        }

        $objects = $this->latestDirectObjects($definitions);
        $xrefEntries = $this->xrefEntriesFromStartxrefChain($pdfBytes, $objects, $definitions);
        if ($xrefEntries !== []) {
            $objects = $this->liveDirectObjects($definitions, $xrefEntries);
            $objects = $this->withObjectStreamObjects($objects, $xrefEntries);
            $objects = $this->withTrailerDirectGenerationObjects($pdfBytes, $objects, $definitions);
            $objects = $this->withReferencedDirectGenerationObjects($objects, $definitions, $xrefEntries);
        }

        ksort($objects, SORT_NUMERIC);
        return $objects;
    }

    /**
     * @return array<int, list<array{generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string}>>
     */
    private function directObjectDefinitions(string $pdfBytes): array
    {
        $objects = [];
        $offset = 0;
        while (preg_match('/(\d+)\s+(\d+)\s+obj\b/s', $pdfBytes, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $bodyStart = $match[0][1] + strlen($match[0][0]);
            $bodyEnd = $this->pdfObjectEndOffset($pdfBytes, $bodyStart);
            if ($bodyEnd === null) {
                break;
            }

            $objects[(int) $match[1][0]][] = [
                'generation' => (int) $match[2][0],
                'offset' => $match[0][1],
                'bodyStart' => $bodyStart,
                'bodyEnd' => $bodyEnd,
                'body' => substr($pdfBytes, $bodyStart, $bodyEnd - $bodyStart),
            ];
            $offset = $bodyEnd + strlen('endobj');
        }

        ksort($objects, SORT_NUMERIC);
        return $objects;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<int, string>
     */
    private function latestDirectObjects(array $definitions): array
    {
        $this->objectGenerations = [];
        $objects = [];
        foreach ($definitions as $objectNumber => $entries) {
            $selected = $this->latestDirectObjectDefinition($entries);
            if ($selected !== null) {
                $objects[$objectNumber] = $selected['body'];
                $this->objectGenerations[$objectNumber] = $selected['generation'];
            }
        }

        return $objects;
    }

    /**
     * @param list<array{generation: int, offset: int, body: string}> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function latestDirectObjectDefinition(array $definitions): ?array
    {
        if ($definitions === []) {
            return null;
        }

        usort(
            $definitions,
            static fn (array $left, array $right): int => [$left['generation'], $left['offset']] <=> [$right['generation'], $right['offset']]
        );

        $selected = end($definitions);
        return is_array($selected) ? $selected : null;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @param array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}> $xrefEntries
     * @return array<int, string>
     */
    private function liveDirectObjects(array $definitions, array $xrefEntries): array
    {
        $this->objectGenerations = [];
        $objects = [];
        foreach ($definitions as $objectNumber => $entries) {
            $selected = $this->liveDirectObjectDefinition($entries, $xrefEntries[$objectNumber] ?? null);
            if ($selected !== null) {
                $objects[$objectNumber] = $selected['body'];
                $this->objectGenerations[$objectNumber] = $selected['generation'];
            }
        }

        return $objects;
    }

    /**
     * @param list<array{generation: int, offset: int, body: string}> $definitions
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}|null $xrefEntry
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function liveDirectObjectDefinition(array $definitions, ?array $xrefEntry): ?array
    {
        if ($xrefEntry === null) {
            return $this->latestDirectObjectDefinition($definitions);
        }

        if (($xrefEntry['type'] ?? 1) === 2) {
            $objectStreamDefinition = $this->latestDirectObjectStreamDefinition($definitions);
            if ($objectStreamDefinition !== null) {
                return $objectStreamDefinition;
            }

            $xrefStreamDefinition = $this->latestDirectXrefStreamDefinition($definitions);
            if ($xrefStreamDefinition !== null) {
                return $xrefStreamDefinition;
            }

            return null;
        }

        if (($xrefEntry['type'] ?? 1) !== 1) {
            return null;
        }

        $generation = $xrefEntry['generation'] ?? null;
        $offset = $xrefEntry['offset'] ?? null;
        if ($offset !== null) {
            foreach ($definitions as $definition) {
                if ($definition['offset'] !== $offset) {
                    continue;
                }

                if ($generation !== null && $definition['generation'] !== $generation) {
                    continue;
                }

                return $definition;
            }

            if (($xrefEntry['offsetIsExplicit'] ?? true) === true) {
                return null;
            }
        }

        $candidates = [];
        foreach ($definitions as $definition) {
            if ($generation !== null && $definition['generation'] !== $generation) {
                continue;
            }
            $candidates[] = $definition;
        }

        return $this->latestDirectObjectDefinition($candidates);
    }

    /**
     * Object streams are stream objects and cannot themselves be compressed
     * members. Keep a scanned /ObjStm body available when a malformed type-2
     * row tries to hide the current object-stream base.
     *
     * @param list<array{generation: int, offset: int, body: string}> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function latestDirectObjectStreamDefinition(array $definitions): ?array
    {
        $candidates = [];
        foreach ($definitions as $definition) {
            if ($this->objectBodyHasTypeName($definition['body'], 'ObjStm')) {
                $candidates[] = $definition;
            }
        }

        return $this->latestDirectObjectDefinition($candidates);
    }

    /**
     * Xref streams are selected as direct file-level stream objects by
     * startxref. A malformed decoded type-2 row for that same object number
     * must not replace the direct xref owner with a compressed member cycle.
     *
     * @param list<array{generation: int, offset: int, body: string}> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function latestDirectXrefStreamDefinition(array $definitions): ?array
    {
        $candidates = [];
        foreach ($definitions as $definition) {
            if ($this->objectBodyHasTypeName($definition['body'], 'XRef')) {
                $candidates[] = $definition;
            }
        }

        return $this->latestDirectObjectDefinition($candidates);
    }

    /**
     * Incremental updates can name the current catalog generation from the
     * latest trailer while damaged in-use rows point at invalid offsets. Keep
     * the trailer root authoritative so attachment name trees do not fall back
     * to stale /Prev catalog objects.
     *
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<int, string>
     */
    private function withTrailerDirectGenerationObjects(string $pdfBytes, array $objects, array $definitions): array
    {
        $trailer = $this->trailerDictionaryBody($pdfBytes);
        if ($trailer === null) {
            return $objects;
        }

        $reference = $this->objectReferenceFromValue($this->dictionaryRawValue($trailer, 'Root'));
        if ($reference === null || $reference['generation'] <= 0) {
            return $objects;
        }

        $definition = $this->directObjectDefinitionForGeneration(
            $definitions[$reference['objectNumber']] ?? [],
            $reference['generation']
        );
        if ($definition === null) {
            return $objects;
        }

        $objects[$reference['objectNumber']] = $definition['body'];
        $this->objectGenerations[$reference['objectNumber']] = $definition['generation'];
        ksort($objects, SORT_NUMERIC);

        return $objects;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @param array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}> $xrefEntries
     * @return array<int, string>
     */
    private function withReferencedDirectGenerationObjects(array $objects, array $definitions, array $xrefEntries): array
    {
        $repaired = $objects;

        for ($pass = 0; $pass < 8; $pass++) {
            $added = false;
            foreach ($this->nonZeroGenerationObjectReferences($repaired) as $objectNumber => $generations) {
                $xrefEntry = $xrefEntries[$objectNumber] ?? null;
                if ($xrefEntry !== null && ($xrefEntry['type'] ?? null) !== 1) {
                    continue;
                }

                $selected = isset($repaired[$objectNumber])
                    ? $this->liveDirectObjectDefinition($definitions[$objectNumber] ?? [], $xrefEntry)
                    : null;

                krsort($generations, SORT_NUMERIC);
                foreach (array_keys($generations) as $generation) {
                    $generation = (int) $generation;
                    if ($selected !== null && $selected['generation'] === $generation) {
                        continue;
                    }

                    $definition = $this->directObjectDefinitionForGeneration($definitions[$objectNumber] ?? [], $generation);
                    if ($definition === null) {
                        continue;
                    }

                    $repaired[$objectNumber] = $definition['body'];
                    $this->objectGenerations[$objectNumber] = $definition['generation'];
                    $added = true;
                    break;
                }
            }

            if (!$added) {
                break;
            }
        }

        ksort($repaired, SORT_NUMERIC);
        return $repaired;
    }

    /**
     * Xref-stream type-2 rows select ordinary generation-zero objects from a
     * selected direct /ObjStm carrier. Full embedded-file review needs current
     * compressed catalog, name-tree, and FileSpec dictionaries while keeping
     * EmbeddedFile stream payloads direct.
     *
     * @param array<int, string> $objects
     * @param array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}> $xrefEntries
     * @return array<int, string>
     */
    private function withObjectStreamObjects(array $objects, array $xrefEntries): array
    {
        $expanded = $objects;
        foreach ($xrefEntries as $objectNumber => $entry) {
            if (($entry['type'] ?? null) !== 2 || isset($expanded[$objectNumber])) {
                continue;
            }

            $body = $this->objectStreamMemberBodyForXrefEntry($expanded, $entry, (int) $objectNumber);
            if ($body === null || $body === '' || $this->objectStreamMemberIsTopLevelStreamObject($body)) {
                continue;
            }

            $expanded[(int) $objectNumber] = $body;
            $this->objectGenerations[(int) $objectNumber] = 0;
        }

        ksort($expanded, SORT_NUMERIC);
        return $expanded;
    }

    /**
     * @param array<int, string> $objects
     * @param array{type: int, objectStream?: int, index?: int, indexIsExplicit?: bool} $xrefEntry
     */
    private function objectStreamMemberBodyForXrefEntry(array $objects, array $xrefEntry, int $requestedObjectNumber): ?string
    {
        $objectStreamNumber = $xrefEntry['objectStream'] ?? null;
        if (!is_int($objectStreamNumber) || !isset($objects[$objectStreamNumber])) {
            return null;
        }

        $objectStreamBody = $objects[$objectStreamNumber];
        if (!$this->objectBodyHasTypeName($objectStreamBody, 'ObjStm')) {
            return null;
        }

        $memberTable = $this->decodedObjectStreamMemberTable($objectStreamBody, $objects);
        if ($memberTable === null) {
            return null;
        }

        $member = $this->objectStreamSelectedMember($memberTable['members'], $xrefEntry, $requestedObjectNumber);
        if ($member === null) {
            return null;
        }

        if (!$this->objectStreamMemberOffsetHasTokenBoundary($memberTable, $member)) {
            return null;
        }

        $objectDataLength = strlen($memberTable['decoded']) - $memberTable['first'];
        $nextOffset = $this->objectStreamMemberEndOffset(
            $memberTable['members'],
            $member['offset'],
            $objectDataLength,
            $memberTable
        );
        if ($nextOffset === null) {
            return null;
        }

        return trim(substr(
            $memberTable['decoded'],
            $memberTable['first'] + $member['offset'],
            $nextOffset - $member['offset']
        ));
    }

    /**
     * @param list<array{objectNumber: int, offset: int, index: int}> $members
     * @param array{type: int, index?: int, indexIsExplicit?: bool} $xrefEntry
     * @return array{objectNumber: int, offset: int, index: int}|null
     */
    private function objectStreamSelectedMember(array $members, array $xrefEntry, int $requestedObjectNumber): ?array
    {
        $requestedIndex = $xrefEntry['index'] ?? null;
        if (is_int($requestedIndex) && ($xrefEntry['indexIsExplicit'] ?? true) === true) {
            foreach ($members as $member) {
                if ($member['index'] === $requestedIndex && $member['objectNumber'] === $requestedObjectNumber) {
                    return $member;
                }
            }

            return null;
        }

        $selected = null;
        foreach ($members as $member) {
            if ($member['objectNumber'] !== $requestedObjectNumber) {
                continue;
            }

            if ($selected !== null) {
                return null;
            }

            $selected = $member;
        }

        return $selected;
    }

    private function objectStreamMemberIsTopLevelStreamObject(string $memberBody): bool
    {
        $dictionaryOffset = $this->skipWhitespace($memberBody, 0);
        $dictionary = $this->readPdfDictionaryAt($memberBody, $dictionaryOffset);
        if ($dictionary === null) {
            return false;
        }

        $streamOffset = $this->skipWhitespace($memberBody, $dictionary['end']);
        return $this->pdfKeywordAt($memberBody, $streamOffset, 'stream');
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, array<int, true>>
     */
    private function nonZeroGenerationObjectReferences(array $objects): array
    {
        $references = [];
        foreach ($objects as $body) {
            $source = $this->dictionaryObjectBody($body) ?? $body;
            if (preg_match_all('/\b(\d+)\s+([1-9]\d*)\s+R\b/s', $source, $matches, PREG_SET_ORDER) < 1) {
                continue;
            }

            foreach ($matches as $match) {
                $references[(int) $match[1]][(int) $match[2]] = true;
            }
        }

        return $references;
    }

    /**
     * @param list<array{generation: int, offset: int, body: string}> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function directObjectDefinitionForGeneration(array $definitions, int $generation): ?array
    {
        $candidates = [];
        foreach ($definitions as $definition) {
            if ($definition['generation'] === $generation) {
                $candidates[] = $definition;
            }
        }

        return $this->latestDirectObjectDefinition($candidates);
    }

    /**
     * @param list<array{generation: int, offset: int, body: string}> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function directObjectDefinitionForGenerationBeforeOffset(array $definitions, int $generation, int $beforeOffset): ?array
    {
        $candidates = [];
        foreach ($definitions as $definition) {
            if (
                $definition['generation'] !== $generation
                || $definition['offset'] >= $beforeOffset
            ) {
                continue;
            }

            $candidates[] = $definition;
        }

        return $this->latestDirectObjectDefinition($candidates);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}>
     */
    private function xrefEntriesFromStartxrefChain(string $pdfBytes, array $objects, array $definitions): array
    {
        $offset = $this->startxrefOffsetWithClassicRebuild($pdfBytes, $definitions);
        if ($offset === null) {
            return [];
        }

        $entries = $this->xrefEntriesFromOffsetChain($pdfBytes, $offset, $objects, $definitions);
        ksort($entries, SORT_NUMERIC);

        return $entries;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @param array<int, true> $seenOffsets
     * @return array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}>
     */
    private function xrefEntriesFromOffsetChain(string $pdfBytes, int $offset, array $objects, array $definitions, array $seenOffsets = []): array
    {
        if ($offset < 0 || isset($seenOffsets[$offset])) {
            return [];
        }
        $seenOffsets[$offset] = true;

        $tableSection = $this->xrefTableSectionAt($pdfBytes, $offset, $definitions, $objects);
        if ($tableSection !== null) {
            $entries = $tableSection['entries'];
            $hybridStreamOffset = $this->dictionaryIntegerValue($tableSection['trailer'], 'XRefStm', $objects);
            if ($hybridStreamOffset !== null && $hybridStreamOffset >= 0 && !isset($seenOffsets[$hybridStreamOffset])) {
                foreach ($this->xrefStreamEntriesAtOffset($hybridStreamOffset, $objects, $definitions) as $objectNumber => $entry) {
                    $entries[$objectNumber] ??= $entry;
                }
            }

            $previousOffset = $this->previousXrefOffsetForSectionBody($pdfBytes, $tableSection['trailer'], $offset, $definitions, $objects);
            $entries = $this->repairCurrentObjectStreamCarrierRows($entries, $definitions, $previousOffset, $offset);
            $entries = $this->repairOmittedCurrentUpdateGraphRows($entries, $definitions, $tableSection['trailer'], $previousOffset, $offset);
            if ($previousOffset !== null && $previousOffset >= 0) {
                $previousEntries = $this->xrefEntriesFromOffsetChain($pdfBytes, $previousOffset, $objects, $definitions, $seenOffsets);
                foreach ($previousEntries as $objectNumber => $entry) {
                    if (
                        !isset($entries[$objectNumber])
                        && $this->previousCompressedEntryUsesUpdatedObjectStream($entry, $entries, $previousEntries, $definitions, $previousOffset, $offset)
                    ) {
                        continue;
                    }

                    $entries[$objectNumber] ??= $entry;
                }
            }

            return $entries;
        }

        $streamSection = $this->xrefStreamSectionAtOffset($offset, $definitions);
        if ($streamSection === null) {
            return [];
        }

        $entries = $this->xrefStreamEntriesFromDefinition($streamSection['definition'], $objects, $definitions);
        $previousOffset = $this->previousXrefOffsetForSectionBody($pdfBytes, $streamSection['body'], $offset, $definitions, $objects);
        $entries = $this->repairCurrentObjectStreamCarrierRows($entries, $definitions, $previousOffset, $offset);
        $entries = $this->repairOmittedCurrentUpdateGraphRows($entries, $definitions, $streamSection['body'], $previousOffset, $offset);
        if ($previousOffset !== null && $previousOffset >= 0) {
            $previousEntries = $this->xrefEntriesFromOffsetChain($pdfBytes, $previousOffset, $objects, $definitions, $seenOffsets);
            foreach ($previousEntries as $objectNumber => $entry) {
                if (
                    !isset($entries[$objectNumber])
                    && $this->previousCompressedEntryUsesUpdatedObjectStream($entry, $entries, $previousEntries, $definitions, $previousOffset, $offset)
                ) {
                    continue;
                }

                $entries[$objectNumber] ??= $entry;
            }
        }

        return $entries;
    }

    /**
     * @param array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}> $entries
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}>
     */
    private function repairOmittedCurrentUpdateGraphRows(
        array $entries,
        array $definitions,
        string $sectionBody,
        ?int $previousOffset,
        int $currentXrefOffset
    ): array {
        if ($previousOffset === null || $previousOffset < 0 || $currentXrefOffset <= $previousOffset) {
            return $entries;
        }

        $pending = [];
        foreach (['Root', 'Info'] as $name) {
            $reference = $this->objectReferenceFromValue($this->dictionaryRawValue($sectionBody, $name));
            if ($reference !== null) {
                $pending[] = $reference;
            }
        }

        $seen = [];
        while ($pending !== [] && count($seen) < 128) {
            $reference = array_shift($pending);
            $objectNumber = $reference['objectNumber'];
            $generation = $reference['generation'];
            if ($objectNumber <= 0 || $generation < 0) {
                continue;
            }

            $key = $objectNumber . ':' . $generation;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            if (isset($entries[$objectNumber])) {
                $definition = $this->currentUpdateDirectObjectDefinitionForExistingGraphEntry(
                    $objectNumber,
                    $generation,
                    $entries[$objectNumber],
                    $previousOffset,
                    $currentXrefOffset,
                    $definitions
                );
                if ($definition !== null) {
                    foreach ($this->objectReferencesInBody($definition['body']) as $nestedReference) {
                        $pending[] = $nestedReference;
                    }
                }

                continue;
            }

            $definition = $this->currentUpdateDirectObjectDefinitionForXrefRow(
                $objectNumber,
                $generation,
                $previousOffset,
                $currentXrefOffset,
                $definitions
            );
            if ($definition === null) {
                continue;
            }

            $entries[$objectNumber] = [
                'type' => 1,
                'generation' => $definition['generation'],
                'offset' => $definition['offset'],
                'offsetIsExplicit' => true,
            ];

            foreach ($this->objectReferencesInBody($definition['body']) as $nestedReference) {
                $pending[] = $nestedReference;
            }
        }

        return $entries;
    }

    /**
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool} $entry
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function currentUpdateDirectObjectDefinitionForExistingGraphEntry(
        int $objectNumber,
        int $generation,
        array $entry,
        int $previousOffset,
        int $currentXrefOffset,
        array $definitions
    ): ?array {
        if (($entry['type'] ?? null) !== 1 || ($entry['generation'] ?? null) !== $generation) {
            return null;
        }

        $offset = $entry['offset'] ?? null;
        if (!is_int($offset) || $offset <= $previousOffset || $offset >= $currentXrefOffset) {
            return null;
        }

        $definition = $this->directObjectDefinitionAtOffset($definitions, $offset);
        if (
            $definition === null
            || $definition['objectNumber'] !== $objectNumber
            || $definition['generation'] !== $generation
        ) {
            return null;
        }

        return [
            'generation' => $definition['generation'],
            'offset' => $definition['offset'],
            'body' => $definition['body'],
        ];
    }

    /**
     * @return list<array{objectNumber: int, generation: int}>
     */
    private function objectReferencesInBody(string $objectBody): array
    {
        $body = $this->dictionaryObjectBody($objectBody) ?? $objectBody;
        if (preg_match_all('/(?:^|[\s\[\]<>{}\/])(\d+)\s+(\d+)\s+R\b/s', $body, $matches, PREG_SET_ORDER) !== false) {
            $references = [];
            foreach ($matches as $match) {
                $references[] = [
                    'objectNumber' => (int) $match[1],
                    'generation' => (int) $match[2],
                ];
            }

            return $references;
        }

        return [];
    }

    /**
     * Current xref streams may update compressed FileSpec/name-tree members
     * while omitting the direct carrier row. Recover only an in-window current
     * /ObjStm carrier before stale /Prev rows are inherited.
     *
     * @param array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}> $entries
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}>
     */
    private function repairCurrentObjectStreamCarrierRows(
        array $entries,
        array $definitions,
        ?int $previousOffset,
        int $currentXrefOffset
    ): array {
        $carrierObjectNumbers = [];
        foreach ($entries as $entry) {
            if (($entry['type'] ?? null) === 2 && isset($entry['objectStream'])) {
                $carrierObjectNumbers[(int) $entry['objectStream']] = true;
            }
        }

        foreach (array_keys($carrierObjectNumbers) as $objectNumber) {
            $entry = $entries[$objectNumber] ?? null;
            if ($entry === null) {
                $definition = $this->latestDirectObjectStreamDefinitionBetweenOffsets(
                    $definitions[$objectNumber] ?? [],
                    $previousOffset ?? -1,
                    $currentXrefOffset
                );
                if ($definition === null) {
                    continue;
                }

                $entries[$objectNumber] = [
                    'type' => 1,
                    'generation' => $definition['generation'],
                    'offset' => $definition['offset'],
                    'offsetIsExplicit' => true,
                ];
                continue;
            }

            if (($entry['type'] ?? null) !== 1) {
                continue;
            }

            if ($this->liveDirectObjectDefinition($definitions[$objectNumber] ?? [], $entry) !== null) {
                continue;
            }

            $definition = $this->latestDirectObjectStreamDefinitionBetweenOffsets(
                $definitions[$objectNumber] ?? [],
                $previousOffset ?? -1,
                $currentXrefOffset
            );
            if ($definition === null) {
                continue;
            }

            $entries[$objectNumber] = [
                'type' => 1,
                'generation' => $definition['generation'],
                'offset' => $definition['offset'],
                'offsetIsExplicit' => true,
            ];
        }

        return $entries;
    }

    /**
     * @param list<array{generation: int, offset: int, body: string}> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function latestDirectObjectStreamDefinitionBetweenOffsets(array $definitions, int $afterOffset, int $beforeOffset): ?array
    {
        $candidates = [];
        foreach ($definitions as $definition) {
            if (
                $definition['offset'] > $afterOffset
                && $definition['offset'] < $beforeOffset
                && $this->objectBodyHasTypeName($definition['body'], 'ObjStm')
            ) {
                $candidates[] = $definition;
            }
        }

        return $this->latestDirectObjectDefinition($candidates);
    }

    /**
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool} $entry
     * @param array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}> $currentEntries
     * @param array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}> $previousEntries
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     */
    private function previousCompressedEntryUsesUpdatedObjectStream(
        array $entry,
        array $currentEntries,
        array $previousEntries,
        array $definitions,
        int $previousXrefOffset,
        int $currentXrefOffset
    ): bool
    {
        if (($entry['type'] ?? null) !== 2 || !isset($entry['objectStream'])) {
            return false;
        }

        $objectStreamNumber = (int) $entry['objectStream'];
        $previousObjectStreamEntry = $previousEntries[$objectStreamNumber] ?? null;
        if (($previousObjectStreamEntry['type'] ?? null) !== 1) {
            return true;
        }

        $currentObjectStreamEntry = $currentEntries[$objectStreamNumber] ?? null;
        if ($currentObjectStreamEntry === null) {
            $previousDefinition = $this->xrefEntrySelectedDirectDefinition($objectStreamNumber, $previousObjectStreamEntry, $definitions);
            if ($previousDefinition === null) {
                return true;
            }

            return $this->latestDirectObjectStreamDefinitionBetweenOffsets(
                $definitions[$objectStreamNumber] ?? [],
                max($previousXrefOffset, $previousDefinition['offset']),
                $currentXrefOffset
            ) !== null;
        }

        if ($this->currentCarrierEntryCanRecoverPreviousObjectStreamStorage(
            $objectStreamNumber,
            $currentObjectStreamEntry,
            $previousObjectStreamEntry,
            $previousEntries,
            $definitions
        )) {
            return false;
        }

        return !$this->xrefEntriesSelectSameStorage($currentObjectStreamEntry, $previousObjectStreamEntry);
    }

    /**
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool} $currentEntry
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool} $previousEntry
     * @param array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}> $previousEntries
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     */
    private function currentCarrierEntryCanRecoverPreviousObjectStreamStorage(
        int $objectNumber,
        array $currentEntry,
        array $previousEntry,
        array $previousEntries,
        array $definitions
    ): bool {
        if (($currentEntry['type'] ?? null) !== 1 || ($previousEntry['type'] ?? null) !== 1) {
            return false;
        }

        if (!$this->xrefEntriesContainType2CarrierReference($previousEntries, $objectNumber)) {
            return false;
        }

        $currentDefinition = $this->xrefEntrySelectedDirectDefinition($objectNumber, $currentEntry, $definitions);
        if ($currentDefinition !== null) {
            return false;
        }

        $previousDefinition = $this->xrefEntrySelectedDirectDefinition($objectNumber, $previousEntry, $definitions);
        return $previousDefinition !== null && $this->objectBodyHasTypeName($previousDefinition['body'], 'ObjStm');
    }

    /**
     * @param array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}> $entries
     */
    private function xrefEntriesContainType2CarrierReference(array $entries, int $objectStreamNumber): bool
    {
        foreach ($entries as $entry) {
            if (($entry['type'] ?? null) === 2 && ($entry['objectStream'] ?? null) === $objectStreamNumber) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool} $entry
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function xrefEntrySelectedDirectDefinition(int $objectNumber, array $entry, array $definitions): ?array
    {
        if (($entry['type'] ?? null) !== 1) {
            return null;
        }

        return $this->liveDirectObjectDefinition($definitions[$objectNumber] ?? [], $entry);
    }

    /**
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool} $left
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool} $right
     */
    private function xrefEntriesSelectSameStorage(array $left, array $right): bool
    {
        if (
            ($left['type'] ?? null) === 1
            && ($right['type'] ?? null) === 1
            && ($left['offsetIsExplicit'] ?? true) === true
            && ($right['offsetIsExplicit'] ?? true) === true
            && isset($left['offset'], $right['offset'])
            && $left['offset'] === $right['offset']
        ) {
            return true;
        }

        foreach (['type', 'generation', 'offset', 'objectStream', 'index'] as $field) {
            if (($left[$field] ?? null) !== ($right[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, bodyStart?: int, bodyEnd?: int, body: string}>> $definitions
     */
    private function previousXrefOffsetForSectionBody(
        string $pdfBytes,
        string $sectionBody,
        int $currentOffset,
        array $definitions,
        array $objects = []
    ): ?int {
        $previousOffset = $this->dictionaryIntegerValue($sectionBody, 'Prev', $objects);
        if ($previousOffset === null && $objects !== []) {
            $helperObjects = $this->objectsWithCompressedXrefPrevOperandHelpers(
                $sectionBody,
                $objects,
                $definitions,
                $currentOffset
            );
            if ($helperObjects !== $objects) {
                $previousOffset = $this->dictionaryIntegerValue($sectionBody, 'Prev', $helperObjects);
            }
        }

        if ($previousOffset === null || $previousOffset < 0) {
            return $previousOffset;
        }

        if ($previousOffset >= $currentOffset) {
            return $this->latestXrefSectionOffsetBefore($pdfBytes, $currentOffset, $definitions);
        }

        if ($this->xrefSectionExistsAtOffset($pdfBytes, $previousOffset, $definitions)) {
            return $previousOffset;
        }

        return $this->latestXrefSectionOffsetBefore($pdfBytes, $currentOffset, $definitions);
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, bodyStart?: int, bodyEnd?: int, body: string}>> $definitions
     */
    private function xrefSectionExistsAtOffset(string $pdfBytes, int $offset, array $definitions): bool
    {
        return $this->xrefTableSectionAt($pdfBytes, $offset, $definitions, [], false) !== null
            || $this->xrefStreamSectionAtOffset($offset, $definitions) !== null;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, bodyStart?: int, bodyEnd?: int, body: string}>> $definitions
     */
    private function latestXrefSectionOffsetBefore(string $pdfBytes, int $currentOffset, array $definitions): ?int
    {
        $offsets = $this->xrefTableKeywordOffsets($pdfBytes, $definitions);
        foreach ($definitions as $entries) {
            foreach ($entries as $definition) {
                if (preg_match('/\/Type\s*\/XRef\b/s', $definition['body']) === 1) {
                    $offsets[] = (int) $definition['offset'];
                }
            }
        }
        rsort($offsets, SORT_NUMERIC);

        foreach ($offsets as $offset) {
            if ($offset >= $currentOffset) {
                continue;
            }

            if ($this->xrefSectionExistsAtOffset($pdfBytes, $offset, $definitions)) {
                return $offset;
            }
        }

        return null;
    }

    /**
     * @param array<int, list<array{bodyStart?: int, bodyEnd?: int}>>|null $definitions
     * @return array{entries: array<int, array{type: int, generation: int, offset: int, offsetIsExplicit: bool}>, trailer: string}|null
     */
    private function xrefTableSectionAt(
        string $pdfBytes,
        int $offset,
        ?array $definitions = null,
        array $objects = [],
        bool $repairCurrentRows = true
    ): ?array
    {
        if ($definitions !== null && $this->offsetOwnedByDirectObjectBody($offset, $definitions)) {
            return null;
        }

        $offset = $this->skipWhitespace($pdfBytes, $offset);
        if (
            $this->tokenStartsInPdfCommentLine($pdfBytes, $offset)
            || $this->tokenStartsInsidePdfCompositeToken($pdfBytes, $offset, $definitions)
        ) {
            return null;
        }

        if (!$this->pdfKeywordAt($pdfBytes, $offset, 'xref')) {
            return null;
        }
        $afterKeywordOffset = $offset + 4;
        if ($afterKeywordOffset >= strlen($pdfBytes)) {
            return null;
        }

        $afterKeyword = $pdfBytes[$afterKeywordOffset];
        if ($afterKeyword !== '%' && !$this->isPdfWhitespace($afterKeyword)) {
            return null;
        }

        $sectionBodyOffset = $afterKeywordOffset;
        $trailerOffset = $this->xrefTableTrailerKeywordOffset($pdfBytes, $sectionBodyOffset, $definitions);
        if ($trailerOffset === null) {
            return null;
        }

        $dictionaryOffset = strpos($pdfBytes, '<<', $trailerOffset);
        if ($dictionaryOffset === false) {
            return null;
        }

        $trailer = $this->readPdfDictionaryAt($pdfBytes, $dictionaryOffset);
        if ($trailer === null) {
            return null;
        }

        $trailerBody = $trailer['body'];
        $entries = $this->xrefTableRows(substr($pdfBytes, $sectionBodyOffset, $trailerOffset - $sectionBodyOffset));
        if ($entries === null) {
            return null;
        }

        if ($definitions !== null && $repairCurrentRows) {
            $entries = $this->repairClassicXrefGenerationOffsetRows($entries, $definitions, $offset);
            $entries = $this->repairCurrentUpdateXrefTableRows($pdfBytes, $entries, $definitions, $trailerBody, $offset, $objects);
        }

        return [
            'entries' => $entries,
            'trailer' => $trailerBody,
        ];
    }

    /**
     * @param array<int, list<array{bodyStart?: int, bodyEnd?: int}>> $definitions
     */
    private function offsetOwnedByDirectObjectBody(int $offset, array $definitions): bool
    {
        foreach ($definitions as $entries) {
            foreach ($entries as $definition) {
                $bodyStart = $definition['bodyStart'] ?? null;
                $bodyEnd = $definition['bodyEnd'] ?? null;
                if (is_int($bodyStart) && is_int($bodyEnd) && $offset >= $bodyStart && $offset <= $bodyEnd) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<int, array{type: int, generation: int, offset: int, offsetIsExplicit: bool}>|null
     */
    private function xrefTableRows(string $sectionBody): ?array
    {
        $sectionBody = str_replace(["\0", "\f"], ' ', $sectionBody);
        $entries = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($sectionBody));
        if ($lines === false) {
            return null;
        }

        $foundSection = false;
        for ($lineIndex = 0, $lineCount = count($lines); $lineIndex < $lineCount; $lineIndex++) {
            $line = trim($lines[$lineIndex]);
            if ($line === '' || str_starts_with($line, '%')) {
                continue;
            }

            if (preg_match('/^(\d+)\s+(\d+)(?:\s*(?:%.*)?)$/', $line, $header) !== 1) {
                if (!$foundSection) {
                    return null;
                }

                continue;
            }

            $foundSection = true;
            $startObject = (int) $header[1];
            $count = max(0, (int) $header[2]);
            if ($count === 0) {
                return $entries === [] ? null : $entries;
            }

            $entriesBeforeSection = $entries;
            for ($entryIndex = 0; $entryIndex < $count;) {
                if (++$lineIndex >= $lineCount) {
                    return $entries === [] ? null : $entries;
                }

                $row = trim($lines[$lineIndex]);
                if ($row === '' || str_starts_with($row, '%')) {
                    continue;
                }

                if (preg_match('/^(\d{10})\s+(\d{5})\s+([nf])(?:\s*(?:%.*)?)$/', $row, $rowMatch) !== 1) {
                    if ($entriesBeforeSection !== []) {
                        return $entriesBeforeSection;
                    }

                    return null;
                }

                $entries[$startObject + $entryIndex] = [
                    'type' => $rowMatch[3] === 'n' ? 1 : 0,
                    'generation' => (int) $rowMatch[2],
                    'offset' => (int) $rowMatch[1],
                    'offsetIsExplicit' => true,
                ];
                $entryIndex++;
            }
        }

        return $foundSection ? $entries : null;
    }

    /**
     * @param array<int, array{type: int, generation: int, offset: int, offsetIsExplicit: bool}> $entries
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<int, array{type: int, generation: int, offset: int, offsetIsExplicit: bool}>
     */
    private function repairClassicXrefGenerationOffsetRows(array $entries, array $definitions, int $xrefOffset): array
    {
        foreach ($entries as $objectNumber => $entry) {
            if (($entry['type'] ?? null) !== 1) {
                continue;
            }

            $offset = $entry['offset'] ?? null;
            if (!is_int($offset)) {
                continue;
            }

            $entryGeneration = (int) ($entry['generation'] ?? 0);
            $offsetOwner = $this->directObjectDefinitionAtOffset($definitions, $offset);
            if (
                $offsetOwner !== null
                && $offsetOwner['offset'] < $xrefOffset
                && $offsetOwner['objectNumber'] === (int) $objectNumber
                && $offsetOwner['generation'] === $entryGeneration
            ) {
                continue;
            }

            $definition = $this->directObjectDefinitionForGenerationBeforeOffset(
                $definitions[$objectNumber] ?? [],
                $entryGeneration,
                $xrefOffset
            );
            if ($definition === null) {
                continue;
            }

            $entries[$objectNumber]['offset'] = $definition['offset'];
            $entries[$objectNumber]['generation'] = $definition['generation'];
        }

        return $entries;
    }

    /**
     * @param array<int, array{type: int, generation: int, offset: int, offsetIsExplicit: bool}> $entries
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<int, array{type: int, generation: int, offset: int, offsetIsExplicit: bool}>
     */
    private function repairCurrentUpdateXrefTableRows(
        string $pdfBytes,
        array $entries,
        array $definitions,
        string $trailer,
        int $xrefOffset,
        array $objects = []
    ): array
    {
        $previousOffset = $this->previousXrefOffsetForSectionBody($pdfBytes, $trailer, $xrefOffset, $definitions, $objects);

        if ($previousOffset === null || $previousOffset < 0) {
            return $entries;
        }

        foreach ($entries as $objectNumber => $entry) {
            if (($entry['type'] ?? null) !== 1) {
                continue;
            }

            $offset = $entry['offset'] ?? null;
            $offsetOwner = is_int($offset) ? $this->directObjectDefinitionAtOffset($definitions, $offset) : null;
            $updateOwner = $this->currentUpdateDirectObjectDefinitionForStaleXrefOffset(
                (int) $objectNumber,
                (int) ($entry['generation'] ?? 0),
                $offsetOwner,
                $previousOffset,
                $xrefOffset,
                $definitions
            );
            if ($offsetOwner !== null && $updateOwner === null) {
                continue;
            }

            if ($updateOwner === null) {
                continue;
            }

            $entries[$objectNumber]['offset'] = $updateOwner['offset'];
            $entries[$objectNumber]['generation'] = $updateOwner['generation'];
        }

        return $entries;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}>
     */
    private function xrefStreamEntriesAtOffset(int $offset, array $objects, array $definitions): array
    {
        $section = $this->xrefStreamSectionAtOffset($offset, $definitions);
        return $section === null ? [] : $this->xrefStreamEntriesFromDefinition($section['definition'], $objects, $definitions);
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array{definition: array{generation: int, offset: int, body: string}, body: string}|null
     */
    private function xrefStreamSectionAtOffset(int $offset, array $definitions): ?array
    {
        foreach ($definitions as $entries) {
            foreach ($entries as $definition) {
                if ($definition['offset'] === $offset && preg_match('/\/Type\s*\/XRef\b/s', $definition['body']) === 1) {
                    return [
                        'definition' => $definition,
                        'body' => $definition['body'],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param array{generation: int, offset: int, body: string} $definition
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>>|null $definitions
     * @return array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}>
     */
    private function xrefStreamEntriesFromDefinition(array $definition, array $objects, ?array $definitions = null): array
    {
        $entries = [];
        $body = $definition['body'];
        $operandObjects = $definitions === null
            ? $objects
            : $this->objectsWithCompressedXrefPrevOperandHelpers($body, $objects, $definitions, (int) $definition['offset']);
        $widthValue = $this->resolvedDictionaryRawValue($body, 'W', $operandObjects);
        $widthBody = $widthValue === null ? null : $this->arrayBody($widthValue);
        if ($widthBody === null) {
            return $entries;
        }

        $widths = array_slice($this->integersFromPdfArray($widthBody), 0, 3);
        if (count($widths) < 3) {
            return $entries;
        }

        $entryWidth = array_sum($widths);
        if ($entryWidth <= 0) {
            return $entries;
        }

        $stream = $this->decodeStreamObject($body, $operandObjects);
        if ($stream === null) {
            return $entries;
        }

        $decoded = $stream['content'];
        $decodedEntryCount = strlen($decoded) % $entryWidth === 0 ? intdiv(strlen($decoded), $entryWidth) : null;
        $previousOffset = $definitions === null ? null : $this->dictionaryIntegerValue($body, 'Prev', $operandObjects);
        $xrefOffset = (int) $definition['offset'];
        $offset = 0;
        foreach ($this->xrefIndexRanges($body, $decodedEntryCount, $operandObjects) as $range) {
            [$startObject, $count] = $range;
            for ($index = 0; $index < $count; $index++) {
                if ($offset + $entryWidth > strlen($decoded)) {
                    break 2;
                }

                $fieldOffset = $offset;
                $type = $widths[0] === 0 ? 1 : $this->xrefFieldValue($decoded, $fieldOffset, $widths[0]);
                $fieldTwo = $this->xrefFieldValue($decoded, $fieldOffset, $widths[1]);
                $fieldThree = $this->xrefFieldValue($decoded, $fieldOffset, $widths[2]);
                $objectNumber = $startObject + $index;
                $generation = $fieldThree;
                if ($type === 1 && $widths[1] > 0 && $definitions !== null) {
                    $rowObjectNumber = $objectNumber;
                    $rowGeneration = $generation;
                    $offsetOwner = $this->directObjectDefinitionAtOffset($definitions, $fieldTwo);
                    $updateOwner = $previousOffset !== null && $previousOffset >= 0
                        ? $this->currentUpdateDirectObjectDefinitionForStaleXrefOffset(
                            $rowObjectNumber,
                            $rowGeneration,
                            $offsetOwner,
                            $previousOffset,
                            $xrefOffset,
                            $definitions
                        )
                        : null;
                    if ($updateOwner !== null) {
                        $objectNumber = $rowObjectNumber;
                        $fieldTwo = $updateOwner['offset'];
                        $generation = $updateOwner['generation'];
                    } elseif (
                        $offsetOwner !== null
                        && $previousOffset !== null
                        && $previousOffset >= 0
                        && $offsetOwner['offset'] > $previousOffset
                        && $offsetOwner['offset'] < $xrefOffset
                    ) {
                        $objectNumber = $offsetOwner['objectNumber'];
                        $generation = $offsetOwner['generation'];
                    }
                }

                if ($type === 0) {
                    $entries[$objectNumber] = [
                        'type' => 0,
                        'generation' => $generation,
                        'offset' => $fieldTwo,
                        'offsetIsExplicit' => $widths[1] > 0,
                    ];
                } elseif ($type === 1) {
                    $entries[$objectNumber] = [
                        'type' => 1,
                        'offset' => $fieldTwo,
                        'generation' => $generation,
                        'offsetIsExplicit' => $widths[1] > 0,
                    ];
                } elseif ($type === 2 && $fieldTwo > 0) {
                    $entries[$objectNumber] = [
                        'type' => 2,
                        'objectStream' => $fieldTwo,
                        'index' => $fieldThree,
                        'indexIsExplicit' => $widths[2] > 0,
                    ];
                } elseif ($type > 2 && $objectNumber > 0) {
                    $entries[$objectNumber] = [
                        'type' => 0,
                        'generation' => $fieldThree,
                        'offset' => $fieldTwo,
                        'offsetIsExplicit' => $widths[1] > 0,
                    ];
                }

                $offset += $entryWidth;
            }
        }

        return $entries;
    }

    /**
     * Resolve only safe direct or compressed scalar helpers referenced by
     * xref-stream /Prev before decoding current rows for attachments.
     *
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<int, string>
     */
    private function objectsWithCompressedXrefPrevOperandHelpers(
        string $xrefBody,
        array $objects,
        array $definitions,
        int $beforeOffset
    ): array {
        $reference = $this->objectReferenceFromValue($this->dictionaryRawValue($xrefBody, 'Prev'));
        if ($reference === null) {
            return $objects;
        }

        $definition = $this->directObjectDefinitionForGenerationBeforeOffset(
            $definitions[$reference['objectNumber']] ?? [],
            $reference['generation'],
            $beforeOffset
        );
        $helper = $reference['generation'] === 0
            ? $this->compressedObjectStreamHelperBodyBeforeOffset(
                $definitions,
                $objects,
                $reference['objectNumber'],
                $beforeOffset
            )
            : null;

        $directBody = $definition === null ? null : trim($definition['body']);
        $helperBody = $helper === null ? null : trim($helper['body']);
        if (
            $helperBody !== null
            && $this->safeXrefOperandHelperBody($helperBody)
            && ($definition === null || $helper['carrierOffset'] > $definition['offset'])
        ) {
            $objects[$reference['objectNumber']] = $helperBody;
            $this->objectGenerations[$reference['objectNumber']] = $reference['generation'];
            return $objects;
        }

        if ($directBody !== null && $this->safeXrefOperandHelperBody($directBody)) {
            $objects[$reference['objectNumber']] = $directBody;
            $this->objectGenerations[$reference['objectNumber']] = $reference['generation'];
            return $objects;
        }

        if ($helperBody === null || !$this->safeXrefOperandHelperBody($helperBody)) {
            return $objects;
        }

        $objects[$reference['objectNumber']] = $helperBody;
        $this->objectGenerations[$reference['objectNumber']] = $reference['generation'];
        return $objects;
    }

    private function objectBodyHasTypeName(string $body, string $name): bool
    {
        $dictionary = $this->readPdfDictionaryAt($body, $this->skipWhitespace($body, 0));
        if ($dictionary === null) {
            return false;
        }

        return preg_match('/\/Type\s*\/' . preg_quote($name, '/') . '(?=$|[\s\[\]()<>{}\/%])/s', $dictionary['body']) === 1;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @param array<int, string> $objects
     * @return array{body: string, carrierOffset: int}|null
     */
    private function compressedObjectStreamHelperBodyBeforeOffset(
        array $definitions,
        array $objects,
        int $objectNumber,
        int $beforeOffset
    ): ?array {
        if ($objectNumber <= 0) {
            return null;
        }

        $selected = null;
        foreach ($definitions as $entries) {
            foreach ($entries as $definition) {
                if ($definition['offset'] >= $beforeOffset || !$this->objectBodyHasTypeName($definition['body'], 'ObjStm')) {
                    continue;
                }

                $memberTable = $this->decodedObjectStreamMemberTable($definition['body'], $objects);
                if ($memberTable === null) {
                    continue;
                }

                $objectDataLength = strlen($memberTable['decoded']) - $memberTable['first'];
                foreach ($memberTable['members'] as $member) {
                    if ($member['objectNumber'] !== $objectNumber) {
                        continue;
                    }

                    $nextOffset = $this->objectStreamMemberEndOffset(
                        $memberTable['members'],
                        $member['offset'],
                        $objectDataLength,
                        $memberTable
                    );
                    if ($nextOffset === null) {
                        continue;
                    }

                    if ($selected === null || $definition['offset'] > $selected['carrierOffset']) {
                        $selected = [
                            'body' => trim(substr(
                                $memberTable['decoded'],
                                $memberTable['first'] + $member['offset'],
                                $nextOffset - $member['offset']
                            )),
                            'carrierOffset' => $definition['offset'],
                        ];
                    }
                }
            }
        }

        return $selected;
    }

    /**
     * @param array<int, string> $objects
     * @return array{decoded: string, first: int, members: list<array{objectNumber: int, offset: int, index: int}>}|null
     */
    private function decodedObjectStreamMemberTable(string $body, array $objects): ?array
    {
        $stream = $this->decodeStreamObject($body, $objects);
        if ($stream === null) {
            return null;
        }

        $decoded = $stream['content'];
        $count = $this->dictionaryIntegerValue($body, 'N', $objects);
        $first = $this->dictionaryIntegerValue($body, 'First', $objects);
        if ($count === null || $first === null || $count <= 0 || $first < 0 || $first >= strlen($decoded)) {
            return null;
        }

        $members = $this->objectStreamHeaderMembers(substr($decoded, 0, $first), $count);
        if ($members === []) {
            return null;
        }

        return [
            'decoded' => $decoded,
            'first' => $first,
            'members' => $members,
        ];
    }

    /**
     * @return list<array{objectNumber: int, offset: int, index: int}>
     */
    private function objectStreamHeaderMembers(string $header, int $count): array
    {
        $members = [];
        $offset = 0;
        for ($index = 0; $index < $count; $index++) {
            $objectNumber = $this->readUnsignedIntegerToken($header, $offset);
            $objectOffset = $this->readUnsignedIntegerToken($header, $offset);
            if ($objectNumber === null || $objectOffset === null) {
                return [];
            }

            if ($objectNumber > 0) {
                $members[] = [
                    'objectNumber' => $objectNumber,
                    'offset' => $objectOffset,
                    'index' => $index,
                ];
            }
        }

        if ($this->skipWhitespace($header, $offset) !== strlen($header)) {
            return [];
        }

        return $members;
    }

    /**
     * @param list<array{objectNumber: int, offset: int, index: int}> $members
     */
    private function objectStreamMemberEndOffset(
        array $members,
        int $memberOffset,
        int $objectDataLength,
        ?array $memberTable = null
    ): ?int
    {
        if ($memberOffset < 0 || $memberOffset >= $objectDataLength) {
            return null;
        }

        $endOffset = $objectDataLength;
        foreach ($members as $member) {
            if ($member['offset'] > $memberOffset && $member['offset'] < $endOffset) {
                if ($memberTable !== null && !$this->objectStreamMemberOffsetHasTokenBoundary($memberTable, $member)) {
                    continue;
                }
                $endOffset = $member['offset'];
            }
        }

        return $endOffset > $memberOffset ? $endOffset : null;
    }

    /**
     * @param array{decoded: string, first: int, members: list<array{objectNumber: int, offset: int, index: int}>} $memberTable
     * @param array{objectNumber: int, offset: int, index: int} $member
     */
    private function objectStreamMemberOffsetHasTokenBoundary(array $memberTable, array $member): bool
    {
        $decoded = $memberTable['decoded'];
        $absoluteOffset = $memberTable['first'] + $member['offset'];
        if ($member['offset'] < 0 || $absoluteOffset < $memberTable['first'] || $absoluteOffset >= strlen($decoded)) {
            return false;
        }

        if ($absoluteOffset === $memberTable['first']) {
            return true;
        }

        if ($decoded[$absoluteOffset] === '%') {
            return false;
        }

        $index = $memberTable['first'];
        $length = strlen($decoded);
        while ($index < $absoluteOffset && $index < $length) {
            $char = $decoded[$index];
            if ($char === '(') {
                $literal = $this->readLiteralStringAt($decoded, $index);
                if ($literal === null || $absoluteOffset < $literal['end']) {
                    return false;
                }

                $index = $literal['end'];
                continue;
            }

            if ($char === '<') {
                if (($decoded[$index + 1] ?? '') === '<') {
                    $dictionary = $this->readPdfDictionaryAt($decoded, $index);
                    if ($dictionary === null || $absoluteOffset < $dictionary['end']) {
                        return false;
                    }

                    $index = $dictionary['end'];
                    continue;
                }

                $hexEnd = $this->skipHexString($decoded, $index);
                if ($hexEnd === null || $absoluteOffset < $hexEnd) {
                    return false;
                }

                $index = $hexEnd;
                continue;
            }

            if ($char === '[') {
                $array = $this->readPdfArrayAt($decoded, $index);
                if ($array === null || $absoluteOffset < $array['end']) {
                    return false;
                }

                $index = $array['end'];
                continue;
            }

            if ($char === '%') {
                $commentEnd = $this->pdfCommentEndOffset($decoded, $index);
                if ($absoluteOffset < $commentEnd) {
                    return false;
                }

                $index = $commentEnd;
                continue;
            }

            $index++;
        }

        if ($index !== $absoluteOffset) {
            return false;
        }

        return $this->isDelimiter($decoded[$absoluteOffset - 1]);
    }

    private function isDelimiter(string $char): bool
    {
        return ctype_space($char) || str_contains('[]()<>{}/%', $char);
    }

    private function isPdfWhitespace(string $char): bool
    {
        return $char === "\0" || ctype_space($char);
    }

    private function readUnsignedIntegerToken(string $value, int &$offset): ?int
    {
        $offset = $this->skipWhitespace($value, $offset);
        if (preg_match('/\G\+?(\d+)(?=$|[\s\[\]()<>{}\/%])/s', $value, $match, 0, $offset) !== 1) {
            return null;
        }

        $offset += strlen($match[0]);
        return (int) $match[1];
    }

    private function safeXrefOperandHelperBody(string $body): bool
    {
        if ($body === '' || preg_match('/\b(?:obj|endobj|stream|endstream|xref|trailer|startxref)\b/s', $body) === 1) {
            return false;
        }

        $offset = $this->skipWhitespace($body, 0);
        return preg_match('/\G[+-]?\d+\s*\z/s', $body, $match, 0, $offset) === 1;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array{objectNumber: int, generation: int, offset: int, body: string}|null
     */
    private function directObjectDefinitionAtOffset(array $definitions, int $offset): ?array
    {
        foreach ($definitions as $objectNumber => $entries) {
            foreach ($entries as $definition) {
                if ($definition['offset'] === $offset) {
                    return [
                        'objectNumber' => $objectNumber,
                        'generation' => $definition['generation'],
                        'offset' => $definition['offset'],
                        'body' => $definition['body'],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param array{objectNumber: int, generation: int, offset: int, body: string}|null $offsetOwner
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function currentUpdateDirectObjectDefinitionForStaleXrefOffset(
        int $objectNumber,
        int $generation,
        ?array $offsetOwner,
        int $previousOffset,
        int $xrefOffset,
        array $definitions
    ): ?array {
        if (
            $offsetOwner !== null
            && $offsetOwner['offset'] > $previousOffset
            && $offsetOwner['offset'] < $xrefOffset
        ) {
            if (
                $offsetOwner['objectNumber'] === $objectNumber
                && $offsetOwner['generation'] === $generation
            ) {
                return null;
            }

            return $this->currentUpdateDirectObjectDefinitionForXrefRow(
                $objectNumber,
                $generation,
                $previousOffset,
                $xrefOffset,
                $definitions
            );
        }

        return $this->currentUpdateDirectObjectDefinitionForXrefRow(
            $objectNumber,
            $generation,
            $previousOffset,
            $xrefOffset,
            $definitions
        );
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function currentUpdateDirectObjectDefinitionForXrefRow(
        int $objectNumber,
        int $generation,
        int $previousOffset,
        int $xrefOffset,
        array $definitions
    ): ?array {
        if ($objectNumber <= 0 || $previousOffset < 0 || $xrefOffset <= $previousOffset) {
            return null;
        }

        $candidates = [];
        foreach ($definitions[$objectNumber] ?? [] as $definition) {
            if (
                $definition['generation'] !== $generation
                || $definition['offset'] <= $previousOffset
                || $definition['offset'] >= $xrefOffset
            ) {
                continue;
            }

            $candidates[] = $definition;
        }

        return $this->latestDirectObjectDefinition($candidates);
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private function xrefIndexRanges(string $xrefBody, ?int $decodedEntryCount, array $objects = []): array
    {
        $indexValue = $this->resolvedDictionaryRawValue($xrefBody, 'Index', $objects === [] ? null : $objects);
        $indexBody = $indexValue === null ? null : $this->arrayBody($indexValue);
        if ($indexBody !== null) {
            $values = $this->integersFromPdfArray($indexBody);
            $ranges = [];
            for ($index = 0, $count = count($values); $index + 1 < $count; $index += 2) {
                $ranges[] = [max(0, $values[$index]), max(0, $values[$index + 1])];
            }

            return $ranges;
        }

        $size = $this->dictionaryIntegerValue($xrefBody, 'Size', $objects === [] ? null : $objects);
        if ($size !== null) {
            $size = max(0, $size);
            if ($decodedEntryCount !== null && $decodedEntryCount > $size) {
                $size = $decodedEntryCount;
            }

            return [[0, $size]];
        }

        return [];
    }

    /**
     * @return list<int>
     */
    private function integersFromPdfArray(string $arrayBody): array
    {
        if (!preg_match_all('/-?\d+/', $arrayBody, $matches)) {
            return [];
        }

        return array_map('intval', $matches[0]);
    }

    private function xrefFieldValue(string $bytes, int &$offset, int $width): int
    {
        $value = 0;
        for ($index = 0; $index < $width; $index++) {
            $value = ($value << 8) + ord($bytes[$offset + $index]);
        }
        $offset += $width;

        return $value;
    }

    private function arrayBody(string $value): ?string
    {
        $array = $this->readPdfArrayAt(trim($value), 0);
        return $array === null ? null : substr($array['raw'], 1, -1);
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
            if ($before === '/' || (!$this->isPdfWhitespace($before) && !str_contains('[]()<>{}%', $before))) {
                return false;
            }
        }

        $afterOffset = $offset + strlen($keyword);
        if ($afterOffset >= strlen($value)) {
            return true;
        }

        $after = $value[$afterOffset];
        return $this->isPdfWhitespace($after) || str_contains('[]()<>{}/%', $after);
    }

    /**
     * @param array<int, string> $objects
     */
    private function catalogObjectBody(string $pdfBytes, array $objects): ?string
    {
        $definitions = $this->directObjectDefinitions($pdfBytes);
        if ($definitions !== []) {
            $rootResolution = $this->trailerRootReferenceResolution($pdfBytes, $definitions, $objects);
            if ($rootResolution['present']) {
                $reference = $rootResolution['reference'];
                if ($reference === null) {
                    return null;
                }

                $objectNumber = $reference['objectNumber'];
                if (isset($objects[$objectNumber])) {
                    return $this->dictionaryObjectBody($objects[$objectNumber]);
                }

                return null;
            }
        }

        $trailer = $this->trailerDictionaryBody($pdfBytes);
        if ($trailer !== null) {
            $rootValue = $this->dictionaryRawValue($trailer, 'Root');
            if ($rootValue !== null) {
                $rootReference = $this->objectReferenceFromValue($rootValue);
                if ($rootReference === null) {
                    return null;
                }

                $objectNumber = $rootReference['objectNumber'];
                $body = $this->objectBodyFromReferenceValue($rootValue, $objects);
                if ($body !== null) {
                    return $this->dictionaryObjectBody($body);
                }

                return null;
            }
        }

        foreach ($objects as $body) {
            if (preg_match('/\/Type\s*\/Catalog\b/', $body) === 1) {
                return $this->dictionaryObjectBody($body);
            }
        }

        return null;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @param array<int, string> $objects
     * @return array{present: bool, reference: array{objectNumber: int, generation: int}|null}
     */
    private function trailerRootReferenceResolution(string $pdfBytes, array $definitions, array $objects): array
    {
        $offset = $this->startxrefOffsetWithClassicRebuild($pdfBytes, $definitions);
        if ($offset === null) {
            return [
                'present' => false,
                'reference' => null,
            ];
        }

        return $this->trailerRootReferenceResolutionAtOffset($pdfBytes, $offset, $definitions, $objects);
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @param array<int, string> $objects
     * @param array<int, bool> $seenOffsets
     * @return array{present: bool, reference: array{objectNumber: int, generation: int}|null}
     */
    private function trailerRootReferenceResolutionAtOffset(
        string $pdfBytes,
        int $offset,
        array $definitions,
        array $objects,
        array $seenOffsets = []
    ): array {
        if ($offset < 0 || isset($seenOffsets[$offset])) {
            return [
                'present' => false,
                'reference' => null,
            ];
        }
        $seenOffsets[$offset] = true;

        $tableSection = $this->xrefTableSectionAt($pdfBytes, $offset, $definitions, $objects);
        if ($tableSection !== null) {
            $rootValue = $this->dictionaryRawValue($tableSection['trailer'], 'Root');
            if ($rootValue !== null) {
                $reference = $this->objectReferenceFromValue($rootValue);
                if ($reference !== null && $this->xrefEntriesSuppressObjectReference($tableSection['entries'], $reference)) {
                    return [
                        'present' => true,
                        'reference' => null,
                    ];
                }

                return [
                    'present' => true,
                    'reference' => $reference,
                ];
            }

            $hybridStreamOffset = $this->dictionaryIntegerValue($tableSection['trailer'], 'XRefStm', $objects);
            if ($hybridStreamOffset !== null && $hybridStreamOffset >= 0 && !isset($seenOffsets[$hybridStreamOffset])) {
                $streamDictionary = $this->xrefStreamDictionaryAtObjectOffset($pdfBytes, $hybridStreamOffset);
                if ($streamDictionary !== null) {
                    $rootValue = $this->dictionaryRawValue($streamDictionary, 'Root');
                    if ($rootValue !== null) {
                        return [
                            'present' => true,
                            'reference' => $this->objectReferenceFromValue($rootValue),
                        ];
                    }
                }
            }

            $previousOffset = $this->previousXrefOffsetForSectionBody(
                $pdfBytes,
                $tableSection['trailer'],
                $offset,
                $definitions,
                $objects
            );

            return $previousOffset === null
                ? ['present' => false, 'reference' => null]
                : $this->trailerRootReferenceResolutionAtOffset($pdfBytes, $previousOffset, $definitions, $objects, $seenOffsets);
        }

        $streamDictionary = $this->xrefStreamDictionaryAtObjectOffset($pdfBytes, $offset);
        if ($streamDictionary === null) {
            return [
                'present' => false,
                'reference' => null,
            ];
        }

        $rootValue = $this->dictionaryRawValue($streamDictionary, 'Root');
        if ($rootValue !== null) {
            $reference = $this->objectReferenceFromValue($rootValue);
            $streamSection = $this->xrefStreamSectionAtOffset($offset, $definitions);
            $entries = $streamSection === null
                ? []
                : $this->xrefStreamEntriesFromDefinition($streamSection['definition'], $objects, $definitions);
            if ($reference !== null && $this->xrefEntriesSuppressObjectReference($entries, $reference)) {
                return [
                    'present' => true,
                    'reference' => null,
                ];
            }

            return [
                'present' => true,
                'reference' => $reference,
            ];
        }

        $previousOffset = $this->previousXrefOffsetForSectionBody(
            $pdfBytes,
            $streamDictionary,
            $offset,
            $definitions,
            $objects
        );

        return $previousOffset === null
            ? ['present' => false, 'reference' => null]
            : $this->trailerRootReferenceResolutionAtOffset($pdfBytes, $previousOffset, $definitions, $objects, $seenOffsets);
    }

    /**
     * @param array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}> $entries
     * @param array{objectNumber: int, generation: int} $reference
     */
    private function xrefEntriesSuppressObjectReference(array $entries, array $reference): bool
    {
        $entry = $entries[$reference['objectNumber']] ?? null;
        $type = $entry['type'] ?? null;

        return $type === 0 || (is_int($type) && $type > 2);
    }

    private function trailerDictionaryBody(string $pdfBytes): ?string
    {
        $definitions = $this->directObjectDefinitions($pdfBytes);
        $startxrefOffset = $this->startxrefOffsetWithClassicRebuild(
            $pdfBytes,
            $definitions === [] ? null : $definitions
        );
        if ($startxrefOffset !== null) {
            $trailer = $this->trailerDictionaryBodyAtOffset(
                $pdfBytes,
                $startxrefOffset,
                $definitions === [] ? null : $definitions
            );
            if ($trailer !== null) {
                return $trailer;
            }
        }

        $body = null;
        $offset = 0;
        while (($position = strpos($pdfBytes, 'trailer', $offset)) !== false) {
            if (
                !$this->pdfKeywordAt($pdfBytes, $position, 'trailer')
                || $this->tokenStartsInPdfCommentLine($pdfBytes, $position)
                || $this->offsetOwnedByDirectObjectBody($position, $definitions)
                || $this->tokenStartsInsidePdfCompositeToken($pdfBytes, $position, $definitions)
            ) {
                $offset = $position + 7;
                continue;
            }

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

        return $body ?? $this->lastXrefStreamDictionaryBody($pdfBytes);
    }

    /**
     * @param array<int, list<array{bodyStart?: int, bodyEnd?: int}>>|null $definitions
     * @return array{offset: int, tokenOffset: int}|null
     */
    private function latestStartxrefEntry(string $pdfBytes, ?array $definitions = null): ?array
    {
        if (preg_match_all('/\bstartxref\b/s', $pdfBytes, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return null;
        }

        $linearizedHintRanges = $this->linearizedHintTableRanges($pdfBytes, $definitions);
        for ($index = count($matches[0]) - 1; $index >= 0; $index--) {
            $tokenOffset = $matches[0][$index][1] ?? null;
            if (
                !is_int($tokenOffset)
                || !$this->pdfKeywordAt($pdfBytes, $tokenOffset, 'startxref')
                || $this->tokenStartsInPdfCommentLine($pdfBytes, $tokenOffset)
                || (
                    $definitions !== null
                    && $this->offsetOwnedByDirectObjectBody($tokenOffset, $definitions)
                )
                || $this->tokenStartsInsidePdfCompositeToken($pdfBytes, $tokenOffset, $definitions)
                || $this->offsetInPdfByteRanges($tokenOffset, $linearizedHintRanges)
            ) {
                continue;
            }

            $operandBytes = substr($pdfBytes, $tokenOffset + strlen('startxref'), 64);
            $declaredOffset = $this->startxrefDeclaredOffsetFromOperand($operandBytes);
            if ($declaredOffset === null) {
                continue;
            }

            return [
                'offset' => max(0, $declaredOffset),
                'tokenOffset' => $tokenOffset,
            ];
        }

        return null;
    }

    private function startxrefDeclaredOffsetFromOperand(string $operandBytes): ?int
    {
        $offset = 0;
        $length = strlen($operandBytes);
        while ($offset < $length && $this->isPdfWhitespace($operandBytes[$offset])) {
            $offset++;
        }

        if (preg_match('/\G[+-]?\d+/s', $operandBytes, $match, 0, $offset) !== 1) {
            return 0;
        }

        $after = $offset + strlen($match[0]);
        while ($after < $length) {
            $char = $operandBytes[$after];
            if ($char === "\n" || $char === "\r" || $char === '%') {
                return (int) $match[0];
            }
            if (!$this->isPdfWhitespace($char)) {
                return null;
            }

            $after++;
        }

        return (int) $match[0];
    }

    /**
     * @param array<int, list<array{bodyStart?: int, bodyEnd?: int, generation?: int, offset?: int, body?: string}>>|null $definitions
     * @return list<array{start: int, end: int}>
     */
    private function linearizedHintTableRanges(string $pdfBytes, ?array $definitions = null): array
    {
        $definitions ??= $this->directObjectDefinitions($pdfBytes);
        $firstDefinition = null;
        foreach ($definitions as $entries) {
            foreach ($entries as $definition) {
                if (!isset($definition['offset'], $definition['body'])) {
                    continue;
                }

                if ($firstDefinition === null || $definition['offset'] < $firstDefinition['offset']) {
                    $firstDefinition = $definition;
                }
            }
        }

        if (
            $firstDefinition === null
            || preg_match('/\/Linearized\b/', (string) $firstDefinition['body']) !== 1
            || preg_match('/\/H\s*\[(.*?)\]/s', (string) $firstDefinition['body'], $match) !== 1
            || preg_match_all('/[-+]?\d+/', $match[1], $values) < 2
        ) {
            return [];
        }

        $ranges = [];
        $numbers = array_map('intval', $values[0]);
        for ($index = 0, $count = count($numbers); $index + 1 < $count; $index += 2) {
            $start = max(0, $numbers[$index]);
            $length = max(0, $numbers[$index + 1]);
            if ($length === 0) {
                continue;
            }

            $ranges[] = [
                'start' => $start,
                'end' => $start + $length,
            ];
        }

        return $ranges;
    }

    /**
     * @param list<array{start: int, end: int}> $ranges
     */
    private function offsetInPdfByteRanges(int $offset, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if ($offset >= $range['start'] && $offset < $range['end']) {
                return true;
            }
        }

        return false;
    }

    private function tokenStartsInPdfCommentLine(string $pdfBytes, int $tokenOffset): bool
    {
        $before = substr($pdfBytes, 0, $tokenOffset);
        $lastLineFeed = strrpos($before, "\n");
        $lastCarriageReturn = strrpos($before, "\r");
        $lineStart = max($lastLineFeed === false ? -1 : $lastLineFeed, $lastCarriageReturn === false ? -1 : $lastCarriageReturn) + 1;
        $commentOffset = strpos($pdfBytes, '%', $lineStart);

        return $commentOffset !== false && $commentOffset < $tokenOffset;
    }

    /**
     * @param array<int, list<array{bodyStart?: int, bodyEnd?: int}>>|null $definitions
     */
    private function tokenStartsInsidePdfCompositeToken(string $pdfBytes, int $tokenOffset, ?array $definitions = null): bool
    {
        $length = strlen($pdfBytes);
        $offset = 0;
        while ($offset < $tokenOffset && $offset < $length) {
            if ($definitions !== null) {
                foreach ($definitions as $entries) {
                    foreach ($entries as $definition) {
                        $bodyStart = $definition['bodyStart'] ?? null;
                        $bodyEnd = $definition['bodyEnd'] ?? null;
                        if (is_int($bodyStart) && is_int($bodyEnd) && $offset >= $bodyStart && $offset <= $bodyEnd) {
                            $offset = $bodyEnd + 1;
                            continue 3;
                        }
                    }
                }
            }

            $char = $pdfBytes[$offset];

            if ($char === '%') {
                $offset = $this->pdfCommentEndOffset($pdfBytes, $offset);
                continue;
            }

            if ($char === '(') {
                $literal = $this->readLiteralStringAt($pdfBytes, $offset);
                if ($literal === null) {
                    return true;
                }

                if ($tokenOffset > $offset && $tokenOffset < $literal['end']) {
                    return true;
                }
                $offset = $literal['end'];
                continue;
            }

            $compositeEnd = $this->skipPdfCompositeTokenAt($pdfBytes, $offset);
            if ($compositeEnd !== null) {
                if ($tokenOffset > $offset && $tokenOffset < $compositeEnd) {
                    return true;
                }
                $offset = $compositeEnd;
                continue;
            }

            if ($char === '<' && ($pdfBytes[$offset + 1] ?? '') !== '<') {
                $end = $this->skipPdfHexStringToken($pdfBytes, $offset);
                if ($end !== null) {
                    if ($tokenOffset > $offset && $tokenOffset < $end) {
                        return true;
                    }
                    $offset = $end;
                    continue;
                }
            }

            $offset++;
        }

        return false;
    }

    /**
     * Damaged producer output can append a current classic xref table while the
     * final startxref still points at an older table. Keep embedded-file review
     * on the latest top-level classic table before the selected startxref token.
     *
     * @param array<int, list<array{generation: int, offset: int, body: string}>>|null $definitions
     */
    private function startxrefOffsetWithClassicRebuild(string $pdfBytes, ?array $definitions = null): ?int
    {
        $entry = $this->latestStartxrefEntry($pdfBytes, $definitions);
        if ($entry === null) {
            return null;
        }

        $definitions ??= $this->directObjectDefinitions($pdfBytes);

        return $this->classicRebuildOffsetForStartxref(
            $pdfBytes,
            $entry['offset'],
            $definitions,
            $entry['tokenOffset']
        ) ?? $entry['offset'];
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     */
    private function classicRebuildOffsetForStartxref(
        string $pdfBytes,
        int $offset,
        array $definitions,
        ?int $candidateBeforeOffset = null
    ): ?int {
        if ($this->xrefStreamSectionAtOffset($offset, $definitions) !== null) {
            return null;
        }

        $latestClassicOffset = $this->latestClassicXrefTableOffset($pdfBytes, $definitions, $candidateBeforeOffset);
        if ($latestClassicOffset === null) {
            return null;
        }

        if ($this->xrefTableSectionAt($pdfBytes, $offset, $definitions) === null) {
            if (
                $candidateBeforeOffset !== null
                && $offset < $candidateBeforeOffset
                && $latestClassicOffset < $candidateBeforeOffset
            ) {
                return $latestClassicOffset;
            }

            if ($offset < strlen($pdfBytes) && $latestClassicOffset <= $offset) {
                return null;
            }

            return $latestClassicOffset;
        }

        return $latestClassicOffset > $offset ? $latestClassicOffset : null;
    }

    /**
     * @param array<int, list<array{bodyStart?: int, bodyEnd?: int}>> $definitions
     */
    private function latestClassicXrefTableOffset(string $pdfBytes, array $definitions, ?int $candidateBeforeOffset = null): ?int
    {
        $offsets = $this->xrefTableKeywordOffsets($pdfBytes, $definitions);
        for ($index = count($offsets) - 1; $index >= 0; $index--) {
            $offset = $offsets[$index];
            if ($candidateBeforeOffset !== null && $offset > $candidateBeforeOffset) {
                continue;
            }

            if ($this->xrefTableSectionAt($pdfBytes, $offset, $definitions) !== null) {
                return $offset;
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function xrefTableKeywordOffsets(string $pdfBytes, ?array $definitions = null): array
    {
        $offsets = [];
        $length = strlen($pdfBytes);
        $offset = 0;
        while ($offset < $length) {
            if ($definitions !== null) {
                foreach ($definitions as $entries) {
                    foreach ($entries as $definition) {
                        $bodyStart = $definition['bodyStart'] ?? null;
                        $bodyEnd = $definition['bodyEnd'] ?? null;
                        if (is_int($bodyStart) && is_int($bodyEnd) && $offset >= $bodyStart && $offset <= $bodyEnd) {
                            $offset = $bodyEnd + 1;
                            continue 3;
                        }
                    }
                }
            }

            $char = $pdfBytes[$offset];

            if ($char === '%') {
                $offset = $this->pdfCommentEndOffset($pdfBytes, $offset);
                continue;
            }

            if ($char === '(') {
                $literal = $this->readLiteralStringAt($pdfBytes, $offset);
                if ($literal === null) {
                    return $offsets;
                }

                $offset = $literal['end'];
                continue;
            }

            $compositeEnd = $this->skipPdfCompositeTokenAt($pdfBytes, $offset);
            if ($compositeEnd !== null) {
                $offset = $compositeEnd;
                continue;
            }

            if ($char === '<' && ($pdfBytes[$offset + 1] ?? '') !== '<') {
                $end = $this->skipPdfHexStringToken($pdfBytes, $offset);
                if ($end !== null) {
                    $offset = $end;
                    continue;
                }
            }

            if ($this->pdfKeywordAt($pdfBytes, $offset, 'xref')) {
                $offsets[] = $offset;
                $offset += strlen('xref');
                continue;
            }

            $offset++;
        }

        return $offsets;
    }

    /**
     * @param array<int, list<array{bodyStart?: int, bodyEnd?: int}>>|null $definitions
     */
    private function trailerDictionaryBodyAtOffset(string $pdfBytes, int $offset, ?array $definitions = null): ?string
    {
        $offset = $this->skipWhitespace($pdfBytes, $offset);
        if (substr($pdfBytes, $offset, 4) === 'xref') {
            return $this->xrefTableTrailerDictionaryAtOffset($pdfBytes, $offset, $definitions);
        }

        return $this->xrefStreamDictionaryAtObjectOffset($pdfBytes, $offset);
    }

    /**
     * @param array<int, list<array{bodyStart?: int, bodyEnd?: int}>>|null $definitions
     */
    private function xrefTableTrailerDictionaryAtOffset(string $pdfBytes, int $offset, ?array $definitions = null): ?string
    {
        $trailerOffset = $this->xrefTableTrailerKeywordOffset($pdfBytes, $offset + 4, $definitions);
        if ($trailerOffset === null) {
            return null;
        }

        $dictionaryOffset = strpos($pdfBytes, '<<', $trailerOffset);
        if ($dictionaryOffset === false) {
            return null;
        }

        $dictionary = $this->readPdfDictionaryAt($pdfBytes, $dictionaryOffset);
        return $dictionary === null ? null : $dictionary['body'];
    }

    /**
     * @param array<int, list<array{bodyStart?: int, bodyEnd?: int}>>|null $definitions
     */
    private function xrefTableTrailerKeywordOffset(string $pdfBytes, int $offset, ?array $definitions = null): ?int
    {
        $length = strlen($pdfBytes);
        while ($offset < $length) {
            if ($definitions !== null) {
                foreach ($definitions as $entries) {
                    foreach ($entries as $definition) {
                        $bodyStart = $definition['bodyStart'] ?? null;
                        $bodyEnd = $definition['bodyEnd'] ?? null;
                        if (is_int($bodyStart) && is_int($bodyEnd) && $offset >= $bodyStart && $offset <= $bodyEnd) {
                            $offset = $bodyEnd + 1;
                            continue 3;
                        }
                    }
                }
            }

            $char = $pdfBytes[$offset];

            if (
                substr($pdfBytes, $offset, 5) === '%%EOF'
                || $this->pdfKeywordAt($pdfBytes, $offset, 'startxref')
            ) {
                return null;
            }

            if ($char === '%') {
                $offset = $this->pdfCommentEndOffset($pdfBytes, $offset);
                continue;
            }

            if ($char === '(') {
                $literal = $this->readLiteralStringAt($pdfBytes, $offset);
                if ($literal === null) {
                    return null;
                }

                $offset = $literal['end'];
                continue;
            }

            $compositeEnd = $this->skipPdfCompositeTokenAt($pdfBytes, $offset);
            if ($compositeEnd !== null) {
                $offset = $compositeEnd;
                continue;
            }

            if ($char === '<' && ($pdfBytes[$offset + 1] ?? '') !== '<') {
                $end = $this->skipPdfHexStringToken($pdfBytes, $offset);
                if ($end !== null) {
                    $offset = $end;
                    continue;
                }
            }

            if ($this->pdfKeywordAt($pdfBytes, $offset, 'trailer')) {
                $dictionaryOffset = $this->skipPdfWhitespace($pdfBytes, $offset + strlen('trailer'));
                if (substr($pdfBytes, $dictionaryOffset, 2) === '<<') {
                    return $offset;
                }
            }

            $offset++;
        }

        return null;
    }

    private function skipPdfCompositeTokenAt(string $pdfBytes, int $offset): ?int
    {
        if ($offset < 0 || $offset >= strlen($pdfBytes)) {
            return null;
        }

        if ($pdfBytes[$offset] === '[') {
            $array = $this->readPdfArrayAt($pdfBytes, $offset);
            return $array === null ? null : $array['end'];
        }

        if (substr($pdfBytes, $offset, 2) === '<<') {
            $dictionary = $this->readPdfDictionaryAt($pdfBytes, $offset);
            return $dictionary === null ? null : $dictionary['end'];
        }

        return null;
    }

    private function xrefStreamDictionaryAtObjectOffset(string $pdfBytes, int $offset): ?string
    {
        $offset = $this->skipWhitespace($pdfBytes, $offset);
        if (preg_match('/\d+\s+\d+\s+obj\b/A', substr($pdfBytes, $offset), $match) !== 1) {
            return null;
        }

        $dictionaryOffset = strpos($pdfBytes, '<<', $offset + strlen($match[0]));
        if ($dictionaryOffset === false) {
            return null;
        }

        $dictionary = $this->readPdfDictionaryAt($pdfBytes, $dictionaryOffset);
        if ($dictionary === null || preg_match('/\/Type\s*\/XRef\b/s', $dictionary['body']) !== 1) {
            return null;
        }

        return $dictionary['body'];
    }

    private function lastXrefStreamDictionaryBody(string $pdfBytes): ?string
    {
        $body = null;
        $offset = 0;
        while (preg_match('/\d+\s+\d+\s+obj\b/s', $pdfBytes, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $candidate = $this->xrefStreamDictionaryAtObjectOffset($pdfBytes, $match[0][1]);
            if ($candidate !== null) {
                $body = $candidate;
            }
            $offset = $match[0][1] + strlen($match[0][0]);
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
            $body = $this->objectBodyFromReferenceValue($value, $objects);
            if ($body === null) {
                return null;
            }

            $body = $this->dictionaryObjectBody($body);
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

        return $this->objectBodyFromReferenceValue($trimmed, $objects);
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
        $filterSlots = $this->streamFilterSlots($dictionary, $objects);
        if ($filterSlots === null) {
            return null;
        }

        $filters = $this->streamFilterNames($filterSlots);
        $decodeParms = $this->streamDecodeParmsForFilters($dictionary, $objects, $filterSlots);
        if ($decodeParms === null) {
            return null;
        }
        if (!$this->decodeParmsSupportedForFilters($filterSlots, $decodeParms, $objects)) {
            return null;
        }

        foreach ($filterSlots as $filterIndex => $filter) {
            if ($filter === null) {
                continue;
            }

            $filterDecodeParms = $this->decodeParmsForFilterIndex($filterSlots, $decodeParms, $filterIndex);
            if (!$this->canApplyDecodeParms($filter, $filterDecodeParms, $objects)) {
                return null;
            }
            if (!$this->streamFilterInputHasBoundedEndMarker($filter, $stream, $filterDecodeParms, $objects)) {
                return null;
            }

            $decoded = match ($filter) {
                'ASCIIHexDecode', 'AHx' => $this->decodeAsciiHexStream($stream),
                'ASCII85Decode', 'A85' => $this->decodeAscii85Stream($stream),
                'FlateDecode', 'Fl' => $this->decodeFlateStream($stream, $filterDecodeParms, $objects),
                'RunLengthDecode', 'RL' => $this->decodeRunLengthStream($stream),
                'LZWDecode', 'LZW' => $this->decodeLzwStream($stream, $filterDecodeParms, $objects),
                'Crypt' => $this->decodeCryptIdentityStream($stream, $filterDecodeParms, $objects),
                default => null,
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
     * @return list<string|null>|null
     * @param array<int, string> $objects
     */
    private function streamFilterSlots(string $dictionary, array $objects): ?array
    {
        $value = $this->dictionaryRawValue($dictionary, 'Filter');
        if ($value === null) {
            return [];
        }

        $filters = $this->streamFilterSlotsFromValue($value, $objects);
        if ($filters === null) {
            return null;
        }

        foreach ($filters as $filter) {
            if (is_string($filter)) {
                return $filters;
            }
        }

        return [];
    }

    /**
     * @param list<string|null> $filterSlots
     * @return list<string>
     */
    private function streamFilterNames(array $filterSlots): array
    {
        $filters = [];
        foreach ($filterSlots as $filter) {
            if (is_string($filter)) {
                $filters[] = $filter;
            }
        }

        return $filters;
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, true> $seen
     * @return list<string|null>|null
     */
    private function streamFilterSlotsFromValue(
        string $value,
        array $objects,
        array $seen = [],
        bool $allowArray = true
    ): ?array {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $reference = $this->objectReferenceFromValue($trimmed);
        if ($reference !== null) {
            $objectKey = $reference['objectNumber'] . ':' . $reference['generation'];
            $body = $this->objectBodyFromReferenceValue($trimmed, $objects);
            if ($reference['objectNumber'] <= 0 || isset($seen[$objectKey]) || $body === null) {
                return null;
            }

            $nextSeen = $seen;
            $nextSeen[$objectKey] = true;
            return $this->streamFilterSlotsFromValue(
                $body,
                $objects,
                $nextSeen,
                $allowArray
            );
        }

        if (str_starts_with($trimmed, '[')) {
            if (!$allowArray) {
                return null;
            }

            $array = $this->readPdfArrayAt($trimmed, 0);
            if ($array === null || $this->skipWhitespace($trimmed, $array['end']) !== strlen($trimmed)) {
                return null;
            }

            $filters = [];
            foreach ($this->arrayItemsFromValue($array['raw'], $objects) as $item) {
                $slot = $this->streamFilterSlotsFromValue($item, $objects, $seen, false);
                if ($slot === null || count($slot) !== 1) {
                    return null;
                }

                $filters[] = $slot[0];
            }

            return $filters;
        }

        if ($trimmed === 'null') {
            return [null];
        }

        if (preg_match('/^\/([^\s\[\]()<>{}\/%]+)$/', $trimmed, $match) === 1) {
            return [$this->decodePdfName($match[1])];
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @return list<string|null>|null
     */
    private function streamDecodeParms(string $dictionary, array $objects): ?array
    {
        $value = $this->dictionaryRawValue($dictionary, 'DecodeParms');
        if ($value === null) {
            return [];
        }

        return $this->decodeParmsValueList($value, $objects);
    }

    /**
     * @param array<int, string> $objects
     * @param list<string|null> $filters
     * @return list<string|null>|null
     */
    private function streamDecodeParmsForFilters(string $dictionary, array $objects, array $filters): ?array
    {
        if ($filters === []) {
            return [];
        }

        $value = $this->dictionaryRawValue($dictionary, 'DecodeParms');
        if ($value === null) {
            return [];
        }

        $resolved = $this->resolveRawValue($value, $objects) ?? $value;
        $resolved = trim($resolved);
        if ($resolved === '') {
            return null;
        }

        if (!str_starts_with($resolved, '[')) {
            return $this->decodeParmsValueList($value, $objects);
        }

        $array = $this->readPdfArrayAt($resolved, 0);
        if ($array === null || $this->skipWhitespace($resolved, $array['end']) !== strlen($resolved)) {
            return null;
        }

        return $this->decodeParmsArrayItemsForFilters($array['raw'], $objects, $filters);
    }

    /**
     * @param array<int, string> $objects
     * @param list<string|null> $filters
     * @return list<string|null>|null
     */
    private function decodeParmsArrayItemsForFilters(string $arrayRaw, array $objects, array $filters): ?array
    {
        $rawItems = $this->arrayItemsFromValue($arrayRaw, $objects);
        $nonNullFilterIndexes = [];
        foreach ($filters as $filterIndex => $filter) {
            if (is_string($filter)) {
                $nonNullFilterIndexes[] = $filterIndex;
            }
        }

        $compactArray = count($rawItems) === count($nonNullFilterIndexes)
            && count($rawItems) !== count($filters);

        $items = [];
        foreach ($rawItems as $itemIndex => $rawItem) {
            $filterIndex = $compactArray ? ($nonNullFilterIndexes[$itemIndex] ?? null) : $itemIndex;
            if ($filterIndex !== null && array_key_exists($filterIndex, $filters) && $filters[$filterIndex] === null) {
                $items[] = null;
                continue;
            }

            $resolved = $this->decodeParmsValueList($rawItem, $objects);
            if ($resolved === null || count($resolved) !== 1) {
                return null;
            }

            $items[] = $resolved[0];
        }

        return $items;
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, true> $seen
     * @return list<string|null>|null
     */
    private function decodeParmsValueList(string $value, array $objects, array $seen = []): ?array
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $reference = $this->objectReferenceFromValue($trimmed);
        if ($reference !== null) {
            $objectKey = $reference['objectNumber'] . ':' . $reference['generation'];
            $body = $this->objectBodyFromReferenceValue($trimmed, $objects);
            if ($reference['objectNumber'] <= 0 || isset($seen[$objectKey]) || $body === null) {
                return null;
            }

            $nextSeen = $seen;
            $nextSeen[$objectKey] = true;
            return $this->decodeParmsValueList($body, $objects, $nextSeen);
        }

        if ($trimmed === 'null') {
            return [null];
        }

        if (str_starts_with($trimmed, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($trimmed, 0);
            if ($dictionary === null || $this->skipWhitespace($trimmed, $dictionary['end']) !== strlen($trimmed)) {
                return null;
            }

            return [$dictionary['body']];
        }

        if (!str_starts_with($trimmed, '[')) {
            return null;
        }

        $array = $this->readPdfArrayAt($trimmed, 0);
        if ($array === null || $this->skipWhitespace($trimmed, $array['end']) !== strlen($trimmed)) {
            return null;
        }

        $items = [];
        foreach ($this->arrayItemsFromValue($array['raw'], $objects) as $item) {
            $resolved = $this->decodeParmsValueList($item, $objects, $seen);
            if ($resolved === null || count($resolved) !== 1) {
                return null;
            }

            $items[] = $resolved[0];
        }

        return $items;
    }

    /**
     * @param list<string|null> $filters
     * @param list<string|null> $decodeParms
     */
    private function decodeParmsForFilterIndex(array $filters, array $decodeParms, int $filterIndex): ?string
    {
        $decodeParmsIndex = $this->decodeParmsIndexForFilterIndex($filters, $decodeParms, $filterIndex);
        return $decodeParmsIndex === null ? null : ($decodeParms[$decodeParmsIndex] ?? null);
    }

    /**
     * @param list<string|null> $filters
     * @param list<string|null> $decodeParms
     */
    private function decodeParmsIndexForFilterIndex(array $filters, array $decodeParms, int $filterIndex): ?int
    {
        if ($decodeParms === []) {
            return null;
        }

        $nonNullFilterIndexes = [];
        foreach ($filters as $candidateFilterIndex => $filter) {
            if (is_string($filter)) {
                $nonNullFilterIndexes[] = $candidateFilterIndex;
            }
        }

        if ($nonNullFilterIndexes === []) {
            return null;
        }

        if (count($decodeParms) === count($nonNullFilterIndexes) && count($decodeParms) !== count($filters)) {
            $compactPosition = array_search($filterIndex, $nonNullFilterIndexes, true);
            if ($compactPosition !== false) {
                $decodeParmsIndexes = array_keys($decodeParms);
                $decodeParmsIndex = $decodeParmsIndexes[$compactPosition] ?? null;
                return is_int($decodeParmsIndex) ? $decodeParmsIndex : null;
            }
        }

        if (array_key_exists($filterIndex, $decodeParms)) {
            return $filterIndex;
        }

        if (count($decodeParms) !== 1 || $nonNullFilterIndexes !== [$filterIndex]) {
            return null;
        }

        $decodeParmsIndex = array_key_first($decodeParms);
        return is_int($decodeParmsIndex) ? $decodeParmsIndex : null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function canApplyDecodeParms(string $filter, ?string $decodeParms, array $objects): bool
    {
        if ($decodeParms === null || trim($decodeParms) === '') {
            return true;
        }

        if ($filter === 'Crypt') {
            return $this->decodeCryptIdentityStream('', $decodeParms, $objects) !== null;
        }

        if (!$this->decodeParmsHaveSupportedKeys($decodeParms)) {
            return false;
        }

        foreach (['Predictor', 'Columns', 'Colors', 'BitsPerComponent', 'EarlyChange'] as $name) {
            if (
                $this->dictionaryRawValue($decodeParms, $name) !== null
                && $this->decodeParmsInt($decodeParms, $name, $objects) === null
            ) {
                return false;
            }
        }

        $predictor = $this->decodeParmsInt($decodeParms, 'Predictor', $objects) ?? 1;
        if (!in_array($filter, ['FlateDecode', 'Fl', 'LZWDecode', 'LZW'], true)) {
            return $predictor === 1
                && ($this->decodeParmsInt($decodeParms, 'Columns', $objects) ?? 1) === 1
                && ($this->decodeParmsInt($decodeParms, 'Colors', $objects) ?? 1) === 1
                && ($this->decodeParmsInt($decodeParms, 'BitsPerComponent', $objects) ?? 8) === 8
                && ($this->decodeParmsInt($decodeParms, 'EarlyChange', $objects) ?? 1) === 1;
        }

        if ($predictor !== 1 && $predictor !== 2 && ($predictor < 10 || $predictor > 15)) {
            return false;
        }

        foreach (['Columns', 'Colors', 'BitsPerComponent'] as $name) {
            $integer = $this->decodeParmsInt($decodeParms, $name, $objects);
            if ($integer !== null && $integer < 1) {
                return false;
            }
        }

        $earlyChange = $this->decodeParmsInt($decodeParms, 'EarlyChange', $objects);
        if ($earlyChange !== null && !in_array($earlyChange, [0, 1], true)) {
            return false;
        }

        return true;
    }

    private function decodeParmsHaveSupportedKeys(string $decodeParms): bool
    {
        foreach (array_keys($this->dictionaryEntries($decodeParms)) as $name) {
            if (!in_array($name, ['Predictor', 'Columns', 'Colors', 'BitsPerComponent', 'EarlyChange'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string|null> $filters
     * @param list<string|null> $decodeParms
     * @param array<int, string> $objects
     */
    private function decodeParmsSupportedForFilters(array $filters, array $decodeParms, array $objects): bool
    {
        if ($filters === []) {
            return true;
        }

        $appliedDecodeParmsIndexes = [];
        $nonNullFilterCount = 0;
        foreach ($filters as $filterIndex => $filter) {
            if ($filter === null) {
                continue;
            }

            $nonNullFilterCount++;
            $decodeParmsIndex = $this->decodeParmsIndexForFilterIndex($filters, $decodeParms, $filterIndex);
            if ($decodeParmsIndex === null || !array_key_exists($decodeParmsIndex, $decodeParms)) {
                continue;
            }

            $appliedDecodeParmsIndexes[$decodeParmsIndex] = true;
            if (!$this->canApplyDecodeParms($filter, $decodeParms[$decodeParmsIndex], $objects)) {
                return false;
            }
        }

        foreach ($decodeParms as $decodeParmsIndex => $value) {
            if (isset($appliedDecodeParmsIndexes[$decodeParmsIndex]) || $value === null) {
                continue;
            }
            if (
                count($decodeParms) !== $nonNullFilterCount
                && array_key_exists($decodeParmsIndex, $filters)
                && $filters[$decodeParmsIndex] === null
            ) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeParmsValueIsDefault(?string $decodeParms, array $objects): bool
    {
        if ($decodeParms === null || trim($decodeParms) === '') {
            return true;
        }
        if (!$this->decodeParmsHaveSupportedKeys($decodeParms)) {
            return false;
        }

        foreach ([
            'Predictor' => 1,
            'Columns' => 1,
            'Colors' => 1,
            'BitsPerComponent' => 8,
            'EarlyChange' => 1,
        ] as $name => $default) {
            $value = $this->decodeParmsInt($decodeParms, $name, $objects);
            if ($value !== null && $value !== $default) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeParmsInt(string $decodeParms, string $name, array $objects): ?int
    {
        $value = $this->dictionaryRawValue($decodeParms, $name);
        if ($value === null) {
            return null;
        }

        $reviewValue = $this->reviewValueFromRaw($value, $objects);
        return is_int($reviewValue) ? $reviewValue : null;
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

    /**
     * @param array<int, string> $objects
     */
    private function decodeCryptIdentityStream(string $stream, ?string $decodeParms, array $objects): ?string
    {
        if ($decodeParms === null || trim($decodeParms) === '') {
            return $stream;
        }

        $entries = $this->dictionaryEntries($decodeParms);
        $name = $this->dictionaryNameValue($decodeParms, 'Name', $objects);
        if ($name !== null) {
            return $name === 'Identity' ? $stream : null;
        }

        foreach (array_keys($entries) as $key) {
            if ($key !== 'Type') {
                return null;
            }
        }

        return $stream;
    }

    private function decodeAscii85Stream(string $stream): ?string
    {
        $body = trim($stream);
        if (str_starts_with($body, '<~')) {
            $body = substr($body, 2);
        }

        $terminator = strpos($body, '~>');
        if ($terminator !== false) {
            $body = substr($body, 0, $terminator);
        }

        $out = '';
        $group = [];
        $length = strlen($body);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $body[$offset];
            if (ctype_space($char)) {
                continue;
            }

            if ($char === 'z') {
                if ($group !== []) {
                    return null;
                }
                $out .= "\0\0\0\0";
                continue;
            }

            $ord = ord($char);
            if ($ord < 33 || $ord > 117) {
                return null;
            }

            $group[] = $ord - 33;
            if (count($group) === 5) {
                $out .= $this->decodeAscii85Group($group, 4);
                $group = [];
            }
        }

        if ($group !== []) {
            $groupLength = count($group);
            if ($groupLength === 1) {
                return null;
            }
            while (count($group) < 5) {
                $group[] = 84;
            }
            $out .= $this->decodeAscii85Group($group, $groupLength - 1);
        }

        return $out;
    }

    /**
     * @param list<int> $group
     */
    private function decodeAscii85Group(array $group, int $bytesToReturn): string
    {
        $value = 0;
        foreach ($group as $digit) {
            $value = ($value * 85) + $digit;
        }

        $bytes = '';
        for ($shift = 24; $shift >= 0; $shift -= 8) {
            $bytes .= chr(($value >> $shift) & 0xff);
        }

        return substr($bytes, 0, $bytesToReturn);
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeFlateStream(string $stream, ?string $decodeParms = null, array $objects = []): ?string
    {
        $inflated = @gzuncompress($stream);
        if ($inflated === false) {
            $inflated = @gzinflate($stream);
        }
        if ($inflated === false) {
            $inflated = @gzdecode($stream);
        }

        if ($inflated === false) {
            return null;
        }

        return $this->applyDecodeParmsPredictor($inflated, $decodeParms, $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeLzwStream(string $stream, ?string $decodeParms = null, array $objects = []): ?string
    {
        $earlyChange = ($this->decodeParmsInt($decodeParms ?? '', 'EarlyChange', $objects) ?? 1) === 0 ? 0 : 1;
        $bitOffset = 0;
        $dictionary = [];
        $nextCode = 258;
        $codeSize = 9;

        $resetDictionary = static function () use (&$dictionary, &$nextCode, &$codeSize): void {
            $dictionary = [];
            for ($code = 0; $code < 256; $code++) {
                $dictionary[$code] = chr($code);
            }
            $nextCode = 258;
            $codeSize = 9;
        };
        $resetDictionary();

        $out = '';
        $previous = null;
        while (($code = $this->readLzwCode($stream, $bitOffset, $codeSize)) !== null) {
            if ($code === 256) {
                $resetDictionary();
                $previous = null;
                continue;
            }

            if ($code === 257) {
                return $this->applyDecodeParmsPredictor($out, $decodeParms, $objects);
            }

            if (isset($dictionary[$code])) {
                $entry = $dictionary[$code];
            } elseif ($code === $nextCode && $previous !== null) {
                $entry = $previous . $previous[0];
            } else {
                return null;
            }

            $out .= $entry;
            if ($previous !== null && $nextCode < 4096) {
                $dictionary[$nextCode] = $previous . $entry[0];
                $nextCode++;
                if ($codeSize < 12 && $nextCode + $earlyChange >= (1 << $codeSize)) {
                    $codeSize++;
                }
            }
            $previous = $entry;
        }

        return null;
    }

    private function readLzwCode(string $bytes, int &$bitOffset, int $codeSize): ?int
    {
        $totalBits = strlen($bytes) * 8;
        if ($bitOffset + $codeSize > $totalBits) {
            return null;
        }

        $code = 0;
        for ($index = 0; $index < $codeSize; $index++) {
            $absoluteBit = $bitOffset + $index;
            $byte = ord($bytes[intdiv($absoluteBit, 8)]);
            $shift = 7 - ($absoluteBit % 8);
            $code = ($code << 1) | (($byte >> $shift) & 1);
        }
        $bitOffset += $codeSize;

        return $code;
    }

    /**
     * @param array<int, string> $objects
     */
    private function applyDecodeParmsPredictor(string $bytes, ?string $decodeParms, array $objects): ?string
    {
        $predictor = $this->decodeParmsInt($decodeParms ?? '', 'Predictor', $objects) ?? 1;
        if ($predictor === 1) {
            return $bytes;
        }

        $colors = max(1, $this->decodeParmsInt($decodeParms ?? '', 'Colors', $objects) ?? 1);
        $bitsPerComponent = max(1, $this->decodeParmsInt($decodeParms ?? '', 'BitsPerComponent', $objects) ?? 8);
        $columns = max(1, $this->decodeParmsInt($decodeParms ?? '', 'Columns', $objects) ?? 1);
        $rowLength = intdiv(($colors * $columns * $bitsPerComponent) + 7, 8);
        $bytesPerPixel = max(1, intdiv(($colors * $bitsPerComponent) + 7, 8));

        if ($predictor === 2) {
            return $this->applyTiffPredictor($bytes, $rowLength, $bytesPerPixel);
        }

        if ($predictor < 10 || $predictor > 15) {
            return null;
        }

        return $this->applyPngPredictor($bytes, $rowLength, $bytesPerPixel);
    }

    private function applyTiffPredictor(string $bytes, int $rowLength, int $bytesPerPixel): ?string
    {
        if ($rowLength < 1 || strlen($bytes) % $rowLength !== 0) {
            return null;
        }

        $out = '';
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $rowLength) {
            $row = substr($bytes, $offset, $rowLength);
            for ($index = $bytesPerPixel; $index < $rowLength; $index++) {
                $row[$index] = chr((ord($row[$index]) + ord($row[$index - $bytesPerPixel])) & 0xff);
            }
            $out .= $row;
        }

        return $out;
    }

    private function applyPngPredictor(string $bytes, int $rowLength, int $bytesPerPixel): ?string
    {
        $stride = $rowLength + 1;
        if ($rowLength < 1 || strlen($bytes) % $stride !== 0) {
            return null;
        }

        $out = '';
        $previous = str_repeat("\0", $rowLength);
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $stride) {
            $filter = ord($bytes[$offset]);
            $row = substr($bytes, $offset + 1, $rowLength);
            if ($filter > 4) {
                return null;
            }

            for ($index = 0; $index < $rowLength; $index++) {
                $left = $index >= $bytesPerPixel ? ord($row[$index - $bytesPerPixel]) : 0;
                $up = ord($previous[$index]);
                $upperLeft = $index >= $bytesPerPixel ? ord($previous[$index - $bytesPerPixel]) : 0;
                $encoded = ord($row[$index]);
                $row[$index] = chr(($encoded + $this->pngPredictorValue($filter, $left, $up, $upperLeft)) & 0xff);
            }

            $out .= $row;
            $previous = $row;
        }

        return $out;
    }

    private function pngPredictorValue(int $filter, int $left, int $up, int $upperLeft): int
    {
        return match ($filter) {
            0 => 0,
            1 => $left,
            2 => $up,
            3 => intdiv($left + $up, 2),
            4 => $this->paethPredictor($left, $up, $upperLeft),
        };
    }

    private function paethPredictor(int $left, int $up, int $upperLeft): int
    {
        $estimate = $left + $up - $upperLeft;
        $leftDistance = abs($estimate - $left);
        $upDistance = abs($estimate - $up);
        $upperLeftDistance = abs($estimate - $upperLeft);

        if ($leftDistance <= $upDistance && $leftDistance <= $upperLeftDistance) {
            return $left;
        }
        if ($upDistance <= $upperLeftDistance) {
            return $up;
        }

        return $upperLeft;
    }

    private function decodeRunLengthStream(string $stream): ?string
    {
        $out = '';
        $length = strlen($stream);
        for ($offset = 0; $offset < $length; $offset++) {
            $control = ord($stream[$offset]);
            if ($control === 128) {
                return $out;
            }

            if ($control <= 127) {
                $copyLength = $control + 1;
                if ($offset + $copyLength >= $length) {
                    return null;
                }
                $out .= substr($stream, $offset + 1, $copyLength);
                $offset += $copyLength;
                continue;
            }

            if ($offset + 1 >= $length) {
                return null;
            }
            $out .= str_repeat($stream[$offset + 1], 257 - $control);
            $offset++;
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function streamFilterInputHasBoundedEndMarker(
        string $filter,
        string $stream,
        ?string $decodeParms = null,
        array $objects = []
    ): bool {
        $offset = $this->streamFilterInputEndByteOffset($filter, $stream, $decodeParms, $objects);
        if ($offset === null) {
            return !in_array($filter, [
                'FlateDecode',
                'Fl',
                'ASCII85Decode',
                'A85',
                'ASCIIHexDecode',
                'AHx',
                'RunLengthDecode',
                'RL',
                'LZWDecode',
                'LZW',
            ], true);
        }

        return $this->streamHasOnlyWhitespaceAfterOffset($stream, $offset);
    }

    private function streamHasOnlyWhitespaceAfterOffset(string $stream, int $offset): bool
    {
        $length = strlen($stream);
        for ($index = $offset; $index < $length;) {
            if ($this->isPdfWhitespace($stream[$index])) {
                $index++;
                continue;
            }

            if ($stream[$index] === '%') {
                $lineLength = strcspn($stream, "\r\n", $index);
                if ($index + $lineLength >= $length) {
                    return false;
                }
                $index += $lineLength;
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * @param array<int, string> $objects
     */
    private function streamFilterInputEndByteOffset(
        string $filter,
        string $stream,
        ?string $decodeParms = null,
        array $objects = []
    ): ?int {
        return match ($filter) {
            'ASCIIHexDecode', 'AHx' => (($offset = strpos($stream, '>')) !== false) ? $offset + 1 : null,
            'ASCII85Decode', 'A85' => (($offset = strpos($stream, '~>')) !== false) ? $offset + 2 : null,
            'RunLengthDecode', 'RL' => (($offset = $this->runLengthExplicitEndOffset($stream)) !== null) ? $offset + 1 : null,
            'FlateDecode', 'Fl' => $this->flateExplicitEndByteOffset($stream),
            'LZWDecode', 'LZW' => $this->lzwExplicitEndByteOffset($stream, $decodeParms, $objects),
            default => null,
        };
    }

    private function runLengthExplicitEndOffset(string $stream): ?int
    {
        $length = strlen($stream);
        for ($offset = 0; $offset < $length; $offset++) {
            $control = ord($stream[$offset]);
            if ($control === 128) {
                return $offset;
            }
            if ($control <= 127) {
                $literalLength = $control + 1;
                if ($offset + $literalLength >= $length) {
                    return null;
                }
                $offset += $literalLength;
                continue;
            }
            if ($offset + 1 >= $length) {
                return null;
            }
            $offset++;
        }

        return null;
    }

    private function flateExplicitEndByteOffset(string $stream): ?int
    {
        if (
            !function_exists('inflate_init')
            || !function_exists('inflate_add')
            || !function_exists('inflate_get_status')
            || !function_exists('inflate_get_read_len')
        ) {
            return null;
        }

        $encodings = [];
        foreach (['ZLIB_ENCODING_DEFLATE', 'ZLIB_ENCODING_RAW', 'ZLIB_ENCODING_GZIP'] as $constant) {
            if (defined($constant)) {
                $encodings[] = constant($constant);
            }
        }

        $finish = defined('ZLIB_FINISH') ? constant('ZLIB_FINISH') : 4;
        $streamEnd = defined('ZLIB_STREAM_END') ? constant('ZLIB_STREAM_END') : 1;
        foreach (array_unique($encodings) as $encoding) {
            $context = @inflate_init($encoding);
            if ($context === false) {
                continue;
            }

            $decoded = @inflate_add($context, $stream, $finish);
            if ($decoded === false || @inflate_get_status($context) !== $streamEnd) {
                continue;
            }

            $readLength = @inflate_get_read_len($context);
            if (is_int($readLength) && $readLength > 0) {
                return $readLength;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function lzwExplicitEndByteOffset(string $stream, ?string $decodeParms = null, array $objects = []): ?int
    {
        $earlyChange = ($this->decodeParmsInt($decodeParms ?? '', 'EarlyChange', $objects) ?? 1) === 0 ? 0 : 1;
        $bitOffset = 0;
        $dictionary = [];
        $nextCode = 258;
        $codeSize = 9;

        $resetDictionary = static function () use (&$dictionary, &$nextCode, &$codeSize): void {
            $dictionary = [];
            for ($code = 0; $code < 256; $code++) {
                $dictionary[$code] = chr($code);
            }
            $nextCode = 258;
            $codeSize = 9;
        };
        $resetDictionary();

        $previous = null;
        while (($code = $this->readLzwCode($stream, $bitOffset, $codeSize)) !== null) {
            if ($code === 256) {
                $resetDictionary();
                $previous = null;
                continue;
            }

            if ($code === 257) {
                return intdiv($bitOffset + 7, 8);
            }

            if (isset($dictionary[$code])) {
                $entry = $dictionary[$code];
            } elseif ($code === $nextCode && $previous !== null) {
                $entry = $previous . $previous[0];
            } else {
                return null;
            }

            if ($previous !== null && $nextCode < 4096) {
                $dictionary[$nextCode] = $previous . $entry[0];
                $nextCode++;
                if ($codeSize < 12 && $nextCode + $earlyChange >= (1 << $codeSize)) {
                    $codeSize++;
                }
            }
            $previous = $entry;
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

    /**
     * @param array<int, string>|null $objects
     */
    private function dictionaryIntegerValue(string $dictionary, string $key, ?array $objects = null): ?int
    {
        $value = $this->resolvedDictionaryRawValue($dictionary, $key, $objects);
        if ($value === null) {
            return null;
        }

        $resolved = trim($value);
        if (preg_match('/^[+-]?\d+$/', $resolved) !== 1) {
            return null;
        }

        return (int) $resolved;
    }

    /**
     * @param array<int, string>|null $objects
     */
    private function resolvedDictionaryRawValue(string $dictionary, string $key, ?array $objects): ?string
    {
        $value = $this->dictionaryRawValue($dictionary, $key);
        if ($value === null) {
            return null;
        }

        return $objects === null ? $value : ($this->resolveRawValue($value, $objects) ?? $value);
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
        $entries = $this->dictionaryEntries($dictionary);
        return $entries[$key] ?? null;
    }

    /**
     * @param list<string> $keys
     */
    private function dictionaryHasDuplicateKeys(string $dictionary, array $keys): bool
    {
        $counts = $this->dictionaryEntryCounts($dictionary);
        foreach ($keys as $key) {
            if (($counts[$key] ?? 0) > 1) {
                return true;
            }
        }

        return false;
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

    private function skipPdfHexStringToken(string $value, int $offset): ?int
    {
        if (($value[$offset] ?? '') !== '<' || ($value[$offset + 1] ?? '') === '<') {
            return null;
        }

        for ($index = $offset + 1, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '>') {
                return $index + 1;
            }

            if (ctype_xdigit($char) || ctype_space($char)) {
                continue;
            }

            return null;
        }

        return null;
    }

    private function skipHexString(string $value, int $offset): ?int
    {
        $end = strpos($value, '>', $offset + 1);
        return $end === false ? null : $end + 1;
    }

    private function pdfCommentEndOffset(string $value, int $offset): int
    {
        $length = strlen($value);
        while ($offset < $length && $value[$offset] !== "\n" && $value[$offset] !== "\r") {
            $offset++;
        }

        return $offset;
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

    private function skipPdfWhitespace(string $value, int $offset): int
    {
        for ($length = strlen($value); $offset < $length;) {
            if ($this->isPdfWhitespace($value[$offset])) {
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

    private function bytesThroughTerminalEof(string $pdfBytes): string
    {
        $eof = strrpos($pdfBytes, '%%EOF');
        if ($eof === false) {
            return $pdfBytes;
        }

        return substr($pdfBytes, 0, $eof + strlen('%%EOF'));
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
     * @param array<int, string> $objects
     */
    private function objectBodyFromReferenceValue(?string $value, array $objects): ?string
    {
        $reference = $this->objectReferenceFromValue($value);
        if ($reference === null) {
            return null;
        }

        $objectNumber = $reference['objectNumber'];
        if (!isset($objects[$objectNumber])) {
            return null;
        }

        if (($this->objectGenerations[$objectNumber] ?? null) !== $reference['generation']) {
            return null;
        }

        return $objects[$objectNumber];
    }

    /**
     * @return array{objectNumber: int, generation: int}|null
     */
    private function objectReferenceFromValue(?string $value): ?array
    {
        if ($value === null || preg_match('/^(\d+)\s+(\d+)\s+R\b/s', trim($value), $match) !== 1) {
            return null;
        }

        return [
            'objectNumber' => (int) $match[1],
            'generation' => (int) $match[2],
        ];
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
     * @return array<string, int>
     */
    private function dictionaryEntryCounts(string $dictionary): array
    {
        $counts = [];
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

            $counts[$name] = ($counts[$name] ?? 0) + 1;
            $offset = $value['end'];
        }

        return $counts;
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

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private function uniqueStrings(array $values): array
    {
        $seen = [];
        $unique = [];
        foreach ($values as $value) {
            if ($value === '' || isset($seen[$value])) {
                continue;
            }

            $seen[$value] = true;
            $unique[] = $value;
        }

        return $unique;
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
}
