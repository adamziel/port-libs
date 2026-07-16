<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$portfolioAssociatedPieceInfoChecksumCurrentBasePdf = static function (): array {
    $sourcePayload = '<wp-export><post id="portfolio-pieceinfo-checksum-current"/></wp-export>';
    $previewPayload = '{"preview":"checksum-mismatch"}';
    $privatePayload = '{"piece":"verified","checksum":"source"}';
    $previewPrivatePayload = 'BT /F1 12 Tf 72 720 Td (Portfolio PieceInfo Private Payload Leak) Tj ET';
    $staleSourcePayload = '<wp-export><post id="stale-portfolio-pieceinfo"/></wp-export>';
    $stalePrivatePayload = 'BT /F1 12 Tf 72 720 Td (Stale Portfolio PieceInfo Private Leak) Tj ET';

    $privateStream = gzcompress($privatePayload);
    $stalePrivateStream = gzcompress($stalePrivatePayload);
    if (!is_string($privateStream) || !is_string($stalePrivateStream)) {
        throw new RuntimeException('Unable to compress Portfolio PieceInfo checksum fixture streams.');
    }

    $sourceChecksum = strtoupper(hash('md5', $sourcePayload));
    $previewChecksum = str_repeat('0c', 16);
    $privateChecksum = strtoupper(hash('md5', $privatePayload));
    $previewPrivateChecksum = str_repeat('7a', 16);
    $content = 'BT /F1 12 Tf 72 720 Td (Current Portfolio Attachment Body) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale Portfolio Attachment Body) Tj ET';

    $pdf = "%PDF-2.0\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /PageMode /UseAttachments /Collection 5 0 R /AF [10 0 R 20 0 R] >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
    $addObject(4, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(5, '<< /Type /Collection /View /T /D (source.xml) /Schema << /Subject << /Subtype /S /N (Subject) /O 1 >> /Checksum << /Subtype /S /N (Embedded checksum) /O 2 >> /PieceChecksum << /Subtype /S /N (Piece checksum) /O 3 >> /Bytes << /Subtype /Size /N (Bytes) /O 4 >> >> /Sort << /S [/PieceChecksum /Subject] /A [true false] >> >>');
    $addObject(10, '<< /Type /Filespec /F (source.xml) /Desc (Current WordPress portfolio source) /AFRelationship /Source /CI 30 0 R /PieceInfo 32 0 R /EF << /F 11 0 R >> >>');
    $addObject(11, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($sourcePayload) . ' /CheckSum <' . $sourceChecksum . "> /ModDate (D:20260602211840Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream");
    $addObject(20, '<< /Type /Filespec /F (preview.json) /Desc (Generated preview packet) /AFRelationship /Alternative /CI << /Subject (Preview JSON) /Checksum << /Type /CollectionSubitem /D (stale) /P (embedded: ) >> /PieceChecksum << /Type /CollectionSubitem /D (stale-private) /P (piece: ) >> >> /PieceInfo << /WPPreview << /LastModified (D:20260602211920Z) /Private 41 0 R >> >> /EF << /F 21 0 R >> >>');
    $addObject(21, '<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size ' . strlen($previewPayload) . ' /CheckSum <' . $previewChecksum . "> >> /Length " . strlen($previewPayload) . " >>\nstream\n{$previewPayload}\nendstream");
    $addObject(30, '<< /Type /CollectionItem /Subject (Current WordPress Export) /Checksum << /Type /CollectionSubitem /D (verified) /P (embedded: ) >> /PieceChecksum << /Type /CollectionSubitem /D (verified-private) /P (piece: ) >> /Bytes ' . strlen($sourcePayload) . ' >>');
    $addObject(32, '<< /WPImport << /LastModified (D:20260602211900Z) /Private << /ManifestId (portfolio-pieceinfo-checksum-current) /PrivateStream 33 0 R /ChecksumState << /Type /CollectionSubitem /D (verified-private) /P (piece: ) >> >> >> >>');
    $addObject(33, '<< /Type /Metadata /Subtype /application#2Fjson /Filter /FlateDecode /Params << /Size ' . strlen($privatePayload) . ' /CheckSum <' . $privateChecksum . "> /ModDate (D:20260602211910Z) >> /Length " . strlen($privateStream) . " >>\nstream\n{$privateStream}\nendstream");
    $addObject(41, '<< /Type /Metadata /Subtype /text#2Fplain /Params << /Size ' . strlen($previewPrivatePayload) . ' /CheckSum <' . $previewPrivateChecksum . "> /CreationDate (D:20260602211915Z) >> /Length " . strlen($previewPrivatePayload) . " >>\nstream\n{$previewPrivatePayload}\nendstream");

    $xrefOffset = strlen($pdf);
    $rows = '';
    for ($objectNumber = 0; $objectNumber < 51; $objectNumber++) {
        if ($objectNumber === 0 || (!isset($offsets[$objectNumber]) && $objectNumber !== 50)) {
            $rows .= pack('CNn', 0, 0, $objectNumber === 0 ? 65535 : 0);
            continue;
        }

        $rows .= pack('CNn', 1, $objectNumber === 50 ? $xrefOffset : $offsets[$objectNumber], 0);
    }

    $compressedXref = gzcompress($rows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress Portfolio PieceInfo checksum xref stream.');
    }

    $pdf .= "50 0 obj\n"
        . '<< /Type /XRef /Size 51 /Root 1 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageMode /UseAttachments /Collection 5 0 R /AF [10 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Collection /View /D /D (stale-source.xml) /Schema << /Subject << /Subtype /S /N (Stale Subject) /O 1 >> >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (stale-source.xml) /Desc (Stale portfolio source) /AFRelationship /Source /PieceInfo 32 0 R /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length " . strlen($staleSourcePayload) . " >>\nstream\n{$staleSourcePayload}\nendstream\nendobj\n"
        . "32 0 obj\n<< /WPImport << /LastModified (D:20260602220000Z) /Private << /ManifestId (stale-pieceinfo-checksum) /PrivateStream 33 0 R >> >> >>\nendobj\n"
        . "33 0 obj\n<< /Type /Metadata /Subtype /application#2Fjson /Filter /FlateDecode /Length " . strlen($stalePrivateStream) . " >>\nstream\n{$stalePrivateStream}\nendstream\nendobj\n";

    return [$pdf, $sourcePayload, $previewPayload, $privatePayload, $previewPrivatePayload, $staleSourcePayload, $stalePrivatePayload];
};

return [
    'keeps current portfolio associated FileSpec PieceInfo checksum metadata review-only' => static function (
        TestRunner $t
    ) use ($portfolioAssociatedPieceInfoChecksumCurrentBasePdf): void {
        [$pdf, $sourcePayload, $previewPayload, $privatePayload, $previewPrivatePayload, $staleSourcePayload, $stalePrivatePayload] = $portfolioAssociatedPieceInfoChecksumCurrentBasePdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $attachments = $metadata['collection']['associated_files'] ?? [];

        $t->same(['catalog'], $metadata['source']);
        $t->same('Current Portfolio Attachment Body', $plainText);
        $t->same('catalog_collection', $metadata['collection']['source'] ?? null);
        $t->same(['PieceChecksum', 'Subject'], $metadata['collection']['sort']['keys'] ?? []);
        $t->same(2, $metadata['collection']['associated_file_count'] ?? null);
        $t->same(2, count($attachments));
        $t->true(!isset($metadata['associated_files']));

        $source = $attachments[0];
        $sourcePrivate = $source['piece_info']['WPImport']['private_streams'][0] ?? [];
        $sourceProvenance = $source['provenance_review'] ?? [];
        $sourcePieceStreams = $sourceProvenance['piece_info_private_streams']['streams'][0] ?? [];

        $t->same('catalog_collection_associated_files', $source['source'] ?? null);
        $t->same('source.xml', $source['filename'] ?? null);
        $t->same('Current WordPress portfolio source', $source['description'] ?? null);
        $t->same('Source', $source['relationship'] ?? null);
        $t->same('text/xml', $source['mime_type'] ?? null);
        $t->same(true, $source['checksum_matches'] ?? null);
        $t->same('Current WordPress Export', $source['collection_item']['Subject'] ?? null);
        $t->same('embedded:verified', $source['collection_field_values']['Checksum']['display_value'] ?? null);
        $t->same('piece:verified-private', $source['collection_field_values']['PieceChecksum']['display_value'] ?? null);
        $t->same(strlen($sourcePayload), $source['collection_field_values']['Bytes']['value'] ?? null);

        $t->same('D:20260602211900Z', $source['piece_info']['WPImport']['last_modified'] ?? null);
        $t->same('portfolio-pieceinfo-checksum-current', $source['piece_info']['WPImport']['private']['ManifestId'] ?? null);
        $t->same('CollectionSubitem', $source['piece_info']['WPImport']['private']['ChecksumState']['Type'] ?? null);
        $t->same('verified-private', $source['piece_info']['WPImport']['private']['ChecksumState']['D'] ?? null);
        $t->same('piece:', $source['piece_info']['WPImport']['private']['ChecksumState']['P'] ?? null);
        $t->same('PrivateStream', $sourcePrivate['key'] ?? null);
        $t->same(33, $sourcePrivate['object'] ?? null);
        $t->same(strlen($privatePayload), $sourcePrivate['declared_size'] ?? null);
        $t->same('application/json', $sourcePrivate['mime_type'] ?? null);
        $t->same(['FlateDecode'], $sourcePrivate['filters'] ?? []);
        $t->same(hash('sha256', $privatePayload), $sourcePrivate['content_sha256'] ?? null);
        $t->same(hash('md5', $privatePayload), $sourcePrivate['checksum'] ?? null);
        $t->same('md5', $sourcePrivate['checksum_algorithm'] ?? null);
        $t->same(hash('md5', $privatePayload), $sourcePrivate['computed_checksum'] ?? null);
        $t->same(true, $sourcePrivate['checksum_matches'] ?? null);
        $t->same(false, array_key_exists('content', $sourcePrivate));

        $t->same(['filespec_afrelationship', 'embedded_file_payload_hash', 'embedded_file_params_checksum', 'filespec_pieceinfo_private_streams'], $sourceProvenance['sources'] ?? []);
        $t->same('filespec_pieceinfo_private_streams', $sourceProvenance['piece_info_private_streams']['source'] ?? null);
        $t->same(['WPImport'], $sourceProvenance['piece_info_private_streams']['applications'] ?? []);
        $t->same('WPImport', $sourcePieceStreams['application'] ?? null);
        $t->same('PrivateStream', $sourcePieceStreams['key'] ?? null);
        $t->same(true, $sourcePieceStreams['checksum_matches'] ?? null);
        $t->same(false, $sourceProvenance['piece_info_private_streams']['payload_included'] ?? null);

        $preview = $attachments[1];
        $previewPrivate = $preview['piece_info']['WPPreview']['private_stream'] ?? [];
        $previewProvenance = $preview['provenance_review'] ?? [];

        $t->same('preview.json', $preview['filename'] ?? null);
        $t->same('Alternative', $preview['relationship'] ?? null);
        $t->same(false, $preview['checksum_matches'] ?? null);
        $t->same('embedded:stale', $preview['collection_field_values']['Checksum']['display_value'] ?? null);
        $t->same('piece:stale-private', $preview['collection_field_values']['PieceChecksum']['display_value'] ?? null);
        $t->same(41, $previewPrivate['object'] ?? null);
        $t->same('text/plain', $previewPrivate['mime_type'] ?? null);
        $t->same(hash('md5', $previewPrivatePayload), $previewPrivate['computed_checksum'] ?? null);
        $t->same(false, $previewPrivate['checksum_matches'] ?? null);
        $t->same(['filespec_afrelationship', 'embedded_file_payload_hash', 'embedded_file_params_checksum', 'filespec_pieceinfo_private_streams'], $previewProvenance['sources'] ?? []);

        $t->true(is_string($encoded) && !str_contains($encoded, $sourcePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $previewPayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $privatePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $previewPrivatePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $staleSourcePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $stalePrivatePayload));
        $t->true(!str_contains($plainText, '<wp-export>'));
        $t->true(!str_contains($plainText, 'checksum-mismatch'));
        $t->true(!str_contains($plainText, 'Portfolio PieceInfo Private Payload Leak'));
        $t->true(!str_contains($plainText, 'Stale Portfolio Attachment Body'));
    },
];
