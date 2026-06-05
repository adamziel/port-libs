<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use Exception;

final class PdfMetadataExtractor
{
    /**
     * @var array<int, array{generation: int, body: string}>
     */
    private array $currentObjectReferenceOwners = [];

    private const VIEWER_PREFERENCE_NAME_VALUES = [
        'NonFullScreenPageMode' => ['key' => 'non_full_screen_page_mode', 'allowed' => ['UseNone', 'UseOutlines', 'UseThumbs', 'UseOC']],
        'Direction' => ['key' => 'direction', 'allowed' => ['L2R', 'R2L']],
        'ViewArea' => ['key' => 'view_area', 'allowed' => ['MediaBox', 'CropBox', 'BleedBox', 'TrimBox', 'ArtBox']],
        'ViewClip' => ['key' => 'view_clip', 'allowed' => ['MediaBox', 'CropBox', 'BleedBox', 'TrimBox', 'ArtBox']],
        'PrintArea' => ['key' => 'print_area', 'allowed' => ['MediaBox', 'CropBox', 'BleedBox', 'TrimBox', 'ArtBox']],
        'PrintClip' => ['key' => 'print_clip', 'allowed' => ['MediaBox', 'CropBox', 'BleedBox', 'TrimBox', 'ArtBox']],
        'PrintScaling' => ['key' => 'print_scaling', 'allowed' => ['AppDefault', 'None']],
        'Duplex' => ['key' => 'duplex', 'allowed' => ['Simplex', 'DuplexFlipShortEdge', 'DuplexFlipLongEdge']],
    ];
    private const VIEWER_PREFERENCE_ENFORCEABLE_NAMES = [
        'HideToolbar',
        'HideMenubar',
        'HideWindowUI',
        'FitWindow',
        'CenterWindow',
        'DisplayDocTitle',
        'NonFullScreenPageMode',
        'Direction',
        'ViewArea',
        'ViewClip',
        'PrintArea',
        'PrintClip',
        'PrintScaling',
        'Duplex',
        'PickTrayByPDFSize',
        'PrintPageRange',
        'NumCopies',
    ];
    private const STANDARD_PERMISSION_FLAGS = [
        ['mask' => 4, 'name' => 'print', 'minimum_revision' => 2],
        ['mask' => 8, 'name' => 'modify_contents', 'minimum_revision' => 2],
        ['mask' => 16, 'name' => 'copy_or_extract', 'minimum_revision' => 2],
        ['mask' => 32, 'name' => 'add_or_modify_annotations', 'minimum_revision' => 2],
        ['mask' => 256, 'name' => 'fill_form_fields', 'minimum_revision' => 3],
        ['mask' => 512, 'name' => 'extract_for_accessibility', 'minimum_revision' => 3],
        ['mask' => 1024, 'name' => 'assemble_document', 'minimum_revision' => 3],
        ['mask' => 2048, 'name' => 'high_quality_print', 'minimum_revision' => 3],
    ];
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
    private const SPECIALIZED_CATALOG_NAME_TREE_KEYS = [
        'Dests' => true,
        'EmbeddedFiles' => true,
    ];
    private const VALID_DESTINATION_VIEW_NAMES = [
        'Fit' => true,
        'FitB' => true,
        'FitBH' => true,
        'FitBV' => true,
        'FitH' => true,
        'FitR' => true,
        'FitV' => true,
        'XYZ' => true,
    ];
    private const NON_OUTLINE_ITEM_TYPES = [
        'Action' => true,
        'Annot' => true,
        'Bead' => true,
        'Catalog' => true,
        'EmbeddedFile' => true,
        'Filespec' => true,
        'Font' => true,
        'Metadata' => true,
        'ObjStm' => true,
        'Outlines' => true,
        'Page' => true,
        'Pages' => true,
        'StructElem' => true,
        'StructTreeRoot' => true,
        'Thread' => true,
        'XObject' => true,
        'XRef' => true,
    ];

    private const NS_DC = 'http://purl.org/dc/elements/1.1/';
    private const NS_PDF = 'http://ns.adobe.com/pdf/1.3/';
    private const NS_PDFA_EXTENSION = 'http://www.aiim.org/pdfa/ns/extension/';
    private const NS_PDFA_PROPERTY = 'http://www.aiim.org/pdfa/ns/property#';
    private const NS_PDFA_SCHEMA = 'http://www.aiim.org/pdfa/ns/schema#';
    private const NS_RDF = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    private const NS_XMP = 'http://ns.adobe.com/xap/1.0/';
    private const NS_XML = 'http://www.w3.org/XML/1998/namespace';
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
     * Native metadata boundary for PDF Catalog /Metadata XMP streams plus the
     * trailer /Info dictionary and trailer /ID file identifiers used by
     * pdfium-backed document metadata flows.
     *
     * @return array{
     *     source: list<string>,
     *     xmp: array<string, mixed>,
     *     info: array<string, mixed>,
     *     catalog?: array<string, mixed>,
     *     output_intents: list<array<string, mixed>>,
     *     encryption?: array<string, mixed>,
     *     trailer_ids?: array<string, mixed>,
     *     document_fingerprint?: string,
     *     document_fingerprint_source?: string,
     *     title?: string,
     *     authors?: list<string>,
     *     description?: string,
     *     keywords?: list<string>,
     *     creator_tool?: string,
     *     producer?: string,
     *     created_at?: string,
     *     created_at_utc?: string,
     *     modified_at?: string,
     *     modified_at_utc?: string,
     *     metadata_date?: string,
     *     metadata_date_utc?: string,
     *     language?: string,
     *     mark_info?: array<string, mixed>,
     *     metadata_stream_review?: array<string, mixed>,
     *     page_layout?: string,
     *     page_mode?: string,
     *     viewer_preferences?: array<string, mixed>,
     *     collection?: array<string, mixed>,
     *     associated_files?: list<array<string, mixed>>,
     *     embedded_files?: list<array<string, mixed>>,
     *     document_name_trees?: array<string, mixed>,
     *     structure_tree?: array<string, mixed>,
     *     document_destinations?: array<string, mixed>,
     *     document_outline?: array<string, mixed>,
     *     document_security_store?: array<string, mixed>,
     *     pdfa_associated_name_tree?: array<string, mixed>,
     *     pdfa_associated_files?: array<string, mixed>,
     *     pdfa?: array{has_output_intent: bool, output_condition_identifiers: list<string>, profile_sha256: list<string>}
     * }
     */
    public function extractDocumentMetadata(string $pdfBytes): array
    {
        $pdfBytes = $this->bytesThroughCurrentEof($pdfBytes);
        $objects = $this->pdfObjects($pdfBytes);
        $encryption = $this->extractEncryptionMetadata($pdfBytes, $objects);
        $metadataSourcePolicy = $this->encryptedMetadataSourcePolicy($pdfBytes, $objects, $encryption);
        if ($metadataSourcePolicy !== []) {
            $encryption['metadata_source_policy'] = $metadataSourcePolicy;
        }

        $catalog = $this->extractCatalogReviewMetadata($pdfBytes, $objects);
        if ($encryption !== []) {
            $catalog = $this->redactEncryptedCatalogAssociatedFileMetadata($catalog, $encryption);
        }
        $xmp = $this->shouldReadXmpMetadata($metadataSourcePolicy)
            ? $this->extractXmpMetadata($pdfBytes, $objects)
            : [];
        $info = $this->shouldReadInfoMetadata($metadataSourcePolicy)
            ? $this->extractInfoMetadata($pdfBytes, $objects)
            : [];
        $outputIntents = $this->shouldReadOutputIntentMetadata($metadataSourcePolicy)
            ? $this->extractOutputIntentMetadata($pdfBytes, $objects)
            : [];
        $trailerIds = $this->extractTrailerIdMetadata($pdfBytes);

        return $this->mergedMetadata($xmp, $info, $outputIntents, $catalog, $trailerIds, $encryption);
    }

    /**
     * PDF encryption applies to strings and streams unless the security
     * handler explicitly leaves the metadata stream unencrypted. Keep these
     * review sources fail-closed when no native decryption has happened.
     *
     * @param array<int, string> $objects
     * @param array<string, mixed> $encryption
     * @return array<string, mixed>
     */
    private function encryptedMetadataSourcePolicy(string $pdfBytes, array $objects, array $encryption): array
    {
        if ($encryption === []) {
            return [];
        }

        $catalog = $this->catalogObjectBody($pdfBytes, $objects);
        $trailer = $this->trailerDictionaryBody($pdfBytes);
        $encryptMetadata = ($encryption['encrypt_metadata'] ?? true) !== false;
        $hasXmp = $catalog !== null && $this->dictionaryTopLevelRawValue($catalog, 'Metadata') !== null;
        $hasInfo = $trailer !== null && $this->dictionaryRawValue($trailer, 'Info') !== null;
        $hasOutputIntents = $catalog !== null && $this->dictionaryTopLevelRawValue($catalog, 'OutputIntents') !== null;
        $hasAssociatedFiles = $catalog !== null && $this->catalogHasAssociatedFileMetadata($catalog, $objects);

        $suppressed = [];
        $preserved = [];
        $xmpPolicy = 'absent';
        if ($hasXmp && $encryptMetadata) {
            $suppressed[] = 'xmp';
            $xmpPolicy = 'suppressed_encrypted_metadata_stream';
        } elseif ($hasXmp) {
            $preserved[] = 'xmp';
            $xmpPolicy = 'preserved_unencrypted_by_encrypt_metadata_false';
        }

        $infoPolicy = 'absent';
        if ($hasInfo) {
            $suppressed[] = 'info';
            $infoPolicy = 'suppressed_encrypted_document_strings';
        }

        $outputIntentPolicy = 'absent';
        if ($hasOutputIntents) {
            $suppressed[] = 'output_intents';
            $outputIntentPolicy = 'suppressed_encrypted_stream_or_strings';
        }

        $policy = [
            'encrypted_document' => true,
            'decryption_performed' => false,
            'encrypt_metadata' => !$encryptMetadata ? false : true,
            'encrypt_metadata_explicit' => (bool) ($encryption['encrypt_metadata_explicit'] ?? false),
            'encrypt_metadata_trusted' => (bool) ($encryption['encrypt_metadata_trusted'] ?? true),
            'encrypt_metadata_defaulted' => (bool) ($encryption['encrypt_metadata_defaulted'] ?? false),
            'encrypt_metadata_defaulted_fail_closed' => (bool) ($encryption['encrypt_metadata_defaulted_fail_closed'] ?? false),
            'encrypt_metadata_status' => is_string($encryption['encrypt_metadata_status'] ?? null)
                ? $encryption['encrypt_metadata_status']
                : null,
            'encrypt_metadata_declaration_review' => is_array($encryption['encrypt_metadata_declaration_review'] ?? null)
                ? $encryption['encrypt_metadata_declaration_review']
                : [],
            'xmp_stream_policy' => $xmpPolicy,
            'info_dictionary_policy' => $infoPolicy,
            'output_intents_policy' => $outputIntentPolicy,
            'suppressed_sources' => $suppressed,
            'preserved_sources' => $preserved,
            'raw_encrypted_metadata_parsed' => false,
        ];

        if ($hasAssociatedFiles) {
            $suppressed[] = 'associated_files';
            $policy['associated_files_policy'] = 'suppressed_encrypted_associated_file_metadata';
            $policy['associated_files_review'] = $this->encryptedAssociatedFileReviewPolicy($encryption);
            $policy['suppressed_sources'] = $suppressed;
        }

        return $policy;
    }

    /**
     * @param array<int, string> $objects
     */
    private function catalogHasAssociatedFileMetadata(string $catalog, array $objects): bool
    {
        if (
            $this->dictionaryTopLevelRawValue($catalog, 'AF') !== null
            || $this->dictionaryTopLevelRawValue($catalog, 'Collection') !== null
        ) {
            return true;
        }

        $names = $this->resolveDictionaryFromValue($this->dictionaryTopLevelRawValue($catalog, 'Names'), $objects);
        if ($names === null) {
            return false;
        }

        return $this->dictionaryTopLevelRawValue($names['body'], 'EmbeddedFiles') !== null;
    }

    /**
     * @param array<string, mixed> $catalog
     * @param array<string, mixed> $encryption
     * @return array<string, mixed>
     */
    private function redactEncryptedCatalogAssociatedFileMetadata(array $catalog, array $encryption): array
    {
        $policy = $this->encryptedAssociatedFileReviewPolicy($encryption);
        $changed = false;

        foreach (['associated_files', 'embedded_files'] as $field) {
            if (!isset($catalog[$field]) || !is_array($catalog[$field])) {
                continue;
            }

            $catalog[$field] = $this->redactEncryptedAssociatedFileRows($catalog[$field], $policy);
            $changed = true;
        }

        if (isset($catalog['collection']) && is_array($catalog['collection'])) {
            $collection = $catalog['collection'];
            if (isset($collection['associated_files']) && is_array($collection['associated_files'])) {
                $collection['associated_files'] = $this->redactEncryptedAssociatedFileRows($collection['associated_files'], $policy);
                $catalog['collection'] = $collection;
                $changed = true;
            }
        }

        if ($changed) {
            $catalog['encrypted_associated_files_policy'] = $policy;
        }

        return $catalog;
    }

    /**
     * @param list<array<string, mixed>>|array<int, mixed> $files
     * @param array<string, mixed> $policy
     * @return list<array<string, mixed>>
     */
    private function redactEncryptedAssociatedFileRows(array $files, array $policy): array
    {
        $redacted = [];
        foreach ($files as $file) {
            if (!is_array($file)) {
                continue;
            }

            $redacted[] = $this->redactEncryptedAssociatedFileRow($file, $policy);
        }

        return $redacted;
    }

    /**
     * @param array<string, mixed> $file
     * @param array<string, mixed> $policy
     * @return array<string, mixed>
     */
    private function redactEncryptedAssociatedFileRow(array $file, array $policy): array
    {
        $stringsEncrypted = ($policy['file_spec_strings_policy'] ?? null) === 'suppressed_encrypted_strings';
        $payloadEncrypted = ($policy['embedded_file_stream_policy'] ?? null) === 'suppressed_encrypted_embedded_file_streams';

        if ($stringsEncrypted) {
            foreach (['name', 'filename', 'unicode_filename', 'platform_filename', 'description', 'language'] as $key) {
                unset($file[$key]);
            }
        }

        foreach ([
            'metadata_review',
            'output_intents_review',
            'piece_info',
            'collection_item',
            'collection_field_values',
            'portfolio_item',
            'portfolio_field_values',
        ] as $key) {
            unset($file[$key]);
        }

        if ($payloadEncrypted) {
            foreach ([
                'size',
                'content_sha256',
                'mime_type',
                'filters',
                'checksum',
                'checksum_algorithm',
                'computed_checksum',
                'checksum_matches',
                'created_at',
                'modified_at',
            ] as $key) {
                unset($file[$key]);
            }
        }

        if (isset($file['related_files']) && is_array($file['related_files'])) {
            $file['related_files'] = $this->redactEncryptedRelatedFileRows($file['related_files'], $policy);
        }

        $file['encryption_policy'] = $policy;
        $provenance = $file['provenance_review'] ?? [];
        $file['provenance_review'] = $this->redactEncryptedAssociatedFileProvenance(
            is_array($provenance) ? $provenance : [],
            $file,
            $policy
        );

        return $file;
    }

    /**
     * @param list<array<string, mixed>>|array<int, mixed> $relatedFiles
     * @param array<string, mixed> $policy
     * @return list<array<string, mixed>>
     */
    private function redactEncryptedRelatedFileRows(array $relatedFiles, array $policy): array
    {
        $rows = [];
        foreach ($relatedFiles as $row) {
            if (!is_array($row)) {
                continue;
            }

            foreach ([
                'size',
                'content_sha256',
                'mime_type',
                'filters',
                'checksum',
                'checksum_algorithm',
                'computed_checksum',
                'checksum_matches',
                'created_at',
                'modified_at',
            ] as $key) {
                unset($row[$key]);
            }
            $row['encryption_policy'] = $policy;
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $provenance
     * @param array<string, mixed> $file
     * @param array<string, mixed> $policy
     * @return array<string, mixed>
     */
    private function redactEncryptedAssociatedFileProvenance(array $provenance, array $file, array $policy): array
    {
        $metadata = [
            'source' => $provenance['source'] ?? 'associated_file_provenance',
            'review_only' => true,
            'payload_included' => false,
            'encryption_policy' => $policy,
        ];

        $sources = [];
        $relationship = $file['relationship'] ?? $provenance['relationship'] ?? null;
        if (is_string($relationship) && $relationship !== '') {
            $metadata['relationship'] = $relationship;
            $metadata['relationship_role'] = self::ASSOCIATED_FILE_RELATIONSHIP_ROLES[$relationship] ?? 'unrecognized';
            $metadata['relationship_status'] = array_key_exists($relationship, self::ASSOCIATED_FILE_RELATIONSHIP_ROLES)
                ? 'standard_pdf_associated_file_relationship'
                : 'unrecognized_pdf_associated_file_relationship';
            $sources[] = 'filespec_afrelationship';
        } elseif (is_string($provenance['relationship_status'] ?? null)) {
            $metadata['relationship_status'] = $provenance['relationship_status'];
        } else {
            $metadata['relationship_status'] = 'encrypted_or_missing_pdf_associated_file_relationship';
        }

        $sources[] = 'encrypted_file_spec_boundary';
        $metadata['sources'] = $this->uniqueStrings($sources);

        return $metadata;
    }

    /**
     * @param array<string, mixed> $encryption
     * @return array<string, mixed>
     */
    private function encryptedAssociatedFileReviewPolicy(array $encryption): array
    {
        $stringFilter = is_string($encryption['string_filter'] ?? null) ? $encryption['string_filter'] : null;
        $streamFilter = is_string($encryption['stream_filter'] ?? null) ? $encryption['stream_filter'] : null;
        $embeddedFileFilter = is_string($encryption['embedded_file_filter'] ?? null) ? $encryption['embedded_file_filter'] : null;
        $cryptFilters = is_array($encryption['crypt_filters'] ?? null) ? $encryption['crypt_filters'] : [];
        $stringFilterStatus = $this->encryptedAssociatedFileCryptFilterStatus($stringFilter, $cryptFilters);
        $streamFilterStatus = $this->encryptedAssociatedFileCryptFilterStatus($streamFilter, $cryptFilters);
        $embeddedFileFilterStatus = $this->encryptedAssociatedFileCryptFilterStatus($embeddedFileFilter, $cryptFilters);
        $cryptFilterDictionaryReview = is_array($encryption['crypt_filter_dictionary_declaration_review'] ?? null)
            ? $encryption['crypt_filter_dictionary_declaration_review']
            : [];
        $cryptFilterDictionaryFailClosed = ($cryptFilterDictionaryReview['fail_closed'] ?? false) === true;
        $cryptFilterRoleReview = is_array($encryption['crypt_filter_role_declaration_review'] ?? null)
            ? $encryption['crypt_filter_role_declaration_review']
            : [];
        $roleFailClosedNames = array_values(array_filter(
            $cryptFilterRoleReview['fail_closed_role_names'] ?? [],
            static fn (mixed $value): bool => is_string($value)
        ));
        $stringRoleFailClosed = in_array('document_strings', $roleFailClosedNames, true);
        $embeddedFileRoleFailClosed = in_array('embedded_file_streams', $roleFailClosedNames, true)
            || (
                ($encryption['embedded_file_filter_defaulted_from_stream_filter'] ?? false) === true
                && in_array('document_streams', $roleFailClosedNames, true)
            );
        $stringsEncrypted = $stringFilterStatus !== 'identity_crypt_filter' || $stringRoleFailClosed;
        $embeddedStreamsEncrypted = $embeddedFileFilterStatus !== 'identity_crypt_filter' || $embeddedFileRoleFailClosed;

        $policy = [
            'source' => 'encrypted_associated_file_review',
            'encrypted_document' => true,
            'decryption_performed' => false,
            'file_spec_strings_policy' => $stringsEncrypted
                ? 'suppressed_encrypted_strings'
                : 'preserved_identity_crypt_filter',
            'embedded_file_stream_policy' => $embeddedStreamsEncrypted
                ? 'suppressed_encrypted_embedded_file_streams'
                : 'preserved_identity_crypt_filter',
            'metadata_stream_policy' => 'suppressed_encrypted_associated_metadata_streams',
            'output_intents_policy' => 'suppressed_encrypted_associated_output_intents',
            'payload_hash_available' => !$embeddedStreamsEncrypted,
            'xmp_summary_available' => false,
            'attachment_output_intents_available' => false,
            'payload_content_included' => false,
            'raw_encrypted_bytes_exposed' => false,
            'executes_decryption' => false,
        ];

        if ($cryptFilterDictionaryFailClosed) {
            $policy['file_spec_strings_policy'] = 'suppressed_encrypted_strings';
            $policy['embedded_file_stream_policy'] = 'suppressed_encrypted_embedded_file_streams';
            $policy['payload_hash_available'] = false;
            $policy['crypt_filter_dictionary_declaration_review'] = $cryptFilterDictionaryReview;
            $policy['crypt_filter_dictionary_status'] = $cryptFilterDictionaryReview['status'] ?? null;
            $policy['crypt_filter_dictionary_fail_closed'] = true;
            $policy['crypt_filter_dictionary_policy'] = 'suppressed_malformed_crypt_filter_dictionary';
        }

        if ($roleFailClosedNames !== []) {
            $policy['crypt_filter_role_declaration_review'] = $cryptFilterRoleReview;
            $policy['crypt_filter_role_declaration_status'] = 'malformed_crypt_filter_role_entry_review';
            $policy['crypt_filter_role_fail_closed_role_names'] = $roleFailClosedNames;
            $policy['crypt_filter_role_fail_closed_pdf_names'] = array_values(array_filter(
                $cryptFilterRoleReview['fail_closed_pdf_names'] ?? [],
                static fn (mixed $value): bool => is_string($value)
            ));
            $policy['crypt_filter_role_policy'] = 'suppressed_malformed_crypt_filter_role';
        }
        if ($stringRoleFailClosed) {
            $policy['file_spec_strings_policy_reason'] = 'suppressed_malformed_crypt_filter_role';
        }
        if ($embeddedFileRoleFailClosed) {
            $policy['embedded_file_stream_policy_reason'] = 'suppressed_malformed_crypt_filter_role';
        }

        if ($stringFilter !== null) {
            $policy['string_filter'] = $stringFilter;
            $policy['string_filter_status'] = $stringFilterStatus;
        }
        if ($streamFilter !== null) {
            $policy['stream_filter'] = $streamFilter;
            $policy['stream_filter_status'] = $streamFilterStatus;
        }
        if ($embeddedFileFilter !== null) {
            $policy['embedded_file_filter'] = $embeddedFileFilter;
            $policy['embedded_file_filter_status'] = $embeddedFileFilterStatus;
        }
        if (is_string($encryption['filter'] ?? null)) {
            $policy['security_handler'] = $encryption['filter'];
        }

        return $policy;
    }

    /**
     * @param array<string, mixed> $cryptFilters
     */
    private function encryptedAssociatedFileCryptFilterStatus(?string $filterName, array $cryptFilters): string
    {
        if ($filterName === 'Identity') {
            return 'identity_crypt_filter';
        }

        if ($filterName === null || $filterName === '') {
            return 'undeclared_crypt_filter_fail_closed';
        }

        $filter = is_array($cryptFilters[$filterName] ?? null) ? $cryptFilters[$filterName] : null;
        if ($filter === null) {
            return 'missing_declared_crypt_filter';
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
     * @param array<string, mixed> $metadataSourcePolicy
     */
    private function shouldReadXmpMetadata(array $metadataSourcePolicy): bool
    {
        return $metadataSourcePolicy === []
            || ($metadataSourcePolicy['xmp_stream_policy'] ?? null) === 'preserved_unencrypted_by_encrypt_metadata_false';
    }

    /**
     * @param array<string, mixed> $metadataSourcePolicy
     */
    private function shouldReadInfoMetadata(array $metadataSourcePolicy): bool
    {
        return $metadataSourcePolicy === [];
    }

    /**
     * @param array<string, mixed> $metadataSourcePolicy
     */
    private function shouldReadOutputIntentMetadata(array $metadataSourcePolicy): bool
    {
        return $metadataSourcePolicy === [];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function extractXmpMetadata(string $pdfBytes, array $objects): array
    {
        $catalog = $this->catalogObjectBody($pdfBytes, $objects);
        if ($catalog === null) {
            return [];
        }

        $value = $this->dictionaryTopLevelRawValue($catalog, 'Metadata');
        if ($value === null || preg_match('/^(\d+)\s+\d+\s+R\b/s', trim($value), $match) !== 1) {
            return [];
        }

        $objectBody = $this->objectBodyFromReferenceValue($value, $objects);
        if ($objectBody === null) {
            return [];
        }

        $stream = $this->decodeStreamEntryObject($objectBody, $objects);
        if (
            $stream === null
            || !$this->metadataStreamObjectConsumesSingleStreamToken($objectBody, $objects)
            || !$this->isDocumentXmpMetadataStream($stream['dictionary'], $objects)
        ) {
            return [];
        }

        return $this->parseXmpPacket($stream['content']);
    }

    /**
     * Root document XMP is only promoted when Catalog /Metadata targets a PDF
     * metadata XML stream. Other XML-like streams stay review-only.
     *
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function catalogMetadataStreamBoundaryReview(?string $value, array $objects): array
    {
        if ($value === null) {
            return [];
        }
        if ($this->trimPdfWhitespaceAndComments($value) === 'null') {
            return [];
        }

        $base = [
            'source' => 'catalog_metadata_stream_boundary',
            'review_only' => true,
            'payload_included' => false,
            'accepted_as_document_xmp' => false,
        ];

        $objectNumber = $this->objectNumberFromReference($value);
        if ($objectNumber === null) {
            return $base + [
                'status' => 'rejected_non_indirect_metadata_reference',
            ];
        }

        $objectBody = $this->objectBodyFromReferenceValue($value, $objects);
        if ($objectBody === null) {
            return $base + [
                'status' => 'unresolved_metadata_reference',
                'object_number' => $objectNumber,
            ];
        }

        $stream = $this->decodeStreamEntryObject($objectBody, $objects);
        if ($stream === null) {
            $dictionary = $this->dictionaryObjectBody($objectBody);
            if ($dictionary !== null && !$this->streamObjectHasStreamKeyword($objectBody)) {
                $review = $base + [
                    'status' => 'rejected_non_stream_metadata_object',
                    'object_number' => $objectNumber,
                ];
                foreach ($this->metadataStreamDictionaryLabels($dictionary, $objects) as $key => $metadataValue) {
                    $review[$key] = $metadataValue;
                }

                $declaredLength = $this->streamLength($dictionary, $objects);
                if ($declaredLength !== null) {
                    $review['declared_length'] = $declaredLength;
                }

                return $review;
            }

            $review = $base + [
                'status' => 'unreadable_metadata_stream',
                'object_number' => $objectNumber,
            ];
            if ($dictionary !== null) {
                foreach ($this->metadataStreamDictionaryLabels($dictionary, $objects) as $key => $metadataValue) {
                    $review[$key] = $metadataValue;
                }

                $filters = $this->streamFilters($dictionary, $objects);
                if ($filters !== []) {
                    $review['filters'] = $filters;
                }

                $declaredLength = $this->streamLength($dictionary, $objects);
                if ($declaredLength !== null) {
                    $review['declared_length'] = $declaredLength;
                }
            }

            return $review;
        }

        if (!$this->metadataStreamObjectConsumesSingleStreamToken($objectBody, $objects)) {
            $review = $base + [
                'status' => 'rejected_malformed_metadata_stream_object',
                'object_number' => $objectNumber,
                'bytes' => strlen($stream['content']),
                'sha256' => hash('sha256', $stream['content']),
            ];

            foreach ($this->metadataStreamDictionaryLabels($stream['dictionary'], $objects) as $key => $metadataValue) {
                $review[$key] = $metadataValue;
            }

            $filters = $this->streamFilters($stream['dictionary'], $objects);
            if ($filters !== []) {
                $review['filters'] = $filters;
            }

            $xmpSummary = $this->xmpPacketReviewSummary($stream['content']);
            if ($xmpSummary !== []) {
                $review['xmp_summary'] = $xmpSummary;
            }

            return $review;
        }

        if ($this->isDocumentXmpMetadataStream($stream['dictionary'], $objects)) {
            $xmpSummary = $this->xmpPacketReviewSummary($stream['content']);
            if (($xmpSummary['status'] ?? null) === 'rejected_dtd_or_entity_declaration') {
                $review = $base + [
                    'status' => 'rejected_unsafe_document_xmp_stream',
                    'object_number' => $objectNumber,
                    'bytes' => strlen($stream['content']),
                    'sha256' => hash('sha256', $stream['content']),
                    'xmp_summary' => $xmpSummary,
                ];

                foreach ($this->metadataStreamDictionaryLabels($stream['dictionary'], $objects) as $key => $metadataValue) {
                    $review[$key] = $metadataValue;
                }

                $filters = $this->streamFilters($stream['dictionary'], $objects);
                if ($filters !== []) {
                    $review['filters'] = $filters;
                }

                return $review;
            }

            return [];
        }

        $review = $base + [
            'status' => 'rejected_non_metadata_xml_stream',
            'object_number' => $objectNumber,
            'bytes' => strlen($stream['content']),
            'sha256' => hash('sha256', $stream['content']),
        ];

        foreach ($this->metadataStreamDictionaryLabels($stream['dictionary'], $objects) as $key => $metadataValue) {
            $review[$key] = $metadataValue;
        }

        $filters = $this->streamFilters($stream['dictionary'], $objects);
        if ($filters !== []) {
            $review['filters'] = $filters;
        }

        $xmpSummary = $this->xmpPacketReviewSummary($stream['content']);
        if ($xmpSummary !== []) {
            $review['xmp_summary'] = $xmpSummary;
        }

        return $review;
    }

    /**
     * Catalog /Metadata may point to an indirect stream object, but that
     * object must not carry extra top-level tokens after the stream body.
     * Otherwise a malformed stream like `endstream /A ...` would promote XMP
     * while silently dropping action or sibling tokens.
     *
     * @param array<int, string> $objects
     */
    private function metadataStreamObjectConsumesSingleStreamToken(string $objectBody, array $objects): bool
    {
        $dictionaryOffset = $this->skipPdfWhitespace($objectBody, 0);
        $dictionary = $this->readPdfDictionaryAt($objectBody, $dictionaryOffset);
        if ($dictionary === null) {
            return false;
        }

        $streamKeywordOffset = $this->skipPdfWhitespace($objectBody, $dictionaryOffset + strlen($dictionary) + 4);
        if (!$this->pdfKeywordAt($objectBody, $streamKeywordOffset, 'stream')) {
            return false;
        }

        $streamStart = $streamKeywordOffset + strlen('stream');
        if (substr($objectBody, $streamStart, 2) === "\r\n") {
            $streamStart += 2;
        } elseif (($objectBody[$streamStart] ?? '') === "\n" || ($objectBody[$streamStart] ?? '') === "\r") {
            $streamStart++;
        }

        $streamEnd = $this->streamPayloadEndOffset($objectBody, $streamStart, $dictionary, $objects);
        if ($streamEnd === null || !$this->endstreamKeywordAt($objectBody, $streamEnd)) {
            return false;
        }

        $afterEndstream = $this->skipPdfWhitespace($objectBody, $streamEnd + strlen('endstream'));

        return $afterEndstream >= strlen($objectBody);
    }

    private function streamObjectHasStreamKeyword(string $objectBody): bool
    {
        $dictionaryOffset = $this->skipPdfWhitespace($objectBody, 0);
        $dictionary = $this->readPdfDictionaryAt($objectBody, $dictionaryOffset);
        if ($dictionary === null) {
            return false;
        }

        $streamKeywordOffset = $this->skipPdfWhitespace($objectBody, $dictionaryOffset + strlen($dictionary) + 4);

        return $this->pdfKeywordAt($objectBody, $streamKeywordOffset, 'stream');
    }

    /**
     * @param array<int, string> $objects
     */
    private function isDocumentXmpMetadataStream(string $dictionary, array $objects): bool
    {
        return $this->dictionaryNameValue($dictionary, 'Type', $objects) === 'Metadata'
            && $this->dictionaryNameValue($dictionary, 'Subtype', $objects) === 'XML';
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, string>
     */
    private function metadataStreamDictionaryLabels(string $dictionary, array $objects): array
    {
        $metadata = [];
        foreach ([
            'type' => $this->dictionaryNameValue($dictionary, 'Type', $objects),
            'subtype' => $this->dictionaryNameValue($dictionary, 'Subtype', $objects),
        ] as $key => $metadataValue) {
            if (is_string($metadataValue) && $metadataValue !== '') {
                $metadata[$key] = $metadataValue;
            }
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function extractInfoMetadata(string $pdfBytes, array $objects): array
    {
        $infoDictionary = $this->trailerInfoDictionary($pdfBytes, $objects);
        if ($infoDictionary === null) {
            return [];
        }

        $dictionary = $infoDictionary['body'];
        $fields = [];
        foreach (['Title', 'Author', 'Subject', 'Keywords', 'Creator', 'Producer', 'CreationDate', 'ModDate'] as $key) {
            $value = $this->dictionaryStringValue($dictionary, $key);
            if ($value !== null) {
                $fields[$key] = $value;
            }
        }

        $review = $this->trailerInfoReviewMetadata($dictionary, $objects);
        if ($review !== []) {
            $fields['review'] = $review;
        }

        return $fields;
    }

    /**
     * @param array<int, string> $objects
     * @return array{body: string, object: int|null}|null
     */
    private function trailerInfoDictionary(string $pdfBytes, array $objects): ?array
    {
        $definitions = $this->directObjectDefinitions($pdfBytes);
        if ($definitions !== []) {
            $reference = $this->trailerInfoReferenceFromStartxrefChain($pdfBytes, $objects, $definitions);
            if ($reference !== null) {
                $infoDictionary = $this->resolveDictionaryFromValue(
                    $reference['objectNumber'] . ' ' . $reference['generation'] . ' R',
                    $objects
                );
                if ($infoDictionary !== null) {
                    return $infoDictionary;
                }
            }

            if ($this->trailerInfoClearedByStartxrefChain($pdfBytes, $objects, $definitions)) {
                return null;
            }

            $reference = $this->trailerInfoReferenceFromLatestClassicXrefTable($pdfBytes, $objects, $definitions);
            if ($reference !== null) {
                $infoDictionary = $this->resolveDictionaryFromValue(
                    $reference['objectNumber'] . ' ' . $reference['generation'] . ' R',
                    $objects
                );
                if ($infoDictionary !== null) {
                    return $infoDictionary;
                }
            }
        }

        $trailer = $this->trailerDictionaryBody($pdfBytes);
        return $trailer === null
            ? null
            : $this->resolveDictionaryFromValue($this->dictionaryTopLevelRawValue($trailer, 'Info'), $objects);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, bodyStart?: int, bodyEnd?: int, body: string}>> $definitions
     * @return array{objectNumber: int, generation: int}|null
     */
    private function trailerInfoReferenceFromStartxrefChain(string $pdfBytes, array $objects, array $definitions): ?array
    {
        $offset = $this->startxrefOffsetWithClassicRebuild($pdfBytes, $definitions);
        if ($offset === null) {
            return null;
        }

        return $this->trailerInfoReferenceFromOffsetChain($pdfBytes, $offset, $objects, $definitions);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, bodyStart?: int, bodyEnd?: int, body: string}>> $definitions
     * @return array{objectNumber: int, generation: int}|null
     */
    private function trailerInfoReferenceFromLatestClassicXrefTable(string $pdfBytes, array $objects, array $definitions): ?array
    {
        $startxrefEntry = $this->latestStartxrefEntry($pdfBytes, $definitions);
        $offset = $this->latestClassicXrefTableOffset(
            $pdfBytes,
            $definitions,
            $startxrefEntry['tokenOffset'] ?? null
        );
        if ($offset === null) {
            return null;
        }

        return $this->trailerInfoReferenceFromOffsetChain($pdfBytes, $offset, $objects, $definitions);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, bodyStart?: int, bodyEnd?: int, body: string}>> $definitions
     * @param array<int, bool> $seenOffsets
     * @return array{objectNumber: int, generation: int}|null
     */
    private function trailerInfoReferenceFromOffsetChain(
        string $pdfBytes,
        int $offset,
        array $objects,
        array $definitions,
        array $seenOffsets = []
    ): ?array {
        if ($offset < 0 || isset($seenOffsets[$offset])) {
            return null;
        }
        $seenOffsets[$offset] = true;

        $tableSection = $this->xrefTableSectionAt($pdfBytes, $offset, $definitions, $objects);
        if ($tableSection !== null) {
            $infoValue = $this->dictionaryTopLevelRawValue($tableSection['trailer'], 'Info');
            $info = $this->objectReferenceFromValue($infoValue);
            if ($info !== null) {
                return $info;
            }
            if ($this->trailerExplicitlyClearsInfo($tableSection['trailer'])) {
                return null;
            }

            $hybridStreamOffset = $this->dictionaryIntegerValue($tableSection['trailer'], 'XRefStm', $objects);
            if ($hybridStreamOffset !== null && $hybridStreamOffset >= 0 && !isset($seenOffsets[$hybridStreamOffset])) {
                $streamSection = $this->xrefStreamSectionAtOffset($hybridStreamOffset, $definitions);
                if ($streamSection !== null) {
                    $info = $this->objectReferenceFromValue($this->dictionaryTopLevelRawValue($streamSection['body'], 'Info'));
                    if ($info !== null) {
                        return $info;
                    }
                    if ($this->trailerExplicitlyClearsInfo($streamSection['body'])) {
                        return null;
                    }
                }
            }

            if ($this->trailerExplicitlyClearsEncryption($tableSection['trailer'])) {
                return null;
            }

            $previousOffset = $this->previousXrefOffsetForSectionBody(
                $pdfBytes,
                $tableSection['trailer'],
                $offset,
                $definitions,
                $objects
            );
            return $previousOffset === null
                ? null
                : $this->trailerInfoReferenceFromOffsetChain($pdfBytes, $previousOffset, $objects, $definitions, $seenOffsets);
        }

        $streamSection = $this->xrefStreamSectionAtOffset($offset, $definitions);
        if ($streamSection === null) {
            return null;
        }

        $info = $this->objectReferenceFromValue($this->dictionaryTopLevelRawValue($streamSection['body'], 'Info'));
        if ($info !== null) {
            return $info;
        }
        if ($this->trailerExplicitlyClearsInfo($streamSection['body'])) {
            return null;
        }

        if ($this->trailerExplicitlyClearsEncryption($streamSection['body'])) {
            return null;
        }

        $previousOffset = $this->previousXrefOffsetForSectionBody(
            $pdfBytes,
            $streamSection['body'],
            $offset,
            $definitions,
            $objects
        );
        return $previousOffset === null
            ? null
            : $this->trailerInfoReferenceFromOffsetChain($pdfBytes, $previousOffset, $objects, $definitions, $seenOffsets);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, bodyStart?: int, bodyEnd?: int, body: string}>> $definitions
     */
    private function trailerInfoClearedByStartxrefChain(string $pdfBytes, array $objects, array $definitions): bool
    {
        $offset = $this->startxrefOffsetWithClassicRebuild($pdfBytes, $definitions);
        if ($offset === null) {
            return false;
        }

        $tableSection = $this->xrefTableSectionAt($pdfBytes, $offset, $definitions, $objects);
        if ($tableSection !== null) {
            if ($this->dictionaryTopLevelRawValue($tableSection['trailer'], 'Info') !== null) {
                return $this->trailerExplicitlyClearsInfo($tableSection['trailer']);
            }

            return $this->trailerExplicitlyClearsEncryption($tableSection['trailer']);
        }

        $streamSection = $this->xrefStreamSectionAtOffset($offset, $definitions);
        if ($streamSection === null) {
            return false;
        }

        if ($this->dictionaryTopLevelRawValue($streamSection['body'], 'Info') !== null) {
            return $this->trailerExplicitlyClearsInfo($streamSection['body']);
        }

        return $this->trailerExplicitlyClearsEncryption($streamSection['body']);
    }

    private function trailerExplicitlyClearsInfo(string $body): bool
    {
        $info = $this->dictionaryTopLevelRawValue($body, 'Info');

        return $info !== null && trim($info) === 'null';
    }

    private function trailerExplicitlyClearsEncryption(string $body): bool
    {
        $encrypt = $this->dictionaryTopLevelRawValue($body, 'Encrypt');

        return $encrypt !== null && trim($encrypt) === 'null';
    }

    /**
     * Trailer /Info dictionaries may carry standard extension keys such as
     * /Trapped plus producer-specific scalars. Keep them typed and review-only
     * instead of promoting them into title/author fallbacks.
     *
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function trailerInfoReviewMetadata(string $dictionary, array $objects): array
    {
        $review = [];
        foreach ($this->dictionaryTopLevelEntries($dictionary) as $key => $value) {
            $reviewValue = $this->reviewValueFromRaw($value, $objects);
            if ($reviewValue === null || $reviewValue === '' || (is_array($reviewValue) && $reviewValue === [])) {
                continue;
            }

            $review[$key] = $reviewValue;
        }

        return $review;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function extractOutputIntentMetadata(string $pdfBytes, array $objects): array
    {
        $catalog = $this->catalogObjectBody($pdfBytes, $objects);
        if ($catalog === null) {
            return [];
        }

        $value = $this->dictionaryTopLevelRawValue($catalog, 'OutputIntents');
        if ($value === null) {
            return [];
        }

        $outputIntents = [];
        foreach ($this->outputIntentDictionariesFromValue($value, $objects) as $dictionary) {
            $intent = $this->outputIntentFromDictionary($dictionary, $objects);
            if ($intent !== null) {
                $outputIntents[] = $intent;
            }
        }

        return $outputIntents;
    }

    /**
     * Native equivalent of pypdfium2/PDFium document identifiers: read the
     * permanent and changing file IDs from the latest trailer /ID array.
     *
     * @return array<string, mixed>
     */
    private function extractTrailerIdMetadata(string $pdfBytes): array
    {
        $trailer = $this->trailerDictionaryBody($pdfBytes);
        if ($trailer === null) {
            return [];
        }

        $value = $this->dictionaryRawValue($trailer, 'ID');
        if ($value === null) {
            return [];
        }

        $identifiers = $this->trailerIdentifierBytesFromValue($value);
        if ($identifiers === []) {
            return [];
        }

        $permanent = $identifiers[0];
        $changing = $identifiers[1] ?? $permanent;

        return [
            'source' => 'trailer_id',
            'permanent' => $this->identifierMetadata($permanent),
            'changing' => $this->identifierMetadata($changing),
            'id_count' => count($identifiers),
            'changed_since_creation' => $permanent !== $changing,
        ];
    }

    /**
     * @return list<string>
     */
    private function trailerIdentifierBytesFromValue(string $value): array
    {
        $body = $this->arrayBody(trim($value));
        if ($body === null) {
            return [];
        }

        $identifiers = [];
        for ($offset = 0, $length = strlen($body); $offset < $length && count($identifiers) < 2;) {
            while ($offset < $length && ctype_space($body[$offset])) {
                $offset++;
            }

            if ($offset >= $length) {
                break;
            }

            if ($body[$offset] === '(') {
                $literal = $this->literalStringBytesAt($body, $offset);
                if ($literal !== null) {
                    $identifiers[] = $literal['bytes'];
                    $offset = $literal['nextOffset'];
                    continue;
                }
            }

            if ($body[$offset] === '<' && $offset + 1 < $length && $body[$offset + 1] !== '<') {
                $hex = $this->hexStringBytesAt($body, $offset);
                if ($hex !== null) {
                    $identifiers[] = $hex['bytes'];
                    $offset = $hex['nextOffset'];
                    continue;
                }
            }

            $offset++;
        }

        return $identifiers;
    }

    /**
     * @return array{bytes: string, nextOffset: int}|null
     */
    private function literalStringBytesAt(string $value, int $offset): ?array
    {
        $depth = 0;
        $body = '';
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '\\') {
                if ($index + 1 < $length) {
                    $body .= $char . $value[$index + 1];
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
                    return [
                        'bytes' => $this->decodeLiteralEscapes($body),
                        'nextOffset' => $index + 1,
                    ];
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

    /**
     * @return array{bytes: string, nextOffset: int}|null
     */
    private function hexStringBytesAt(string $value, int $offset): ?array
    {
        $end = strpos($value, '>', $offset + 1);
        if ($end === false) {
            return null;
        }

        $hex = preg_replace('/\s+/', '', substr($value, $offset + 1, $end - $offset - 1));
        if ($hex === null || preg_match('/^[\da-fA-F]*$/', $hex) !== 1) {
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
            'bytes' => $bytes,
            'nextOffset' => $end + 1,
        ];
    }

    /**
     * @return array{hex: string, bytes: int, sha256: string}
     */
    private function identifierMetadata(string $bytes): array
    {
        return [
            'hex' => bin2hex($bytes),
            'bytes' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
        ];
    }

    /**
     * Native metadata boundary for PDF Catalog review fields that influence
     * document presentation but should not execute actions or override OCR.
     *
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function extractCatalogReviewMetadata(string $pdfBytes, array $objects): array
    {
        $catalog = $this->catalogObjectBody($pdfBytes, $objects);
        if ($catalog === null) {
            return [];
        }

        $metadata = [];
        $language = $this->dictionaryTopLevelStringValue($catalog, 'Lang', $objects);
        if ($language !== null) {
            $metadata['language'] = $language;
        }

        $markInfo = $this->catalogMarkInfoMetadata($catalog, $objects);
        if ($markInfo !== []) {
            $metadata['mark_info'] = $markInfo;
        }

        foreach ([
            'PageLayout' => 'page_layout',
            'PageMode' => 'page_mode',
        ] as $pdfName => $key) {
            $value = $this->dictionaryTopLevelStringValue($catalog, $pdfName, $objects);
            if ($value !== null) {
                $metadata[$key] = $value;
            }
        }

        $viewerPreferences = $this->extractViewerPreferences($catalog, $objects);
        if ($viewerPreferences !== []) {
            $metadata['viewer_preferences'] = $viewerPreferences;
        }

        $structureTree = $this->structureTreeReviewMetadata($catalog, $objects);
        if ($structureTree !== []) {
            $metadata['structure_tree'] = $structureTree;
        }

        $pieceInfo = $this->pieceInfoMetadata($this->dictionaryTopLevelRawValue($catalog, 'PieceInfo'), $objects);
        if ($pieceInfo !== []) {
            $metadata['piece_info'] = $pieceInfo;
        }

        $metadataStreamReview = $this->catalogMetadataStreamBoundaryReview(
            $this->dictionaryTopLevelRawValue($catalog, 'Metadata'),
            $objects
        );
        if ($metadataStreamReview !== []) {
            $metadata['metadata_stream_review'] = $metadataStreamReview;
        }

        $collection = $this->collectionMetadata(
            $this->dictionaryTopLevelRawValue($catalog, 'Collection'),
            $this->dictionaryTopLevelRawValue($catalog, 'AF'),
            $objects
        );
        if ($collection !== []) {
            $metadata['collection'] = $collection;
        }

        if ($collection === []) {
            $associatedFiles = $this->catalogAssociatedFilesMetadata(
                $this->dictionaryTopLevelRawValue($catalog, 'AF'),
                $objects
            );
            if ($associatedFiles !== []) {
                $metadata['associated_files'] = $associatedFiles;
            }
        }

        $embeddedFiles = $this->catalogEmbeddedFileNameTreeMetadata($catalog, $objects, $collection);
        if ($embeddedFiles !== []) {
            $metadata['embedded_files'] = $embeddedFiles;
        }

        $documentDestinations = $this->documentDestinationMetadata($catalog, $objects);
        if ($documentDestinations !== []) {
            $metadata['document_destinations'] = $documentDestinations;
        }

        $documentOutline = $this->documentOutlineMetadata($pdfBytes, $catalog, $objects);
        if ($documentOutline !== []) {
            $metadata['document_outline'] = $documentOutline;
        }

        $documentNameTrees = $this->catalogNameTreeReviewMetadata($catalog, $objects);
        if ($documentNameTrees !== []) {
            $metadata['document_name_trees'] = $documentNameTrees;
        }

        $documentSecurityStore = (new PdfDocumentSecurityStoreExtractor())->extract($pdfBytes);
        if (($documentSecurityStore['present'] ?? false) === true) {
            $metadata['document_security_store'] = $documentSecurityStore;
        }

        return $metadata;
    }

    /**
     * Catalog /MarkInfo declares tagged-PDF review flags. Keep them as
     * document metadata so WordPress import can surface accessibility state
     * without treating the dictionary as visible text.
     *
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function catalogMarkInfoMetadata(string $catalog, array $objects): array
    {
        $markInfo = $this->resolveDictionaryFromValue($this->dictionaryTopLevelRawValue($catalog, 'MarkInfo'), $objects);
        if ($markInfo === null) {
            return [];
        }

        $metadata = [
            'source' => 'catalog_mark_info',
            'review_only' => true,
            'visible_text_source' => false,
        ];
        if ($markInfo['object'] !== null) {
            $metadata['object_number'] = $markInfo['object'];
        }

        foreach ([
            'Marked' => 'marked',
            'UserProperties' => 'user_properties',
            'Suspects' => 'suspects',
        ] as $pdfName => $key) {
            $value = $this->dictionaryBooleanValue($markInfo['body'], $pdfName, $objects);
            if ($value !== null) {
                $metadata[$key] = $value;
            }
        }

        return count($metadata) > ($markInfo['object'] === null ? 3 : 4) ? $metadata : [];
    }

    /**
     * PDF Portfolio /Collection dictionaries are document review metadata.
     * Associated FileSpec rows remain attachment-local: their payload, XMP, and
     * OutputIntent streams are summarized without becoming document roots.
     *
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function collectionMetadata(?string $collectionValue, ?string $associatedFilesValue, array $objects): array
    {
        $collection = $this->resolveDictionaryFromValue($collectionValue, $objects);
        if ($collection === null) {
            return [];
        }

        $body = $collection['body'];
        $entries = $this->dictionaryTopLevelEntries($body);
        $metadata = ['source' => 'catalog_collection'];

        foreach ([
            'type' => $this->reviewStringFromRaw($entries['Type'] ?? null, $objects),
            'view' => $this->reviewStringFromRaw($entries['View'] ?? null, $objects),
            'default_document' => $this->reviewValueFromRaw($entries['D'] ?? null, $objects),
        ] as $key => $value) {
            if ($value !== null && $value !== '') {
                $metadata[$key] = $value;
            }
        }

        $schema = $this->collectionSchemaMetadata($entries['Schema'] ?? null, $objects);
        if ($schema !== []) {
            $metadata['schema'] = $schema;
        }

        $sort = $this->collectionSortMetadata($entries['Sort'] ?? null, $objects);
        if ($sort !== []) {
            $metadata['sort'] = $sort;
        }

        if (isset($entries['Folders'])) {
            $metadata['has_folders'] = true;
        }

        $associatedFiles = $this->collectionAssociatedFiles($associatedFilesValue, $metadata, $objects);
        if ($associatedFiles !== []) {
            $metadata['associated_file_count'] = count($associatedFiles);
            $metadata['associated_files'] = $associatedFiles;
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
        foreach ($this->dictionaryTopLevelEntries($schema['body']) as $name => $fieldValue) {
            $fieldDictionary = $this->resolveDictionaryFromValue($fieldValue, $objects);
            if ($fieldDictionary === null) {
                continue;
            }

            $entries = $this->dictionaryTopLevelEntries($fieldDictionary['body']);
            $field = [];
            foreach ([
                'subtype' => $this->reviewStringFromRaw($entries['Subtype'] ?? null, $objects),
                'label' => $this->reviewStringFromRaw($entries['N'] ?? null, $objects),
                'order' => $this->reviewValueFromRaw($entries['O'] ?? null, $objects),
                'visible' => $this->reviewValueFromRaw($entries['V'] ?? null, $objects),
                'editable' => $this->reviewValueFromRaw($entries['E'] ?? null, $objects),
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

        $entries = $this->dictionaryTopLevelEntries($sort['body']);
        $metadata = [];
        $keys = $this->reviewListFromRaw($entries['S'] ?? null, $objects);
        if ($keys !== []) {
            $metadata['keys'] = $keys;
        }

        $ascending = $this->reviewListFromRaw($entries['A'] ?? null, $objects);
        if ($ascending !== []) {
            $metadata['ascending'] = $ascending;
        }

        return $metadata;
    }

    /**
     * Catalog /AF entries can describe document-associated source files,
     * alternatives, schemas, or supplements without a Portfolio /Collection.
     * Keep FileSpec-local XMP and OutputIntents as review dictionaries only.
     *
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function catalogAssociatedFilesMetadata(?string $associatedFilesValue, array $objects): array
    {
        if ($associatedFilesValue === null) {
            return [];
        }

        $files = [];
        foreach ($this->arrayItemsFromValue($associatedFilesValue, $objects) as $index => $fileSpecValue) {
            $file = $this->catalogAssociatedFileFromValue($fileSpecValue, $index, $objects);
            if ($file !== null) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, mixed> $collection
     * @return array<string, mixed>|null
     */
    private function catalogAssociatedFileFromValue(string $value, int $index, array $objects): ?array
    {
        $fileSpec = $this->resolveDictionaryFromValue($value, $objects);
        if ($fileSpec === null) {
            return null;
        }

        $body = $fileSpec['body'];
        $ef = $this->resolveDictionaryFromValue($this->dictionaryTopLevelRawValue($body, 'EF'), $objects);
        if ($ef === null) {
            return null;
        }

        $unicodeFilename = $this->dictionaryStringValue($body, 'UF');
        $platformFilename = $this->firstDictionaryString($body, ['F', 'DOS', 'Unix', 'Mac']);
        $filename = $unicodeFilename ?? $platformFilename ?? 'embedded-file';
        $file = [
            'source' => 'catalog_associated_files',
            'associated_file' => true,
            'associated_file_index' => $index,
            'name' => $filename,
            'filename' => $filename,
            'file_spec_object' => $fileSpec['object'],
        ];

        if ($unicodeFilename !== null && $unicodeFilename !== '') {
            $file['unicode_filename'] = $unicodeFilename;
        }
        if ($platformFilename !== null && $platformFilename !== '' && $platformFilename !== $filename) {
            $file['platform_filename'] = $platformFilename;
        }

        foreach ([
            'description' => $this->dictionaryStringValue($body, 'Desc'),
            'relationship' => $this->dictionaryNameValue($body, 'AFRelationship', $objects),
            'language' => $this->dictionaryStringValue($body, 'Lang'),
        ] as $key => $metadataValue) {
            if (is_string($metadataValue) && $metadataValue !== '') {
                $file[$key] = $metadataValue;
            }
        }

        $streamMetadata = null;
        foreach ($this->embeddedFileKeys($unicodeFilename !== null) as $efKey) {
            $streamValue = $this->dictionaryTopLevelRawValue($ef['body'], $efKey);
            if ($streamValue === null) {
                continue;
            }

            $streamMetadata = $this->embeddedFileStreamReviewMetadata($streamValue, $objects);
            if ($streamMetadata === null) {
                continue;
            }

            $file['ef_key'] = $efKey;
            foreach ($streamMetadata as $key => $metadataValue) {
                $file[$key] = $metadataValue;
            }
            break;
        }

        if ($streamMetadata === null) {
            return null;
        }

        $metadataReview = $this->reviewValueFromRaw($this->dictionaryTopLevelRawValue($body, 'Metadata'), $objects);
        if (is_array($metadataReview) && $metadataReview !== []) {
            $file['metadata_review'] = $metadataReview;
        }

        $outputIntentReview = $this->reviewValueFromRaw($this->dictionaryTopLevelRawValue($body, 'OutputIntents'), $objects);
        if (is_array($outputIntentReview) && $outputIntentReview !== []) {
            $file['output_intents_review'] = $outputIntentReview;
        }

        $pieceInfo = $this->pieceInfoMetadata($this->dictionaryTopLevelRawValue($body, 'PieceInfo'), $objects);
        if ($pieceInfo !== []) {
            $file['piece_info'] = $pieceInfo;
        }

        $relatedFiles = $this->relatedFileReviewRows($this->dictionaryTopLevelRawValue($body, 'RF'), $objects);
        if ($relatedFiles !== []) {
            $file['related_file_count'] = count($relatedFiles);
            $file['related_files'] = $relatedFiles;
        }

        $provenanceReview = $this->associatedFileProvenanceReview($file, $body, $objects);
        if ($provenanceReview !== []) {
            $file['provenance_review'] = $provenanceReview;
        }

        return $file;
    }

    /**
     * @param array<string, mixed> $collection
     * @param array<int, string> $objects
     * @param array<string, mixed> $collection
     * @return list<array<string, mixed>>
     */
    private function collectionAssociatedFiles(?string $associatedFilesValue, array $collection, array $objects): array
    {
        if ($associatedFilesValue === null) {
            return [];
        }

        $files = [];
        foreach ($this->arrayItemsFromValue($associatedFilesValue, $objects) as $index => $fileSpecValue) {
            $file = $this->collectionAssociatedFileFromValue($fileSpecValue, $index, $collection, $objects);
            if ($file !== null) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * @param array<string, mixed> $collection
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function collectionAssociatedFileFromValue(string $value, int $index, array $collection, array $objects): ?array
    {
        $fileSpec = $this->resolveDictionaryFromValue($value, $objects);
        if ($fileSpec === null) {
            return null;
        }

        $body = $fileSpec['body'];
        $ef = $this->resolveDictionaryFromValue($this->dictionaryTopLevelRawValue($body, 'EF'), $objects);
        if ($ef === null) {
            return null;
        }

        $unicodeFilename = $this->dictionaryStringValue($body, 'UF');
        $platformFilename = $this->firstDictionaryString($body, ['F', 'DOS', 'Unix', 'Mac']);
        $filename = $unicodeFilename ?? $platformFilename ?? 'embedded-file';
        $file = [
            'source' => 'catalog_collection_associated_files',
            'associated_file' => true,
            'associated_file_index' => $index,
            'name' => $filename,
            'filename' => $filename,
            'file_spec_object' => $fileSpec['object'],
        ];

        if ($unicodeFilename !== null && $unicodeFilename !== '') {
            $file['unicode_filename'] = $unicodeFilename;
        }
        if ($platformFilename !== null && $platformFilename !== '' && $platformFilename !== $filename) {
            $file['platform_filename'] = $platformFilename;
        }

        foreach ([
            'description' => $this->dictionaryStringValue($body, 'Desc'),
            'relationship' => $this->dictionaryNameValue($body, 'AFRelationship', $objects),
        ] as $key => $metadataValue) {
            if (is_string($metadataValue) && $metadataValue !== '') {
                $file[$key] = $metadataValue;
            }
        }

        $streamMetadata = null;
        foreach ($this->embeddedFileKeys($unicodeFilename !== null) as $efKey) {
            $streamValue = $this->dictionaryTopLevelRawValue($ef['body'], $efKey);
            if ($streamValue === null) {
                continue;
            }

            $streamMetadata = $this->embeddedFileStreamReviewMetadata($streamValue, $objects);
            if ($streamMetadata === null) {
                continue;
            }

            $file['ef_key'] = $efKey;
            foreach ($streamMetadata as $key => $metadataValue) {
                $file[$key] = $metadataValue;
            }
            break;
        }

        if ($streamMetadata === null) {
            return null;
        }

        $collectionItemValue = $this->dictionaryTopLevelRawValue($body, 'CI');
        $collectionItem = $this->collectionItemMetadata($collectionItemValue, $objects);
        if ($collectionItem !== []) {
            $file['collection_item'] = $collectionItem;
        }

        $fieldValues = $this->collectionFieldValueReview($collection, $collectionItemValue, $objects, $file);
        if ($fieldValues !== []) {
            $file['collection_field_values'] = $fieldValues;
        }

        $metadataReview = $this->reviewValueFromRaw($this->dictionaryTopLevelRawValue($body, 'Metadata'), $objects);
        if (is_array($metadataReview) && $metadataReview !== []) {
            $file['metadata_review'] = $metadataReview;
        }

        $outputIntentReview = $this->reviewValueFromRaw($this->dictionaryTopLevelRawValue($body, 'OutputIntents'), $objects);
        if (is_array($outputIntentReview) && $outputIntentReview !== []) {
            $file['output_intents_review'] = $outputIntentReview;
        }

        $pieceInfo = $this->pieceInfoMetadata($this->dictionaryTopLevelRawValue($body, 'PieceInfo'), $objects);
        if ($pieceInfo !== []) {
            $file['piece_info'] = $pieceInfo;
        }

        $relatedFiles = $this->relatedFileReviewRows($this->dictionaryTopLevelRawValue($body, 'RF'), $objects);
        if ($relatedFiles !== []) {
            $file['related_file_count'] = count($relatedFiles);
            $file['related_files'] = $relatedFiles;
        }

        $provenanceReview = $this->associatedFileProvenanceReview($file, $body, $objects);
        if ($provenanceReview !== []) {
            $file['provenance_review'] = $provenanceReview;
        }

        return $file;
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
        foreach ($this->dictionaryTopLevelEntries($collectionItem['body']) as $name => $value) {
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
     * @param array<string, mixed> $collection
     * @param array<int, string> $objects
     * @param array<string, mixed> $file
     * @return array<string, array<string, mixed>>
     */
    private function collectionFieldValueReview(
        array $collection,
        ?string $collectionItemValue,
        array $objects,
        array $file
    ): array {
        $schema = $collection['schema'] ?? null;
        if (!is_array($schema) || $schema === []) {
            return [];
        }

        $collectionItem = $this->resolveDictionaryFromValue($collectionItemValue, $objects);
        $collectionItemEntries = $collectionItem === null ? [] : $this->dictionaryTopLevelEntries($collectionItem['body']);
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

            if (is_string($subtype)) {
                $valueType = $this->collectionFieldValueType($subtype);
                if ($valueType !== null) {
                    $field['value_type'] = $valueType;
                }
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
            if ($this->dictionaryTopLevelRawValue($body, 'D') !== null || $this->dictionaryTopLevelRawValue($body, 'P') !== null) {
                $metadata = ['source' => 'collection_subitem'];

                $type = $this->reviewStringFromRaw($this->dictionaryTopLevelRawValue($body, 'Type'), $objects);
                if ($type !== null && $type !== '') {
                    $metadata['subitem_type'] = $type;
                }

                $data = $this->reviewValueFromRaw($this->dictionaryTopLevelRawValue($body, 'D'), $objects);
                if ($data !== null && $data !== '') {
                    $metadata['value'] = $data;
                }

                $prefix = $this->reviewValueFromRaw($this->dictionaryTopLevelRawValue($body, 'P'), $objects);
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
        if ($this->dictionaryTopLevelRawValue($body, 'D') === null && $this->dictionaryTopLevelRawValue($body, 'P') === null) {
            return null;
        }

        $metadata = [];
        $value = $this->reviewValueFromRaw($this->dictionaryTopLevelRawValue($body, 'D'), $objects);
        if ($value !== null && $value !== '') {
            $metadata['value'] = $value;
        }

        $prefix = $this->reviewValueFromRaw($this->dictionaryTopLevelRawValue($body, 'P'), $objects);
        if ($prefix !== null && $prefix !== '') {
            $metadata['prefix'] = $prefix;
        }

        return $metadata === [] ? null : $metadata;
    }

    /**
     * Tagged PDF structure element dictionaries carry accessibility language,
     * expansion, alternate text, IDs, classes, namespaces, and MCID/page links.
     * Keep those fields as WordPress review metadata instead of promoting them
     * into visible text or overriding explicit catalog language.
     *
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function structureTreeReviewMetadata(string $catalog, array $objects): array
    {
        $root = $this->resolveDictionaryFromValue($this->dictionaryTopLevelRawValue($catalog, 'StructTreeRoot'), $objects);
        if ($root === null) {
            return [];
        }

        $rootBody = $root['body'];
        $roleMap = $this->structureRoleMapFromValue($this->dictionaryTopLevelRawValue($rootBody, 'RoleMap'), $objects);
        $namespaces = $this->structureNamespacesFromValue($this->dictionaryTopLevelRawValue($rootBody, 'Namespaces'), $objects);
        $pageObjectNumbers = $this->orderedDestinationPageObjectNumbers($catalog, $objects);
        $pageIndexes = [];
        foreach ($pageObjectNumbers as $pageIndex => $pageObjectNumber) {
            $pageIndexes[$pageObjectNumber] = $pageIndex;
        }

        $catalogLanguage = $this->dictionaryTopLevelStringValue($catalog, 'Lang', $objects);
        $rootLanguage = $this->reviewStringFromRaw($this->dictionaryTopLevelRawValue($rootBody, 'Lang'), $objects)
            ?? $catalogLanguage;

        $elements = [];
        $this->collectStructureReviewElements(
            $this->dictionaryTopLevelRawValue($rootBody, 'K'),
            $objects,
            null,
            $rootLanguage,
            $roleMap,
            $pageIndexes,
            $elements
        );
        $this->collectParentTreeStructureReviewElements(
            $catalog,
            $rootBody,
            $objects,
            $rootLanguage,
            $roleMap,
            $pageIndexes,
            $elements
        );

        if ($elements === [] && $roleMap === [] && $namespaces === [] && $rootLanguage === null) {
            return [];
        }

        $languages = [];
        foreach ($elements as $element) {
            $language = $element['language'] ?? null;
            if (is_string($language)) {
                $languages[] = $language;
            }
        }

        $metadata = [
            'source' => 'catalog_struct_tree_root',
            'element_count' => count($elements),
            'page_count' => count($pageObjectNumbers),
            'elements' => $elements,
            'review_only' => true,
            'visible_text_source' => false,
        ];

        if ($root['object'] !== null) {
            $metadata['root_object'] = $root['object'];
        }
        if ($rootLanguage !== null) {
            $metadata['root_language'] = $rootLanguage;
            $metadata['catalog_language_fallback'] = $catalogLanguage === $rootLanguage
                && $this->dictionaryTopLevelRawValue($rootBody, 'Lang') === null;
        }
        if ($roleMap !== []) {
            $metadata['role_map'] = $roleMap;
        }
        if ($namespaces !== []) {
            $metadata['namespaces'] = $namespaces;
        }

        $languages = $this->uniqueStrings($languages);
        if ($languages !== []) {
            $metadata['languages'] = $languages;
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, string> $roleMap
     * @param array<int, int> $pageIndexes
     * @param list<array<string, mixed>> $elements
     * @param array<int, true> $seenObjects
     */
    private function collectStructureReviewElements(
        ?string $value,
        array $objects,
        ?int $inheritedPageObject,
        ?string $inheritedLanguage,
        array $roleMap,
        array $pageIndexes,
        array &$elements,
        array $seenObjects = [],
        int $depth = 0
    ): void {
        if ($value === null || $depth > 24) {
            return;
        }

        $resolved = trim($this->resolvePdfValue($value, $objects) ?? $value);
        if ($resolved === '') {
            return;
        }

        $objectNumber = $this->objectNumberFromReference($value);
        if ($objectNumber !== null) {
            if (isset($seenObjects[$objectNumber]) || !isset($objects[$objectNumber])) {
                return;
            }
            $seenObjects[$objectNumber] = true;
            $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
            if ($dictionary !== null) {
                $this->collectStructureDictionaryReview(
                    $dictionary,
                    $objectNumber,
                    $objects,
                    $inheritedPageObject,
                    $inheritedLanguage,
                    $roleMap,
                    $pageIndexes,
                    $elements,
                    $seenObjects,
                    $depth + 1
                );
            }
            return;
        }

        if (str_starts_with($resolved, '[')) {
            foreach ($this->arrayItemsFromValue($resolved, $objects) as $item) {
                $this->collectStructureReviewElements(
                    $item,
                    $objects,
                    $inheritedPageObject,
                    $inheritedLanguage,
                    $roleMap,
                    $pageIndexes,
                    $elements,
                    $seenObjects,
                    $depth + 1
                );
            }
            return;
        }

        if (str_starts_with($resolved, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($resolved, 0);
            if ($dictionary !== null) {
                $this->collectStructureDictionaryReview(
                    $dictionary,
                    null,
                    $objects,
                    $inheritedPageObject,
                    $inheritedLanguage,
                    $roleMap,
                    $pageIndexes,
                    $elements,
                    $seenObjects,
                    $depth + 1
                );
            }
        }
    }

    /**
     * Page /StructParents ParentTree arrays can be the only place where
     * page-local MCID StructElems are reachable. Include them in review
     * metadata so tagged-content rows can carry StructElem /AF provenance.
     *
     * @param array<int, string> $objects
     * @param array<string, string> $roleMap
     * @param array<int, int> $pageIndexes
     * @param list<array<string, mixed>> $elements
     */
    private function collectParentTreeStructureReviewElements(
        string $catalog,
        string $rootBody,
        array $objects,
        ?string $rootLanguage,
        array $roleMap,
        array $pageIndexes,
        array &$elements
    ): void {
        $parentTree = $this->resolveDictionaryFromValue($this->dictionaryTopLevelRawValue($rootBody, 'ParentTree'), $objects);
        if ($parentTree === null) {
            return;
        }

        $arrays = [];
        $this->collectStructureParentTreeArrays($parentTree['body'], $objects, $arrays);
        if ($arrays === []) {
            return;
        }

        $seenElementObjects = [];
        foreach ($elements as $element) {
            $object = $element['object'] ?? null;
            if (is_int($object)) {
                $seenElementObjects[$object] = true;
            }
        }

        foreach ($this->orderedDestinationPageObjectNumbers($catalog, $objects) as $pageObject) {
            $pageBody = $this->dictionaryObjectBody($objects[$pageObject] ?? '');
            if ($pageBody === null) {
                continue;
            }

            $structParents = $this->dictionaryIntegerValue($pageBody, 'StructParents', $objects);
            if ($structParents === null || !isset($arrays[$structParents])) {
                continue;
            }

            foreach ($this->arrayItemsFromValue($arrays[$structParents], $objects) as $parentValue) {
                $struct = $this->resolveDictionaryFromValue($parentValue, $objects);
                if ($struct === null) {
                    continue;
                }

                $object = $struct['object'];
                if ($object !== null && isset($seenElementObjects[$object])) {
                    continue;
                }

                $this->collectStructureDictionaryReview(
                    $struct['body'],
                    $object,
                    $objects,
                    $pageObject,
                    $rootLanguage,
                    $roleMap,
                    $pageIndexes,
                    $elements,
                    $object === null ? [] : [$object => true],
                    0
                );

                if ($object !== null) {
                    $seenElementObjects[$object] = true;
                }
            }
        }
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, string> $arrays
     * @param array<int, true> $seenObjects
     */
    private function collectStructureParentTreeArrays(
        string $dictionary,
        array $objects,
        array &$arrays,
        array $seenObjects = [],
        int $depth = 0
    ): void {
        if ($depth > 20) {
            return;
        }

        $nums = $this->dictionaryTopLevelRawValue($dictionary, 'Nums');
        if ($nums !== null) {
            $items = $this->arrayItemsFromValue($nums, $objects);
            for ($index = 0, $count = count($items); $index + 1 < $count; $index += 2) {
                $key = trim($items[$index]);
                if (preg_match('/^[+-]?\d+$/', $key) !== 1) {
                    continue;
                }

                $array = $this->arrayBody(trim($this->resolvePdfValue($items[$index + 1], $objects) ?? $items[$index + 1]));
                if ($array !== null) {
                    $arrays[(int) $key] = '[' . $array . ']';
                }
            }
        }

        $kids = $this->dictionaryTopLevelRawValue($dictionary, 'Kids');
        if ($kids === null) {
            return;
        }

        foreach ($this->arrayItemsFromValue($kids, $objects) as $kidValue) {
            $kidObject = $this->objectNumberFromReference($kidValue);
            if ($kidObject === null || isset($seenObjects[$kidObject])) {
                continue;
            }

            $kid = $this->resolveDictionaryFromValue($kidValue, $objects);
            if ($kid === null) {
                continue;
            }

            $nextSeen = $seenObjects;
            $nextSeen[$kidObject] = true;
            $this->collectStructureParentTreeArrays($kid['body'], $objects, $arrays, $nextSeen, $depth + 1);
        }
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, string> $roleMap
     * @param array<int, int> $pageIndexes
     * @param list<array<string, mixed>> $elements
     * @param array<int, true> $seenObjects
     */
    private function collectStructureDictionaryReview(
        string $dictionary,
        ?int $objectNumber,
        array $objects,
        ?int $inheritedPageObject,
        ?string $inheritedLanguage,
        array $roleMap,
        array $pageIndexes,
        array &$elements,
        array $seenObjects,
        int $depth
    ): void {
        $type = $this->dictionaryNameValue($dictionary, 'Type', $objects);
        $rawRole = $this->dictionaryNameValue($dictionary, 'S', $objects);
        if ($type !== null && $type !== 'StructElem') {
            return;
        }
        if ($type !== 'StructElem' && $rawRole === null) {
            return;
        }
        if ($type !== 'StructElem' && $rawRole !== null && $this->isActionLikeStructureRoleDictionary($dictionary, $rawRole)) {
            return;
        }

        $pageObject = $this->objectNumberFromReference($this->dictionaryTopLevelRawValue($dictionary, 'Pg') ?? '')
            ?? $inheritedPageObject;
        $languageRaw = $this->dictionaryTopLevelRawValue($dictionary, 'Lang');
        $language = $this->reviewStringFromRaw($languageRaw, $objects) ?? $inheritedLanguage;
        $namespace = $this->structureNamespaceMetadataFromRaw($this->dictionaryTopLevelRawValue($dictionary, 'NS'), $objects);
        $namespaceRoleMap = is_array($namespace['role_map'] ?? null) ? $namespace['role_map'] : [];
        $effectiveRoleMap = $namespaceRoleMap + $roleMap;
        $role = $rawRole === null ? null : $this->resolveStructureRole($rawRole, $effectiveRoleMap);

        $row = [
            'source' => 'struct_elem',
            'review_only' => true,
        ];
        if ($objectNumber !== null) {
            $row['object'] = $objectNumber;
        }
        if ($rawRole !== null) {
            $row['raw_role'] = $rawRole;
        }
        if ($role !== null) {
            $row['role'] = $role;
            $row['role_mapped'] = $role !== $rawRole;
        }
        if ($pageObject !== null) {
            $this->applyPageReviewMetadata($row, $pageObject, $pageIndexes);
        }
        if ($language !== null) {
            $row['language'] = $language;
            $row['language_inherited'] = $languageRaw === null;
        }

        foreach ([
            'title' => 'T',
            'id' => 'ID',
            'alternate_text' => 'Alt',
            'actual_text' => 'ActualText',
            'expansion_text' => 'E',
        ] as $metadataKey => $pdfKey) {
            $value = $this->reviewStringFromRaw($this->dictionaryTopLevelRawValue($dictionary, $pdfKey), $objects);
            if ($value !== null) {
                $row[$metadataKey] = $value;
            }
        }

        $classes = $this->structureClassNames($this->reviewValueFromRaw($this->dictionaryTopLevelRawValue($dictionary, 'C'), $objects));
        if ($classes !== []) {
            $row['classes'] = $classes;
        }

        $revision = $this->dictionaryIntegerValue($dictionary, 'R', $objects);
        if ($revision !== null) {
            $row['revision'] = $revision;
        }

        if ($namespace !== []) {
            $row['namespace'] = $namespace;
        }

        $associatedFiles = $this->structureAssociatedFiles($this->dictionaryTopLevelRawValue($dictionary, 'AF'), $objects);
        if ($associatedFiles !== []) {
            $row['associated_file_count'] = count($associatedFiles);
            $row['associated_files'] = $associatedFiles;
        }

        $markedContent = $this->structureMarkedContentFromKidValue(
            $this->dictionaryTopLevelRawValue($dictionary, 'K'),
            $objects,
            $pageObject
        );
        if ($markedContent !== []) {
            foreach ($markedContent as &$entry) {
                $entryPageObject = $entry['page_object'] ?? null;
                if (is_int($entryPageObject)) {
                    $this->applyPageReviewMetadata($entry, $entryPageObject, $pageIndexes);
                }
            }
            unset($entry);

            $row['marked_content'] = $markedContent;
            $row['mcids'] = $this->uniqueIntegers(array_map(
                static fn (array $entry): int => (int) $entry['mcid'],
                $markedContent
            ));
        }

        $elements[] = $row;

        $this->collectStructureReviewElements(
            $this->dictionaryTopLevelRawValue($dictionary, 'K'),
            $objects,
            $pageObject,
            $language,
            $roleMap,
            $pageIndexes,
            $elements,
            $seenObjects,
            $depth + 1
        );
    }

    /**
     * Outline items may reference a dictionary through /SE. A PDF action
     * dictionary also uses /S for its subtype, so keep untyped action
     * dictionaries out of structure-element review metadata unless they are
     * explicitly declared as /Type /StructElem.
     */
    private function isActionLikeStructureRoleDictionary(string $dictionary, string $role): bool
    {
        $actionRoles = [
            'GoTo' => true,
            'GoToR' => true,
            'GoToE' => true,
            'Launch' => true,
            'Thread' => true,
            'URI' => true,
            'Sound' => true,
            'Movie' => true,
            'Hide' => true,
            'Named' => true,
            'SubmitForm' => true,
            'ResetForm' => true,
            'ImportData' => true,
            'JavaScript' => true,
            'SetOCGState' => true,
            'Rendition' => true,
            'Trans' => true,
            'GoTo3DView' => true,
            'RichMediaExecute' => true,
        ];
        if (!isset($actionRoles[$role])) {
            return false;
        }

        foreach ([
            'D',
            'F',
            'JS',
            'URI',
            'Next',
            'NewWindow',
            'Win',
            'AN',
            'TA',
            'OP',
            'Fields',
            'Flags',
            'State',
            'Position',
            'Start',
            '3DView',
        ] as $actionKey) {
            if ($this->dictionaryTopLevelRawValue($dictionary, $actionKey) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Structure-element associated files connect attachment provenance to a
     * tagged PDF region. Keep them review-only so fallback text extraction does
     * not ingest payload, nested XMP, or nested OutputIntent bytes.
     *
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function structureAssociatedFiles(?string $associatedFilesValue, array $objects): array
    {
        if ($associatedFilesValue === null) {
            return [];
        }

        $files = [];
        foreach ($this->arrayItemsFromValue($associatedFilesValue, $objects) as $index => $fileSpecValue) {
            $file = $this->associatedFileReviewFromValue(
                $fileSpecValue,
                $index,
                $objects,
                'structure_element_associated_files'
            );
            if ($file !== null) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function structureNamespacesFromValue(?string $value, array $objects): array
    {
        if ($value === null) {
            return [];
        }

        $items = str_starts_with(trim($this->resolvePdfValue($value, $objects) ?? $value), '[')
            ? $this->arrayItemsFromValue($value, $objects)
            : [$value];
        $namespaces = [];
        $seen = [];
        foreach ($items as $item) {
            $namespace = $this->structureNamespaceMetadataFromRaw($item, $objects);
            if ($namespace === []) {
                continue;
            }

            $key = (string) ($namespace['object'] ?? '') . '|' . (string) ($namespace['namespace'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $namespaces[] = $namespace;
        }

        return $namespaces;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function structureNamespaceMetadataFromRaw(?string $value, array $objects): array
    {
        $namespace = $this->resolveDictionaryFromValue($value, $objects);
        if ($namespace === null) {
            return [];
        }

        $metadata = [];
        if ($namespace['object'] !== null) {
            $metadata['object'] = $namespace['object'];
        }

        $name = $this->reviewStringFromRaw($this->dictionaryTopLevelRawValue($namespace['body'], 'NS'), $objects);
        if ($name !== null) {
            $metadata['namespace'] = $name;
        }

        $roleMap = $this->structureRoleMapFromValue($this->dictionaryTopLevelRawValue($namespace['body'], 'RoleMap'), $objects);
        if ($roleMap !== []) {
            $metadata['role_map'] = $roleMap;
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, string>
     */
    private function structureRoleMapFromValue(?string $value, array $objects): array
    {
        $roleMap = $this->resolveDictionaryFromValue($value, $objects);
        if ($roleMap === null) {
            return [];
        }

        $mapped = [];
        foreach ($this->dictionaryTopLevelEntries($roleMap['body']) as $rawRole => $rawValue) {
            $role = $this->reviewStringFromRaw($rawValue, $objects);
            if ($role !== null) {
                $mapped[$rawRole] = $role;
            }
        }

        return $mapped;
    }

    /**
     * @param array<string, string> $roleMap
     */
    private function resolveStructureRole(string $role, array $roleMap): string
    {
        $current = $role;
        $seen = [];
        for ($depth = 0; $depth < 16; $depth++) {
            if (!isset($roleMap[$current]) || isset($seen[$current])) {
                break;
            }

            $seen[$current] = true;
            $current = $roleMap[$current];
        }

        return $current;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function structureMarkedContentFromKidValue(
        ?string $value,
        array $objects,
        ?int $inheritedPageObject,
        array $seenObjects = [],
        int $depth = 0
    ): array {
        if ($value === null || $depth > 12) {
            return [];
        }

        $resolved = trim($this->resolvePdfValue($value, $objects) ?? $value);
        if ($resolved === '') {
            return [];
        }

        $objectNumber = $this->objectNumberFromReference($value);
        if ($objectNumber !== null) {
            if (isset($seenObjects[$objectNumber]) || !isset($objects[$objectNumber])) {
                return [];
            }
            $seenObjects[$objectNumber] = true;
            $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
            if ($dictionary === null || $this->dictionaryNameValue($dictionary, 'Type', $objects) === 'StructElem' || $this->dictionaryTopLevelRawValue($dictionary, 'S') !== null) {
                return [];
            }

            return $this->structureMarkedContentFromDictionary($dictionary, $objects, $inheritedPageObject);
        }

        if (preg_match('/^[+-]?\d+$/', $resolved) === 1) {
            $mcid = (int) $resolved;
            return $mcid >= 0 ? [[
                'mcid' => $mcid,
                'page_object' => $inheritedPageObject,
            ]] : [];
        }

        if (str_starts_with($resolved, '[')) {
            $entries = [];
            foreach ($this->arrayItemsFromValue($resolved, $objects) as $item) {
                foreach ($this->structureMarkedContentFromKidValue($item, $objects, $inheritedPageObject, $seenObjects, $depth + 1) as $entry) {
                    $entries[] = $entry;
                }
            }

            return $this->dedupeMarkedContentEntries($entries);
        }

        if (str_starts_with($resolved, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($resolved, 0);
            if ($dictionary === null || $this->dictionaryNameValue($dictionary, 'Type', $objects) === 'StructElem' || $this->dictionaryTopLevelRawValue($dictionary, 'S') !== null) {
                return [];
            }

            return $this->structureMarkedContentFromDictionary($dictionary, $objects, $inheritedPageObject);
        }

        return [];
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{mcid: int, page_object: int|null}>
     */
    private function structureMarkedContentFromDictionary(string $dictionary, array $objects, ?int $inheritedPageObject): array
    {
        $mcid = $this->dictionaryIntegerValue($dictionary, 'MCID', $objects);
        if ($mcid === null || $mcid < 0) {
            return [];
        }

        return [[
            'mcid' => $mcid,
            'page_object' => $this->objectNumberFromReference($this->dictionaryTopLevelRawValue($dictionary, 'Pg') ?? '')
                ?? $inheritedPageObject,
        ]];
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    private function dedupeMarkedContentEntries(array $entries): array
    {
        $deduped = [];
        $seen = [];
        foreach ($entries as $entry) {
            $key = (string) ($entry['page_object'] ?? '') . ':' . (string) ($entry['mcid'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduped[] = $entry;
        }

        return $deduped;
    }

    /**
     * @param array<string, mixed> $target
     * @param array<int, int> $pageIndexes
     */
    private function applyPageReviewMetadata(array &$target, int $pageObject, array $pageIndexes): void
    {
        $target['page_object'] = $pageObject;
        if (isset($pageIndexes[$pageObject])) {
            $target['page'] = $pageIndexes[$pageObject];
            $target['page_number'] = $pageIndexes[$pageObject] + 1;
        }
    }

    /**
     * @return list<string>
     */
    private function structureClassNames(mixed $value): array
    {
        if (is_string($value)) {
            return $value === '' ? [] : [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $classes = [];
        foreach ($value as $entry) {
            if (is_string($entry) && $entry !== '') {
                $classes[] = $entry;
            }
        }

        return $this->uniqueStrings($classes);
    }

    /**
     * @param list<int> $values
     * @return list<int>
     */
    private function uniqueIntegers(array $values): array
    {
        $out = [];
        foreach ($values as $value) {
            if (!in_array($value, $out, true)) {
                $out[] = $value;
            }
        }

        return $out;
    }

    /**
     * Catalog destination name trees are navigation metadata. They should be
     * available to WordPress review UIs without becoming title/author fallback
     * strings or visible page text.
     *
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function documentDestinationMetadata(string $catalog, array $objects): array
    {
        $pageObjectNumbers = $this->orderedDestinationPageObjectNumbers($catalog, $objects);
        if ($pageObjectNumbers === []) {
            return [];
        }

        $pageIndexes = [];
        foreach ($pageObjectNumbers as $index => $pageObjectNumber) {
            $pageIndexes[$pageObjectNumber] = $index;
        }

        $entries = [];
        $names = $this->resolveDictionaryFromValue($this->dictionaryTopLevelRawValue($catalog, 'Names'), $objects);
        $nameTreeRoot = $names === null
            ? null
            : $this->resolveDictionaryFromValue($this->dictionaryTopLevelRawValue($names['body'], 'Dests'), $objects);
        if ($nameTreeRoot !== null) {
            $seenNameTreeObjects = [];
            $this->collectDestinationNameTreeEntries($nameTreeRoot, $objects, $entries, $seenNameTreeObjects);
        }

        $legacyDests = $this->resolveDictionaryFromValue($this->dictionaryTopLevelRawValue($catalog, 'Dests'), $objects);
        if ($legacyDests !== null) {
            foreach ($this->dictionaryTopLevelEntries($legacyDests['body']) as $name => $value) {
                $entries[] = [
                    'name' => $name,
                    'value' => $value,
                    'source' => 'legacy_dests',
                ];
            }
        }

        if ($entries === []) {
            return [];
        }

        $rawDestinationsByName = $this->documentDestinationEntryValueMap($entries, $objects, $pageIndexes);

        $destinationsByName = [];
        $unresolved = 0;
        foreach ($entries as $entry) {
            $details = $this->documentDestinationDetails(
                $entry['value'],
                $objects,
                $pageIndexes,
                $rawDestinationsByName,
                $entry['name']
            );
            if ($details === null) {
                $unresolved++;
                continue;
            }

            $details['name'] = $entry['name'];
            $details['destination'] = $entry['name'];
            $details['source'] = $entry['source'];
            if ($entry['source'] === 'names_dests' || !isset($destinationsByName[$entry['name']])) {
                $destinationsByName[$entry['name']] = $details;
            }
        }

        $destinations = array_values($destinationsByName);

        if ($destinations === []) {
            return $unresolved > 0
                ? [
                    'source' => $this->uniqueStrings(array_column($entries, 'source')),
                    'count' => 0,
                    'page_count' => count($pageObjectNumbers),
                    'names' => [],
                    'destinations' => [],
                    'unresolved_count' => $unresolved,
                ]
                : [];
        }

        return [
            'source' => $this->uniqueStrings(array_column($entries, 'source')),
            'count' => count($destinations),
            'page_count' => count($pageObjectNumbers),
            'names' => array_values(array_map(static fn (array $destination): string => $destination['name'], $destinations)),
            'destinations' => $destinations,
            'unresolved_count' => $unresolved,
        ];
    }

    /**
     * Catalog /Outlines is navigation metadata, not page text. Summarize the
     * current xref-selected outline tree here so WordPress import can review
     * document bookmarks alongside XMP/Info metadata without relying on stale
     * duplicate objects appended outside the current xref chain.
     *
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function documentOutlineMetadata(string $pdfBytes, string $catalog, array $objects): array
    {
        $outlineRoot = $this->resolveDictionaryFromValue($this->dictionaryTopLevelRawValue($catalog, 'Outlines'), $objects);
        if ($outlineRoot === null || !$this->isDocumentOutlineRootDictionary($outlineRoot['body'], $objects)) {
            return [];
        }

        $pageObjectNumbers = $this->orderedDestinationPageObjectNumbers($catalog, $objects);
        $pageIndexes = [];
        foreach ($pageObjectNumbers as $index => $pageObjectNumber) {
            $pageIndexes[$pageObjectNumber] = $index;
        }

        $structureContext = $this->documentOutlineStructureElementContext($catalog, $objects);
        $destinationsByName = $this->documentDestinationRawMap($catalog, $objects);
        $pageLabels = (new PdfTextExtractor())->extractPageLabels($pdfBytes);
        $items = $this->documentOutlineRootAllowsItemTraversal($outlineRoot['body'], $objects)
            ? $this->documentOutlineItemMetadataRows(
                $this->dictionaryTopLevelRawValue($outlineRoot['body'], 'First'),
                $objects,
                $pageIndexes,
                $pageLabels,
                $destinationsByName,
                $structureContext,
                $outlineRoot['object'],
                $this->validObjectNumberFromReference($this->dictionaryTopLevelRawValue($outlineRoot['body'], 'Last'), $objects),
                15
            )
            : [];

        $firstItemObject = $this->objectNumberFromReference($this->dictionaryTopLevelRawValue($outlineRoot['body'], 'First') ?? '');
        $lastItemObject = $this->objectNumberFromReference($this->dictionaryTopLevelRawValue($outlineRoot['body'], 'Last') ?? '');
        $declaredCount = $this->dictionaryIntegerValue($outlineRoot['body'], 'Count', $objects);
        $hasChildren = $firstItemObject !== null || $lastItemObject !== null;
        $resolvedCount = count(array_filter(
            $items,
            static fn (array $item): bool => ($item['destination_resolved'] ?? false) === true
        ));
        $maxLevel = 0;
        foreach ($items as $item) {
            if (is_int($item['level'] ?? null)) {
                $maxLevel = max($maxLevel, $item['level']);
            }
        }

        $metadata = [
            'source' => 'catalog_outlines',
            'review_only' => true,
            'payload_included' => false,
            'outline_root_object' => $outlineRoot['object'],
            'first_item_object' => $firstItemObject,
            'last_item_object' => $lastItemObject,
            'has_children' => $hasChildren,
            'outline_count' => $declaredCount,
            'declared_visible_count' => $declaredCount === null ? null : abs($declaredCount),
            'descendant_count' => $declaredCount === null ? null : abs($declaredCount),
            'is_open' => $declaredCount === null ? null : $declaredCount >= 0,
            'is_collapsed' => $declaredCount === null ? null : $declaredCount < 0,
            'structure_state' => $declaredCount === null
                ? ($hasChildren ? 'parent' : 'leaf')
                : ($declaredCount < 0 ? 'collapsed' : ($hasChildren ? 'expanded' : 'leaf')),
            'item_count' => count($items),
            'resolved_destination_count' => $resolvedCount,
            'unresolved_destination_count' => count($items) - $resolvedCount,
            'max_depth' => $maxLevel,
            'titles' => array_values(array_map(static fn (array $item): string => $item['title'], $items)),
            'items' => $items,
        ];
        $outlinePageLabels = array_values(array_filter(
            array_map(
                static fn (array $item): ?string => is_string($item['page_label'] ?? null) ? $item['page_label'] : null,
                $items
            ),
            static fn (?string $label): bool => $label !== null && $label !== ''
        ));
        if ($outlinePageLabels !== []) {
            $metadata['page_labels'] = $outlinePageLabels;
        }

        foreach ($this->documentOutlineStructureElementSummary($items) as $key => $value) {
            $metadata[$key] = $value;
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     * @return array{root_language: string|null, role_map: array<string, string>}
     */
    private function documentOutlineStructureElementContext(string $catalog, array $objects): array
    {
        $catalogLanguage = $this->dictionaryTopLevelStringValue($catalog, 'Lang', $objects);
        $root = $this->resolveDictionaryFromValue($this->dictionaryTopLevelRawValue($catalog, 'StructTreeRoot'), $objects);
        if ($root === null) {
            return [
                'root_language' => $catalogLanguage,
                'role_map' => [],
            ];
        }

        $rootLanguage = $this->reviewStringFromRaw($this->dictionaryTopLevelRawValue($root['body'], 'Lang'), $objects)
            ?? $catalogLanguage;

        return [
            'root_language' => $rootLanguage,
            'role_map' => $this->structureRoleMapFromValue(
                $this->dictionaryTopLevelRawValue($root['body'], 'RoleMap'),
                $objects
            ),
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private function documentOutlineStructureElementSummary(array $items): array
    {
        $objects = [];
        $roles = [];
        $rawRoles = [];
        $mcids = [];
        $associatedFileCount = 0;

        foreach ($items as $item) {
            $structure = $item['structure_element'] ?? null;
            if (!is_array($structure)) {
                continue;
            }

            $object = $structure['object'] ?? null;
            if (is_int($object)) {
                $objects[] = $object;
            }

            $role = $structure['role'] ?? null;
            if (is_string($role) && $role !== '') {
                $roles[] = $role;
            }

            $rawRole = $structure['raw_role'] ?? null;
            if (is_string($rawRole) && $rawRole !== '') {
                $rawRoles[] = $rawRole;
            }

            foreach (($structure['mcids'] ?? []) as $mcid) {
                if (is_int($mcid)) {
                    $mcids[] = $mcid;
                }
            }

            $count = $structure['associated_file_count'] ?? null;
            if (is_int($count)) {
                $associatedFileCount += $count;
            }
        }

        if ($objects === [] && $roles === [] && $rawRoles === [] && $mcids === [] && $associatedFileCount === 0) {
            return [];
        }

        $summary = [
            'structure_element_count' => count(array_filter(
                $items,
                static fn (array $item): bool => is_array($item['structure_element'] ?? null)
            )),
            'structure_element_review_only' => true,
            'structure_element_payload_included' => false,
        ];

        if ($objects !== []) {
            $summary['structure_element_objects'] = array_values(array_unique($objects));
        }
        if ($roles !== []) {
            $summary['structure_element_roles'] = $this->uniqueStrings($roles);
        }
        if ($rawRoles !== []) {
            $summary['structure_element_raw_roles'] = $this->uniqueStrings($rawRoles);
        }
        if ($mcids !== []) {
            $summary['structure_element_mcids'] = array_values(array_unique($mcids));
        }
        if ($associatedFileCount > 0) {
            $summary['structure_element_associated_file_count'] = $associatedFileCount;
        }

        return $summary;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, string>
     */
    private function documentDestinationRawMap(string $catalog, array $objects): array
    {
        $entries = [];
        $names = $this->resolveDictionaryFromValue($this->dictionaryTopLevelRawValue($catalog, 'Names'), $objects);
        $nameTreeRoot = $names === null
            ? null
            : $this->resolveDictionaryFromValue($this->dictionaryTopLevelRawValue($names['body'], 'Dests'), $objects);
        if ($nameTreeRoot !== null) {
            $seenNameTreeObjects = [];
            $this->collectDestinationNameTreeEntries($nameTreeRoot, $objects, $entries, $seenNameTreeObjects);
        }

        $legacyDests = $this->resolveDictionaryFromValue($this->dictionaryTopLevelRawValue($catalog, 'Dests'), $objects);
        if ($legacyDests !== null) {
            foreach ($this->dictionaryTopLevelEntries($legacyDests['body']) as $name => $value) {
                $entries[] = [
                    'name' => $name,
                    'value' => $value,
                    'source' => 'legacy_dests',
                ];
            }
        }

        $pageObjectNumbers = $this->orderedDestinationPageObjectNumbers($catalog, $objects);
        $pageIndexes = [];
        foreach ($pageObjectNumbers as $index => $pageObjectNumber) {
            $pageIndexes[$pageObjectNumber] = $index;
        }

        return $this->documentDestinationEntryValueMap($entries, $objects, $pageIndexes);
    }

    /**
     * @param list<array{name: string, value: string, source: string}> $entries
     * @param array<int, string> $objects
     * @param array<int, int> $pageIndexes
     * @return array<string, string>
     */
    private function documentDestinationEntryValueMap(array $entries, array $objects, array $pageIndexes): array
    {
        $destinations = [];
        foreach ($entries as $entry) {
            if (!$this->documentDestinationValueAllowedForMap($entry['value'], $objects, $pageIndexes)) {
                continue;
            }
            if ($entry['source'] === 'names_dests' || !isset($destinations[$entry['name']])) {
                $destinations[$entry['name']] = $entry['value'];
            }
        }

        return $destinations;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, int> $pageIndexes
     */
    private function documentDestinationValueAllowedForMap(string $value, array $objects, array $pageIndexes): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        if ($this->destinationNameFromRaw($trimmed, $objects) !== null) {
            return true;
        }

        $page = $this->destinationPageFromRaw($trimmed, $objects, $pageIndexes);
        if ($page !== null) {
            return true;
        }

        $dictionary = $this->resolveDictionaryFromValue($trimmed, $objects);
        if ($dictionary !== null) {
            $entries = $this->dictionaryTopLevelEntries($dictionary['body']);
            if (isset($entries['D'])) {
                return $this->documentDestinationValueAllowedForMap($entries['D'], $objects, $pageIndexes);
            }
        }

        $items = $this->arrayItemsFromValue($trimmed, $objects);
        if ($items !== []) {
            return $this->destinationPageFromRaw($items[0] ?? '', $objects, $pageIndexes) !== null
                && $this->documentDestinationArrayViewIsValid($items, $objects);
        }

        $resolved = $this->trimPdfWhitespaceAndComments($this->resolvePdfValue($trimmed, $objects) ?? $trimmed);
        if (preg_match('/^\d+$/', $resolved) === 1) {
            $pageIndex = (int) $resolved;
            return $pageIndex >= 0 && $pageIndex < count($pageIndexes);
        }

        return false;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, int> $pageIndexes
     * @param list<string> $pageLabels
     * @param array<string, string> $destinationsByName
     * @param array{root_language: string|null, role_map: array<string, string>} $structureContext
     * @param array<int, true> $seen
     * @return list<array<string, mixed>>
     */
    private function documentOutlineItemMetadataRows(
        ?string $firstItemValue,
        array $objects,
        array $pageIndexes,
        array $pageLabels,
        array $destinationsByName,
        array $structureContext,
        ?int $expectedParentObject,
        ?int $lastItemObject,
        int $maxDepth,
        int $level = 1,
        array $seen = []
    ): array {
        if ($level > $maxDepth) {
            return [];
        }

        $items = [];
        $current = $this->validObjectNumberFromReference($firstItemValue, $objects);
        $previousSiblingObject = null;
        while ($current !== null && !isset($seen[$current])) {
            $seen[$current] = true;
            $dictionary = isset($objects[$current]) ? $this->dictionaryObjectBody($objects[$current]) : null;
            if ($dictionary === null) {
                break;
            }
            if (!$this->documentOutlineItemAllowsTraversalByType($dictionary, $objects)) {
                break;
            }
            if (!$this->documentOutlineItemParentMatches($dictionary, $objects, $expectedParentObject)) {
                break;
            }
            if (!$this->documentOutlineItemPrevMatches($dictionary, $objects, $previousSiblingObject)) {
                break;
            }

            $title = $this->reviewOutlineTitleFromRaw($this->dictionaryTopLevelRawValue($dictionary, 'Title'), $objects);
            if ($title === null) {
                if ($lastItemObject === null || $current === $lastItemObject) {
                    break;
                }

                $previousSiblingObject = $current;
                $current = $this->validObjectNumberFromReference($this->dictionaryTopLevelRawValue($dictionary, 'Next'), $objects);
                continue;
            }

            $items[] = $this->documentOutlineItemMetadataRow(
                $dictionary,
                $current,
                $title,
                $level,
                $objects,
                $pageIndexes,
                $pageLabels,
                $destinationsByName,
                $structureContext
            );

            if ($this->documentOutlineItemAllowsChildTraversal($dictionary, $objects)) {
                foreach ($this->documentOutlineItemMetadataRows(
                    $this->dictionaryTopLevelRawValue($dictionary, 'First'),
                    $objects,
                    $pageIndexes,
                    $pageLabels,
                    $destinationsByName,
                    $structureContext,
                    $current,
                    $this->validObjectNumberFromReference($this->dictionaryTopLevelRawValue($dictionary, 'Last'), $objects),
                    $maxDepth,
                    $level + 1,
                    $seen
                ) as $child) {
                    $items[] = $child;
                }
            }

            if ($lastItemObject !== null && $current === $lastItemObject) {
                break;
            }

            $previousSiblingObject = $current;
            $current = $this->validObjectNumberFromReference($this->dictionaryTopLevelRawValue($dictionary, 'Next'), $objects);
        }

        return $items;
    }

    private function documentOutlineItemParentMatches(string $dictionary, array $objects, ?int $expectedParentObject): bool
    {
        $parentValue = $this->dictionaryTopLevelRawValue($dictionary, 'Parent');
        if ($expectedParentObject === null) {
            return $parentValue === null;
        }

        if ($parentValue === null) {
            return $this->isDocumentOutlineRootObject($expectedParentObject, $objects);
        }

        $parent = $this->validObjectNumberFromReference($parentValue, $objects);

        return $parent === $expectedParentObject;
    }

    /**
     * @param array<int, string> $objects
     */
    private function documentOutlineItemAllowsTraversalByType(string $dictionary, array $objects): bool
    {
        if ($this->dictionaryTopLevelRawValue($dictionary, 'S') !== null) {
            return false;
        }

        $type = $this->dictionaryNameValue($dictionary, 'Type', $objects);

        return $type === null || !isset(self::NON_OUTLINE_ITEM_TYPES[$type]);
    }

    /**
     * PDF outline sibling lists are linked in both directions. Missing /Prev
     * is tolerated for lightweight producers, but an explicit contradictory
     * backlink marks a corrupt or stale sibling boundary.
     *
     * @param array<int, string> $objects
     */
    private function documentOutlineItemPrevMatches(string $dictionary, array $objects, ?int $previousSiblingObject): bool
    {
        $prevValue = $this->dictionaryTopLevelRawValue($dictionary, 'Prev');
        if ($prevValue === null) {
            return true;
        }

        $previous = $this->validObjectNumberFromReference($prevValue, $objects);

        return $previous !== null && $previous === $previousSiblingObject;
    }

    /**
     * A zero `/Count` declares no open descendants. Preserve contradictory
     * child references on the item row, but do not import those child rows.
     *
     * @param array<int, string> $objects
     */
    private function documentOutlineItemAllowsChildTraversal(string $dictionary, array $objects): bool
    {
        $count = $this->dictionaryIntegerValue($dictionary, 'Count', $objects);

        return $count !== 0;
    }

    /**
     * A root `/Count 0` declares no visible outline items. Preserve root
     * review metadata, but do not promote contradictory child rows.
     *
     * @param array<int, string> $objects
     */
    private function documentOutlineRootAllowsItemTraversal(string $dictionary, array $objects): bool
    {
        $count = $this->dictionaryIntegerValue($dictionary, 'Count', $objects);

        return $count !== 0;
    }

    /**
     * @param array<int, string> $objects
     */
    private function isDocumentOutlineRootObject(?int $objectNumber, array $objects): bool
    {
        if ($objectNumber === null || !isset($objects[$objectNumber])) {
            return false;
        }

        $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
        if ($dictionary === null) {
            return false;
        }

        return $this->isDocumentOutlineRootDictionary($dictionary, $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function isDocumentOutlineRootDictionary(string $dictionary, array $objects): bool
    {
        if ($this->dictionaryTopLevelRawValue($dictionary, 'Type') !== null) {
            return $this->dictionaryNameValue($dictionary, 'Type', $objects) === 'Outlines';
        }

        return $this->dictionaryTopLevelRawValue($dictionary, 'Title') === null
            && (
                $this->dictionaryTopLevelRawValue($dictionary, 'First') !== null
                || $this->dictionaryTopLevelRawValue($dictionary, 'Last') !== null
                || $this->dictionaryTopLevelRawValue($dictionary, 'Count') !== null
            );
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, int> $pageIndexes
     * @param list<string> $pageLabels
     * @param array<string, string> $destinationsByName
     * @param array{root_language: string|null, role_map: array<string, string>} $structureContext
     * @return array<string, mixed>
     */
    private function documentOutlineItemMetadataRow(
        string $dictionary,
        int $objectNumber,
        string $title,
        int $level,
        array $objects,
        array $pageIndexes,
        array $pageLabels,
        array $destinationsByName,
        array $structureContext
    ): array {
        $firstChild = $this->objectNumberFromReference($this->dictionaryTopLevelRawValue($dictionary, 'First') ?? '');
        $lastChild = $this->objectNumberFromReference($this->dictionaryTopLevelRawValue($dictionary, 'Last') ?? '');
        $count = $this->dictionaryIntegerValue($dictionary, 'Count', $objects);
        $hasChildren = $firstChild !== null || $lastChild !== null;
        $destination = $this->documentOutlineItemDestination($dictionary, $objects);

        $row = [
            'title' => $title,
            'level' => $level,
            'outline_object' => $objectNumber,
            'parent_object' => $this->objectNumberFromReference($this->dictionaryTopLevelRawValue($dictionary, 'Parent') ?? ''),
            'previous_object' => $this->objectNumberFromReference($this->dictionaryTopLevelRawValue($dictionary, 'Prev') ?? ''),
            'next_object' => $this->objectNumberFromReference($this->dictionaryTopLevelRawValue($dictionary, 'Next') ?? ''),
            'first_child_object' => $firstChild,
            'last_child_object' => $lastChild,
            'has_children' => $hasChildren,
            'outline_count' => $count,
            'descendant_count' => $count === null ? null : abs($count),
            'is_open' => $count === null ? null : $count >= 0,
            'is_collapsed' => $count === null ? null : $count < 0,
            'structure_state' => $count === null
                ? ($hasChildren ? 'parent' : 'leaf')
                : ($count < 0 ? 'collapsed' : ($hasChildren ? 'expanded' : 'leaf')),
            'destination' => $destination['name'],
            'destination_resolved' => false,
            'action_type' => $destination['action_type'],
            'action_object' => $destination['action_object'],
        ];

        $styleFlags = $this->dictionaryIntegerValue($dictionary, 'F', $objects);
        if ($styleFlags !== null) {
            $row['style_flags'] = $styleFlags;
            $row['is_italic'] = ($styleFlags & 1) !== 0;
            $row['is_bold'] = ($styleFlags & 2) !== 0;
        }

        $textColor = $this->documentOutlineColorRgb($this->dictionaryTopLevelRawValue($dictionary, 'C'), $objects);
        if ($textColor !== null) {
            $row['text_color_rgb'] = $textColor;
            $row['text_color_hex'] = $this->rgbUnitColorToHex($textColor);
        }

        $metadataStreamReview = $this->documentOutlineItemMetadataStreamReview(
            $this->dictionaryTopLevelRawValue($dictionary, 'Metadata'),
            $objects
        );
        if ($metadataStreamReview !== []) {
            $row['metadata_stream_review'] = $metadataStreamReview;
        }

        foreach ($this->documentOutlineActionChainMetadata($this->dictionaryTopLevelRawValue($dictionary, 'A'), $objects) as $key => $value) {
            $row[$key] = $value;
        }
        foreach ($this->documentOutlineDestinationActionChainMetadata($destination['value'], $objects, $destinationsByName, $destination['name']) as $key => $value) {
            $row[$key] = $value;
        }
        foreach ($this->documentDestinationAliasReview($destination['value'], $objects, $destinationsByName) as $key => $value) {
            $row[$key] = $value;
        }

        $structureElement = $this->documentOutlineItemStructureElementMetadata(
            $this->dictionaryTopLevelRawValue($dictionary, 'SE'),
            $objects,
            $pageIndexes,
            $structureContext
        );
        if ($structureElement !== []) {
            $row['structure_element'] = $structureElement;
            foreach ([
                'object' => 'structure_element_object',
                'raw_role' => 'structure_element_raw_role',
                'role' => 'structure_element_role',
                'page' => 'structure_element_page',
                'page_number' => 'structure_element_page_number',
                'page_object' => 'structure_element_page_object',
                'mcids' => 'structure_element_mcids',
                'associated_file_count' => 'structure_element_associated_file_count',
            ] as $sourceKey => $targetKey) {
                if (array_key_exists($sourceKey, $structureElement)) {
                    $row[$targetKey] = $structureElement[$sourceKey];
                }
            }
        }

        if ($destination['value'] !== null && $pageIndexes !== []) {
            $details = $this->documentDestinationDetails(
                $destination['value'],
                $objects,
                $pageIndexes,
                $destinationsByName,
                $destination['name']
            );
            if ($details !== null) {
                $row['destination_resolved'] = true;
                foreach (['destination', 'page', 'page_number', 'page_object', 'view_mode', 'view_position', 'view_parameters'] as $key) {
                    $row[$key] = $details[$key] ?? null;
                }
                if (is_int($row['page'] ?? null)) {
                    $row['page_label'] = $pageLabels[$row['page']] ?? (string) ($row['page'] + 1);
                }
            }
        }

        return $row;
    }

    /**
     * Named destinations may alias another name before reaching an explicit
     * page target. Keep the original outline operand and bounded alias chain
     * reviewable without turning cyclic names into TOC targets.
     *
     * @param array<int, string> $objects
     * @param array<string, string> $destinationsByName
     * @return array<string, mixed>
     */
    private function documentDestinationAliasReview(?string $value, array $objects, array $destinationsByName): array
    {
        if ($value === null) {
            return [];
        }

        $firstName = $this->destinationNameFromRaw($value, $objects);
        if ($firstName === null) {
            return [];
        }

        $chain = [];
        $seen = [];
        $currentName = $firstName;
        for ($depth = 0; $depth < 32; $depth++) {
            if (isset($seen[$currentName])) {
                $chain[] = $currentName;

                return [
                    'declared_destination' => $firstName,
                    'destination_alias_chain' => $chain,
                    'destination_alias_resolved' => false,
                    'destination_alias_cycle' => true,
                    'destination_unresolved_reason' => 'destination_alias_cycle',
                ];
            }

            $chain[] = $currentName;
            $seen[$currentName] = true;
            if (!array_key_exists($currentName, $destinationsByName)) {
                if (count($chain) < 2) {
                    return [];
                }

                return [
                    'declared_destination' => $firstName,
                    'destination_alias_chain' => $chain,
                    'destination_alias_resolved' => false,
                    'destination_alias_cycle' => false,
                    'destination_unresolved_reason' => 'destination_alias_missing_target',
                ];
            }

            $nextName = $this->destinationNameFromRaw($destinationsByName[$currentName], $objects);
            if ($nextName === null) {
                if (count($chain) < 2) {
                    return [];
                }

                return [
                    'declared_destination' => $firstName,
                    'destination_alias_chain' => $chain,
                    'destination_target' => $currentName,
                    'destination_alias_resolved' => true,
                    'destination_alias_cycle' => false,
                ];
            }

            $currentName = $nextName;
        }

        return [
            'declared_destination' => $firstName,
            'destination_alias_chain' => $chain,
            'destination_alias_resolved' => false,
            'destination_alias_cycle' => false,
            'destination_unresolved_reason' => 'destination_alias_depth_limit',
        ];
    }

    /**
     * Outline item /Metadata streams are bookmark-local review metadata. Keep
     * their bytes hashed and typed without promoting their XML or stream
     * payload to root document metadata or visible WordPress paragraphs.
     *
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function documentOutlineItemMetadataStreamReview(?string $value, array $objects): array
    {
        if ($value === null || $this->trimPdfWhitespaceAndComments($value) === 'null') {
            return [];
        }

        $base = [
            'source' => 'outline_item_metadata_stream',
            'review_only' => true,
            'payload_included' => false,
            'visible_text_source' => false,
            'accepted_as_document_xmp' => false,
        ];

        $objectNumber = $this->objectNumberFromReference($value);
        if ($objectNumber === null) {
            return $base + [
                'status' => 'rejected_non_indirect_metadata_reference',
            ];
        }

        $objectBody = $this->objectBodyFromReferenceValue($value, $objects);
        if ($objectBody === null) {
            return $base + [
                'status' => 'unresolved_metadata_reference',
                'object_number' => $objectNumber,
            ];
        }

        $stream = $this->decodeStreamEntryObject($objectBody, $objects);
        if ($stream === null) {
            $review = $base + [
                'status' => 'unreadable_metadata_stream',
                'object_number' => $objectNumber,
            ];
            $dictionary = $this->dictionaryObjectBody($objectBody);
            if ($dictionary !== null) {
                foreach ($this->metadataStreamDictionaryLabels($dictionary, $objects) as $key => $metadataValue) {
                    $review[$key] = $metadataValue;
                }

                $filters = $this->streamFilters($dictionary, $objects);
                if ($filters !== []) {
                    $review['filters'] = $filters;
                }

                $declaredLength = $this->streamLength($dictionary, $objects);
                if ($declaredLength !== null) {
                    $review['declared_length'] = $declaredLength;
                }
            }

            return $review;
        }

        $review = $base + [
            'status' => 'reviewed_outline_item_metadata_stream',
            'object_number' => $objectNumber,
            'bytes' => strlen($stream['content']),
            'sha256' => hash('sha256', $stream['content']),
        ];

        foreach ($this->metadataStreamDictionaryLabels($stream['dictionary'], $objects) as $key => $metadataValue) {
            $review[$key] = $metadataValue;
        }

        $filters = $this->streamFilters($stream['dictionary'], $objects);
        if ($filters !== []) {
            $review['filters'] = $filters;
        }

        $declaredLength = $this->streamLength($stream['dictionary'], $objects);
        if ($declaredLength !== null) {
            $review['declared_length'] = $declaredLength;
        }

        $xmpSummary = $this->xmpPacketReviewSummary($stream['content']);
        if ($xmpSummary !== []) {
            $review['xmp_summary'] = $xmpSummary;
        }

        return $review;
    }

    /**
     * Name-tree destinations can resolve to action dictionaries instead of
     * plain destination arrays. Keep a payload-free summary on document outline
     * rows so metadata reviewers see the action boundary without URI/JS text.
     *
     * @param array<int, string> $objects
     * @param array<string, string> $destinationsByName
     * @param array<string, true> $seenNames
     * @return array<string, mixed>
     */
    private function documentOutlineDestinationActionChainMetadata(
        ?string $value,
        array $objects,
        array $destinationsByName,
        ?string $destinationName = null,
        array $seenNames = []
    ): array {
        if ($value === null) {
            return [];
        }

        $name = $this->destinationNameFromRaw($value, $objects);
        if ($name !== null && array_key_exists($name, $destinationsByName)) {
            if (isset($seenNames[$name])) {
                return [];
            }
            $seenNames[$name] = true;

            return $this->documentOutlineDestinationActionChainMetadata(
                $destinationsByName[$name],
                $objects,
                $destinationsByName,
                $name,
                $seenNames
            );
        }

        $dictionary = $this->resolveDictionaryFromValue($value, $objects);
        if ($dictionary === null) {
            return [];
        }

        $entries = $this->dictionaryTopLevelEntries($dictionary['body']);
        if (!isset($entries['S']) && !isset($entries['Next'])) {
            return [];
        }

        $actionMetadata = $this->documentOutlineActionChainMetadata($value, $objects);
        if ($actionMetadata === []) {
            return [];
        }

        $metadata = [
            'destination_action_review_only' => true,
            'destination_action_payload_included' => false,
            'destination_action_executes_action' => false,
            'destination_action_chain_count' => $actionMetadata['action_chain_count'] ?? 0,
            'destination_action_chain_types' => $actionMetadata['action_chain_types'] ?? [],
            'destination_action_chain_has_next' => $actionMetadata['action_chain_has_next'] ?? false,
            'destination_action_chain_has_javascript' => $actionMetadata['action_chain_has_javascript'] ?? false,
            'destination_action_chain_has_launch' => $actionMetadata['action_chain_has_launch'] ?? false,
        ];

        if ($destinationName !== null) {
            $metadata['destination_action_name'] = $destinationName;
        }

        if ($dictionary['object'] !== null) {
            $metadata['destination_action_object'] = $dictionary['object'];
        }

        $actionType = $this->dictionaryNameValue($dictionary['body'], 'S', $objects);
        if ($actionType !== null) {
            $metadata['destination_action_type'] = $actionType;
        }

        if (isset($actionMetadata['action_chain_objects'])) {
            $metadata['destination_action_chain_objects'] = $actionMetadata['action_chain_objects'];
        }

        return $metadata;
    }

    /**
     * Outline action dictionaries may include chained /Next actions. Keep a
     * payload-free document metadata summary in parity with navigation review.
     *
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function documentOutlineActionChainMetadata(?string $value, array $objects): array
    {
        $seen = [];
        $actions = $this->documentOutlineActionChainRows($value, $objects, $seen);
        if ($actions === []) {
            return [];
        }

        $types = [];
        $objectNumbers = [];
        foreach ($actions as $action) {
            $type = $action['action_type'] ?? null;
            if (is_string($type) && $type !== '') {
                $types[] = $type;
            }

            $objectNumber = $action['action_object'] ?? null;
            if (is_int($objectNumber)) {
                $objectNumbers[] = $objectNumber;
            }
        }

        $types = $this->uniqueStrings($types);
        $metadata = [
            'action_review_only' => true,
            'action_payload_included' => false,
            'executes_action' => false,
            'action_chain_count' => count($actions),
            'action_chain_types' => $types,
            'action_chain_has_next' => count($actions) > 1,
            'action_chain_has_javascript' => in_array('JavaScript', $types, true),
            'action_chain_has_launch' => in_array('Launch', $types, true),
        ];

        if ($objectNumbers !== []) {
            $metadata['action_chain_objects'] = array_values(array_unique($objectNumbers));
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, true> $seen
     * @return list<array{action_type?: string, action_object?: int}>
     */
    private function documentOutlineActionChainRows(
        ?string $value,
        array $objects,
        array &$seen,
        int $depth = 0
    ): array {
        if ($value === null || $depth > 20) {
            return [];
        }

        $arrayItems = $this->arrayItemsFromValue($value, $objects);
        if ($arrayItems !== []) {
            $rows = [];
            foreach ($arrayItems as $item) {
                foreach ($this->documentOutlineActionChainRows($item, $objects, $seen, $depth + 1) as $row) {
                    $rows[] = $row;
                }
            }

            return $rows;
        }

        $dictionary = $this->resolveDictionaryFromValue($value, $objects);
        if ($dictionary === null) {
            return [];
        }

        $actionObject = $this->objectNumberFromReference($value) ?? $dictionary['object'];
        $identity = $actionObject === null ? 'dict:' . md5($dictionary['body']) : 'obj:' . $actionObject;
        if (isset($seen[$identity])) {
            return [];
        }
        $seen[$identity] = true;

        $rows = [];
        $type = $this->dictionaryNameValue($dictionary['body'], 'S', $objects);
        if ($type !== null && $type !== '') {
            $row = ['action_type' => $type];
            if ($actionObject !== null) {
                $row['action_object'] = $actionObject;
            }
            $rows[] = $row;
        }

        foreach ($this->documentOutlineActionChainRows(
            $this->dictionaryTopLevelRawValue($dictionary['body'], 'Next'),
            $objects,
            $seen,
            $depth + 1
        ) as $nextRow) {
            $rows[] = $nextRow;
        }

        return $rows;
    }

    /**
     * @param array<int, string> $objects
     * @return list<float>|null
     */
    private function documentOutlineColorRgb(?string $value, array $objects): ?array
    {
        if ($value === null) {
            return null;
        }

        $items = $this->arrayItemsFromValue($value, $objects);
        if (count($items) !== 3) {
            return null;
        }

        $rgb = [];
        for ($index = 0; $index < 3; $index++) {
            $component = $this->destinationNumericValue($items[$index], $objects);
            if ($component === null) {
                return null;
            }

            $rgb[] = max(0.0, min(1.0, $component));
        }

        return $rgb;
    }

    /**
     * @param list<float> $rgb
     */
    private function rgbUnitColorToHex(array $rgb): string
    {
        return sprintf(
            '#%02x%02x%02x',
            (int) round($rgb[0] * 255),
            (int) round($rgb[1] * 255),
            (int) round($rgb[2] * 255)
        );
    }

    /**
     * PDF outline items may associate a bookmark with a structure element
     * through `/SE`. Keep the association as review-only metadata so tagged
     * accessibility provenance is available without treating structure strings
     * or associated-file payloads as visible document text.
     *
     * @param array<int, string> $objects
     * @param array<int, int> $pageIndexes
     * @param array{root_language: string|null, role_map: array<string, string>} $structureContext
     * @return array<string, mixed>
     */
    private function documentOutlineItemStructureElementMetadata(
        ?string $value,
        array $objects,
        array $pageIndexes,
        array $structureContext
    ): array {
        if ($value === null) {
            return [];
        }

        $elements = [];
        $this->collectStructureReviewElements(
            $value,
            $objects,
            null,
            $structureContext['root_language'],
            $structureContext['role_map'],
            $pageIndexes,
            $elements
        );
        if ($elements === []) {
            return [];
        }

        $element = $elements[0];
        $metadata = [
            'source' => 'outline_item_structure_element',
            'review_only' => true,
            'visible_text_source' => false,
            'payload_included' => false,
        ];

        foreach ([
            'object',
            'raw_role',
            'role',
            'role_mapped',
            'page',
            'page_number',
            'page_object',
            'language',
            'language_inherited',
            'title',
            'id',
            'alternate_text',
            'actual_text',
            'expansion_text',
            'classes',
            'revision',
            'namespace',
            'marked_content',
            'mcids',
            'associated_file_count',
            'associated_files',
        ] as $key) {
            if (array_key_exists($key, $element)) {
                $metadata[$key] = $element[$key];
            }
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     * @return array{value: string|null, name: string|null, action_type: string|null, action_object: int|null}
     */
    private function documentOutlineItemDestination(string $dictionary, array $objects): array
    {
        $destination = $this->dictionaryTopLevelRawValue($dictionary, 'Dest');
        if ($destination !== null) {
            return [
                'value' => $destination,
                'name' => $this->destinationNameFromRaw($destination, $objects),
                'action_type' => null,
                'action_object' => null,
            ];
        }

        $actionValue = $this->dictionaryTopLevelRawValue($dictionary, 'A');
        $action = $this->resolveDictionaryFromValue($actionValue, $objects);
        if ($action === null) {
            return [
                'value' => null,
                'name' => null,
                'action_type' => null,
                'action_object' => null,
            ];
        }

        $actionType = $this->dictionaryNameValue($action['body'], 'S', $objects);
        $actionDestination = $this->dictionaryTopLevelRawValue($action['body'], 'D');

        return [
            'value' => $actionType === 'GoTo' ? $actionDestination : null,
            'name' => $actionDestination === null ? null : $this->destinationNameFromRaw($actionDestination, $objects),
            'action_type' => $actionType,
            'action_object' => $action['object'],
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return list<int>
     */
    private function orderedDestinationPageObjectNumbers(string $catalog, array $objects): array
    {
        $pagesValue = $this->dictionaryTopLevelRawValue($catalog, 'Pages');
        $pagesRoot = $this->validObjectNumberFromReference($pagesValue, $objects);
        if ($pagesRoot !== null) {
            $pages = $this->destinationPageObjectNumbersFromTree($pagesRoot, $objects);
            if ($pages !== []) {
                return $pages;
            }
        }

        $pages = [];
        foreach ($objects as $objectNumber => $objectBody) {
            $dictionary = $this->dictionaryObjectBody($objectBody);
            if ($dictionary !== null && $this->dictionaryStringValue($dictionary, 'Type') === 'Page') {
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
    private function destinationPageObjectNumbersFromTree(int $objectNumber, array $objects, array $seen = []): array
    {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return [];
        }
        $seen[$objectNumber] = true;

        $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
        if ($dictionary === null) {
            return [];
        }

        $type = $this->dictionaryStringValue($dictionary, 'Type');
        if ($type === 'Page') {
            return [$objectNumber];
        }

        $kids = $this->arrayItemsFromValue($this->dictionaryTopLevelRawValue($dictionary, 'Kids') ?? '', $objects);
        if ($kids === []) {
            return [];
        }

        $pages = [];
        foreach ($kids as $kid) {
            $kidObjectNumber = $this->validObjectNumberFromReference($kid, $objects);
            if ($kidObjectNumber === null) {
                continue;
            }

            foreach ($this->destinationPageObjectNumbersFromTree($kidObjectNumber, $objects, $seen) as $pageObjectNumber) {
                $pages[] = $pageObjectNumber;
            }
        }

        return $pages;
    }

    /**
     * @param array{body: string, object: int|null} $node
     * @param array<int, string> $objects
     * @param list<array{name: string, value: string, source: string}> $entries
     * @param array<int, true> $seenObjects
     * @param array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}|null $inheritedLimits
     */
    private function collectDestinationNameTreeEntries(
        array $node,
        array $objects,
        array &$entries,
        array &$seenObjects,
        int $depth = 0,
        ?array $inheritedLimits = null
    ): void
    {
        if ($depth > 20) {
            return;
        }

        $objectNumber = $node['object'];
        if ($objectNumber !== null) {
            if (isset($seenObjects[$objectNumber])) {
                return;
            }
            $seenObjects[$objectNumber] = true;
        }

        $limits = $this->nameTreeEffectiveLimits($node, $objects, $inheritedLimits);
        $kids = $this->arrayItemsFromValue($this->dictionaryTopLevelRawValue($node['body'], 'Kids') ?? '', $objects);
        $names = $this->arrayItemsFromValue($this->dictionaryTopLevelRawValue($node['body'], 'Names') ?? '', $objects);
        if ($kids === []) {
            $entryLimits = $this->nameTreeLimitsMatchAnyPairKey($names, $objects, $limits)
                ? $limits
                : $inheritedLimits;
            for ($index = 0, $count = count($names); $index + 1 < $count;) {
                $name = $this->destinationNameDetailsFromRaw($names[$index], $objects);
                if ($name === null || $name['text'] === '') {
                    $index++;
                    continue;
                }

                if (!$this->nameTreeNameWithinLimits($name['text'], $entryLimits, $name['bytes'])) {
                    $index += 2;
                    continue;
                }

                $entries[] = [
                    'name' => $name['text'],
                    'value' => $names[$index + 1],
                    'source' => 'names_dests',
                ];
                $index += 2;
            }
        }

        foreach ($kids as $kid) {
            if ($this->validObjectNumberFromReference($kid, $objects) === null) {
                continue;
            }

            $child = $this->resolveDictionaryFromValue($kid, $objects);
            if ($child !== null) {
                $this->collectDestinationNameTreeEntries($child, $objects, $entries, $seenObjects, $depth + 1, $limits);
            }
        }
    }

    /**
     * Catalog /Names may include document-level name trees beyond embedded
     * files and destinations. Summarize those rows as review-only metadata so
     * JavaScript, URL, rendition, or presentation operands do not become
     * visible WordPress text or document title fallbacks.
     *
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function catalogNameTreeReviewMetadata(string $catalog, array $objects): array
    {
        $names = $this->resolveDictionaryFromValue($this->dictionaryTopLevelRawValue($catalog, 'Names'), $objects);
        if ($names === null) {
            return [];
        }

        $trees = [];
        foreach ($this->dictionaryTopLevelEntries($names['body']) as $treeName => $treeValue) {
            if (isset(self::SPECIALIZED_CATALOG_NAME_TREE_KEYS[$treeName])) {
                continue;
            }

            $treeRoot = $this->resolveDictionaryFromValue($treeValue, $objects);
            if ($treeRoot === null) {
                continue;
            }

            $entries = [];
            $seenObjects = [];
            $this->collectCatalogNameTreeReviewRows($treeName, $treeRoot, $objects, $entries, $seenObjects);
            if ($entries === []) {
                continue;
            }

            $trees[$treeName] = [
                'source' => 'catalog_names_' . strtolower($treeName),
                'review_only' => true,
                'payload_included' => false,
                'count' => count($entries),
                'names' => array_values(array_map(static fn (array $entry): string => $entry['name'], $entries)),
                'entries' => $entries,
            ];
        }

        if ($trees === []) {
            return [];
        }

        return [
            'source' => 'catalog_names_review',
            'review_only' => true,
            'payload_included' => false,
            'tree_count' => count($trees),
            'tree_names' => array_keys($trees),
            'trees' => $trees,
        ];
    }

    /**
     * @param array{body: string, object: int|null} $node
     * @param array<int, string> $objects
     * @param list<array<string, mixed>> $entries
     * @param array<int, true> $seenObjects
     * @param array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}|null $inheritedLimits
     */
    private function collectCatalogNameTreeReviewRows(
        string $treeName,
        array $node,
        array $objects,
        array &$entries,
        array &$seenObjects,
        int $depth = 0,
        ?array $inheritedLimits = null
    ): void {
        if ($depth > 20) {
            return;
        }

        $objectNumber = $node['object'];
        if ($objectNumber !== null) {
            if (isset($seenObjects[$objectNumber])) {
                return;
            }
            $seenObjects[$objectNumber] = true;
        }

        $limits = $this->nameTreeEffectiveLimits($node, $objects, $inheritedLimits);
        $kids = $this->arrayItemsFromValue($this->dictionaryTopLevelRawValue($node['body'], 'Kids') ?? '', $objects);
        $names = $this->arrayItemsFromValue($this->dictionaryTopLevelRawValue($node['body'], 'Names') ?? '', $objects);
        if ($kids === []) {
            $entryLimits = $this->nameTreeLimitsMatchAnyPairKey($names, $objects, $limits)
                ? $limits
                : $inheritedLimits;
            for ($index = 0, $count = count($names); $index + 1 < $count; $index += 2) {
                $name = $this->destinationNameDetailsFromRaw($names[$index], $objects);
                if (
                    $name === null
                    || $name['text'] === ''
                    || !$this->nameTreeNameWithinLimits($name['text'], $entryLimits, $name['bytes'])
                ) {
                    continue;
                }

                $entries[] = [
                    'tree' => $treeName,
                    'name' => $name['text'],
                    'index' => count($entries),
                ] + $this->catalogNameTreeEntryReview($names[$index + 1], $objects);
            }
        }

        foreach ($kids as $kid) {
            if ($this->validObjectNumberFromReference($kid, $objects) === null) {
                continue;
            }

            $child = $this->resolveDictionaryFromValue($kid, $objects);
            if ($child !== null) {
                $this->collectCatalogNameTreeReviewRows($treeName, $child, $objects, $entries, $seenObjects, $depth + 1, $limits);
            }
        }
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function catalogNameTreeEntryReview(string $value, array $objects): array
    {
        $review = [
            'review_only' => true,
            'payload_included' => false,
            'executes_action' => false,
        ];

        $objectNumber = $this->objectNumberFromReference($value);
        if ($objectNumber !== null) {
            $review['value_object'] = $objectNumber;
        }

        $dictionary = $this->resolveDictionaryFromValue($value, $objects);
        if ($dictionary !== null) {
            return $review + $this->catalogNameTreeDictionaryReview($dictionary['body'], $dictionary['object'], $objects);
        }

        $resolved = trim($this->resolvePdfValue($value, $objects) ?? $value);
        if ($resolved === '') {
            return $review + ['value_type' => 'unresolved'];
        }

        if (str_starts_with($resolved, '[')) {
            return $review + [
                'value_type' => 'array',
                'item_count' => count($this->arrayItemsFromValue($resolved, $objects)),
            ];
        }

        $stringValue = $this->stringValueFromRaw($resolved);
        if ($stringValue !== null) {
            return $review + [
                'value_type' => 'scalar',
                'scalar_type' => str_starts_with($resolved, '/') ? 'name' : 'string',
                'value_bytes' => strlen($stringValue),
                'value_sha256' => hash('sha256', $stringValue),
            ];
        }

        if (preg_match('/^-?\d+$/', $resolved) === 1) {
            return $review + ['value_type' => 'integer'];
        }

        if (preg_match('/^-?(?:\d+\.\d*|\d*\.\d+)$/', $resolved) === 1) {
            return $review + ['value_type' => 'number'];
        }

        return $review + ['value_type' => 'unknown'];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function catalogNameTreeDictionaryReview(string $dictionary, ?int $objectNumber, array $objects): array
    {
        $entries = $this->dictionaryTopLevelEntries($dictionary);
        $review = [
            'value_type' => 'dictionary',
            'dictionary_keys' => array_keys($entries),
        ];

        if ($objectNumber !== null) {
            $review['dictionary_object'] = $objectNumber;
        }

        foreach ([
            'type' => $this->dictionaryNameValue($dictionary, 'Type', $objects),
            'subtype' => $this->dictionaryNameValue($dictionary, 'Subtype', $objects),
        ] as $key => $metadataValue) {
            if (is_string($metadataValue) && $metadataValue !== '') {
                $review[$key] = $metadataValue;
            }
        }

        $actionType = $this->dictionaryNameValue($dictionary, 'S', $objects);
        if ($actionType !== null && $actionType !== '') {
            $review['action_type'] = $actionType;
            $review['action_review_only'] = true;
            $review['executes_action'] = false;
            if ($actionType === 'JavaScript') {
                $review['has_javascript'] = true;
                $review['javascript_payload_included'] = false;
                if (isset($entries['JS'])) {
                    $review['javascript_source'] = $this->catalogNameTreePayloadSourceReview($entries['JS'], $objects);
                }
            }
            if ($actionType === 'URI') {
                $review['uri_payload_included'] = false;
                if (isset($entries['URI'])) {
                    $review['uri_source'] = $this->catalogNameTreePayloadSourceReview($entries['URI'], $objects);
                }
            }
        }

        $metadataReview = $this->associatedFileMetadataStreamProvenance($entries['Metadata'] ?? null, $objects);
        if ($metadataReview !== []) {
            $review['metadata_review'] = $metadataReview;
        }

        $outputIntentsReview = $this->associatedFileOutputIntentProvenance($entries['OutputIntents'] ?? null, $objects);
        if ($outputIntentsReview !== []) {
            $review['output_intents_review'] = $outputIntentsReview;
        }

        return $review;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function catalogNameTreePayloadSourceReview(string $value, array $objects): array
    {
        $objectNumber = $this->objectNumberFromReference($value);
        if ($objectNumber !== null) {
            $stream = isset($objects[$objectNumber]) ? $this->decodeStreamEntryObject($objects[$objectNumber], $objects) : null;
            $review = [
                'source_type' => $stream === null ? 'object' : 'stream',
                'object' => $objectNumber,
                'payload_included' => false,
            ];
            if ($stream !== null) {
                $review['bytes'] = strlen($stream['content']);
                $review['sha256'] = hash('sha256', $stream['content']);
                $filters = $this->streamFilters($stream['dictionary'], $objects);
                if ($filters !== []) {
                    $review['filters'] = $filters;
                }
            }

            return $review;
        }

        $resolved = trim($this->resolvePdfValue($value, $objects) ?? $value);
        $scalar = $this->stringValueFromRaw($resolved);
        if ($scalar !== null) {
            return [
                'source_type' => 'string',
                'bytes' => strlen($scalar),
                'sha256' => hash('sha256', $scalar),
                'payload_included' => false,
            ];
        }

        return [
            'source_type' => 'unknown',
            'payload_included' => false,
        ];
    }

    /**
     * @param array{body: string, object: int|null} $node
     * @param array<int, string> $objects
     * @param array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}|null $inheritedLimits
     * @return array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}|null
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

        $lower = strcmp($nodeLimits['lower_bytes'], $inheritedLimits['lower_bytes']) < 0
            ? ['text' => $inheritedLimits['lower'], 'bytes' => $inheritedLimits['lower_bytes']]
            : ['text' => $nodeLimits['lower'], 'bytes' => $nodeLimits['lower_bytes']];
        $upper = strcmp($nodeLimits['upper_bytes'], $inheritedLimits['upper_bytes']) > 0
            ? ['text' => $inheritedLimits['upper'], 'bytes' => $inheritedLimits['upper_bytes']]
            : ['text' => $nodeLimits['upper'], 'bytes' => $nodeLimits['upper_bytes']];
        if (strcmp($lower['bytes'], $upper['bytes']) > 0) {
            return $inheritedLimits;
        }

        return [
            'lower' => $lower['text'],
            'upper' => $upper['text'],
            'lower_bytes' => $lower['bytes'],
            'upper_bytes' => $upper['bytes'],
        ];
    }

    /**
     * @param array{body: string, object: int|null} $node
     * @param array<int, string> $objects
     * @return array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}|null
     */
    private function nameTreeNodeLimits(array $node, array $objects): ?array
    {
        $items = $this->arrayItemsFromValue($this->dictionaryTopLevelRawValue($node['body'], 'Limits') ?? '', $objects);
        if (count($items) < 2) {
            return null;
        }

        $lower = $this->destinationNameDetailsFromRaw($items[0], $objects);
        $upper = $this->destinationNameDetailsFromRaw($items[1], $objects);
        if ($lower === null || $upper === null || $lower['text'] === '' || $upper['text'] === '') {
            return null;
        }
        if (strcmp($lower['bytes'], $upper['bytes']) > 0) {
            return null;
        }

        return [
            'lower' => $lower['text'],
            'upper' => $upper['text'],
            'lower_bytes' => $lower['bytes'],
            'upper_bytes' => $upper['bytes'],
        ];
    }

    /**
     * @param array{lower: string, upper: string, lower_bytes?: string, upper_bytes?: string}|null $limits
     */
    private function nameTreeNameWithinLimits(string $name, ?array $limits, ?string $nameBytes = null): bool
    {
        if ($limits === null) {
            return true;
        }

        $candidate = $nameBytes ?? $name;
        $lower = $limits['lower_bytes'] ?? $limits['lower'];
        $upper = $limits['upper_bytes'] ?? $limits['upper'];

        return strcmp($lower, $upper) <= 0
            && strcmp($candidate, $lower) >= 0
            && strcmp($candidate, $upper) <= 0;
    }

    /**
     * @param list<string> $items
     * @param array<int, string> $objects
     * @param array{lower: string, upper: string, lower_bytes?: string, upper_bytes?: string}|null $limits
     */
    private function nameTreeLimitsMatchAnyPairKey(array $items, array $objects, ?array $limits): bool
    {
        if ($limits === null || $items === []) {
            return true;
        }

        for ($index = 0, $count = count($items); $index + 1 < $count;) {
            $name = $this->destinationNameDetailsFromRaw($items[$index], $objects);
            if ($name === null) {
                $index++;
                continue;
            }

            if ($this->nameTreeNameWithinLimits($name['text'], $limits, $name['bytes'])) {
                return true;
            }
            $index += 2;
        }

        return false;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, int> $pageIndexes
     * @param array<string, string> $destinationsByName
     * @param array<string, true> $seenNames
     * @return array<string, mixed>|null
     */
    private function documentDestinationDetails(
        string $value,
        array $objects,
        array $pageIndexes,
        array $destinationsByName,
        ?string $destinationName,
        array $seenNames = []
    ): ?array {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $page = $this->destinationPageFromRaw($trimmed, $objects, $pageIndexes);
        if ($page !== null) {
            return $this->documentDestinationRow($page, $destinationName, null, []);
        }

        $name = $this->destinationNameFromRaw($trimmed, $objects);
        if ($name !== null && array_key_exists($name, $destinationsByName)) {
            if (isset($seenNames[$name])) {
                return null;
            }
            $seenNames[$name] = true;

            return $this->documentDestinationDetails(
                $destinationsByName[$name],
                $objects,
                $pageIndexes,
                $destinationsByName,
                $name,
                $seenNames
            );
        }

        $dictionary = $this->resolveDictionaryFromValue($trimmed, $objects);
        if ($dictionary !== null) {
            $entries = $this->dictionaryTopLevelEntries($dictionary['body']);
            if (isset($entries['D'])) {
                return $this->documentDestinationDetails(
                    $entries['D'],
                    $objects,
                    $pageIndexes,
                    $destinationsByName,
                    $destinationName,
                    $seenNames
                );
            }
        }

        $items = $this->arrayItemsFromValue($trimmed, $objects);
        if ($items !== []) {
            return $this->documentDestinationArrayDetails($items, $objects, $pageIndexes, $destinationName);
        }

        $resolved = $this->trimPdfWhitespaceAndComments($this->resolvePdfValue($trimmed, $objects) ?? $trimmed);
        if (preg_match('/^\d+$/', $resolved) === 1) {
            $pageIndex = (int) $resolved;
            return $pageIndex >= 0 && $pageIndex < count($pageIndexes)
                ? $this->documentDestinationRow(['page' => $pageIndex, 'page_object' => null], $destinationName, null, [])
                : null;
        }

        return null;
    }

    /**
     * @param list<string> $items
     * @param array<int, string> $objects
     * @param array<int, int> $pageIndexes
     * @return array<string, mixed>|null
     */
    private function documentDestinationArrayDetails(array $items, array $objects, array $pageIndexes, ?string $destinationName): ?array
    {
        $page = $this->destinationPageFromRaw($items[0] ?? '', $objects, $pageIndexes);
        if ($page === null) {
            return null;
        }

        $viewMode = isset($items[1]) ? $this->destinationNameFromRaw($items[1], $objects) : null;
        if ($viewMode !== null && !isset(self::VALID_DESTINATION_VIEW_NAMES[$viewMode])) {
            return null;
        }
        if (!$this->documentDestinationArrayViewIsValid($items, $objects)) {
            return null;
        }

        $viewPosition = [];
        for ($index = 2, $count = count($items); $index < $count; $index++) {
            $viewPosition[] = $this->destinationNumericValue($items[$index], $objects);
        }
        $viewPosition = $this->normalizedDestinationViewPosition($viewMode, $viewPosition);

        if ($viewMode === 'XYZ' && array_key_exists(2, $viewPosition) && $viewPosition[2] === 0.0) {
            $viewPosition[2] = null;
        }

        return $this->documentDestinationRow($page, $destinationName, $viewMode, $viewPosition);
    }

    /**
     * @param list<string> $items
     * @param array<int, string> $objects
     */
    private function documentDestinationArrayViewIsValid(array $items, array $objects): bool
    {
        if (count($items) < 2) {
            return true;
        }

        $viewMode = $this->destinationNameFromRaw($items[1], $objects);
        if ($viewMode === null || !isset(self::VALID_DESTINATION_VIEW_NAMES[$viewMode])) {
            return false;
        }

        $requiredOperands = match ($viewMode) {
            'XYZ' => [2 => true, 3 => true, 4 => true],
            'FitH', 'FitBH', 'FitV', 'FitBV' => [2 => true],
            'FitR' => [2 => true, 3 => true, 4 => true, 5 => true],
            default => [],
        };

        foreach ($requiredOperands as $index => $allowsNull) {
            if (!array_key_exists($index, $items)) {
                return false;
            }

            if (!$this->documentDestinationCoordinateOperandIsValid($items[$index], $objects, $allowsNull)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, string> $objects
     */
    private function documentDestinationCoordinateOperandIsValid(string $value, array $objects, bool $allowsNull): bool
    {
        if ($this->objectNumberFromReference($value) !== null && $this->validObjectNumberFromReference($value, $objects) === null) {
            return false;
        }

        $resolved = $this->trimPdfWhitespaceAndComments($this->resolvePdfValue($value, $objects) ?? $value);
        if ($resolved === 'null') {
            return $allowsNull;
        }

        return is_numeric($resolved);
    }

    /**
     * @param list<float|null> $viewPosition
     * @return list<float|null>
     */
    private function normalizedDestinationViewPosition(?string $viewMode, array $viewPosition): array
    {
        $expectedCount = match ($viewMode) {
            'Fit', 'FitB' => 0,
            'FitH', 'FitBH', 'FitV', 'FitBV' => 1,
            'FitR' => 4,
            'XYZ' => 3,
            default => null,
        };

        return $expectedCount === null ? $viewPosition : array_slice($viewPosition, 0, $expectedCount);
    }

    /**
     * @param array{page: int, page_object: int|null} $page
     * @param list<float|null> $viewPosition
     * @return array<string, mixed>
     */
    private function documentDestinationRow(array $page, ?string $destinationName, ?string $viewMode, array $viewPosition): array
    {
        return [
            'name' => $destinationName,
            'destination' => $destinationName,
            'page' => $page['page'],
            'page_number' => $page['page'] + 1,
            'page_object' => $page['page_object'],
            'view_mode' => $viewMode,
            'view_position' => $viewPosition,
            'view_parameters' => $this->destinationViewParameters($viewMode, $viewPosition),
        ];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, int> $pageIndexes
     * @return array{page: int, page_object: int|null}|null
     */
    private function destinationPageFromRaw(string $value, array $objects, array $pageIndexes): ?array
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $objectNumber = $this->validObjectNumberFromReference($trimmed, $objects);
        if ($objectNumber !== null && isset($pageIndexes[$objectNumber])) {
            return [
                'page' => $pageIndexes[$objectNumber],
                'page_object' => $objectNumber,
            ];
        }

        $resolved = $this->trimPdfWhitespaceAndComments($this->resolvePdfValue($trimmed, $objects) ?? $trimmed);
        $resolvedObjectNumber = $this->validObjectNumberFromReference($resolved, $objects);
        if ($resolvedObjectNumber !== null && isset($pageIndexes[$resolvedObjectNumber])) {
            return [
                'page' => $pageIndexes[$resolvedObjectNumber],
                'page_object' => $resolvedObjectNumber,
            ];
        }

        if (preg_match('/^\d+$/', $resolved) === 1 && (int) $resolved < count($pageIndexes)) {
            return [
                'page' => (int) $resolved,
                'page_object' => null,
            ];
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function destinationNameFromRaw(string $value, array $objects): ?string
    {
        $resolved = $this->reviewValueFromRaw($value, $objects);

        return is_string($resolved) && $resolved !== '' ? $resolved : null;
    }

    /**
     * @param array<int, string> $objects
     * @return array{text: string, bytes: string}|null
     */
    private function destinationNameDetailsFromRaw(string $value, array $objects): ?array
    {
        $resolved = $this->trimPdfWhitespaceAndComments($this->resolvePdfValue($value, $objects) ?? $value);
        if ($resolved === '') {
            return null;
        }

        if ($resolved[0] === '(') {
            $string = $this->literalStringBytesAt($resolved, 0);
            if ($string === null) {
                return null;
            }

            $text = $this->decodePdfStringBytes($string['bytes']);
            return $text === '' ? null : [
                'text' => $text,
                'bytes' => $string['bytes'],
            ];
        }

        if ($resolved[0] === '<' && ($resolved[1] ?? '') !== '<') {
            $string = $this->hexStringBytesAt($resolved, 0);
            if ($string === null) {
                return null;
            }

            $text = $this->decodePdfStringBytes($string['bytes']);
            return $text === '' ? null : [
                'text' => $text,
                'bytes' => $string['bytes'],
            ];
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function destinationNumericValue(string $value, array $objects): ?float
    {
        $resolved = $this->trimPdfWhitespaceAndComments($this->resolvePdfValue($value, $objects) ?? $value);
        if ($resolved === '' || $resolved === 'null') {
            return null;
        }

        return is_numeric($resolved) ? (float) $resolved : null;
    }

    /**
     * @param list<float|null> $viewPosition
     * @return array<string, float|null>
     */
    private function destinationViewParameters(?string $viewMode, array $viewPosition): array
    {
        $names = match ($viewMode) {
            'XYZ' => ['left', 'top', 'zoom'],
            'FitH', 'FitBH' => ['top'],
            'FitV', 'FitBV' => ['left'],
            'FitR' => ['left', 'bottom', 'right', 'top'],
            default => [],
        };

        $parameters = [];
        foreach ($names as $index => $name) {
            $parameters[$name] = $viewPosition[$index] ?? null;
        }

        return $parameters;
    }

    /**
     * Catalog /PieceInfo is application-private review metadata. It can carry
     * keys named /Metadata or /OutputIntents, but those are not document-level
     * XMP or PDF/A roots unless they are direct Catalog entries.
     *
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function pieceInfoMetadata(?string $pieceInfoValue, array $objects): array
    {
        $pieceInfo = $this->resolveDictionaryFromValue($pieceInfoValue, $objects);
        if ($pieceInfo === null) {
            return [];
        }

        $metadata = [];
        foreach ($this->dictionaryTopLevelEntries($pieceInfo['body']) as $application => $pieceValue) {
            $piece = $this->resolveDictionaryFromValue($pieceValue, $objects);
            if ($piece === null) {
                continue;
            }

            $entry = [];
            $lastModified = $this->reviewValueFromRaw($this->dictionaryTopLevelRawValue($piece['body'], 'LastModified'), $objects);
            if (is_string($lastModified) && $lastModified !== '') {
                $entry['last_modified'] = $lastModified;
            }

            $privateValue = $this->dictionaryTopLevelRawValue($piece['body'], 'Private');
            $privateStream = $this->pieceInfoPrivateStreamReviewMetadata($privateValue, $objects);
            if ($privateStream !== null && $this->pieceInfoPrivateStreamHasChecksumReview($privateStream)) {
                $entry['private_stream'] = $privateStream;
            } else {
                $private = $this->reviewValueFromRaw($privateValue, $objects);
                if ($private !== null && $private !== '' && (!is_array($private) || $private !== [])) {
                    $entry['private'] = $private;
                }
            }

            $privateStreams = $this->pieceInfoPrivateStreamReviewRows($privateValue, $objects);
            if ($privateStreams !== []) {
                $entry['private_streams'] = $privateStreams;
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
    private function pieceInfoPrivateStreamReviewRows(?string $privateValue, array $objects): array
    {
        if ($privateValue === null) {
            return [];
        }

        $stream = $this->pieceInfoPrivateStreamReviewMetadata($privateValue, $objects);
        if ($stream !== null && $this->pieceInfoPrivateStreamHasChecksumReview($stream)) {
            return [['key' => 'Private'] + $stream];
        }

        $private = $this->resolveDictionaryFromValue($privateValue, $objects);
        if ($private === null) {
            return [];
        }

        $rows = [];
        foreach ($this->dictionaryTopLevelEntries($private['body']) as $key => $value) {
            $stream = $this->pieceInfoPrivateStreamReviewMetadata($value, $objects);
            if ($stream !== null && $this->pieceInfoPrivateStreamHasChecksumReview($stream)) {
                $rows[] = ['key' => $key] + $stream;
            }
        }

        return $rows;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function pieceInfoPrivateStreamReviewMetadata(?string $value, array $objects): ?array
    {
        if ($value === null) {
            return null;
        }

        $objectNumber = $this->objectNumberFromReference($value);
        $body = $objectNumber === null
            ? trim($this->resolvePdfValue($value, $objects) ?? $value)
            : ($objects[$objectNumber] ?? null);
        if ($body === null || $body === '') {
            return null;
        }

        $stream = $this->decodeStreamEntryObject($body, $objects);
        if ($stream === null) {
            return null;
        }

        $metadata = [
            'object' => $objectNumber,
            'size' => strlen($stream['content']),
            'content_sha256' => hash('sha256', $stream['content']),
        ];

        $declaredLength = $this->dictionaryIntegerValue($stream['dictionary'], 'Length', $objects);
        if ($declaredLength !== null) {
            $metadata['declared_length'] = $declaredLength;
        }

        $mimeType = $this->dictionaryNameValue($stream['dictionary'], 'Subtype', $objects);
        if ($mimeType !== null && $mimeType !== '') {
            $metadata['mime_type'] = $mimeType;
        }

        $filters = $this->streamFilters($stream['dictionary'], $objects);
        if ($filters !== []) {
            $metadata['filters'] = $filters;
        }

        foreach ($this->embeddedFileParamsMetadata($stream['dictionary'], $objects, $stream['content']) as $key => $metadataValue) {
            $metadata[$key] = $metadataValue;
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $stream
     */
    private function pieceInfoPrivateStreamHasChecksumReview(array $stream): bool
    {
        return array_key_exists('checksum', $stream)
            || array_key_exists('computed_checksum', $stream)
            || array_key_exists('checksum_matches', $stream);
    }

    /**
     * Expose Standard security-handler review metadata without attempting
     * password validation or decrypting document content.
     *
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function extractEncryptionMetadata(string $pdfBytes, array $objects): array
    {
        $entry = $this->encryptionDictionaryEntry($pdfBytes, $objects);
        if ($entry === null) {
            return [];
        }

        $dictionary = $entry['body'];
        $version = $this->dictionaryIntegerValue($dictionary, 'V', $objects);
        $revision = $this->dictionaryIntegerValue($dictionary, 'R', $objects);
        $keyLength = $this->dictionaryIntegerValue($dictionary, 'Length', $objects);
        $encryptMetadataReview = $this->encryptMetadataDeclarationReview($dictionary, $objects);

        $metadata = [
            'is_encrypted' => true,
            'source' => $entry['source'],
            'review_only' => true,
            'requires_password_for_content_extraction' => true,
        ];

        if ($entry['object'] !== null) {
            $metadata['object_number'] = $entry['object'];
        }
        if (isset($entry['generation'])) {
            $metadata['object_generation'] = $entry['generation'];
        }
        if (($entry['malformed_encrypt_dictionary'] ?? false) === true) {
            $metadata['malformed_encrypt_dictionary'] = true;
            $metadata['encrypt_dictionary_resolved'] = false;
            $metadata['encrypt_operand_shape'] = $entry['encrypt_operand_shape'] ?? null;
            $metadata['encrypt_operand_status'] = $entry['encrypt_operand_status'] ?? null;
            foreach ([
                'duplicate_encrypt_dictionary_entries',
                'encrypt_dictionary_declared_entry_count',
                'encrypt_dictionary_resolved_entry_count',
                'encrypt_dictionary_entry_statuses',
                'encrypt_dictionary_entry_shapes',
            ] as $key) {
                if (array_key_exists($key, $entry)) {
                    $metadata[$key] = $entry[$key];
                }
            }
        }

        $filter = $this->dictionaryNameValue($dictionary, 'Filter', $objects)
            ?? $this->dictionaryStringValue($dictionary, 'Filter');
        if ($filter !== null) {
            $metadata['filter'] = $filter;
        }

        $subfilter = $this->dictionaryNameValue($dictionary, 'SubFilter', $objects)
            ?? $this->dictionaryStringValue($dictionary, 'SubFilter');
        if ($subfilter !== null) {
            $metadata['subfilter'] = $subfilter;
        }

        if ($version !== null) {
            $metadata['version'] = $version;
            $metadata['algorithm'] = $this->encryptionAlgorithmLabel($version);
        }

        if ($revision !== null) {
            $metadata['revision'] = $revision;
            $metadata['revision_label'] = $this->standardRevisionLabel($revision);
        }

        if ($keyLength !== null || $version === 1) {
            $metadata['key_length_bits'] = $keyLength ?? 40;
        }

        $metadata['encrypt_metadata'] = (bool) $encryptMetadataReview['effective_value'];
        $metadata['encrypt_metadata_explicit'] = (bool) $encryptMetadataReview['explicit'];
        $metadata['encrypt_metadata_trusted'] = (bool) $encryptMetadataReview['trusted'];
        $metadata['encrypt_metadata_defaulted'] = (bool) $encryptMetadataReview['defaulted'];
        $metadata['encrypt_metadata_defaulted_fail_closed'] = (bool) $encryptMetadataReview['defaulted_fail_closed'];
        $metadata['encrypt_metadata_status'] = $encryptMetadataReview['status'];
        $metadata['encrypt_metadata_declaration_review'] = $encryptMetadataReview;

        foreach ([
            'StmF' => 'stream_filter',
            'StrF' => 'string_filter',
            'EFF' => 'embedded_file_filter',
        ] as $pdfName => $key) {
            $value = $this->dictionaryNameValue($dictionary, $pdfName, $objects)
                ?? $this->dictionaryStringValue($dictionary, $pdfName);
            if ($value !== null) {
                $metadata[$key] = $value;
            }
        }
        $this->applyCryptFilterDefaults($metadata, $version);

        $cryptFilterDictionaryReview = $this->cryptFilterDictionaryDeclarationReview($dictionary, $objects);
        if ($cryptFilterDictionaryReview !== []) {
            $metadata['crypt_filter_dictionary_declaration_review'] = $cryptFilterDictionaryReview;
        }

        $cryptFilterRoleReview = $this->cryptFilterRoleDeclarationReview($dictionary, $objects, $metadata);
        if ($cryptFilterRoleReview !== []) {
            $metadata['crypt_filter_role_declaration_review'] = $cryptFilterRoleReview;
        }

        $cryptFilters = $this->cryptFilterMetadata($dictionary, $objects);
        if ($cryptFilters !== []) {
            $metadata['crypt_filters'] = $cryptFilters;
        }

        $publicKeyRecipientReview = $this->publicKeyRecipientReview($dictionary, $objects, $cryptFilters, $metadata);
        if ($publicKeyRecipientReview !== []) {
            $metadata['public_key_recipient_review'] = $publicKeyRecipientReview;
        }

        $perms = $this->encryptedPermissionValidationMetadata($dictionary, $objects);
        if ($perms !== null) {
            $metadata['perms'] = $perms;
        }

        $permissionReview = $this->standardPermissionWordDeclarationReview($dictionary, $objects, $revision);
        if ($permissionReview !== []) {
            $metadata['standard_permission_word_review'] = $permissionReview;
        }

        $permissionValue = $this->dictionaryIntegerValue($dictionary, 'P', $objects);
        if ($permissionValue !== null) {
            $metadata['standard_permissions'] = $this->standardPermissionMetadata($permissionValue, $revision);
        }

        $authReview = $this->standardAuthenticationReview($dictionary, $objects, $cryptFilters, $metadata, $perms);
        if ($authReview !== []) {
            $metadata['standard_authentication_review'] = $authReview;
        }

        $standardParameterDeclarationReview = ($metadata['filter'] ?? null) === 'Standard'
            ? $this->standardSecurityHandlerParameterDeclarationReview($dictionary)
            : [];
        if ($standardParameterDeclarationReview !== []) {
            $metadata['standard_security_handler_parameter_declaration_review'] = $standardParameterDeclarationReview;
        }

        $standardParameterReview = $this->standardSecurityHandlerParameterReview($metadata);
        if ($standardParameterReview !== []) {
            $metadata['standard_security_handler_parameter_review'] = $standardParameterReview;
        }

        return $metadata;
    }

    /**
     * Encryption dictionaries using crypt filters inherit PDF defaults for
     * omitted content roles. Materialize them once so security and attachment
     * review paths make the same import decision.
     *
     * @param array<string, mixed> $metadata
     */
    private function applyCryptFilterDefaults(array &$metadata, ?int $version): void
    {
        if (!in_array($version, [4, 5], true)) {
            return;
        }

        if (!is_string($metadata['stream_filter'] ?? null) || $metadata['stream_filter'] === '') {
            $metadata['stream_filter'] = 'Identity';
            $metadata['stream_filter_defaulted'] = true;
            $metadata['stream_filter_source'] = 'pdf_default_identity';
        }

        if (!is_string($metadata['string_filter'] ?? null) || $metadata['string_filter'] === '') {
            $metadata['string_filter'] = 'Identity';
            $metadata['string_filter_defaulted'] = true;
            $metadata['string_filter_source'] = 'pdf_default_identity';
        }

        if (!is_string($metadata['embedded_file_filter'] ?? null) || $metadata['embedded_file_filter'] === '') {
            $metadata['embedded_file_filter'] = $metadata['stream_filter'];
            $metadata['embedded_file_filter_defaulted_from_stream_filter'] = true;
            $metadata['embedded_file_filter_source'] = 'pdf_default_stream_filter';
        }
    }

    /**
     * /EncryptMetadata is optional and defaults to true. A single valid
     * boolean false is the only state that can preserve root XMP without
     * decryption; malformed or duplicate declarations stay fail-closed.
     *
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function encryptMetadataDeclarationReview(string $dictionary, array $objects): array
    {
        $values = $this->dictionaryTopLevelRawValues($dictionary, 'EncryptMetadata');
        if ($values === []) {
            return [
                'source' => 'encrypt_metadata_declaration_review',
                'pdf_name' => 'EncryptMetadata',
                'declared_entry_count' => 0,
                'boolean_entry_count' => 0,
                'duplicate_entries' => false,
                'ambiguous' => false,
                'explicit' => false,
                'defaulted' => true,
                'defaulted_fail_closed' => false,
                'trusted' => true,
                'effective_value' => true,
                'status' => 'encrypt_metadata_default_true',
                'entry_statuses' => [],
                'boolean_values' => [],
                'entries' => [],
                'review_only' => true,
            ];
        }

        $entries = [];
        foreach ($values as $index => $value) {
            $resolved = $this->resolvePdfValue($value, $objects);
            $valueForReview = trim($resolved ?? $value);
            $operandShape = $this->encryptMetadataOperandShape($valueForReview);
            $booleanValue = match ($valueForReview) {
                'true' => true,
                'false' => false,
                default => null,
            };

            $entries[] = [
                'source' => 'encrypt_metadata_entry_review',
                'index' => $index,
                'pdf_name' => 'EncryptMetadata',
                'present' => true,
                'resolved' => $resolved !== null,
                'operand_shape' => $operandShape,
                'boolean' => $booleanValue !== null,
                'value' => $booleanValue,
                'status' => $booleanValue !== null
                    ? 'well_formed_encrypt_metadata_boolean'
                    : $this->encryptMetadataOperandStatus($value, $operandShape, $resolved !== null),
                'review_only' => true,
            ];
        }

        $booleanEntries = array_values(array_filter(
            $entries,
            static fn (array $entry): bool => ($entry['boolean'] ?? false) === true
        ));
        $malformedEntries = array_values(array_filter(
            $entries,
            static fn (array $entry): bool => ($entry['status'] ?? null) !== 'well_formed_encrypt_metadata_boolean'
        ));
        $duplicate = count($values) > 1;
        $trusted = !$duplicate && count($booleanEntries) === 1 && $malformedEntries === [];
        $effectiveValue = $trusted ? (bool) $booleanEntries[0]['value'] : true;
        $status = $trusted
            ? 'well_formed_encrypt_metadata_boolean'
            : ($duplicate ? 'duplicate_encrypt_metadata_entries_review' : 'malformed_encrypt_metadata_declaration_review');

        return [
            'source' => 'encrypt_metadata_declaration_review',
            'pdf_name' => 'EncryptMetadata',
            'declared_entry_count' => count($values),
            'boolean_entry_count' => count($booleanEntries),
            'duplicate_entries' => $duplicate,
            'ambiguous' => $duplicate || $malformedEntries !== [],
            'explicit' => true,
            'defaulted' => false,
            'defaulted_fail_closed' => !$trusted,
            'trusted' => $trusted,
            'effective_value' => $effectiveValue,
            'status' => $status,
            'entry_statuses' => $this->uniqueStrings(array_values(array_filter(
                array_map(
                    static fn (array $entry): mixed => $entry['status'] ?? null,
                    $entries
                ),
                static fn (mixed $status): bool => is_string($status)
            ))),
            'boolean_values' => array_values(array_map(
                static fn (array $entry): bool => (bool) $entry['value'],
                $booleanEntries
            )),
            'entries' => $entries,
            'review_only' => true,
            'decryption_performed' => false,
        ];
    }

    private function encryptMetadataOperandShape(string $value): string
    {
        $trimmed = $this->trimPdfWhitespaceAndComments($value);
        if ($trimmed === '') {
            return 'empty';
        }
        if (str_starts_with($trimmed, '[')) {
            return 'array';
        }
        if (str_starts_with($trimmed, '<<')) {
            return 'dictionary';
        }
        if (str_starts_with($trimmed, '(')) {
            return 'literal_string';
        }
        if (str_starts_with($trimmed, '<')) {
            return 'hex_string';
        }
        if (str_starts_with($trimmed, '/')) {
            return 'name';
        }
        if ($this->objectReferenceFromValue($trimmed) !== null) {
            return 'indirect_reference';
        }

        return 'token';
    }

    private function encryptMetadataOperandStatus(string $rawValue, string $operandShape, bool $resolved): string
    {
        if (!$resolved && $this->objectReferenceFromValue($rawValue) !== null) {
            return 'encrypt_metadata_unresolved_reference';
        }
        if (in_array($operandShape, ['array', 'dictionary'], true)) {
            return 'encrypt_metadata_composite_operand_review';
        }

        return 'encrypt_metadata_non_boolean_review';
    }

    /**
     * /CF is the crypt-filter dictionary. Duplicate or non-dictionary /CF
     * declarations are ambiguous for import preflight, even if the last parsed
     * value names identity filters.
     *
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function cryptFilterDictionaryDeclarationReview(string $dictionary, array $objects): array
    {
        $values = $this->dictionaryTopLevelRawValues($dictionary, 'CF');
        if ($values === []) {
            return [];
        }

        $entries = [];
        $declaredFilterNames = [];
        foreach ($values as $index => $value) {
            $resolved = $this->resolvePdfValue($value, $objects);
            $valueForReview = trim($resolved ?? $value);
            $operandShape = $this->encryptMetadataOperandShape($valueForReview);
            $filterDictionary = $this->resolveDictionaryFromValue($value, $objects);
            $filterNames = [];
            if ($filterDictionary !== null) {
                $filterNames = array_keys($this->dictionaryTopLevelEntries($filterDictionary['body']));
                foreach ($filterNames as $name) {
                    if (!in_array($name, $declaredFilterNames, true)) {
                        $declaredFilterNames[] = $name;
                    }
                }
            }

            $entries[] = [
                'source' => 'crypt_filter_dictionary_entry_review',
                'index' => $index,
                'pdf_name' => 'CF',
                'present' => true,
                'resolved' => $resolved !== null,
                'operand_shape' => $operandShape,
                'dictionary_resolved' => $filterDictionary !== null,
                'filter_names' => $filterNames,
                'status' => $filterDictionary !== null
                    ? 'crypt_filter_dictionary_entry_resolved'
                    : $this->cryptFilterDictionaryOperandStatus($value, $operandShape, $resolved !== null),
                'review_only' => true,
            ];
        }

        $resolvedEntries = array_values(array_filter(
            $entries,
            static fn (array $entry): bool => ($entry['dictionary_resolved'] ?? false) === true
        ));
        $malformedEntries = array_values(array_filter(
            $entries,
            static fn (array $entry): bool => ($entry['dictionary_resolved'] ?? false) !== true
        ));
        $duplicate = count($values) > 1;
        $selectedIndex = count($entries) - 1;
        $selectedEntry = $selectedIndex >= 0 ? $entries[$selectedIndex] : [];
        $ambiguous = $duplicate || $malformedEntries !== [];
        $status = $duplicate
            ? 'duplicate_crypt_filter_dictionary_entries_review'
            : ($malformedEntries !== [] ? 'malformed_crypt_filter_dictionary_entry_review' : 'well_formed_crypt_filter_dictionary');

        return [
            'source' => 'encryption_crypt_filter_dictionary_declaration_review',
            'pdf_name' => 'CF',
            'declared_entry_count' => count($values),
            'resolved_dictionary_entry_count' => count($resolvedEntries),
            'duplicate_entries' => $duplicate,
            'malformed_entries' => $malformedEntries !== [],
            'malformed_entry_count' => count($malformedEntries),
            'ambiguous' => $ambiguous,
            'fail_closed' => $ambiguous,
            'status' => $status,
            'entry_statuses' => $this->uniqueStrings(array_values(array_filter(
                array_map(
                    static fn (array $entry): mixed => $entry['status'] ?? null,
                    $entries
                ),
                static fn (mixed $status): bool => is_string($status)
            ))),
            'declared_filter_names' => $declaredFilterNames,
            'selected_entry_index' => $selectedIndex >= 0 ? $selectedIndex : null,
            'selected_filter_names' => is_array($selectedEntry['filter_names'] ?? null)
                ? $selectedEntry['filter_names']
                : [],
            'entries' => $entries,
            'review_only' => true,
            'decryption_performed' => false,
            'executes_permission_enforcement' => false,
        ];
    }

    private function cryptFilterDictionaryOperandStatus(string $rawValue, string $operandShape, bool $resolved): string
    {
        if (!$resolved && $this->objectReferenceFromValue($rawValue) !== null) {
            return 'crypt_filter_dictionary_unresolved_reference';
        }
        if ($operandShape === 'dictionary') {
            return 'crypt_filter_dictionary_malformed_dictionary_review';
        }

        return 'crypt_filter_dictionary_non_dictionary_operand_review';
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function cryptFilterRoleDeclarationReview(string $dictionary, array $objects, array $metadata): array
    {
        $definitions = [
            [
                'role' => 'document_streams',
                'pdf_name' => 'StmF',
                'metadata_key' => 'stream_filter',
                'defaulted_key' => 'stream_filter_defaulted',
                'source_key' => 'stream_filter_source',
            ],
            [
                'role' => 'document_strings',
                'pdf_name' => 'StrF',
                'metadata_key' => 'string_filter',
                'defaulted_key' => 'string_filter_defaulted',
                'source_key' => 'string_filter_source',
            ],
            [
                'role' => 'embedded_file_streams',
                'pdf_name' => 'EFF',
                'metadata_key' => 'embedded_file_filter',
                'defaulted_key' => 'embedded_file_filter_defaulted_from_stream_filter',
                'source_key' => 'embedded_file_filter_source',
            ],
        ];

        $roles = [];
        foreach ($definitions as $definition) {
            $rawValues = $this->dictionaryTopLevelRawValues($dictionary, $definition['pdf_name']);
            $entries = [];
            foreach ($rawValues as $index => $rawValue) {
                $entries[] = $this->cryptFilterRoleDeclarationEntryReview(
                    $rawValue,
                    $objects,
                    $definition['role'],
                    $definition['pdf_name'],
                    $definition['metadata_key'],
                    $index
                );
            }

            $entryStatuses = $this->uniqueStrings(array_values(array_filter(
                array_map(
                    static fn (array $entry): mixed => $entry['status'] ?? null,
                    $entries
                ),
                static fn (mixed $status): bool => is_string($status)
            )));
            $entryFilterNames = $this->uniqueStrings(array_values(array_filter(
                array_map(
                    static fn (array $entry): mixed => $entry['filter_name'] ?? null,
                    $entries
                ),
                static fn (mixed $filterName): bool => is_string($filterName) && $filterName !== ''
            )));
            $declaredEntryCount = count($rawValues);
            $duplicateEntries = $declaredEntryCount > 1;
            $malformedEntries = array_values(array_filter(
                $entries,
                static fn (array $entry): bool => ($entry['status'] ?? null) !== 'crypt_filter_role_entry_name'
            ));
            $defaulted = $declaredEntryCount === 0 && ($metadata[$definition['defaulted_key']] ?? false) === true;
            $selectedFilterName = is_string($metadata[$definition['metadata_key']] ?? null)
                ? $metadata[$definition['metadata_key']]
                : null;
            $sourcePolicy = is_string($metadata[$definition['source_key']] ?? null)
                ? $metadata[$definition['source_key']]
                : ($declaredEntryCount > 0 ? 'pdf_dictionary' : 'not_declared');

            $status = 'single_crypt_filter_role_entry';
            if ($duplicateEntries) {
                $status = 'duplicate_crypt_filter_role_entries_review';
            } elseif ($malformedEntries !== []) {
                $status = 'malformed_crypt_filter_role_entry_review';
            } elseif ($defaulted) {
                $status = 'defaulted_crypt_filter_role';
            } elseif ($declaredEntryCount === 0) {
                $status = 'missing_crypt_filter_role_review';
            }

            $roles[] = [
                'source' => 'crypt_filter_role_declaration_row',
                'role' => $definition['role'],
                'pdf_name' => $definition['pdf_name'],
                'metadata_key' => $definition['metadata_key'],
                'declared' => $declaredEntryCount > 0,
                'declared_entry_count' => $declaredEntryCount,
                'duplicate_entries' => $duplicateEntries,
                'malformed_entry_count' => count($malformedEntries),
                'defaulted' => $defaulted,
                'source_policy' => $sourcePolicy,
                'selected_filter_name' => $selectedFilterName,
                'entry_filter_names' => $entryFilterNames,
                'entry_statuses' => $entryStatuses,
                'status' => $status,
                'fail_closed' => $duplicateEntries || $malformedEntries !== [],
                'entries' => $entries,
                'review_only' => true,
                'executes_decryption' => false,
                'executes_permission_enforcement' => false,
            ];
        }

        $duplicateRoleNames = $this->cryptFilterDeclarationRoleNamesByStatus(
            $roles,
            'duplicate_crypt_filter_role_entries_review',
            'role'
        );
        $duplicatePdfNames = $this->cryptFilterDeclarationRoleNamesByStatus(
            $roles,
            'duplicate_crypt_filter_role_entries_review',
            'pdf_name'
        );
        $malformedRoleNames = $this->cryptFilterDeclarationRoleNamesByStatus(
            $roles,
            'malformed_crypt_filter_role_entry_review',
            'role'
        );
        $malformedPdfNames = $this->cryptFilterDeclarationRoleNamesByStatus(
            $roles,
            'malformed_crypt_filter_role_entry_review',
            'pdf_name'
        );
        if ($duplicateRoleNames === [] && $malformedRoleNames === []) {
            return [];
        }

        return [
            'source' => 'encryption_crypt_filter_role_declaration_review',
            'role_count' => count($roles),
            'duplicate_role_names' => $duplicateRoleNames,
            'duplicate_pdf_names' => $duplicatePdfNames,
            'malformed_role_names' => $malformedRoleNames,
            'malformed_pdf_names' => $malformedPdfNames,
            'role_statuses' => $this->uniqueStrings(array_values(array_filter(
                array_map(
                    static fn (array $role): mixed => $role['status'] ?? null,
                    $roles
                ),
                static fn (mixed $status): bool => is_string($status)
            ))),
            'fail_closed_role_names' => $this->cryptFilterDeclarationFailClosedNames($roles, 'role'),
            'fail_closed_pdf_names' => $this->cryptFilterDeclarationFailClosedNames($roles, 'pdf_name'),
            'roles' => $roles,
            'review_only' => true,
            'executes_decryption' => false,
            'executes_permission_enforcement' => false,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function cryptFilterRoleDeclarationEntryReview(
        string $rawValue,
        array $objects,
        string $role,
        string $pdfName,
        string $metadataKey,
        int $index
    ): array {
        $resolved = $this->resolvePdfValue($rawValue, $objects);
        $value = $this->trimPdfWhitespaceAndComments($resolved ?? $rawValue);
        $entry = [
            'source' => 'crypt_filter_role_declaration_entry_review',
            'role' => $role,
            'pdf_name' => $pdfName,
            'metadata_key' => $metadataKey,
            'index' => $index,
            'resolved' => $resolved !== null,
            'operand_shape' => $this->cryptFilterRoleOperandShape($value),
            'review_only' => true,
        ];

        if ($value !== '' && $value[0] === '/' && preg_match('/^\/([^\s\[\]()<>{}\/%]+)/', $value, $match) === 1) {
            return $entry + [
                'filter_name' => $this->decodePdfName($match[1]),
                'status' => 'crypt_filter_role_entry_name',
            ];
        }

        return $entry + [
            'status' => $resolved === null && $this->objectReferenceFromValue($rawValue) !== null
                ? 'crypt_filter_role_entry_unresolved_reference'
                : 'crypt_filter_role_entry_non_name_review',
        ];
    }

    private function cryptFilterRoleOperandShape(string $value): string
    {
        $trimmed = $this->trimPdfWhitespaceAndComments($value);
        if ($trimmed === '') {
            return 'empty';
        }
        if (str_starts_with($trimmed, '[')) {
            return 'array';
        }
        if (str_starts_with($trimmed, '<<')) {
            return 'dictionary';
        }
        if (str_starts_with($trimmed, '(')) {
            return 'literal_string';
        }
        if (str_starts_with($trimmed, '<')) {
            return 'hex_string';
        }
        if (str_starts_with($trimmed, '/')) {
            return 'name';
        }
        if ($this->objectReferenceFromValue($trimmed) !== null) {
            return 'indirect_reference';
        }

        return 'token';
    }

    /**
     * @param list<array<string, mixed>> $roles
     * @return list<string>
     */
    private function cryptFilterDeclarationRoleNamesByStatus(array $roles, string $status, string $key): array
    {
        $names = [];
        foreach ($roles as $role) {
            if (($role['status'] ?? null) !== $status || !is_string($role[$key] ?? null)) {
                continue;
            }
            if (!in_array($role[$key], $names, true)) {
                $names[] = $role[$key];
            }
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $roles
     * @return list<string>
     */
    private function cryptFilterDeclarationFailClosedNames(array $roles, string $key): array
    {
        $names = [];
        foreach ($roles as $role) {
            if (($role['fail_closed'] ?? false) !== true || !is_string($role[$key] ?? null)) {
                continue;
            }
            if (!in_array($role[$key], $names, true)) {
                $names[] = $role[$key];
            }
        }

        return $names;
    }

    /**
     * @param array<int, string> $objects
     * @return array{body: string, object: int|null, generation?: int, source: string, malformed_encrypt_dictionary?: bool, encrypt_operand_shape?: string, encrypt_operand_status?: string}|null
     */
    private function encryptionDictionaryEntry(string $pdfBytes, array $objects): ?array
    {
        $chainEntry = $this->trailerEncryptionDictionaryEntryFromStartxrefChain($pdfBytes, $objects);
        if ($chainEntry['parsed']) {
            return $chainEntry['entry'];
        }

        $trailerEntry = $this->trailerDictionaryEntry($pdfBytes);
        if ($trailerEntry !== null) {
            $trailer = $trailerEntry['body'];
            $values = $this->dictionaryTopLevelRawValues($trailer, 'Encrypt');
            $source = $trailerEntry['source'] === 'xref_stream_trailer'
                ? 'xref_stream_trailer_encrypt'
                : 'trailer_encrypt';
            if ($values !== []) {
                if (count($values) > 1) {
                    return $this->duplicateEncryptionDictionaryEntry($values, $objects, $source);
                }

                $value = $values[0];
                if (trim($value) === 'null') {
                    return null;
                }

                $entry = $this->resolvedEncryptionDictionary($value, $objects, $source)
                    ?? $this->malformedEncryptionDictionaryEntry($value, $source);
                if ($entry !== null) {
                    return $entry;
                }
            }
        }

        foreach ($objects as $objectNumber => $body) {
            if (preg_match('/\/Type\s*\/XRef\b/s', $body) !== 1) {
                continue;
            }

            $values = $this->dictionaryTopLevelRawValues($body, 'Encrypt');
            $entry = $values === []
                ? null
                : (
                    count($values) > 1
                        ? $this->duplicateEncryptionDictionaryEntry($values, $objects, 'xref_stream_encrypt')
                        : (
                            trim($values[0]) === 'null'
                                ? null
                                : (
                                    $this->resolvedEncryptionDictionary($values[0], $objects, 'xref_stream_encrypt')
                                    ?? $this->malformedEncryptionDictionaryEntry($values[0], 'xref_stream_encrypt')
                                )
                        )
                );
            if ($entry !== null) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @return array{parsed: bool, entry: array{body: string, object: int|null, generation?: int, source: string, malformed_encrypt_dictionary?: bool, encrypt_operand_shape?: string, encrypt_operand_status?: string}|null}
     */
    private function trailerEncryptionDictionaryEntryFromStartxrefChain(string $pdfBytes, array $objects): array
    {
        $definitions = $this->directObjectDefinitions($pdfBytes);
        $offset = $this->latestStartxrefOffset($pdfBytes, $definitions === [] ? null : $definitions);
        if ($offset === null) {
            return ['parsed' => false, 'entry' => null];
        }

        return $this->trailerEncryptionDictionaryEntryAtOffsetChain(
            $pdfBytes,
            $offset,
            $objects,
            $definitions === [] ? null : $definitions
        );
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array{bodyStart?: int, bodyEnd?: int}>>|null $definitions
     * @param array<int, true> $seenOffsets
     * @return array{parsed: bool, entry: array{body: string, object: int|null, generation?: int, source: string, malformed_encrypt_dictionary?: bool, encrypt_operand_shape?: string, encrypt_operand_status?: string}|null}
     */
    private function trailerEncryptionDictionaryEntryAtOffsetChain(
        string $pdfBytes,
        int $offset,
        array $objects,
        ?array $definitions = null,
        array $seenOffsets = [],
        int $depth = 0
    ): array {
        if ($offset < 0 || isset($seenOffsets[$offset])) {
            return ['parsed' => false, 'entry' => null];
        }
        $seenOffsets[$offset] = true;

        $trailer = $this->trailerDictionaryBodyAtOffset($pdfBytes, $offset, $definitions);
        if ($trailer === null) {
            return ['parsed' => false, 'entry' => null];
        }

        $values = $this->dictionaryTopLevelRawValues($trailer, 'Encrypt');
        if ($values !== []) {
            if (count($values) > 1) {
                return [
                    'parsed' => true,
                    'entry' => $this->duplicateEncryptionDictionaryEntry(
                        $values,
                        $objects,
                        $this->trailerEncryptionSourceAtOffset($pdfBytes, $offset, $depth)
                    ),
                ];
            }

            $value = $values[0];
            if (trim($value) === 'null') {
                return ['parsed' => true, 'entry' => null];
            }

            $source = $this->trailerEncryptionSourceAtOffset($pdfBytes, $offset, $depth);
            $entry = $this->resolvedEncryptionDictionary($value, $objects, $source);

            return [
                'parsed' => true,
                'entry' => $entry ?? $this->malformedEncryptionDictionaryEntry($value, $source),
            ];
        }

        $previousOffset = $this->dictionaryIntegerValue($trailer, 'Prev');
        if ($previousOffset !== null && $previousOffset >= 0) {
            $previous = $this->trailerEncryptionDictionaryEntryAtOffsetChain(
                $pdfBytes,
                $previousOffset,
                $objects,
                $definitions,
                $seenOffsets,
                $depth + 1
            );
            if ($previous['parsed']) {
                return $previous;
            }
        }

        return ['parsed' => true, 'entry' => null];
    }

    private function trailerEncryptionSourceAtOffset(string $pdfBytes, int $offset, int $depth): string
    {
        $trailerSource = $this->trailerDictionarySourceAtOffset($pdfBytes, $offset);
        $source = $trailerSource === 'xref_stream_trailer'
            ? 'xref_stream_trailer_encrypt'
            : 'trailer_encrypt';

        return $depth > 0 ? 'prev_' . $source : $source;
    }

    /**
     * @param array<int, string> $objects
     * @return array{body: string, object: int|null, generation?: int, source: string}|null
     */
    private function resolvedEncryptionDictionary(string $value, array $objects, string $source): ?array
    {
        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === 'null') {
            return null;
        }

        if (preg_match('/^(\d+)\s+(\d+)\s+R\b/s', $trimmed, $match) === 1) {
            $objectNumber = (int) $match[1];
            $generation = (int) $match[2];
            $objectBody = $this->objectBodyForReference($objects, $objectNumber, $generation);
            if ($objectBody === null) {
                return null;
            }

            $dictionary = $this->dictionaryObjectBody($objectBody);
            return $dictionary === null ? null : [
                'body' => $dictionary,
                'object' => $objectNumber,
                'generation' => $generation,
                'source' => $source,
            ];
        }

        if (str_starts_with($trimmed, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($trimmed, 0);
            return $dictionary === null ? null : [
                'body' => $dictionary,
                'object' => null,
                'source' => $source,
            ];
        }

        $dictionary = $this->dictionaryObjectBody($trimmed);
        return $dictionary === null ? null : [
            'body' => $dictionary,
            'object' => null,
            'source' => $source,
        ];
    }

    /**
     * A non-null /Encrypt in the selected trailer chain means the import must
     * fail closed even when the referenced dictionary cannot be resolved.
     *
     * @return array{body: string, object: int|null, generation?: int, source: string, malformed_encrypt_dictionary: bool, encrypt_operand_shape: string, encrypt_operand_status: string}
     */
    private function malformedEncryptionDictionaryEntry(string $value, string $source): array
    {
        $trimmed = $this->trimPdfWhitespaceAndComments($value);
        $reference = $this->objectReferenceFromValue($trimmed);
        $shape = $this->encryptionDictionaryOperandShape($trimmed);
        $entry = [
            'body' => '',
            'object' => null,
            'source' => $source,
            'malformed_encrypt_dictionary' => true,
            'encrypt_operand_shape' => $shape,
            'encrypt_operand_status' => $reference !== null
                ? 'encrypt_dictionary_unresolved_reference'
                : (
                    $shape === 'dictionary'
                        ? 'encrypt_dictionary_malformed_direct_dictionary'
                        : 'encrypt_dictionary_non_dictionary_operand'
                ),
        ];

        if ($reference !== null) {
            $entry['object'] = $reference['objectNumber'];
            $entry['generation'] = $reference['generation'];
        }

        return $entry;
    }

    /**
     * @param list<string> $values
     * @param array<int, string> $objects
     * @return array{body: string, object: int|null, source: string, malformed_encrypt_dictionary: true, encrypt_dictionary_resolved: false, duplicate_encrypt_dictionary_entries: true, encrypt_dictionary_declared_entry_count: int, encrypt_dictionary_resolved_entry_count: int, encrypt_dictionary_entry_statuses: list<string>, encrypt_dictionary_entry_shapes: list<string>, encrypt_operand_shape: string, encrypt_operand_status: string}
     */
    private function duplicateEncryptionDictionaryEntry(array $values, array $objects, string $source): array
    {
        $statuses = [];
        $shapes = [];
        $resolvedCount = 0;

        foreach ($values as $value) {
            $trimmed = $this->trimPdfWhitespaceAndComments($value);
            $shape = $this->encryptionDictionaryOperandShape($trimmed);
            if (!in_array($shape, $shapes, true)) {
                $shapes[] = $shape;
            }

            if ($trimmed === 'null') {
                $status = 'encrypt_dictionary_explicit_null';
            } elseif ($this->resolvedEncryptionDictionary($value, $objects, $source) !== null) {
                $status = 'encrypt_dictionary_entry_resolved';
                $resolvedCount++;
            } else {
                $status = $this->malformedEncryptionDictionaryEntry($value, $source)['encrypt_operand_status'];
            }

            if (!in_array($status, $statuses, true)) {
                $statuses[] = $status;
            }
        }

        return [
            'body' => '',
            'object' => null,
            'source' => $source,
            'malformed_encrypt_dictionary' => true,
            'encrypt_dictionary_resolved' => false,
            'duplicate_encrypt_dictionary_entries' => true,
            'encrypt_dictionary_declared_entry_count' => count($values),
            'encrypt_dictionary_resolved_entry_count' => $resolvedCount,
            'encrypt_dictionary_entry_statuses' => $statuses,
            'encrypt_dictionary_entry_shapes' => $shapes,
            'encrypt_operand_shape' => 'duplicate_entries',
            'encrypt_operand_status' => 'duplicate_encrypt_dictionary_entries_review',
        ];
    }

    private function encryptionDictionaryOperandShape(string $value): string
    {
        $trimmed = $this->trimPdfWhitespaceAndComments($value);
        if ($trimmed === '') {
            return 'empty';
        }
        if ($this->objectReferenceFromValue($trimmed) !== null) {
            return 'indirect_reference';
        }
        if (str_starts_with($trimmed, '<<')) {
            return 'dictionary';
        }
        if (str_starts_with($trimmed, '[')) {
            return 'array';
        }
        if (str_starts_with($trimmed, '(')) {
            return 'literal_string';
        }
        if (str_starts_with($trimmed, '<')) {
            return 'hex_string';
        }
        if (str_starts_with($trimmed, '/')) {
            return 'name';
        }

        return 'token';
    }

    private function encryptionAlgorithmLabel(int $version): string
    {
        return match ($version) {
            0 => 'none',
            1 => 'rc4_40_bit',
            2 => 'rc4_variable_length',
            3 => 'unpublished_security_handler',
            4 => 'security_handler_crypt_filters',
            5 => 'aes_256',
            default => 'unknown',
        };
    }

    private function standardRevisionLabel(int $revision): string
    {
        return match ($revision) {
            2 => 'standard_handler_revision_2',
            3 => 'standard_handler_revision_3',
            4 => 'standard_handler_revision_4',
            5 => 'standard_handler_revision_5',
            6 => 'standard_handler_revision_6',
            default => 'standard_handler_revision_unknown',
        };
    }

    /**
     * The Standard handler's version, revision, and top-level key length gate
     * whether the permission word can be interpreted as reliable review data.
     *
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function standardSecurityHandlerParameterReview(array $metadata): array
    {
        if (($metadata['filter'] ?? null) !== 'Standard') {
            return [];
        }

        $version = is_int($metadata['version'] ?? null) ? $metadata['version'] : null;
        $revision = is_int($metadata['revision'] ?? null) ? $metadata['revision'] : null;
        $keyLengthBits = is_int($metadata['key_length_bits'] ?? null) ? $metadata['key_length_bits'] : null;
        $supportedVersions = [1, 2, 4, 5];
        $supportedRevisions = [2, 3, 4, 5, 6];
        $versionSupported = $version === null ? null : in_array($version, $supportedVersions, true);
        $revisionSupported = $revision === null ? null : in_array($revision, $supportedRevisions, true);
        $compatible = $versionSupported === true && $revisionSupported === true
            ? $this->standardSecurityHandlerVersionRevisionCompatible($version, $revision)
            : null;
        $keyLength = $this->standardSecurityHandlerKeyLengthReview($version, $keyLengthBits);
        $permissionWordReview = is_array($metadata['standard_permission_word_review'] ?? null)
            ? $metadata['standard_permission_word_review']
            : [];
        $permissionWordDeclaredEntryCount = (int) ($permissionWordReview['declared_entry_count'] ?? 0);
        $permissionWordPresent = $permissionWordDeclaredEntryCount > 0
            || is_array($metadata['standard_permissions'] ?? null);
        $parameterDeclarationReview = is_array($metadata['standard_security_handler_parameter_declaration_review'] ?? null)
            ? $metadata['standard_security_handler_parameter_declaration_review']
            : [];
        $duplicateParameterNames = array_values(array_filter(
            $parameterDeclarationReview['duplicate_parameter_names'] ?? [],
            static fn (mixed $name): bool => is_string($name)
        ));

        $violations = [];
        if ($version === null) {
            $violations[] = 'missing_standard_security_handler_version';
        } elseif ($versionSupported !== true) {
            $violations[] = 'unsupported_standard_security_handler_version';
        }
        if ($revision === null) {
            $violations[] = 'missing_standard_security_handler_revision';
        } elseif ($revisionSupported !== true) {
            $violations[] = 'unsupported_standard_security_handler_revision';
        }
        if ($compatible === false) {
            $violations[] = 'standard_security_handler_version_revision_mismatch';
        }
        if (($keyLength['status'] ?? null) === 'missing_standard_security_handler_key_length_review') {
            $violations[] = 'missing_standard_security_handler_key_length';
        } elseif (($keyLength['valid'] ?? null) === false) {
            $violations[] = 'invalid_standard_security_handler_key_length';
        }
        if (!$permissionWordPresent) {
            $violations[] = 'missing_standard_permission_word';
        }
        if ($duplicateParameterNames !== []) {
            $violations[] = 'duplicate_standard_security_handler_parameter_entries';
        }

        return [
            'source' => 'standard_security_handler_parameter_review',
            'handler' => 'Standard',
            'version' => $version,
            'revision' => $revision,
            'revision_label' => $metadata['revision_label'] ?? null,
            'key_length_bits' => $keyLengthBits,
            'version_present' => $version !== null,
            'revision_present' => $revision !== null,
            'key_length_present' => array_key_exists('key_length_bits', $metadata),
            'permission_word_present' => $permissionWordPresent,
            'permission_word_declared_entry_count' => $permissionWordDeclaredEntryCount,
            'version_supported' => $versionSupported,
            'revision_supported' => $revisionSupported,
            'version_revision_compatible' => $compatible,
            'key_length_valid' => $keyLength['valid'],
            'key_length_status' => $keyLength['status'],
            'minimum_key_length_bits' => $keyLength['minimum_key_length_bits'],
            'maximum_key_length_bits' => $keyLength['maximum_key_length_bits'],
            'parameter_declaration_review' => $parameterDeclarationReview,
            'duplicate_parameter_names' => $duplicateParameterNames,
            'duplicate_parameter_count' => count($duplicateParameterNames),
            'parameters_well_formed' => $violations === [],
            'status' => $violations === []
                ? 'standard_security_handler_parameters_well_formed'
                : 'malformed_standard_security_handler_parameters_review',
            'violations' => $violations,
            'review_only' => true,
            'password_validation_performed' => false,
            'permissions_authenticated' => false,
            'executes_decryption' => false,
            'executes_permission_enforcement' => false,
        ];
    }

    /**
     * Security-handler parameters are dictionary keys, so duplicate
     * declarations are ambiguous even when the last value is syntactically
     * valid. Keep them as review metadata and let preflight fail closed.
     *
     * @return array<string, mixed>
     */
    private function standardSecurityHandlerParameterDeclarationReview(string $dictionary): array
    {
        $rows = [];
        $entryCounts = [];
        $duplicateNames = [];

        foreach ([
            'Filter' => 'filter',
            'V' => 'version',
            'R' => 'revision',
            'Length' => 'key_length_bits',
        ] as $pdfName => $metadataKey) {
            $values = $this->dictionaryTopLevelRawValues($dictionary, $pdfName);
            $entryCount = count($values);
            if ($entryCount === 0) {
                continue;
            }

            $entryCounts[$pdfName] = $entryCount;
            $entries = [];
            foreach ($values as $index => $value) {
                $entries[] = [
                    'source' => 'standard_security_handler_parameter_entry_review',
                    'index' => $index,
                    'pdf_name' => $pdfName,
                    'metadata_key' => $metadataKey,
                    'operand_shape' => $this->standardSecurityHandlerParameterOperandShape($value),
                    'review_only' => true,
                ];
            }

            $duplicate = $entryCount > 1;
            if ($duplicate) {
                $duplicateNames[] = $pdfName;
            }

            $rows[] = [
                'source' => 'standard_security_handler_parameter_declaration_row',
                'pdf_name' => $pdfName,
                'metadata_key' => $metadataKey,
                'declared_entry_count' => $entryCount,
                'duplicate_entries' => $duplicate,
                'selected_entry_index' => $entryCount - 1,
                'entry_operand_shapes' => $this->uniqueStrings(array_values(array_filter(
                    array_map(
                        static fn (array $entry): mixed => $entry['operand_shape'] ?? null,
                        $entries
                    ),
                    static fn (mixed $shape): bool => is_string($shape)
                ))),
                'entries' => $entries,
                'review_only' => true,
                'executes_decryption' => false,
                'executes_permission_enforcement' => false,
            ];
        }

        if ($duplicateNames === []) {
            return [];
        }

        return [
            'source' => 'standard_security_handler_parameter_declaration_review',
            'duplicate_parameter_names' => $duplicateNames,
            'duplicate_parameter_count' => count($duplicateNames),
            'parameter_entry_counts' => $entryCounts,
            'status' => 'duplicate_standard_security_handler_parameter_entries_review',
            'fail_closed' => true,
            'rows' => $rows,
            'review_only' => true,
            'executes_decryption' => false,
            'executes_permission_enforcement' => false,
        ];
    }

    private function standardSecurityHandlerParameterOperandShape(string $value): string
    {
        $trimmed = $this->trimPdfWhitespaceAndComments($value);
        if ($trimmed === '') {
            return 'empty';
        }
        if ($this->objectReferenceFromValue($trimmed) !== null) {
            return 'indirect_reference';
        }
        if (str_starts_with($trimmed, '[')) {
            return 'array';
        }
        if (str_starts_with($trimmed, '<<')) {
            return 'dictionary';
        }
        if (str_starts_with($trimmed, '(')) {
            return 'literal_string';
        }
        if (str_starts_with($trimmed, '<')) {
            return 'hex_string';
        }
        if (str_starts_with($trimmed, '/')) {
            return 'name';
        }

        return 'token';
    }

    private function standardSecurityHandlerVersionRevisionCompatible(int $version, int $revision): bool
    {
        return match ($revision) {
            2 => $version === 1,
            3 => $version === 2,
            4 => $version === 4,
            5, 6 => $version === 5,
            default => false,
        };
    }

    /**
     * @return array{valid: bool|null, status: string, minimum_key_length_bits: int|null, maximum_key_length_bits: int|null}
     */
    private function standardSecurityHandlerKeyLengthReview(?int $version, ?int $keyLengthBits): array
    {
        $range = match ($version) {
            1 => ['minimum' => 40, 'maximum' => 40],
            2, 4 => ['minimum' => 40, 'maximum' => 128],
            5 => ['minimum' => 256, 'maximum' => 256],
            default => null,
        };

        if ($range === null) {
            return [
                'valid' => null,
                'status' => 'standard_security_handler_key_length_not_reviewed',
                'minimum_key_length_bits' => null,
                'maximum_key_length_bits' => null,
            ];
        }

        if ($keyLengthBits === null) {
            if ($version === 5) {
                return [
                    'valid' => false,
                    'status' => 'missing_standard_security_handler_key_length_review',
                    'minimum_key_length_bits' => $range['minimum'],
                    'maximum_key_length_bits' => $range['maximum'],
                ];
            }

            return [
                'valid' => $version === 1 ? false : null,
                'status' => $version === 1
                    ? 'standard_security_handler_key_length_missing'
                    : 'standard_security_handler_key_length_default_or_unavailable_review',
                'minimum_key_length_bits' => $range['minimum'],
                'maximum_key_length_bits' => $range['maximum'],
            ];
        }

        $valid = $keyLengthBits >= $range['minimum']
            && $keyLengthBits <= $range['maximum']
            && $keyLengthBits % 8 === 0;

        return [
            'valid' => $valid,
            'status' => $valid
                ? 'standard_security_handler_key_length_supported'
                : 'invalid_standard_security_handler_key_length_review',
            'minimum_key_length_bits' => $range['minimum'],
            'maximum_key_length_bits' => $range['maximum'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function standardPermissionMetadata(int $declared, ?int $revision): array
    {
        $word = $this->standardPermissionWordValues($declared);
        $signed = $word['signed'];
        $unsigned = $word['unsigned'];
        $rangeValid = (bool) $word['range_valid'];
        $effectiveRevision = $revision ?? 6;
        $permissionBits = $rangeValid && is_int($unsigned)
            ? $this->standardPermissionBitReview($unsigned, $effectiveRevision)
            : [];
        $allowed = $this->standardPermissionNamesByStatus($permissionBits, 'allowed_by_permission_bit');
        $denied = $this->standardPermissionNamesByStatus($permissionBits, 'denied_by_permission_bit');

        $canPrint = in_array('print', $allowed, true);
        $highQuality = in_array('high_quality_print', $allowed, true);
        $reserved = $this->standardPermissionReservedBitsMetadata($unsigned ?? 0, $effectiveRevision, $rangeValid);

        return array_merge([
            'declared' => $declared,
            'declared_form' => $word['declared_form'],
            'normalized_from_unsigned_decimal' => $word['normalized_from_unsigned_decimal'],
            'permission_word_range_valid' => $rangeValid,
            'permission_word_range_status' => $word['range_status'],
            'word_range' => [
                'source' => 'standard_permission_word_range_review',
                'valid' => $rangeValid,
                'status' => $word['range_status'],
                'declared' => $declared,
                'signed_min' => -2147483648,
                'signed_max' => 2147483647,
                'unsigned_max' => 4294967295,
                'review_only' => true,
            ],
            'signed' => $signed,
            'unsigned' => $unsigned,
            'hex' => $rangeValid && is_int($unsigned) ? strtoupper(sprintf('%08X', $unsigned)) : null,
            'effective_revision' => $effectiveRevision,
            'allowed' => $allowed,
            'denied' => $denied,
            'applicable_permission_names' => $this->standardApplicablePermissionNames($permissionBits, true),
            'not_applicable_permission_names' => $this->standardApplicablePermissionNames($permissionBits, false),
            'permission_bit_review_count' => count($permissionBits),
            'permission_bit_statuses' => $this->standardPermissionBitStatuses($permissionBits),
            'permission_bits' => $permissionBits,
            'print_quality' => !$rangeValid ? null : (!$canPrint ? 'disallowed' : ($effectiveRevision >= 3 && !$highQuality ? 'low_resolution' : 'high_resolution')),
        ], $reserved);
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function standardPermissionWordDeclarationReview(string $dictionary, array $objects, ?int $revision): array
    {
        $values = $this->dictionaryTopLevelRawValues($dictionary, 'P');
        if ($values === []) {
            return [];
        }

        $entries = [];
        foreach ($values as $index => $value) {
            $resolved = $this->resolvePdfValue($value, $objects);
            $valueForReview = trim($resolved ?? $value);
            $operandShape = $this->standardPermissionWordOperandShape($valueForReview);
            $entry = [
                'source' => 'standard_permission_word_entry_review',
                'index' => $index,
                'pdf_name' => 'P',
                'present' => true,
                'resolved' => $resolved !== null,
                'operand_shape' => $operandShape,
                'integer' => false,
                'review_only' => true,
            ];

            if (preg_match('/^[+-]?\d+$/', $valueForReview) !== 1) {
                $entries[] = $entry + [
                    'status' => $this->standardPermissionWordOperandStatus($value, $operandShape, $resolved !== null),
                ];
                continue;
            }

            $metadata = $this->standardPermissionMetadata((int) $valueForReview, $revision);
            $permissionBitsByName = [];
            foreach ($metadata['permission_bits'] as $bit) {
                if (is_array($bit) && is_string($bit['name'] ?? null)) {
                    $permissionBitsByName[$bit['name']] = $bit;
                }
            }

            $entries[] = array_merge($entry, [
                'integer' => true,
                'declared' => $metadata['declared'],
                'declared_form' => $metadata['declared_form'],
                'permission_word_range_valid' => $metadata['permission_word_range_valid'],
                'permission_word_range_status' => $metadata['permission_word_range_status'],
                'word_range' => $metadata['word_range'],
                'signed' => $metadata['signed'],
                'unsigned' => $metadata['unsigned'],
                'hex' => $metadata['hex'],
                'permission_word_status' => $metadata['permission_word_status'],
                'reserved_bits_valid' => $metadata['reserved_bits_valid'],
                'allowed' => $metadata['allowed'],
                'denied' => $metadata['denied'],
                'permission_bit_statuses' => $metadata['permission_bit_statuses'],
                'permission_bits_by_name' => $permissionBitsByName,
                'status' => $metadata['permission_word_status'],
            ]);
        }

        $integerEntries = array_values(array_filter(
            $entries,
            static fn (array $entry): bool => ($entry['integer'] ?? false) === true
        ));
        $duplicate = count($values) > 1;
        $conflicts = $this->standardPermissionWordConflicts($integerEntries);
        $entryStatuses = $this->uniqueStrings(array_values(array_filter(
            array_map(
                static fn (array $entry): mixed => $entry['status'] ?? null,
                $entries
            ),
            static fn (mixed $status): bool => is_string($status)
        )));
        $malformedEntries = array_values(array_filter(
            $entries,
            static fn (array $entry): bool => ($entry['status'] ?? null) !== 'well_formed_standard_permissions'
        ));
        $ambiguous = $duplicate || $conflicts !== [] || $malformedEntries !== [];

        return [
            'source' => 'standard_permission_word_declaration_review',
            'pdf_name' => 'P',
            'declared_entry_count' => count($values),
            'integer_entry_count' => count($integerEntries),
            'duplicate_permission_entries' => $duplicate,
            'permission_word_ambiguous' => $ambiguous,
            'status' => $duplicate
                ? 'duplicate_standard_permission_entries_review'
                : ($ambiguous ? 'malformed_standard_permission_word_review' : 'well_formed_standard_permissions'),
            'entry_statuses' => $entryStatuses,
            'unsigned_values' => array_values(array_map(
                static fn (array $entry): int => (int) $entry['unsigned'],
                array_values(array_filter(
                    $integerEntries,
                    static fn (array $entry): bool => is_int($entry['unsigned'] ?? null)
                ))
            )),
            'hex_values' => array_values(array_map(
                static fn (array $entry): string => (string) $entry['hex'],
                array_values(array_filter(
                    $integerEntries,
                    static fn (array $entry): bool => is_string($entry['hex'] ?? null)
                ))
            )),
            'conflicting_permission_names' => array_keys($conflicts),
            'conflicting_statuses' => $conflicts,
            'entries' => $entries,
            'review_only' => true,
            'decryption_performed' => false,
            'executes_permission_enforcement' => false,
        ];
    }

    private function standardPermissionWordOperandShape(string $value): string
    {
        $trimmed = $this->trimPdfWhitespaceAndComments($value);
        if ($trimmed === '') {
            return 'empty';
        }
        if (str_starts_with($trimmed, '[')) {
            return 'array';
        }
        if (str_starts_with($trimmed, '<<')) {
            return 'dictionary';
        }
        if (str_starts_with($trimmed, '(')) {
            return 'literal_string';
        }
        if (str_starts_with($trimmed, '<')) {
            return 'hex_string';
        }
        if (str_starts_with($trimmed, '/')) {
            return 'name';
        }
        if ($this->objectReferenceFromValue($trimmed) !== null) {
            return 'indirect_reference';
        }

        return 'token';
    }

    private function standardPermissionWordOperandStatus(string $rawValue, string $operandShape, bool $resolved): string
    {
        if (!$resolved && $this->objectReferenceFromValue($rawValue) !== null) {
            return 'permission_word_unresolved_reference';
        }
        if (in_array($operandShape, ['array', 'dictionary'], true)) {
            return 'permission_word_composite_operand_review';
        }

        return 'permission_word_non_integer_review';
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return array<string, list<string>>
     */
    private function standardPermissionWordConflicts(array $entries): array
    {
        $statusesByName = [];
        foreach ($entries as $entry) {
            $bits = is_array($entry['permission_bits_by_name'] ?? null) ? $entry['permission_bits_by_name'] : [];
            foreach ($bits as $name => $bit) {
                if (!is_string($name) || !is_array($bit) || !is_string($bit['status'] ?? null)) {
                    continue;
                }

                $statusesByName[$name] ??= [];
                if (!in_array($bit['status'], $statusesByName[$name], true)) {
                    $statusesByName[$name][] = $bit['status'];
                }
            }
        }

        $conflicts = [];
        foreach ($statusesByName as $name => $statuses) {
            if (count($statuses) > 1) {
                $conflicts[$name] = $statuses;
            }
        }

        return $conflicts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function standardPermissionBitReview(int $unsigned, int $effectiveRevision): array
    {
        $rows = [];
        foreach (self::STANDARD_PERMISSION_FLAGS as $flag) {
            $mask = (int) $flag['mask'];
            $minimumRevision = (int) $flag['minimum_revision'];
            $bitSet = ($unsigned & $mask) !== 0;
            $applicable = $effectiveRevision >= $minimumRevision;
            $allowed = $applicable && $bitSet;
            $denied = $applicable && !$bitSet;

            $rows[] = [
                'source' => 'standard_permission_bit_review',
                'name' => $flag['name'],
                'bit' => $this->standardPermissionBitNumber($mask),
                'mask' => $mask,
                'mask_hex' => strtoupper(sprintf('%08X', $mask)),
                'minimum_revision' => $minimumRevision,
                'effective_revision' => $effectiveRevision,
                'bit_set' => $bitSet,
                'applicable' => $applicable,
                'allowed' => $allowed,
                'denied' => $denied,
                'status' => !$applicable
                    ? 'not_applicable_for_revision'
                    : ($allowed ? 'allowed_by_permission_bit' : 'denied_by_permission_bit'),
            ];
        }

        return $rows;
    }

    private function standardPermissionBitNumber(int $mask): int
    {
        $bit = 1;
        while ($mask > 1) {
            $mask >>= 1;
            $bit++;
        }

        return $bit;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private function standardPermissionNamesByStatus(array $rows, string $status): array
    {
        $names = [];
        foreach ($rows as $row) {
            if (($row['status'] ?? null) === $status && is_string($row['name'] ?? null)) {
                $names[] = $row['name'];
            }
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private function standardApplicablePermissionNames(array $rows, bool $applicable): array
    {
        $names = [];
        foreach ($rows as $row) {
            if (($row['applicable'] ?? null) === $applicable && is_string($row['name'] ?? null)) {
                $names[] = $row['name'];
            }
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private function standardPermissionBitStatuses(array $rows): array
    {
        $statuses = [];
        foreach ($rows as $row) {
            if (is_string($row['status'] ?? null) && !in_array($row['status'], $statuses, true)) {
                $statuses[] = $row['status'];
            }
        }

        return $statuses;
    }

    /**
     * Standard permission words are signed 32-bit integers, but some writers
     * serialize the same bit pattern as an unsigned decimal.
     *
     * @return array{signed: int|null, unsigned: int|null, declared_form: string, normalized_from_unsigned_decimal: bool, range_valid: bool, range_status: string}
     */
    private function standardPermissionWordValues(int $declared): array
    {
        $signed32Min = -2147483648;
        $unsigned32Max = 4294967295;
        $signed32Max = 2147483647;
        if ($declared < $signed32Min || $declared > $unsigned32Max) {
            return [
                'signed' => null,
                'unsigned' => null,
                'declared_form' => 'out_of_range_decimal',
                'normalized_from_unsigned_decimal' => false,
                'range_valid' => false,
                'range_status' => 'permission_word_out_of_range_review',
            ];
        }

        if ($declared > $signed32Max && $declared <= $unsigned32Max) {
            return [
                'signed' => $declared - ($unsigned32Max + 1),
                'unsigned' => $declared,
                'declared_form' => 'unsigned_decimal',
                'normalized_from_unsigned_decimal' => true,
                'range_valid' => true,
                'range_status' => 'permission_word_in_32bit_range',
            ];
        }

        return [
            'signed' => $declared,
            'unsigned' => $declared < 0 ? $declared + ($unsigned32Max + 1) : $declared,
            'declared_form' => 'signed_decimal',
            'normalized_from_unsigned_decimal' => false,
            'range_valid' => true,
            'range_status' => 'permission_word_in_32bit_range',
        ];
    }

    /**
     * @return array{reserved_bits_valid: bool, permission_word_status: string, reserved_bits: array<string, mixed>}
     */
    private function standardPermissionReservedBitsMetadata(int $unsigned, int $effectiveRevision, bool $rangeValid): array
    {
        $expectedSetMask = $effectiveRevision < 3 ? 0xFFFFFFC0 : 0xFFFFF0C0;
        $expectedClearMask = 0x00000003;
        if (!$rangeValid) {
            return [
                'reserved_bits_valid' => false,
                'permission_word_status' => 'permission_word_out_of_range_review',
                'reserved_bits' => [
                    'expected_set_mask_hex' => strtoupper(sprintf('%08X', $expectedSetMask)),
                    'expected_clear_mask_hex' => strtoupper(sprintf('%08X', $expectedClearMask)),
                    'set_bits_ok' => null,
                    'clear_bits_ok' => null,
                    'word_range_valid' => false,
                    'word_range_status' => 'permission_word_out_of_range_review',
                    'violations' => ['permission_word_out_of_32bit_range'],
                ],
            ];
        }

        $setBitsOk = ($unsigned & $expectedSetMask) === $expectedSetMask;
        $clearBitsOk = ($unsigned & $expectedClearMask) === 0;
        $violations = [];

        if (!$clearBitsOk) {
            $violations[] = 'reserved_bits_1_2_set';
        }
        if (($unsigned & 0x000000C0) !== 0x000000C0) {
            $violations[] = 'reserved_bits_7_8_clear';
        }
        if ($effectiveRevision < 3) {
            if (($unsigned & 0xFFFFFF00) !== 0xFFFFFF00) {
                $violations[] = 'reserved_bits_9_32_clear';
            }
        } elseif (($unsigned & 0xFFFFF000) !== 0xFFFFF000) {
            $violations[] = 'reserved_bits_13_32_clear';
        }

        $valid = $setBitsOk && $clearBitsOk;

        return [
            'reserved_bits_valid' => $valid,
            'permission_word_status' => $valid ? 'well_formed_standard_permissions' : 'malformed_reserved_bits_review',
            'reserved_bits' => [
                'expected_set_mask_hex' => strtoupper(sprintf('%08X', $expectedSetMask)),
                'expected_clear_mask_hex' => strtoupper(sprintf('%08X', $expectedClearMask)),
                'set_bits_ok' => $setBitsOk,
                'clear_bits_ok' => $clearBitsOk,
                'word_range_valid' => true,
                'word_range_status' => 'permission_word_in_32bit_range',
                'violations' => $violations,
            ],
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, array<string, mixed>>
     */
    private function cryptFilterMetadata(string $dictionary, array $objects): array
    {
        $value = $this->dictionaryTopLevelRawValue($dictionary, 'CF');
        if ($value === null) {
            return [];
        }

        $cfDictionary = $this->resolveDictionaryFromValue($value, $objects);
        if ($cfDictionary === null) {
            return [];
        }

        $body = $cfDictionary['body'];
        $filters = [];
        for ($offset = 0, $length = strlen($body); $offset < $length;) {
            while ($offset < $length && ctype_space($body[$offset])) {
                $offset++;
            }

            if ($offset >= $length || $body[$offset] !== '/') {
                $offset++;
                continue;
            }

            if (preg_match('/\/([^\s\[\]()<>{}\/%]+)/A', substr($body, $offset), $match) !== 1) {
                $offset++;
                continue;
            }

            $name = $this->decodePdfName($match[1]);
            $offset += strlen($match[0]);
            while ($offset < $length && ctype_space($body[$offset])) {
                $offset++;
            }

            $valueOffset = $offset;
            $filterValue = $this->readPdfValueAt($body, $valueOffset);
            if ($filterValue === null) {
                $offset++;
                continue;
            }

            $filterDictionary = $this->resolveDictionaryFromValue($filterValue, $objects);
            if ($filterDictionary === null) {
                $offset = $valueOffset + strlen($filterValue);
                continue;
            }

            $filterBody = $filterDictionary['body'];
            $metadata = [];
            $methodRawValue = $this->dictionaryTopLevelRawValue($filterBody, 'CFM');
            $method = $methodRawValue === null
                ? null
                : ($this->dictionaryNameValue($filterBody, 'CFM', $objects)
                    ?? $this->dictionaryStringValue($filterBody, 'CFM'));
            if ($method !== null) {
                $metadata['method'] = $method;
            } elseif ($methodRawValue === null) {
                $metadata['method'] = 'None';
                $metadata['cfm_defaulted'] = true;
                $metadata['cfm_source'] = 'pdf_default_none';
            }

            $authEvent = $this->dictionaryNameValue($filterBody, 'AuthEvent', $objects)
                ?? $this->dictionaryStringValue($filterBody, 'AuthEvent');
            if ($authEvent !== null) {
                $metadata['auth_event'] = $authEvent;
            } elseif ($method !== null && !in_array($method, ['Identity', 'None'], true)) {
                $metadata['auth_event'] = 'DocOpen';
                $metadata['auth_event_defaulted'] = true;
                $metadata['auth_event_source'] = 'pdf_default_doc_open';
            }

            $lengthBytes = $this->dictionaryIntegerValue($filterBody, 'Length', $objects);
            if ($lengthBytes !== null) {
                $metadata['key_length_bytes'] = $lengthBytes;
            }

            $recipients = $this->recipientListMetadata(
                $this->dictionaryTopLevelRawValue($filterBody, 'Recipients'),
                $objects,
                'crypt_filter_recipients',
                $name
            );
            if ($recipients !== null) {
                $metadata['recipients'] = $recipients;
            }

            if ($metadata !== []) {
                $filters[$name] = $metadata;
            }

            $offset = $valueOffset + strlen($filterValue);
        }

        return $filters;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function encryptedPermissionValidationMetadata(string $dictionary, array $objects): ?array
    {
        $values = $this->dictionaryTopLevelRawValues($dictionary, 'Perms');
        if ($values === []) {
            return null;
        }

        $entries = [];
        foreach ($values as $index => $value) {
            $bytes = $this->pdfStringBytesFromValue($value, $objects);
            $entries[] = [
                'source' => 'standard_permissions_validation_ciphertext_entry',
                'index' => $index,
                'present' => true,
                'bytes_resolved' => $bytes !== null,
                'bytes' => $bytes === null ? null : strlen($bytes),
                'sha256' => $bytes === null ? null : hash('sha256', $bytes),
                'status' => $bytes === null
                    ? 'permission_digest_entry_unresolved'
                    : 'permission_digest_ciphertext_review',
                'raw_bytes_exposed' => false,
            ];
        }

        $selectedIndex = count($entries) - 1;
        $selected = $entries[$selectedIndex];

        return [
            'bytes_resolved' => (bool) ($selected['bytes_resolved'] ?? false),
            'bytes' => $selected['bytes'] ?? null,
            'sha256' => $selected['sha256'] ?? null,
            'declared_entry_count' => count($values),
            'duplicate_entries' => count($values) > 1,
            'selected_entry_index' => $selectedIndex,
            'selected_entry_status' => $selected['status'] ?? null,
            'entry_statuses' => $this->uniqueStrings(array_values(array_filter(
                array_map(
                    static fn (array $entry): mixed => $entry['status'] ?? null,
                    $entries
                ),
                static fn (mixed $status): bool => is_string($status)
            ))),
            'entries' => $entries,
        ];
    }

    /**
     * Standard security-handler authentication entries are password-derived
     * validation/encryption-key material. Keep them review-only: expose length
     * and digest fingerprints for fixture parity, never raw bytes, and never
     * validate passwords or authenticate the encrypted permissions string.
     *
     * @param array<int, string> $objects
     * @param array<string, array<string, mixed>> $cryptFilters
     * @param array<string, mixed> $metadata
     * @param array<string, mixed>|null $perms
     * @return array<string, mixed>
     */
    private function standardAuthenticationReview(
        string $dictionary,
        array $objects,
        array $cryptFilters,
        array $metadata,
        ?array $perms
    ): array {
        if (($metadata['filter'] ?? null) !== 'Standard') {
            return [];
        }

        $revision = is_int($metadata['revision'] ?? null) ? $metadata['revision'] : null;
        $expectedLengths = $this->standardAuthenticationExpectedLengths($revision);
        $entries = [];
        foreach ([
            'O' => ['key' => 'owner_validation', 'label' => 'owner_password_validation'],
            'U' => ['key' => 'user_validation', 'label' => 'user_password_validation'],
            'OE' => ['key' => 'owner_encryption_key', 'label' => 'owner_file_key_encryption'],
            'UE' => ['key' => 'user_encryption_key', 'label' => 'user_file_key_encryption'],
        ] as $pdfName => $definition) {
            $entries[$definition['key']] = $this->standardAuthenticationEntryMetadata(
                $dictionary,
                $objects,
                $pdfName,
                $definition['label'],
                $expectedLengths[$pdfName] ?? null,
                $this->standardAuthenticationEntryRequired($pdfName, $revision)
            );
        }

        $permissionDigest = [
            'source' => 'standard_permissions_validation_ciphertext',
            'present' => $perms !== null,
            'bytes' => $perms['bytes'] ?? null,
            'sha256' => $perms['sha256'] ?? null,
            'declared_entry_count' => $perms['declared_entry_count'] ?? 0,
            'duplicate_entries' => (bool) ($perms['duplicate_entries'] ?? false),
            'selected_entry_index' => $perms['selected_entry_index'] ?? null,
            'selected_entry_status' => $perms['selected_entry_status'] ?? null,
            'entry_statuses' => is_array($perms['entry_statuses'] ?? null) ? $perms['entry_statuses'] : [],
            'entry_reviews' => is_array($perms['entries'] ?? null) ? $perms['entries'] : [],
            'expected_bytes' => $expectedLengths['Perms'] ?? null,
            'length_valid' => $perms !== null && isset($expectedLengths['Perms'])
                ? $perms['bytes'] === $expectedLengths['Perms']
                : ($perms === null ? null : true),
            'status' => $this->standardPermissionDigestStatus($perms, $expectedLengths['Perms'] ?? null, $revision),
            'raw_bytes_exposed' => false,
            'permissions_authenticated' => false,
        ];

        $credentialEntriesPresent = [];
        foreach ($entries as $key => $entry) {
            if (($entry['present'] ?? false) === true) {
                $credentialEntriesPresent[] = $key;
            }
        }

        return [
            'source' => 'standard_security_handler_authentication_review',
            'handler' => 'Standard',
            'revision' => $revision,
            'revision_label' => $metadata['revision_label'] ?? null,
            'algorithm' => $metadata['algorithm'] ?? null,
            'key_length_bits' => $metadata['key_length_bits'] ?? null,
            'auth_events' => $this->standardAuthenticationAuthEvents($cryptFilters),
            'expected_lengths' => $expectedLengths,
            'entries' => $entries,
            'permission_digest' => $permissionDigest,
            'credential_entries_present' => $credentialEntriesPresent,
            'credential_material_exposed' => false,
            'raw_owner_user_keys_exposed' => false,
            'raw_file_encryption_keys_exposed' => false,
            'password_validation_performed' => false,
            'owner_password_validated' => false,
            'user_password_validated' => false,
            'permissions_authenticated' => false,
            'decryption_performed' => false,
            'executes_decryption' => false,
            'executes_permission_enforcement' => false,
            'review_only' => true,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function standardAuthenticationExpectedLengths(?int $revision): array
    {
        if ($revision === null) {
            return [];
        }

        if ($revision >= 5) {
            return [
                'O' => 48,
                'U' => 48,
                'OE' => 32,
                'UE' => 32,
                'Perms' => 16,
            ];
        }

        if ($revision >= 2) {
            return [
                'O' => 32,
                'U' => 32,
            ];
        }

        return [];
    }

    private function standardAuthenticationEntryRequired(string $pdfName, ?int $revision): bool
    {
        if ($revision === null) {
            return false;
        }

        return match ($pdfName) {
            'O', 'U' => $revision >= 2,
            'OE', 'UE' => $revision >= 5,
            default => false,
        };
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function standardAuthenticationEntryMetadata(
        string $dictionary,
        array $objects,
        string $pdfName,
        string $purpose,
        ?int $expectedBytes,
        bool $required
    ): array {
        $values = $this->dictionaryTopLevelRawValues($dictionary, $pdfName);
        $entries = [];
        foreach ($values as $index => $value) {
            $entryBytes = $this->pdfStringBytesFromValue($value, $objects);
            $entryLength = $entryBytes === null ? null : strlen($entryBytes);
            $entryLengthValid = $entryLength !== null && $expectedBytes !== null
                ? $entryLength === $expectedBytes
                : ($entryLength === null ? null : true);
            $entries[] = [
                'source' => 'standard_authentication_entry_declaration_review',
                'index' => $index,
                'present' => true,
                'bytes_resolved' => $entryBytes !== null,
                'bytes' => $entryLength,
                'expected_bytes' => $expectedBytes,
                'length_valid' => $entryLengthValid,
                'sha256' => $entryBytes === null ? null : hash('sha256', $entryBytes),
                'status' => $this->standardAuthenticationEntryStatus(true, $entryBytes !== null, $entryLengthValid, $required),
                'raw_bytes_exposed' => false,
            ];
        }

        $selectedIndex = count($entries) - 1;
        $selectedEntry = $selectedIndex >= 0 ? $entries[$selectedIndex] : null;
        $value = $selectedIndex >= 0 ? $values[$selectedIndex] : null;
        $bytes = $value === null ? null : $this->pdfStringBytesFromValue($value, $objects);
        $length = $bytes === null ? null : strlen($bytes);
        $lengthValid = $length !== null && $expectedBytes !== null ? $length === $expectedBytes : ($length === null ? null : true);
        $selectedStatus = $this->standardAuthenticationEntryStatus($value !== null, $bytes !== null, $lengthValid, $required);
        $duplicateEntries = count($values) > 1;

        return [
            'pdf_name' => $pdfName,
            'purpose' => $purpose,
            'required_for_revision' => $required,
            'present' => $value !== null,
            'declared_entry_count' => count($values),
            'duplicate_entries' => $duplicateEntries,
            'selected_entry_index' => $selectedIndex >= 0 ? $selectedIndex : null,
            'selected_entry_status' => $selectedEntry['status'] ?? $selectedStatus,
            'entry_statuses' => $this->uniqueStrings(array_values(array_filter(
                array_map(
                    static fn (array $entry): mixed => $entry['status'] ?? null,
                    $entries
                ),
                static fn (mixed $status): bool => is_string($status)
            ))),
            'entry_reviews' => $entries,
            'bytes_resolved' => $bytes !== null,
            'bytes' => $length,
            'expected_bytes' => $expectedBytes,
            'length_valid' => $lengthValid,
            'sha256' => $bytes === null ? null : hash('sha256', $bytes),
            'status' => $duplicateEntries ? 'authentication_entry_duplicate_entries_review' : $selectedStatus,
            'raw_bytes_exposed' => false,
            'validated' => false,
        ];
    }

    private function standardAuthenticationEntryStatus(bool $present, bool $resolved, ?bool $lengthValid, bool $required): string
    {
        if (!$present) {
            return $required ? 'required_authentication_entry_missing' : 'authentication_entry_absent';
        }
        if (!$resolved) {
            return 'authentication_entry_unresolved';
        }
        if ($lengthValid === false) {
            return 'authentication_entry_length_mismatch_review';
        }

        return 'authentication_entry_digest_review';
    }

    /**
     * @param array<string, mixed>|null $perms
     */
    private function standardPermissionDigestStatus(?array $perms, ?int $expectedBytes, ?int $revision): string
    {
        if ($perms === null) {
            return $revision !== null && $revision >= 5
                ? 'required_permission_digest_missing'
                : 'permission_digest_absent_for_legacy_revision';
        }

        if (($perms['duplicate_entries'] ?? false) === true) {
            return 'permission_digest_duplicate_entries_review';
        }

        if (($perms['bytes_resolved'] ?? true) !== true) {
            return 'permission_digest_unresolved';
        }

        if ($expectedBytes !== null && ($perms['bytes'] ?? null) !== $expectedBytes) {
            return 'permission_digest_length_mismatch_review';
        }

        return 'permission_digest_ciphertext_review';
    }

    /**
     * @param array<string, array<string, mixed>> $cryptFilters
     * @return list<string>
     */
    private function standardAuthenticationAuthEvents(array $cryptFilters): array
    {
        $events = [];
        foreach ($cryptFilters as $filter) {
            $event = $filter['auth_event'] ?? null;
            if (is_string($event)) {
                $events[] = $event;
            }
        }

        return $this->uniqueStrings($events);
    }

    /**
     * Public-key security handlers place recipient-specific permissions inside
     * PKCS#7 envelopes. This lane can inventory envelopes, but it does not parse
     * CMS, expose certificate bytes, or decide recipient access rights.
     *
     * @param array<int, string> $objects
     * @param array<string, array<string, mixed>> $cryptFilters
     * @return array<string, mixed>
     */
    private function publicKeyRecipientReview(string $dictionary, array $objects, array $cryptFilters, array $metadata): array
    {
        $handler = $this->dictionaryNameValue($dictionary, 'Filter', $objects)
            ?? $this->dictionaryStringValue($dictionary, 'Filter');
        $subfilter = $this->dictionaryNameValue($dictionary, 'SubFilter', $objects)
            ?? $this->dictionaryStringValue($dictionary, 'SubFilter');
        $topLevelRecipients = $this->recipientListMetadata(
            $this->dictionaryTopLevelRawValue($dictionary, 'Recipients'),
            $objects,
            'encryption_dictionary_recipients'
        );

        $recipientLists = [];
        $cryptFilterRecipientNames = [];
        if ($topLevelRecipients !== null) {
            $recipientLists[] = $topLevelRecipients;
        }

        foreach ($cryptFilters as $name => $filter) {
            $recipients = is_array($filter['recipients'] ?? null) ? $filter['recipients'] : null;
            if ($recipients === null) {
                continue;
            }

            $recipientLists[] = $recipients;
            $cryptFilterRecipientNames[] = $name;
        }

        $hasPublicKeySubfilter = is_string($subfilter)
            && in_array($subfilter, ['adbe.pkcs7.s3', 'adbe.pkcs7.s4', 'adbe.pkcs7.s5'], true);
        $hasRecipientLists = $recipientLists !== [];
        $isPublicKeyHandler = is_string($handler) && $handler !== 'Standard' && ($hasPublicKeySubfilter || $hasRecipientLists);
        if (!$isPublicKeyHandler && !$hasRecipientLists) {
            return [];
        }

        $recipientCount = 0;
        $recipientBytes = 0;
        $unresolvedCount = 0;
        $recipientHashes = [];
        foreach ($recipientLists as $list) {
            $recipientCount += (int) ($list['recipient_count'] ?? 0);
            $recipientBytes += (int) ($list['recipient_bytes'] ?? 0);
            $unresolvedCount += (int) ($list['unresolved_recipient_count'] ?? 0);
            foreach ($list['recipient_sha256'] ?? [] as $hash) {
                if (is_string($hash) && !in_array($hash, $recipientHashes, true)) {
                    $recipientHashes[] = $hash;
                }
            }
        }

        $cryptFilterSelection = $this->publicKeyRecipientCryptFilterSelection($dictionary, $objects, $cryptFilters, $metadata);
        $topLevelRecipientsSelected = $this->publicKeyTopLevelRecipientsSelected($handler, $subfilter, $topLevelRecipients !== null);
        $selectedRecipientCount = (int) ($cryptFilterSelection['selected_recipient_count'] ?? 0);
        $selectedRecipientBytes = (int) ($cryptFilterSelection['selected_recipient_bytes'] ?? 0);
        $selectedUnresolvedRecipientCount = (int) ($cryptFilterSelection['selected_unresolved_recipient_count'] ?? 0);
        $selectedRecipientHashes = array_values(array_filter(
            $cryptFilterSelection['selected_recipient_sha256'] ?? [],
            static fn (mixed $hash): bool => is_string($hash)
        ));
        $selectedRecipientSources = [];
        if ($topLevelRecipientsSelected && $topLevelRecipients !== null) {
            $selectedRecipientSources[] = 'encryption_dictionary_recipients';
            $selectedRecipientCount += (int) ($topLevelRecipients['recipient_count'] ?? 0);
            $selectedRecipientBytes += (int) ($topLevelRecipients['recipient_bytes'] ?? 0);
            $selectedUnresolvedRecipientCount += (int) ($topLevelRecipients['unresolved_recipient_count'] ?? 0);
            foreach ($topLevelRecipients['recipient_sha256'] ?? [] as $hash) {
                if (is_string($hash) && !in_array($hash, $selectedRecipientHashes, true)) {
                    $selectedRecipientHashes[] = $hash;
                }
            }
        }
        if (($cryptFilterSelection['selected_recipient_count'] ?? 0) > 0) {
            $selectedRecipientSources[] = 'crypt_filter_recipients';
        }
        $selectedRecipientSources = $this->uniqueStrings($selectedRecipientSources);

        return [
            'source' => 'public_key_security_handler',
            'handler' => $handler,
            'subfilter' => $subfilter,
            'recipient_source_policy' => $this->publicKeyRecipientSourcePolicy($subfilter, $topLevelRecipients !== null, $cryptFilterRecipientNames !== []),
            'recipient_count' => $recipientCount,
            'recipient_bytes' => $recipientBytes,
            'unresolved_recipient_count' => $unresolvedCount,
            'recipient_sha256' => $recipientHashes,
            'top_level_recipient_count' => (int) ($topLevelRecipients['recipient_count'] ?? 0),
            'top_level_recipient_bytes' => (int) ($topLevelRecipients['recipient_bytes'] ?? 0),
            'top_level_unresolved_recipient_count' => (int) ($topLevelRecipients['unresolved_recipient_count'] ?? 0),
            'top_level_recipient_sha256' => array_values(array_filter(
                $topLevelRecipients['recipient_sha256'] ?? [],
                static fn (mixed $hash): bool => is_string($hash)
            )),
            'top_level_recipients_selected' => $topLevelRecipientsSelected,
            'crypt_filter_recipient_filter_names' => $this->uniqueStrings($cryptFilterRecipientNames),
            'selected_crypt_filter_recipient_filter_names' => $cryptFilterSelection['selected_recipient_filter_names'],
            'unselected_crypt_filter_recipient_filter_names' => $cryptFilterSelection['unselected_recipient_filter_names'],
            'selected_recipient_count' => $selectedRecipientCount,
            'selected_recipient_bytes' => $selectedRecipientBytes,
            'selected_unresolved_recipient_count' => $selectedUnresolvedRecipientCount,
            'selected_recipient_sha256' => $selectedRecipientHashes,
            'selected_recipient_sources' => $selectedRecipientSources,
            'selected_recipient_source_policy' => $this->publicKeySelectedRecipientSourcePolicy($selectedRecipientSources),
            'crypt_filter_selection' => $cryptFilterSelection,
            'recipient_lists' => $recipientLists,
            'permissions_available_in_recipient_envelopes' => $recipientCount > 0,
            'selected_permissions_available_in_recipient_envelopes' => $selectedRecipientCount > 0,
            'permissions_decoded' => false,
            'permission_decode_status' => $recipientCount > 0
                ? 'cms_pkcs7_permission_decode_unavailable'
                : 'public_key_recipient_envelopes_missing',
            'requires_private_key_for_permission_review' => true,
            'recipient_bytes_exposed' => false,
            'recipient_certificates_exposed' => false,
            'executes_cms_parse' => false,
            'executes_decryption' => false,
            'review_only' => true,
        ];
    }

    private function publicKeyTopLevelRecipientsSelected(?string $handler, ?string $subfilter, bool $hasTopLevelRecipients): bool
    {
        if (!$hasTopLevelRecipients || !is_string($handler) || $handler === 'Standard') {
            return false;
        }

        return $subfilter !== 'adbe.pkcs7.s5';
    }

    /**
     * @param list<string> $sources
     */
    private function publicKeySelectedRecipientSourcePolicy(array $sources): string
    {
        if ($sources === []) {
            return 'no_selected_recipient_permission_envelopes';
        }

        if (count($sources) === 1) {
            return $sources[0];
        }

        return 'mixed_selected_recipient_permission_envelopes';
    }

    private function publicKeyRecipientSourcePolicy(?string $subfilter, bool $hasTopLevelRecipients, bool $hasCryptFilterRecipients): string
    {
        if ($subfilter === 'adbe.pkcs7.s5') {
            if ($hasCryptFilterRecipients) {
                return $hasTopLevelRecipients
                    ? 'crypt_filter_recipients_with_legacy_encryption_dictionary_recipients'
                    : 'crypt_filter_recipients';
            }

            return $hasTopLevelRecipients
                ? 'legacy_encryption_dictionary_recipients_present_for_s5'
                : 'crypt_filter_recipients_expected_but_missing';
        }

        if (in_array($subfilter, ['adbe.pkcs7.s3', 'adbe.pkcs7.s4'], true)) {
            return $hasTopLevelRecipients
                ? 'encryption_dictionary_recipients'
                : 'encryption_dictionary_recipients_expected_but_missing';
        }

        if ($hasCryptFilterRecipients) {
            return 'crypt_filter_recipients_without_pkcs7_s5_subfilter';
        }

        return $hasTopLevelRecipients ? 'encryption_dictionary_recipients' : 'recipient_arrays_missing';
    }

    /**
     * @param array<string, array<string, mixed>> $cryptFilters
     * @return array<string, mixed>
     */
    private function publicKeyRecipientCryptFilterSelection(
        string $dictionary,
        array $objects,
        array $cryptFilters,
        array $metadata
    ): array
    {
        $declared = [];
        $defaulted = [];
        $sources = [];
        $selectedRows = [];
        $selectedNames = [];
        $selectedRecipientNames = [];
        $selectedRecipientHashes = [];
        $selectedRecipientCount = 0;
        $selectedRecipientBytes = 0;
        $selectedUnresolvedRecipientCount = 0;
        $countedRecipientFilters = [];

        foreach ([
            'stream_filter' => [
                'pdf_name' => 'StmF',
                'default_flag' => 'stream_filter_defaulted',
                'source_key' => 'stream_filter_source',
            ],
            'string_filter' => [
                'pdf_name' => 'StrF',
                'default_flag' => 'string_filter_defaulted',
                'source_key' => 'string_filter_source',
            ],
            'embedded_file_filter' => [
                'pdf_name' => 'EFF',
                'default_flag' => 'embedded_file_filter_defaulted_from_stream_filter',
                'source_key' => 'embedded_file_filter_source',
            ],
        ] as $role => $definition) {
            $pdfName = $definition['pdf_name'];
            $explicitName = $this->dictionaryNameValue($dictionary, $pdfName, $objects)
                ?? $this->dictionaryStringValue($dictionary, $pdfName);
            $name = $explicitName;
            $roleDefaulted = false;
            $roleSource = 'pdf_dictionary';
            if ($name === null && is_string($metadata[$role] ?? null) && $metadata[$role] !== '') {
                $name = $metadata[$role];
                $roleDefaulted = ($metadata[$definition['default_flag']] ?? false) === true;
                $roleSource = is_string($metadata[$definition['source_key']] ?? null)
                    ? $metadata[$definition['source_key']]
                    : 'pdf_default_crypt_filter_role';
            }
            if ($name === null) {
                continue;
            }

            $declared[$role] = $name;
            $sources[$role] = $roleSource;
            if ($roleDefaulted) {
                $defaulted[$role] = $name;
            }
            $filter = $cryptFilters[$name] ?? null;
            $recipients = is_array($filter['recipients'] ?? null) ? $filter['recipients'] : null;
            $hasRecipients = $recipients !== null;
            if (!in_array($name, $selectedNames, true)) {
                $selectedNames[] = $name;
            }
            if ($hasRecipients && !in_array($name, $selectedRecipientNames, true)) {
                $selectedRecipientNames[] = $name;
            }

            $selectedRows[] = [
                'role' => $role,
                'pdf_name' => $pdfName,
                'name' => $name,
                'filter_source' => $roleSource,
                'filter_defaulted' => $roleDefaulted,
                'crypt_filter_present' => is_array($filter),
                'method' => is_array($filter) ? ($filter['method'] ?? null) : null,
                'auth_event' => is_array($filter) ? ($filter['auth_event'] ?? null) : null,
                'has_recipients' => $hasRecipients,
                'recipient_count' => $hasRecipients ? (int) ($recipients['recipient_count'] ?? 0) : 0,
                'recipient_bytes' => $hasRecipients ? (int) ($recipients['recipient_bytes'] ?? 0) : 0,
                'unresolved_recipient_count' => $hasRecipients ? (int) ($recipients['unresolved_recipient_count'] ?? 0) : 0,
                'permission_decode_status' => $hasRecipients ? ($recipients['permission_decode_status'] ?? null) : null,
                'recipient_bytes_exposed' => false,
            ];

            if (!$hasRecipients || isset($countedRecipientFilters[$name])) {
                continue;
            }

            $countedRecipientFilters[$name] = true;
            $selectedRecipientCount += (int) ($recipients['recipient_count'] ?? 0);
            $selectedRecipientBytes += (int) ($recipients['recipient_bytes'] ?? 0);
            $selectedUnresolvedRecipientCount += (int) ($recipients['unresolved_recipient_count'] ?? 0);
            foreach ($recipients['recipient_sha256'] ?? [] as $hash) {
                if (is_string($hash) && !in_array($hash, $selectedRecipientHashes, true)) {
                    $selectedRecipientHashes[] = $hash;
                }
            }
        }

        $cryptFilterRecipientNames = [];
        foreach ($cryptFilters as $name => $filter) {
            if (is_array($filter['recipients'] ?? null)) {
                $cryptFilterRecipientNames[] = (string) $name;
            }
        }

        $unselectedRecipientNames = [];
        foreach ($cryptFilterRecipientNames as $name) {
            if (!in_array($name, $selectedRecipientNames, true)) {
                $unselectedRecipientNames[] = $name;
            }
        }

        return [
            'source' => 'public_key_crypt_filter_selection',
            'declared_content_filters' => $declared,
            'defaulted_content_filters' => $defaulted,
            'content_filter_sources' => $sources,
            'selected_crypt_filters' => $selectedRows,
            'selected_filter_names' => $this->uniqueStrings($selectedNames),
            'selected_recipient_filter_names' => $this->uniqueStrings($selectedRecipientNames),
            'unselected_recipient_filter_names' => $this->uniqueStrings($unselectedRecipientNames),
            'selected_recipient_count' => $selectedRecipientCount,
            'selected_recipient_bytes' => $selectedRecipientBytes,
            'selected_unresolved_recipient_count' => $selectedUnresolvedRecipientCount,
            'selected_recipient_sha256' => $selectedRecipientHashes,
            'recipient_bytes_exposed' => false,
            'permissions_decoded' => false,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function recipientListMetadata(?string $value, array $objects, string $source, ?string $cryptFilter = null): ?array
    {
        if ($value === null) {
            return null;
        }

        $items = $this->arrayItemsFromValue($value, $objects);
        if ($items === []) {
            $single = $this->pdfStringBytesFromValue($value, $objects);
            if ($single === null) {
                return [
                    'source' => $source,
                    'recipient_count' => 0,
                    'unresolved_recipient_count' => 1,
                    'recipient_bytes' => 0,
                    'recipient_sha256' => [],
                    'recipients' => [],
                    'permissions_decoded' => false,
                    'permission_decode_status' => 'recipient_bytes_unresolved',
                    'recipient_bytes_exposed' => false,
                ];
            }

            $items = [$value];
        }

        $recipients = [];
        $hashes = [];
        $bytesTotal = 0;
        $unresolved = 0;
        foreach (array_values($items) as $index => $item) {
            $bytes = $this->pdfStringBytesFromValue($item, $objects);
            if ($bytes === null) {
                $unresolved++;
                continue;
            }

            $hash = hash('sha256', $bytes);
            $hashes[] = $hash;
            $bytesTotal += strlen($bytes);
            $recipients[] = [
                'index' => $index,
                'bytes' => strlen($bytes),
                'sha256' => $hash,
                'permissions_decoded' => false,
                'permission_source' => 'pkcs7_recipient_envelope',
                'recipient_bytes_exposed' => false,
            ];
        }

        $metadata = [
            'source' => $source,
            'recipient_count' => count($recipients),
            'unresolved_recipient_count' => $unresolved,
            'recipient_bytes' => $bytesTotal,
            'recipient_sha256' => $hashes,
            'recipients' => $recipients,
            'permissions_decoded' => false,
            'permission_decode_status' => $recipients === []
                ? 'recipient_bytes_unresolved'
                : 'cms_pkcs7_permission_decode_unavailable',
            'recipient_bytes_exposed' => false,
        ];

        if ($cryptFilter !== null) {
            $metadata['crypt_filter'] = $cryptFilter;
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfStringBytesFromValue(string $value, array $objects): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^\d+\s+\d+\s+R\b/s', $trimmed) === 1) {
            $objectBody = $this->objectBodyFromReferenceValue($trimmed, $objects);
            return $objectBody === null ? null : $this->pdfStringBytesFromValue($objectBody, $objects);
        }

        if ($trimmed[0] === '(') {
            $literal = $this->literalStringBytesAt($trimmed, 0);
            return $literal['bytes'] ?? null;
        }

        if ($trimmed[0] === '<' && substr($trimmed, 0, 2) !== '<<') {
            $hex = $this->hexStringBytesAt($trimmed, 0);
            return $hex['bytes'] ?? null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $xmp
     * @param array<string, string> $info
     * @param list<array<string, mixed>> $outputIntents
     * @param array<string, mixed> $catalog
     * @param array<string, mixed> $trailerIds
     * @param array<string, mixed> $encryption
     * @return array<string, mixed>
     */
    private function mergedMetadata(array $xmp, array $info, array $outputIntents, array $catalog, array $trailerIds, array $encryption): array
    {
        $result = [
            'source' => [],
            'xmp' => $xmp,
            'info' => $info,
            'output_intents' => $outputIntents,
        ];

        if ($encryption !== []) {
            $result['source'][] = 'encryption';
            $result['encryption'] = $encryption;
        }
        if ($xmp !== []) {
            $result['source'][] = 'xmp';
        }
        if ($info !== []) {
            $result['source'][] = 'info';
        }
        if ($catalog !== []) {
            $result['source'][] = 'catalog';
            $result['catalog'] = $catalog;
        }
        if ($outputIntents !== []) {
            $result['source'][] = 'output_intents';
        }
        if ($trailerIds !== []) {
            $result['source'][] = 'trailer_id';
            $result['trailer_ids'] = $trailerIds;
            $fingerprint = $trailerIds['permanent']['sha256'] ?? null;
            if (is_string($fingerprint) && $fingerprint !== '') {
                $result['document_fingerprint'] = $fingerprint;
                $result['document_fingerprint_source'] = 'trailer_id_permanent';
            }
        }

        foreach (['title', 'description', 'creator_tool', 'producer', 'created_at', 'modified_at', 'metadata_date'] as $field) {
            $value = $xmp[$field] ?? $this->infoField($info, $field);
            if (is_string($value) && $value !== '') {
                $result[$field] = $value;
                if (in_array($field, ['created_at', 'modified_at', 'metadata_date'], true)) {
                    $normalized = $this->normalizedDateTimeUtc($value);
                    if ($normalized !== null) {
                        $result[$field . '_utc'] = $normalized;
                    }
                }
            }
        }

        $infoReview = $info['review'] ?? null;
        if (is_array($infoReview) && $infoReview !== []) {
            $result['trailer_info_review'] = $infoReview;
        }

        $authors = $xmp['authors'] ?? $this->authorsFromInfo($info);
        if (is_array($authors) && $authors !== []) {
            $result['authors'] = array_values($authors);
        }

        $keywords = $xmp['keywords'] ?? $this->keywordsFromInfo($info);
        if (is_array($keywords) && $keywords !== []) {
            $result['keywords'] = array_values($keywords);
        }

        $pdfaExtensionSchemas = $xmp['pdfa_extension_schemas'] ?? null;
        if (is_array($pdfaExtensionSchemas) && $pdfaExtensionSchemas !== []) {
            $result['pdfa_extension_schemas'] = array_values($pdfaExtensionSchemas);
        }

        foreach (['language', 'mark_info', 'page_layout', 'page_mode', 'viewer_preferences', 'collection', 'associated_files', 'embedded_files', 'document_name_trees', 'structure_tree', 'document_destinations', 'document_outline', 'document_security_store'] as $field) {
            if (array_key_exists($field, $catalog)) {
                $result[$field] = $catalog[$field];
            }
        }

        $pdfa = $this->pdfaOutputIntentSummary($outputIntents);
        if ($pdfa !== null) {
            $result['pdfa'] = $pdfa;
            $pdfaAssociatedNameTree = $this->pdfaAssociatedNameTreeMetadata($result['embedded_files'] ?? [], $pdfa);
            if ($pdfaAssociatedNameTree !== []) {
                $result['pdfa_associated_name_tree'] = $pdfaAssociatedNameTree;
            }
            $pdfaAssociatedFiles = $this->pdfaAssociatedCatalogFilesMetadata($result['associated_files'] ?? [], $pdfa);
            if ($pdfaAssociatedFiles !== []) {
                $result['pdfa_associated_files'] = $pdfaAssociatedFiles;
            }
            $pdfaXmpAssociatedSchema = $this->pdfaXmpAssociatedSchemaMetadata(
                $result['pdfa_extension_schemas'] ?? [],
                $result['pdfa_associated_name_tree'] ?? [],
                $result['pdfa_associated_files'] ?? []
            );
            if ($pdfaXmpAssociatedSchema !== []) {
                $result['pdfa_xmp_associated_schema'] = $pdfaXmpAssociatedSchema;
            }
        }

        return $result;
    }

    /**
     * Root XMP can declare PDF/A extension schemas while the concrete schema
     * payload is supplied as an associated FileSpec in a catalog name tree.
     * Correlate those review surfaces without loading schema payload bytes into
     * document metadata or visible WordPress content.
     *
     * @param mixed $schemas
     * @param mixed ...$associatedSummaries
     * @return array<string, mixed>
     */
    private function pdfaXmpAssociatedSchemaMetadata(mixed $schemas, mixed ...$associatedSummaries): array
    {
        if (!is_array($schemas) || $schemas === []) {
            return [];
        }

        $schemaRows = [];
        foreach ($schemas as $schema) {
            if (is_array($schema) && $schema !== []) {
                $schemaRows[] = $schema;
            }
        }
        if ($schemaRows === []) {
            return [];
        }

        $schemaFiles = [];
        foreach ($associatedSummaries as $summary) {
            foreach ($this->pdfaAssociatedSchemaFileRows($summary) as $file) {
                $schemaFiles[] = $file;
            }
        }
        if ($schemaFiles === []) {
            return [];
        }

        $schemaNames = [];
        $schemaNamespaces = [];
        $schemaPrefixes = [];
        $propertyNames = [];
        foreach ($schemaRows as $schema) {
            $schemaName = $schema['schema'] ?? null;
            if (is_string($schemaName) && $schemaName !== '') {
                $schemaNames[] = $schemaName;
            }

            $schemaNamespace = $schema['namespace_uri'] ?? null;
            if (is_string($schemaNamespace) && $schemaNamespace !== '') {
                $schemaNamespaces[] = $schemaNamespace;
            }

            $schemaPrefix = $schema['prefix'] ?? null;
            if (is_string($schemaPrefix) && $schemaPrefix !== '') {
                $schemaPrefixes[] = $schemaPrefix;
            }

            $properties = $schema['properties'] ?? null;
            if (!is_array($properties)) {
                continue;
            }
            foreach ($properties as $property) {
                if (!is_array($property)) {
                    continue;
                }
                $name = $property['name'] ?? null;
                if (is_string($name) && $name !== '') {
                    $propertyNames[] = $name;
                }
            }
        }

        $fileNames = [];
        $fileSources = [];
        foreach ($schemaFiles as $file) {
            $fileName = $file['name_tree_name'] ?? $file['filename'] ?? $file['name'] ?? null;
            if (is_string($fileName) && $fileName !== '') {
                $fileNames[] = $fileName;
            }

            $source = $file['source'] ?? null;
            if (is_string($source) && $source !== '') {
                $fileSources[] = $source;
            }
        }

        return [
            'source' => 'pdfa_xmp_associated_schema',
            'review_only' => true,
            'payload_included' => false,
            'xmp_schema_count' => count($schemaRows),
            'xmp_schema_names' => $this->uniqueStrings($schemaNames),
            'xmp_schema_namespaces' => $this->uniqueStrings($schemaNamespaces),
            'xmp_schema_prefixes' => $this->uniqueStrings($schemaPrefixes),
            'xmp_schema_property_names' => $this->uniqueStrings($propertyNames),
            'associated_schema_file_count' => count($schemaFiles),
            'associated_schema_file_names' => $this->uniqueStrings($fileNames),
            'associated_schema_file_sources' => $this->uniqueStrings($fileSources),
            'schemas' => $schemaRows,
            'associated_schema_files' => $schemaFiles,
        ];
    }

    /**
     * @param mixed $summary
     * @return list<array<string, mixed>>
     */
    private function pdfaAssociatedSchemaFileRows(mixed $summary): array
    {
        if (!is_array($summary)) {
            return [];
        }

        $entries = $summary['entries'] ?? null;
        if (!is_array($entries)) {
            return [];
        }

        $files = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $relationship = $entry['relationship'] ?? null;
            $relationshipRole = $entry['relationship_role'] ?? null;
            if ($relationship !== 'Schema' && $relationshipRole !== 'schema_definition') {
                continue;
            }

            $file = [
                'source' => $entry['source'] ?? null,
                'review_only' => true,
                'payload_included' => false,
                'relationship' => $relationship,
                'relationship_role' => $relationshipRole,
            ];

            foreach ([
                'name_tree_name',
                'filename',
                'name',
                'description',
                'mime_type',
                'ef_key',
            ] as $key) {
                $value = $entry[$key] ?? null;
                if (is_string($value) && $value !== '') {
                    $file[$key] = $value;
                }
            }

            foreach ([
                'index',
                'name_tree_index',
                'associated_file_index',
                'file_spec_object',
                'embedded_file_object',
            ] as $key) {
                $value = $entry[$key] ?? null;
                if (is_int($value)) {
                    $file[$key] = $value;
                }
            }

            foreach ([
                'payload',
                'xmp_metadata',
                'piece_info_xmp_metadata',
                'attachment_pdfa_output_intents',
                'related_files',
                'provenance_sources',
            ] as $key) {
                $value = $entry[$key] ?? null;
                if (is_array($value) && $value !== []) {
                    $file[$key] = $value;
                }
            }

            $files[] = $file;
        }

        return $files;
    }

    /**
     * PDF/A-associated files are often discoverable through Catalog /Names
     * /EmbeddedFiles rows plus FileSpec /AFRelationship. Summarize those
     * sanitized rows only when a root PDF/A OutputIntent exists.
     *
     * @param mixed $embeddedFiles
     * @param array{has_output_intent: bool, output_condition_identifiers: list<string>, profile_sha256: list<string>} $pdfa
     * @return array<string, mixed>
     */
    private function pdfaAssociatedNameTreeMetadata(mixed $embeddedFiles, array $pdfa): array
    {
        return $this->pdfaAssociatedFilesSummaryMetadata(
            $embeddedFiles,
            $pdfa,
            'pdfa_associated_name_tree',
            ['catalog_names_embedded_files' => true]
        );
    }

    /**
     * Catalog /AF rows are the normative associated-file hook for PDF/A-3
     * source, schema, alternative, and supplement attachments. Summarize them
     * beside the root PDF/A OutputIntent without promoting attachment-local XMP
     * or profile bytes into document roots.
     *
     * @param mixed $associatedFiles
     * @param array{has_output_intent: bool, output_condition_identifiers: list<string>, profile_sha256: list<string>} $pdfa
     * @return array<string, mixed>
     */
    private function pdfaAssociatedCatalogFilesMetadata(mixed $associatedFiles, array $pdfa): array
    {
        return $this->pdfaAssociatedFilesSummaryMetadata(
            $associatedFiles,
            $pdfa,
            'pdfa_associated_files',
            ['catalog_associated_files' => true]
        );
    }

    /**
     * @param mixed $files
     * @param array{has_output_intent: bool, output_condition_identifiers: list<string>, profile_sha256: list<string>} $pdfa
     * @param array<string, true> $allowedSources
     * @return array<string, mixed>
     */
    private function pdfaAssociatedFilesSummaryMetadata(
        mixed $files,
        array $pdfa,
        string $source,
        array $allowedSources
    ): array
    {
        if (!is_array($files) || $files === [] || ($pdfa['has_output_intent'] ?? false) !== true) {
            return [];
        }

        $entries = [];
        foreach ($files as $file) {
            if (!is_array($file)) {
                continue;
            }

            $entry = $this->pdfaAssociatedFileEntry($file, count($entries), $allowedSources);
            if ($entry !== []) {
                $entries[] = $entry;
            }
        }

        if ($entries === []) {
            return [];
        }

        $names = [];
        $filenames = [];
        $relationships = [];
        $relationshipRoles = [];
        $attachmentPdfaIdentifiers = [];
        foreach ($entries as $entry) {
            $name = $entry['name_tree_name'] ?? $entry['name'] ?? null;
            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
            $filename = $entry['filename'] ?? null;
            if (is_string($filename) && $filename !== '') {
                $filenames[] = $filename;
            }
            $relationship = $entry['relationship'] ?? null;
            if (is_string($relationship) && $relationship !== '') {
                $relationships[] = $relationship;
            }
            $relationshipRole = $entry['relationship_role'] ?? null;
            if (is_string($relationshipRole) && $relationshipRole !== '') {
                $relationshipRoles[] = $relationshipRole;
            }

            $attachmentPdfa = $entry['attachment_pdfa_output_intents']['output_condition_identifiers'] ?? null;
            $pieceInfoPdfa = $entry['piece_info_pdfa_output_intents']['output_condition_identifiers'] ?? null;
            foreach ([$attachmentPdfa, $pieceInfoPdfa] as $identifiers) {
                if (!is_array($identifiers)) {
                    continue;
                }

                foreach ($identifiers as $identifier) {
                    if (is_string($identifier) && $identifier !== '') {
                        $attachmentPdfaIdentifiers[] = $identifier;
                    }
                }
            }
        }

        $metadata = [
            'source' => $source,
            'review_only' => true,
            'payload_included' => false,
            'root_has_output_intent' => true,
            'root_output_condition_identifiers' => $pdfa['output_condition_identifiers'],
            'root_profile_sha256' => $pdfa['profile_sha256'],
            'count' => count($entries),
            'names' => $this->uniqueStrings($names),
            'filenames' => $this->uniqueStrings($filenames),
            'relationships' => $this->uniqueStrings($relationships),
            'relationship_roles' => $this->uniqueStrings($relationshipRoles),
            'entries' => $entries,
        ];

        $attachmentPdfaIdentifiers = $this->uniqueStrings($attachmentPdfaIdentifiers);
        if ($attachmentPdfaIdentifiers !== []) {
            $metadata['has_attachment_pdfa_output_intent'] = true;
            $metadata['attachment_output_condition_identifiers'] = $attachmentPdfaIdentifiers;
        } else {
            $metadata['has_attachment_pdfa_output_intent'] = false;
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $file
     * @param array<string, true> $allowedSources
     * @return array<string, mixed>
     */
    private function pdfaAssociatedFileEntry(array $file, int $index, array $allowedSources): array
    {
        $source = $file['source'] ?? null;
        if (!is_string($source) || !isset($allowedSources[$source])) {
            return [];
        }

        $relationship = $file['relationship'] ?? null;
        if (!is_string($relationship) || $relationship === '') {
            return [];
        }

        $provenance = $file['provenance_review'] ?? [];
        $provenance = is_array($provenance) ? $provenance : [];
        $entry = [
            'source' => $source,
            'review_only' => true,
            'payload_included' => false,
            'index' => $index,
            'relationship' => $relationship,
            'relationship_role' => is_string($provenance['relationship_role'] ?? null)
                ? $provenance['relationship_role']
                : (self::ASSOCIATED_FILE_RELATIONSHIP_ROLES[$relationship] ?? 'unrecognized'),
            'relationship_status' => is_string($provenance['relationship_status'] ?? null)
                ? $provenance['relationship_status']
                : (array_key_exists($relationship, self::ASSOCIATED_FILE_RELATIONSHIP_ROLES)
                    ? 'standard_pdf_associated_file_relationship'
                    : 'unrecognized_pdf_associated_file_relationship'),
        ];

        foreach ([
            'name_tree_name',
            'filename',
            'name',
            'description',
            'mime_type',
            'ef_key',
            'language',
        ] as $key) {
            $value = $file[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $entry[$key] = $value;
            }
        }

        foreach ([
            'name_tree_index',
            'file_spec_object',
            'embedded_file_object',
            'size',
            'declared_size',
            'associated_file_index',
        ] as $key) {
            $value = $file[$key] ?? null;
            if (is_int($value)) {
                $entry[$key] = $value;
            }
        }

        $payload = $provenance['payload'] ?? $this->associatedFilePayloadProvenance($file);
        if (is_array($payload) && $payload !== []) {
            $entry['payload'] = $payload;
        }

        foreach ([
            'xmp_metadata' => 'xmp_metadata',
            'piece_info_xmp_metadata' => 'piece_info_xmp_metadata',
            'piece_info_private_streams' => 'piece_info_private_streams',
            'piece_info_pdfa_output_intents' => 'piece_info_pdfa_output_intents',
            'pdfa_output_intents' => 'attachment_pdfa_output_intents',
            'related_files' => 'related_files',
        ] as $sourceKey => $targetKey) {
            $value = $provenance[$sourceKey] ?? null;
            if (is_array($value) && $value !== []) {
                $entry[$targetKey] = $value;
            }
        }

        $sources = $provenance['sources'] ?? null;
        if (is_array($sources)) {
            $sourceValues = [];
            foreach ($sources as $source) {
                if (is_string($source) && $source !== '') {
                    $sourceValues[] = $source;
                }
            }
            if ($sourceValues !== []) {
                $entry['provenance_sources'] = $this->uniqueStrings($sourceValues);
            }
        }

        return $entry;
    }

    /**
     * @param array<string, string> $info
     */
    private function infoField(array $info, string $field): ?string
    {
        return match ($field) {
            'title' => $info['Title'] ?? null,
            'description' => $info['Subject'] ?? null,
            'creator_tool' => $info['Creator'] ?? null,
            'producer' => $info['Producer'] ?? null,
            'created_at' => $info['CreationDate'] ?? null,
            'modified_at' => $info['ModDate'] ?? null,
            default => null,
        };
    }

    /**
     * @param array<string, string> $info
     * @return list<string>
     */
    private function authorsFromInfo(array $info): array
    {
        $author = $info['Author'] ?? '';
        if ($author === '') {
            return [];
        }

        return $this->cleanList(preg_split('/\s*;\s*/', $author) ?: []);
    }

    /**
     * @param array<string, string> $info
     * @return list<string>
     */
    private function keywordsFromInfo(array $info): array
    {
        $keywords = $info['Keywords'] ?? '';
        if ($keywords === '') {
            return [];
        }

        return $this->splitKeywords($keywords);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseXmpPacket(string $xml): array
    {
        $xml = trim($xml, " \t\r\n");
        if ($xml === '' || !$this->looksLikeXmlPacket($xml)) {
            return [];
        }

        foreach ($this->xmpXmlCandidates($xml) as $candidate) {
            if ($this->xmpXmlCandidateUnsafeMarkup($candidate['xml']) !== []) {
                continue;
            }

            $previous = libxml_use_internal_errors(true);
            $document = new DOMDocument();
            $loaded = $document->loadXML($candidate['xml'], LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            if (!$loaded) {
                continue;
            }

            $metadata = $this->metadataFromXmpDocument($document);
            if ($metadata === []) {
                continue;
            }

            $metadata['packet_encoding'] = $candidate['packet_encoding'];
            if ($candidate['decoded_to_utf8']) {
                $metadata['decoded_to_utf8'] = true;
            }
            if ($candidate['encoding_fallback']) {
                $metadata['encoding_fallback'] = true;
            }
            if ($candidate['packet_boundary_applied']) {
                $metadata['packet_boundary_applied'] = true;
            }

            return $metadata;
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataFromXmpDocument(DOMDocument $document): array
    {
        $metadata = [];

        foreach ([
            'title' => [self::NS_DC, 'title', true],
            'description' => [self::NS_DC, 'description', true],
            'creator_tool' => [self::NS_XMP, 'CreatorTool', false],
            'producer' => [self::NS_PDF, 'Producer', false],
            'created_at' => [self::NS_XMP, 'CreateDate', false],
            'modified_at' => [self::NS_XMP, 'ModifyDate', false],
            'metadata_date' => [self::NS_XMP, 'MetadataDate', false],
        ] as $field => $spec) {
            [$namespace, $localName, $preferAlt] = $spec;
            $value = $this->xmpSingleValue($document, $namespace, $localName, $preferAlt);
            if ($value !== null) {
                $metadata[$field] = $value;
            }
        }

        $authors = $this->xmpListValues($document, self::NS_DC, 'creator');
        if ($authors !== []) {
            $metadata['authors'] = $authors;
        }

        $keywords = $this->xmpListValues($document, self::NS_DC, 'subject');
        if ($keywords === []) {
            $keywordsText = $this->xmpSingleValue($document, self::NS_PDF, 'Keywords', false);
            $keywords = $keywordsText === null ? [] : $this->splitKeywords($keywordsText);
        }
        if ($keywords !== []) {
            $metadata['keywords'] = $keywords;
        }

        $pdfaExtensionSchemas = $this->xmpPdfaExtensionSchemas($document);
        if ($pdfaExtensionSchemas !== []) {
            $metadata['pdfa_extension_schemas'] = $pdfaExtensionSchemas;
        }

        return $metadata;
    }

    /**
     * PDF/A extension schemas in root XMP are document metadata. Keep them as
     * structured review rows so schema declarations can be correlated with
     * associated /Schema FileSpec attachments without exposing raw XMP bytes.
     *
     * @return list<array<string, mixed>>
     */
    private function xmpPdfaExtensionSchemas(DOMDocument $document): array
    {
        $schemas = [];
        foreach ($document->getElementsByTagNameNS(self::NS_PDFA_EXTENSION, 'schemas') as $schemasElement) {
            if (!$schemasElement instanceof DOMElement) {
                continue;
            }

            foreach ($this->xmpRdfCollectionItems($schemasElement) as $schemaItem) {
                $schema = $this->xmpPdfaExtensionSchemaRow($schemaItem);
                if ($schema !== []) {
                    $schemas[] = $schema;
                }
            }
        }

        return $schemas;
    }

    /**
     * @return array<string, mixed>
     */
    private function xmpPdfaExtensionSchemaRow(DOMElement $schemaItem): array
    {
        $row = [
            'source' => 'xmp_pdfa_extension_schema',
            'review_only' => true,
            'payload_included' => false,
        ];

        foreach ([
            'schema' => [self::NS_PDFA_SCHEMA, 'schema'],
            'namespace_uri' => [self::NS_PDFA_SCHEMA, 'namespaceURI'],
            'prefix' => [self::NS_PDFA_SCHEMA, 'prefix'],
        ] as $key => $spec) {
            [$namespace, $localName] = $spec;
            $value = $this->xmpElementValue($schemaItem, $namespace, $localName);
            if ($value !== null && $value !== '') {
                $row[$key] = $value;
            }
        }

        $properties = [];
        foreach ($this->xmpChildElements($schemaItem, self::NS_PDFA_SCHEMA, 'property') as $propertyElement) {
            foreach ($this->xmpRdfCollectionItems($propertyElement) as $propertyItem) {
                $property = $this->xmpPdfaExtensionPropertyRow($propertyItem);
                if ($property !== []) {
                    $properties[] = $property;
                }
            }
        }

        if ($properties !== []) {
            $propertyNames = [];
            foreach ($properties as $property) {
                $name = $property['name'] ?? null;
                if (is_string($name) && $name !== '') {
                    $propertyNames[] = $name;
                }
            }

            $row['property_count'] = count($properties);
            $row['property_names'] = $this->uniqueStrings($propertyNames);
            $row['properties'] = $properties;
        } else {
            $row['property_count'] = 0;
            $row['property_names'] = [];
        }

        return count($row) > 5 ? $row : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function xmpPdfaExtensionPropertyRow(DOMElement $propertyItem): array
    {
        $row = [];
        foreach ([
            'name' => [self::NS_PDFA_PROPERTY, 'name'],
            'value_type' => [self::NS_PDFA_PROPERTY, 'valueType'],
            'category' => [self::NS_PDFA_PROPERTY, 'category'],
            'description' => [self::NS_PDFA_PROPERTY, 'description'],
        ] as $key => $spec) {
            [$namespace, $localName] = $spec;
            $value = $this->xmpElementValue($propertyItem, $namespace, $localName);
            if ($value !== null && $value !== '') {
                $row[$key] = $value;
            }
        }

        return $row;
    }

    private function xmpElementValue(DOMElement $element, string $namespace, string $localName): ?string
    {
        if ($element->hasAttributeNS($namespace, $localName)) {
            return $this->cleanText($element->getAttributeNS($namespace, $localName));
        }

        foreach ($this->xmpChildElements($element, $namespace, $localName) as $child) {
            $value = $this->xmpQualifiedTextValue($child);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, true> $seenResourceIds
     */
    private function xmpQualifiedTextValue(DOMElement $element, array $seenResourceIds = []): ?string
    {
        if ($element->hasAttributeNS(self::NS_RDF, 'value')) {
            $value = $this->cleanText($element->getAttributeNS(self::NS_RDF, 'value'));
            if ($value !== null) {
                return $value;
            }
        }

        foreach ($this->xmpChildElements($element, self::NS_RDF, 'value') as $valueElement) {
            $value = $this->xmpQualifiedTextValue($valueElement, $seenResourceIds);
            if ($value !== null) {
                return $value;
            }
        }

        foreach ($this->xmpChildElements($element, self::NS_RDF, 'Description') as $description) {
            $value = $this->xmpQualifiedTextValue($description, $seenResourceIds);
            if ($value !== null) {
                return $value;
            }
        }

        $value = $this->xmpResourceReferenceTextValue($element, $seenResourceIds);
        if ($value !== null) {
            return $value;
        }

        return $this->cleanText($element->textContent);
    }

    /**
     * XMP writers sometimes store scalar values in same-packet RDF resource
     * nodes. Resolve only fragment-local references and prefer rdf:value so
     * private qualifiers do not become document metadata.
     *
     * @param array<string, true> $seenResourceIds
     */
    private function xmpResourceReferenceTextValue(DOMElement $element, array $seenResourceIds): ?string
    {
        $target = $this->xmpResourceReferenceTargetElement($element, $seenResourceIds);
        if ($target === null) {
            return null;
        }

        if ($target->hasAttributeNS(self::NS_RDF, 'value')) {
            $value = $this->cleanText($target->getAttributeNS(self::NS_RDF, 'value'));
            if ($value !== null) {
                return $value;
            }
        }

        foreach ($this->xmpChildElements($target, self::NS_RDF, 'value') as $valueElement) {
            $value = $this->xmpQualifiedTextValue($valueElement, $seenResourceIds);
            if ($value !== null) {
                return $value;
            }
        }

        foreach ($this->xmpChildElements($target, self::NS_RDF, 'Description') as $description) {
            $value = $this->xmpQualifiedTextValue($description, $seenResourceIds);
            if ($value !== null) {
                return $value;
            }
        }

        $value = $this->xmpResourceReferenceTextValue($target, $seenResourceIds);
        if ($value !== null) {
            return $value;
        }

        if ($this->xmpElementHasElementChildren($target)) {
            return null;
        }

        return $this->cleanText($target->textContent);
    }

    /**
     * @return list<DOMElement>
     */
    private function xmpChildElements(DOMElement $element, ?string $namespace = null, ?string $localName = null): array
    {
        $children = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }
            if ($namespace !== null && $child->namespaceURI !== $namespace) {
                continue;
            }
            if ($localName !== null && $child->localName !== $localName) {
                continue;
            }

            $children[] = $child;
        }

        return $children;
    }

    /**
     * @return list<DOMElement>
     */
    private function xmpRdfCollectionItems(DOMElement $element): array
    {
        $directItems = $this->xmpChildElements($element, self::NS_RDF, 'li');
        if ($this->xmpRdfCollectionItemsHaveValues($directItems)) {
            return $directItems;
        }

        foreach (['Bag', 'Seq', 'Alt'] as $containerName) {
            foreach ($this->xmpChildElements($element, self::NS_RDF, $containerName) as $container) {
                $items = $this->xmpRdfContainerItems($container);
                if ($this->xmpRdfCollectionItemsHaveValues($items)) {
                    return $items;
                }
            }
        }

        foreach ($this->xmpChildElements($element, self::NS_RDF, 'Description') as $description) {
            $items = $this->xmpRdfResourceWrappedCollectionItems($description);
            if ($this->xmpRdfCollectionItemsHaveValues($items)) {
                return $items;
            }
        }

        $items = $this->xmpRdfResourceReferenceCollectionItems($element);
        if ($this->xmpRdfCollectionItemsHaveValues($items)) {
            return $items;
        }

        return [];
    }

    /**
     * Empty array placeholders are common in producer-repaired XMP. Do not let
     * them block a later RDF resource wrapper that carries the actual values.
     *
     * @param list<DOMElement> $items
     */
    private function xmpRdfCollectionItemsHaveValues(array $items): bool
    {
        foreach ($items as $item) {
            if ($this->xmpQualifiedTextValue($item) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<DOMElement>
     */
    private function xmpRdfContainerItems(DOMElement $container): array
    {
        $directItems = $this->xmpChildElements($container, self::NS_RDF, 'li');
        if ($this->xmpRdfCollectionItemsHaveValues($directItems)) {
            return $directItems;
        }

        foreach ($this->xmpChildElements($container, self::NS_RDF, 'Description') as $description) {
            $items = $this->xmpRdfResourceWrappedCollectionItems($description);
            if ($this->xmpRdfCollectionItemsHaveValues($items)) {
                return $items;
            }
        }

        return [];
    }

    /**
     * @return list<DOMElement>
     */
    private function xmpRdfResourceWrappedCollectionItems(DOMElement $description): array
    {
        $directItems = $this->xmpChildElements($description, self::NS_RDF, 'li');
        if ($this->xmpRdfCollectionItemsHaveValues($directItems)) {
            return $directItems;
        }

        foreach (['Bag', 'Seq', 'Alt'] as $containerName) {
            foreach ($this->xmpChildElements($description, self::NS_RDF, $containerName) as $container) {
                $items = $this->xmpRdfContainerItems($container);
                if ($this->xmpRdfCollectionItemsHaveValues($items)) {
                    return $items;
                }
            }
        }

        return [];
    }

    /**
     * @param array<string, true> $seenResourceIds
     * @return list<DOMElement>
     */
    private function xmpRdfResourceReferenceCollectionItems(DOMElement $element, array $seenResourceIds = []): array
    {
        $target = $this->xmpResourceReferenceTargetElement($element, $seenResourceIds);
        if ($target === null) {
            return [];
        }

        $directItems = $this->xmpChildElements($target, self::NS_RDF, 'li');
        if ($this->xmpRdfCollectionItemsHaveValues($directItems)) {
            return $directItems;
        }

        foreach (['Bag', 'Seq', 'Alt'] as $containerName) {
            foreach ($this->xmpChildElements($target, self::NS_RDF, $containerName) as $container) {
                $items = $this->xmpRdfContainerItems($container);
                if ($this->xmpRdfCollectionItemsHaveValues($items)) {
                    return $items;
                }
            }
        }

        foreach ($this->xmpChildElements($target, self::NS_RDF, 'Description') as $description) {
            $items = $this->xmpRdfResourceWrappedCollectionItems($description);
            if ($this->xmpRdfCollectionItemsHaveValues($items)) {
                return $items;
            }
        }

        return $this->xmpRdfResourceReferenceCollectionItems($target, $seenResourceIds);
    }

    /**
     * @param array<string, true> $seenResourceIds
     */
    private function xmpResourceReferenceTargetElement(DOMElement $element, array &$seenResourceIds): ?DOMElement
    {
        if (count($seenResourceIds) >= 8) {
            return null;
        }

        if ($element->hasAttributeNS(self::NS_RDF, 'resource')) {
            $id = $this->xmpFragmentResourceId($element->getAttributeNS(self::NS_RDF, 'resource'));
            if ($id === null || isset($seenResourceIds['resource:' . $id])) {
                return null;
            }

            $seenResourceIds['resource:' . $id] = true;

            return $this->xmpDocumentLevelResourceTargetElement($element, $id);
        }

        if ($element->hasAttributeNS(self::NS_RDF, 'nodeID')) {
            $id = $this->xmpBlankNodeId($element->getAttributeNS(self::NS_RDF, 'nodeID'));
            if ($id === null || isset($seenResourceIds['node:' . $id])) {
                return null;
            }

            $seenResourceIds['node:' . $id] = true;

            return $this->xmpDocumentLevelNodeIdTargetElement($element, $id);
        }

        return null;
    }

    private function xmpFragmentResourceId(string $resource): ?string
    {
        $resource = trim($resource);
        if (!str_starts_with($resource, '#')) {
            return null;
        }

        $id = substr($resource, 1);
        if ($id === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_.:-]*$/', $id) !== 1) {
            return null;
        }

        return $id;
    }

    private function xmpBlankNodeId(string $nodeId): ?string
    {
        $nodeId = trim($nodeId);
        if ($nodeId === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $nodeId) !== 1) {
            return null;
        }

        return $nodeId;
    }

    private function xmpDocumentLevelResourceTargetElement(DOMElement $source, string $id): ?DOMElement
    {
        $document = $source->ownerDocument;
        if (!$document instanceof DOMDocument) {
            return null;
        }

        foreach ($document->getElementsByTagNameNS(self::NS_RDF, 'RDF') as $rdf) {
            if (!$rdf instanceof DOMElement || !$this->isDocumentLevelXmpRdfElement($rdf)) {
                continue;
            }

            foreach ($rdf->childNodes as $child) {
                if (!$child instanceof DOMElement || !$this->xmpElementMatchesResourceId($child, $id)) {
                    continue;
                }

                return $child;
            }
        }

        return null;
    }

    private function xmpDocumentLevelNodeIdTargetElement(DOMElement $source, string $id): ?DOMElement
    {
        $document = $source->ownerDocument;
        if (!$document instanceof DOMDocument) {
            return null;
        }

        foreach ($document->getElementsByTagNameNS(self::NS_RDF, 'RDF') as $rdf) {
            if (!$rdf instanceof DOMElement || !$this->isDocumentLevelXmpRdfElement($rdf)) {
                continue;
            }

            foreach ($rdf->childNodes as $child) {
                if (!$child instanceof DOMElement || !$this->xmpElementMatchesNodeId($child, $id)) {
                    continue;
                }

                return $child;
            }
        }

        return null;
    }

    private function xmpElementMatchesResourceId(DOMElement $element, string $id): bool
    {
        if ($element->hasAttributeNS(self::NS_RDF, 'ID') && trim($element->getAttributeNS(self::NS_RDF, 'ID')) === $id) {
            return true;
        }

        if ($element->hasAttributeNS(self::NS_XML, 'id') && trim($element->getAttributeNS(self::NS_XML, 'id')) === $id) {
            return true;
        }

        return $element->hasAttributeNS(self::NS_RDF, 'about')
            && trim($element->getAttributeNS(self::NS_RDF, 'about')) === '#' . $id;
    }

    private function xmpElementMatchesNodeId(DOMElement $element, string $id): bool
    {
        return $element->hasAttributeNS(self::NS_RDF, 'nodeID')
            && trim($element->getAttributeNS(self::NS_RDF, 'nodeID')) === $id;
    }

    private function xmpElementHasElementChildren(DOMElement $element): bool
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<DOMElement>
     */
    private function xmpTopLevelDescriptions(DOMDocument $document): array
    {
        $nodes = [];
        foreach ($document->getElementsByTagNameNS(self::NS_RDF, 'RDF') as $rdf) {
            if (!$rdf instanceof DOMElement || !$this->isDocumentLevelXmpRdfElement($rdf)) {
                continue;
            }

            foreach ($rdf->childNodes as $child) {
                if (!$child instanceof DOMElement) {
                    continue;
                }

                if ($child->namespaceURI === self::NS_RDF) {
                    if ($child->localName === 'Description') {
                        if ($this->xmpElementIsNonDocumentResource($child)) {
                            continue;
                        }

                        $nodes[] = $child;
                    }
                    continue;
                }

                if (is_string($child->namespaceURI) && $child->namespaceURI !== '') {
                    if ($this->xmpElementIsNonDocumentResource($child)) {
                        continue;
                    }

                    $nodes[] = $child;
                }
            }
        }

        return $nodes;
    }

    private function isDocumentLevelXmpRdfElement(DOMElement $rdf): bool
    {
        $parent = $rdf->parentNode;
        if ($parent instanceof DOMDocument) {
            return true;
        }

        return $parent instanceof DOMElement
            && $parent->namespaceURI === 'adobe:ns:meta/'
            && $parent->localName === 'xmpmeta'
            && $parent->parentNode instanceof DOMDocument;
    }

    private function xmpElementIsNonDocumentResource(DOMElement $element): bool
    {
        if (
            $element->hasAttributeNS(self::NS_RDF, 'ID')
            || $element->hasAttributeNS(self::NS_XML, 'id')
            || $element->hasAttributeNS(self::NS_RDF, 'nodeID')
        ) {
            return true;
        }

        if (!$element->hasAttributeNS(self::NS_RDF, 'about')) {
            return false;
        }

        $about = trim($element->getAttributeNS(self::NS_RDF, 'about'));

        return $about !== '';
    }

    /**
     * @return list<DOMElement>
     */
    private function xmpTopLevelPropertyElements(DOMDocument $document, string $namespace, string $localName): array
    {
        $properties = [];
        foreach ($this->xmpTopLevelDescriptions($document) as $description) {
            foreach ($this->xmpChildElements($description, $namespace, $localName) as $child) {
                $properties[] = $child;
            }
        }

        return $properties;
    }

    private function looksLikeXmlPacket(string $xml): bool
    {
        return str_contains($xml, '<')
            || str_starts_with($xml, "\xfe\xff")
            || str_starts_with($xml, "\xff\xfe")
            || str_starts_with($xml, "\xef\xbb\xbf");
    }

    /**
     * @return list<array{xml: string, packet_encoding: string, encoding_fallback: bool, decoded_to_utf8: bool, packet_boundary_applied: bool}>
     */
    private function xmpXmlCandidates(string $xml): array
    {
        $candidates = [];

        if (str_starts_with($xml, "\xef\xbb\xbf")) {
            $this->addXmpXmlCandidate($candidates, substr($xml, 3), 'UTF-8-BOM', false, true);
        }

        if (str_starts_with($xml, "\xfe\xff")) {
            $candidate = $this->convertedXmpXmlCandidate(substr($xml, 2), 'UTF-16BE', false);
            if ($candidate !== null) {
                $this->addXmpXmlCandidate($candidates, $candidate['xml'], $candidate['packet_encoding'], false, true);
            } else {
                $this->addXmpXmlCandidate($candidates, $xml, 'UTF-16BE', false, false);
            }

            return $candidates;
        }

        if (str_starts_with($xml, "\xff\xfe")) {
            $candidate = $this->convertedXmpXmlCandidate(substr($xml, 2), 'UTF-16LE', false);
            if ($candidate !== null) {
                $this->addXmpXmlCandidate($candidates, $candidate['xml'], $candidate['packet_encoding'], false, true);
            } else {
                $this->addXmpXmlCandidate($candidates, $xml, 'UTF-16LE', false, false);
            }

            return $candidates;
        }

        $sniffedUtf16Encoding = $this->sniffBomlessUtf16XmlEncoding($xml);
        if ($sniffedUtf16Encoding !== null) {
            $candidate = $this->convertedXmpXmlCandidate($xml, $sniffedUtf16Encoding, false);
            if ($candidate !== null) {
                $this->addXmpXmlCandidate($candidates, $candidate['xml'], $candidate['packet_encoding'], false, true);
                return $candidates;
            }
        }

        foreach ($this->xmpPacketContentCandidates($xml) as $packetXml) {
            $packetDeclaredEncoding = $this->declaredXmlEncoding($packetXml);
            if ($packetDeclaredEncoding === null || $this->isUtf8EncodingName($packetDeclaredEncoding)) {
                continue;
            }

            $candidate = $this->convertedXmpXmlCandidate($packetXml, $packetDeclaredEncoding, false);
            if ($candidate !== null) {
                $this->addXmpPacketXmlCandidate($candidates, $candidate['xml'], $candidate['packet_encoding'], false, true);
            }
        }

        $declaredEncoding = $this->declaredXmlEncoding($xml);
        $this->addXmpXmlCandidate($candidates, $xml, $declaredEncoding ?? 'UTF-8', false, false);

        if ($declaredEncoding !== null && !$this->isUtf8EncodingName($declaredEncoding)) {
            $candidate = $this->convertedXmpXmlCandidate($xml, $declaredEncoding, false);
            if ($candidate !== null) {
                $this->addXmpXmlCandidate($candidates, $candidate['xml'], $candidate['packet_encoding'], false, true);
            }
        }

        if ($declaredEncoding === null && !$this->isValidUtf8($xml)) {
            foreach (['Windows-1252', 'ISO-8859-1'] as $fallbackEncoding) {
                $candidate = $this->convertedXmpXmlCandidate($xml, $fallbackEncoding, true);
                if ($candidate !== null) {
                    $this->addXmpXmlCandidate($candidates, $candidate['xml'], $candidate['packet_encoding'], true, true);
                }
            }
        }

        return $candidates;
    }

    /**
     * @param list<array{xml: string, packet_encoding: string, encoding_fallback: bool, decoded_to_utf8: bool, packet_boundary_applied: bool}> $candidates
     */
    private function addXmpXmlCandidate(
        array &$candidates,
        string $xml,
        string $packetEncoding,
        bool $encodingFallback,
        bool $decodedToUtf8,
        bool $packetBoundaryApplied = false
    ): void
    {
        if ($xml === '') {
            return;
        }

        foreach ($candidates as $candidate) {
            if ($candidate['xml'] === $xml) {
                return;
            }
        }

        $candidates[] = [
            'xml' => $xml,
            'packet_encoding' => $this->canonicalXmlEncodingLabel($packetEncoding),
            'encoding_fallback' => $encodingFallback,
            'decoded_to_utf8' => $decodedToUtf8,
            'packet_boundary_applied' => $packetBoundaryApplied,
        ];

        if ($packetBoundaryApplied) {
            return;
        }

        foreach ($this->boundedXmpXmlRootCandidates($xml) as $boundedXml) {
            $this->addXmpXmlCandidate($candidates, $boundedXml, $packetEncoding, $encodingFallback, $decodedToUtf8, true);
        }
    }

    /**
     * XMP packet begin/end markers can wrap an XML declaration. Decode that
     * packet body using the declaration before the undeclared legacy fallback
     * path, and still mark the parsed root as packet-bounded metadata.
     *
     * @param list<array{xml: string, packet_encoding: string, encoding_fallback: bool, decoded_to_utf8: bool, packet_boundary_applied: bool}> $candidates
     */
    private function addXmpPacketXmlCandidate(
        array &$candidates,
        string $xml,
        string $packetEncoding,
        bool $encodingFallback,
        bool $decodedToUtf8
    ): void
    {
        $this->addXmpXmlCandidate($candidates, $xml, $packetEncoding, $encodingFallback, $decodedToUtf8, true);

        foreach ($this->boundedXmpXmlRootCandidatesFromXml($xml) as $boundedXml) {
            $this->addXmpXmlCandidate($candidates, $boundedXml, $packetEncoding, $encodingFallback, $decodedToUtf8, true);
        }
    }

    /**
     * XMP metadata streams commonly include xpacket processing instructions,
     * packet padding, or stale appended bytes. DOMDocument must see a single
     * XML root, so keep the current x:xmpmeta/rdf:RDF root as a fallback
     * candidate before any trailing padding or decoy packet.
     *
     * @return list<string>
     */
    private function boundedXmpXmlRootCandidates(string $xml): array
    {
        $packetCandidates = [];
        foreach ($this->xmpPacketContentCandidates($xml) as $packetXml) {
            $candidates = $this->boundedXmpXmlRootCandidatesFromXml($packetXml);
            if ($candidates !== []) {
                foreach ($candidates as $candidate) {
                    if (!in_array($candidate, $packetCandidates, true)) {
                        $packetCandidates[] = $candidate;
                    }
                }

                if (!$this->xmpXmlRootCandidatesAreOnlyEmptyXmpmetaWrappers($candidates)) {
                    return $packetCandidates;
                }
            }
        }
        if ($packetCandidates !== []) {
            return $packetCandidates;
        }

        return $this->boundedXmpXmlRootCandidatesFromXml($xml);
    }

    /**
     * @param list<string> $candidates
     */
    private function xmpXmlRootCandidatesAreOnlyEmptyXmpmetaWrappers(array $candidates): bool
    {
        if ($candidates === []) {
            return false;
        }

        foreach ($candidates as $candidate) {
            if (!$this->xmpXmlRootCandidateIsEmptyXmpmetaWrapper($candidate)) {
                return false;
            }
        }

        return true;
    }

    private function xmpXmlRootCandidateIsEmptyXmpmetaWrapper(string $xml): bool
    {
        $root = $this->xmlRootStartForLocalName($xml, 'xmpmeta');
        if ($root === null || $root['offset'] !== 0 || !$this->xmpmetaRootDeclaresAdobeNamespace($xml)) {
            return false;
        }

        $openEnd = $this->xmlTagEndOffset($xml, 0);
        if ($openEnd === null) {
            return false;
        }

        $openTag = substr($xml, 0, $openEnd);
        if (str_ends_with(rtrim($openTag), '/>')) {
            return true;
        }

        $closeStart = strrpos($xml, '</');
        if ($closeStart === false || $closeStart < $openEnd) {
            return false;
        }

        $inner = substr($xml, $openEnd, $closeStart - $openEnd);
        $inner = preg_replace('/<!--.*?-->|<\?.*?\?>/s', '', $inner) ?? $inner;

        return trim($inner, " \t\r\n\0") === '';
    }

    /**
     * @return list<string>
     */
    private function boundedXmpXmlRootCandidatesFromXml(string $xml): array
    {
        $candidates = [];
        $offset = 0;
        $sawXmpmetaRoot = false;
        $sawAdobeXmpmetaRoot = false;
        while (true) {
            $entry = $this->boundedXmlRootCandidateEntry($xml, 'xmpmeta', $offset);
            if ($entry === null) {
                if (
                    $offset === 0
                    && $this->xmlWholeRootHasLocalName($xml, 'xmpmeta')
                    && !$this->xmpmetaRootDeclaresAdobeNamespace($xml)
                ) {
                    return [];
                }

                break;
            }

            $sawXmpmetaRoot = true;
            $candidates[] = $entry['xml'];
            $offset = max($entry['end_offset'], $entry['start_offset'] + 1);
            $isAdobeXmpmetaRoot = !$entry['self_closing']
                && $this->xmpmetaRootDeclaresAdobeNamespace($entry['xml']);
            if ($isAdobeXmpmetaRoot) {
                $sawAdobeXmpmetaRoot = true;
            }
            if (
                $isAdobeXmpmetaRoot
                && $this->xmlRootStartForLocalName($entry['xml'], 'RDF') !== null
            ) {
                return $candidates;
            }
        }

        $candidate = $this->boundedXmlRootCandidate($xml, 'RDF', $offset);
        if ($candidate !== null) {
            $candidates[] = $candidate;
        }

        if ($candidate === null && $sawXmpmetaRoot && !$sawAdobeXmpmetaRoot) {
            return [];
        }

        return $candidates;
    }

    /**
     * XMP packet processing instructions define the active packet body. Use
     * complete begin/end pairs before scanning for XML roots so stale preamble
     * XMP-looking roots cannot replace the current metadata packet.
     *
     * @return list<string>
     */
    private function xmpPacketContentCandidates(string $xml): array
    {
        $candidates = [];
        $offset = 0;
        while (true) {
            $begin = $this->xmpPacketInstructionOffset($xml, 'begin', $offset);
            if ($begin === null) {
                break;
            }

            $beginEnd = $this->xmlProcessingInstructionEndOffset($xml, $begin);
            if ($beginEnd === null) {
                break;
            }

            $end = $this->xmpPacketInstructionOffset($xml, 'end', $beginEnd);
            if ($end === null) {
                break;
            }

            $nextBegin = $this->xmpPacketInstructionOffset($xml, 'begin', $beginEnd);
            if (
                $nextBegin !== null
                && $nextBegin < $end
                && !$this->xmpPacketInstructionInsideBoundedXmlRoot($xml, $beginEnd, $nextBegin, $end)
            ) {
                $offset = $nextBegin;
                continue;
            }

            $endEnd = $this->xmlProcessingInstructionEndOffset($xml, $end);
            if ($endEnd === null) {
                break;
            }

            $packet = substr($xml, $beginEnd, $end - $beginEnd);
            if (trim($packet, " \t\r\n\0") !== '') {
                $candidates[] = $packet;
            }

            $offset = $endEnd;
        }

        return $candidates;
    }

    private function xmpPacketInstructionInsideBoundedXmlRoot(
        string $xml,
        int $packetStart,
        int $instructionOffset,
        int $packetEnd
    ): bool {
        foreach (['xmpmeta', 'RDF'] as $localName) {
            for ($offset = $packetStart; $offset < $instructionOffset;) {
                $entry = $this->boundedXmlRootCandidateEntry($xml, $localName, $offset);
                if ($entry === null || $entry['start_offset'] >= $packetEnd) {
                    break;
                }

                if ($instructionOffset < $entry['start_offset']) {
                    break;
                }

                if (
                    $entry['end_offset'] <= $packetEnd
                    && $instructionOffset > $entry['start_offset']
                    && $instructionOffset < $entry['end_offset']
                ) {
                    return true;
                }

                $offset = max($entry['end_offset'], $entry['start_offset'] + 1);
            }
        }

        return false;
    }

    private function xmpPacketInstructionOffset(string $xml, string $kind, int $offset): ?int
    {
        $offset = max(0, $offset);
        $length = strlen($xml);
        while ($offset < $length) {
            $tagStart = strpos($xml, '<', $offset);
            if ($tagStart === false) {
                return null;
            }

            if (str_starts_with(substr($xml, $tagStart, 4), '<!--')) {
                $end = strpos($xml, '-->', $tagStart + 4);
                if ($end === false) {
                    return null;
                }
                $offset = $end + 3;
                continue;
            }

            if (str_starts_with(substr($xml, $tagStart, 9), '<![CDATA[')) {
                $end = strpos($xml, ']]>', $tagStart + 9);
                if ($end === false) {
                    return null;
                }
                $offset = $end + 3;
                continue;
            }

            if (str_starts_with(substr($xml, $tagStart, 2), '<?')) {
                $end = $this->xmlProcessingInstructionEndOffset($xml, $tagStart);
                if ($end === null) {
                    return null;
                }

                $instruction = substr($xml, $tagStart, $end - $tagStart);
                if ($this->xmpPacketInstructionMatchesKind($instruction, $kind)) {
                    return $tagStart;
                }

                $offset = $end;
                continue;
            }

            $end = str_starts_with(substr($xml, $tagStart, 2), '<!')
                ? ($this->xmlMarkupDeclarationEndOffset($xml, $tagStart) ?? $this->xmlTagEndOffset($xml, $tagStart))
                : $this->xmlTagEndOffset($xml, $tagStart);
            if ($end === null) {
                return null;
            }
            $offset = $end;
        }

        return null;
    }

    private function xmpPacketInstructionMatchesKind(string $instruction, string $kind): bool
    {
        if (preg_match('/^<\?\s*xpacket\b/si', $instruction) !== 1) {
            return false;
        }

        $hasBegin = preg_match('/\bbegin\s*=/si', $instruction) === 1;
        $hasTerminalEnd = preg_match('/\bend\s*=\s*([\'"])[rw]\1/si', $instruction) === 1;

        return match ($kind) {
            'begin' => $hasBegin && !$hasTerminalEnd,
            'end' => $hasTerminalEnd && !$hasBegin,
            default => false,
        };
    }

    private function xmpmetaRootDeclaresAdobeNamespace(string $xml): bool
    {
        $root = $this->xmlRootStartForLocalName($xml, 'xmpmeta');
        if ($root === null || $root['offset'] !== 0) {
            return false;
        }

        $openEnd = $this->xmlTagEndOffset($xml, 0);
        if ($openEnd === null) {
            return false;
        }

        $openTag = substr($xml, 0, $openEnd);
        $tagName = $root['tag_name'];
        $colon = strpos($tagName, ':');
        if ($colon === false) {
            return preg_match('/\sxmlns\s*=\s*([\'"])adobe:ns:meta\/\1/i', $openTag) === 1;
        }

        $prefix = preg_quote(substr($tagName, 0, $colon), '/');
        return preg_match('/\sxmlns:' . $prefix . '\s*=\s*([\'"])adobe:ns:meta\/\1/i', $openTag) === 1;
    }

    private function xmlWholeRootHasLocalName(string $xml, string $localName): bool
    {
        $root = $this->xmlRootStartForLocalName($xml, $localName);
        if ($root === null) {
            return false;
        }

        $start = $root['offset'];
        if (trim(substr($xml, 0, $start), " \t\r\n\0") !== '') {
            return false;
        }

        $openEnd = $this->xmlTagEndOffset($xml, $start);
        if ($openEnd === null) {
            return false;
        }

        $openTag = substr($xml, $start, $openEnd - $start);
        $end = str_ends_with(rtrim($openTag), '/>')
            ? $openEnd
            : $this->matchingXmlRootEndOffset($xml, $openEnd, $root['tag_name']);
        if ($end === null) {
            return false;
        }

        return trim(substr($xml, $end), " \t\r\n\0") === '';
    }

    private function boundedXmlRootCandidate(string $xml, string $localName, int $offset = 0): ?string
    {
        $entry = $this->boundedXmlRootCandidateEntry($xml, $localName, $offset);

        return $entry['xml'] ?? null;
    }

    /**
     * @return array{xml: string, start_offset: int, end_offset: int, self_closing: bool}|null
     */
    private function boundedXmlRootCandidateEntry(string $xml, string $localName, int $offset = 0): ?array
    {
        $root = $this->xmlRootStartForLocalName($xml, $localName, $offset);
        if ($root === null) {
            return null;
        }

        $tagName = $root['tag_name'];
        $start = $root['offset'];
        $openEnd = $this->xmlTagEndOffset($xml, $start);
        if ($openEnd === null) {
            return null;
        }

        $openTag = substr($xml, $start, $openEnd - $start);
        if (str_ends_with(rtrim($openTag), '/>')) {
            return [
                'xml' => substr($xml, $start, $openEnd - $start),
                'start_offset' => $start,
                'end_offset' => $openEnd,
                'self_closing' => true,
            ];
        }

        $end = $this->matchingXmlRootEndOffset($xml, $openEnd, $tagName);
        if ($end === null) {
            return null;
        }

        $bounded = substr($xml, $start, $end - $start);
        return $bounded === $xml ? null : [
            'xml' => $bounded,
            'start_offset' => $start,
            'end_offset' => $end,
            'self_closing' => false,
        ];
    }

    /**
     * Raw regex root selection can accidentally select XMP-looking tags inside
     * packet comments or declarations. Walk XML markup tokens before deciding
     * which root fallback DOMDocument should receive.
     *
     * @return array{offset: int, tag_name: string}|null
     */
    private function xmlRootStartForLocalName(string $xml, string $localName, int $offset = 0): ?array
    {
        $offset = max(0, $offset);
        $length = strlen($xml);
        while ($offset < $length) {
            $tagStart = strpos($xml, '<', $offset);
            if ($tagStart === false) {
                return null;
            }

            if (str_starts_with(substr($xml, $tagStart, 4), '<!--')) {
                $end = strpos($xml, '-->', $tagStart + 4);
                if ($end === false) {
                    return null;
                }
                $offset = $end + 3;
                continue;
            }

            if (str_starts_with(substr($xml, $tagStart, 9), '<![CDATA[')) {
                $end = strpos($xml, ']]>', $tagStart + 9);
                if ($end === false) {
                    return null;
                }
                $offset = $end + 3;
                continue;
            }

            if (str_starts_with(substr($xml, $tagStart, 2), '<?')) {
                $end = strpos($xml, '?>', $tagStart + 2);
                if ($end === false) {
                    return null;
                }
                $offset = $end + 2;
                continue;
            }

            if (str_starts_with(substr($xml, $tagStart, 2), '</')) {
                $end = $this->xmlTagEndOffset($xml, $tagStart);
                if ($end === null) {
                    return null;
                }
                $offset = $end;
                continue;
            }

            if (str_starts_with(substr($xml, $tagStart, 2), '<!')) {
                $end = $this->xmlMarkupDeclarationEndOffset($xml, $tagStart)
                    ?? $this->xmlTagEndOffset($xml, $tagStart);
                if ($end === null) {
                    return null;
                }
                $offset = $end;
                continue;
            }

            $tagName = $this->xmlTagNameAt($xml, $tagStart + 1);
            $end = $this->xmlTagEndOffset($xml, $tagStart);
            if ($tagName !== null && $this->xmlTagLocalName($tagName) === $localName) {
                return [
                    'offset' => $tagStart,
                    'tag_name' => $tagName,
                ];
            }

            if ($end === null) {
                return null;
            }
            $offset = $end;
        }

        return null;
    }

    /**
     * XMP packets are document metadata, not a general XML processing surface.
     * DTD/entity declarations can synthesize text that is absent from the PDF
     * metadata stream payload, so skip those candidates before DOM textContent.
     *
     * @return list<string>
     */
    private function xmpXmlCandidateUnsafeMarkup(string $xml): array
    {
        $unsafe = [];
        $offset = 0;
        $length = strlen($xml);
        while ($offset < $length) {
            $tagStart = strpos($xml, '<', $offset);
            if ($tagStart === false) {
                break;
            }

            if (str_starts_with(substr($xml, $tagStart, 4), '<!--')) {
                $end = strpos($xml, '-->', $tagStart + 4);
                if ($end === false) {
                    break;
                }
                $offset = $end + 3;
                continue;
            }

            if (str_starts_with(substr($xml, $tagStart, 9), '<![CDATA[')) {
                $end = strpos($xml, ']]>', $tagStart + 9);
                if ($end === false) {
                    break;
                }
                $offset = $end + 3;
                continue;
            }

            if (str_starts_with(substr($xml, $tagStart, 2), '<?')) {
                $end = strpos($xml, '?>', $tagStart + 2);
                if ($end === false) {
                    break;
                }
                $offset = $end + 2;
                continue;
            }

            if (str_starts_with(substr($xml, $tagStart, 2), '<!')) {
                $end = $this->xmlMarkupDeclarationEndOffset($xml, $tagStart)
                    ?? $this->xmlTagEndOffset($xml, $tagStart);
                if ($end === null) {
                    break;
                }

                $declaration = substr($xml, $tagStart, $end - $tagStart);
                $keyword = $this->xmlMarkupDeclarationKeyword($declaration);
                if ($keyword === 'DOCTYPE') {
                    $unsafe['DOCTYPE'] = true;
                    if (preg_match('/<!\s*ENTITY\b/i', $declaration) === 1) {
                        $unsafe['ENTITY'] = true;
                    }
                } elseif ($keyword === 'ENTITY') {
                    $unsafe['ENTITY'] = true;
                }

                $offset = $end;
                continue;
            }

            $end = $this->xmlTagEndOffset($xml, $tagStart);
            if ($end === null) {
                break;
            }
            $offset = $end;
        }

        return array_keys($unsafe);
    }

    private function xmlMarkupDeclarationKeyword(string $declaration): ?string
    {
        if (preg_match('/^<!\s*([A-Za-z]+)/', $declaration, $match) !== 1) {
            return null;
        }

        return strtoupper($match[1]);
    }

    private function xmlTagLocalName(string $tagName): string
    {
        $colon = strrpos($tagName, ':');
        return $colon === false ? $tagName : substr($tagName, $colon + 1);
    }

    private function matchingXmlRootEndOffset(string $xml, int $offset, string $tagName): ?int
    {
        $depth = 1;
        $length = strlen($xml);
        for ($index = $offset; $index < $length;) {
            $tagStart = strpos($xml, '<', $index);
            if ($tagStart === false) {
                return null;
            }

            if (str_starts_with(substr($xml, $tagStart, 4), '<!--')) {
                $end = strpos($xml, '-->', $tagStart + 4);
                if ($end === false) {
                    return null;
                }
                $index = $end + 3;
                continue;
            }

            if (str_starts_with(substr($xml, $tagStart, 9), '<![CDATA[')) {
                $end = strpos($xml, ']]>', $tagStart + 9);
                if ($end === false) {
                    return null;
                }
                $index = $end + 3;
                continue;
            }

            if (str_starts_with(substr($xml, $tagStart, 2), '<?')) {
                $end = strpos($xml, '?>', $tagStart + 2);
                if ($end === false) {
                    return null;
                }
                $index = $end + 2;
                continue;
            }

            if (str_starts_with(substr($xml, $tagStart, 2), '</')) {
                $name = $this->xmlTagNameAt($xml, $tagStart + 2);
                $end = $this->xmlTagEndOffset($xml, $tagStart);
                if ($end === null) {
                    return null;
                }

                if ($name === $tagName) {
                    $depth--;
                    if ($depth === 0) {
                        return $end;
                    }
                }
                $index = $end;
                continue;
            }

            if (str_starts_with(substr($xml, $tagStart, 2), '<!')) {
                $end = $this->xmlMarkupDeclarationEndOffset($xml, $tagStart)
                    ?? $this->xmlTagEndOffset($xml, $tagStart);
                if ($end === null) {
                    return null;
                }
                $index = $end;
                continue;
            }

            $name = $this->xmlTagNameAt($xml, $tagStart + 1);
            $end = $this->xmlTagEndOffset($xml, $tagStart);
            if ($end === null) {
                return null;
            }

            if ($name === $tagName && !str_ends_with(rtrim(substr($xml, $tagStart, $end - $tagStart)), '/>')) {
                $depth++;
            }
            $index = $end;
        }

        return null;
    }

    private function xmlMarkupDeclarationEndOffset(string $xml, int $offset): ?int
    {
        $quote = null;
        $bracketDepth = 0;
        for ($index = $offset + 2, $length = strlen($xml); $index < $length; $index++) {
            $char = $xml[$index];
            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '[') {
                $bracketDepth++;
                continue;
            }

            if ($char === ']' && $bracketDepth > 0) {
                $bracketDepth--;
                continue;
            }

            if ($char === '>' && $bracketDepth === 0) {
                return $index + 1;
            }
        }

        return null;
    }

    private function xmlTagEndOffset(string $xml, int $offset): ?int
    {
        $quote = null;
        for ($index = $offset + 1, $length = strlen($xml); $index < $length; $index++) {
            $char = $xml[$index];
            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '>') {
                return $index + 1;
            }
        }

        return null;
    }

    private function xmlProcessingInstructionEndOffset(string $xml, int $offset): ?int
    {
        $end = strpos($xml, '?>', $offset + 2);

        return $end === false ? null : $end + 2;
    }

    private function xmlTagNameAt(string $xml, int $offset): ?string
    {
        $length = strlen($xml);
        while ($offset < $length && ctype_space($xml[$offset])) {
            $offset++;
        }

        if ($offset >= $length || preg_match('/[A-Za-z_]/', $xml[$offset]) !== 1) {
            return null;
        }

        $start = $offset;
        $offset++;
        while ($offset < $length && preg_match('/[A-Za-z0-9_.:-]/', $xml[$offset]) === 1) {
            $offset++;
        }

        return substr($xml, $start, $offset - $start);
    }

    /**
     * @return array{xml: string, packet_encoding: string}|null
     */
    private function convertedXmpXmlCandidate(string $xml, string $encoding, bool $fallback): ?array
    {
        if (!function_exists('iconv')) {
            return null;
        }

        $converted = @iconv($encoding, 'UTF-8//IGNORE', $xml);
        if ($converted === false || $converted === '') {
            return null;
        }

        return [
            'xml' => $fallback ? $converted : $this->forceUtf8XmlDeclaration($converted),
            'packet_encoding' => $encoding,
        ];
    }

    private function declaredXmlEncoding(string $xml): ?string
    {
        if (preg_match('/^\s*<\?xml\b[^>]*\bencoding\s*=\s*([\'"])([^\'"]+)\1/si', $xml, $match) !== 1) {
            return null;
        }

        $encoding = trim($match[2]);
        return $encoding === '' ? null : $encoding;
    }

    private function isUtf8EncodingName(string $encoding): bool
    {
        return in_array(strtoupper(str_replace(['_', '-'], '', $encoding)), ['UTF8'], true);
    }

    private function isValidUtf8(string $value): bool
    {
        return preg_match('//u', $value) === 1;
    }

    private function sniffBomlessUtf16XmlEncoding(string $xml): ?string
    {
        $length = min(strlen($xml), 256);
        if ($length < 8) {
            return null;
        }

        $beScore = 0;
        $leScore = 0;
        $hasBeXmlStart = false;
        $hasLeXmlStart = false;
        for ($index = 0; $index + 1 < $length; $index += 2) {
            $first = $xml[$index];
            $second = $xml[$index + 1];
            if ($first === "\0" && $second !== "\0") {
                $beScore++;
            }
            if ($first !== "\0" && $second === "\0") {
                $leScore++;
            }
            if ($first === "\0" && $second === '<') {
                $hasBeXmlStart = true;
            }
            if ($first === '<' && $second === "\0") {
                $hasLeXmlStart = true;
            }
        }

        if ($hasBeXmlStart && $beScore >= 4 && $beScore > ($leScore * 2)) {
            return 'UTF-16BE';
        }
        if ($hasLeXmlStart && $leScore >= 4 && $leScore > ($beScore * 2)) {
            return 'UTF-16LE';
        }

        return null;
    }

    private function forceUtf8XmlDeclaration(string $xml): string
    {
        return preg_replace_callback(
            '/^(\s*<\?xml\b[^>]*\bencoding\s*=\s*)([\'"])([^\'"]+)([\'"])/si',
            static fn (array $match): string => $match[1] . $match[2] . 'UTF-8' . $match[4],
            $xml,
            1
        ) ?? $xml;
    }

    private function canonicalXmlEncodingLabel(string $encoding): string
    {
        return match (strtoupper(str_replace('_', '-', $encoding))) {
            'UTF8', 'UTF-8' => 'UTF-8',
            'UTF-8-BOM' => 'UTF-8-BOM',
            'UTF16', 'UTF-16' => 'UTF-16',
            'UTF16BE', 'UTF-16BE' => 'UTF-16BE',
            'UTF16LE', 'UTF-16LE' => 'UTF-16LE',
            'WINDOWS-1252', 'CP1252' => 'Windows-1252',
            'ISO-8859-1', 'ISO8859-1', 'LATIN1', 'LATIN-1' => 'ISO-8859-1',
            default => $encoding,
        };
    }

    private function xmpSingleValue(DOMDocument $document, string $namespace, string $localName, bool $preferAlt): ?string
    {
        foreach ($this->xmpTopLevelPropertyElements($document, $namespace, $localName) as $element) {
            $value = $preferAlt ? $this->preferredAltText($element) : $this->xmpQualifiedTextValue($element);
            if ($value !== null) {
                return $value;
            }
        }

        foreach ($this->xmpTopLevelDescriptions($document) as $description) {
            if (!$description instanceof DOMElement || !$description->hasAttributeNS($namespace, $localName)) {
                continue;
            }

            $value = $this->cleanText($description->getAttributeNS($namespace, $localName));
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function xmpListValues(DOMDocument $document, string $namespace, string $localName): array
    {
        foreach ($this->xmpTopLevelPropertyElements($document, $namespace, $localName) as $element) {
            $values = [];
            foreach ($this->xmpRdfCollectionItems($element) as $item) {
                $value = $this->xmpQualifiedTextValue($item);
                if ($value !== null) {
                    $values[] = $value;
                }
            }

            if ($values !== []) {
                $cleanValues = $this->cleanList($values);
                if ($cleanValues !== []) {
                    return $cleanValues;
                }
            }

            $value = $this->xmpQualifiedTextValue($element);
            if ($value !== null) {
                if ($namespace === self::NS_DC && $localName === 'subject') {
                    $keywords = $this->splitKeywords($value);
                    return $keywords === [] ? [$value] : $keywords;
                }

                return [$value];
            }
        }

        foreach ($this->xmpTopLevelDescriptions($document) as $description) {
            if (!$description instanceof DOMElement || !$description->hasAttributeNS($namespace, $localName)) {
                continue;
            }

            $value = $this->cleanText($description->getAttributeNS($namespace, $localName));
            if ($value === null) {
                return [];
            }

            if ($namespace === self::NS_DC && $localName === 'creator') {
                return [$value];
            }

            $values = $this->splitKeywords($value);
            if ($values !== []) {
                return $values;
            }
        }

        return [];
    }

    private function normalizedDateTimeUtc(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match("/^D:(\d{4})(\d{2})?(\d{2})?(\d{2})?(\d{2})?(\d{2})?(?:(Z)|([+\-])(\d{2})'?(?:(\d{2})'?)?)?$/", $value, $match) === 1) {
            return $this->normalizedPdfDateTimeUtc($match);
        }

        if (preg_match('/(?:Z|[+\-]\d{2}:\d{2})$/', $value) !== 1) {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s\Z');
        } catch (Exception) {
            return null;
        }
    }

    /**
     * @param array<int, string> $match
     */
    private function normalizedPdfDateTimeUtc(array $match): ?string
    {
        if (($match[7] ?? '') === '' && ($match[8] ?? '') === '') {
            return null;
        }

        $year = (int) $match[1];
        $month = ($match[2] ?? '') === '' ? 1 : (int) $match[2];
        $day = ($match[3] ?? '') === '' ? 1 : (int) $match[3];
        $hour = ($match[4] ?? '') === '' ? 0 : (int) $match[4];
        $minute = ($match[5] ?? '') === '' ? 0 : (int) $match[5];
        $second = ($match[6] ?? '') === '' ? 0 : (int) $match[6];

        if (
            !checkdate($month, $day, $year)
            || $hour < 0 || $hour > 23
            || $minute < 0 || $minute > 59
            || $second < 0 || $second > 59
        ) {
            return null;
        }

        $date = new DateTimeImmutable(
            sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second),
            new DateTimeZone('UTC')
        );

        $sign = $match[8] ?? '';
        if ($sign !== '') {
            $offsetHours = (int) ($match[9] ?? 0);
            $offsetMinutes = ($match[10] ?? '') === '' ? 0 : (int) $match[10];
            if ($offsetHours > 23 || $offsetMinutes > 59) {
                return null;
            }

            $offsetSeconds = ($offsetHours * 3600) + ($offsetMinutes * 60);
            $date = $date->modify(($sign === '+' ? '-' : '+') . $offsetSeconds . ' seconds');
        }

        return $date->format('Y-m-d\TH:i:s\Z');
    }

    private function preferredAltText(DOMElement $element): ?string
    {
        $first = null;
        foreach ($this->xmpRdfCollectionItems($element) as $item) {
            $value = $this->xmpQualifiedTextValue($item);
            if ($value === null) {
                continue;
            }

            $first ??= $value;
            if (strcasecmp($this->xmpInheritedXmlLang($item), 'x-default') === 0) {
                return $value;
            }
        }

        return $first ?? $this->xmpQualifiedTextValue($element);
    }

    private function xmpInheritedXmlLang(DOMElement $element): string
    {
        $node = $element;
        while ($node instanceof DOMElement) {
            if ($node->hasAttributeNS(self::NS_XML, 'lang')) {
                return trim($node->getAttributeNS(self::NS_XML, 'lang'));
            }

            $node = $node->parentNode;
        }

        return '';
    }

    /**
     * @return array<int, string>
     */
    private function pdfObjects(string $pdfBytes): array
    {
        $this->currentObjectReferenceOwners = [];
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
        $this->currentObjectReferenceOwners = $this->objectReferenceOwners($objects, $definitions);
        return $objects;
    }

    private function bytesThroughCurrentEof(string $pdfBytes): string
    {
        $definitions = $this->directObjectDefinitions($pdfBytes);
        $entry = $this->latestStartxrefEntry($pdfBytes, $definitions === [] ? null : $definitions);
        if ($entry !== null) {
            $eofOffset = strpos($pdfBytes, '%%EOF', $entry['tokenOffset']);
            if ($eofOffset !== false) {
                return substr($pdfBytes, 0, $eofOffset + strlen('%%EOF'));
            }
        }

        $eofOffset = strrpos($pdfBytes, '%%EOF');
        if ($eofOffset !== false) {
            return substr($pdfBytes, 0, $eofOffset + strlen('%%EOF'));
        }

        return $pdfBytes;
    }

    /**
     * @return array<int, list<array{generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string}>>
     */
    private function directObjectDefinitions(string $pdfBytes): array
    {
        $definitions = [];
        $offset = 0;
        while (preg_match('/(\d+)\s+(\d+)\s+obj\b/s', $pdfBytes, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $bodyStart = $match[0][1] + strlen($match[0][0]);
            $bodyEnd = $this->pdfObjectEndOffset($pdfBytes, $bodyStart);
            if ($bodyEnd === null) {
                break;
            }

            $definitions[(int) $match[1][0]][] = [
                'generation' => (int) $match[2][0],
                'offset' => $match[1][1],
                'bodyStart' => $bodyStart,
                'bodyEnd' => $bodyEnd,
                'body' => substr($pdfBytes, $bodyStart, $bodyEnd - $bodyStart),
            ];
            $offset = $bodyEnd + strlen('endobj');
        }

        ksort($definitions, SORT_NUMERIC);
        return $definitions;
    }

    private function pdfObjectEndOffset(string $pdfBytes, int $offset): ?int
    {
        $objectBodyStart = $offset;
        $index = $offset;
        $length = strlen($pdfBytes);
        while ($index < $length) {
            $char = $pdfBytes[$index];
            if ($char === '%') {
                $index = $this->lineCommentEndOffset($pdfBytes, $index);
                continue;
            }

            if ($char === '(') {
                $index = $this->literalTokenEndOffset($pdfBytes, $index);
                continue;
            }

            if ($char === '<') {
                if ($index + 1 < $length && $pdfBytes[$index + 1] === '<') {
                    $dictionary = $this->readPdfDictionaryAt($pdfBytes, $index);
                    if ($dictionary !== null) {
                        $index += strlen($dictionary) + 4;
                        continue;
                    }
                } else {
                    $hexEnd = strpos($pdfBytes, '>', $index + 1);
                    if ($hexEnd !== false) {
                        $index = $hexEnd + 1;
                        continue;
                    }
                }
            }

            if ($char === '[') {
                $array = $this->readPdfArrayAt($pdfBytes, $index);
                if ($array !== null) {
                    $index += strlen($array);
                    continue;
                }
            }

            if ($this->pdfKeywordAt($pdfBytes, $index, 'stream')) {
                $streamEnd = $this->directObjectStreamEndOffset($pdfBytes, $objectBodyStart, $index);
                if ($streamEnd !== null) {
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

    private function directObjectStreamEndOffset(string $pdfBytes, int $objectBodyStart, int $streamKeywordOffset): ?int
    {
        $dictionaryOffset = $this->skipPdfWhitespace($pdfBytes, $objectBodyStart);
        $dictionary = $this->readPdfDictionaryAt($pdfBytes, $dictionaryOffset);
        if ($dictionary === null) {
            return null;
        }

        if ($this->skipPdfWhitespace($pdfBytes, $dictionaryOffset + strlen($dictionary) + 4) !== $streamKeywordOffset) {
            return null;
        }

        $streamStart = $streamKeywordOffset + strlen('stream');
        if (substr($pdfBytes, $streamStart, 2) === "\r\n") {
            $streamStart += 2;
        } elseif (($pdfBytes[$streamStart] ?? '') === "\n" || ($pdfBytes[$streamStart] ?? '') === "\r") {
            $streamStart++;
        }

        $lengthValue = $this->dictionaryTopLevelRawValue($dictionary, 'Length');
        if ($lengthValue !== null && preg_match('/^[+-]?\d+$/', trim($lengthValue)) === 1) {
            $declaredEnd = $streamStart + max(0, (int) trim($lengthValue));
            if ($declaredEnd <= strlen($pdfBytes)) {
                return $this->streamLengthTerminatorOffset($pdfBytes, $declaredEnd)
                    ?? $this->endstreamTerminatorOffset($pdfBytes, $streamStart, $declaredEnd);
            }
        }

        return $this->filteredEndstreamTerminatorOffset($pdfBytes, $streamStart, $dictionary, [])
            ?? $this->endstreamTerminatorOffset($pdfBytes, $streamStart, null);
    }

    private function lineCommentEndOffset(string $value, int $offset): int
    {
        $end = strcspn($value, "\r\n", $offset);
        return $offset + $end;
    }

    private function literalTokenEndOffset(string $value, int $offset): int
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

            if ($char === ')') {
                $depth--;
                if ($depth <= 0) {
                    return $index + 1;
                }
            }
        }

        return strlen($value);
    }

    private function pdfKeywordAt(string $value, int $offset, string $keyword): bool
    {
        $keywordLength = strlen($keyword);
        if (substr($value, $offset, $keywordLength) !== $keyword) {
            return false;
        }

        if ($offset > 0) {
            $before = $value[$offset - 1];
            if ($before === '/' || (!$this->isPdfWhitespace($before) && !str_contains('[]()<>{}%', $before))) {
                return false;
            }
        }

        $afterOffset = $offset + $keywordLength;
        if ($afterOffset >= strlen($value)) {
            return true;
        }

        $after = $value[$afterOffset];
        return $this->isPdfWhitespace($after) || str_contains('[]()<>{}/%', $after);
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, bodyStart?: int, bodyEnd?: int, body: string}>> $definitions
     * @return array<int, string>
     */
    private function latestDirectObjects(array $definitions): array
    {
        $objects = [];
        foreach ($definitions as $objectNumber => $entries) {
            $selected = $this->latestDirectObjectDefinition($entries);
            if ($selected !== null) {
                $objects[$objectNumber] = $selected['body'];
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
        $objects = [];
        foreach ($definitions as $objectNumber => $entries) {
            $selected = $this->liveDirectObjectDefinition($entries, $xrefEntries[$objectNumber] ?? null);
            if ($selected !== null) {
                $objects[$objectNumber] = $selected['body'];
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
     * Incremental xref updates can name the current catalog, Info, or Encrypt
     * generation in the latest trailer while carrying damaged explicit offsets for those
     * rows. Keep that latest trailer generation authoritative instead of
     * falling back to stale /Prev metadata.
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

        $repaired = $objects;
        foreach (['Root', 'Info', 'Encrypt'] as $name) {
            $reference = $this->objectReferenceFromValue($this->dictionaryTopLevelRawValue($trailer, $name));
            if ($reference === null || $reference['generation'] <= 0) {
                continue;
            }

            $definition = $this->directObjectDefinitionForGeneration(
                $definitions[$reference['objectNumber']] ?? [],
                $reference['generation']
            );
            if ($definition !== null) {
                $repaired[$reference['objectNumber']] = $definition['body'];
            }
        }

        ksort($repaired, SORT_NUMERIC);
        return $repaired;
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
     * selected direct /ObjStm carrier. Metadata review needs those compressed
     * catalog, Info, and name-tree dictionaries, but stream payload objects
     * remain direct objects only.
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
        $dictionaryOffset = $this->skipPdfWhitespace($memberBody, 0);
        $dictionary = $this->readPdfDictionaryAt($memberBody, $dictionaryOffset);
        if ($dictionary === null) {
            return false;
        }

        $streamOffset = $this->skipPdfWhitespace($memberBody, $dictionaryOffset + strlen($dictionary) + 4);
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
     * @return array<int, array{generation: int, body: string}>
     */
    private function objectReferenceOwners(array $objects, array $definitions): array
    {
        $owners = [];
        foreach ($objects as $objectNumber => $body) {
            $definition = $this->directObjectDefinitionForBody($definitions[$objectNumber] ?? [], $body);
            if ($definition === null) {
                continue;
            }

            $owners[$objectNumber] = [
                'generation' => $definition['generation'],
                'body' => $definition['body'],
            ];
        }

        return $owners;
    }

    /**
     * @param list<array{generation: int, offset: int, body: string}> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function directObjectDefinitionForBody(array $definitions, string $body): ?array
    {
        $candidates = [];
        foreach ($definitions as $definition) {
            if ($definition['body'] === $body) {
                $candidates[] = $definition;
            }
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
            $reference = $this->objectReferenceFromValue($this->dictionaryTopLevelRawValue($sectionBody, $name));
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
     * Current xref streams may update type-2 object-stream members while
     * omitting the direct carrier row. Recover only an in-window current
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

        $offset = $this->skipPdfWhitespace($pdfBytes, $offset);
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

        $dictionaryOffset = $this->skipPdfWhitespace($pdfBytes, $trailerOffset + strlen('trailer'));
        if (substr($pdfBytes, $dictionaryOffset, 2) !== '<<') {
            return null;
        }

        $trailer = $this->readPdfDictionaryAt($pdfBytes, $dictionaryOffset);
        if ($trailer === null) {
            return null;
        }

        $entries = $this->xrefTableRows(substr($pdfBytes, $sectionBodyOffset, $trailerOffset - $sectionBodyOffset));
        if ($entries === null) {
            return null;
        }

        if ($definitions !== null && $repairCurrentRows) {
            $entries = $this->repairClassicXrefGenerationOffsetRows($entries, $definitions, $offset);
            $entries = $this->repairCurrentUpdateXrefTableRows($pdfBytes, $entries, $definitions, $trailer, $offset, $objects);
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
        $index = $offset;
        while ($index < $length) {
            if ($definitions !== null) {
                foreach ($definitions as $entries) {
                    foreach ($entries as $definition) {
                        $bodyStart = $definition['bodyStart'] ?? null;
                        $bodyEnd = $definition['bodyEnd'] ?? null;
                        if (is_int($bodyStart) && is_int($bodyEnd) && $index >= $bodyStart && $index <= $bodyEnd) {
                            $index = $bodyEnd + 1;
                            continue 3;
                        }
                    }
                }
            }

            $char = $pdfBytes[$index];

            if (
                substr($pdfBytes, $index, 5) === '%%EOF'
                || $this->pdfKeywordAt($pdfBytes, $index, 'startxref')
            ) {
                return null;
            }

            if ($char === '%') {
                $index = $this->lineCommentEndOffset($pdfBytes, $index);
                continue;
            }

            if ($char === '(') {
                $index = $this->literalTokenEndOffset($pdfBytes, $index);
                continue;
            }

            $compositeEnd = $this->skipPdfCompositeTokenAt($pdfBytes, $index);
            if ($compositeEnd !== null) {
                $index = $compositeEnd;
                continue;
            }

            if ($char === '<' && ($pdfBytes[$index + 1] ?? '') !== '<') {
                $end = $this->skipPdfHexStringTokenAt($pdfBytes, $index);
                if ($end !== null) {
                    $index = $end;
                    continue;
                }
            }

            if ($this->pdfKeywordAt($pdfBytes, $index, 'trailer')) {
                $dictionaryOffset = $this->skipPdfWhitespace($pdfBytes, $index + strlen('trailer'));
                if (substr($pdfBytes, $dictionaryOffset, 2) === '<<') {
                    return $index;
                }
            }

            $index++;
        }

        return null;
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

        $decoded = $this->decodeStreamObject($body, $operandObjects);
        if ($decoded === null) {
            return $entries;
        }

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
                if (isset($entries[$objectNumber])) {
                    $offset += $entryWidth;
                    continue;
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
                } elseif ($type === 2 && $objectNumber > 0) {
                    $entries[$objectNumber] = [
                        'type' => 2,
                        'objectStream' => $fieldTwo,
                        'objectStreamIsExplicit' => $widths[1] > 0,
                        'index' => $fieldThree,
                        'indexIsExplicit' => $widths[2] > 0,
                    ];
                }

                $offset += $entryWidth;
            }
        }

        return $entries;
    }

    /**
     * Xref-stream /Prev may be a safe numeric helper in a direct object or
     * compressed object stream that appears before the current xref stream.
     * Resolve only the referenced scalar helper needed to repair current rows.
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
        $reference = $this->objectReferenceFromValue($this->dictionaryTopLevelRawValue($xrefBody, 'Prev'));
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
            return $objects;
        }

        if ($directBody !== null && $this->safeXrefOperandHelperBody($directBody)) {
            $objects[$reference['objectNumber']] = $directBody;
            return $objects;
        }

        if ($helperBody === null || !$this->safeXrefOperandHelperBody($helperBody)) {
            return $objects;
        }

        $objects[$reference['objectNumber']] = $helperBody;
        return $objects;
    }

    private function objectBodyHasTypeName(string $body, string $name): bool
    {
        $dictionary = $this->readPdfDictionaryAt($body, $this->skipPdfWhitespace($body, 0));
        if ($dictionary === null) {
            return false;
        }

        return preg_match('/\/Type\s*\/' . preg_quote($name, '/') . '(?=$|[\s\[\]()<>{}\/%])/s', $dictionary) === 1;
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
        $decoded = $this->decodeStreamObject($body, $objects);
        if ($decoded === null) {
            return null;
        }

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

        if ($this->skipPdfWhitespace($header, $offset) !== strlen($header)) {
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
     * Object-stream offsets are relative to the first object byte and must
     * start at a top-level member boundary, not inside another member's string,
     * comment, array, or dictionary payload.
     *
     * @param array{decoded: string, first: int, members: list<array{objectNumber: int, offset: int, index: int}>} $memberTable
     * @param array{objectNumber: int, offset: int, index: int} $member
     */
    private function objectStreamMemberOffsetHasTokenBoundary(array $memberTable, array $member): bool
    {
        $decoded = $memberTable['decoded'];
        $absoluteOffset = $memberTable['first'] + $member['offset'];
        $length = strlen($decoded);
        if ($member['offset'] < 0 || $absoluteOffset < $memberTable['first'] || $absoluteOffset >= $length) {
            return false;
        }

        if ($absoluteOffset === $memberTable['first']) {
            return true;
        }

        if ($decoded[$absoluteOffset] === '%') {
            return false;
        }

        $index = $memberTable['first'];
        while ($index < $absoluteOffset && $index < $length) {
            $char = $decoded[$index];
            if ($char === '%') {
                $start = $index;
                $index = $this->lineCommentEndOffset($decoded, $index);
                if ($absoluteOffset < $index || $index === $start) {
                    return false;
                }
                continue;
            }

            if ($char === '(') {
                $start = $index;
                $index = $this->literalTokenEndOffset($decoded, $index);
                if ($absoluteOffset < $index || $index === $start) {
                    return false;
                }
                continue;
            }

            if ($char === '<' && ($decoded[$index + 1] ?? '') !== '<') {
                $end = strpos($decoded, '>', $index + 1);
                if ($end === false) {
                    return false;
                }

                $end++;
                if ($absoluteOffset < $end) {
                    return false;
                }

                $index = $end;
                continue;
            }

            if ($char === '[') {
                $array = $this->readPdfArrayAt($decoded, $index);
                if ($array !== null) {
                    $end = $index + strlen($array);
                    if ($absoluteOffset < $end) {
                        return false;
                    }

                    $index = $end;
                    continue;
                }
            }

            if ($char === '<' && ($decoded[$index + 1] ?? '') === '<') {
                $dictionary = $this->readPdfDictionaryAt($decoded, $index);
                if ($dictionary !== null) {
                    $end = $index + strlen($dictionary) + 4;
                    if ($absoluteOffset < $end) {
                        return false;
                    }

                    $index = $end;
                    continue;
                }
            }

            $index++;
        }

        if ($index !== $absoluteOffset) {
            return false;
        }

        $before = $decoded[$absoluteOffset - 1];
        return ctype_space($before) || str_contains('[]()<>{}%', $before);
    }

    private function readUnsignedIntegerToken(string $value, int &$offset): ?int
    {
        $offset = $this->skipPdfWhitespace($value, $offset);
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

        $offset = $this->skipPdfWhitespace($body, 0);
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
                return $reference === null
                    ? null
                    : $this->objectBodyForReference($objects, $reference['objectNumber'], $reference['generation']);
            }
        }

        $trailer = $this->trailerDictionaryBody($pdfBytes);
        if ($trailer !== null) {
            $rootReference = $this->dictionaryTopLevelRawValue($trailer, 'Root');
            if ($rootReference !== null) {
                return $this->objectBodyFromReferenceValue($rootReference, $objects);
            }
        }

        foreach ($objects as $body) {
            if (preg_match('/\/Type\s*\/Catalog\b/', $body) === 1) {
                return $body;
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
            $rootValue = $this->dictionaryTopLevelRawValue($tableSection['trailer'], 'Root');
            if ($rootValue !== null) {
                return [
                    'present' => true,
                    'reference' => $this->objectReferenceFromValue($rootValue),
                ];
            }

            $hybridStreamOffset = $this->dictionaryIntegerValue($tableSection['trailer'], 'XRefStm', $objects);
            if ($hybridStreamOffset !== null && $hybridStreamOffset >= 0 && !isset($seenOffsets[$hybridStreamOffset])) {
                $streamDictionary = $this->xrefStreamDictionaryAtObjectOffset($pdfBytes, $hybridStreamOffset);
                if ($streamDictionary !== null) {
                    $rootValue = $this->dictionaryTopLevelRawValue($streamDictionary, 'Root');
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

        $rootValue = $this->dictionaryTopLevelRawValue($streamDictionary, 'Root');
        if ($rootValue !== null) {
            return [
                'present' => true,
                'reference' => $this->objectReferenceFromValue($rootValue),
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

    private function trailerDictionaryBody(string $pdfBytes): ?string
    {
        $entry = $this->trailerDictionaryEntry($pdfBytes);

        return $entry['body'] ?? null;
    }

    /**
     * @return array{body: string, source: string}|null
     */
    private function trailerDictionaryEntry(string $pdfBytes): ?array
    {
        $definitions = $this->directObjectDefinitions($pdfBytes);
        $startxrefOffset = $this->startxrefOffsetWithClassicRebuild($pdfBytes, $definitions === [] ? null : $definitions);
        if ($startxrefOffset !== null) {
            $trailer = $this->trailerDictionaryBodyAtOffset(
                $pdfBytes,
                $startxrefOffset,
                $definitions === [] ? null : $definitions
            );
            if ($trailer !== null) {
                return [
                    'body' => $trailer,
                    'source' => $this->trailerDictionarySourceAtOffset($pdfBytes, $startxrefOffset) ?? 'trailer',
                ];
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
                $body = $candidate;
            }
            $offset = $position + 7;
        }

        if ($body !== null) {
            return [
                'body' => $body,
                'source' => 'textual_trailer_fallback',
            ];
        }

        $lastXrefStream = $this->lastXrefStreamDictionaryBody($pdfBytes);
        return $lastXrefStream === null ? null : [
            'body' => $lastXrefStream,
            'source' => 'xref_stream_trailer',
        ];
    }

    private function latestStartxrefOffset(string $pdfBytes): ?int
    {
        $entry = $this->latestStartxrefEntry($pdfBytes);

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
                $offset = $this->lineCommentEndOffset($pdfBytes, $offset);
                continue;
            }

            if ($char === '(') {
                $end = $this->literalTokenEndOffset($pdfBytes, $offset);
                if ($end > $offset) {
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
                $end = $this->skipPdfHexStringTokenAt($pdfBytes, $offset);
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
     * @param array<int, list<array{bodyStart?: int, bodyEnd?: int, generation: int, offset: int, body: string}>>|null $definitions
     */
    private function startxrefOffsetWithClassicRebuild(string $pdfBytes, ?array $definitions = null): ?int
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
     * @param array<int, list<array{bodyStart?: int, bodyEnd?: int, generation: int, offset: int, body: string}>>|null $definitions
     */
    private function classicRebuildOffsetForStartxref(
        string $pdfBytes,
        int $offset,
        ?array $definitions = null,
        ?int $candidateBeforeOffset = null
    ): ?int {
        if ($definitions !== null && $this->xrefStreamSectionAtOffset($offset, $definitions) !== null) {
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
     * @param array<int, list<array{bodyStart?: int, bodyEnd?: int, generation: int, offset: int, body: string}>>|null $definitions
     */
    private function latestClassicXrefTableOffset(
        string $pdfBytes,
        ?array $definitions = null,
        ?int $candidateBeforeOffset = null
    ): ?int {
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
        $index = 0;
        while ($index < $length) {
            if ($definitions !== null) {
                foreach ($definitions as $entries) {
                    foreach ($entries as $definition) {
                        $bodyStart = $definition['bodyStart'] ?? null;
                        $bodyEnd = $definition['bodyEnd'] ?? null;
                        if (is_int($bodyStart) && is_int($bodyEnd) && $index >= $bodyStart && $index <= $bodyEnd) {
                            $index = $bodyEnd + 1;
                            continue 3;
                        }
                    }
                }
            }

            $char = $pdfBytes[$index];

            if ($char === '%') {
                $index = $this->lineCommentEndOffset($pdfBytes, $index);
                continue;
            }

            if ($char === '(') {
                $index = $this->literalTokenEndOffset($pdfBytes, $index);
                continue;
            }

            $compositeEnd = $this->skipPdfCompositeTokenAt($pdfBytes, $index);
            if ($compositeEnd !== null) {
                $index = $compositeEnd;
                continue;
            }

            if ($char === '<' && ($pdfBytes[$index + 1] ?? '') !== '<') {
                $end = $this->skipPdfHexStringTokenAt($pdfBytes, $index);
                if ($end !== null) {
                    $index = $end;
                    continue;
                }
            }

            if ($this->pdfKeywordAt($pdfBytes, $index, 'xref')) {
                $offsets[] = $index;
                $index += strlen('xref');
                continue;
            }

            $index++;
        }

        return $offsets;
    }

    private function skipPdfHexStringTokenAt(string $pdfBytes, int $offset): ?int
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

    private function skipPdfCompositeTokenAt(string $pdfBytes, int $offset): ?int
    {
        if ($offset < 0 || $offset >= strlen($pdfBytes)) {
            return null;
        }

        if ($pdfBytes[$offset] === '[') {
            $array = $this->readPdfArrayAt($pdfBytes, $offset);
            return $array === null ? null : $offset + strlen($array);
        }

        if (substr($pdfBytes, $offset, 2) === '<<') {
            $dictionary = $this->readPdfDictionaryAt($pdfBytes, $offset);
            return $dictionary === null ? null : $offset + strlen($dictionary) + 4;
        }

        return null;
    }

    /**
     * @param array<int, list<array{bodyStart?: int, bodyEnd?: int}>>|null $definitions
     */
    private function trailerDictionaryBodyAtOffset(string $pdfBytes, int $offset, ?array $definitions = null): ?string
    {
        $offset = $this->skipPdfWhitespace($pdfBytes, $offset);
        if (substr($pdfBytes, $offset, 4) === 'xref') {
            return $this->xrefTableTrailerDictionaryAtOffset($pdfBytes, $offset, $definitions);
        }

        return $this->xrefStreamDictionaryAtObjectOffset($pdfBytes, $offset);
    }

    private function trailerDictionarySourceAtOffset(string $pdfBytes, int $offset): ?string
    {
        $offset = $this->skipPdfWhitespace($pdfBytes, $offset);
        if (substr($pdfBytes, $offset, 4) === 'xref') {
            return 'xref_table_trailer';
        }

        return $this->xrefStreamDictionaryAtObjectOffset($pdfBytes, $offset) === null
            ? null
            : 'xref_stream_trailer';
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

        $dictionaryOffset = $this->skipPdfWhitespace($pdfBytes, $trailerOffset + strlen('trailer'));
        return substr($pdfBytes, $dictionaryOffset, 2) === '<<'
            ? $this->readPdfDictionaryAt($pdfBytes, $dictionaryOffset)
            : null;
    }

    private function xrefStreamDictionaryAtObjectOffset(string $pdfBytes, int $offset): ?string
    {
        $offset = $this->skipPdfWhitespace($pdfBytes, $offset);
        if (preg_match('/\d+\s+\d+\s+obj\b/A', substr($pdfBytes, $offset), $match) !== 1) {
            return null;
        }

        $dictionaryOffset = strpos($pdfBytes, '<<', $offset + strlen($match[0]));
        if ($dictionaryOffset === false) {
            return null;
        }

        $body = $this->readPdfDictionaryAt($pdfBytes, $dictionaryOffset);
        if ($body === null || preg_match('/\/Type\s*\/XRef\b/s', $body) !== 1) {
            return null;
        }

        return $body;
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

    private function skipPdfWhitespace(string $pdfBytes, int $offset): int
    {
        $length = strlen($pdfBytes);
        while ($offset < $length) {
            if ($this->isPdfWhitespace($pdfBytes[$offset])) {
                $offset++;
                continue;
            }

            if ($pdfBytes[$offset] === '%') {
                $offset = $this->lineCommentEndOffset($pdfBytes, $offset);
                continue;
            }

            break;
        }

        return $offset;
    }

    private function isPdfWhitespace(string $char): bool
    {
        return $char === "\0" || ctype_space($char);
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeStreamObject(string $objectBody, array $objects): ?string
    {
        $entry = $this->decodeStreamEntryObject($objectBody, $objects);
        return $entry['content'] ?? null;
    }

    /**
     * @param array<int, string> $objects
     * @return array{dictionary: string, content: string}|null
     */
    private function decodeStreamEntryObject(string $objectBody, array $objects): ?array
    {
        $dictionaryOffset = $this->skipPdfWhitespace($objectBody, 0);
        $dictionary = $this->readPdfDictionaryAt($objectBody, $dictionaryOffset);
        if ($dictionary === null) {
            return null;
        }

        $streamKeywordOffset = $this->skipPdfWhitespace($objectBody, $dictionaryOffset + strlen($dictionary) + 4);
        if (!$this->pdfKeywordAt($objectBody, $streamKeywordOffset, 'stream')) {
            return null;
        }

        $streamStart = $streamKeywordOffset + strlen('stream');
        if (substr($objectBody, $streamStart, 2) === "\r\n") {
            $streamStart += 2;
        } elseif (($objectBody[$streamStart] ?? '') === "\n" || ($objectBody[$streamStart] ?? '') === "\r") {
            $streamStart++;
        }

        $streamEnd = $this->streamPayloadEndOffset($objectBody, $streamStart, $dictionary, $objects);
        if ($streamEnd === null || $streamEnd < $streamStart) {
            return null;
        }

        $stream = $this->stripStreamTerminatingLineEnding(substr($objectBody, $streamStart, $streamEnd - $streamStart));
        $content = $this->decodeStream($dictionary, $stream, $objects);
        return $content === null ? null : [
            'dictionary' => $dictionary,
            'content' => $content,
        ];
    }

    /**
     * @param array<int, string> $objects
     */
    private function streamPayloadEndOffset(string $value, int $streamStart, string $dictionary, array $objects): ?int
    {
        $length = $this->streamLength($dictionary, $objects);
        if ($length !== null) {
            $declaredEnd = $streamStart + $length;
            if ($length >= 0 && $declaredEnd <= strlen($value)) {
                return $this->streamLengthTerminatorOffset($value, $declaredEnd)
                    ?? $this->endstreamTerminatorOffset($value, $streamStart, $declaredEnd)
                    ?? $declaredEnd;
            }
        }

        return $this->filteredEndstreamTerminatorOffset($value, $streamStart, $dictionary, $objects)
            ?? $this->endstreamTerminatorOffset($value, $streamStart, null);
    }

    /**
     * @param array<int, string> $objects
     */
    private function streamLength(string $dictionary, array $objects): ?int
    {
        $value = $this->dictionaryTopLevelRawValue($dictionary, 'Length');
        if ($value === null) {
            return null;
        }

        $resolved = trim($this->resolvePdfValue($value, $objects) ?? $value);
        if (preg_match('/^[+-]?\d+$/', $resolved) !== 1) {
            return null;
        }

        $length = (int) $resolved;
        return $length < 0 ? null : $length;
    }

    private function streamLengthTerminatorOffset(string $value, int $declaredEnd): ?int
    {
        $offset = $declaredEnd;
        if (substr($value, $offset, 2) === "\r\n") {
            $offset += 2;
        } elseif (($value[$offset] ?? '') === "\n" || ($value[$offset] ?? '') === "\r") {
            $offset++;
        }

        return $this->endstreamKeywordAt($value, $offset) ? $offset : null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function filteredEndstreamTerminatorOffset(string $value, int $streamStart, string $dictionary, array $objects): ?int
    {
        if (!$this->hasVerifiableStreamFilter($this->streamFilters($dictionary, $objects))) {
            return null;
        }

        $offset = $streamStart;
        while (($candidate = strpos($value, 'endstream', $offset)) !== false) {
            $offset = $candidate + strlen('endstream');
            if (!$this->endstreamTerminatorAt($value, $candidate, $streamStart)) {
                continue;
            }

            $payload = $this->stripStreamTerminatingLineEnding(substr($value, $streamStart, $candidate - $streamStart));
            if ($this->decodeStream($dictionary, $payload, $objects) !== null) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param list<string> $filters
     */
    private function hasVerifiableStreamFilter(array $filters): bool
    {
        foreach ($filters as $filter) {
            if (in_array($filter, ['ASCIIHexDecode', 'AHx', 'FlateDecode', 'Fl'], true)) {
                return true;
            }
        }

        return false;
    }

    private function endstreamTerminatorOffset(string $value, int $streamStart, ?int $declaredEnd): ?int
    {
        $fallback = null;
        $beforeDeclaredEnd = null;
        $offset = $streamStart;
        while (($candidate = strpos($value, 'endstream', $offset)) !== false) {
            $fallback ??= $candidate;
            $offset = $candidate + strlen('endstream');
            if (!$this->endstreamTerminatorAt($value, $candidate, $streamStart)) {
                continue;
            }

            if ($declaredEnd === null || $candidate >= $declaredEnd) {
                return $candidate;
            }

            $beforeDeclaredEnd = $candidate;
        }

        return $beforeDeclaredEnd ?? $fallback;
    }

    private function endstreamTerminatorAt(string $value, int $offset, int $streamStart): bool
    {
        if (!$this->endstreamKeywordAt($value, $offset)) {
            return false;
        }

        if ($offset <= $streamStart) {
            return true;
        }

        $previous = $value[$offset - 1] ?? '';
        return $previous === "\n" || $previous === "\r";
    }

    private function endstreamKeywordAt(string $value, int $offset): bool
    {
        if (substr($value, $offset, strlen('endstream')) !== 'endstream') {
            return false;
        }

        $after = $offset + strlen('endstream');
        return $after >= strlen($value) || ctype_space($value[$after]);
    }

    private function stripStreamTerminatingLineEnding(string $stream): string
    {
        if (str_ends_with($stream, "\r\n")) {
            return substr($stream, 0, -2);
        }

        if (str_ends_with($stream, "\n") || str_ends_with($stream, "\r")) {
            return substr($stream, 0, -1);
        }

        return $stream;
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeStream(string $dict, string $stream, array $objects): ?string
    {
        foreach ($this->streamFilters($dict, $objects) as $filter) {
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

        return $stream;
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function streamFilters(string $dict, array $objects): array
    {
        $value = $this->dictionaryTopLevelRawValue($dict, 'Filter');
        return $value === null ? [] : $this->filterNamesFromValue($value, $objects);
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function filterNamesFromValue(string $value, array $objects): array
    {
        $value = $this->trimPdfWhitespaceAndComments($this->resolvePdfValue($value, $objects) ?? $value);
        if ($value === '') {
            return [];
        }

        if (str_starts_with($value, '[')) {
            $filters = [];
            foreach ($this->arrayItemsFromValue($value, $objects) as $item) {
                foreach ($this->filterNamesFromValue($item, $objects) as $filter) {
                    $filters[] = $filter;
                }
            }

            return $filters;
        }

        if (str_starts_with($value, '/')) {
            $name = $this->nameValueAt($value, 0);
            return $name === null ? [] : [$name];
        }

        $reference = $this->objectReferenceFromValue($value);
        if ($reference === null) {
            return [];
        }

        $objectBody = $this->objectBodyForReference($objects, $reference['objectNumber'], $reference['generation']);
        if ($objectBody === null) {
            return [];
        }

        return $this->filterNamesFromValue($objectBody, $objects);
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function outputIntentDictionariesFromValue(string $value, array $objects): array
    {
        $resolved = trim($this->resolvePdfValue($value, $objects) ?? $value);
        if ($resolved === '') {
            return [];
        }

        if (str_starts_with($resolved, '[')) {
            return $this->outputIntentDictionariesFromArray($resolved, $objects);
        }

        if (str_starts_with($resolved, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($resolved, 0);
            return $dictionary === null ? [] : [$dictionary];
        }

        $dictionary = $this->dictionaryObjectBody($resolved);
        return $dictionary === null ? [] : [$dictionary];
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function outputIntentDictionariesFromArray(string $arrayValue, array $objects): array
    {
        $body = $this->arrayBody($arrayValue);
        if ($body === null) {
            return [];
        }

        $dictionaries = [];
        for ($offset = 0, $length = strlen($body); $offset < $length;) {
            while ($offset < $length && ctype_space($body[$offset])) {
                $offset++;
            }

            if ($offset >= $length) {
                break;
            }

            $remaining = substr($body, $offset);
            if (preg_match('/(\d+)\s+\d+\s+R\b/A', $remaining, $match) === 1) {
                $objectNumber = (int) $match[1];
                if (isset($objects[$objectNumber])) {
                    foreach ($this->outputIntentDictionariesFromValue($objects[$objectNumber], $objects) as $dictionary) {
                        $dictionaries[] = $dictionary;
                    }
                }
                $offset += strlen($match[0]);
                continue;
            }

            if (substr($body, $offset, 2) === '<<') {
                $dictionary = $this->readPdfDictionaryAt($body, $offset);
                if ($dictionary !== null) {
                    $dictionaries[] = $dictionary;
                    $offset += strlen($dictionary) + 4;
                    continue;
                }
            }

            $offset++;
        }

        return $dictionaries;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function outputIntentFromDictionary(string $dictionary, array $objects): ?array
    {
        $subtype = $this->dictionaryStringValue($dictionary, 'S');
        $identifier = $this->dictionaryStringValue($dictionary, 'OutputConditionIdentifier');
        $condition = $this->dictionaryStringValue($dictionary, 'OutputCondition');
        $registryName = $this->dictionaryStringValue($dictionary, 'RegistryName');
        $info = $this->dictionaryStringValue($dictionary, 'Info');
        $type = $this->dictionaryStringValue($dictionary, 'Type');

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

        $associatedFiles = $this->outputIntentAssociatedFiles($dictionary, $objects);
        if ($associatedFiles !== []) {
            $intent['associated_files'] = $associatedFiles;
        }

        return $intent;
    }

    /**
     * OutputIntent-associated files are review attachments for color-profile or
     * conformance context. They must not become document-level XMP/PDF-A roots.
     *
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function outputIntentAssociatedFiles(string $dictionary, array $objects): array
    {
        $value = $this->dictionaryTopLevelRawValue($dictionary, 'AF');
        if ($value === null) {
            return [];
        }

        $files = [];
        foreach ($this->arrayItemsFromValue($value, $objects) as $index => $fileSpecValue) {
            $file = $this->outputIntentAssociatedFileFromValue($fileSpecValue, $index, $objects);
            if ($file !== null) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function outputIntentAssociatedFileFromValue(string $value, int $index, array $objects): ?array
    {
        return $this->associatedFileReviewFromValue($value, $index, $objects, 'output_intent_associated_files');
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function associatedFileReviewFromValue(string $value, int $index, array $objects, string $source): ?array
    {
        return $this->fileSpecReviewFromValue($value, $index, $objects, $source, true);
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function fileSpecReviewFromValue(
        string $value,
        int $index,
        array $objects,
        string $source,
        bool $associatedFile,
        array $collection = []
    ): ?array
    {
        $fileSpec = $this->resolveDictionaryFromValue($value, $objects);
        if ($fileSpec === null) {
            return null;
        }

        $body = $fileSpec['body'];
        $ef = $this->resolveDictionaryFromValue($this->dictionaryTopLevelRawValue($body, 'EF'), $objects);
        if ($ef === null) {
            return null;
        }

        $unicodeFilename = $this->dictionaryStringValue($body, 'UF');
        $filename = $unicodeFilename
            ?? $this->firstDictionaryString($body, ['F', 'DOS', 'Unix', 'Mac'])
            ?? 'embedded-file';
        $file = [
            'source' => $source,
            'name' => $filename,
            'filename' => $filename,
            'file_spec_object' => $fileSpec['object'],
        ];
        if ($associatedFile) {
            $file['associated_file'] = true;
            $file['associated_file_index'] = $index;
        } else {
            $file['name_tree_file'] = true;
            $file['name_tree_index'] = $index;
        }

        if ($unicodeFilename !== null && $unicodeFilename !== '') {
            $file['unicode_filename'] = $unicodeFilename;
        }

        foreach ([
            'description' => $this->dictionaryStringValue($body, 'Desc'),
            'relationship' => $this->dictionaryNameValue($body, 'AFRelationship', $objects),
            'language' => $this->dictionaryStringValue($body, 'Lang'),
        ] as $key => $metadataValue) {
            if (is_string($metadataValue) && $metadataValue !== '') {
                $file[$key] = $metadataValue;
            }
        }

        $streamMetadata = null;
        foreach ($this->embeddedFileKeys($unicodeFilename !== null) as $efKey) {
            $streamValue = $this->dictionaryTopLevelRawValue($ef['body'], $efKey);
            if ($streamValue === null) {
                continue;
            }

            $streamMetadata = $this->embeddedFileStreamReviewMetadata($streamValue, $objects);
            if ($streamMetadata === null) {
                continue;
            }

            $file['ef_key'] = $efKey;
            foreach ($streamMetadata as $key => $metadataValue) {
                $file[$key] = $metadataValue;
            }
            break;
        }

        if ($streamMetadata === null) {
            return null;
        }

        $metadataReview = $this->reviewValueFromRaw($this->dictionaryTopLevelRawValue($body, 'Metadata'), $objects);
        if (is_array($metadataReview) && $metadataReview !== []) {
            $file['metadata_review'] = $metadataReview;
        }

        $outputIntentReview = $this->reviewValueFromRaw($this->dictionaryTopLevelRawValue($body, 'OutputIntents'), $objects);
        if (is_array($outputIntentReview) && $outputIntentReview !== []) {
            $file['output_intents_review'] = $outputIntentReview;
        }

        $pieceInfo = $this->pieceInfoMetadata($this->dictionaryTopLevelRawValue($body, 'PieceInfo'), $objects);
        if ($pieceInfo !== []) {
            $file['piece_info'] = $pieceInfo;
        }

        if ($collection !== []) {
            $collectionItemValue = $this->dictionaryTopLevelRawValue($body, 'CI');
            $collectionItem = $this->collectionItemMetadata($collectionItemValue, $objects);
            if ($collectionItem !== []) {
                $file['collection_item'] = $collectionItem;
            }

            $fieldValues = $this->collectionFieldValueReview($collection, $collectionItemValue, $objects, $file);
            if ($fieldValues !== []) {
                $file['collection_field_values'] = $fieldValues;
            }
        }

        $relatedFiles = $this->relatedFileReviewRows($this->dictionaryTopLevelRawValue($body, 'RF'), $objects);
        if ($relatedFiles !== []) {
            $file['related_file_count'] = count($relatedFiles);
            $file['related_files'] = $relatedFiles;
        }

        $provenanceReview = $this->associatedFileProvenanceReview($file, $body, $objects);
        if ($provenanceReview !== []) {
            $file['provenance_review'] = $provenanceReview;
        }

        return $file;
    }

    /**
     * FileSpec /RF related files are attachment-local review rows. They extend
     * the primary /EF relationship without exposing related payload bytes as
     * document metadata or visible text.
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
        foreach ($this->dictionaryTopLevelEntries($relatedFiles['body']) as $rfKey => $streamValues) {
            if (!in_array($rfKey, ['F', 'UF', 'DOS', 'Unix', 'Mac'], true)) {
                continue;
            }

            $items = $this->arrayItemsFromValue($streamValues, $objects);
            if ($items === []) {
                $items = [trim($streamValues)];
            }

            foreach ($items as $relatedFileIndex => $streamValue) {
                $streamMetadata = $this->embeddedFileStreamReviewMetadata($streamValue, $objects);
                if ($streamMetadata === null) {
                    continue;
                }

                $row = [
                    'source' => 'filespec_related_files',
                    'rf_key' => $rfKey,
                    'related_file_index' => $relatedFileIndex,
                ];
                foreach ($streamMetadata as $key => $metadataValue) {
                    $row[$key] = $metadataValue;
                }
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Catalog /Names /EmbeddedFiles is a name tree of FileSpec entries. Expose
     * review-only attachment metadata here so document metadata can be reviewed
     * without reading embedded payload bytes into visible WordPress output.
     *
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function catalogEmbeddedFileNameTreeMetadata(string $catalog, array $objects, array $collection = []): array
    {
        $names = $this->resolveDictionaryFromValue($this->dictionaryTopLevelRawValue($catalog, 'Names'), $objects);
        if ($names === null) {
            return [];
        }

        $embeddedFilesRoot = $this->resolveDictionaryFromValue(
            $this->dictionaryTopLevelRawValue($names['body'], 'EmbeddedFiles'),
            $objects
        );
        if ($embeddedFilesRoot === null) {
            return [];
        }

        $files = [];
        $seenObjects = [];
        $this->collectEmbeddedFileNameTreeReviewRows($embeddedFilesRoot, $objects, $files, $seenObjects, 0, null, $collection);

        return $this->dedupeFileSpecReviewRows($files);
    }

    /**
     * @param array{body: string, object: int|null} $node
     * @param array<int, string> $objects
     * @param list<array<string, mixed>> $files
     * @param array<int, true> $seenObjects
     * @param array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}|null $inheritedLimits
     * @param array<string, mixed> $collection
     */
    private function collectEmbeddedFileNameTreeReviewRows(
        array $node,
        array $objects,
        array &$files,
        array &$seenObjects,
        int $depth = 0,
        ?array $inheritedLimits = null,
        array $collection = []
    ): void
    {
        if ($depth > 20) {
            return;
        }

        $objectNumber = $node['object'];
        if ($objectNumber !== null) {
            if (isset($seenObjects[$objectNumber])) {
                return;
            }
            $seenObjects[$objectNumber] = true;
        }

        $limits = $this->nameTreeEffectiveLimits($node, $objects, $inheritedLimits);
        $kids = $this->arrayItemsFromValue($this->dictionaryTopLevelRawValue($node['body'], 'Kids') ?? '', $objects);
        $names = $this->arrayItemsFromValue($this->dictionaryTopLevelRawValue($node['body'], 'Names') ?? '', $objects);
        if ($kids === []) {
            $entryLimits = $this->nameTreeLimitsMatchAnyPairKey($names, $objects, $limits)
                ? $limits
                : $inheritedLimits;
            for ($index = 0, $count = count($names); $index + 1 < $count; $index += 2) {
                $name = $this->destinationNameDetailsFromRaw($names[$index], $objects);
                if (
                    $name === null
                    || $name['text'] === ''
                    || !$this->nameTreeNameWithinLimits($name['text'], $entryLimits, $name['bytes'])
                ) {
                    continue;
                }

                $file = $this->fileSpecReviewFromValue(
                    $names[$index + 1],
                    count($files),
                    $objects,
                    'catalog_names_embedded_files',
                    false,
                    $collection
                );
                if ($file === null) {
                    continue;
                }

                $file['name_tree_name'] = $name['text'];
                $files[] = $file;
            }
        }

        foreach ($kids as $kid) {
            $child = $this->resolveDictionaryFromValue($kid, $objects);
            if ($child !== null) {
                $this->collectEmbeddedFileNameTreeReviewRows($child, $objects, $files, $seenObjects, $depth + 1, $limits, $collection);
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $files
     * @return list<array<string, mixed>>
     */
    private function dedupeFileSpecReviewRows(array $files): array
    {
        $deduped = [];
        $seen = [];
        foreach ($files as $file) {
            $key = implode('|', [
                (string) ($file['source'] ?? ''),
                (string) ($file['name_tree_name'] ?? ''),
                (string) ($file['file_spec_object'] ?? ''),
                (string) ($file['embedded_file_object'] ?? ''),
                (string) ($file['filename'] ?? ''),
            ]);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduped[] = $file;
        }

        return $deduped;
    }

    /**
     * Attachment-local XMP/PDF-A provenance is review metadata. Hash decoded
     * streams, but do not expose payload bytes or promote nested roots.
     *
     * @param array<string, mixed> $file
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function associatedFileProvenanceReview(array $file, string $fileSpecBody, array $objects): array
    {
        $sources = [];
        $metadata = [
            'source' => 'associated_file_provenance',
            'review_only' => true,
            'payload_included' => false,
        ];

        $relationship = $file['relationship'] ?? null;
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

        $payload = $this->associatedFilePayloadProvenance($file);
        if ($payload !== []) {
            $metadata['payload'] = $payload;
            $sources[] = 'embedded_file_payload_hash';
            if (array_key_exists('checksum', $payload) || array_key_exists('computed_checksum', $payload)) {
                $sources[] = 'embedded_file_params_checksum';
            }
        }

        $xmpMetadata = $this->associatedFileMetadataStreamProvenance(
            $this->dictionaryTopLevelRawValue($fileSpecBody, 'Metadata'),
            $objects
        );
        if ($xmpMetadata !== []) {
            $metadata['xmp_metadata'] = $xmpMetadata;
            $sources[] = 'filespec_metadata_stream';
        }

        $pieceInfoXmpMetadata = $this->associatedFilePieceInfoXmpMetadataProvenance(
            $this->dictionaryTopLevelRawValue($fileSpecBody, 'PieceInfo'),
            $objects
        );
        if ($pieceInfoXmpMetadata !== []) {
            $metadata['piece_info_xmp_metadata'] = $pieceInfoXmpMetadata;
            $sources[] = 'filespec_pieceinfo_metadata_stream';
        }

        $pieceInfoPrivateStreams = $this->associatedFilePieceInfoPrivateStreamProvenance(
            $this->dictionaryTopLevelRawValue($fileSpecBody, 'PieceInfo'),
            $objects
        );
        if ($pieceInfoPrivateStreams !== []) {
            $metadata['piece_info_private_streams'] = $pieceInfoPrivateStreams;
            $sources[] = 'filespec_pieceinfo_private_streams';
        }

        $pieceInfoOutputIntents = $this->associatedFilePieceInfoOutputIntentProvenance(
            $this->dictionaryTopLevelRawValue($fileSpecBody, 'PieceInfo'),
            $objects
        );
        if ($pieceInfoOutputIntents !== []) {
            $metadata['piece_info_pdfa_output_intents'] = $pieceInfoOutputIntents;
            $sources[] = 'filespec_pieceinfo_output_intents';
        }

        $outputIntents = $this->associatedFileOutputIntentProvenance(
            $this->dictionaryTopLevelRawValue($fileSpecBody, 'OutputIntents'),
            $objects
        );
        if ($outputIntents !== []) {
            $metadata['pdfa_output_intents'] = $outputIntents;
            $sources[] = 'filespec_output_intents';
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
     * @param array<string, mixed> $file
     * @return array<string, mixed>
     */
    private function associatedFilePayloadProvenance(array $file): array
    {
        $payload = [];

        foreach ([
            'filename' => 'filename',
            'mime_type' => 'mime_type',
        ] as $target => $source) {
            if (isset($file[$source]) && is_string($file[$source]) && $file[$source] !== '') {
                $payload[$target] = $file[$source];
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
        if (isset($file['content_sha256']) && is_string($file['content_sha256']) && $file['content_sha256'] !== '') {
            $payload['sha256'] = $file['content_sha256'];
        }

        foreach ([
            'checksum_algorithm',
            'checksum',
            'computed_checksum',
            'checksum_matches',
        ] as $key) {
            if (array_key_exists($key, $file)) {
                $payload[$key] = $file[$key];
            }
        }

        return $payload;
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
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function associatedFileMetadataStreamProvenance(?string $value, array $objects): array
    {
        if ($value === null) {
            return [];
        }

        $reference = $this->objectReferenceFromValue($value);
        $objectNumber = $reference['objectNumber'] ?? null;
        $objectGeneration = $reference['generation'] ?? null;
        $body = $reference === null
            ? trim($this->resolvePdfValue($value, $objects) ?? $value)
            : $this->objectBodyFromReferenceValue($value, $objects);
        if ($body === null || $body === '') {
            return [];
        }

        $stream = $this->decodeStreamEntryObject($body, $objects);
        if ($stream === null) {
            return [];
        }

        $metadata = [
            'object_number' => $objectNumber,
            'bytes' => strlen($stream['content']),
            'sha256' => hash('sha256', $stream['content']),
            'payload_included' => false,
        ];
        if ($objectGeneration !== null) {
            $metadata['object_generation'] = $objectGeneration;
        }

        foreach ([
            'type' => $this->dictionaryStringValue($stream['dictionary'], 'Type'),
            'subtype' => $this->dictionaryStringValue($stream['dictionary'], 'Subtype'),
        ] as $key => $metadataValue) {
            if (is_string($metadataValue) && $metadataValue !== '') {
                $metadata[$key] = $metadataValue;
            }
        }

        $filters = $this->streamFilters($stream['dictionary'], $objects);
        if ($filters !== []) {
            $metadata['filters'] = $filters;
        }

        $xmpSummary = $this->xmpPacketReviewSummary($stream['content']);
        if ($xmpSummary !== []) {
            $metadata['xmp_summary'] = $xmpSummary;
        }

        return $metadata;
    }

    /**
     * FileSpec /PieceInfo application dictionaries can include private
     * Metadata streams. Summarize those XMP packets without promoting titles,
     * descriptions, authors, keywords, or payload bytes into document metadata.
     *
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function associatedFilePieceInfoXmpMetadataProvenance(?string $pieceInfoValue, array $objects): array
    {
        $pieceInfo = $this->resolveDictionaryFromValue($pieceInfoValue, $objects);
        if ($pieceInfo === null) {
            return [];
        }

        $rows = [];
        foreach ($this->dictionaryTopLevelEntries($pieceInfo['body']) as $application => $pieceValue) {
            $piece = $this->resolveDictionaryFromValue($pieceValue, $objects);
            if ($piece === null) {
                continue;
            }

            $private = $this->resolveDictionaryFromValue(
                $this->dictionaryTopLevelRawValue($piece['body'], 'Private'),
                $objects
            );
            if ($private === null) {
                continue;
            }

            $metadata = $this->associatedFileMetadataStreamProvenance(
                $this->dictionaryTopLevelRawValue($private['body'], 'Metadata'),
                $objects
            );
            if ($metadata === []) {
                continue;
            }

            $row = [
                'application' => $application,
            ] + $metadata;

            $lastModified = $this->reviewStringFromRaw($this->dictionaryTopLevelRawValue($piece['body'], 'LastModified'), $objects);
            if ($lastModified !== null) {
                $row['last_modified'] = $lastModified;
            }

            $rows[] = $row;
        }

        if ($rows === []) {
            return [];
        }

        return [
            'source' => 'filespec_pieceinfo_metadata_stream',
            'review_only' => true,
            'payload_included' => false,
            'count' => count($rows),
            'applications' => $this->uniqueStrings(array_map(
                static fn (array $row): string => (string) $row['application'],
                $rows
            )),
            'metadata_streams' => $rows,
        ];
    }

    /**
     * FileSpec /PieceInfo private streams can carry producer-specific
     * attachment state. Hash and checksum-review those streams without
     * surfacing their bytes in document metadata or visible text.
     *
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function associatedFilePieceInfoPrivateStreamProvenance(?string $pieceInfoValue, array $objects): array
    {
        $pieceInfo = $this->resolveDictionaryFromValue($pieceInfoValue, $objects);
        if ($pieceInfo === null) {
            return [];
        }

        $rows = [];
        foreach ($this->dictionaryTopLevelEntries($pieceInfo['body']) as $application => $pieceValue) {
            $piece = $this->resolveDictionaryFromValue($pieceValue, $objects);
            if ($piece === null) {
                continue;
            }

            $lastModified = $this->reviewStringFromRaw($this->dictionaryTopLevelRawValue($piece['body'], 'LastModified'), $objects);
            foreach ($this->pieceInfoPrivateStreamReviewRows($this->dictionaryTopLevelRawValue($piece['body'], 'Private'), $objects) as $stream) {
                $row = [
                    'application' => $application,
                ] + $stream;

                if ($lastModified !== null) {
                    $row['last_modified'] = $lastModified;
                }

                $rows[] = $row;
            }
        }

        if ($rows === []) {
            return [];
        }

        return [
            'source' => 'filespec_pieceinfo_private_streams',
            'review_only' => true,
            'payload_included' => false,
            'count' => count($rows),
            'applications' => $this->uniqueStrings(array_map(
                static fn (array $row): string => (string) $row['application'],
                $rows
            )),
            'streams' => $rows,
        ];
    }

    /**
     * FileSpec /PieceInfo private dictionaries may carry attachment-local
     * OutputIntents. Summarize those beside the associated file instead of
     * promoting them to document-root PDF/A metadata.
     *
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function associatedFilePieceInfoOutputIntentProvenance(?string $pieceInfoValue, array $objects): array
    {
        $pieceInfo = $this->resolveDictionaryFromValue($pieceInfoValue, $objects);
        if ($pieceInfo === null) {
            return [];
        }

        $rows = [];
        $outputIntentCount = 0;
        $hasPdfaOutputIntent = false;
        $identifiers = [];
        $profileHashes = [];
        foreach ($this->dictionaryTopLevelEntries($pieceInfo['body']) as $application => $pieceValue) {
            $piece = $this->resolveDictionaryFromValue($pieceValue, $objects);
            if ($piece === null) {
                continue;
            }

            $private = $this->resolveDictionaryFromValue(
                $this->dictionaryTopLevelRawValue($piece['body'], 'Private'),
                $objects
            );
            if ($private === null) {
                continue;
            }

            $outputIntents = $this->associatedFileOutputIntentProvenance(
                $this->dictionaryTopLevelRawValue($private['body'], 'OutputIntents'),
                $objects
            );
            if ($outputIntents === []) {
                continue;
            }

            $row = [
                'application' => $application,
                'output_intents' => $outputIntents,
            ];

            $lastModified = $this->reviewStringFromRaw($this->dictionaryTopLevelRawValue($piece['body'], 'LastModified'), $objects);
            if ($lastModified !== null) {
                $row['last_modified'] = $lastModified;
            }

            $rows[] = $row;
            $outputIntentCount += is_int($outputIntents['count'] ?? null) ? $outputIntents['count'] : 0;
            if (($outputIntents['has_pdfa_output_intent'] ?? false) === true) {
                $hasPdfaOutputIntent = true;
            }

            foreach ($outputIntents['output_condition_identifiers'] ?? [] as $identifier) {
                if (is_string($identifier) && $identifier !== '') {
                    $identifiers[] = $identifier;
                }
            }
            foreach ($outputIntents['profile_sha256'] ?? [] as $hash) {
                if (is_string($hash) && $hash !== '') {
                    $profileHashes[] = $hash;
                }
            }
        }

        if ($rows === []) {
            return [];
        }

        return [
            'source' => 'filespec_pieceinfo_output_intents',
            'review_only' => true,
            'payload_included' => false,
            'count' => count($rows),
            'output_intent_count' => $outputIntentCount,
            'has_pdfa_output_intent' => $hasPdfaOutputIntent,
            'output_condition_identifiers' => $this->uniqueStrings($identifiers),
            'profile_sha256' => $this->uniqueStrings($profileHashes),
            'applications' => $this->uniqueStrings(array_map(
                static fn (array $row): string => (string) $row['application'],
                $rows
            )),
            'entries' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function xmpPacketReviewSummary(string $xml): array
    {
        $parsed = $this->parseXmpPacket($xml);
        if ($parsed === []) {
            return $this->xmpPacketSafetyReviewSummary($xml);
        }

        $fieldNames = [];
        foreach (['title', 'description', 'creator_tool', 'producer', 'created_at', 'modified_at', 'metadata_date'] as $field) {
            if (isset($parsed[$field]) && is_string($parsed[$field]) && $parsed[$field] !== '') {
                $fieldNames[] = $field;
            }
        }

        $authors = is_array($parsed['authors'] ?? null) ? array_values($parsed['authors']) : [];
        if ($authors !== []) {
            $fieldNames[] = 'authors';
        }

        $keywords = is_array($parsed['keywords'] ?? null) ? array_values($parsed['keywords']) : [];
        if ($keywords !== []) {
            $fieldNames[] = 'keywords';
        }

        $pdfaExtensionSchemas = is_array($parsed['pdfa_extension_schemas'] ?? null)
            ? array_values($parsed['pdfa_extension_schemas'])
            : [];
        if ($pdfaExtensionSchemas !== []) {
            $fieldNames[] = 'pdfa_extension_schemas';
        }

        $datesUtc = [];
        foreach (['created_at', 'modified_at', 'metadata_date'] as $field) {
            $value = $parsed[$field] ?? null;
            if (!is_string($value) || $value === '') {
                continue;
            }

            $normalized = $this->normalizedDateTimeUtc($value);
            if ($normalized !== null) {
                $datesUtc[$field] = $normalized;
            }
        }

        $summary = [
            'source' => 'xmp_packet_review',
            'field_names' => $fieldNames,
            'field_count' => count($fieldNames),
            'author_count' => count($authors),
            'keyword_count' => count($keywords),
            'packet_encoding' => $parsed['packet_encoding'] ?? 'unknown',
            'payload_included' => false,
            'text_values_redacted' => true,
            'redacted_fields' => ['title', 'description', 'creator_tool', 'producer', 'authors', 'keywords'],
        ];

        if (($parsed['decoded_to_utf8'] ?? false) === true) {
            $summary['decoded_to_utf8'] = true;
        }
        if (($parsed['encoding_fallback'] ?? false) === true) {
            $summary['encoding_fallback'] = true;
        }
        if (($parsed['packet_boundary_applied'] ?? false) === true) {
            $summary['packet_boundary_applied'] = true;
        }
        if ($datesUtc !== []) {
            $summary['dates_utc'] = $datesUtc;
        }
        if ($pdfaExtensionSchemas !== []) {
            $namespaces = [];
            foreach ($pdfaExtensionSchemas as $schema) {
                if (!is_array($schema)) {
                    continue;
                }
                $namespace = $schema['namespace_uri'] ?? null;
                if (is_string($namespace) && $namespace !== '') {
                    $namespaces[] = $namespace;
                }
            }
            $summary['pdfa_extension_schema_count'] = count($pdfaExtensionSchemas);
            $summary['pdfa_extension_schema_namespaces'] = $this->uniqueStrings($namespaces);
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private function xmpPacketSafetyReviewSummary(string $xml): array
    {
        $unsafe = [];
        $packetEncodings = [];
        foreach ($this->xmpXmlCandidates($xml) as $candidate) {
            $candidateUnsafe = $this->xmpXmlCandidateUnsafeMarkup($candidate['xml']);
            if ($candidateUnsafe === []) {
                continue;
            }

            foreach ($candidateUnsafe as $name) {
                $unsafe[$name] = true;
            }

            $packetEncodings[] = $candidate['packet_encoding'];
        }

        if ($unsafe === []) {
            return [];
        }

        return [
            'source' => 'xmp_packet_review',
            'status' => 'rejected_dtd_or_entity_declaration',
            'field_names' => [],
            'field_count' => 0,
            'author_count' => 0,
            'keyword_count' => 0,
            'packet_encoding' => $packetEncodings[0] ?? 'unknown',
            'payload_included' => false,
            'text_values_redacted' => true,
            'redacted_fields' => ['title', 'description', 'creator_tool', 'producer', 'authors', 'keywords'],
            'unsafe_markup' => array_keys($unsafe),
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function associatedFileOutputIntentProvenance(?string $value, array $objects): array
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
     * @return array<string, mixed>|null
     */
    private function outputIntentProvenanceFromDictionary(string $dictionary, array $objects): ?array
    {
        $subtype = $this->dictionaryStringValue($dictionary, 'S');
        $identifier = $this->dictionaryStringValue($dictionary, 'OutputConditionIdentifier');
        $condition = $this->dictionaryStringValue($dictionary, 'OutputCondition');
        $registryName = $this->dictionaryStringValue($dictionary, 'RegistryName');
        $info = $this->dictionaryStringValue($dictionary, 'Info');
        $type = $this->dictionaryStringValue($dictionary, 'Type');

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
        ] as $key => $metadataValue) {
            if (is_string($metadataValue) && $metadataValue !== '') {
                $intent[$key] = $metadataValue;
            }
        }

        $profile = $this->outputProfileMetadata($dictionary, $objects);
        if ($profile !== null) {
            $intent['dest_output_profile'] = $profile;
        }

        return $intent;
    }

    /**
     * @return list<string>
     */
    private function embeddedFileKeys(bool $hasUnicodeFilename): array
    {
        return $hasUnicodeFilename
            ? ['UF', 'F', 'DOS', 'Unix', 'Mac']
            : ['F', 'UF', 'DOS', 'Unix', 'Mac'];
    }

    /**
     * @param list<string> $keys
     */
    private function firstDictionaryString(string $dictionary, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->dictionaryStringValue($dictionary, $key);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function embeddedFileStreamReviewMetadata(string $value, array $objects): ?array
    {
        $objectNumber = $this->objectNumberFromReference($value);
        $body = $objectNumber === null
            ? trim($this->resolvePdfValue($value, $objects) ?? $value)
            : ($objects[$objectNumber] ?? null);
        if ($body === null || $body === '') {
            return null;
        }

        $stream = $this->decodeStreamEntryObject($body, $objects);
        if ($stream === null) {
            return null;
        }

        $metadata = [
            'embedded_file_object' => $objectNumber,
            'size' => strlen($stream['content']),
            'content_sha256' => hash('sha256', $stream['content']),
        ];

        $mimeType = $this->dictionaryNameValue($stream['dictionary'], 'Subtype', $objects);
        if ($mimeType !== null && $mimeType !== '') {
            $metadata['mime_type'] = $mimeType;
        }

        $filters = $this->streamFilters($stream['dictionary'], $objects);
        if ($filters !== []) {
            $metadata['filters'] = $filters;
        }

        foreach ($this->embeddedFileParamsMetadata($stream['dictionary'], $objects, $stream['content']) as $key => $metadataValue) {
            $metadata[$key] = $metadataValue;
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function embeddedFileParamsMetadata(string $streamDictionary, array $objects, string $content): array
    {
        $params = $this->resolveDictionaryFromValue($this->dictionaryTopLevelRawValue($streamDictionary, 'Params'), $objects);
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

        foreach ([
            'CreationDate' => 'created_at',
            'ModDate' => 'modified_at',
        ] as $pdfName => $key) {
            $value = $this->dictionaryStringValue($params['body'], $pdfName);
            if ($value !== null && $value !== '') {
                $metadata[$key] = $value;
            }
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     */
    private function dictionaryChecksumValue(string $dictionary, string $key, array $objects): ?string
    {
        $value = $this->resolvedDictionaryRawValue($dictionary, $key, $objects);
        if ($value === null) {
            return null;
        }

        $bytes = $this->pdfStringBytesFromValue($value, $objects);
        if ($bytes === null || $bytes === '') {
            return null;
        }

        if (strlen($bytes) === 16) {
            return strtolower(bin2hex($bytes));
        }

        $decoded = $this->decodePdfStringBytes($bytes);
        if (preg_match('/^[0-9a-fA-F]{32}$/', $decoded) === 1) {
            return strtolower($decoded);
        }

        return strtolower(bin2hex($bytes));
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function outputProfileMetadata(string $dictionary, array $objects): ?array
    {
        $value = $this->dictionaryRawValue($dictionary, 'DestOutputProfile');
        if ($value === null || preg_match('/^(\d+)\s+\d+\s+R\b/', trim($value), $match) !== 1) {
            return null;
        }

        $objectNumber = (int) $match[1];
        if (!isset($objects[$objectNumber])) {
            return null;
        }

        $profileDictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
        $stream = $this->decodeStreamObject($objects[$objectNumber], $objects);
        if ($profileDictionary === null || $stream === null) {
            return null;
        }

        $profile = [
            'object_number' => $objectNumber,
            'bytes' => strlen($stream),
            'sha256' => hash('sha256', $stream),
        ];

        $components = $this->dictionaryIntegerValue($profileDictionary, 'N');
        if ($components !== null) {
            $profile['color_components'] = $components;
        }

        $alternate = $this->dictionaryStringValue($profileDictionary, 'Alternate');
        if ($alternate !== null) {
            $profile['alternate_color_space'] = $alternate;
        }

        $filters = $this->streamFilters($profileDictionary, $objects);
        if ($filters !== []) {
            $profile['filters'] = $filters;
        }

        return $profile;
    }

    /**
     * @param list<array<string, mixed>> $outputIntents
     * @return array{has_output_intent: bool, output_condition_identifiers: list<string>, profile_sha256: list<string>}|null
     */
    private function pdfaOutputIntentSummary(array $outputIntents): ?array
    {
        $hasPdfaOutputIntent = false;
        $identifiers = [];
        $hashes = [];

        foreach ($outputIntents as $intent) {
            if (($intent['subtype'] ?? null) !== 'GTS_PDFA1') {
                continue;
            }

            $hasPdfaOutputIntent = true;
            if (isset($intent['output_condition_identifier']) && is_string($intent['output_condition_identifier'])) {
                $identifiers[] = $intent['output_condition_identifier'];
            }

            $profile = $intent['dest_output_profile'] ?? null;
            if (is_array($profile) && isset($profile['sha256']) && is_string($profile['sha256'])) {
                $hashes[] = $profile['sha256'];
            }
        }

        $identifiers = $this->uniqueStrings($identifiers);
        $hashes = $this->uniqueStrings($hashes);
        if (!$hasPdfaOutputIntent) {
            return null;
        }

        return [
            'has_output_intent' => true,
            'output_condition_identifiers' => $identifiers,
            'profile_sha256' => $hashes,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function extractViewerPreferences(string $catalog, array $objects): array
    {
        $dictionary = $this->viewerPreferencesDictionary($catalog, $objects);
        if ($dictionary === null) {
            return [];
        }

        $preferences = [];
        foreach ([
            'HideToolbar' => 'hide_toolbar',
            'HideMenubar' => 'hide_menubar',
            'HideWindowUI' => 'hide_window_ui',
            'FitWindow' => 'fit_window',
            'CenterWindow' => 'center_window',
            'DisplayDocTitle' => 'display_doc_title',
            'PickTrayByPDFSize' => 'pick_tray_by_pdf_size',
        ] as $pdfName => $key) {
            $value = $this->dictionaryBooleanValue($dictionary, $pdfName, $objects);
            if ($value !== null) {
                $preferences[$key] = $value;
            }
        }

        foreach (self::VIEWER_PREFERENCE_NAME_VALUES as $pdfName => $definition) {
            $value = $this->dictionaryNameValue($dictionary, $pdfName, $objects);
            if ($value !== null && in_array($value, $definition['allowed'], true)) {
                $preferences[$definition['key']] = $value;
            }
        }

        $printPageRange = $this->dictionaryPositiveIntegerPairArrayValue($dictionary, 'PrintPageRange', $objects);
        if ($printPageRange !== null) {
            $preferences['print_page_range'] = $printPageRange;
        }

        $enforced = $this->dictionaryNameArrayValue($dictionary, 'Enforce', $objects);
        $enforced = array_values(array_filter(
            $enforced,
            static fn (string $name): bool => in_array($name, self::VIEWER_PREFERENCE_ENFORCEABLE_NAMES, true)
        ));
        if ($enforced !== []) {
            $preferences['enforce'] = $this->uniqueStrings($enforced);
        }

        $numCopies = $this->dictionaryIntegerValue($dictionary, 'NumCopies', $objects);
        if ($numCopies !== null && $numCopies > 0) {
            $preferences['num_copies'] = $numCopies;
        }

        return $preferences;
    }

    /**
     * @param array<int, string> $objects
     */
    private function viewerPreferencesDictionary(string $catalog, array $objects): ?string
    {
        $value = $this->dictionaryTopLevelRawValue($catalog, 'ViewerPreferences');
        if ($value === null) {
            return null;
        }

        $resolved = trim($this->resolvePdfValue($value, $objects) ?? $value);
        if ($resolved === '') {
            return null;
        }

        if (str_starts_with($resolved, '<<')) {
            return $this->readPdfDictionaryAt($resolved, 0);
        }

        return $this->dictionaryObjectBody($resolved);
    }

    /**
     * @param array<int, string> $objects
     */
    private function resolvePdfValue(string $value, array $objects): ?string
    {
        $trimmed = $this->trimPdfWhitespaceAndComments($value);
        if (preg_match('/^(\d+)\s+(\d+)\s+R\b/s', $trimmed) !== 1) {
            return $trimmed;
        }

        return $this->objectBodyFromReferenceValue($trimmed, $objects);
    }

    private function dictionaryRawValue(string $dictionary, string $key): ?string
    {
        if (preg_match('/\/' . preg_quote($key, '/') . '\b/s', $dictionary, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $offset = $match[0][1] + strlen($match[0][0]);
        return $this->readPdfValueAt($dictionary, $offset);
    }

    private function dictionaryTopLevelRawValue(string $dictionary, string $key): ?string
    {
        $entries = $this->dictionaryTopLevelEntries($dictionary);

        return $entries[$key] ?? null;
    }

    /**
     * @return list<string>
     */
    private function dictionaryTopLevelRawValues(string $dictionary, string $key): array
    {
        $body = $this->normalizedDictionaryBody($dictionary);
        $values = [];
        for ($offset = 0, $length = strlen($body); $offset < $length;) {
            $offset = $this->skipPdfWhitespace($body, $offset);
            if ($offset >= $length) {
                break;
            }

            if ($body[$offset] !== '/') {
                $offset = $this->skipNonDictionaryKeyToken($body, $offset);
                continue;
            }

            $remaining = substr($body, $offset);
            if (preg_match('/\/([^\s\[\]()<>{}\/%]+)/A', $remaining, $match) !== 1) {
                $offset++;
                continue;
            }

            $valueOffset = $this->skipPdfWhitespace($body, $offset + strlen($match[0]));
            $value = $this->readPdfValueAt($body, $valueOffset);
            if ($value === null) {
                $offset += strlen($match[0]);
                continue;
            }

            if ($this->decodePdfName($match[1]) === $key) {
                $values[] = $value;
            }

            $offset = $valueOffset + strlen($value);
        }

        return $values;
    }

    /**
     * @return array<string, string>
     */
    private function dictionaryTopLevelEntries(string $dictionary): array
    {
        $body = $this->normalizedDictionaryBody($dictionary);
        $entries = [];
        for ($offset = 0, $length = strlen($body); $offset < $length;) {
            $offset = $this->skipPdfWhitespace($body, $offset);
            if ($offset >= $length) {
                break;
            }

            if ($body[$offset] !== '/') {
                $offset = $this->skipNonDictionaryKeyToken($body, $offset);
                continue;
            }

            $remaining = substr($body, $offset);
            if (preg_match('/\/([^\s\[\]()<>{}\/%]+)/A', $remaining, $match) !== 1) {
                $offset++;
                continue;
            }

            $valueOffset = $this->skipPdfWhitespace($body, $offset + strlen($match[0]));
            $value = $this->readPdfValueAt($body, $valueOffset);
            if ($value === null) {
                $offset += strlen($match[0]);
                continue;
            }

            $entries[$this->decodePdfName($match[1])] = $value;
            $offset = $valueOffset + strlen($value);
        }

        return $entries;
    }

    private function normalizedDictionaryBody(string $dictionary): string
    {
        $trimmed = $this->trimPdfWhitespaceAndComments($dictionary);
        if (str_starts_with($trimmed, '<<')) {
            $body = $this->readPdfDictionaryAt($trimmed, 0);
            $after = $body === null ? null : $this->skipPdfWhitespace($trimmed, strlen($body) + 4);
            if (
                $body !== null
                && is_int($after)
                && ($after >= strlen($trimmed) || $this->pdfKeywordAt($trimmed, $after, 'stream'))
            ) {
                return $body;
            }
        }

        return $dictionary;
    }

    private function skipNonDictionaryKeyToken(string $body, int $offset): int
    {
        $length = strlen($body);
        if ($offset >= $length) {
            return $offset;
        }

        $char = $body[$offset];
        if ($char === '%') {
            return $this->lineCommentEndOffset($body, $offset);
        }

        if ($char === '(') {
            return $this->literalTokenEndOffset($body, $offset);
        }

        if ($char === '[') {
            $array = $this->readPdfArrayAt($body, $offset);
            return $array === null ? $length : $offset + strlen($array);
        }

        if (substr($body, $offset, 2) === '<<') {
            $dictionary = $this->readPdfDictionaryAt($body, $offset);
            return $dictionary === null ? $length : $offset + strlen($dictionary) + 4;
        }

        if ($char === '<') {
            $end = strpos($body, '>', $offset + 1);
            return $end === false ? $length : $end + 1;
        }

        if (preg_match('/[^\s\[\]()<>{}\/%]+/A', substr($body, $offset), $match) === 1) {
            return $offset + strlen($match[0]);
        }

        return $offset + 1;
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

        $reference = $this->objectReferenceFromValue($value);
        if ($reference !== null) {
            $objectBody = $this->objectBodyForReference($objects, $reference['objectNumber'], $reference['generation']);
            if ($objectBody === null) {
                return null;
            }

            $body = $this->dictionaryObjectBody($objectBody);
            return $body === null ? null : ['body' => $body, 'object' => $reference['objectNumber']];
        }

        $resolved = $this->trimPdfWhitespaceAndComments($this->resolvePdfValue($value, $objects) ?? $value);
        if ($resolved === '') {
            return null;
        }

        if (str_starts_with($resolved, '<<')) {
            $body = $this->readPdfDictionaryAt($resolved, 0);
            return $body === null ? null : ['body' => $body, 'object' => null];
        }

        $body = $this->dictionaryObjectBody($resolved);
        return $body === null ? null : ['body' => $body, 'object' => null];
    }

    private function objectNumberFromReference(string $value): ?int
    {
        return preg_match('/^(\d+)\s+\d+\s+R\b/s', $this->trimPdfWhitespaceAndComments($value), $match) === 1 ? (int) $match[1] : null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function validObjectNumberFromReference(?string $value, array $objects): ?int
    {
        $reference = $this->objectReferenceFromValue($value);
        if ($reference === null) {
            return null;
        }

        return $this->objectBodyForReference($objects, $reference['objectNumber'], $reference['generation']) === null
            ? null
            : $reference['objectNumber'];
    }

    /**
     * @param array<int, string> $objects
     */
    private function objectBodyFromReferenceValue(string $value, array $objects): ?string
    {
        $reference = $this->objectReferenceFromValue($value);
        if ($reference === null) {
            return null;
        }

        return $this->objectBodyForReference($objects, $reference['objectNumber'], $reference['generation']);
    }

    /**
     * @param array<int, string> $objects
     */
    private function objectBodyForReference(array $objects, int $objectNumber, int $generation): ?string
    {
        if ($objectNumber <= 0 || $generation < 0 || !isset($objects[$objectNumber])) {
            return null;
        }

        $owner = $this->currentObjectReferenceOwners[$objectNumber] ?? null;
        if ($owner !== null && $objects[$objectNumber] === $owner['body']) {
            return $owner['generation'] === $generation ? $owner['body'] : null;
        }

        return $objects[$objectNumber];
    }

    /**
     * @return array{objectNumber: int, generation: int}|null
     */
    private function objectReferenceFromValue(?string $value): ?array
    {
        if ($value === null || preg_match('/^(\d+)\s+(\d+)\s+R\b/s', $this->trimPdfWhitespaceAndComments($value), $match) !== 1) {
            return null;
        }

        return [
            'objectNumber' => (int) $match[1],
            'generation' => (int) $match[2],
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function arrayItemsFromValue(string $value, array $objects): array
    {
        $resolved = $this->trimPdfWhitespaceAndComments($this->resolvePdfValue($value, $objects) ?? $value);
        $body = $this->arrayBody($resolved);
        if ($body === null) {
            return [];
        }

        $items = [];
        for ($offset = 0, $length = strlen($body); $offset < $length;) {
            $offset = $this->skipPdfWhitespace($body, $offset);
            if ($offset >= $length) {
                break;
            }

            $item = $this->readPdfValueAt($body, $offset);
            if ($item === null) {
                $offset++;
                continue;
            }

            $items[] = $item;
            $offset += strlen($item);
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

        $resolved = $this->trimPdfWhitespaceAndComments($this->resolvePdfValue($value, $objects) ?? $value);
        if ($resolved === '') {
            return null;
        }

        if (str_starts_with($resolved, '[')) {
            $items = [];
            foreach ($this->arrayItemsFromValue($resolved, $objects) as $item) {
                $reviewValue = $this->reviewValueFromRaw($item, $objects, $depth + 1);
                if ($reviewValue !== null && $reviewValue !== '') {
                    $items[] = $reviewValue;
                }
            }

            return $items === [] ? null : $items;
        }

        if (str_starts_with($resolved, '<<')) {
            $body = $this->readPdfDictionaryAt($resolved, 0);
            if ($body === null) {
                return null;
            }

            $metadata = [];
            foreach ($this->dictionaryTopLevelEntries($body) as $name => $entryValue) {
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

        return $this->stringValueFromRaw($resolved);
    }

    /**
     * @param array<int, string> $objects
     */
    private function reviewStringFromRaw(?string $value, array $objects): ?string
    {
        $reviewValue = $this->reviewValueFromRaw($value, $objects);

        return is_string($reviewValue) && $reviewValue !== '' ? $reviewValue : null;
    }

    /**
     * Outline title strings may be indirect, but the referenced object must be
     * one scalar token. Otherwise a malformed object like `(Title) /A ...`
     * would leak a partial title while silently discarding action tokens.
     *
     * @param array<int, string> $objects
     */
    private function reviewOutlineTitleFromRaw(?string $value, array $objects): ?string
    {
        if ($value === null || !$this->rawValueIsSinglePdfStringOrNameToken($value, $objects)) {
            return null;
        }

        return $this->reviewStringFromRaw($value, $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function rawValueIsSinglePdfStringOrNameToken(string $value, array $objects): bool
    {
        $candidate = $value;
        $reference = $this->objectReferenceFromValue($value);
        if ($reference !== null) {
            $candidate = $this->objectBodyForReference($objects, $reference['objectNumber'], $reference['generation']);
            if ($candidate === null) {
                return false;
            }
        }

        $offset = $this->skipPdfWhitespace($candidate, 0);
        $token = $this->readPdfValueAt($candidate, $offset);
        if ($token === null || $token === '') {
            return false;
        }

        $first = $token[0];
        $isStringOrName = $first === '('
            || $first === '/'
            || ($first === '<' && ($token[1] ?? '') !== '<');
        if (!$isStringOrName) {
            return false;
        }

        $after = $this->skipPdfWhitespace($candidate, $offset + strlen($token));

        return $after >= strlen($candidate);
    }

    /**
     * @param array<int, string> $objects
     * @return list<mixed>
     */
    private function reviewListFromRaw(?string $value, array $objects): array
    {
        $reviewValue = $this->reviewValueFromRaw($value, $objects);
        if (is_array($reviewValue)) {
            return array_values($reviewValue);
        }

        return ($reviewValue === null || $reviewValue === '') ? [] : [$reviewValue];
    }

    private function stringValueFromRaw(string $value): ?string
    {
        $value = $this->trimPdfWhitespaceAndComments($value);
        if ($value === '') {
            return null;
        }

        return match ($value[0]) {
            '(' => $this->literalStringValueAt($value, 0),
            '<' => (strlen($value) > 1 && $value[1] !== '<') ? $this->hexStringValueAt($value, 0) : null,
            '/' => $this->nameValueAt($value, 0),
            default => preg_match('/^[^\s\[\]()<>{}\/%]+$/', $value) === 1 ? $this->cleanText($value) : null,
        };
    }

    private function readPdfValueAt(string $value, int $offset): ?string
    {
        $length = strlen($value);
        $offset = $this->skipPdfWhitespace($value, $offset);

        if ($offset >= $length) {
            return null;
        }

        if ($value[$offset] === '[') {
            return $this->readPdfArrayAt($value, $offset);
        }

        if (substr($value, $offset, 2) === '<<') {
            $dictionary = $this->readPdfDictionaryAt($value, $offset);
            return $dictionary === null ? null : '<<' . $dictionary . '>>';
        }

        $remaining = substr($value, $offset);
        if (preg_match('/\d+\s+\d+\s+R\b/A', $remaining, $match) === 1) {
            return $match[0];
        }

        if ($value[$offset] === '(') {
            $end = $this->literalTokenEndOffset($value, $offset);
            return $end <= $offset ? null : substr($value, $offset, $end - $offset);
        }

        if ($value[$offset] === '<' && $offset + 1 < $length && $value[$offset + 1] !== '<') {
            $end = strpos($value, '>', $offset + 1);
            return $end === false ? null : substr($value, $offset, $end - $offset + 1);
        }

        if (preg_match('/\/[^\s\[\]()<>{}\/%]+|[^\s\[\]()<>{}\/%]+/A', $remaining, $match) === 1) {
            return $match[0];
        }

        return null;
    }

    private function readPdfArrayAt(string $value, int $offset): ?string
    {
        if ($offset >= strlen($value) || $value[$offset] !== '[') {
            return null;
        }

        $depth = 0;
        $literalDepth = 0;
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];

            if ($literalDepth > 0) {
                if ($char === '\\') {
                    $index++;
                    continue;
                }
                if ($char === '(') {
                    $literalDepth++;
                    continue;
                }
                if ($char === ')') {
                    $literalDepth--;
                }
                continue;
            }

            if ($char === '%') {
                $index = $this->lineCommentEndOffset($value, $index) - 1;
                continue;
            }

            if ($char === '(') {
                $literalDepth = 1;
                continue;
            }

            if ($char === '<' && $index + 1 < $length && $value[$index + 1] !== '<') {
                $end = strpos($value, '>', $index + 1);
                if ($end === false) {
                    return null;
                }
                $index = $end;
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
                return substr($value, $offset, $index - $offset + 1);
            }
        }

        return null;
    }

    private function arrayBody(string $arrayValue): ?string
    {
        $array = $this->readPdfArrayAt(trim($arrayValue), 0);
        if ($array === null || strlen($array) < 2) {
            return null;
        }

        return substr($array, 1, -1);
    }

    /**
     * @param array<int, string>|null $objects
     */
    private function dictionaryIntegerValue(string $dictionary, string $key, ?array $objects = null): ?int
    {
        $value = $this->resolvedDictionaryRawValue($dictionary, $key, $objects);
        if ($value === null || preg_match('/^[+-]?\d+$/', trim($value)) !== 1) {
            return null;
        }

        return (int) trim($value);
    }

    /**
     * @param array<int, string>|null $objects
     */
    private function dictionaryBooleanValue(string $dictionary, string $key, ?array $objects = null): ?bool
    {
        $value = $this->resolvedDictionaryRawValue($dictionary, $key, $objects);
        if ($value === null) {
            return null;
        }

        return match (trim($value)) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }

    /**
     * @return list<int>
     */
    private function dictionaryIntegerArrayValue(string $dictionary, string $key, ?array $objects = null): array
    {
        $value = $this->resolvedDictionaryRawValue($dictionary, $key, $objects);
        if ($value === null) {
            return [];
        }

        $body = $this->arrayBody(trim($value));
        if ($body === null) {
            return [];
        }

        preg_match_all('/-?\d+/', $body, $matches);
        return array_map('intval', $matches[0]);
    }

    /**
     * @param array<int, string> $objects
     * @return list<int>|null
     */
    private function dictionaryPositiveIntegerPairArrayValue(string $dictionary, string $key, array $objects): ?array
    {
        $values = $this->dictionaryIntegerArrayValue($dictionary, $key, $objects);
        if ($values === [] || count($values) % 2 !== 0) {
            return null;
        }

        foreach ($values as $value) {
            if ($value < 1) {
                return null;
            }
        }

        return $values;
    }

    /**
     * @return list<string>
     * @param array<int, string>|null $objects
     */
    private function dictionaryNameArrayValue(string $dictionary, string $key, ?array $objects = null): array
    {
        $value = $this->resolvedDictionaryRawValue($dictionary, $key, $objects);
        if ($value === null) {
            return [];
        }

        $body = $this->arrayBody(trim($value));
        if ($body === null) {
            return [];
        }

        preg_match_all('/\/([^\s\[\]()<>{}\/%]+)/', $body, $matches);
        $names = [];
        foreach ($matches[1] as $name) {
            $names[] = $this->decodePdfName($name);
        }

        return $this->uniqueStrings($names);
    }

    /**
     * @param array<int, string> $objects
     */
    private function dictionaryNameValue(string $dictionary, string $key, array $objects): ?string
    {
        $value = $this->resolvedDictionaryRawValue($dictionary, $key, $objects);
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || $value[0] !== '/') {
            return null;
        }

        if (preg_match('/^\/([^\s\[\]()<>{}\/%]+)/', $value, $match) !== 1) {
            return null;
        }

        return $this->decodePdfName($match[1]);
    }

    /**
     * @param array<int, string>|null $objects
     */
    private function resolvedDictionaryRawValue(string $dictionary, string $key, ?array $objects): ?string
    {
        $value = $this->dictionaryTopLevelRawValue($dictionary, $key);
        if ($value === null || $objects === null) {
            return $value;
        }

        return $this->resolvePdfValue($value, $objects);
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

    private function dictionaryObjectBody(string $objectBody): ?string
    {
        $offset = strpos($objectBody, '<<');
        return $offset === false ? null : $this->readPdfDictionaryAt($objectBody, $offset);
    }

    /**
     * @param array<int, string> $objects
     */
    private function dictionaryTopLevelStringValue(string $dictionary, string $key, array $objects): ?string
    {
        return $this->reviewStringFromRaw($this->dictionaryTopLevelRawValue($dictionary, $key), $objects);
    }

    private function readPdfDictionaryAt(string $value, int $offset): ?string
    {
        if (substr($value, $offset, 2) !== '<<') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 2;
        for ($index = $offset, $length = strlen($value); $index < $length - 1; $index++) {
            $char = $value[$index];
            if ($char === '%') {
                $index = $this->lineCommentEndOffset($value, $index) - 1;
                continue;
            }

            if ($char === '(') {
                $end = $this->literalTokenEndOffset($value, $index);
                $index = max($index, $end - 1);
                continue;
            }

            if ($char === '<' && ($value[$index + 1] ?? '') !== '<') {
                $end = strpos($value, '>', $index + 1);
                $index = $end === false ? $length : $end;
                continue;
            }

            if ($char === '[') {
                $array = $this->readPdfArrayAt($value, $index);
                if ($array !== null) {
                    $index += strlen($array) - 1;
                    continue;
                }
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
                return substr($value, $bodyStart, $index - $bodyStart);
            }
            $index++;
        }

        return null;
    }

    private function trimPdfWhitespaceAndComments(string $value): string
    {
        return trim(substr($value, $this->skipPdfWhitespace($value, 0)));
    }

    private function dictionaryStringValue(string $dictionary, string $key): ?string
    {
        if (preg_match('/\/' . preg_quote($key, '/') . '\b/s', $dictionary, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $offset = $this->skipPdfWhitespace($dictionary, $match[0][1] + strlen($match[0][0]));

        if ($offset >= strlen($dictionary)) {
            return null;
        }

        return match ($dictionary[$offset]) {
            '(' => $this->literalStringValueAt($dictionary, $offset),
            '<' => ($offset + 1 < strlen($dictionary) && $dictionary[$offset + 1] !== '<')
                ? $this->hexStringValueAt($dictionary, $offset)
                : null,
            '/' => $this->nameValueAt($dictionary, $offset),
            default => $this->plainValueAt($dictionary, $offset),
        };
    }

    private function literalStringValueAt(string $value, int $offset): ?string
    {
        $depth = 0;
        $body = '';
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '\\') {
                if ($index + 1 < $length) {
                    $body .= $char . $value[$index + 1];
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
                    return $this->decodePdfStringBytes($this->decodeLiteralEscapes($body));
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

    private function hexStringValueAt(string $value, int $offset): ?string
    {
        $end = strpos($value, '>', $offset + 1);
        if ($end === false) {
            return null;
        }

        $hex = preg_replace('/\s+/', '', substr($value, $offset + 1, $end - $offset - 1));
        if ($hex === null || preg_match('/^[\da-fA-F]*$/', $hex) !== 1) {
            return null;
        }
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $bytes = hex2bin($hex);
        return $bytes === false ? null : $this->decodePdfStringBytes($bytes);
    }

    private function nameValueAt(string $value, int $offset): ?string
    {
        if (preg_match('/\/([^\s\[\]()<>{}\/%]+)/A', substr($value, $offset), $match) !== 1) {
            return null;
        }

        return $this->decodePdfName($match[1]);
    }

    private function plainValueAt(string $value, int $offset): ?string
    {
        if (preg_match('/([^\s\[\]()<>{}\/%]+)/A', substr($value, $offset), $match) !== 1) {
            return null;
        }

        return $this->cleanText($match[1]);
    }

    private function decodeLiteralEscapes(string $value): string
    {
        $out = '';
        for ($index = 0, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char !== '\\') {
                $out .= $char;
                continue;
            }

            if ($index + 1 >= $length) {
                continue;
            }

            $next = $value[++$index];
            if ($next === "\r" || $next === "\n") {
                if ($next === "\r" && $index + 1 < $length && $value[$index + 1] === "\n") {
                    $index++;
                }
                continue;
            }

            if ($next >= '0' && $next <= '7') {
                $octal = $next;
                for ($count = 0; $count < 2 && $index + 1 < $length; $count++) {
                    $candidate = $value[$index + 1];
                    if ($candidate < '0' || $candidate > '7') {
                        break;
                    }
                    $octal .= $candidate;
                    $index++;
                }
                $out .= chr(octdec($octal) & 0xff);
                continue;
            }

            $out .= match ($next) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\x0c",
                default => $next,
            };
        }

        return $out;
    }

    private function decodePdfStringBytes(string $bytes): string
    {
        if (str_starts_with($bytes, "\xfe\xff")) {
            $utf16 = substr($bytes, 2);
            if (strlen($utf16) % 2 !== 0 || !mb_check_encoding($utf16, 'UTF-16BE')) {
                return '';
            }

            $decoded = @iconv('UTF-16BE', 'UTF-8//IGNORE', $utf16);
            return $decoded === false ? '' : $this->cleanText($decoded) ?? '';
        }

        if (str_starts_with($bytes, "\xff\xfe")) {
            $utf16 = substr($bytes, 2);
            if (strlen($utf16) % 2 !== 0 || !mb_check_encoding($utf16, 'UTF-16LE')) {
                return '';
            }

            $decoded = @iconv('UTF-16LE', 'UTF-8//IGNORE', $utf16);
            return $decoded === false ? '' : $this->cleanText($decoded) ?? '';
        }

        return $this->cleanText($this->decodePdfDocEncoding($bytes)) ?? '';
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

    private function decodePdfName(string $name): string
    {
        return preg_replace_callback('/#([\da-fA-F]{2})/', static fn (array $match): string => chr(hexdec($match[1])), $name) ?? $name;
    }

    private function cleanText(string $value): ?string
    {
        $clean = preg_replace('/\s+/u', ' ', trim($value));
        if ($clean === null || $clean === '') {
            return null;
        }

        return $clean;
    }

    /**
     * @param list<string|mixed> $values
     * @return list<string>
     */
    private function cleanList(array $values): array
    {
        $out = [];
        foreach ($values as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $clean = $this->cleanText((string) $value);
            if ($clean !== null && !in_array($clean, $out, true)) {
                $out[] = $clean;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function splitKeywords(string $keywords): array
    {
        return $this->cleanList(preg_split('/\s*[,;]\s*/', $keywords) ?: []);
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private function uniqueStrings(array $values): array
    {
        $out = [];
        foreach ($values as $value) {
            if ($value !== '' && !in_array($value, $out, true)) {
                $out[] = $value;
            }
        }

        return $out;
    }
}
