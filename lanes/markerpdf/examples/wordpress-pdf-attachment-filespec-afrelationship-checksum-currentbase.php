<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePayload = '<wp-export><post id="afrelationship-current"/></wp-export>';
$reviewPayload = '{"review":"relationship-mismatch"}';
$orphanPayload = '{"review":"missing-relationship"}';
$staleSourcePayload = '<wp-export><post id="stale-afrelationship"/></wp-export>';
$staleReviewPayload = '{"review":"stale"}';

$sourceChecksum = strtoupper(hash('md5', $sourcePayload));
$reviewChecksum = str_repeat('0d', 16);
$orphanChecksum = strtoupper(hash('md5', $orphanPayload));
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current FileSpec Associated Body) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale FileSpec Associated Body) Tj ET';

$pdf = "%PDF-2.0\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /AF [10 0 R 20 0 R 30 0 R] >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
$addObject(4, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(10, '<< /Type /Filespec /F (source.xml) /Desc (Current source export) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$addObject(11, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($sourcePayload) . ' /CheckSum <' . $sourceChecksum . "> /ModDate (D:20260602213538Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream");
$addObject(20, '<< /Type /Filespec /F (review.json) /Desc (Custom relationship review packet) /AFRelationship /MigrationReview /EF << /F 21 0 R >> >>');
$addObject(21, '<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size ' . strlen($reviewPayload) . ' /CheckSum <' . $reviewChecksum . "> >> /Length " . strlen($reviewPayload) . " >>\nstream\n{$reviewPayload}\nendstream");
$addObject(30, '<< /Type /Filespec /F (orphan-review.json) /Desc (Missing relationship review packet) /EF << /F 31 0 R >> >>');
$addObject(31, '<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size ' . strlen($orphanPayload) . ' /CheckSum <' . $orphanChecksum . "> >> /Length " . strlen($orphanPayload) . " >>\nstream\n{$orphanPayload}\nendstream");

$xrefOffset = strlen($pdf);
$rows = '';
for ($objectNumber = 0; $objectNumber < 61; $objectNumber++) {
    if ($objectNumber === 0 || (!isset($offsets[$objectNumber]) && $objectNumber !== 60)) {
        $rows .= pack('CNn', 0, 0, $objectNumber === 0 ? 65535 : 0);
        continue;
    }

    $rows .= pack('CNn', 1, $objectNumber === 60 ? $xrefOffset : $offsets[$objectNumber], 0);
}

$compressedXref = gzcompress($rows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress FileSpec AFRelationship checksum xref stream.');
}

$pdf .= "60 0 obj\n"
    . '<< /Type /XRef /Size 61 /Root 1 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AF [10 0 R 20 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (stale-source.xml) /Desc (Stale source export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length " . strlen($staleSourcePayload) . " >>\nstream\n{$staleSourcePayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (stale-review.json) /Desc (Stale review packet) /AFRelationship /Alternative /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Length " . strlen($staleReviewPayload) . " >>\nstream\n{$staleReviewPayload}\nendstream\nendobj\n";

$attachments = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$encoded = json_encode($attachments, JSON_UNESCAPED_SLASHES);
if (count($attachments) !== 3) {
    throw new RuntimeException('Expected three current associated FileSpec rows.');
}
if (($attachments[0]['provenance_review']['relationship_role'] ?? null) !== 'original_source') {
    throw new RuntimeException('Expected standard Source AFRelationship provenance.');
}
if (($attachments[1]['provenance_review']['relationship_status'] ?? null) !== 'unrecognized_pdf_associated_file_relationship') {
    throw new RuntimeException('Expected custom AFRelationship review status.');
}
if (($attachments[2]['provenance_review']['relationship_status'] ?? null) !== 'missing_pdf_associated_file_relationship') {
    throw new RuntimeException('Expected missing AFRelationship review status.');
}
if ($plainText !== 'Current FileSpec Associated Body' || !is_string($encoded) || str_contains($encoded, 'stale-source.xml')) {
    throw new RuntimeException('Expected current xref-selected attachment rows and visible text.');
}
if (
    str_contains($plainText, '<wp-export>')
    || str_contains($plainText, 'relationship-mismatch')
    || str_contains($plainText, 'missing-relationship')
    || str_contains($plainText, 'Stale FileSpec Associated Body')
) {
    throw new RuntimeException('Expected attachment payloads and stale text to stay out of visible WordPress text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-attachment-filespec-afrelationship-checksum-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-associated-filespec-afrelationship-checksum-parser',
    'native_boundary' => 'current xref-selected catalog /AF FileSpec /AFRelationship and embedded-file /Params /CheckSum review before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'attachment_count' => count($attachments),
    'filenames' => array_map(static fn (array $attachment): string => (string) ($attachment['filename'] ?? ''), $attachments),
    'relationship_statuses' => array_map(static fn (array $attachment): ?string => $attachment['provenance_review']['relationship_status'] ?? null, $attachments),
    'checksum_matches' => array_map(static fn (array $attachment): ?bool => $attachment['provenance_review']['payload']['checksum_matches'] ?? null, $attachments),
    'current_xref_selected' => !str_contains((string) $encoded, 'stale-source.xml'),
    'visible_text_excludes_attachment_payloads' => !str_contains($plainText, '<wp-export>')
        && !str_contains($plainText, 'relationship-mismatch')
        && !str_contains($plainText, 'missing-relationship'),
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

foreach ($attachments as $attachment) {
    echo '<!-- wp:file {"href":"media/' . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
    echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</a></div>\n";
    echo "<!-- /wp:file -->\n";
    echo '<!-- markerpdf:associated-filespec-review ' . $htmlJson([
        'filename' => $attachment['filename'] ?? null,
        'description' => $attachment['description'] ?? null,
        'relationship' => $attachment['relationship'] ?? null,
        'relationship_status' => $attachment['provenance_review']['relationship_status'] ?? null,
        'relationship_role' => $attachment['provenance_review']['relationship_role'] ?? null,
        'checksum_matches' => $attachment['provenance_review']['payload']['checksum_matches'] ?? null,
        'payload_sha256' => $attachment['provenance_review']['payload']['sha256'] ?? null,
    ]) . " -->\n\n";
}
