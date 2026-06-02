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

    private const NS_DC = 'http://purl.org/dc/elements/1.1/';
    private const NS_PDF = 'http://ns.adobe.com/pdf/1.3/';
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
     *     info: array<string, string>,
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
     *     page_layout?: string,
     *     page_mode?: string,
     *     viewer_preferences?: array<string, mixed>,
     *     collection?: array<string, mixed>,
     *     associated_files?: list<array<string, mixed>>,
     *     structure_tree?: array<string, mixed>,
     *     document_destinations?: array<string, mixed>,
     *     pdfa?: array{has_output_intent: bool, output_condition_identifiers: list<string>, profile_sha256: list<string>}
     * }
     */
    public function extractDocumentMetadata(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $encryption = $this->extractEncryptionMetadata($pdfBytes, $objects);
        $metadataSourcePolicy = $this->encryptedMetadataSourcePolicy($pdfBytes, $objects, $encryption);
        if ($metadataSourcePolicy !== []) {
            $encryption['metadata_source_policy'] = $metadataSourcePolicy;
        }

        $catalog = $this->extractCatalogReviewMetadata($pdfBytes, $objects);
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

        return [
            'encrypted_document' => true,
            'decryption_performed' => false,
            'encrypt_metadata' => !$encryptMetadata ? false : true,
            'encrypt_metadata_explicit' => (bool) ($encryption['encrypt_metadata_explicit'] ?? false),
            'xmp_stream_policy' => $xmpPolicy,
            'info_dictionary_policy' => $infoPolicy,
            'output_intents_policy' => $outputIntentPolicy,
            'suppressed_sources' => $suppressed,
            'preserved_sources' => $preserved,
            'raw_encrypted_metadata_parsed' => false,
        ];
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

        $objectNumber = (int) $match[1];
        if (!isset($objects[$objectNumber])) {
            return [];
        }

        $stream = $this->decodeStreamObject($objects[$objectNumber], $objects);
        if ($stream === null) {
            return [];
        }

        return $this->parseXmpPacket($stream);
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, string>
     */
    private function extractInfoMetadata(string $pdfBytes, array $objects): array
    {
        $trailer = $this->trailerDictionaryBody($pdfBytes);
        if ($trailer === null || preg_match('/\/Info\s+(\d+)\s+\d+\s+R\b/s', $trailer, $match) !== 1) {
            return [];
        }

        $objectNumber = (int) $match[1];
        if (!isset($objects[$objectNumber])) {
            return [];
        }

        $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
        if ($dictionary === null) {
            return [];
        }

        $fields = [];
        foreach (['Title', 'Author', 'Subject', 'Keywords', 'Creator', 'Producer', 'CreationDate', 'ModDate'] as $key) {
            $value = $this->dictionaryStringValue($dictionary, $key);
            if ($value !== null) {
                $fields[$key] = $value;
            }
        }

        return $fields;
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
        $language = $this->dictionaryStringValue($catalog, 'Lang');
        if ($language !== null) {
            $metadata['language'] = $language;
        }

        foreach ([
            'PageLayout' => 'page_layout',
            'PageMode' => 'page_mode',
        ] as $pdfName => $key) {
            $value = $this->dictionaryStringValue($catalog, $pdfName);
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

        $documentDestinations = $this->documentDestinationMetadata($catalog, $objects);
        if ($documentDestinations !== []) {
            $metadata['document_destinations'] = $documentDestinations;
        }

        return $metadata;
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

        return $file;
    }

    /**
     * @param array<string, mixed> $collection
     * @param array<int, string> $objects
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

        $catalogLanguage = $this->dictionaryStringValue($catalog, 'Lang');
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
        $isStructElement = $type === 'StructElem' || $rawRole !== null;
        if (!$isStructElement) {
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

        $destinationsByName = [];
        foreach ($entries as $entry) {
            if (!isset($destinationsByName[$entry['name']])) {
                $destinationsByName[$entry['name']] = $entry['value'];
            }
        }

        $destinations = [];
        $unresolved = 0;
        foreach ($entries as $entry) {
            $details = $this->documentDestinationDetails(
                $entry['value'],
                $objects,
                $pageIndexes,
                $destinationsByName,
                $entry['name']
            );
            if ($details === null) {
                $unresolved++;
                continue;
            }

            $details['name'] = $entry['name'];
            $details['destination'] = $entry['name'];
            $details['source'] = $entry['source'];
            $destinations[] = $details;
        }

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
     * @param array<int, string> $objects
     * @return list<int>
     */
    private function orderedDestinationPageObjectNumbers(string $catalog, array $objects): array
    {
        $pagesValue = $this->dictionaryTopLevelRawValue($catalog, 'Pages');
        $pagesRoot = $pagesValue === null ? null : $this->objectNumberFromReference($pagesValue);
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
            $kidObjectNumber = $this->objectNumberFromReference($kid);
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
     */
    private function collectDestinationNameTreeEntries(array $node, array $objects, array &$entries, array &$seenObjects, int $depth = 0): void
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

        $names = $this->arrayItemsFromValue($this->dictionaryTopLevelRawValue($node['body'], 'Names') ?? '', $objects);
        for ($index = 0, $count = count($names); $index + 1 < $count; $index += 2) {
            $name = $this->destinationNameFromRaw($names[$index], $objects);
            if ($name === null || $name === '') {
                continue;
            }

            $entries[] = [
                'name' => $name,
                'value' => $names[$index + 1],
                'source' => 'names_dests',
            ];
        }

        $kids = $this->arrayItemsFromValue($this->dictionaryTopLevelRawValue($node['body'], 'Kids') ?? '', $objects);
        foreach ($kids as $kid) {
            $child = $this->resolveDictionaryFromValue($kid, $objects);
            if ($child !== null) {
                $this->collectDestinationNameTreeEntries($child, $objects, $entries, $seenObjects, $depth + 1);
            }
        }
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

        $resolved = trim($this->resolvePdfValue($trimmed, $objects) ?? $trimmed);
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
        $viewPosition = [];
        for ($index = 2, $count = count($items); $index < $count; $index++) {
            $viewPosition[] = $this->destinationNumericValue($items[$index], $objects);
        }

        if ($viewMode === 'XYZ' && array_key_exists(2, $viewPosition) && $viewPosition[2] === 0.0) {
            $viewPosition[2] = null;
        }

        return $this->documentDestinationRow($page, $destinationName, $viewMode, $viewPosition);
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

        $objectNumber = $this->objectNumberFromReference($trimmed);
        if ($objectNumber !== null && isset($pageIndexes[$objectNumber])) {
            return [
                'page' => $pageIndexes[$objectNumber],
                'page_object' => $objectNumber,
            ];
        }

        $resolved = trim($this->resolvePdfValue($trimmed, $objects) ?? $trimmed);
        $resolvedObjectNumber = $this->objectNumberFromReference($resolved);
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
     */
    private function destinationNumericValue(string $value, array $objects): ?float
    {
        $resolved = trim($this->resolvePdfValue($value, $objects) ?? $value);
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

            $private = $this->reviewValueFromRaw($this->dictionaryTopLevelRawValue($piece['body'], 'Private'), $objects);
            if ($private !== null && $private !== '' && (!is_array($private) || $private !== [])) {
                $entry['private'] = $private;
            }

            if ($entry !== []) {
                $metadata[$application] = $entry;
            }
        }

        return $metadata;
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
        $version = $this->dictionaryIntegerValue($dictionary, 'V');
        $revision = $this->dictionaryIntegerValue($dictionary, 'R');
        $keyLength = $this->dictionaryIntegerValue($dictionary, 'Length');
        $encryptMetadata = $this->dictionaryBooleanValue($dictionary, 'EncryptMetadata');

        $metadata = [
            'is_encrypted' => true,
            'source' => $entry['source'],
            'review_only' => true,
            'requires_password_for_content_extraction' => true,
        ];

        if ($entry['object'] !== null) {
            $metadata['object_number'] = $entry['object'];
        }

        $filter = $this->dictionaryStringValue($dictionary, 'Filter');
        if ($filter !== null) {
            $metadata['filter'] = $filter;
        }

        $subfilter = $this->dictionaryStringValue($dictionary, 'SubFilter');
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

        $metadata['encrypt_metadata'] = $encryptMetadata ?? true;
        $metadata['encrypt_metadata_explicit'] = $encryptMetadata !== null;

        foreach ([
            'StmF' => 'stream_filter',
            'StrF' => 'string_filter',
            'EFF' => 'embedded_file_filter',
        ] as $pdfName => $key) {
            $value = $this->dictionaryStringValue($dictionary, $pdfName);
            if ($value !== null) {
                $metadata[$key] = $value;
            }
        }

        $cryptFilters = $this->cryptFilterMetadata($dictionary, $objects);
        if ($cryptFilters !== []) {
            $metadata['crypt_filters'] = $cryptFilters;
        }

        $publicKeyRecipientReview = $this->publicKeyRecipientReview($dictionary, $objects, $cryptFilters);
        if ($publicKeyRecipientReview !== []) {
            $metadata['public_key_recipient_review'] = $publicKeyRecipientReview;
        }

        $perms = $this->encryptedPermissionValidationMetadata($dictionary, $objects);
        if ($perms !== null) {
            $metadata['perms'] = $perms;
        }

        $permissionValue = $this->dictionaryIntegerValue($dictionary, 'P');
        if ($permissionValue !== null) {
            $metadata['standard_permissions'] = $this->standardPermissionMetadata($permissionValue, $revision);
        }

        $authReview = $this->standardAuthenticationReview($dictionary, $objects, $cryptFilters, $metadata, $perms);
        if ($authReview !== []) {
            $metadata['standard_authentication_review'] = $authReview;
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     * @return array{body: string, object: int|null, source: string}|null
     */
    private function encryptionDictionaryEntry(string $pdfBytes, array $objects): ?array
    {
        $trailerEntry = $this->trailerDictionaryEntry($pdfBytes);
        if ($trailerEntry !== null) {
            $trailer = $trailerEntry['body'];
            $value = $this->dictionaryRawValue($trailer, 'Encrypt');
            $source = $trailerEntry['source'] === 'xref_stream_trailer'
                ? 'xref_stream_trailer_encrypt'
                : 'trailer_encrypt';
            if ($value !== null) {
                if (trim($value) === 'null') {
                    return null;
                }

                $entry = $this->resolvedEncryptionDictionary($value, $objects, $source);
                if ($entry !== null) {
                    return $entry;
                }
            }
        }

        foreach ($objects as $objectNumber => $body) {
            if (preg_match('/\/Type\s*\/XRef\b/s', $body) !== 1) {
                continue;
            }

            $value = $this->dictionaryRawValue($body, 'Encrypt');
            $entry = $value === null ? null : $this->resolvedEncryptionDictionary($value, $objects, 'xref_stream_encrypt');
            if ($entry !== null) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @return array{body: string, object: int|null, source: string}|null
     */
    private function resolvedEncryptionDictionary(string $value, array $objects, string $source): ?array
    {
        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === 'null') {
            return null;
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/s', $trimmed, $match) === 1) {
            $objectNumber = (int) $match[1];
            if (!isset($objects[$objectNumber])) {
                return null;
            }

            $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
            return $dictionary === null ? null : [
                'body' => $dictionary,
                'object' => $objectNumber,
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
     * @return array<string, mixed>
     */
    private function standardPermissionMetadata(int $signed, ?int $revision): array
    {
        $unsigned = $signed < 0 ? $signed + 4294967296 : $signed;
        $effectiveRevision = $revision ?? 6;
        $allowed = [];
        $denied = [];

        foreach (self::STANDARD_PERMISSION_FLAGS as $flag) {
            if ($effectiveRevision < $flag['minimum_revision']) {
                continue;
            }

            if (($unsigned & $flag['mask']) !== 0) {
                $allowed[] = $flag['name'];
                continue;
            }

            $denied[] = $flag['name'];
        }

        $canPrint = in_array('print', $allowed, true);
        $highQuality = in_array('high_quality_print', $allowed, true);
        $reserved = $this->standardPermissionReservedBitsMetadata($unsigned, $effectiveRevision);

        return array_merge([
            'signed' => $signed,
            'unsigned' => $unsigned,
            'hex' => strtoupper(sprintf('%08X', $unsigned)),
            'effective_revision' => $effectiveRevision,
            'allowed' => $allowed,
            'denied' => $denied,
            'print_quality' => !$canPrint ? 'disallowed' : ($effectiveRevision >= 3 && !$highQuality ? 'low_resolution' : 'high_resolution'),
        ], $reserved);
    }

    /**
     * @return array{reserved_bits_valid: bool, permission_word_status: string, reserved_bits: array<string, mixed>}
     */
    private function standardPermissionReservedBitsMetadata(int $unsigned, int $effectiveRevision): array
    {
        $expectedSetMask = $effectiveRevision < 3 ? 0xFFFFFFC0 : 0xFFFFF0C0;
        $expectedClearMask = 0x00000003;
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
        $value = $this->dictionaryRawValue($dictionary, 'CF');
        if ($value === null) {
            return [];
        }

        $resolved = trim($this->resolvePdfValue($value, $objects) ?? $value);
        $body = str_starts_with($resolved, '<<')
            ? $this->readPdfDictionaryAt($resolved, 0)
            : $this->dictionaryObjectBody($resolved);
        if ($body === null) {
            return [];
        }

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

            if (substr($body, $offset, 2) !== '<<') {
                continue;
            }

            $filterBody = $this->readPdfDictionaryAt($body, $offset);
            if ($filterBody === null) {
                $offset += 2;
                continue;
            }

            $metadata = [];
            $method = $this->dictionaryStringValue($filterBody, 'CFM');
            if ($method !== null) {
                $metadata['method'] = $method;
            }

            $authEvent = $this->dictionaryStringValue($filterBody, 'AuthEvent');
            if ($authEvent !== null) {
                $metadata['auth_event'] = $authEvent;
            }

            $lengthBytes = $this->dictionaryIntegerValue($filterBody, 'Length');
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

            $offset += strlen($filterBody) + 4;
        }

        return $filters;
    }

    /**
     * @param array<int, string> $objects
     * @return array{bytes: int, sha256: string}|null
     */
    private function encryptedPermissionValidationMetadata(string $dictionary, array $objects): ?array
    {
        $value = $this->dictionaryRawValue($dictionary, 'Perms');
        if ($value === null) {
            return null;
        }

        $bytes = $this->pdfStringBytesFromValue($value, $objects);
        if ($bytes === null) {
            return null;
        }

        return [
            'bytes' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
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
     * @param array{bytes: int, sha256: string}|null $perms
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
        $value = $this->dictionaryRawValue($dictionary, $pdfName);
        $bytes = $value === null ? null : $this->pdfStringBytesFromValue($value, $objects);
        $length = $bytes === null ? null : strlen($bytes);
        $lengthValid = $length !== null && $expectedBytes !== null ? $length === $expectedBytes : ($length === null ? null : true);

        return [
            'pdf_name' => $pdfName,
            'purpose' => $purpose,
            'required_for_revision' => $required,
            'present' => $value !== null,
            'bytes_resolved' => $bytes !== null,
            'bytes' => $length,
            'expected_bytes' => $expectedBytes,
            'length_valid' => $lengthValid,
            'sha256' => $bytes === null ? null : hash('sha256', $bytes),
            'status' => $this->standardAuthenticationEntryStatus($value !== null, $bytes !== null, $lengthValid, $required),
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
     * @param array{bytes: int, sha256: string}|null $perms
     */
    private function standardPermissionDigestStatus(?array $perms, ?int $expectedBytes, ?int $revision): string
    {
        if ($perms === null) {
            return $revision !== null && $revision >= 5
                ? 'required_permission_digest_missing'
                : 'permission_digest_absent_for_legacy_revision';
        }

        if ($expectedBytes !== null && $perms['bytes'] !== $expectedBytes) {
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
    private function publicKeyRecipientReview(string $dictionary, array $objects, array $cryptFilters): array
    {
        $handler = $this->dictionaryStringValue($dictionary, 'Filter');
        $subfilter = $this->dictionaryStringValue($dictionary, 'SubFilter');
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

        $cryptFilterSelection = $this->publicKeyRecipientCryptFilterSelection($dictionary, $cryptFilters);

        return [
            'source' => 'public_key_security_handler',
            'handler' => $handler,
            'subfilter' => $subfilter,
            'recipient_source_policy' => $this->publicKeyRecipientSourcePolicy($subfilter, $topLevelRecipients !== null, $cryptFilterRecipientNames !== []),
            'recipient_count' => $recipientCount,
            'recipient_bytes' => $recipientBytes,
            'unresolved_recipient_count' => $unresolvedCount,
            'recipient_sha256' => $recipientHashes,
            'crypt_filter_recipient_filter_names' => $this->uniqueStrings($cryptFilterRecipientNames),
            'selected_crypt_filter_recipient_filter_names' => $cryptFilterSelection['selected_recipient_filter_names'],
            'unselected_crypt_filter_recipient_filter_names' => $cryptFilterSelection['unselected_recipient_filter_names'],
            'selected_recipient_count' => $cryptFilterSelection['selected_recipient_count'],
            'selected_recipient_bytes' => $cryptFilterSelection['selected_recipient_bytes'],
            'selected_unresolved_recipient_count' => $cryptFilterSelection['selected_unresolved_recipient_count'],
            'selected_recipient_sha256' => $cryptFilterSelection['selected_recipient_sha256'],
            'crypt_filter_selection' => $cryptFilterSelection,
            'recipient_lists' => $recipientLists,
            'permissions_available_in_recipient_envelopes' => $recipientCount > 0,
            'selected_permissions_available_in_recipient_envelopes' => $cryptFilterSelection['selected_recipient_count'] > 0,
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
    private function publicKeyRecipientCryptFilterSelection(string $dictionary, array $cryptFilters): array
    {
        $declared = [];
        $selectedRows = [];
        $selectedNames = [];
        $selectedRecipientNames = [];
        $selectedRecipientHashes = [];
        $selectedRecipientCount = 0;
        $selectedRecipientBytes = 0;
        $selectedUnresolvedRecipientCount = 0;
        $countedRecipientFilters = [];

        foreach ([
            'stream_filter' => 'StmF',
            'string_filter' => 'StrF',
            'embedded_file_filter' => 'EFF',
        ] as $role => $pdfName) {
            $name = $this->dictionaryStringValue($dictionary, $pdfName);
            if ($name === null) {
                continue;
            }

            $declared[$role] = $name;
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

        if (preg_match('/^(\d+)\s+\d+\s+R\b/s', $trimmed, $match) === 1) {
            $objectNumber = (int) $match[1];
            return isset($objects[$objectNumber]) ? $this->pdfStringBytesFromValue($objects[$objectNumber], $objects) : null;
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

        $authors = $xmp['authors'] ?? $this->authorsFromInfo($info);
        if (is_array($authors) && $authors !== []) {
            $result['authors'] = array_values($authors);
        }

        $keywords = $xmp['keywords'] ?? $this->keywordsFromInfo($info);
        if (is_array($keywords) && $keywords !== []) {
            $result['keywords'] = array_values($keywords);
        }

        foreach (['language', 'page_layout', 'page_mode', 'viewer_preferences', 'collection', 'associated_files', 'structure_tree', 'document_destinations'] as $field) {
            if (array_key_exists($field, $catalog)) {
                $result[$field] = $catalog[$field];
            }
        }

        $pdfa = $this->pdfaOutputIntentSummary($outputIntents);
        if ($pdfa !== null) {
            $result['pdfa'] = $pdfa;
        }

        return $result;
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
            $previous = libxml_use_internal_errors(true);
            $document = new DOMDocument();
            $loaded = $document->loadXML($candidate['xml'], LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            if (!$loaded) {
                continue;
            }

            $metadata = $this->metadataFromXmpDocument($document);
            if ($metadata !== []) {
                $metadata['packet_encoding'] = $candidate['packet_encoding'];
                if ($candidate['decoded_to_utf8']) {
                    $metadata['decoded_to_utf8'] = true;
                }
                if ($candidate['encoding_fallback']) {
                    $metadata['encoding_fallback'] = true;
                }
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

        return $metadata;
    }

    private function looksLikeXmlPacket(string $xml): bool
    {
        return str_contains($xml, '<')
            || str_starts_with($xml, "\xfe\xff")
            || str_starts_with($xml, "\xff\xfe")
            || str_starts_with($xml, "\xef\xbb\xbf");
    }

    /**
     * @return list<array{xml: string, packet_encoding: string, encoding_fallback: bool, decoded_to_utf8: bool}>
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
     * @param list<array{xml: string, packet_encoding: string, encoding_fallback: bool, decoded_to_utf8: bool}> $candidates
     */
    private function addXmpXmlCandidate(array &$candidates, string $xml, string $packetEncoding, bool $encodingFallback, bool $decodedToUtf8): void
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
        ];
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
        $elements = $document->getElementsByTagNameNS($namespace, $localName);
        if ($elements->length > 0) {
            $element = $elements->item(0);
            if ($element instanceof DOMElement) {
                $value = $preferAlt ? $this->preferredAltText($element) : $this->cleanText($element->textContent);
                if ($value !== null) {
                    return $value;
                }
            }
        }

        foreach ($document->getElementsByTagNameNS(self::NS_RDF, 'Description') as $description) {
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
        $elements = $document->getElementsByTagNameNS($namespace, $localName);
        if ($elements->length > 0) {
            $element = $elements->item(0);
            if ($element instanceof DOMElement) {
                $values = [];
                foreach ($element->getElementsByTagNameNS(self::NS_RDF, 'li') as $item) {
                    $value = $this->cleanText($item->textContent);
                    if ($value !== null) {
                        $values[] = $value;
                    }
                }

                if ($values !== []) {
                    return $this->cleanList($values);
                }

                $value = $this->cleanText($element->textContent);
                return $value === null ? [] : [$value];
            }
        }

        foreach ($document->getElementsByTagNameNS(self::NS_RDF, 'Description') as $description) {
            if (!$description instanceof DOMElement || !$description->hasAttributeNS($namespace, $localName)) {
                continue;
            }

            $value = $this->cleanText($description->getAttributeNS($namespace, $localName));
            return $value === null ? [] : $this->splitKeywords($value);
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
        foreach ($element->getElementsByTagNameNS(self::NS_RDF, 'li') as $item) {
            if (!$item instanceof DOMElement) {
                continue;
            }

            $value = $this->cleanText($item->textContent);
            if ($value === null) {
                continue;
            }

            $first ??= $value;
            if ($item->getAttributeNS(self::NS_XML, 'lang') === 'x-default') {
                return $value;
            }
        }

        return $first ?? $this->cleanText($element->textContent);
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
        }

        ksort($objects, SORT_NUMERIC);
        return $objects;
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
                'offset' => $match[1][1],
                'body' => $match[3][0],
            ];
        }

        ksort($definitions, SORT_NUMERIC);
        return $definitions;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
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

        if (($xrefEntry['type'] ?? 1) !== 1) {
            return null;
        }

        $offset = $xrefEntry['offset'] ?? null;
        if ($offset !== null) {
            foreach ($definitions as $definition) {
                if ($definition['offset'] === $offset) {
                    return $definition;
                }
            }

            if (($xrefEntry['offsetIsExplicit'] ?? true) === true) {
                return null;
            }
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
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}>
     */
    private function xrefEntriesFromStartxrefChain(string $pdfBytes, array $objects, array $definitions): array
    {
        $offset = $this->latestStartxrefOffset($pdfBytes);
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

        $tableSection = $this->xrefTableSectionAt($pdfBytes, $offset);
        if ($tableSection !== null) {
            $entries = $tableSection['entries'];
            $hybridStreamOffset = $this->dictionaryIntegerValue($tableSection['trailer'], 'XRefStm');
            if ($hybridStreamOffset !== null && $hybridStreamOffset >= 0 && !isset($seenOffsets[$hybridStreamOffset])) {
                foreach ($this->xrefStreamEntriesAtOffset($hybridStreamOffset, $objects, $definitions) as $objectNumber => $entry) {
                    $entries[$objectNumber] ??= $entry;
                }
            }

            $previousOffset = $this->dictionaryIntegerValue($tableSection['trailer'], 'Prev');
            if ($previousOffset !== null && $previousOffset >= 0) {
                foreach ($this->xrefEntriesFromOffsetChain($pdfBytes, $previousOffset, $objects, $definitions, $seenOffsets) as $objectNumber => $entry) {
                    $entries[$objectNumber] ??= $entry;
                }
            }

            return $entries;
        }

        $streamSection = $this->xrefStreamSectionAtOffset($offset, $definitions);
        if ($streamSection === null) {
            return [];
        }

        $entries = $this->xrefStreamEntriesFromDefinition($streamSection['definition'], $objects);
        $previousOffset = $this->dictionaryIntegerValue($streamSection['body'], 'Prev');
        if ($previousOffset !== null && $previousOffset >= 0) {
            foreach ($this->xrefEntriesFromOffsetChain($pdfBytes, $previousOffset, $objects, $definitions, $seenOffsets) as $objectNumber => $entry) {
                $entries[$objectNumber] ??= $entry;
            }
        }

        return $entries;
    }

    /**
     * @return array{entries: array<int, array{type: int, generation: int, offset: int, offsetIsExplicit: bool}>, trailer: string}|null
     */
    private function xrefTableSectionAt(string $pdfBytes, int $offset): ?array
    {
        $offset = $this->skipPdfWhitespace($pdfBytes, $offset);
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

        $trailer = $this->readPdfDictionaryAt($pdfBytes, $dictionaryOffset);
        if ($trailer === null) {
            return null;
        }

        return [
            'entries' => $this->xrefTableRows(substr($pdfBytes, $sectionBodyOffset, $trailerOffset - $sectionBodyOffset)),
            'trailer' => $trailer,
        ];
    }

    /**
     * @return array<int, array{type: int, generation: int, offset: int, offsetIsExplicit: bool}>
     */
    private function xrefTableRows(string $sectionBody): array
    {
        $entries = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($sectionBody));
        if ($lines === false) {
            return $entries;
        }

        for ($lineIndex = 0, $lineCount = count($lines); $lineIndex < $lineCount; $lineIndex++) {
            $line = trim($lines[$lineIndex]);
            if (preg_match('/^(\d+)\s+(\d+)$/', $line, $header) !== 1) {
                continue;
            }

            $startObject = (int) $header[1];
            $count = max(0, (int) $header[2]);
            for ($entryIndex = 0; $entryIndex < $count && $lineIndex + 1 < $lineCount; $entryIndex++) {
                $row = trim($lines[++$lineIndex]);
                if (preg_match('/^(\d{10})\s+(\d{5})\s+([nf])\b/', $row, $rowMatch) !== 1) {
                    continue;
                }

                $entries[$startObject + $entryIndex] = [
                    'type' => $rowMatch[3] === 'n' ? 1 : 0,
                    'generation' => (int) $rowMatch[2],
                    'offset' => (int) $rowMatch[1],
                    'offsetIsExplicit' => true,
                ];
            }
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
        return $section === null ? [] : $this->xrefStreamEntriesFromDefinition($section['definition'], $objects);
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
     * @return array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}>
     */
    private function xrefStreamEntriesFromDefinition(array $definition, array $objects): array
    {
        $entries = [];
        $body = $definition['body'];
        $widthValue = $this->dictionaryTopLevelRawValue($body, 'W');
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

        $decoded = $this->decodeStreamObject($body, $objects);
        if ($decoded === null) {
            return $entries;
        }

        $decodedEntryCount = strlen($decoded) % $entryWidth === 0 ? intdiv(strlen($decoded), $entryWidth) : null;
        $offset = 0;
        foreach ($this->xrefIndexRanges($body, $decodedEntryCount) as $range) {
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

                if ($type === 0) {
                    $entries[$objectNumber] = [
                        'type' => 0,
                        'generation' => $fieldThree,
                        'offset' => $fieldTwo,
                        'offsetIsExplicit' => $widths[1] > 0,
                    ];
                } elseif ($type === 1) {
                    $entries[$objectNumber] = [
                        'type' => 1,
                        'offset' => $fieldTwo,
                        'generation' => $fieldThree,
                        'offsetIsExplicit' => $widths[1] > 0,
                    ];
                } elseif ($type === 2 && $fieldTwo > 0) {
                    $entries[$objectNumber] = [
                        'type' => 2,
                        'objectStream' => $fieldTwo,
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
     * @return list<array{0: int, 1: int}>
     */
    private function xrefIndexRanges(string $xrefBody, ?int $decodedEntryCount): array
    {
        $indexValue = $this->dictionaryTopLevelRawValue($xrefBody, 'Index');
        $indexBody = $indexValue === null ? null : $this->arrayBody($indexValue);
        if ($indexBody !== null) {
            $values = $this->integersFromPdfArray($indexBody);
            $ranges = [];
            for ($index = 0, $count = count($values); $index + 1 < $count; $index += 2) {
                $ranges[] = [max(0, $values[$index]), max(0, $values[$index + 1])];
            }

            return $ranges;
        }

        $size = $this->dictionaryIntegerValue($xrefBody, 'Size');
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
        $trailer = $this->trailerDictionaryBody($pdfBytes);
        if ($trailer !== null && preg_match('/\/Root\s+(\d+)\s+\d+\s+R\b/s', $trailer, $match) === 1) {
            $objectNumber = (int) $match[1];
            if (isset($objects[$objectNumber])) {
                return $objects[$objectNumber];
            }
        }

        foreach ($objects as $body) {
            if (preg_match('/\/Type\s*\/Catalog\b/', $body) === 1) {
                return $body;
            }
        }

        return null;
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
        $startxrefOffset = $this->latestStartxrefOffset($pdfBytes);
        if ($startxrefOffset !== null) {
            $trailer = $this->trailerDictionaryBodyAtOffset($pdfBytes, $startxrefOffset);
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
        if (preg_match_all('/\bstartxref\s+(\d+)/s', $pdfBytes, $matches, PREG_SET_ORDER) < 1) {
            return null;
        }

        $latest = end($matches);
        if (!is_array($latest)) {
            return null;
        }

        return max(0, (int) $latest[1]);
    }

    private function trailerDictionaryBodyAtOffset(string $pdfBytes, int $offset): ?string
    {
        $offset = $this->skipPdfWhitespace($pdfBytes, $offset);
        if (substr($pdfBytes, $offset, 4) === 'xref') {
            return $this->xrefTableTrailerDictionaryAtOffset($pdfBytes, $offset);
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

    private function xrefTableTrailerDictionaryAtOffset(string $pdfBytes, int $offset): ?string
    {
        $trailerOffset = strpos($pdfBytes, 'trailer', $offset + 4);
        if ($trailerOffset === false) {
            return null;
        }

        $dictionaryOffset = strpos($pdfBytes, '<<', $trailerOffset);
        return $dictionaryOffset === false ? null : $this->readPdfDictionaryAt($pdfBytes, $dictionaryOffset);
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
        while ($offset < $length && ctype_space($pdfBytes[$offset])) {
            $offset++;
        }

        return $offset;
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
        if (!preg_match('/<<(.*?)>>\s*stream\r?\n?(.*?)\r?\n?endstream/s', $objectBody, $match)) {
            return null;
        }

        $content = $this->decodeStream($match[1], $match[2], $objects);
        return $content === null ? null : [
            'dictionary' => $match[1],
            'content' => $content,
        ];
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
            ? $this->filterNamesFromValue($objects[$objectNumber], $objects)
            : [];
    }

    /**
     * @param array<int, string> $objects
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
                foreach ($this->filterNamesFromValue($objects[$objectNumber], $objects) as $filter) {
                    $filters[] = $filter;
                }
            }
        }

        return $filters;
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
            'associated_file' => true,
            'associated_file_index' => $index,
            'name' => $filename,
            'filename' => $filename,
            'file_spec_object' => $fileSpec['object'],
        ];

        if ($unicodeFilename !== null && $unicodeFilename !== '') {
            $file['unicode_filename'] = $unicodeFilename;
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

        $metadataReview = $this->reviewValueFromRaw($this->dictionaryTopLevelRawValue($body, 'Metadata'), $objects);
        if (is_array($metadataReview) && $metadataReview !== []) {
            $file['metadata_review'] = $metadataReview;
        }

        $outputIntentReview = $this->reviewValueFromRaw($this->dictionaryTopLevelRawValue($body, 'OutputIntents'), $objects);
        if (is_array($outputIntentReview) && $outputIntentReview !== []) {
            $file['output_intents_review'] = $outputIntentReview;
        }

        $provenanceReview = $this->associatedFileProvenanceReview($file, $body, $objects);
        if ($provenanceReview !== []) {
            $file['provenance_review'] = $provenanceReview;
        }

        return $file;
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

        $outputIntents = $this->associatedFileOutputIntentProvenance(
            $this->dictionaryTopLevelRawValue($fileSpecBody, 'OutputIntents'),
            $objects
        );
        if ($outputIntents !== []) {
            $metadata['pdfa_output_intents'] = $outputIntents;
            $sources[] = 'filespec_output_intents';
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
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function associatedFileMetadataStreamProvenance(?string $value, array $objects): array
    {
        if ($value === null) {
            return [];
        }

        $objectNumber = $this->objectNumberFromReference($value);
        $body = $objectNumber === null
            ? trim($this->resolvePdfValue($value, $objects) ?? $value)
            : ($objects[$objectNumber] ?? null);
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

        return $metadata;
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
        $value = $this->dictionaryRawValue($catalog, 'ViewerPreferences');
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
        $trimmed = trim($value);
        if (preg_match('/^(\d+)\s+\d+\s+R\b/s', $trimmed, $match) !== 1) {
            return $trimmed;
        }

        $objectNumber = (int) $match[1];
        return $objects[$objectNumber] ?? null;
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
                $offset++;
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
        $trimmed = trim($dictionary);
        if (str_starts_with($trimmed, '<<')) {
            return $this->readPdfDictionaryAt($trimmed, 0) ?? $dictionary;
        }

        return $dictionary;
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

        $resolved = trim($this->resolvePdfValue($value, $objects) ?? $value);
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
        return preg_match('/^(\d+)\s+\d+\s+R\b/s', trim($value), $match) === 1 ? (int) $match[1] : null;
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function arrayItemsFromValue(string $value, array $objects): array
    {
        $resolved = trim($this->resolvePdfValue($value, $objects) ?? $value);
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

        $resolved = trim($this->resolvePdfValue($value, $objects) ?? $value);
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
        $value = trim($value);
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
        while ($offset < $length && ctype_space($value[$offset])) {
            $offset++;
        }

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
            $literal = $this->literalStringValueAt($value, $offset);
            return $literal === null ? null : '(' . $literal . ')';
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
        if ($value === null || preg_match('/^-?\d+$/', trim($value)) !== 1) {
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
        $value = $this->dictionaryRawValue($dictionary, $key);
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

    private function readPdfDictionaryAt(string $value, int $offset): ?string
    {
        if (substr($value, $offset, 2) !== '<<') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 2;
        for ($index = $offset, $length = strlen($value); $index < $length - 1; $index++) {
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

    private function dictionaryStringValue(string $dictionary, string $key): ?string
    {
        if (preg_match('/\/' . preg_quote($key, '/') . '\b/s', $dictionary, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $offset = $match[0][1] + strlen($match[0][0]);
        while ($offset < strlen($dictionary) && ctype_space($dictionary[$offset])) {
            $offset++;
        }

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
            $decoded = iconv('UTF-16BE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $this->cleanText($decoded) ?? '';
        }

        if (str_starts_with($bytes, "\xff\xfe")) {
            $decoded = iconv('UTF-16LE', 'UTF-8//IGNORE', substr($bytes, 2));
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
