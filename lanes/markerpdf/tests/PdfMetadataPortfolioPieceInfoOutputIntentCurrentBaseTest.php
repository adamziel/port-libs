<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$portfolioMetadataXmp = static function (string $title): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<xmp:CreateDate>2026-06-02T19:40:21Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$portfolioPieceInfoOutputIntentPdf = static function () use ($portfolioMetadataXmp): array {
    $fileXmp = $portfolioMetadataXmp('Portfolio FileSpec XMP Hidden Title');
    $pieceInfoXmp = $portfolioMetadataXmp('Portfolio PieceInfo XMP Hidden Title');
    $profileBytes = 'Portfolio attachment ICC bytes should be hashed only';
    $privatePayload = 'BT /F1 12 Tf 72 720 Td (Portfolio PieceInfo Private Payload Leak) Tj ET';
    $sourcePayload = '<wp-export><post id="portfolio-pieceinfo-outputintent"/></wp-export>';

    $fileXmpStream = gzcompress($fileXmp);
    $pieceInfoXmpStream = gzcompress($pieceInfoXmp);
    $profileStream = gzcompress($profileBytes);
    $privateStream = gzcompress($privatePayload);
    if (
        !is_string($fileXmpStream)
        || !is_string($pieceInfoXmpStream)
        || !is_string($profileStream)
        || !is_string($privateStream)
    ) {
        throw new RuntimeException('Unable to compress Portfolio metadata fixture streams.');
    }

    $sourceChecksum = strtoupper(hash('md5', $sourcePayload));
    $content = 'BT /F1 12 Tf 72 720 Td (Current Portfolio Metadata Body) Tj ET';
    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageMode /UseAttachments /Collection 5 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Collection /View /T /D (source.xml) /Schema << /Subject << /Subtype /S /N (Subject) /O 1 >> /Priority << /Subtype /N /N (Priority) /O 2 >> /Bytes << /Subtype /Size /N (Bytes) /O 3 >> >> /Sort << /S [/Priority /Subject] /A [true false] >> >>\nendobj\n"
        . "6 0 obj\n<< /Names [(source.xml) 10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (legacy-source.xml) /UF (source.xml) /Desc (Original WordPress portfolio export) /AFRelationship /Source /Metadata 30 0 R /OutputIntents [40 0 R << /Type /OutputIntent /S /GTS_PDFX /OutputConditionIdentifier (Portfolio Proof) >>] /CI 20 0 R /PieceInfo 31 0 R /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260602194021Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Subject (Migration Source) /Priority << /Type /CollectionSubitem /D 2 /P (P) >> /Bytes " . strlen($sourcePayload) . " >>\nendobj\n"
        . "30 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($fileXmpStream) . " >>\nstream\n{$fileXmpStream}\nendstream\nendobj\n"
        . "31 0 obj\n<< /WPImport << /LastModified (D:20260602194100Z) /Private << /ManifestId (portfolio-meta-1940) /Metadata 32 0 R /OutputIntents [40 0 R] /PrivateStream 33 0 R >> >> >>\nendobj\n"
        . "32 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($pieceInfoXmpStream) . " >>\nstream\n{$pieceInfoXmpStream}\nendstream\nendobj\n"
        . "33 0 obj\n<< /Type /Metadata /Subtype /application#2Fjson /Filter /FlateDecode /Length " . strlen($privateStream) . " >>\nstream\n{$privateStream}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Portfolio Attachment sRGB) /Info (Attachment-local PDF/A profile) /DestOutputProfile 41 0 R >>\nendobj\n"
        . "41 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($profileStream) . " >>\nstream\n{$profileStream}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $fileXmp, $pieceInfoXmp, $profileBytes, $privatePayload, $sourcePayload];
};

return [
    'summarizes portfolio FileSpec PieceInfo and OutputIntent provenance without promoting private metadata' => static function (
        TestRunner $t
    ) use ($portfolioPieceInfoOutputIntentPdf): void {
        [$pdf, $fileXmp, $pieceInfoXmp, $profileBytes, $privatePayload, $sourcePayload] = $portfolioPieceInfoOutputIntentPdf();

        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $documentMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);
        $encodedDocumentMetadata = json_encode($documentMetadata, JSON_UNESCAPED_SLASHES);

        $t->same(1, count($files));
        $file = $files[0];
        $provenance = $file['provenance_review'] ?? [];

        $t->same('source.xml', $file['filename']);
        $t->same('source.xml', $file['name']);
        $t->same('Original WordPress portfolio export', $file['description']);
        $t->same('Source', $file['relationship']);
        $t->same('Migration Source', $file['portfolio_item']['Subject']);
        $t->same('P2', $file['portfolio_field_values']['Priority']['display_value']);
        $t->same('portfolio-meta-1940', $file['piece_info']['WPImport']['private']['ManifestId']);
        $t->same('Portfolio Attachment sRGB', $file['output_intents_review'][0]['OutputConditionIdentifier']);

        $t->same('portfolio_filespec_provenance', $provenance['source'] ?? null);
        $t->same(true, $provenance['review_only'] ?? null);
        $t->same(false, $provenance['metadata_payloads_included'] ?? null);
        $t->same(true, $provenance['payload_content_returned'] ?? null);
        $t->same('original_source', $provenance['relationship_role'] ?? null);
        $t->same(['filespec_afrelationship', 'embedded_file_payload', 'catalog_collection', 'filespec_collection_item', 'filespec_metadata_stream', 'filespec_pieceinfo', 'filespec_output_intents'], $provenance['sources'] ?? []);

        $t->same('source.xml', $provenance['payload']['filename'] ?? null);
        $t->same(hash('sha256', $sourcePayload), $provenance['payload']['sha256'] ?? null);
        $t->same(false, array_key_exists('content', $provenance['payload'] ?? []));
        $t->same(['Subject', 'Priority', 'Bytes'], $provenance['portfolio']['schema_fields'] ?? []);
        $t->same(['Priority', 'Subject'], $provenance['portfolio']['sort_keys'] ?? []);
        $t->same(['Subject', 'Priority', 'Bytes'], $provenance['portfolio_fields']['field_names'] ?? []);
        $t->same('P2', $provenance['portfolio_fields']['values']['Priority']['display_value'] ?? null);

        $t->same(30, $provenance['xmp_metadata']['object_number'] ?? null);
        $t->same(hash('sha256', $fileXmp), $provenance['xmp_metadata']['sha256'] ?? null);
        $t->same(false, $provenance['xmp_metadata']['payload_included'] ?? null);

        $pieceInfo = $provenance['piece_info'] ?? [];
        $t->same(['WPImport'], $pieceInfo['applications'] ?? []);
        $t->same(1, $pieceInfo['count'] ?? null);
        $t->same('D:20260602194100Z', $pieceInfo['entries'][0]['last_modified'] ?? null);
        $t->same(['ManifestId', 'Metadata', 'OutputIntents', 'PrivateStream'], $pieceInfo['entries'][0]['private_keys'] ?? []);
        $t->same(32, $pieceInfo['entries'][0]['metadata_streams'][0]['object_number'] ?? null);
        $t->same(hash('sha256', $pieceInfoXmp), $pieceInfo['entries'][0]['metadata_streams'][0]['sha256'] ?? null);
        $t->same(33, $pieceInfo['entries'][0]['private_streams'][0]['object'] ?? null);
        $t->same(hash('sha256', $privatePayload), $pieceInfo['entries'][0]['private_streams'][0]['content_sha256'] ?? null);
        $t->same('Portfolio Attachment sRGB', $pieceInfo['entries'][0]['output_intents']['output_condition_identifiers'][0] ?? null);

        $outputIntents = $provenance['pdfa_output_intents'] ?? [];
        $t->same(2, $outputIntents['count'] ?? null);
        $t->same(true, $outputIntents['has_pdfa_output_intent'] ?? null);
        $t->same(['Portfolio Attachment sRGB'], $outputIntents['output_condition_identifiers'] ?? []);
        $t->same([hash('sha256', $profileBytes)], $outputIntents['profile_sha256'] ?? []);
        $t->same('GTS_PDFX', $outputIntents['intents'][1]['subtype'] ?? null);

        $t->same([], $documentMetadata['output_intents']);
        $t->true(!isset($documentMetadata['pdfa']));
        $t->same('Current Portfolio Metadata Body', $plainText);
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'Portfolio FileSpec XMP Hidden Title'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'Portfolio PieceInfo XMP Hidden Title'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, $profileBytes));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, $privatePayload));
        $t->true(is_string($encodedDocumentMetadata) && !str_contains($encodedDocumentMetadata, 'Portfolio FileSpec XMP Hidden Title'));
        $t->true(!str_contains($plainText, '<wp-export>'));
        $t->true(!str_contains($plainText, 'Portfolio PieceInfo Private Payload Leak'));
    },
];
