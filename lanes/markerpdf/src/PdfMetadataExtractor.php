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

    private const NS_DC = 'http://purl.org/dc/elements/1.1/';
    private const NS_PDF = 'http://ns.adobe.com/pdf/1.3/';
    private const NS_RDF = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    private const NS_XMP = 'http://ns.adobe.com/xap/1.0/';
    private const NS_XML = 'http://www.w3.org/XML/1998/namespace';

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
     *     pdfa?: array{has_output_intent: bool, output_condition_identifiers: list<string>, profile_sha256: list<string>}
     * }
     */
    public function extractDocumentMetadata(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $catalog = $this->extractCatalogReviewMetadata($pdfBytes, $objects);
        $xmp = $this->extractXmpMetadata($pdfBytes, $objects);
        $info = $this->extractInfoMetadata($pdfBytes, $objects);
        $outputIntents = $this->extractOutputIntentMetadata($pdfBytes, $objects);
        $trailerIds = $this->extractTrailerIdMetadata($pdfBytes);
        $encryption = $this->extractEncryptionMetadata($pdfBytes, $objects);

        return $this->mergedMetadata($xmp, $info, $outputIntents, $catalog, $trailerIds, $encryption);
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function extractXmpMetadata(string $pdfBytes, array $objects): array
    {
        $catalog = $this->catalogObjectBody($pdfBytes, $objects);
        if ($catalog === null || preg_match('/\/Metadata\s+(\d+)\s+\d+\s+R\b/s', $catalog, $match) !== 1) {
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

        $value = $this->dictionaryRawValue($catalog, 'OutputIntents');
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

        $permissionValue = $this->dictionaryIntegerValue($dictionary, 'P');
        if ($permissionValue !== null) {
            $metadata['standard_permissions'] = $this->standardPermissionMetadata($permissionValue, $revision);
        }

        $perms = $this->encryptedPermissionValidationMetadata($dictionary, $objects);
        if ($perms !== null) {
            $metadata['perms'] = $perms;
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     * @return array{body: string, object: int|null, source: string}|null
     */
    private function encryptionDictionaryEntry(string $pdfBytes, array $objects): ?array
    {
        $trailer = $this->trailerDictionaryBody($pdfBytes);
        if ($trailer !== null) {
            $value = $this->dictionaryRawValue($trailer, 'Encrypt');
            $entry = $value === null ? null : $this->resolvedEncryptionDictionary($value, $objects, 'trailer_encrypt');
            if ($entry !== null) {
                return $entry;
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
     * @return array{signed: int, unsigned: int, hex: string, allowed: list<string>, denied: list<string>, print_quality: string}
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

        return [
            'signed' => $signed,
            'unsigned' => $unsigned,
            'hex' => strtoupper(sprintf('%08X', $unsigned)),
            'allowed' => $allowed,
            'denied' => $denied,
            'print_quality' => !$canPrint ? 'disallowed' : ($effectiveRevision >= 3 && !$highQuality ? 'low_resolution' : 'high_resolution'),
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
        if ($encryption !== []) {
            $result['source'][] = 'encryption';
            $result['encryption'] = $encryption;
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

        foreach (['language', 'page_layout', 'page_mode', 'viewer_preferences'] as $field) {
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
        $xml = trim($xml);
        if ($xml === '' || !str_contains($xml, '<')) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return [];
        }

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
        if (!preg_match_all('/(\d+)\s+\d+\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER)) {
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

        return $body;
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeStreamObject(string $objectBody, array $objects): ?string
    {
        if (!preg_match('/<<(.*?)>>\s*stream\r?\n?(.*?)\r?\n?endstream/s', $objectBody, $match)) {
            return null;
        }

        return $this->decodeStream($match[1], $match[2], $objects);
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

        return $intent;
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
            $value = $this->dictionaryBooleanValue($dictionary, $pdfName);
            if ($value !== null) {
                $preferences[$key] = $value;
            }
        }

        foreach ([
            'NonFullScreenPageMode' => 'non_full_screen_page_mode',
            'Direction' => 'direction',
            'ViewArea' => 'view_area',
            'ViewClip' => 'view_clip',
            'PrintArea' => 'print_area',
            'PrintClip' => 'print_clip',
            'PrintScaling' => 'print_scaling',
            'Duplex' => 'duplex',
        ] as $pdfName => $key) {
            $value = $this->dictionaryStringValue($dictionary, $pdfName);
            if ($value !== null) {
                $preferences[$key] = $value;
            }
        }

        $printPageRange = $this->dictionaryIntegerArrayValue($dictionary, 'PrintPageRange');
        if ($printPageRange !== []) {
            $preferences['print_page_range'] = $printPageRange;
        }

        $enforced = $this->dictionaryNameArrayValue($dictionary, 'Enforce');
        if ($enforced !== []) {
            $preferences['enforce'] = $enforced;
        }

        $numCopies = $this->dictionaryIntegerValue($dictionary, 'NumCopies');
        if ($numCopies !== null) {
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

    private function dictionaryIntegerValue(string $dictionary, string $key): ?int
    {
        $value = $this->dictionaryRawValue($dictionary, $key);
        if ($value === null || preg_match('/^-?\d+$/', trim($value)) !== 1) {
            return null;
        }

        return (int) trim($value);
    }

    private function dictionaryBooleanValue(string $dictionary, string $key): ?bool
    {
        $value = $this->dictionaryRawValue($dictionary, $key);
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
    private function dictionaryIntegerArrayValue(string $dictionary, string $key): array
    {
        $value = $this->dictionaryRawValue($dictionary, $key);
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
     * @return list<string>
     */
    private function dictionaryNameArrayValue(string $dictionary, string $key): array
    {
        $value = $this->dictionaryRawValue($dictionary, $key);
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

        return $this->cleanText($bytes) ?? '';
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
