<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePayload = '<wp-export><post id="portfolio-pieceinfo-checksum-smoke"/></wp-export>';
$previewPayload = '{"preview":"portfolio-pieceinfo"}';
$privatePayload = '{"piece":"verified","checksum":"source"}';
$previewPrivatePayload = 'BT /F1 12 Tf 72 720 Td (Portfolio PieceInfo Private Payload Leak) Tj ET';
$staleSourcePayload = '<wp-export><post id="stale-portfolio-pieceinfo"/></wp-export>';
$stalePrivatePayload = 'BT /F1 12 Tf 72 720 Td (Stale Portfolio PieceInfo Private Leak) Tj ET';
$privateStream = gzcompress($privatePayload);
$stalePrivateStream = gzcompress($stalePrivatePayload);
if (!is_string($privateStream) || !is_string($stalePrivateStream)) {
    throw new RuntimeException('Unable to compress Portfolio PieceInfo checksum smoke streams.');
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
$addObject(32, '<< /WPImport << /LastModified (D:20260602211900Z) /Private << /ManifestId (portfolio-pieceinfo-checksum-current) /PrivateStream 33 0 R >> >> >>');
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
    throw new RuntimeException('Unable to compress Portfolio PieceInfo checksum smoke xref stream.');
}

$pdf .= "50 0 obj\n"
    . '<< /Type /XRef /Size 51 /Root 1 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageMode /UseAttachments /Collection 5 0 R /AF [10 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (stale-source.xml) /Desc (Stale portfolio source) /AFRelationship /Source /PieceInfo 32 0 R /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length " . strlen($staleSourcePayload) . " >>\nstream\n{$staleSourcePayload}\nendstream\nendobj\n"
    . "32 0 obj\n<< /WPImport << /LastModified (D:20260602220000Z) /Private << /ManifestId (stale-pieceinfo-checksum) /PrivateStream 33 0 R >> >> >>\nendobj\n"
    . "33 0 obj\n<< /Type /Metadata /Subtype /application#2Fjson /Filter /FlateDecode /Length " . strlen($stalePrivateStream) . " >>\nstream\n{$stalePrivateStream}\nendstream\nendobj\n";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$attachments = $metadata['collection']['associated_files'] ?? [];
if (count($attachments) !== 2) {
    throw new RuntimeException('Expected two Portfolio associated attachment metadata rows.');
}

$source = $attachments[0];
$preview = $attachments[1];
$sourcePrivate = $source['piece_info']['WPImport']['private_streams'][0] ?? null;
$previewPrivate = $preview['piece_info']['WPPreview']['private_stream'] ?? null;
if (!is_array($sourcePrivate) || !is_array($previewPrivate)) {
    throw new RuntimeException('Expected PieceInfo private-stream review metadata on both Portfolio attachments.');
}
if (($sourcePrivate['checksum_matches'] ?? null) !== true || ($previewPrivate['checksum_matches'] ?? null) !== false) {
    throw new RuntimeException('Expected verified and stale PieceInfo private checksum states.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-pdf-metadata-portfolio-associated-pieceinfo-checksum-currentbase-smoke ' . $htmlJson([
    'support_component' => 'native-pdf-portfolio-associated-pieceinfo-checksum-review',
    'native_boundary' => 'current xref-selected catalog /Collection /AF FileSpec /PieceInfo private stream /Params /CheckSum review before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'collection_associated_file_count' => count($attachments),
    'filenames' => array_map(static fn (array $attachment): ?string => $attachment['filename'] ?? null, $attachments),
    'relationships' => array_map(static fn (array $attachment): ?string => $attachment['relationship'] ?? null, $attachments),
    'embedded_checksum_matches' => array_map(static fn (array $attachment): ?bool => $attachment['checksum_matches'] ?? null, $attachments),
    'piece_info_private_checksum_matches' => [
        $sourcePrivate['checksum_matches'] ?? null,
        $previewPrivate['checksum_matches'] ?? null,
    ],
    'provenance_sources' => $source['provenance_review']['sources'] ?? [],
    'current_xref_selected' => ($source['filename'] ?? null) === 'source.xml' && $plainText === 'Current Portfolio Attachment Body',
    'visible_text_excludes_attachment_payloads' => !str_contains($plainText, '<wp-export>') && !str_contains($plainText, 'portfolio-pieceinfo'),
    'visible_text_excludes_pieceinfo_private_streams' => !str_contains($plainText, 'Portfolio PieceInfo Private Payload Leak') && !str_contains($plainText, 'Stale Portfolio PieceInfo Private Leak'),
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

foreach ($attachments as $attachment) {
    $pieceInfoPrivateChecksumMatches = [];
    foreach ($attachment['piece_info'] ?? [] as $piece) {
        if (!is_array($piece)) {
            continue;
        }

        foreach ($piece['private_streams'] ?? [] as $privateStream) {
            if (is_array($privateStream)) {
                $pieceInfoPrivateChecksumMatches[] = $privateStream['checksum_matches'] ?? null;
            }
        }
    }

    echo '<!-- wp:file {"href":"media/' . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
    echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</a></div>\n";
    echo "<!-- /wp:file -->\n\n";
    echo '<!-- markerpdf:portfolio-associated-pieceinfo-file ' . $htmlJson([
        'filename' => $attachment['filename'] ?? null,
        'description' => $attachment['description'] ?? null,
        'relationship' => $attachment['relationship'] ?? null,
        'collection_item' => $attachment['collection_item'] ?? [],
        'embedded_checksum_matches' => $attachment['checksum_matches'] ?? null,
        'piece_info_applications' => array_keys($attachment['piece_info'] ?? []),
        'piece_info_private_checksum_matches' => $pieceInfoPrivateChecksumMatches,
        'payload_content_omitted_from_metadata' => !array_key_exists('content', $attachment),
    ]) . " -->\n\n";
}
