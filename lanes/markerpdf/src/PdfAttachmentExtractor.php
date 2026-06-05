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

    private const ANNOTATION_FLAGS = [
        1 => 'invisible',
        2 => 'hidden',
        3 => 'print',
        4 => 'no_zoom',
        5 => 'no_rotate',
        6 => 'no_view',
        7 => 'read_only',
        8 => 'locked',
        9 => 'toggle_no_view',
        10 => 'locked_contents',
    ];

    private const FILE_ATTACHMENT_ICONS = [
        'Graph' => 'graph',
        'Paperclip' => 'paperclip',
        'PushPin' => 'push_pin',
        'Tag' => 'tag',
    ];

    private const EMBEDDED_FILE_FALLBACK_KEYS = ['UF', 'F', 'Unix', 'Mac', 'DOS'];

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
        $seenEmbeddedFileNames = [];
        foreach ($this->embeddedFilesNameTreeEntries($objects, $catalogObjectIds) as $entry) {
            if (isset($seenEmbeddedFileNames[$entry['name']])) {
                continue;
            }

            $context = [
                'name_key' => $entry['name'],
            ];
            if (($entry['portfolio'] ?? []) !== []) {
                $context['portfolio'] = $entry['portfolio'];
            }

            $attachment = $this->attachmentFromFileSpecValue(
                $entry['fileSpec'],
                $objects,
                'embedded-files-name-tree',
                $context,
                $encryptionPolicy
            );
            if ($attachment !== null) {
                $seenEmbeddedFileNames[$entry['name']] = true;
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
                ] + (($entry['portfolio'] ?? []) !== [] ? ['portfolio' => $entry['portfolio']] : []),
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
            $context = [
                'page_number' => $entry['pageNumber'],
                'page_object_id' => $entry['pageObjectId'],
                'annotation_object_id' => $entry['annotationObjectId'],
                'annotation_contents' => $entry['contents'],
                'annotation_rect' => $entry['rect'],
            ];
            foreach ([
                'annotation_flags',
                'annotation_flag_names',
                'annotation_visibility',
                'annotation_visible',
                'annotation_hidden',
                'annotation_printable',
                'annotation_no_view',
                'annotation_icon',
                'annotation_icon_label',
                'annotation_icon_status',
                'annotation_title',
                'annotation_subject',
                'annotation_modified_at',
                'annotation_name',
                'annotation_color',
                'annotation_color_space',
                'annotation_color_component_count',
                'annotation_opacity',
            ] as $annotationReviewKey) {
                if (array_key_exists($annotationReviewKey, $entry)) {
                    $context[$annotationReviewKey] = $entry[$annotationReviewKey];
                }
            }

            $attachment = $this->attachmentFromFileSpecValue(
                $entry['fileSpec'],
                $objects,
                'file-attachment-annotation',
                $context,
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
            'annotation_flags',
            'annotation_flag_names',
            'annotation_visibility',
            'annotation_visible',
            'annotation_hidden',
            'annotation_printable',
            'annotation_no_view',
            'annotation_icon',
            'annotation_icon_label',
            'annotation_icon_status',
            'annotation_title',
            'annotation_subject',
            'annotation_modified_at',
            'annotation_name',
            'annotation_color',
            'annotation_color_space',
            'annotation_color_component_count',
            'annotation_opacity',
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
            if (!$this->attachmentCanAcceptMirror($attachment, $candidate)) {
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
    private function attachmentCanAcceptMirror(array $attachment, array $candidate): bool
    {
        $attachmentSource = $attachment['source'] ?? null;
        $candidateSource = $candidate['source'] ?? null;
        if ($attachmentSource === 'embedded-files-name-tree') {
            return true;
        }

        if ($attachmentSource === 'catalog-associated-file') {
            return in_array($candidateSource, [
                'catalog-associated-file',
                'page-associated-file',
                'file-attachment-annotation',
            ], true);
        }

        if ($attachmentSource === 'page-associated-file' && $candidateSource === 'file-attachment-annotation') {
            return ($attachment['page_object_id'] ?? null) === ($candidate['page_object_id'] ?? null);
        }

        return false;
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

        if ($this->hasGeneratedFilenameMirror($attachment, $candidate)) {
            return true;
        }

        return ($attachment['filename'] ?? null) === ($candidate['filename'] ?? null)
            && ($attachment['byte_length'] ?? null) === ($candidate['byte_length'] ?? null)
            && ($attachment['sha256'] ?? null) === ($candidate['sha256'] ?? null);
    }

    /**
     * @param array<string, mixed> $attachment
     * @param array<string, mixed> $candidate
     */
    private function hasGeneratedFilenameMirror(array $attachment, array $candidate): bool
    {
        $attachmentSource = $attachment['filename_source'] ?? null;
        $candidateSource = $candidate['filename_source'] ?? null;

        return ($attachmentSource === 'generated' && is_string($candidateSource) && $candidateSource !== 'generated')
            || ($candidateSource === 'generated' && is_string($attachmentSource) && $attachmentSource !== 'generated');
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
            return $this->latestTrailerHasRootCatalogEntry($pdfBytes) ? [] : null;
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
        $definitions = $this->directObjectDefinitions($pdfBytes);

        return $this->latestTrailerRootResolution($pdfBytes, $definitions)['reference'];
    }

    private function latestTrailerHasRootCatalogEntry(string $pdfBytes): bool
    {
        $pdfBytes = $this->bytesThroughTerminalEof($pdfBytes);
        $definitions = $this->directObjectDefinitions($pdfBytes);

        return $this->latestTrailerRootResolution($pdfBytes, $definitions)['present'];
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

            $portfolio = $this->collectionMetadata($dict['Collection'] ?? null, $objects);
            $names = $this->dict($this->resolveValue($dict['Names'] ?? null, $objects));
            if ($names === null || !array_key_exists('EmbeddedFiles', $names)) {
                continue;
            }

            foreach ($this->nameTreeEntries($names['EmbeddedFiles'], $objects) as $entry) {
                if ($portfolio !== []) {
                    $entry['portfolio'] = $portfolio;
                }
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

            $portfolio = $this->collectionMetadata($dict['Collection'] ?? null, $objects);
            $associatedFiles = $this->arrayValue($this->resolveValue($dict['AF'] ?? null, $objects));
            if ($associatedFiles === null) {
                continue;
            }

            foreach ($associatedFiles as $index => $fileSpec) {
                $entries[] = [
                    'catalogObjectId' => $objectId,
                    'associatedFileIndex' => $index,
                    'fileSpec' => $fileSpec,
                    'portfolio' => $portfolio,
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
                $name = $this->stringValue($this->resolveValue($names[$index], $objects));
                if ($name === null || $name === '' || !$this->nameTreeNameWithinLimits($name, $entryLimits)) {
                    continue;
                }

                $entries[] = [
                    'name' => $name,
                    'fileSpec' => $names[$index + 1],
                ];
            }
        }

        $kids = $this->arrayValue($this->resolveValue($dict['Kids'] ?? null, $objects));
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
                ] + $this->fileAttachmentAnnotationReview($dict, $objects);
            }
        }

        return $entries;
    }

    /**
     * FileAttachment annotations can carry review presentation state. Keep it
     * with the attachment summary so WordPress import can distinguish visible
     * attachment icons from hidden/no-view review packets without executing
     * annotation actions or embedded payloads.
     *
     * @param array<string, mixed> $annotation
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return array<string, mixed>
     */
    private function fileAttachmentAnnotationReview(array $annotation, array $objects): array
    {
        $flags = $this->intValue($this->resolveValue($annotation['F'] ?? null, $objects)) ?? 0;
        $review = [
            'annotation_flags' => $flags,
            'annotation_flag_names' => $this->annotationFlagNames($flags),
            'annotation_visibility' => $this->annotationVisibility($flags),
            'annotation_visible' => !$this->annotationFlagsHide($flags),
            'annotation_hidden' => $this->annotationFlagsHide($flags),
            'annotation_printable' => $this->hasFlagBit($flags, 3),
            'annotation_no_view' => $this->hasFlagBit($flags, 6),
        ];

        $icon = $this->nameOrStringValue($annotation['Name'] ?? null, $objects);
        if ($icon !== null && $icon !== '') {
            $review['annotation_icon'] = $icon;
            $review['annotation_icon_label'] = self::FILE_ATTACHMENT_ICONS[$icon] ?? 'custom';
            $review['annotation_icon_status'] = array_key_exists($icon, self::FILE_ATTACHMENT_ICONS)
                ? 'standard_file_attachment_icon'
                : 'custom_file_attachment_icon';
        }

        foreach ([
            'annotation_title' => $this->stringValue($this->resolveValue($annotation['T'] ?? null, $objects)),
            'annotation_subject' => $this->stringValue($this->resolveValue($annotation['Subj'] ?? null, $objects)),
            'annotation_modified_at' => $this->stringValue($this->resolveValue($annotation['M'] ?? null, $objects)),
            'annotation_name' => $this->stringValue($this->resolveValue($annotation['NM'] ?? null, $objects)),
        ] as $key => $value) {
            if ($value !== null && $value !== '') {
                $review[$key] = $value;
            }
        }

        $color = $this->resolvedNumberArray($annotation['C'] ?? null, $objects);
        if ($color !== []) {
            $review['annotation_color'] = $color;
            $review['annotation_color_space'] = $this->annotationColorSpace(count($color));
            $review['annotation_color_component_count'] = count($color);
        }

        $opacity = $this->numberValue($this->resolveValue($annotation['CA'] ?? null, $objects));
        if ($opacity !== null) {
            $review['annotation_opacity'] = $opacity;
        }

        return $review;
    }

    private function annotationColorSpace(int $componentCount): string
    {
        return match ($componentCount) {
            0 => 'transparent',
            1 => 'grayscale',
            3 => 'rgb',
            4 => 'cmyk',
            default => 'unknown',
        };
    }

    /**
     * @return list<string>
     */
    private function annotationFlagNames(int $flags): array
    {
        $names = [];
        foreach (self::ANNOTATION_FLAGS as $bit => $name) {
            if ($this->hasFlagBit($flags, $bit)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function annotationFlagsHide(int $flags): bool
    {
        return $this->hasFlagBit($flags, 1)
            || $this->hasFlagBit($flags, 2)
            || $this->hasFlagBit($flags, 6);
    }

    private function annotationVisibility(int $flags): string
    {
        if ($this->hasFlagBit($flags, 2)) {
            return 'hidden';
        }

        if ($this->hasFlagBit($flags, 1)) {
            return 'invisible';
        }

        if ($this->hasFlagBit($flags, 6)) {
            return 'no_view';
        }

        return 'visible';
    }

    private function hasFlagBit(int $flags, int $bit): bool
    {
        return ($flags & (1 << ($bit - 1))) !== 0;
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
        $kids = $this->arrayValue($this->resolveValue($dict['Kids'] ?? null, $objects)) ?? [];
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

        $nameKey = isset($context['name_key']) && is_string($context['name_key']) ? $context['name_key'] : null;
        [$filename, $filenameSource] = $this->filenameWithSource($fileSpec, $objects, $nameKey, null);
        $streamReference = $this->embeddedFileStreamReference(
            $fileSpec['EF'] ?? null,
            $objects,
            $filenameSource
        );
        if ($streamReference === null || !isset($objects[$streamReference['objectId']])) {
            return null;
        }

        $streamObjectId = $streamReference['objectId'];
        if ($filename === '') {
            $filename = 'attachment-' . $streamObjectId;
            $filenameSource = 'generated';
        }
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
        $filenameReview = $this->filenamePathReview($filename);
        $filters = $this->filterNames($streamDict['Filter'] ?? null, $objects);
        $declaredSize = $this->intValue($this->resolveValue($params['Size'] ?? null, $objects));
        $decodedLength = $this->intValue($this->resolveValue($streamDict['DL'] ?? null, $objects));
        $checksum = $this->stringBytesHex($this->resolveValue($params['CheckSum'] ?? null, $objects));
        $relationship = $this->nameValue($this->resolveValue($fileSpec['AFRelationship'] ?? null, $objects));
        $fileSystem = $this->nameOrStringValue($fileSpec['FS'] ?? null, $objects);
        $fileIdentifier = $this->fileSpecIdentifierReview($fileSpec['ID'] ?? null, $objects);
        $volatile = $this->boolValue($this->resolveValue($fileSpec['V'] ?? null, $objects));
        $portfolioItem = $this->collectionItemReview($fileSpec['CI'] ?? null, $objects);

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
        foreach ($filenameReview as $key => $metadataValue) {
            $attachment[$key] = $metadataValue;
        }

        if ($bytes !== null) {
            $attachment['byte_length'] = strlen($bytes);
            $attachment['sha256'] = hash('sha256', $bytes);
            $attachment['bytes'] = $bytes;
        }
        if ($filters !== []) {
            $attachment['filters'] = $filters;
        }
        if ($filenameSource === 'UF' && $filename !== '') {
            $attachment['unicode_filename'] = $filename;
        }
        if ($declaredSize !== null && $bytes !== null) {
            $attachment['declared_size_matches'] = $declaredSize === strlen($bytes);
        }
        if ($decodedLength !== null) {
            $attachment['decoded_length'] = $decodedLength;
            if ($bytes !== null) {
                $attachment['decoded_length_matches'] = $decodedLength === strlen($bytes);
            }
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
        if ($fileSystem !== null && $fileSystem !== '') {
            $attachment['file_system'] = $fileSystem;
            $attachment['file_system_status'] = $this->fileSpecFileSystemStatus($fileSystem);
        }
        if ($fileIdentifier !== []) {
            $attachment['file_identifier'] = $fileIdentifier;
        }
        if ($volatile !== null) {
            $attachment['volatile'] = $volatile;
            $attachment['volatile_status'] = $volatile
                ? 'volatile_file_spec_review'
                : 'stable_file_spec_review';
        }
        if ($portfolioItem !== []) {
            $attachment['portfolio_item'] = $portfolioItem;
            $attachment['portfolio_item_count'] = count($portfolioItem);
        }
        $portfolio = is_array($attachment['portfolio'] ?? null) ? $attachment['portfolio'] : [];
        $portfolioFieldValues = $this->collectionFieldValueReview(
            $portfolio,
            $fileSpec['CI'] ?? null,
            $objects,
            $attachment
        );
        if ($portfolioFieldValues !== []) {
            $attachment['portfolio_field_values'] = $portfolioFieldValues;
        }

        $relatedFiles = $this->relatedFileRows($fileSpec['RF'] ?? null, $objects, $encryptionPolicy);
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
                'unicode_filename',
                'filename_source',
                'filename_leaf',
                'filename_storage_name',
                'filename_path_status',
                'filename_has_path_segments',
                'filename_contains_parent_segment',
                'filename_absolute_path',
                'filename_url_scheme',
                'description',
                'annotation_contents',
                'annotation_title',
                'annotation_subject',
                'annotation_modified_at',
                'annotation_name',
                'portfolio',
                'portfolio_field_values',
                'file_identifier',
                'portfolio_item',
                'portfolio_item_count',
            ] as $key) {
                unset($attachment[$key]);
            }
        }

        if ($payloadEncrypted) {
            foreach ([
                'content_type',
                'declared_size',
                'declared_size_matches',
                'decoded_length',
                'decoded_length_matches',
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
                foreach ([
                    'related_filename',
                    'related_filename_source',
                    'related_filename_leaf',
                    'related_filename_storage_name',
                    'related_filename_path_status',
                    'related_filename_has_path_segments',
                    'related_filename_contains_parent_segment',
                    'related_filename_absolute_path',
                    'related_filename_url_scheme',
                ] as $key) {
                    unset($row[$key]);
                }
            }
            if ($payloadEncrypted) {
                foreach ([
                    'content_type',
                    'byte_length',
                    'sha256',
                    'declared_size',
                    'declared_size_matches',
                    'decoded_length',
                    'decoded_length_matches',
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
        $offset = $this->startxrefOffsetWithClassicRebuild($pdfBytes, $definitions);
        if ($offset !== null) {
            $table = $this->xrefTableSectionAt($pdfBytes, $offset, $definitions);
            if ($table !== null) {
                return $table['trailer'];
            }

            $stream = $this->xrefStreamSectionAt($offset, $definitions);
            if ($stream !== null) {
                return $stream['dictionary'];
            }
        }

        $trailer = null;
        $searchOffset = 0;
        while (($trailerOffset = strpos($pdfBytes, 'trailer', $searchOffset)) !== false) {
            if (
                !$this->pdfKeywordAt($pdfBytes, $trailerOffset, 'trailer')
                || $this->tokenStartsInPdfCommentLine($pdfBytes, $trailerOffset)
                || $this->offsetOwnedByDirectObjectBody($trailerOffset, $definitions)
                || $this->tokenStartsInsidePdfCompositeToken($pdfBytes, $trailerOffset, $definitions)
            ) {
                $searchOffset = $trailerOffset + strlen('trailer');
                continue;
            }

            $dictionaryOffset = $this->skipWhitespaceOffset($pdfBytes, $trailerOffset + strlen('trailer'));
            if (substr($pdfBytes, $dictionaryOffset, 2) !== '<<') {
                $searchOffset = $trailerOffset + strlen('trailer');
                continue;
            }

            $index = $dictionaryOffset;
            $candidate = $this->dict($this->parseValue($pdfBytes, $index));
            if ($candidate !== null) {
                $trailer = $candidate;
            }

            $searchOffset = $trailerOffset + strlen('trailer');
        }

        return $trailer;
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

    private function boolValue(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
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
     * FileSpec /ID byte strings identify the external or embedded file
     * revision. Keep them as hex review metadata, never as payload text.
     *
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return array<string, mixed>
     */
    private function fileSpecIdentifierReview(mixed $value, array $objects): array
    {
        $items = $this->arrayValue($this->resolveValue($value, $objects));
        if ($items === null || $items === []) {
            return [];
        }

        $identifiers = [];
        foreach ($items as $item) {
            $hex = $this->stringBytesHex($this->resolveValue($item, $objects));
            if ($hex !== null && $hex !== '') {
                $identifiers[] = $hex;
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
     * FileSpec /CI collection item dictionaries are PDF Portfolio metadata.
     * Keep scalar/subitem values as review-only attachment metadata and ignore
     * references to streams or arbitrary dictionaries so payload bytes stay out
     * of WordPress preflight summaries.
     *
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return array<string, mixed>
     */
    private function collectionItemReview(mixed $value, array $objects): array
    {
        $item = $this->dict($this->resolveValue($value, $objects));
        if ($item === null) {
            return [];
        }

        $review = [];
        foreach ($item as $name => $fieldValue) {
            if (!is_string($name) || $name === 'Type') {
                continue;
            }

            $subitem = $this->collectionSubitemReview($fieldValue, $objects);
            if ($subitem !== null) {
                $review[$name] = $subitem;
                continue;
            }

            $scalar = $this->collectionReviewScalar($fieldValue, $objects);
            if ($scalar !== null && $scalar !== '') {
                $review[$name] = $scalar;
            }
        }

        return $review;
    }

    /**
     * Catalog /Collection dictionaries define PDF Portfolio review context for
     * EmbeddedFiles entries. Keep schema and ordering metadata, but never
     * promote embedded payloads or arbitrary private streams into summaries.
     *
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return array<string, mixed>
     */
    private function collectionMetadata(mixed $collectionValue, array $objects): array
    {
        $collection = $this->dict($this->resolveValue($collectionValue, $objects));
        if ($collection === null) {
            return [];
        }

        $metadata = ['source' => 'catalog_collection'];
        foreach ([
            'type' => $this->nameOrStringValue($collection['Type'] ?? null, $objects),
            'view' => $this->nameOrStringValue($collection['View'] ?? null, $objects),
            'default_document' => $this->collectionReviewScalar($collection['D'] ?? null, $objects),
        ] as $key => $value) {
            if ($value !== null && $value !== '') {
                $metadata[$key] = $value;
            }
        }

        $schema = $this->collectionSchemaReview($collection['Schema'] ?? null, $objects);
        if ($schema !== []) {
            $metadata['schema'] = $schema;
        }

        $sort = $this->collectionSortReview($collection['Sort'] ?? null, $objects);
        if ($sort !== []) {
            $metadata['sort'] = $sort;
        }

        if (array_key_exists('Folders', $collection)) {
            $metadata['has_folders'] = true;
        }

        return $metadata;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return array<string, array<string, mixed>>
     */
    private function collectionSchemaReview(mixed $schemaValue, array $objects): array
    {
        $schema = $this->dict($this->resolveValue($schemaValue, $objects));
        if ($schema === null) {
            return [];
        }

        $fields = [];
        foreach ($schema as $fieldName => $fieldValue) {
            if (!is_string($fieldName)) {
                continue;
            }

            $fieldDictionary = $this->dict($this->resolveValue($fieldValue, $objects));
            if ($fieldDictionary === null) {
                continue;
            }

            $field = [];
            foreach ([
                'subtype' => $this->nameOrStringValue($fieldDictionary['Subtype'] ?? null, $objects),
                'label' => $this->stringValue($this->resolveValue($fieldDictionary['N'] ?? null, $objects)),
                'order' => $this->intValue($this->resolveValue($fieldDictionary['O'] ?? null, $objects)),
                'visible' => $this->boolValue($this->resolveValue($fieldDictionary['V'] ?? null, $objects)),
                'editable' => $this->boolValue($this->resolveValue($fieldDictionary['E'] ?? null, $objects)),
            ] as $key => $value) {
                if ($value !== null && $value !== '') {
                    $field[$key] = $value;
                }
            }

            if ($field !== []) {
                $fields[$fieldName] = $field;
            }
        }

        return $fields;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return array<string, mixed>
     */
    private function collectionSortReview(mixed $sortValue, array $objects): array
    {
        $sort = $this->dict($this->resolveValue($sortValue, $objects));
        if ($sort === null) {
            return [];
        }

        $metadata = [];
        $keys = $this->collectionReviewList($sort['S'] ?? null, $objects);
        if ($keys !== []) {
            $metadata['keys'] = $keys;
        }

        $ascending = $this->collectionReviewList($sort['A'] ?? null, $objects);
        if ($ascending !== []) {
            $metadata['ascending'] = $ascending;
        }

        return $metadata;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return list<mixed>
     */
    private function collectionReviewList(mixed $value, array $objects): array
    {
        $resolved = $this->resolveValue($value, $objects);
        $items = $this->arrayValue($resolved);
        if ($items === null) {
            $items = $resolved === null ? [] : [$resolved];
        }

        $values = [];
        foreach ($items as $item) {
            $scalar = $this->collectionReviewScalar($item, $objects);
            if ($scalar !== null && $scalar !== '') {
                $values[] = $scalar;
            }
        }

        return $values;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return array<string, mixed>|null
     */
    private function collectionSubitemReview(mixed $value, array $objects): ?array
    {
        $subitem = $this->dict($this->resolveValue($value, $objects));
        if ($subitem === null || (!array_key_exists('D', $subitem) && !array_key_exists('P', $subitem))) {
            return null;
        }

        $review = [];
        $data = $this->collectionReviewScalar($subitem['D'] ?? null, $objects);
        if ($data !== null && $data !== '') {
            $review['value'] = $data;
        }

        $prefix = $this->collectionReviewScalar($subitem['P'] ?? null, $objects);
        if ($prefix !== null && $prefix !== '') {
            $review['prefix'] = $prefix;
        }

        $display = $this->collectionDisplayValue($review['value'] ?? null, $review['prefix'] ?? null);
        if ($display !== null && $display !== '') {
            $review['display_value'] = $display;
        }

        return $review === [] ? null : $review;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     */
    private function collectionReviewScalar(mixed $value, array $objects): mixed
    {
        $resolved = $this->resolveValue($value, $objects);
        if (is_string($resolved) || is_int($resolved) || is_float($resolved) || is_bool($resolved)) {
            return $resolved;
        }

        $name = $this->nameValue($resolved);
        if ($name !== null && $name !== '') {
            return $name;
        }

        $string = $this->stringValue($resolved);
        if ($string !== null && $string !== '') {
            return $string;
        }

        return null;
    }

    private function collectionDisplayValue(mixed $value, mixed $prefix = null): ?string
    {
        $displayValue = $this->collectionScalarDisplayValue($value);
        if ($displayValue === null) {
            return null;
        }

        return ($this->collectionScalarDisplayValue($prefix) ?? '') . $displayValue;
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
     * @param array<string, mixed> $portfolioMetadata
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @param array<string, mixed> $attachment
     * @return array<string, array<string, mixed>>
     */
    private function collectionFieldValueReview(
        array $portfolioMetadata,
        mixed $collectionItemValue,
        array $objects,
        array $attachment
    ): array {
        $schema = $portfolioMetadata['schema'] ?? null;
        if (!is_array($schema) || $schema === []) {
            return [];
        }

        $collectionItem = $this->dict($this->resolveValue($collectionItemValue, $objects));
        $collectionItemEntries = $collectionItem ?? [];
        $metadata = [];
        foreach ($schema as $fieldName => $fieldSchema) {
            if (!is_string($fieldName) || !is_array($fieldSchema)) {
                continue;
            }

            $subtype = $fieldSchema['subtype'] ?? null;
            $value = null;
            if (array_key_exists($fieldName, $collectionItemEntries)) {
                $value = $this->collectionItemFieldValueReview($collectionItemEntries[$fieldName], $objects);
            } elseif (is_string($subtype)) {
                $value = $this->collectionFileRelatedFieldValueReview($subtype, $attachment);
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
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return array<string, mixed>|null
     */
    private function collectionItemFieldValueReview(mixed $value, array $objects): ?array
    {
        $subitem = $this->dict($this->resolveValue($value, $objects));
        if ($subitem !== null && (array_key_exists('D', $subitem) || array_key_exists('P', $subitem))) {
            $metadata = ['source' => 'collection_subitem'];

            $type = $this->nameOrStringValue($subitem['Type'] ?? null, $objects);
            if ($type !== null && $type !== '') {
                $metadata['subitem_type'] = $type;
            }

            $data = $this->collectionReviewScalar($subitem['D'] ?? null, $objects);
            if ($data !== null && $data !== '') {
                $metadata['value'] = $data;
            }

            $prefix = $this->collectionReviewScalar($subitem['P'] ?? null, $objects);
            if ($prefix !== null && $prefix !== '') {
                $metadata['prefix'] = $prefix;
            }

            $displayValue = $this->collectionDisplayValue($metadata['value'] ?? null, $metadata['prefix'] ?? null);
            if ($displayValue !== null && $displayValue !== '') {
                $metadata['display_value'] = $displayValue;
            }

            return array_key_exists('value', $metadata) || array_key_exists('prefix', $metadata)
                ? $metadata
                : null;
        }

        $scalar = $this->collectionReviewScalar($value, $objects);
        if ($scalar === null || $scalar === '') {
            return null;
        }

        $metadata = [
            'source' => 'collection_item',
            'value' => $scalar,
        ];

        $displayValue = $this->collectionDisplayValue($scalar);
        if ($displayValue !== null && $displayValue !== '') {
            $metadata['display_value'] = $displayValue;
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $attachment
     * @return array<string, mixed>|null
     */
    private function collectionFileRelatedFieldValueReview(string $subtype, array $attachment): ?array
    {
        $source = null;
        $value = match ($subtype) {
            'F' => $attachment['unicode_filename'] ?? $attachment['filename'] ?? null,
            'Desc' => $attachment['description'] ?? null,
            'ModDate' => $attachment['modified_at'] ?? null,
            'CreationDate' => $attachment['created_at'] ?? null,
            'Size' => $attachment['declared_size'] ?? null,
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

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return list<array<string, mixed>>
     */
    private function relatedFileRows(mixed $relatedFilesValue, array $objects, ?array $encryptionPolicy = null): array
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
                        $relatedFilename,
                        $encryptionPolicy
                    );
                    if ($row !== null) {
                        $rows[] = $row;
                        $relatedFileIndex++;
                        $index++;
                        continue;
                    }
                }

                $row = $this->relatedFileRowFromStreamValue(
                    $items[$index],
                    $objects,
                    $rfKey,
                    $relatedFileIndex,
                    null,
                    $encryptionPolicy
                );
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
        ?string $relatedFilename = null,
        ?array $encryptionPolicy = null
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

        if ($this->attachmentPolicySuppressesEmbeddedPayload($encryptionPolicy)) {
            $row = [
                'source' => 'filespec_related_files',
                'rf_key' => $rfKey,
                'related_file_index' => $relatedFileIndex,
                'stream_object_id' => $streamObjectId,
                'executes_python_or_models' => false,
                'executes_external_pdf_tools' => false,
            ];

            if ($relatedFilename !== null && $relatedFilename !== '') {
                $row['related_filename'] = $relatedFilename;
                $row['related_filename_source'] = 'rf_name_pair';
                foreach ($this->relatedFilenamePathReview($relatedFilename) as $key => $metadataValue) {
                    $row[$key] = $metadataValue;
                }
            }

            return $row;
        }

        $bytes = $this->decodedStreamBytes($streamObject, $objects);
        if ($bytes === null) {
            return null;
        }

        $streamDict = $this->dict($streamObject['value']) ?? [];
        $params = $this->dict($this->resolveValue($streamDict['Params'] ?? null, $objects)) ?? [];
        $filters = $this->filterNames($streamDict['Filter'] ?? null, $objects);
        $declaredSize = $this->intValue($this->resolveValue($params['Size'] ?? null, $objects));
        $decodedLength = $this->intValue($this->resolveValue($streamDict['DL'] ?? null, $objects));
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
            foreach ($this->relatedFilenamePathReview($relatedFilename) as $key => $metadataValue) {
                $row[$key] = $metadataValue;
            }
        }
        if ($filters !== []) {
            $row['filters'] = $filters;
        }
        if ($declaredSize !== null) {
            $row['declared_size'] = $declaredSize;
            $row['declared_size_matches'] = $declaredSize === strlen($bytes);
        }
        if ($decodedLength !== null) {
            $row['decoded_length'] = $decodedLength;
            $row['decoded_length_matches'] = $decodedLength === strlen($bytes);
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
    private function embeddedFileStreamReference(mixed $efValue, array $objects, string $preferredKey): ?array
    {
        $ef = $this->dict($this->resolveValue($efValue, $objects));
        if ($ef === null) {
            return null;
        }

        $keys = $this->embeddedFileKeyOrder($preferredKey);
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
     * FileSpec /EF dictionaries use the same F/UF/DOS/Unix/Mac keys as the
     * filename entries. Prefer the embedded stream that corresponds to the
     * filename source selected for review before falling back for malformed PDFs.
     *
     * @return list<string>
     */
    private function embeddedFileKeyOrder(string $preferredKey): array
    {
        if (in_array($preferredKey, self::EMBEDDED_FILE_FALLBACK_KEYS, true)) {
            return array_values(array_unique([$preferredKey, ...self::EMBEDDED_FILE_FALLBACK_KEYS]));
        }

        return self::EMBEDDED_FILE_FALLBACK_KEYS;
    }

    /**
     * @param array<string, mixed> $fileSpec
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return array{0: string, 1: string}
     */
    private function filenameWithSource(array $fileSpec, array $objects, ?string $nameKey, ?int $streamObjectId): array
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

        return [$streamObjectId === null ? '' : 'attachment-' . $streamObjectId, 'generated'];
    }

    /**
     * FileSpec filenames may be platform paths or URL-like strings. Preserve
     * the raw PDF filename for review, but give WordPress import code a
     * basename-only storage/display candidate so path segments are never
     * treated as local output paths.
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
        $filters = $this->filterSlots($dict['Filter'] ?? null, $objects);
        if ($filters === null) {
            return null;
        }

        $decodeParms = [];
        if (array_key_exists('DecodeParms', $dict)) {
            $decodeParms = $this->decodeParmsSlots($dict['DecodeParms'], $objects);
            if ($decodeParms === null || !$this->decodeParmsSupportedForFilters($decodeParms, $objects, $filters)) {
                return null;
            }
        }

        foreach ($filters as $filterIndex => $filter) {
            if ($filter === null) {
                continue;
            }

            $filterDecodeParms = $this->decodeParmsForFilterIndex($filters, $decodeParms, $filterIndex);
            $decoded = match ($filter) {
                'FlateDecode', 'Fl' => $this->decodeFlateStream($bytes, $filterDecodeParms, $objects),
                'ASCII85Decode', 'A85' => $this->decodeAscii85Stream($bytes),
                'ASCIIHexDecode', 'AHx' => $this->decodeAsciiHexStream($bytes),
                'RunLengthDecode', 'RL' => $this->decodeRunLengthStream($bytes),
                'Crypt' => $this->decodeCryptIdentityStream($bytes, $filterDecodeParms, $objects),
                default => null,
            };

            if ($decoded === null) {
                return null;
            }
            $bytes = $decoded;
        }

        return $bytes;
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

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return list<string|null>|null
     */
    private function filterSlots(mixed $filterValue, array $objects): ?array
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
            $resolved = $this->resolveValue($value, $objects);
            if ($resolved === null) {
                $filters[] = null;
                continue;
            }

            $filter = $this->nameValue($resolved);
            if ($filter === null) {
                return null;
            }

            $filters[] = $filter;
        }

        return $filters;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return list<string>
     */
    private function filterNames(mixed $filterValue, array $objects): array
    {
        $slots = $this->filterSlots($filterValue, $objects);
        if ($slots === null) {
            return [];
        }

        $filters = [];
        foreach ($slots as $filter) {
            if (is_string($filter)) {
                $filters[] = $filter;
            }
        }

        return $filters;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return list<mixed>|null
     */
    private function decodeParmsSlots(mixed $decodeParmsValue, array $objects): ?array
    {
        $resolved = $this->resolveValue($decodeParmsValue, $objects);
        $array = $this->arrayValue($resolved);

        return $array === null ? [$resolved] : $array;
    }

    /**
     * @param list<string|null> $filters
     * @param list<mixed> $decodeParms
     */
    private function decodeParmsForFilterIndex(array $filters, array $decodeParms, int $filterIndex): mixed
    {
        $decodeParmsIndex = $this->decodeParmsIndexForFilterIndex($filters, $decodeParms, $filterIndex);
        return $decodeParmsIndex === null ? null : ($decodeParms[$decodeParmsIndex] ?? null);
    }

    /**
     * @param list<string|null> $filters
     * @param list<mixed> $decodeParms
     */
    private function decodeParmsIndexForFilterIndex(array $filters, array $decodeParms, int $filterIndex): ?int
    {
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
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @param list<string|null> $filters
     * @param list<mixed> $decodeParms
     */
    private function decodeParmsSupportedForFilters(array $decodeParms, array $objects, array $filters): bool
    {
        $appliedParameterIndexes = [];
        foreach ($filters as $filterIndex => $filter) {
            if ($filter === null) {
                continue;
            }

            $parameterIndex = $this->decodeParmsIndexForFilterIndex($filters, $decodeParms, $filterIndex);
            if ($parameterIndex === null || !array_key_exists($parameterIndex, $decodeParms)) {
                continue;
            }

            $appliedParameterIndexes[$parameterIndex] = true;
            if (!$this->decodeParmsValueCanApplyToFilter($filter, $decodeParms[$parameterIndex], $objects)) {
                return false;
            }
        }

        foreach ($decodeParms as $parameterIndex => $value) {
            if (isset($appliedParameterIndexes[$parameterIndex])) {
                continue;
            }
            if ($this->decodeParmsValueIsDefault($value, $objects)) {
                continue;
            }
            if (
                !($this->decodeParmsUseCompactNonNullFilterIndexes($filters, count($decodeParms)))
                && array_key_exists($parameterIndex, $filters)
                && $filters[$parameterIndex] === null
            ) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * @param list<string|null> $filters
     */
    private function decodeParmsUseCompactNonNullFilterIndexes(array $filters, int $decodeParmsCount): bool
    {
        $nonNullFilterIndexes = [];
        foreach ($filters as $filterIndex => $filter) {
            if (is_string($filter)) {
                $nonNullFilterIndexes[] = $filterIndex;
            }
        }

        return $decodeParmsCount === count($nonNullFilterIndexes) && $decodeParmsCount !== count($filters);
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     */
    private function decodeParmsValueCanApplyToFilter(string $filter, mixed $value, array $objects): bool
    {
        if ($this->decodeParmsValueIsDefault($value, $objects)) {
            return true;
        }

        if ($filter === 'Crypt') {
            return $this->decodeCryptIdentityStream('', $value, $objects) !== null;
        }

        if (!in_array($filter, ['FlateDecode', 'Fl'], true)) {
            return false;
        }

        return $this->flateDecodeParmsAreSupported($value, $objects);
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     */
    private function flateDecodeParmsAreSupported(mixed $value, array $objects): bool
    {
        $resolved = $this->resolveValue($value, $objects);
        if ($resolved === null) {
            return true;
        }

        $dict = $this->dict($resolved);
        if ($dict === null) {
            return false;
        }

        foreach ($dict as $name => $_parameterValue) {
            if (!in_array($name, ['Predictor', 'Columns', 'Colors', 'BitsPerComponent', 'EarlyChange'], true)) {
                return false;
            }
            if ($this->decodeParmsInt($dict, $name, $objects) === null) {
                return false;
            }
        }

        $predictor = $this->decodeParmsInt($dict, 'Predictor', $objects) ?? 1;
        if ($predictor !== 1 && $predictor !== 2 && ($predictor < 10 || $predictor > 15)) {
            return false;
        }

        foreach (['Columns', 'Colors', 'BitsPerComponent'] as $name) {
            $integer = $this->decodeParmsInt($dict, $name, $objects);
            if ($integer !== null && $integer < 1) {
                return false;
            }
        }

        $earlyChange = $this->decodeParmsInt($dict, 'EarlyChange', $objects);
        if ($earlyChange !== null && !in_array($earlyChange, [0, 1], true)) {
            return false;
        }

        return true;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     */
    private function decodeParmsValueIsDefault(mixed $value, array $objects): bool
    {
        $resolved = $this->resolveValue($value, $objects);
        if ($resolved === null) {
            return true;
        }

        $dict = $this->dict($resolved);
        if ($dict === null) {
            return false;
        }

        $defaults = [
            'Predictor' => 1,
            'Columns' => 1,
            'Colors' => 1,
            'BitsPerComponent' => 8,
            'EarlyChange' => 1,
        ];

        foreach ($dict as $name => $parameterValue) {
            if (!array_key_exists($name, $defaults)) {
                return false;
            }

            $resolvedParameter = $this->resolveValue($parameterValue, $objects);
            if (!is_int($resolvedParameter) || $resolvedParameter !== $defaults[$name]) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed>|null $decodeParms
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     */
    private function decodeFlateStream(string $bytes, mixed $decodeParms = null, array $objects = []): ?string
    {
        $decoded = @gzuncompress($bytes);
        if ($decoded === false) {
            $decoded = @gzinflate($bytes);
        }
        if ($decoded === false) {
            $decoded = @gzdecode($bytes);
        }

        if ($decoded === false) {
            return null;
        }

        return $this->applyDecodeParmsPredictor($decoded, $decodeParms, $objects);
    }

    /**
     * @param array<string, mixed>|null $decodeParms
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     */
    private function applyDecodeParmsPredictor(string $bytes, mixed $decodeParms, array $objects): ?string
    {
        $predictor = $this->decodeParmsIntValue($decodeParms, 'Predictor', $objects) ?? 1;
        if ($predictor === 1) {
            return $bytes;
        }

        $colors = max(1, $this->decodeParmsIntValue($decodeParms, 'Colors', $objects) ?? 1);
        $bitsPerComponent = max(1, $this->decodeParmsIntValue($decodeParms, 'BitsPerComponent', $objects) ?? 8);
        $columns = max(1, $this->decodeParmsIntValue($decodeParms, 'Columns', $objects) ?? 1);
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

    /**
     * @param array<string, mixed>|null $dict
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     */
    private function decodeParmsIntValue(mixed $dict, string $name, array $objects): ?int
    {
        $resolved = $this->resolveValue($dict, $objects);
        $parameters = $this->dict($resolved);
        if ($parameters === null || !array_key_exists($name, $parameters)) {
            return null;
        }

        return $this->decodeParmsInt($parameters, $name, $objects);
    }

    /**
     * @param array<string, mixed> $dict
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     */
    private function decodeParmsInt(array $dict, string $name, array $objects): ?int
    {
        if (!array_key_exists($name, $dict)) {
            return null;
        }

        $resolved = $this->resolveValue($dict[$name], $objects);
        return is_int($resolved) ? $resolved : null;
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

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     */
    private function decodeCryptIdentityStream(string $bytes, mixed $decodeParms, array $objects): ?string
    {
        $resolved = $this->resolveValue($decodeParms, $objects);
        if ($resolved === null) {
            return $bytes;
        }

        $dict = $this->dict($resolved);
        if ($dict === null) {
            return null;
        }

        $name = $this->nameValue($this->resolveValue($dict['Name'] ?? null, $objects));
        if ($name !== null) {
            return $name === 'Identity' ? $bytes : null;
        }

        foreach (array_keys($dict) as $key) {
            if ($key !== 'Type') {
                return null;
            }
        }

        return $bytes;
    }

    private function decodeAscii85Stream(string $bytes): ?string
    {
        $body = trim($bytes);
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
        if (!$this->objectStreamMemberOffsetHasTokenBoundary($data, $start)) {
            return null;
        }

        $end = strlen($data);
        foreach ($members as $index => $member) {
            if ($index === $memberIndex || $member['offset'] <= $start) {
                continue;
            }
            if (!$this->objectStreamMemberOffsetHasTokenBoundary($data, $member['offset'])) {
                continue;
            }
            $end = min($end, $member['offset']);
        }

        if ($end <= $start) {
            return null;
        }

        return substr($data, $start, $end - $start);
    }

    private function objectStreamMemberOffsetHasTokenBoundary(string $data, int $offset): bool
    {
        $length = strlen($data);
        if ($offset < 0 || $offset >= $length) {
            return false;
        }

        if ($offset === 0) {
            return true;
        }

        if ($data[$offset] === '%') {
            return false;
        }

        $index = 0;
        while ($index < $offset && $index < $length) {
            $char = $data[$index];
            if ($char === '(') {
                $probe = $index;
                $this->parseLiteralStringBytes($data, $probe);
                if ($probe <= $index || $offset < $probe) {
                    return false;
                }

                $index = $probe;
                continue;
            }

            if ($char === '<') {
                $probe = $index;
                if (($data[$index + 1] ?? '') === '<') {
                    $this->parseDictionary($data, $probe);
                } else {
                    $this->parseHexStringBytes($data, $probe);
                }

                if ($probe <= $index || $offset < $probe) {
                    return false;
                }

                $index = $probe;
                continue;
            }

            if ($char === '[') {
                $probe = $index;
                $this->parseArray($data, $probe);
                if ($probe <= $index || $offset < $probe) {
                    return false;
                }

                $index = $probe;
                continue;
            }

            if ($char === '%') {
                $probe = $index;
                while ($probe < $length && !in_array($data[$probe], ["\r", "\n"], true)) {
                    $probe++;
                }
                if ($offset < $probe) {
                    return false;
                }

                $index = $probe;
                continue;
            }

            $index++;
        }

        if ($index !== $offset) {
            return false;
        }

        return $this->isDelimiter($data[$offset - 1]);
    }

    /**
     * @return array<int, array{objectNumber: int, offset: int}>
     */
    private function objectStreamHeaderMembers(string $header, int $declaredCount): array
    {
        $members = [];
        $offset = 0;
        for ($index = 0; $index < $declaredCount; $index++) {
            $objectNumber = $this->readObjectStreamHeaderUnsignedInteger($header, $offset);
            $objectOffset = $this->readObjectStreamHeaderUnsignedInteger($header, $offset);
            if ($objectNumber === null || $objectOffset === null) {
                return [];
            }

            if ($objectNumber > 0) {
                $members[$index] = [
                    'objectNumber' => $objectNumber,
                    'offset' => $objectOffset,
                ];
            }
        }

        $tailOffset = $offset;
        $this->skipWhitespaceAndComments($header, $tailOffset);
        if ($tailOffset !== strlen($header)) {
            return [];
        }

        return $members;
    }

    private function readObjectStreamHeaderUnsignedInteger(string $header, int &$offset): ?int
    {
        $this->skipWhitespaceAndComments($header, $offset);
        if (preg_match('/\G\+?(\d+)(?=$|[\s\[\]()<>{}\/%])/s', $header, $match, 0, $offset) !== 1) {
            return null;
        }

        $offset += strlen($match[0]);

        return (int) $match[1];
    }

    /**
     * @param array<int, array{objectNumber: int, offset: int}> $members
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
     * @return array<int, list<array{generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string}>>
     */
    private function directObjectDefinitions(string $pdfBytes): array
    {
        if (!preg_match_all('/(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $definitions = [];
        foreach ($matches as $match) {
            $bodyStart = $match[3][1];
            $bodyEnd = $bodyStart + strlen($match[3][0]);
            $definitions[(int) $match[1][0]][] = [
                'generation' => (int) $match[2][0],
                'offset' => $match[0][1],
                'bodyStart' => $bodyStart,
                'bodyEnd' => $bodyEnd,
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

        $generation = $xrefEntry['generation'] ?? null;
        $offset = $xrefEntry['offset'] ?? null;
        if (is_int($offset)) {
            foreach ($definitions as $definition) {
                if ($definition['offset'] !== $offset) {
                    continue;
                }

                if ($generation !== null && $definition['generation'] !== $generation) {
                    continue;
                }

                return $definition;
            }

            return null;
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
     * @param list<array{generation: int, offset: int, body: string}> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function directObjectDefinitionForGenerationBeforeOffset(array $definitions, int $generation, int $beforeOffset): ?array
    {
        $candidates = [];
        foreach ($definitions as $definition) {
            if ($definition['generation'] !== $generation || $definition['offset'] >= $beforeOffset) {
                continue;
            }

            $candidates[] = $definition;
        }

        return $this->latestDirectObjectDefinition($candidates);
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, bodyStart?: int, bodyEnd?: int, body: string}>> $definitions
     * @return array{objectNumber: int, generation: int}|null
     */
    private function latestTrailerRootReference(string $pdfBytes, array $definitions): ?array
    {
        $offset = $this->startxrefOffsetWithClassicRebuild($pdfBytes, $definitions);
        if ($offset === null) {
            return null;
        }

        return $this->trailerRootResolutionAtOffset($pdfBytes, $offset, $definitions)['reference'];
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array{present: bool, reference: array{objectNumber: int, generation: int}|null}
     */
    private function latestTrailerRootResolution(string $pdfBytes, array $definitions): array
    {
        $offset = $this->startxrefOffsetWithClassicRebuild($pdfBytes, $definitions);
        if ($offset === null) {
            return [
                'present' => false,
                'reference' => null,
            ];
        }

        return $this->trailerRootResolutionAtOffset($pdfBytes, $offset, $definitions);
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @param array<int, bool> $seenOffsets
     * @return array{present: bool, reference: array{objectNumber: int, generation: int}|null}
     */
    private function trailerRootResolutionAtOffset(
        string $pdfBytes,
        int $offset,
        array $definitions,
        array $seenOffsets = []
    ): array {
        if ($offset < 0 || isset($seenOffsets[$offset])) {
            return [
                'present' => false,
                'reference' => null,
            ];
        }
        $seenOffsets[$offset] = true;

        $table = $this->xrefTableSectionAt($pdfBytes, $offset, $definitions);
        if ($table !== null) {
            if (array_key_exists('Root', $table['trailer'])) {
                return [
                    'present' => true,
                    'reference' => $this->refObjectReference($table['trailer']['Root']),
                ];
            }

            $hybridStreamOffset = $this->intValue($table['trailer']['XRefStm'] ?? null);
            if ($hybridStreamOffset !== null && $hybridStreamOffset >= 0 && !isset($seenOffsets[$hybridStreamOffset])) {
                $stream = $this->xrefStreamSectionAt($hybridStreamOffset, $definitions);
                if ($stream !== null && array_key_exists('Root', $stream['dictionary'])) {
                    return [
                        'present' => true,
                        'reference' => $this->refObjectReference($stream['dictionary']['Root']),
                    ];
                }
            }

            $previousOffset = $this->previousXrefOffsetForSection(
                $pdfBytes,
                $this->previousXrefOffsetFromTableTrailer($table['trailer'], $definitions, $offset),
                $offset,
                $definitions
            );

            return $previousOffset === null
                ? ['present' => false, 'reference' => null]
                : $this->trailerRootResolutionAtOffset($pdfBytes, $previousOffset, $definitions, $seenOffsets);
        }

        $stream = $this->xrefStreamSectionAt($offset, $definitions);
        if ($stream === null) {
            return [
                'present' => false,
                'reference' => null,
            ];
        }

        if (array_key_exists('Root', $stream['dictionary'])) {
            return [
                'present' => true,
                'reference' => $this->refObjectReference($stream['dictionary']['Root']),
            ];
        }

        $previousOffset = $this->previousXrefOffsetForSection(
            $pdfBytes,
            $this->intValue($this->xrefStreamDictionaryValue($stream, 'Prev', $definitions)),
            $offset,
            $definitions
        );

        return $previousOffset === null
            ? ['present' => false, 'reference' => null]
            : $this->trailerRootResolutionAtOffset($pdfBytes, $previousOffset, $definitions, $seenOffsets);
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, bodyStart?: int, bodyEnd?: int, body: string}>> $definitions
     * @return array<int, array{type: int, generation?: int, offset?: int}>
     */
    private function xrefEntriesFromLatestStartxref(string $pdfBytes, array $definitions): array
    {
        $offset = $this->startxrefOffsetWithClassicRebuild($pdfBytes, $definitions);
        if ($offset === null) {
            return [];
        }

        $entries = $this->xrefEntriesAtOffset($pdfBytes, $offset, $definitions);
        ksort($entries, SORT_NUMERIC);

        return $entries;
    }

    /**
     * @param array<int, list<array{bodyStart?: int, bodyEnd?: int}>>|null $definitions
     */
    private function latestStartxrefOffset(string $pdfBytes, ?array $definitions = null): ?int
    {
        $entry = $this->latestStartxrefEntry($pdfBytes, $definitions);

        return $entry['offset'] ?? null;
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

            $declaredOffset = 0;
            $operandBytes = substr($pdfBytes, $tokenOffset + strlen('startxref'), 64);
            if (preg_match('/^\s*([+-]?\d+)/', $operandBytes, $operandMatch) === 1) {
                $declaredOffset = (int) $operandMatch[1];
            }

            return [
                'offset' => max(0, $declaredOffset),
                'tokenOffset' => $tokenOffset,
            ];
        }

        return null;
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
                $end = $this->literalTokenEndOffset($pdfBytes, $offset);
                if ($end !== null) {
                    if ($tokenOffset > $offset && $tokenOffset < $end) {
                        return true;
                    }
                    $offset = $end;
                    continue;
                }
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
     * @param array<int, list<array{generation: int, offset: int, bodyStart?: int, bodyEnd?: int, body: string}>> $definitions
     */
    private function startxrefOffsetWithClassicRebuild(string $pdfBytes, array $definitions): ?int
    {
        $entry = $this->latestStartxrefEntry($pdfBytes, $definitions);
        if ($entry === null) {
            return null;
        }

        return $this->classicRebuildOffsetForStartxref(
            $pdfBytes,
            $entry['offset'],
            $definitions,
            $entry['tokenOffset']
        ) ?? $entry['offset'];
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, bodyStart?: int, bodyEnd?: int, body: string}>> $definitions
     */
    private function classicRebuildOffsetForStartxref(
        string $pdfBytes,
        int $offset,
        array $definitions,
        ?int $candidateBeforeOffset = null
    ): ?int {
        if ($this->xrefStreamSectionAt($offset, $definitions) !== null) {
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
     * @param array<int, list<array{generation: int, offset: int, bodyStart?: int, bodyEnd?: int, body: string}>> $definitions
     * @param array<int, true> $seenOffsets
     * @return array<int, array{type: int, generation?: int, offset?: int}>
     */
    private function xrefEntriesAtOffset(string $pdfBytes, int $offset, array $definitions, array $seenOffsets = []): array
    {
        if ($offset < 0 || isset($seenOffsets[$offset])) {
            return [];
        }
        $seenOffsets[$offset] = true;

        $table = $this->xrefTableSectionAt($pdfBytes, $offset, $definitions);
        if ($table !== null) {
            $entries = $table['entries'];
            $previousOffset = $this->previousXrefOffsetForSection(
                $pdfBytes,
                $this->previousXrefOffsetFromTableTrailer($table['trailer'], $definitions, $offset),
                $offset,
                $definitions
            );
            $entries = $this->repairCurrentObjectStreamCarrierRows($entries, $definitions, $previousOffset, $offset);
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

        $entries = $this->xrefStreamEntriesFromSection($stream, $definitions);
        $previousOffset = $this->previousXrefOffsetForSection(
            $pdfBytes,
            $this->intValue($this->xrefStreamDictionaryValue($stream, 'Prev', $definitions)),
            $offset,
            $definitions
        );
        $entries = $this->repairCurrentObjectStreamCarrierRows($entries, $definitions, $previousOffset, $offset);
        if ($previousOffset !== null) {
            foreach ($this->xrefEntriesAtOffset($pdfBytes, $previousOffset, $definitions, $seenOffsets) as $objectNumber => $entry) {
                $entries[$objectNumber] ??= $entry;
            }
        }

        return $entries;
    }

    /**
     * Current xref streams may select compressed FileSpec members from a new
     * object stream while omitting the carrier row. Recover only an in-window
     * direct /ObjStm carrier before stale /Prev rows are inherited.
     *
     * @param array<int, array{type: int, generation?: int, offset?: int, objectStream?: int, index?: int, indexIsExplicit?: bool}> $entries
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<int, array{type: int, generation?: int, offset?: int, objectStream?: int, index?: int, indexIsExplicit?: bool}>
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
                ];
                continue;
            }

            if (($entry['type'] ?? null) !== 1) {
                continue;
            }

            if ($this->selectedDirectObjectDefinition($definitions[$objectNumber] ?? [], $entry) !== null) {
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

    private function objectBodyHasTypeName(string $body, string $name): bool
    {
        $index = 0;
        $value = $this->parseValue($body, $index);
        $dictionary = $this->dict($value);
        if ($dictionary === null) {
            return false;
        }

        return $this->nameValue($dictionary['Type'] ?? null) === $name;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, bodyStart?: int, bodyEnd?: int, body: string}>> $definitions
     */
    private function previousXrefOffsetForSection(
        string $pdfBytes,
        ?int $previousOffset,
        int $currentOffset,
        array $definitions
    ): ?int {
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
        return $this->xrefTableSectionAt($pdfBytes, $offset, $definitions) !== null
            || $this->xrefStreamSectionAt($offset, $definitions) !== null;
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
     * @return array{entries: array<int, array{type: int, generation: int, offset: int}>, trailer: array<string, mixed>}|null
     */
    private function xrefTableSectionAt(string $pdfBytes, int $offset, ?array $definitions = null): ?array
    {
        if ($definitions !== null && $this->offsetOwnedByDirectObjectBody($offset, $definitions)) {
            return null;
        }

        $offset = $this->skipWhitespaceOffset($pdfBytes, $offset);
        if (
            $this->tokenStartsInPdfCommentLine($pdfBytes, $offset)
            || $this->tokenStartsInsidePdfCompositeToken($pdfBytes, $offset, $definitions)
        ) {
            return null;
        }

        if (!$this->pdfKeywordAt($pdfBytes, $offset, 'xref')) {
            return null;
        }

        $sectionBodyOffset = $offset + 4;
        if ($sectionBodyOffset >= strlen($pdfBytes)) {
            return null;
        }

        $afterKeyword = $pdfBytes[$sectionBodyOffset];
        if ($afterKeyword !== '%' && !ctype_space($afterKeyword)) {
            return null;
        }

        $trailerOffset = $this->xrefTableTrailerKeywordOffset($pdfBytes, $sectionBodyOffset, $definitions);
        if ($trailerOffset === null) {
            return null;
        }

        $dictionaryOffset = $this->skipWhitespaceOffset($pdfBytes, $trailerOffset + strlen('trailer'));
        if (substr($pdfBytes, $dictionaryOffset, 2) !== '<<') {
            return null;
        }

        $index = $dictionaryOffset;
        $trailer = $this->dict($this->parseValue($pdfBytes, $index)) ?? [];

        $entries = $this->xrefTableRows(substr($pdfBytes, $sectionBodyOffset, $trailerOffset - $sectionBodyOffset));
        if ($entries === null) {
            return null;
        }

        if ($definitions !== null) {
            $entries = $this->repairClassicXrefGenerationOffsetRows($entries, $definitions, $offset);
            $entries = $this->repairCurrentUpdateXrefTableRows($pdfBytes, $entries, $definitions, $trailer, $offset);
        }

        return [
            'entries' => $entries,
            'trailer' => $trailer,
        ];
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
                $end = $this->literalTokenEndOffset($pdfBytes, $offset);
                if ($end !== null) {
                    $offset = $end;
                    continue;
                }
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
                $dictionaryOffset = $this->skipWhitespaceOffset($pdfBytes, $offset + strlen('trailer'));
                if (substr($pdfBytes, $dictionaryOffset, 2) === '<<') {
                    return $offset;
                }
            }

            $offset++;
        }

        return null;
    }

    /**
     * @param array<int, list<array{bodyStart?: int, bodyEnd?: int}>> $definitions
     * @return list<int>
     */
    private function xrefTableKeywordOffsets(string $pdfBytes, array $definitions): array
    {
        $offsets = [];
        $length = strlen($pdfBytes);
        $offset = 0;
        while ($offset < $length) {
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

            $char = $pdfBytes[$offset];
            if ($char === '%') {
                $offset = $this->pdfCommentEndOffset($pdfBytes, $offset);
                continue;
            }

            if ($char === '(') {
                $end = $this->literalTokenEndOffset($pdfBytes, $offset);
                if ($end !== null) {
                    $offset = $end;
                    continue;
                }
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

    private function skipPdfCompositeTokenAt(string $pdfBytes, int $offset): ?int
    {
        if ($offset < 0 || $offset >= strlen($pdfBytes)) {
            return null;
        }

        if (($pdfBytes[$offset] ?? '') !== '[' && substr($pdfBytes, $offset, 2) !== '<<') {
            return null;
        }

        $probe = $offset;
        $this->parseValue($pdfBytes, $probe);

        return $probe > $offset ? $probe : null;
    }

    private function literalTokenEndOffset(string $pdfBytes, int $offset): ?int
    {
        if (($pdfBytes[$offset] ?? '') !== '(') {
            return null;
        }

        $probe = $offset;
        $this->parseLiteralStringBytes($pdfBytes, $probe);

        return $probe > $offset ? $probe : null;
    }

    private function skipPdfHexStringToken(string $pdfBytes, int $offset): ?int
    {
        if (($pdfBytes[$offset] ?? '') !== '<' || ($pdfBytes[$offset + 1] ?? '') === '<') {
            return null;
        }

        for ($index = $offset + 1, $length = strlen($pdfBytes); $index < $length; $index++) {
            $char = $pdfBytes[$index];
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

    private function skipHexString(string $pdfBytes, int $offset): ?int
    {
        if (($pdfBytes[$offset] ?? '') !== '<' || ($pdfBytes[$offset + 1] ?? '') === '<') {
            return null;
        }

        $end = strpos($pdfBytes, '>', $offset + 1);

        return $end === false ? null : $end + 1;
    }

    private function pdfCommentEndOffset(string $pdfBytes, int $offset): int
    {
        $length = strlen($pdfBytes);
        while ($offset < $length && $pdfBytes[$offset] !== "\n" && $pdfBytes[$offset] !== "\r") {
            $offset++;
        }

        return $offset;
    }

    private function pdfKeywordAt(string $pdfBytes, int $offset, string $keyword): bool
    {
        if (substr($pdfBytes, $offset, strlen($keyword)) !== $keyword) {
            return false;
        }

        if ($offset > 0) {
            $before = $pdfBytes[$offset - 1];
            if ($before === '/' || (!ctype_space($before) && !str_contains('[]()<>{}%', $before))) {
                return false;
            }
        }

        $afterOffset = $offset + strlen($keyword);
        if ($afterOffset >= strlen($pdfBytes)) {
            return true;
        }

        $after = $pdfBytes[$afterOffset];

        return ctype_space($after) || str_contains('[]()<>{}/%', $after);
    }

    /**
     * @param array<int, array{type: int, generation: int, offset: int}> $entries
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<int, array{type: int, generation: int, offset: int}>
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
     * @param array<int, array{type: int, generation: int, offset: int}> $entries
     * @param array<int, list<array{generation: int, offset: int, bodyStart?: int, bodyEnd?: int, body: string}>> $definitions
     * @param array<string, mixed> $trailer
     * @return array<int, array{type: int, generation: int, offset: int}>
     */
    private function repairCurrentUpdateXrefTableRows(
        string $pdfBytes,
        array $entries,
        array $definitions,
        array $trailer,
        int $xrefOffset
    ): array {
        $previousOffset = $this->previousXrefOffsetForSection(
            $pdfBytes,
            $this->previousXrefOffsetFromTableTrailer($trailer, $definitions, $xrefOffset),
            $xrefOffset,
            $definitions
        );

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
     * @param array<string, mixed> $trailer
     * @param array<int, list<array{generation: int, offset: int, bodyStart?: int, bodyEnd?: int, body: string}>> $definitions
     */
    private function previousXrefOffsetFromTableTrailer(array $trailer, array $definitions, int $beforeOffset): ?int
    {
        $previous = $trailer['Prev'] ?? null;
        $direct = $this->intValue($previous);
        if ($direct !== null) {
            return $direct;
        }

        $reference = $this->refObjectReference($previous);
        if ($reference === null) {
            return null;
        }

        $definition = $this->directObjectDefinitionForGenerationBeforeOffset(
            $definitions[$reference['objectNumber']] ?? [],
            $reference['generation'],
            $beforeOffset
        );
        if ($definition === null) {
            return null;
        }

        $index = 0;
        $value = $this->parseValue(trim($definition['body']), $index);

        return $this->intValue($value);
    }

    /**
     * @return array<int, array{type: int, generation: int, offset: int}>|null
     */
    private function xrefTableRows(string $sectionBody): ?array
    {
        $entries = [];
        $lines = preg_split('/\r\n|\r|\n/', $sectionBody);
        if (!is_array($lines)) {
            return null;
        }

        $foundSection = false;
        $lineCount = count($lines);
        for ($lineIndex = 0; $lineIndex < $lineCount; $lineIndex++) {
            $line = trim($lines[$lineIndex]);
            if ($line === '' || str_starts_with($line, '%')) {
                continue;
            }

            if (preg_match('/^(\d+)\s+(\d+)(?:\s*(?:%.*)?)$/', $line, $section) !== 1) {
                if (!$foundSection) {
                    return null;
                }

                continue;
            }

            $foundSection = true;
            $firstObject = (int) $section[1];
            $rowCount = (int) $section[2];
            if ($rowCount <= 0) {
                return $entries === [] ? null : $entries;
            }

            $entriesBeforeSection = $entries;
            $rowIndex = 0;
            while ($rowIndex < $rowCount && ++$lineIndex < $lineCount) {
                $row = trim($lines[$lineIndex]);
                if ($row === '' || str_starts_with($row, '%')) {
                    continue;
                }
                if (preg_match('/^(\d{10})\s+(\d{5})\s+([nf])(?:\s*(?:%.*)?)$/', $row, $match) !== 1) {
                    if ($entriesBeforeSection !== []) {
                        return $entriesBeforeSection;
                    }

                    return null;
                }

                $entries[$firstObject + $rowIndex] = [
                    'type' => $match[3] === 'n' ? 1 : 0,
                    'generation' => (int) $match[2],
                    'offset' => (int) $match[1],
                ];
                $rowIndex++;
            }

            if ($rowIndex < $rowCount) {
                if ($entriesBeforeSection !== []) {
                    return $entriesBeforeSection;
                }

                return null;
            }
        }

        return $foundSection ? $entries : null;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array{dictionary: array<string, mixed>, stream: string, offset: int}|null
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
                    'offset' => $definition['offset'],
                ];
            }
        }

        return null;
    }

    /**
     * @param array{dictionary: array<string, mixed>, stream: string, offset?: int} $section
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<int, array{type: int, generation?: int, offset?: int}>
     */
    private function xrefStreamEntriesFromSection(array $section, array $definitions = []): array
    {
        $decoded = $section['stream'];
        foreach ($this->filterNames($this->xrefStreamDictionaryValue($section, 'Filter', $definitions), []) as $filter) {
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

        $widths = $this->xrefStreamFieldWidths($this->xrefStreamDictionaryValue($section, 'W', $definitions));
        if ($widths === null) {
            return [];
        }

        $entryWidth = array_sum($widths);
        if ($entryWidth <= 0) {
            return [];
        }

        $decodedEntryCount = intdiv(strlen($decoded), $entryWidth);
        $previousOffset = $definitions !== [] && isset($section['offset'])
            ? $this->intValue($this->xrefStreamDictionaryValue($section, 'Prev', $definitions))
            : null;
        $xrefOffset = (int) ($section['offset'] ?? -1);
        $entries = [];
        $fieldOffset = 0;
        foreach ($this->xrefStreamIndexRanges($section, $decodedEntryCount, $definitions) as $range) {
            for ($row = 0; $row < $range['count'] && $fieldOffset + $entryWidth <= strlen($decoded); $row++) {
                $objectNumber = $range['first'] + $row;
                $type = $widths[0] === 0 ? 1 : $this->xrefStreamFieldValue($decoded, $fieldOffset, $widths[0]);
                $fieldTwo = $this->xrefStreamFieldValue($decoded, $fieldOffset, $widths[1]);
                $fieldThree = $this->xrefStreamFieldValue($decoded, $fieldOffset, $widths[2]);

                if ($type === 1) {
                    $generation = $fieldThree;
                    if ($definitions !== [] && $widths[1] > 0 && $previousOffset !== null && $previousOffset >= 0) {
                        $rowObjectNumber = $objectNumber;
                        $rowGeneration = $generation;
                        $offsetOwner = $this->directObjectDefinitionAtOffset($definitions, $fieldTwo);
                        $updateOwner = $this->currentUpdateDirectObjectDefinitionForStaleXrefOffset(
                            $rowObjectNumber,
                            $rowGeneration,
                            $offsetOwner,
                            $previousOffset,
                            $xrefOffset,
                            $definitions
                        );
                        if ($updateOwner !== null) {
                            $objectNumber = $rowObjectNumber;
                            $fieldTwo = $updateOwner['offset'];
                            $generation = $updateOwner['generation'];
                        } elseif (
                            $offsetOwner !== null
                            && $previousOffset >= 0
                            && $offsetOwner['offset'] > $previousOffset
                            && $offsetOwner['offset'] < $xrefOffset
                        ) {
                            $objectNumber = $offsetOwner['objectNumber'];
                            $generation = $offsetOwner['generation'];
                        }
                    }

                    $entries[$objectNumber] = [
                        'type' => 1,
                        'offset' => $fieldTwo,
                        'generation' => $generation,
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
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array{objectNumber: int, generation: int, offset: int, body: string}|null
     */
    private function directObjectDefinitionAtOffset(array $definitions, int $offset): ?array
    {
        foreach ($definitions as $objectNumber => $entries) {
            foreach ($entries as $definition) {
                if ($definition['offset'] !== $offset) {
                    continue;
                }

                return [
                    'objectNumber' => (int) $objectNumber,
                    'generation' => $definition['generation'],
                    'offset' => $definition['offset'],
                    'body' => $definition['body'],
                ];
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
     * @param array{dictionary: array<string, mixed>, offset?: int} $section
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     */
    private function xrefStreamDictionaryValue(array $section, string $name, array $definitions): mixed
    {
        $value = $section['dictionary'][$name] ?? null;
        if ($definitions === [] || !isset($section['offset'])) {
            return $value;
        }

        $reference = $this->refObjectReference($value);
        if ($reference === null) {
            return $value;
        }

        $definition = $this->directObjectDefinitionForGenerationBeforeOffset(
            $definitions[$reference['objectNumber']] ?? [],
            $reference['generation'],
            (int) $section['offset']
        );
        if ($definition === null) {
            return $value;
        }

        $index = 0;
        $resolved = $this->parseValue(trim($definition['body']), $index);

        return $resolved ?? $value;
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
     * @param array{dictionary: array<string, mixed>, offset?: int} $section
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return list<array{first: int, count: int}>
     */
    private function xrefStreamIndexRanges(array $section, int $decodedEntryCount, array $definitions = []): array
    {
        $index = $this->arrayValue($this->xrefStreamDictionaryValue($section, 'Index', $definitions));
        if ($index === null || $index === []) {
            $size = $this->intValue($this->xrefStreamDictionaryValue($section, 'Size', $definitions));
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

        return $this->decodePdfDocEncoding($bytes);
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

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return list<float>
     */
    private function resolvedNumberArray(mixed $value, array $objects): array
    {
        return $this->numberArray($this->resolveValue($value, $objects));
    }

    private function numberValue(mixed $value): ?float
    {
        return is_int($value) || is_float($value) ? (float) $value : null;
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
