<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class PdfAttachmentExtractor
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

    /**
     * Native PDF attachment preflight for embedded file streams referenced by
     * document EmbeddedFiles name trees or page FileAttachment annotations.
     *
     * @return list<array<string, mixed>>
     */
    public function extractAttachments(string $pdfBytes): array
    {
        $this->assertPdfBytes($pdfBytes);
        $objects = $this->pdfObjects($pdfBytes);
        if ($objects === []) {
            return [];
        }

        $catalogObjectIds = $this->selectedCatalogObjectIds($pdfBytes, $objects);
        $encryptionPolicy = $this->attachmentEncryptionPolicy($pdfBytes, $objects);
        $attachments = [];
        foreach ($this->embeddedFilesNameTreeEntries($objects, $catalogObjectIds) as $entry) {
            $attachment = $this->attachmentFromFileSpecValue(
                $entry['fileSpec'],
                $objects,
                'embedded-files-name-tree',
                [
                    'name_key' => $entry['name'],
                ],
                $encryptionPolicy
            );
            if ($attachment !== null) {
                $attachments[] = $attachment;
            }
        }

        foreach ($this->catalogAssociatedFileEntries($objects, $catalogObjectIds) as $entry) {
            $attachment = $this->attachmentFromFileSpecValue(
                $entry['fileSpec'],
                $objects,
                'catalog-associated-file',
                [
                    'associated_file' => true,
                    'associated_file_index' => $entry['associatedFileIndex'],
                    'catalog_object_id' => $entry['catalogObjectId'],
                ],
                $encryptionPolicy
            );
            if ($attachment === null) {
                continue;
            }

            $duplicateIndex = $this->documentAttachmentIndex($attachments, $attachment);
            if ($duplicateIndex !== null) {
                $attachments[$duplicateIndex]['associated_file'] = true;
                $attachments[$duplicateIndex]['associated_file_source'] = 'catalog_af';
                $attachments[$duplicateIndex]['associated_file_index'] = $entry['associatedFileIndex'];
                $attachments[$duplicateIndex]['catalog_object_id'] = $entry['catalogObjectId'];
                continue;
            }

            $attachments[] = $attachment;
        }

        foreach ($this->pageAssociatedFileEntries($objects, $catalogObjectIds) as $entry) {
            $attachment = $this->attachmentFromFileSpecValue(
                $entry['fileSpec'],
                $objects,
                'page-associated-file',
                [
                    'associated_file' => true,
                    'page_associated_file' => true,
                    'page_associated_file_index' => $entry['associatedFileIndex'],
                    'page_number' => $entry['pageNumber'],
                    'page_object_id' => $entry['pageObjectId'],
                ],
                $encryptionPolicy
            );
            if ($attachment === null) {
                continue;
            }

            $duplicateIndex = $this->documentAttachmentIndex($attachments, $attachment);
            if ($duplicateIndex !== null) {
                $attachments[$duplicateIndex]['associated_file'] = true;
                $attachments[$duplicateIndex]['page_associated_file'] = true;
                $attachments[$duplicateIndex]['page_associated_file_source'] = 'page_af';
                $attachments[$duplicateIndex]['page_associated_file_index'] = $entry['associatedFileIndex'];
                $attachments[$duplicateIndex]['page_number'] = $entry['pageNumber'];
                $attachments[$duplicateIndex]['page_object_id'] = $entry['pageObjectId'];
                continue;
            }

            $attachments[] = $attachment;
        }

        foreach ($this->fileAttachmentAnnotationEntries($objects, $catalogObjectIds) as $entry) {
            $attachment = $this->attachmentFromFileSpecValue(
                $entry['fileSpec'],
                $objects,
                'file-attachment-annotation',
                [
                    'page_number' => $entry['pageNumber'],
                    'page_object_id' => $entry['pageObjectId'],
                    'annotation_object_id' => $entry['annotationObjectId'],
                    'annotation_contents' => $entry['contents'],
                    'annotation_rect' => $entry['rect'],
                ],
                $encryptionPolicy
            );
            if ($attachment !== null) {
                $attachment['file_attachment_annotation'] = true;
                $attachment['file_attachment_annotation_source'] = 'page_annotation';

                $duplicateIndex = $this->documentAttachmentIndex($attachments, $attachment);
                if ($duplicateIndex !== null) {
                    $this->applyFileAttachmentAnnotationMirrorMetadata($attachments[$duplicateIndex], $attachment);
                    continue;
                }

                $attachments[] = $attachment;
            }
        }

        return $attachments;
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $annotationAttachment
     */
    private function applyFileAttachmentAnnotationMirrorMetadata(array &$target, array $annotationAttachment): void
    {
        $target['file_attachment_annotation'] = true;
        $target['file_attachment_annotation_source'] = 'page_annotation';

        foreach ([
            'page_number',
            'page_object_id',
            'annotation_object_id',
            'annotation_contents',
            'annotation_rect',
        ] as $key) {
            if (array_key_exists($key, $annotationAttachment)) {
                $target[$key] = $annotationAttachment[$key];
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $attachments
     * @param array<string, mixed> $candidate
     */
    private function documentAttachmentIndex(array $attachments, array $candidate): ?int
    {
        foreach ($attachments as $index => $attachment) {
            if (($attachment['source'] ?? null) !== 'embedded-files-name-tree') {
                continue;
            }

            if ($this->sameAttachmentFileSpecMirror($attachment, $candidate)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $attachment
     * @param array<string, mixed> $candidate
     */
    private function sameAttachmentFileSpecMirror(array $attachment, array $candidate): bool
    {
        $attachmentStreamId = $attachment['stream_object_id'] ?? null;
        $candidateStreamId = $candidate['stream_object_id'] ?? null;
        if (!is_int($attachmentStreamId) || !is_int($candidateStreamId) || $attachmentStreamId !== $candidateStreamId) {
            return false;
        }

        $attachmentFileSpecId = $attachment['file_spec_object_id'] ?? null;
        $candidateFileSpecId = $candidate['file_spec_object_id'] ?? null;
        if (is_int($attachmentFileSpecId) && is_int($candidateFileSpecId)) {
            return $attachmentFileSpecId === $candidateFileSpecId;
        }

        return ($attachment['filename'] ?? null) === ($candidate['filename'] ?? null)
            && ($attachment['byte_length'] ?? null) === ($candidate['byte_length'] ?? null)
            && ($attachment['sha256'] ?? null) === ($candidate['sha256'] ?? null);
    }

    /**
     * @return array{attachment_count: int, total_bytes: int, filenames: list<string>, attachments: list<array<string, mixed>>, executes_python_or_models: false, executes_external_pdf_tools: false}
     */
    public function attachmentSummary(string $pdfBytes): array
    {
        $attachments = $this->extractAttachments($pdfBytes);
        $summaryAttachments = [];
        $totalBytes = 0;
        $filenames = [];
        foreach ($attachments as $attachment) {
            if (isset($attachment['byte_length']) && is_int($attachment['byte_length'])) {
                $totalBytes += $attachment['byte_length'];
            }
            if (isset($attachment['filename']) && is_string($attachment['filename']) && $attachment['filename'] !== '') {
                $filenames[] = $attachment['filename'];
            }
            $withoutBytes = $attachment;
            unset($withoutBytes['bytes']);
            $summaryAttachments[] = $withoutBytes;
        }

        return [
            'attachment_count' => count($attachments),
            'total_bytes' => $totalBytes,
            'filenames' => $filenames,
            'attachments' => $summaryAttachments,
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return list<int>|null
     */
    private function selectedCatalogObjectIds(string $pdfBytes, array $objects): ?array
    {
        $catalogReference = $this->latestTrailerRootCatalogReference($pdfBytes);
        if ($catalogReference === null) {
            return null;
        }

        $catalogObjectId = $catalogReference['objectNumber'];
        $catalogObject = $this->objectForReference([
            '__kind' => 'ref',
            'object' => $catalogObjectId,
            'generation' => $catalogReference['generation'],
        ], $objects);
        if ($catalogObject === null) {
            return null;
        }

        $catalog = $this->dict($catalogObject['value']);
        if ($catalog === null || $this->nameValue($catalog['Type'] ?? null) !== 'Catalog') {
            return null;
        }

        return [$catalogObjectId];
    }

    /**
     * @return array{objectNumber: int, generation: int}|null
     */
    private function latestTrailerRootCatalogReference(string $pdfBytes): ?array
    {
        $pdfBytes = $this->bytesThroughTerminalEof($pdfBytes);
        $offset = $this->latestStartxrefOffset($pdfBytes);
        if ($offset === null) {
            return null;
        }

        $table = $this->xrefTableSectionAt($pdfBytes, $offset);
        if ($table !== null) {
            return $this->refObjectReference($table['trailer']['Root'] ?? null);
        }

        $definitions = $this->directObjectDefinitions($pdfBytes);
        $stream = $this->xrefStreamSectionAt($offset, $definitions);
        if ($stream !== null) {
            return $this->refObjectReference($stream['dictionary']['Root'] ?? null);
        }

        return null;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @param list<int>|null $catalogObjectIds
     * @return list<array{name: string, fileSpec: mixed}>
     */
    private function embeddedFilesNameTreeEntries(array $objects, ?array $catalogObjectIds = null): array
    {
        $entries = [];
        foreach ($objects as $objectId => $object) {
            if ($catalogObjectIds !== null && !in_array($objectId, $catalogObjectIds, true)) {
                continue;
            }

            $dict = $this->dict($object['value']);
            if ($dict === null || $this->nameValue($dict['Type'] ?? null) !== 'Catalog') {
                continue;
            }

            $names = $this->dict($this->resolveValue($dict['Names'] ?? null, $objects));
            if ($names === null || !array_key_exists('EmbeddedFiles', $names)) {
                continue;
            }

            foreach ($this->nameTreeEntries($names['EmbeddedFiles'], $objects) as $entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @param list<int>|null $catalogObjectIds
     * @return list<array{catalogObjectId: int, associatedFileIndex: int, fileSpec: mixed}>
     */
    private function catalogAssociatedFileEntries(array $objects, ?array $catalogObjectIds = null): array
    {
        $entries = [];
        foreach ($objects as $objectId => $object) {
            if ($catalogObjectIds !== null && !in_array($objectId, $catalogObjectIds, true)) {
                continue;
            }

            $dict = $this->dict($object['value']);
            if ($dict === null || $this->nameValue($dict['Type'] ?? null) !== 'Catalog') {
                continue;
            }

            $associatedFiles = $this->arrayValue($this->resolveValue($dict['AF'] ?? null, $objects));
            if ($associatedFiles === null) {
                continue;
            }

            foreach ($associatedFiles as $index => $fileSpec) {
                $entries[] = [
                    'catalogObjectId' => $objectId,
                    'associatedFileIndex' => $index,
                    'fileSpec' => $fileSpec,
                ];
            }
        }

        return $entries;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @param list<int> $seen
     * @param array{lower: string, upper: string}|null $inheritedLimits
     * @return list<array{name: string, fileSpec: mixed}>
     */
    private function nameTreeEntries(
        mixed $value,
        array $objects,
        array $seen = [],
        ?array $inheritedLimits = null,
        int $depth = 0
    ): array
    {
        if ($depth > 20) {
            return [];
        }

        $objectId = $this->refObjectId($value);
        if ($objectId !== null) {
            $object = $this->objectForReference($value, $objects);
            if (in_array($objectId, $seen, true) || $object === null) {
                return [];
            }
            $seen[] = $objectId;
            $value = $object['value'];
        }

        $dict = $this->dict($value);
        if ($dict === null) {
            return [];
        }

        $entries = [];
        $limits = $this->nameTreeEffectiveLimits($dict, $objects, $inheritedLimits);
        $childLimits = $limits;
        $names = $this->arrayValue($this->resolveValue($dict['Names'] ?? null, $objects));
        if ($names !== null) {
            $entryLimits = $this->nameTreeLimitsMatchAnyPairKey($names, $objects, $limits)
                ? $limits
                : $inheritedLimits;
            $childLimits = $entryLimits;
            for ($index = 0, $count = count($names); $index + 1 < $count; $index += 2) {
                $name = $this->stringValue($names[$index]);
                if ($name === null || $name === '' || !$this->nameTreeNameWithinLimits($name, $entryLimits)) {
                    continue;
                }

                $entries[] = [
                    'name' => $name,
                    'fileSpec' => $names[$index + 1],
                ];
            }
        }

        $kids = $this->arrayValue($dict['Kids'] ?? null);
        if ($kids !== null) {
            foreach ($kids as $kid) {
                foreach ($this->nameTreeEntries($kid, $objects, $seen, $childLimits, $depth + 1) as $entry) {
                    $entries[] = $entry;
                }
            }
        }

        return $entries;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @param array{lower: string, upper: string}|null $inheritedLimits
     * @return array{lower: string, upper: string}|null
     */
    private function nameTreeEffectiveLimits(array $node, array $objects, ?array $inheritedLimits): ?array
    {
        $nodeLimits = $this->nameTreeNodeLimits($node, $objects);
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
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return array{lower: string, upper: string}|null
     */
    private function nameTreeNodeLimits(array $node, array $objects): ?array
    {
        $limits = $this->arrayValue($this->resolveValue($node['Limits'] ?? null, $objects));
        if ($limits === null || count($limits) < 2) {
            return null;
        }

        $lower = $this->stringValue($this->resolveValue($limits[0], $objects));
        $upper = $this->stringValue($this->resolveValue($limits[1], $objects));
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
     * @param list<mixed> $items
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @param array{lower: string, upper: string}|null $limits
     */
    private function nameTreeLimitsMatchAnyPairKey(array $items, array $objects, ?array $limits): bool
    {
        if ($limits === null || $items === []) {
            return true;
        }

        for ($index = 0, $count = count($items); $index + 1 < $count; $index += 2) {
            $name = $this->stringValue($this->resolveValue($items[$index], $objects));
            if ($name !== null && $this->nameTreeNameWithinLimits($name, $limits)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @param list<int>|null $catalogObjectIds
     * @return list<array{pageNumber: int, pageObjectId: int, associatedFileIndex: int, fileSpec: mixed}>
     */
    private function pageAssociatedFileEntries(array $objects, ?array $catalogObjectIds = null): array
    {
        $entries = [];
        foreach ($this->pageObjectIds($objects, $catalogObjectIds) as $pageIndex => $pageObjectId) {
            if (!isset($objects[$pageObjectId])) {
                continue;
            }

            $page = $this->dict($objects[$pageObjectId]['value']);
            if ($page === null) {
                continue;
            }

            $associatedFiles = $this->arrayValue($this->resolveValue($page['AF'] ?? null, $objects));
            if ($associatedFiles === null) {
                continue;
            }

            foreach ($associatedFiles as $index => $fileSpec) {
                $entries[] = [
                    'pageNumber' => $pageIndex + 1,
                    'pageObjectId' => $pageObjectId,
                    'associatedFileIndex' => $index,
                    'fileSpec' => $fileSpec,
                ];
            }
        }

        return $entries;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @param list<int>|null $catalogObjectIds
     * @return list<array{pageNumber: int, pageObjectId: int, annotationObjectId: int|null, contents: string|null, rect: list<float>, fileSpec: mixed}>
     */
    private function fileAttachmentAnnotationEntries(array $objects, ?array $catalogObjectIds = null): array
    {
        $entries = [];
        foreach ($this->pageObjectIds($objects, $catalogObjectIds) as $pageIndex => $pageObjectId) {
            if (!isset($objects[$pageObjectId])) {
                continue;
            }

            $page = $this->dict($objects[$pageObjectId]['value']);
            if ($page === null) {
                continue;
            }

            foreach ($this->annotationValues($page['Annots'] ?? null, $objects) as $annotation) {
                $dict = $this->dict($annotation['value']);
                if ($dict === null || $this->nameValue($dict['Subtype'] ?? null) !== 'FileAttachment') {
                    continue;
                }

                $entries[] = [
                    'pageNumber' => $pageIndex + 1,
                    'pageObjectId' => $pageObjectId,
                    'annotationObjectId' => $annotation['objectId'],
                    'contents' => $this->stringValue($dict['Contents'] ?? null),
                    'rect' => $this->numberArray($dict['Rect'] ?? null),
                    'fileSpec' => $dict['FS'] ?? null,
                ];
            }
        }

        return $entries;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @param list<int>|null $catalogObjectIds
     * @return list<int>
     */
    private function pageObjectIds(array $objects, ?array $catalogObjectIds = null): array
    {
        foreach ($objects as $objectId => $object) {
            if ($catalogObjectIds !== null && !in_array($objectId, $catalogObjectIds, true)) {
                continue;
            }

            $dict = $this->dict($object['value']);
            if ($dict === null || $this->nameValue($dict['Type'] ?? null) !== 'Catalog') {
                continue;
            }

            $pagesReference = $dict['Pages'] ?? null;
            $pagesId = $this->refObjectId($pagesReference);
            if ($pagesId !== null) {
                $pages = $this->collectPageObjectIds($pagesReference, $objects);
                if ($pages !== []) {
                    return $pages;
                }
            }
        }

        if ($catalogObjectIds !== null) {
            return [];
        }

        $pages = [];
        foreach ($objects as $objectId => $object) {
            $dict = $this->dict($object['value']);
            if ($dict !== null && $this->nameValue($dict['Type'] ?? null) === 'Page') {
                $pages[] = $objectId;
            }
        }

        return $pages;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @param list<int> $seen
     * @return list<int>
     */
    private function collectPageObjectIds(mixed $pageReference, array $objects, array $seen = []): array
    {
        $objectId = $this->refObjectId($pageReference);
        if ($objectId === null) {
            return [];
        }

        $object = $this->objectForReference($pageReference, $objects);
        if (in_array($objectId, $seen, true) || $object === null) {
            return [];
        }

        $seen[] = $objectId;
        $dict = $this->dict($object['value']);
        if ($dict === null) {
            return [];
        }

        $type = $this->nameValue($dict['Type'] ?? null);
        if ($type === 'Page') {
            return [$objectId];
        }
        if ($type !== 'Pages') {
            return [];
        }

        $pages = [];
        $kids = $this->arrayValue($dict['Kids'] ?? null) ?? [];
        foreach ($kids as $kid) {
            foreach ($this->collectPageObjectIds($kid, $objects, $seen) as $pageId) {
                $pages[] = $pageId;
            }
        }

        return $pages;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return list<array{objectId: int|null, value: mixed}>
     */
    private function annotationValues(mixed $annots, array $objects): array
    {
        $annots = $this->resolveValue($annots, $objects);
        $values = $this->arrayValue($annots);
        if ($values === null) {
            return [];
        }

        $annotations = [];
        foreach ($values as $value) {
            $objectId = $this->refObjectId($value);
            $object = $this->objectForReference($value, $objects);
            $annotations[] = [
                'objectId' => $objectId,
                'value' => $object !== null ? $object['value'] : $value,
            ];
        }

        return $annotations;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @param array<string, mixed> $context
     * @return array<string, mixed>|null
     */
    private function attachmentFromFileSpecValue(
        mixed $fileSpecValue,
        array $objects,
        string $source,
        array $context,
        ?array $encryptionPolicy = null
    ): ?array {
        $fileSpecObjectId = $this->refObjectId($fileSpecValue);
        $fileSpec = $this->dict($this->resolveValue($fileSpecValue, $objects));
        if ($fileSpec === null) {
            return null;
        }

        $streamReference = $this->embeddedFileStreamReference(
            $fileSpec['EF'] ?? null,
            $objects,
            array_key_exists('UF', $fileSpec)
        );
        if ($streamReference === null || !isset($objects[$streamReference['objectId']])) {
            return null;
        }

        $streamObjectId = $streamReference['objectId'];
        $streamObject = $objects[$streamObjectId];
        if ($streamObject['stream'] === null) {
            return null;
        }

        $embeddedFileStreamsEncrypted = $this->attachmentPolicySuppressesEmbeddedPayload($encryptionPolicy);
        $bytes = $embeddedFileStreamsEncrypted ? null : $this->decodedStreamBytes($streamObject, $objects);
        if (!$embeddedFileStreamsEncrypted && $bytes === null) {
            return null;
        }

        $streamDict = $this->dict($streamObject['value']) ?? [];
        $params = $this->dict($this->resolveValue($streamDict['Params'] ?? null, $objects)) ?? [];
        $nameKey = isset($context['name_key']) && is_string($context['name_key']) ? $context['name_key'] : null;
        [$filename, $filenameSource] = $this->filenameWithSource($fileSpec, $objects, $nameKey, $streamObjectId);
        $filters = $this->filterNames($streamDict['Filter'] ?? null, $objects);
        $declaredSize = $this->intValue($this->resolveValue($params['Size'] ?? null, $objects));
        $checksum = $this->stringBytesHex($this->resolveValue($params['CheckSum'] ?? null, $objects));
        $relationship = $this->nameValue($this->resolveValue($fileSpec['AFRelationship'] ?? null, $objects));

        $attachment = [
            ...$context,
            'source' => $source,
            'file_spec_object_id' => $fileSpecObjectId,
            'stream_object_id' => $streamObjectId,
            'ef_key' => $streamReference['key'],
            'filename' => $filename,
            'filename_source' => $filenameSource,
            'description' => $this->stringValue($this->resolveValue($fileSpec['Desc'] ?? null, $objects)),
            'content_type' => $this->nameValue($this->resolveValue($streamDict['Subtype'] ?? null, $objects)),
            'declared_size' => $declaredSize,
            'checksum_hex' => $checksum,
            'created_at' => $this->stringValue($this->resolveValue($params['CreationDate'] ?? null, $objects)),
            'modified_at' => $this->stringValue($this->resolveValue($params['ModDate'] ?? null, $objects)),
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
        ];

        if ($bytes !== null) {
            $attachment['byte_length'] = strlen($bytes);
            $attachment['sha256'] = hash('sha256', $bytes);
            $attachment['bytes'] = $bytes;
        }
        if ($filters !== []) {
            $attachment['filters'] = $filters;
        }
        if ($declaredSize !== null && $bytes !== null) {
            $attachment['declared_size_matches'] = $declaredSize === strlen($bytes);
        }
        if ($checksum !== null && $bytes !== null) {
            $computedChecksum = md5($bytes);
            $attachment['computed_checksum_hex'] = $computedChecksum;
            $attachment['checksum_matches'] = strtolower($checksum) === $computedChecksum;
        }
        if ($relationship !== null && $relationship !== '') {
            $attachment['relationship'] = $relationship;
            $attachment['relationship_role'] = self::ASSOCIATED_FILE_RELATIONSHIP_ROLES[$relationship] ?? 'unrecognized';
            $attachment['relationship_status'] = array_key_exists($relationship, self::ASSOCIATED_FILE_RELATIONSHIP_ROLES)
                ? 'standard_pdf_associated_file_relationship'
                : 'unrecognized_pdf_associated_file_relationship';
        }

        $relatedFiles = $this->relatedFileRows($fileSpec['RF'] ?? null, $objects);
        if ($relatedFiles !== []) {
            $attachment['related_file_count'] = count($relatedFiles);
            $attachment['related_files'] = $relatedFiles;
        }

        return $encryptionPolicy === null
            ? $attachment
            : $this->redactEncryptedAttachmentRow($attachment, $encryptionPolicy);
    }

    /**
     * Attachment preflight is a review path. When a trailer /Encrypt
     * dictionary says FileSpec strings or embedded-file streams use an
     * encrypted crypt filter, expose only object identity and policy metadata.
     *
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return array<string, mixed>|null
     */
    private function attachmentEncryptionPolicy(string $pdfBytes, array $objects): ?array
    {
        $dictionary = $this->selectedEncryptionDictionary($pdfBytes, $objects);
        if ($dictionary === null) {
            return null;
        }

        $version = $this->intValue($this->resolveValue($dictionary['V'] ?? null, $objects));
        $cryptFilters = $this->cryptFilterMetadata($dictionary['CF'] ?? null, $objects);
        $streamFilter = $this->nameOrStringValue($dictionary['StmF'] ?? null, $objects);
        $stringFilter = $this->nameOrStringValue($dictionary['StrF'] ?? null, $objects);
        $embeddedFileFilter = $this->nameOrStringValue($dictionary['EFF'] ?? null, $objects);
        $streamFilterDefaulted = false;
        $stringFilterDefaulted = false;
        $embeddedFileFilterDefaulted = false;

        if (in_array($version, [4, 5], true)) {
            if ($streamFilter === null || $streamFilter === '') {
                $streamFilter = 'Identity';
                $streamFilterDefaulted = true;
            }
            if ($stringFilter === null || $stringFilter === '') {
                $stringFilter = 'Identity';
                $stringFilterDefaulted = true;
            }
            if ($embeddedFileFilter === null || $embeddedFileFilter === '') {
                $embeddedFileFilter = $streamFilter;
                $embeddedFileFilterDefaulted = true;
            }
        } else {
            $streamFilter ??= 'Standard';
            $stringFilter ??= 'Standard';
            $embeddedFileFilter ??= $streamFilter;
        }

        $streamFilterStatus = $this->attachmentCryptFilterStatus($streamFilter, $cryptFilters, $version);
        $stringFilterStatus = $this->attachmentCryptFilterStatus($stringFilter, $cryptFilters, $version);
        $embeddedFileFilterStatus = $this->attachmentCryptFilterStatus($embeddedFileFilter, $cryptFilters, $version);
        $stringsEncrypted = $stringFilterStatus !== 'identity_crypt_filter';
        $embeddedStreamsEncrypted = $embeddedFileFilterStatus !== 'identity_crypt_filter';

        $policy = [
            'source' => 'encrypted_attachment_preflight',
            'encrypted_document' => true,
            'decryption_performed' => false,
            'stream_filter' => $streamFilter,
            'stream_filter_status' => $streamFilterStatus,
            'string_filter' => $stringFilter,
            'string_filter_status' => $stringFilterStatus,
            'embedded_file_filter' => $embeddedFileFilter,
            'embedded_file_filter_status' => $embeddedFileFilterStatus,
            'file_spec_strings_policy' => $stringsEncrypted
                ? 'suppressed_encrypted_strings'
                : 'preserved_identity_crypt_filter',
            'embedded_file_stream_policy' => $embeddedStreamsEncrypted
                ? 'suppressed_encrypted_embedded_file_streams'
                : 'preserved_identity_crypt_filter',
            'payload_hash_available' => !$embeddedStreamsEncrypted,
            'payload_content_included' => false,
            'raw_encrypted_bytes_exposed' => false,
            'executes_decryption' => false,
        ];

        if ($version !== null) {
            $policy['version'] = $version;
        }
        if ($streamFilterDefaulted) {
            $policy['stream_filter_defaulted'] = true;
        }
        if ($stringFilterDefaulted) {
            $policy['string_filter_defaulted'] = true;
        }
        if ($embeddedFileFilterDefaulted) {
            $policy['embedded_file_filter_defaulted_from_stream_filter'] = true;
        }
        if ($cryptFilters !== []) {
            $policy['crypt_filters'] = $cryptFilters;
        }

        return $policy;
    }

    /**
     * @param array<string, mixed>|null $policy
     */
    private function attachmentPolicySuppressesEmbeddedPayload(?array $policy): bool
    {
        return ($policy['embedded_file_stream_policy'] ?? null) === 'suppressed_encrypted_embedded_file_streams';
    }

    /**
     * @param array<string, mixed> $attachment
     * @param array<string, mixed> $policy
     * @return array<string, mixed>
     */
    private function redactEncryptedAttachmentRow(array $attachment, array $policy): array
    {
        $stringsEncrypted = ($policy['file_spec_strings_policy'] ?? null) === 'suppressed_encrypted_strings';
        $payloadEncrypted = $this->attachmentPolicySuppressesEmbeddedPayload($policy);

        if ($stringsEncrypted) {
            foreach ([
                'name_key',
                'filename',
                'filename_source',
                'description',
                'annotation_contents',
            ] as $key) {
                unset($attachment[$key]);
            }
        }

        if ($payloadEncrypted) {
            foreach ([
                'content_type',
                'declared_size',
                'declared_size_matches',
                'byte_length',
                'sha256',
                'checksum_hex',
                'computed_checksum_hex',
                'checksum_matches',
                'created_at',
                'modified_at',
                'filters',
                'bytes',
            ] as $key) {
                unset($attachment[$key]);
            }
            $attachment['encrypted_payload_suppressed'] = true;
        } else {
            unset($attachment['bytes']);
            $attachment['encrypted_payload_suppressed'] = false;
        }

        if (isset($attachment['related_files']) && is_array($attachment['related_files'])) {
            $attachment['related_files'] = $this->redactEncryptedRelatedFileRows(
                $attachment['related_files'],
                $policy
            );
        }

        $attachment['encryption_policy'] = $policy;
        $attachment['raw_encrypted_bytes_exposed'] = false;
        $attachment['executes_decryption'] = false;

        return $attachment;
    }

    /**
     * @param list<array<string, mixed>> $relatedFiles
     * @param array<string, mixed> $policy
     * @return list<array<string, mixed>>
     */
    private function redactEncryptedRelatedFileRows(array $relatedFiles, array $policy): array
    {
        $stringsEncrypted = ($policy['file_spec_strings_policy'] ?? null) === 'suppressed_encrypted_strings';
        $payloadEncrypted = $this->attachmentPolicySuppressesEmbeddedPayload($policy);
        $rows = [];
        foreach ($relatedFiles as $row) {
            if ($stringsEncrypted) {
                unset($row['related_filename'], $row['related_filename_source']);
            }
            if ($payloadEncrypted) {
                foreach ([
                    'content_type',
                    'byte_length',
                    'sha256',
                    'declared_size',
                    'declared_size_matches',
                    'checksum_hex',
                    'computed_checksum_hex',
                    'checksum_matches',
                    'created_at',
                    'modified_at',
                    'filters',
                ] as $key) {
                    unset($row[$key]);
                }
                $row['encrypted_payload_suppressed'] = true;
            } else {
                $row['encrypted_payload_suppressed'] = false;
            }

            $row['encryption_policy'] = $policy;
            $row['raw_encrypted_bytes_exposed'] = false;
            $row['executes_decryption'] = false;
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return array<string, mixed>|null
     */
    private function selectedEncryptionDictionary(string $pdfBytes, array $objects): ?array
    {
        $definitions = $this->directObjectDefinitions($this->bytesThroughTerminalEof($pdfBytes));
        $trailer = $this->selectedTrailerDictionary($pdfBytes, $definitions);
        if ($trailer === null || !array_key_exists('Encrypt', $trailer)) {
            return null;
        }

        return $this->dict($this->resolveValue($trailer['Encrypt'], $objects));
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<string, mixed>|null
     */
    private function selectedTrailerDictionary(string $pdfBytes, array $definitions): ?array
    {
        $pdfBytes = $this->bytesThroughTerminalEof($pdfBytes);
        $offset = $this->latestStartxrefOffset($pdfBytes);
        if ($offset !== null) {
            $table = $this->xrefTableSectionAt($pdfBytes, $offset);
            if ($table !== null) {
                return $table['trailer'];
            }

            $stream = $this->xrefStreamSectionAt($offset, $definitions);
            if ($stream !== null) {
                return $stream['dictionary'];
            }
        }

        $trailerOffset = strrpos($pdfBytes, 'trailer');
        if ($trailerOffset === false) {
            return null;
        }

        $dictionaryOffset = strpos($pdfBytes, '<<', $trailerOffset);
        if ($dictionaryOffset === false) {
            return null;
        }

        $index = $dictionaryOffset;

        return $this->dict($this->parseValue($pdfBytes, $index));
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return array<string, array<string, mixed>>
     */
    private function cryptFilterMetadata(mixed $cryptFiltersValue, array $objects): array
    {
        $cryptFilters = $this->dict($this->resolveValue($cryptFiltersValue, $objects));
        if ($cryptFilters === null) {
            return [];
        }

        $metadata = [];
        foreach ($cryptFilters as $name => $value) {
            if (!is_string($name)) {
                continue;
            }

            $dictionary = $this->dict($this->resolveValue($value, $objects));
            if ($dictionary === null) {
                continue;
            }

            $entry = [];
            $method = $this->nameOrStringValue($dictionary['CFM'] ?? null, $objects);
            if ($method !== null && $method !== '') {
                $entry['method'] = $method;
            }

            $authEvent = $this->nameOrStringValue($dictionary['AuthEvent'] ?? null, $objects);
            if ($authEvent !== null && $authEvent !== '') {
                $entry['auth_event'] = $authEvent;
            }

            $length = $this->intValue($this->resolveValue($dictionary['Length'] ?? null, $objects));
            if ($length !== null) {
                $entry['length'] = $length;
            }

            $metadata[$name] = $entry;
        }

        return $metadata;
    }

    /**
     * @param array<string, array<string, mixed>> $cryptFilters
     */
    private function attachmentCryptFilterStatus(?string $filterName, array $cryptFilters, ?int $version): string
    {
        if ($filterName === 'Identity') {
            return 'identity_crypt_filter';
        }

        if ($filterName === null || $filterName === '') {
            return in_array($version, [4, 5], true)
                ? 'undeclared_crypt_filter_fail_closed'
                : 'legacy_standard_encryption';
        }

        $filter = is_array($cryptFilters[$filterName] ?? null) ? $cryptFilters[$filterName] : null;
        if ($filter === null) {
            return $filterName === 'Standard'
                ? 'legacy_standard_encryption'
                : 'missing_declared_crypt_filter';
        }

        $method = is_string($filter['method'] ?? null) ? $filter['method'] : null;
        if ($method === 'Identity' || $method === 'None') {
            return 'identity_crypt_filter';
        }

        if (in_array($method, ['V2', 'AESV2', 'AESV3'], true)) {
            return 'encrypted_crypt_filter';
        }

        return ($method === null || $method === '')
            ? 'unknown_crypt_filter_method_fail_closed'
            : 'unsupported_crypt_filter_method_fail_closed';
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     */
    private function nameOrStringValue(mixed $value, array $objects): ?string
    {
        $resolved = $this->resolveValue($value, $objects);

        return $this->nameValue($resolved) ?? $this->stringValue($resolved);
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return list<array<string, mixed>>
     */
    private function relatedFileRows(mixed $relatedFilesValue, array $objects): array
    {
        $relatedFiles = $this->dict($this->resolveValue($relatedFilesValue, $objects));
        if ($relatedFiles === null) {
            return [];
        }

        $rows = [];
        foreach ($relatedFiles as $rfKey => $streamValues) {
            if (!in_array($rfKey, ['F', 'UF', 'DOS', 'Unix', 'Mac'], true)) {
                continue;
            }

            $items = $this->arrayValue($this->resolveValue($streamValues, $objects));
            if ($items === null) {
                $items = [$streamValues];
            }

            $relatedFileIndex = 0;
            for ($index = 0, $count = count($items); $index < $count; $index++) {
                $relatedFilename = $this->stringValue($this->resolveValue($items[$index], $objects));
                if ($relatedFilename !== null && $relatedFilename !== '' && $index + 1 < $count) {
                    $row = $this->relatedFileRowFromStreamValue(
                        $items[$index + 1],
                        $objects,
                        $rfKey,
                        $relatedFileIndex,
                        $relatedFilename
                    );
                    if ($row !== null) {
                        $rows[] = $row;
                        $relatedFileIndex++;
                        $index++;
                        continue;
                    }
                }

                $row = $this->relatedFileRowFromStreamValue($items[$index], $objects, $rfKey, $relatedFileIndex);
                if ($row !== null) {
                    $rows[] = $row;
                    $relatedFileIndex++;
                }
            }
        }

        return $rows;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return array<string, mixed>|null
     */
    private function relatedFileRowFromStreamValue(
        mixed $streamValue,
        array $objects,
        string $rfKey,
        int $relatedFileIndex,
        ?string $relatedFilename = null
    ): ?array
    {
        $streamObjectId = $this->refObjectId($streamValue);
        $streamObject = $this->objectForReference($streamValue, $objects);
        if ($streamObjectId === null || $streamObject === null) {
            return null;
        }

        if ($streamObject['stream'] === null) {
            return null;
        }

        $bytes = $this->decodedStreamBytes($streamObject, $objects);
        if ($bytes === null) {
            return null;
        }

        $streamDict = $this->dict($streamObject['value']) ?? [];
        $params = $this->dict($this->resolveValue($streamDict['Params'] ?? null, $objects)) ?? [];
        $filters = $this->filterNames($streamDict['Filter'] ?? null, $objects);
        $declaredSize = $this->intValue($this->resolveValue($params['Size'] ?? null, $objects));
        $checksum = $this->stringBytesHex($this->resolveValue($params['CheckSum'] ?? null, $objects));

        $row = [
            'source' => 'filespec_related_files',
            'rf_key' => $rfKey,
            'related_file_index' => $relatedFileIndex,
            'stream_object_id' => $streamObjectId,
            'content_type' => $this->nameValue($this->resolveValue($streamDict['Subtype'] ?? null, $objects)),
            'byte_length' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
            'created_at' => $this->stringValue($this->resolveValue($params['CreationDate'] ?? null, $objects)),
            'modified_at' => $this->stringValue($this->resolveValue($params['ModDate'] ?? null, $objects)),
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
        ];

        if ($relatedFilename !== null && $relatedFilename !== '') {
            $row['related_filename'] = $relatedFilename;
            $row['related_filename_source'] = 'rf_name_pair';
        }
        if ($filters !== []) {
            $row['filters'] = $filters;
        }
        if ($declaredSize !== null) {
            $row['declared_size'] = $declaredSize;
            $row['declared_size_matches'] = $declaredSize === strlen($bytes);
        }
        if ($checksum !== null) {
            $computedChecksum = md5($bytes);
            $row['checksum_hex'] = $checksum;
            $row['computed_checksum_hex'] = $computedChecksum;
            $row['checksum_matches'] = strtolower($checksum) === $computedChecksum;
        }

        return $row;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return array{objectId: int, key: string}|null
     */
    private function embeddedFileStreamReference(mixed $efValue, array $objects, bool $preferUnicode): ?array
    {
        $ef = $this->dict($this->resolveValue($efValue, $objects));
        if ($ef === null) {
            return null;
        }

        $keys = $preferUnicode ? ['UF', 'F', 'DOS', 'Unix', 'Mac'] : ['F', 'UF', 'DOS', 'Unix', 'Mac'];
        foreach ($keys as $key) {
            $objectId = $this->refObjectId($ef[$key] ?? null);
            if ($objectId !== null && $this->objectForReference($ef[$key] ?? null, $objects) !== null) {
                return ['objectId' => $objectId, 'key' => $key];
            }
        }

        foreach ($ef as $key => $value) {
            $objectId = $this->refObjectId($value);
            if ($objectId !== null && $this->objectForReference($value, $objects) !== null) {
                return ['objectId' => $objectId, 'key' => is_string($key) ? $key : 'unknown'];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $fileSpec
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return array{0: string, 1: string}
     */
    private function filenameWithSource(array $fileSpec, array $objects, ?string $nameKey, int $streamObjectId): array
    {
        foreach (['UF', 'F', 'DOS', 'Unix', 'Mac'] as $key) {
            $filename = $this->stringValue($this->resolveValue($fileSpec[$key] ?? null, $objects));
            if ($filename !== null && $filename !== '') {
                return [$filename, $key];
            }
        }

        if ($nameKey !== null && $nameKey !== '') {
            return [$nameKey, 'name_tree_key'];
        }

        return ['attachment-' . $streamObjectId, 'generated'];
    }

    /**
     * @param array{generation: int, body: string, value: mixed, stream: string|null} $streamObject
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     */
    private function decodedStreamBytes(array $streamObject, array $objects): ?string
    {
        if ($streamObject['stream'] === null) {
            return null;
        }

        $bytes = $streamObject['stream'];
        $dict = $this->dict($streamObject['value']) ?? [];
        foreach ($this->filterNames($dict['Filter'] ?? null, $objects) as $filter) {
            $decoded = match ($filter) {
                'FlateDecode', 'Fl' => $this->decodeFlateStream($bytes),
                'ASCIIHexDecode', 'AHx' => $this->decodeAsciiHexStream($bytes),
                'RunLengthDecode', 'RL' => $this->decodeRunLengthStream($bytes),
                default => null,
            };

            if ($decoded === null) {
                return null;
            }
            $bytes = $decoded;
        }

        return $bytes;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return list<string>
     */
    private function filterNames(mixed $filterValue, array $objects): array
    {
        $filterValue = $this->resolveValue($filterValue, $objects);
        $name = $this->nameValue($filterValue);
        if ($name !== null) {
            return [$name];
        }

        $array = $this->arrayValue($filterValue);
        if ($array === null) {
            return [];
        }

        $filters = [];
        foreach ($array as $value) {
            $filter = $this->nameValue($this->resolveValue($value, $objects));
            if ($filter !== null) {
                $filters[] = $filter;
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

    private function decodeRunLengthStream(string $bytes): ?string
    {
        $out = '';
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset++) {
            $control = ord($bytes[$offset]);
            if ($control === 128) {
                return $out;
            }

            if ($control <= 127) {
                $copyLength = $control + 1;
                if ($offset + $copyLength >= $length) {
                    return null;
                }
                $out .= substr($bytes, $offset + 1, $copyLength);
                $offset += $copyLength;
                continue;
            }

            if ($offset + 1 >= $length) {
                return null;
            }
            $out .= str_repeat($bytes[$offset + 1], 257 - $control);
            $offset++;
        }

        return null;
    }

    /**
     * @return array<int, array{generation: int, body: string, value: mixed, stream: string|null}>
     */
    private function pdfObjects(string $pdfBytes): array
    {
        $pdfBytes = $this->bytesThroughTerminalEof($pdfBytes);
        $definitions = $this->directObjectDefinitions($pdfBytes);
        if ($definitions === []) {
            return [];
        }

        $xrefEntries = $this->xrefEntriesFromLatestStartxref($pdfBytes, $definitions);
        $objects = [];
        foreach ($definitions as $objectNumber => $candidates) {
            $definition = $this->selectedDirectObjectDefinition($candidates, $xrefEntries[$objectNumber] ?? null);
            if ($definition === null) {
                continue;
            }

            $objects[$objectNumber] = $this->parsedObjectFromDefinition($definition);
        }
        if ($xrefEntries !== []) {
            $objects = $this->withCompressedObjectStreamObjects($objects, $xrefEntries);
            $objects = $this->withTrailerDirectGenerationObjects($pdfBytes, $objects, $definitions);
            $objects = $this->withReferencedDirectGenerationObjects($objects, $definitions, $xrefEntries);
            $objects = $this->withCompressedObjectStreamObjects($objects, $xrefEntries);
        }
        ksort($objects, SORT_NUMERIC);

        return $objects;
    }

    /**
     * @param array{generation: int, body: string} $definition
     * @return array{generation: int, body: string, value: mixed, stream: string|null}
     */
    private function parsedObjectFromDefinition(array $definition): array
    {
        $body = $definition['body'];
        $index = 0;
        $value = $this->parseValue($body, $index);
        $stream = $this->streamBytesFromBody($body, $index, $value);

        return [
            'generation' => $definition['generation'],
            'body' => $body,
            'value' => $value,
            'stream' => $stream,
        ];
    }

    /**
     * Latest trailers may reference a nonzero-generation catalog even when the
     * latest xref row has a damaged offset. Keep that root generation
     * available so its attachment graph can be followed deliberately.
     *
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<int, array{generation: int, body: string, value: mixed, stream: string|null}>
     */
    private function withTrailerDirectGenerationObjects(string $pdfBytes, array $objects, array $definitions): array
    {
        $reference = $this->latestTrailerRootReference($pdfBytes, $definitions);
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

        $objects[$reference['objectNumber']] = $this->parsedObjectFromDefinition($definition);
        ksort($objects, SORT_NUMERIC);

        return $objects;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @param array<int, array{type: int, generation?: int, offset?: int, objectStream?: int, index?: int, indexIsExplicit?: bool}> $xrefEntries
     * @return array<int, array{generation: int, body: string, value: mixed, stream: string|null}>
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

                krsort($generations, SORT_NUMERIC);
                foreach (array_keys($generations) as $generation) {
                    $generation = (int) $generation;
                    if (($repaired[$objectNumber]['generation'] ?? null) === $generation) {
                        continue;
                    }

                    $definition = $this->directObjectDefinitionForGeneration($definitions[$objectNumber] ?? [], $generation);
                    if ($definition === null) {
                        continue;
                    }

                    $repaired[$objectNumber] = $this->parsedObjectFromDefinition($definition);
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
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return array<int, array<int, true>>
     */
    private function nonZeroGenerationObjectReferences(array $objects): array
    {
        $references = [];
        foreach ($objects as $object) {
            $this->collectNonZeroGenerationObjectReferences($object['value'], $references);
        }

        return $references;
    }

    /**
     * @param array<int, array<int, true>> $references
     */
    private function collectNonZeroGenerationObjectReferences(mixed $value, array &$references): void
    {
        if (!is_array($value)) {
            return;
        }
        if (($value['__kind'] ?? null) === 'ref') {
            $generation = (int) ($value['generation'] ?? 0);
            if ($generation > 0) {
                $references[(int) $value['object']][$generation] = true;
            }

            return;
        }
        if (isset($value['__kind'])) {
            return;
        }

        foreach ($value as $child) {
            $this->collectNonZeroGenerationObjectReferences($child, $references);
        }
    }

    /**
     * PDF 1.5 object streams carry ordinary non-stream objects. Lightweight
     * attachment preflight needs the selected FileSpec dictionaries but must
     * keep stream payloads attached only to direct EmbeddedFile stream objects.
     *
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @param array<int, array{type: int, generation?: int, offset?: int, objectStream?: int, index?: int, indexIsExplicit?: bool}> $xrefEntries
     * @return array<int, array{generation: int, body: string, value: mixed, stream: string|null}>
     */
    private function withCompressedObjectStreamObjects(array $objects, array $xrefEntries): array
    {
        $expanded = $objects;
        for ($pass = 0; $pass < 4; $pass++) {
            $added = false;
            foreach ($xrefEntries as $objectNumber => $entry) {
                if (($entry['type'] ?? null) !== 2 || isset($expanded[$objectNumber])) {
                    continue;
                }

                $body = $this->objectStreamMemberBody($expanded, $entry, (int) $objectNumber);
                if ($body === null) {
                    continue;
                }

                $index = 0;
                $value = $this->parseValue($body, $index);
                if ($value === null) {
                    continue;
                }

                if ($this->streamBytesFromBody($body, $index, $value) !== null) {
                    continue;
                }

                $expanded[$objectNumber] = [
                    'generation' => 0,
                    'body' => $body,
                    'value' => $value,
                    'stream' => null,
                ];
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
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @param array{type: int, objectStream?: int, index?: int, indexIsExplicit?: bool} $xrefEntry
     */
    private function objectStreamMemberBody(array $objects, array $xrefEntry, int $requestedObjectNumber): ?string
    {
        $objectStreamId = $xrefEntry['objectStream'] ?? null;
        if (!is_int($objectStreamId) || !isset($objects[$objectStreamId])) {
            return null;
        }

        $objectStream = $objects[$objectStreamId];
        $dict = $this->dict($objectStream['value']);
        if ($dict === null || $this->nameValue($dict['Type'] ?? null) !== 'ObjStm') {
            return null;
        }

        $declaredCount = $this->intValue($this->resolveValue($dict['N'] ?? null, $objects));
        $firstOffset = $this->intValue($this->resolveValue($dict['First'] ?? null, $objects));
        if ($declaredCount === null || $declaredCount < 1 || $firstOffset === null || $firstOffset < 0) {
            return null;
        }

        $decoded = $this->decodedStreamBytes($objectStream, $objects);
        if ($decoded === null || $firstOffset > strlen($decoded)) {
            return null;
        }

        $members = $this->objectStreamHeaderMembers(substr($decoded, 0, $firstOffset), $declaredCount);
        if ($members === []) {
            return null;
        }

        $memberIndex = $this->objectStreamSelectedMemberIndex($members, $xrefEntry, $requestedObjectNumber);
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

        return substr($data, $start, $end - $start);
    }

    /**
     * @return list<array{objectNumber: int, offset: int}>
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
                'objectNumber' => (int) $tokens[$index],
                'offset' => (int) $tokens[$index + 1],
            ];
        }

        return $members;
    }

    /**
     * @param list<array{objectNumber: int, offset: int}> $members
     * @param array{type: int, index?: int, indexIsExplicit?: bool} $xrefEntry
     */
    private function objectStreamSelectedMemberIndex(array $members, array $xrefEntry, int $requestedObjectNumber): ?int
    {
        $requestedIndex = $xrefEntry['index'] ?? null;
        if (is_int($requestedIndex) && ($xrefEntry['indexIsExplicit'] ?? true) === true) {
            if (!isset($members[$requestedIndex]) || $members[$requestedIndex]['objectNumber'] !== $requestedObjectNumber) {
                return null;
            }

            return $requestedIndex;
        }

        foreach ($members as $index => $member) {
            if ($member['objectNumber'] === $requestedObjectNumber) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return array<int, list<array{generation: int, offset: int, body: string}>>
     */
    private function directObjectDefinitions(string $pdfBytes): array
    {
        if (!preg_match_all('/(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $definitions = [];
        foreach ($matches as $match) {
            $definitions[(int) $match[1][0]][] = [
                'generation' => (int) $match[2][0],
                'offset' => $match[0][1],
                'body' => $match[3][0],
            ];
        }

        ksort($definitions, SORT_NUMERIC);

        return $definitions;
    }

    /**
     * @param list<array{generation: int, offset: int, body: string}> $definitions
     * @param array{type: int, generation?: int, offset?: int}|null $xrefEntry
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function selectedDirectObjectDefinition(array $definitions, ?array $xrefEntry): ?array
    {
        if ($xrefEntry === null) {
            return $this->latestDirectObjectDefinition($definitions);
        }

        if (($xrefEntry['type'] ?? 1) !== 1) {
            return null;
        }

        $offset = $xrefEntry['offset'] ?? null;
        if (is_int($offset)) {
            foreach ($definitions as $definition) {
                if ($definition['offset'] === $offset) {
                    return $definition;
                }
            }

            return null;
        }

        $generation = $xrefEntry['generation'] ?? null;
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
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array{objectNumber: int, generation: int}|null
     */
    private function latestTrailerRootReference(string $pdfBytes, array $definitions): ?array
    {
        $offset = $this->latestStartxrefOffset($pdfBytes);
        if ($offset === null) {
            return null;
        }

        $table = $this->xrefTableSectionAt($pdfBytes, $offset);
        if ($table !== null) {
            return $this->refObjectReference($table['trailer']['Root'] ?? null);
        }

        $stream = $this->xrefStreamSectionAt($offset, $definitions);
        if ($stream !== null) {
            return $this->refObjectReference($stream['dictionary']['Root'] ?? null);
        }

        return null;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<int, array{type: int, generation?: int, offset?: int}>
     */
    private function xrefEntriesFromLatestStartxref(string $pdfBytes, array $definitions): array
    {
        $offset = $this->latestStartxrefOffset($pdfBytes);
        if ($offset === null) {
            return [];
        }

        $entries = $this->xrefEntriesAtOffset($pdfBytes, $offset, $definitions);
        ksort($entries, SORT_NUMERIC);

        return $entries;
    }

    private function latestStartxrefOffset(string $pdfBytes): ?int
    {
        if (preg_match_all('/\bstartxref\s+(\d+)/s', $pdfBytes, $matches, PREG_SET_ORDER) < 1) {
            return null;
        }

        $last = end($matches);
        if (!is_array($last)) {
            return null;
        }

        return (int) $last[1];
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @param array<int, true> $seenOffsets
     * @return array<int, array{type: int, generation?: int, offset?: int}>
     */
    private function xrefEntriesAtOffset(string $pdfBytes, int $offset, array $definitions, array $seenOffsets = []): array
    {
        if ($offset < 0 || isset($seenOffsets[$offset])) {
            return [];
        }
        $seenOffsets[$offset] = true;

        $table = $this->xrefTableSectionAt($pdfBytes, $offset);
        if ($table !== null) {
            $entries = $table['entries'];
            $previousOffset = $this->intValue($table['trailer']['Prev'] ?? null);
            if ($previousOffset !== null) {
                foreach ($this->xrefEntriesAtOffset($pdfBytes, $previousOffset, $definitions, $seenOffsets) as $objectNumber => $entry) {
                    $entries[$objectNumber] ??= $entry;
                }
            }

            return $entries;
        }

        $stream = $this->xrefStreamSectionAt($offset, $definitions);
        if ($stream === null) {
            return [];
        }

        $entries = $this->xrefStreamEntriesFromSection($stream);
        $previousOffset = $this->intValue($stream['dictionary']['Prev'] ?? null);
        if ($previousOffset !== null) {
            foreach ($this->xrefEntriesAtOffset($pdfBytes, $previousOffset, $definitions, $seenOffsets) as $objectNumber => $entry) {
                $entries[$objectNumber] ??= $entry;
            }
        }

        return $entries;
    }

    /**
     * @return array{entries: array<int, array{type: int, generation: int, offset: int}>, trailer: array<string, mixed>}|null
     */
    private function xrefTableSectionAt(string $pdfBytes, int $offset): ?array
    {
        $offset = $this->skipWhitespaceOffset($pdfBytes, $offset);
        if (substr($pdfBytes, $offset, 4) !== 'xref') {
            return null;
        }

        $sectionBodyOffset = $offset + 4;
        $trailerOffset = strpos($pdfBytes, 'trailer', $sectionBodyOffset);
        if ($trailerOffset === false) {
            return null;
        }

        $dictionaryOffset = strpos($pdfBytes, '<<', $trailerOffset);
        if ($dictionaryOffset === false) {
            return null;
        }

        $index = $dictionaryOffset;
        $trailer = $this->dict($this->parseValue($pdfBytes, $index)) ?? [];

        return [
            'entries' => $this->xrefTableRows(substr($pdfBytes, $sectionBodyOffset, $trailerOffset - $sectionBodyOffset)),
            'trailer' => $trailer,
        ];
    }

    /**
     * @return array<int, array{type: int, generation: int, offset: int}>
     */
    private function xrefTableRows(string $sectionBody): array
    {
        $entries = [];
        $lines = preg_split('/\r\n|\r|\n/', $sectionBody);
        if (!is_array($lines)) {
            return [];
        }

        $lineCount = count($lines);
        for ($lineIndex = 0; $lineIndex < $lineCount; $lineIndex++) {
            $line = trim($lines[$lineIndex]);
            if (preg_match('/^(\d+)\s+(\d+)$/', $line, $section) !== 1) {
                continue;
            }

            $firstObject = (int) $section[1];
            $rowCount = (int) $section[2];
            $rowIndex = 0;
            while ($rowIndex < $rowCount && ++$lineIndex < $lineCount) {
                $row = trim($lines[$lineIndex]);
                if ($row === '') {
                    continue;
                }
                if (preg_match('/^(\d{10})\s+(\d{5})\s+([nf])\b/', $row, $match) !== 1) {
                    continue;
                }

                $entries[$firstObject + $rowIndex] = [
                    'type' => $match[3] === 'n' ? 1 : 0,
                    'generation' => (int) $match[2],
                    'offset' => (int) $match[1],
                ];
                $rowIndex++;
            }
        }

        return $entries;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array{dictionary: array<string, mixed>, stream: string}|null
     */
    private function xrefStreamSectionAt(int $offset, array $definitions): ?array
    {
        foreach ($definitions as $entries) {
            foreach ($entries as $definition) {
                if ($definition['offset'] !== $offset) {
                    continue;
                }

                $index = 0;
                $value = $this->parseValue($definition['body'], $index);
                $dictionary = $this->dict($value);
                if ($dictionary === null || $this->nameValue($dictionary['Type'] ?? null) !== 'XRef') {
                    return null;
                }

                $stream = $this->streamBytesFromBody($definition['body'], $index, $value);
                if ($stream === null) {
                    return null;
                }

                return [
                    'dictionary' => $dictionary,
                    'stream' => $stream,
                ];
            }
        }

        return null;
    }

    /**
     * @param array{dictionary: array<string, mixed>, stream: string} $section
     * @return array<int, array{type: int, generation?: int, offset?: int}>
     */
    private function xrefStreamEntriesFromSection(array $section): array
    {
        $dictionary = $section['dictionary'];
        $decoded = $section['stream'];
        foreach ($this->filterNames($dictionary['Filter'] ?? null, []) as $filter) {
            $decodedFilter = match ($filter) {
                'FlateDecode', 'Fl' => $this->decodeFlateStream($decoded),
                'ASCIIHexDecode', 'AHx' => $this->decodeAsciiHexStream($decoded),
                default => null,
            };
            if ($decodedFilter === null) {
                return [];
            }
            $decoded = $decodedFilter;
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
                $objectNumber = $range['first'] + $row;
                $type = $widths[0] === 0 ? 1 : $this->xrefStreamFieldValue($decoded, $fieldOffset, $widths[0]);
                $fieldTwo = $this->xrefStreamFieldValue($decoded, $fieldOffset, $widths[1]);
                $fieldThree = $this->xrefStreamFieldValue($decoded, $fieldOffset, $widths[2]);

                if ($type === 1) {
                    $entries[$objectNumber] = [
                        'type' => 1,
                        'offset' => $fieldTwo,
                        'generation' => $fieldThree,
                    ];
                    continue;
                }

                if ($type === 2 && $fieldTwo > 0) {
                    $entries[$objectNumber] = [
                        'type' => 2,
                        'objectStream' => $fieldTwo,
                        'index' => $fieldThree,
                        'indexIsExplicit' => $widths[2] > 0,
                    ];
                    continue;
                }

                $entries[$objectNumber] = ['type' => $type];
            }
        }

        return $entries;
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private function xrefStreamFieldWidths(mixed $value): ?array
    {
        $items = $this->arrayValue($value);
        if ($items === null || count($items) < 3) {
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
        $index = $this->arrayValue($dictionary['Index'] ?? null);
        if ($index === null || $index === []) {
            $size = $this->intValue($dictionary['Size'] ?? null);
            return [[
                'first' => 0,
                'count' => $size === null ? $decodedEntryCount : min($size, $decodedEntryCount),
            ]];
        }

        $ranges = [];
        for ($offset = 0, $count = count($index); $offset + 1 < $count; $offset += 2) {
            if (!is_int($index[$offset]) || !is_int($index[$offset + 1]) || $index[$offset + 1] < 0) {
                continue;
            }

            $ranges[] = [
                'first' => $index[$offset],
                'count' => min($index[$offset + 1], max(0, $decodedEntryCount - array_sum(array_column($ranges, 'count')))),
            ];
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

    private function skipWhitespaceOffset(string $text, int $index): int
    {
        $this->skipWhitespaceAndComments($text, $index);

        return $index;
    }

    private function bytesThroughTerminalEof(string $pdfBytes): string
    {
        $eof = strrpos($pdfBytes, '%%EOF');
        if ($eof === false) {
            return $pdfBytes;
        }

        return substr($pdfBytes, 0, $eof + strlen('%%EOF'));
    }

    private function streamBytesFromBody(string $body, int $index, mixed $value): ?string
    {
        $dict = $this->dict($value);
        if ($dict === null) {
            return null;
        }

        $this->skipWhitespaceAndComments($body, $index);
        if (!str_starts_with(substr($body, $index), 'stream')) {
            return null;
        }

        $index += strlen('stream');
        if (substr($body, $index, 2) === "\r\n") {
            $index += 2;
        } elseif (($body[$index] ?? '') === "\n" || ($body[$index] ?? '') === "\r") {
            $index++;
        }

        $end = strpos($body, 'endstream', $index);
        if ($end === false) {
            return null;
        }

        $stream = substr($body, $index, $end - $index);
        $length = $this->intValue($dict['Length'] ?? null);
        if ($length !== null && $length >= 0 && $length <= strlen($stream)) {
            return substr($stream, 0, $length);
        }

        return preg_replace("/\r\n$|\n$|\r$/", '', $stream) ?? $stream;
    }

    private function parseValue(string $text, int &$index): mixed
    {
        $this->skipWhitespaceAndComments($text, $index);
        $length = strlen($text);
        if ($index >= $length) {
            return null;
        }

        $char = $text[$index];
        if (substr($text, $index, 2) === '<<') {
            return $this->parseDictionary($text, $index);
        }
        if ($char === '[') {
            return $this->parseArray($text, $index);
        }
        if ($char === '(') {
            return $this->stringToken($this->parseLiteralStringBytes($text, $index));
        }
        if ($char === '<') {
            return $this->stringToken($this->parseHexStringBytes($text, $index));
        }
        if ($char === '/') {
            return [
                '__kind' => 'name',
                'value' => $this->parseName($text, $index),
            ];
        }

        return $this->parseBareTokenValue($text, $index);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseDictionary(string $text, int &$index): array
    {
        $index += 2;
        $dict = [];
        while ($index < strlen($text)) {
            $this->skipWhitespaceAndComments($text, $index);
            if (substr($text, $index, 2) === '>>') {
                $index += 2;
                break;
            }
            if (($text[$index] ?? '') !== '/') {
                $index++;
                continue;
            }

            $key = $this->parseName($text, $index);
            $dict[$key] = $this->parseValue($text, $index);
        }

        return $dict;
    }

    /**
     * @return list<mixed>
     */
    private function parseArray(string $text, int &$index): array
    {
        $index++;
        $items = [];
        while ($index < strlen($text)) {
            $this->skipWhitespaceAndComments($text, $index);
            if (($text[$index] ?? '') === ']') {
                $index++;
                break;
            }
            $items[] = $this->parseValue($text, $index);
        }

        return $items;
    }

    private function parseBareTokenValue(string $text, int &$index): mixed
    {
        $token = $this->readBareToken($text, $index);
        if ($token === '') {
            $index++;
            return null;
        }

        if ($token === 'true') {
            return true;
        }
        if ($token === 'false') {
            return false;
        }
        if ($token === 'null') {
            return null;
        }

        if ($this->isNumericToken($token)) {
            $afterFirst = $index;
            $probe = $index;
            $this->skipWhitespaceAndComments($text, $probe);
            $second = $this->readBareToken($text, $probe);
            if ($this->isIntegerToken($token) && $this->isIntegerToken($second)) {
                $this->skipWhitespaceAndComments($text, $probe);
                if (($text[$probe] ?? '') === 'R') {
                    $index = $probe + 1;
                    return [
                        '__kind' => 'ref',
                        'object' => (int) $token,
                        'generation' => (int) $second,
                    ];
                }
            }

            $index = $afterFirst;
            return str_contains($token, '.') ? (float) $token : (int) $token;
        }

        return $token;
    }

    private function readBareToken(string $text, int &$index): string
    {
        $start = $index;
        $length = strlen($text);
        while ($index < $length && !$this->isDelimiter($text[$index])) {
            $index++;
        }

        return substr($text, $start, $index - $start);
    }

    private function parseName(string $text, int &$index): string
    {
        $index++;
        $start = $index;
        $length = strlen($text);
        while ($index < $length && !$this->isDelimiter($text[$index])) {
            $index++;
        }

        return $this->decodePdfName(substr($text, $start, $index - $start));
    }

    private function parseLiteralStringBytes(string $text, int &$index): string
    {
        $index++;
        $depth = 1;
        $out = '';
        $length = strlen($text);

        while ($index < $length && $depth > 0) {
            $char = $text[$index];
            if ($char === '\\') {
                $index++;
                if ($index >= $length) {
                    break;
                }
                $escaped = $text[$index];
                if ($escaped === "\r" || $escaped === "\n") {
                    if ($escaped === "\r" && ($text[$index + 1] ?? '') === "\n") {
                        $index++;
                    }
                    $index++;
                    continue;
                }
                if (preg_match('/[0-7]/', $escaped) === 1) {
                    $octal = $escaped;
                    for ($count = 1; $count < 3 && preg_match('/[0-7]/', $text[$index + 1] ?? '') === 1; $count++) {
                        $index++;
                        $octal .= $text[$index];
                    }
                    $out .= chr(octdec($octal) & 0xff);
                    $index++;
                    continue;
                }

                $out .= match ($escaped) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'b' => "\x08",
                    'f' => "\x0c",
                    '(' => '(',
                    ')' => ')',
                    '\\' => '\\',
                    default => $escaped,
                };
                $index++;
                continue;
            }

            if ($char === '(') {
                $depth++;
                $out .= $char;
                $index++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $index++;
                    break;
                }
                $out .= $char;
                $index++;
                continue;
            }

            $out .= $char;
            $index++;
        }

        return $out;
    }

    private function parseHexStringBytes(string $text, int &$index): string
    {
        $index++;
        $hex = '';
        $length = strlen($text);
        while ($index < $length && $text[$index] !== '>') {
            if (!ctype_space($text[$index])) {
                $hex .= $text[$index];
            }
            $index++;
        }
        if (($text[$index] ?? '') === '>') {
            $index++;
        }

        if ($hex === '' || preg_match('/^[\da-fA-F]+$/', $hex) !== 1) {
            return '';
        }
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $bytes = hex2bin($hex);
        return $bytes === false ? '' : $bytes;
    }

    /**
     * @return array{__kind: string, value: string, bytes: string}
     */
    private function stringToken(string $bytes): array
    {
        return [
            '__kind' => 'string',
            'value' => $this->decodePdfStringBytes($bytes),
            'bytes' => $bytes,
        ];
    }

    private function decodePdfName(string $name): string
    {
        return preg_replace_callback('/#([\da-fA-F]{2})/', static function (array $match): string {
            return chr(hexdec($match[1]));
        }, $name) ?? $name;
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

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     */
    private function resolveValue(mixed $value, array $objects): mixed
    {
        $object = $this->objectForReference($value, $objects);
        if ($object !== null) {
            return $object['value'];
        }

        return $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dict(mixed $value): ?array
    {
        if (!is_array($value) || isset($value['__kind'])) {
            return null;
        }

        return $value;
    }

    /**
     * @return list<mixed>|null
     */
    private function arrayValue(mixed $value): ?array
    {
        if (!is_array($value) || isset($value['__kind'])) {
            return null;
        }
        if (!array_is_list($value)) {
            return null;
        }

        return $value;
    }

    private function refObjectId(mixed $value): ?int
    {
        return is_array($value) && ($value['__kind'] ?? null) === 'ref' ? (int) $value['object'] : null;
    }

    /**
     * @return array{objectNumber: int, generation: int}|null
     */
    private function refObjectReference(mixed $value): ?array
    {
        if (!is_array($value) || ($value['__kind'] ?? null) !== 'ref') {
            return null;
        }

        return [
            'objectNumber' => (int) $value['object'],
            'generation' => (int) ($value['generation'] ?? 0),
        ];
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return array{generation: int, body: string, value: mixed, stream: string|null}|null
     */
    private function objectForReference(mixed $value, array $objects): ?array
    {
        $reference = $this->refObjectReference($value);
        if ($reference === null) {
            return null;
        }

        $object = $objects[$reference['objectNumber']] ?? null;
        if ($object === null || $object['generation'] !== $reference['generation']) {
            return null;
        }

        return $object;
    }

    private function nameValue(mixed $value): ?string
    {
        return is_array($value) && ($value['__kind'] ?? null) === 'name' ? (string) $value['value'] : null;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_array($value) && ($value['__kind'] ?? null) === 'string' ? (string) $value['value'] : null;
    }

    private function stringBytesHex(mixed $value): ?string
    {
        return is_array($value) && ($value['__kind'] ?? null) === 'string' ? strtolower(bin2hex((string) $value['bytes'])) : null;
    }

    private function intValue(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }

    /**
     * @return list<float>
     */
    private function numberArray(mixed $value): array
    {
        $array = $this->arrayValue($value);
        if ($array === null) {
            return [];
        }

        $numbers = [];
        foreach ($array as $item) {
            if (is_int($item) || is_float($item)) {
                $numbers[] = (float) $item;
            }
        }

        return $numbers;
    }

    private function skipWhitespaceAndComments(string $text, int &$index): void
    {
        $length = strlen($text);
        while ($index < $length) {
            if (ctype_space($text[$index])) {
                $index++;
                continue;
            }

            if ($text[$index] === '%') {
                while ($index < $length && !in_array($text[$index], ["\r", "\n"], true)) {
                    $index++;
                }
                continue;
            }

            break;
        }
    }

    private function isDelimiter(string $char): bool
    {
        return ctype_space($char) || str_contains('()<>[]{}/%', $char);
    }

    private function isNumericToken(string $token): bool
    {
        return preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/', $token) === 1;
    }

    private function isIntegerToken(string $token): bool
    {
        return preg_match('/^[+-]?\d+$/', $token) === 1;
    }

    private function assertPdfBytes(string $pdfBytes): void
    {
        if (!str_starts_with(ltrim($pdfBytes), '%PDF-')) {
            throw new InvalidArgumentException('PDF attachment extraction requires PDF bytes.');
        }
    }
}
