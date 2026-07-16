<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$encryptedObjectXrefObjectStream = static function (array $members): array {
    $data = '';
    $pairs = [];
    $indexes = [];
    foreach ($members as $objectNumber => $body) {
        $pairs[] = $objectNumber . ' ' . strlen($data);
        $indexes[$objectNumber] = count($indexes);
        $data .= $body . "\n";
    }

    $header = implode(' ', $pairs);
    $compressed = gzcompress($header . "\n" . $data);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress encrypted object/xref fixture object stream.');
    }

    return [
        'count' => count($members),
        'first' => strlen($header) + 1,
        'indexes' => $indexes,
        'content' => $compressed,
    ];
};

$encryptedObjectXrefXmp = static function (string $title, string $description): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>2026-06-19T23:26:00Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$encryptedObjectXrefCompressedEncryptPdf = static function () use (
    $encryptedObjectXrefObjectStream,
    $encryptedObjectXrefXmp
): array {
    $staleText = 'BT /F1 12 Tf 72 720 Td (Stale compressed Encrypt duplicate text leak) Tj ET';
    $currentText = 'BT /F1 12 Tf 72 720 Td (Current compressed Encrypt text must stay blocked) Tj ET';
    $staleOwnerKey = 'STALE_OWNER_AESV2_SHOULD_NOT_SURFACE';
    $staleUserKey = 'STALE_USER_AESV2_SHOULD_NOT_SURFACE';
    $currentOwnerKey = str_repeat('O', 32);
    $currentUserKey = str_repeat('U', 32);
    $hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';
    $currentXmp = gzcompress($encryptedObjectXrefXmp(
        'Current Compressed Encrypt XMP Title',
        'Compressed Encrypt object selected through xref stream'
    ));
    if (!is_string($currentXmp)) {
        throw new RuntimeException('Unable to compress encrypted object/xref XMP fixture.');
    }

    $carrier = $encryptedObjectXrefObjectStream([
        30 => '<< /Filter /Standard /V 4 /R 4 /Length 128'
            . ' /O ' . $hex($currentOwnerKey)
            . ' /U ' . $hex($currentUserKey)
            . ' /P -44 /EncryptMetadata false'
            . ' /CF << /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >> >>'
            . ' /StmF /StdCF /StrF /StdCF >>',
    ]);

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets["{$objectNumber}:{$generation}"] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($staleText) . " >>\nstream\n{$staleText}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(6, 0, '<< /Title (Stale compressed Encrypt Info Title) /Author (Stale encrypted object author) >>');
    $addObject(30, 0, '<< /Filter /Standard /V 4 /R 4 /Length 128'
        . ' /O ' . $hex($staleOwnerKey)
        . ' /U ' . $hex($staleUserKey)
        . ' /P -64 /EncryptMetadata true >>');

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 7\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($offsets['1:0'])
        . $xrefTableRow($offsets['2:0'])
        . $xrefTableRow($offsets['3:0'])
        . $xrefTableRow($offsets['4:0'])
        . $xrefTableRow($offsets['5:0'])
        . $xrefTableRow($offsets['6:0'])
        . "30 1\n"
        . $xrefTableRow($offsets['30:0'])
        . "trailer\n<< /Size 40 /Root 1 0 R /Info 6 0 R /Encrypt 30 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Metadata 7 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($currentText) . " >>\nstream\n{$currentText}\nendstream");
    $addObject(6, 0, '<< /Title (Current compressed Encrypt Info Title) /Author (Current encrypted object author) >>');
    $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(20, 0, '<< /Type /ObjStm /N ' . $carrier['count'] . ' /First ' . $carrier['first'] . ' /Filter /FlateDecode /Length ' . strlen($carrier['content']) . " >>\nstream\n{$carrier['content']}\nendstream");

    $currentRows = ''
        . $xrefStreamRow(1, $offsets['1:0'])
        . $xrefStreamRow(1, $offsets['2:0'])
        . $xrefStreamRow(1, $offsets['3:0'])
        . $xrefStreamRow(1, $offsets['4:0'])
        . $xrefStreamRow(1, $offsets['5:0'])
        . $xrefStreamRow(1, $offsets['6:0'])
        . $xrefStreamRow(1, $offsets['7:0'])
        . $xrefStreamRow(1, $offsets['20:0'])
        . $xrefStreamRow(2, 20, $carrier['indexes'][30]);
    $compressedXref = gzcompress($currentRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress encrypted object/xref current xref stream.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "40 0 obj\n"
        . '<< /Type /XRef /Size 41 /Root 1 0 R /Info 6 0 R /Encrypt 30 0 R /Prev ' . $previousXrefOffset . ' /Index [1 7 20 1 30 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return [$pdf, $staleText, $currentText, $staleOwnerKey, $staleUserKey, $currentOwnerKey, $currentUserKey];
};

$encryptedObjectXrefHybridCompressedEncryptPdf = static function () use (
    $encryptedObjectXrefObjectStream,
    $encryptedObjectXrefXmp
): array {
    $visibleText = 'BT /F1 12 Tf 72 720 Td (Hybrid compressed Encrypt text must stay blocked) Tj ET';
    $staleText = 'BT /F1 12 Tf 72 720 Td (Hybrid stale encrypted duplicate leak) Tj ET';
    $staleOwnerKey = 'HYBRID_STALE_OWNER_SHOULD_NOT_SURFACE';
    $staleUserKey = 'HYBRID_STALE_USER_SHOULD_NOT_SURFACE';
    $currentOwnerKey = str_repeat('A', 32);
    $currentUserKey = str_repeat('B', 32);
    $hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';
    $currentXmp = gzcompress($encryptedObjectXrefXmp(
        'Hybrid Companion Compressed Encrypt XMP Title',
        'Hybrid companion xref stream compressed encryption selected'
    ));
    if (!is_string($currentXmp)) {
        throw new RuntimeException('Unable to compress hybrid encrypted object/xref XMP fixture.');
    }
    $carrier = $encryptedObjectXrefObjectStream([
        6 => '<< /Title (Hybrid compressed Encrypt Info Title) /Producer (Hybrid encrypted object producer) >>',
        30 => '<< /Filter /Standard /V 4 /R 4 /Length 128'
            . ' /O ' . $hex($currentOwnerKey)
            . ' /U ' . $hex($currentUserKey)
            . ' /P 31 0 R /EncryptMetadata 32 0 R /CF 33 0 R /StmF 34 0 R /StrF 34 0 R >>',
        31 => '-44',
        32 => 'false',
        33 => '<< /StdCF << /CFM 35 0 R /AuthEvent /DocOpen /Length 16 >> >>',
        34 => '/StdCF',
        35 => '/AESV2',
    ]);

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets["{$objectNumber}:{$generation}"] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($staleText) . " >>\nstream\n{$staleText}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(6, 0, '<< /Title (Hybrid stale Encrypt Info Title) /Producer (Hybrid stale producer) >>');
    $addObject(30, 0, '<< /Filter /Standard /V 2 /R 3 /Length 128'
        . ' /O ' . $hex($staleOwnerKey)
        . ' /U ' . $hex($staleUserKey)
        . ' /P -64 /EncryptMetadata true >>');

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 7\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($offsets['1:0'])
        . $xrefTableRow($offsets['2:0'])
        . $xrefTableRow($offsets['3:0'])
        . $xrefTableRow($offsets['4:0'])
        . $xrefTableRow($offsets['5:0'])
        . $xrefTableRow($offsets['6:0'])
        . "30 1\n"
        . $xrefTableRow($offsets['30:0'])
        . "trailer\n<< /Size 40 /Root 1 0 R /Info 6 0 R /Encrypt 30 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Metadata 7 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($visibleText) . " >>\nstream\n{$visibleText}\nendstream");
    $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(20, 0, '<< /Type /ObjStm /N ' . $carrier['count'] . ' /First ' . $carrier['first'] . ' /Filter /FlateDecode /Length ' . strlen($carrier['content']) . " >>\nstream\n{$carrier['content']}\nendstream");

    $xrefStreamRows = ''
        . $xrefStreamRow(2, 20, $carrier['indexes'][6])
        . $xrefStreamRow(2, 20, $carrier['indexes'][30])
        . $xrefStreamRow(2, 20, $carrier['indexes'][31])
        . $xrefStreamRow(2, 20, $carrier['indexes'][32])
        . $xrefStreamRow(2, 20, $carrier['indexes'][33])
        . $xrefStreamRow(2, 20, $carrier['indexes'][34])
        . $xrefStreamRow(2, 20, $carrier['indexes'][35]);
    $compressedXrefStream = gzcompress($xrefStreamRows);
    if (!is_string($compressedXrefStream)) {
        throw new RuntimeException('Unable to compress encrypted object/xref hybrid xref stream.');
    }
    $xrefStreamOffset = $addObject(21, 0, '<< /Type /XRef /Size 40 /Index [6 1 30 6] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXrefStream) . " >>\nstream\n{$compressedXrefStream}\nendstream");

    $currentTableOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 6\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($offsets['1:0'])
        . $xrefTableRow($offsets['2:0'])
        . $xrefTableRow($offsets['3:0'])
        . $xrefTableRow($offsets['4:0'])
        . $xrefTableRow($offsets['5:0'])
        . "7 1\n"
        . $xrefTableRow($offsets['7:0'])
        . "20 2\n"
        . $xrefTableRow($offsets['20:0'])
        . $xrefTableRow($xrefStreamOffset)
        . "trailer\n<< /Size 40 /Root 1 0 R /Info 6 0 R /Encrypt 30 0 R /XRefStm {$xrefStreamOffset} /Prev {$previousXrefOffset} >>\n"
        . "startxref\n{$currentTableOffset}\n%%EOF";

    return [$pdf, $visibleText, $staleText, $staleOwnerKey, $staleUserKey, $currentOwnerKey, $currentUserKey];
};

$assertNoEncryptedObjectXrefLeaks = static function (
    TestRunner $t,
    array $payload,
    string $expectedSource,
    string $expectedAlgorithm,
    string $expectedRevisionLabel
): void {
    [$pdf, $firstText, $secondText, $staleOwnerKey, $staleUserKey, $currentOwnerKey, $currentUserKey] = $payload;
    $extractor = new PdfTextExtractor();
    $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
    $preflight = (new PdfSecurityPreflight())->analyze($pdf);
    $encryption = $metadata['encryption'];
    $reviewEncryption = $preflight['encryption'];
    $permission = $preflight['permission_preflight'];
    $encoded = json_encode([$metadata, $preflight], JSON_UNESCAPED_SLASHES) ?: '';

    $t->same('', $extractor->extractPlainText($pdf));
    $t->same([], $extractor->extractTextLines($pdf));
    $t->same([], $extractor->extractTextRuns($pdf));
    $t->same(true, $preflight['encrypted']);
    $t->same('blocked_without_decryption', $preflight['text_extraction_policy']);
    $t->same('block_encrypted_content_review_security_metadata', $preflight['import_decision']);
    $t->same($expectedSource, $encryption['source']);
    $t->same($expectedSource, $reviewEncryption['source']);
    $t->same(30, $encryption['object_number']);
    $t->same(30, $reviewEncryption['object_number']);
    $t->same('encrypt_dictionary_indirect_reference_resolved', $encryption['encrypt_operand_status']);
    $t->same('encrypt_dictionary_indirect_reference_resolved', $reviewEncryption['encrypt_operand_status']);
    $t->same($expectedAlgorithm, $encryption['algorithm']);
    $t->same($expectedRevisionLabel, $encryption['revision_label']);
    $t->same(true, $permission['permission_bits_reliable']);
    $t->same(true, $permission['copy_or_extract_allowed']);
    $t->same(false, $preflight['executes_decryption']);
    $t->same(false, $preflight['executes_python_or_models']);
    $t->same(false, $preflight['executes_external_pdf_tools']);
    $t->true(!in_array('standard_security_handler_parameters_malformed', $preflight['review_reasons'], true));
    $t->true(!in_array('encrypted_permissions_malformed', $preflight['review_reasons'], true));
    $t->true(!str_contains($encoded, $firstText));
    $t->true(!str_contains($encoded, $secondText));
    $t->true(!str_contains($encoded, $staleOwnerKey));
    $t->true(!str_contains($encoded, $staleUserKey));
    $t->true(!str_contains($encoded, strtoupper(bin2hex($staleOwnerKey))));
    $t->true(!str_contains($encoded, strtoupper(bin2hex($staleUserKey))));
    $t->true(!str_contains($encoded, $currentOwnerKey));
    $t->true(!str_contains($encoded, $currentUserKey));
    $t->true(!str_contains($encoded, strtoupper(bin2hex($currentOwnerKey))));
    $t->true(!str_contains($encoded, strtoupper(bin2hex($currentUserKey))));
};

return [
    'selects compressed xref-stream Encrypt object before stale Prev duplicate security state' => static function (
        TestRunner $t
    ) use ($encryptedObjectXrefCompressedEncryptPdf, $assertNoEncryptedObjectXrefLeaks): void {
        $payload = $encryptedObjectXrefCompressedEncryptPdf();
        [$pdf] = $payload;
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $preflight = (new PdfSecurityPreflight())->analyze($pdf);

        $assertNoEncryptedObjectXrefLeaks(
            $t,
            $payload,
            'trailer_encrypt',
            'security_handler_crypt_filters',
            'standard_handler_revision_4'
        );
        $t->same(false, $metadata['encryption']['encrypt_metadata']);
        $t->same('AESV2', $metadata['encryption']['crypt_filters']['StdCF']['method']);
        $t->same('Current Compressed Encrypt XMP Title', $metadata['title']);
        $t->same('Compressed Encrypt object selected through xref stream', $metadata['description']);
        $t->same('preserved_unencrypted_by_encrypt_metadata_false', $metadata['encryption']['metadata_source_policy']['xmp_stream_policy']);
        $t->same('suppressed_encrypted_document_strings', $metadata['encryption']['metadata_source_policy']['info_dictionary_policy']);
        $t->same(false, $preflight['crypt_filter_content_review']['fail_closed'] ?? false);
        $t->same(['StdCF'], $preflight['crypt_filter_content_review']['selected_filter_names']);
        $t->same(
            ['document_streams', 'document_strings', 'embedded_file_streams'],
            $preflight['crypt_filter_content_review']['encrypted_role_names']
        );
    },
    'selects hybrid companion xref-stream compressed Encrypt and indirect operands before stale duplicates' => static function (
        TestRunner $t
    ) use ($encryptedObjectXrefHybridCompressedEncryptPdf, $assertNoEncryptedObjectXrefLeaks): void {
        $payload = $encryptedObjectXrefHybridCompressedEncryptPdf();
        [$pdf] = $payload;
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $preflight = (new PdfSecurityPreflight())->analyze($pdf);

        $assertNoEncryptedObjectXrefLeaks(
            $t,
            $payload,
            'trailer_encrypt',
            'security_handler_crypt_filters',
            'standard_handler_revision_4'
        );
        $t->same('Hybrid Companion Compressed Encrypt XMP Title', $metadata['title']);
        $t->same('Hybrid companion xref stream compressed encryption selected', $metadata['description']);
        $t->same([], $metadata['info']);
        $t->same(false, $metadata['encryption']['encrypt_metadata']);
        $t->same(true, $metadata['encryption']['encrypt_metadata_explicit']);
        $t->same('preserved_unencrypted_by_encrypt_metadata_false', $metadata['encryption']['metadata_source_policy']['xmp_stream_policy']);
        $t->same('suppressed_encrypted_document_strings', $metadata['encryption']['metadata_source_policy']['info_dictionary_policy']);
        $t->same(128, $metadata['encryption']['key_length_bits']);
        $t->same('StdCF', $metadata['encryption']['stream_filter']);
        $t->same('StdCF', $metadata['encryption']['string_filter']);
        $t->same('AESV2', $metadata['encryption']['crypt_filters']['StdCF']['method']);
        $t->same('FFFFFFD4', $metadata['encryption']['standard_permissions']['hex']);
        $t->same(false, $preflight['executes_decryption']);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encodedMetadata, 'Hybrid compressed Encrypt Info Title'));
        $t->true(!str_contains($encodedMetadata, 'Hybrid stale'));
    },
];
