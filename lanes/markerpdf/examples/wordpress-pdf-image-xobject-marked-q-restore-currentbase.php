<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Before marked q image) Tj ET\n"
    . "q /Figure << /MCID 7 /Alt (WordPress Figure Image Review) >> BDC 20 0 0 10 72 690 cm Q\n"
    . "/Marked#20Image Do EMC\n"
    . 'BT /F1 12 Tf 72 660 Td (After marked q image) Tj ET';
$imagePayload = 'BT /F1 12 Tf 72 720 Td (Marked q Image Payload Noise) Tj ET';
$compressed = gzcompress($imagePayload);
if (!is_string($compressed)) {
    throw new RuntimeException('Unable to compress marked q/Q image XObject smoke payload.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Marked#20Image 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$marked = $entry['invocation_marked_content'][0] ?? [];
$encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';

$checks = [
    'source' => 'native-pdf-image-xobject-marked-q-restore-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images image handoff; q/Q restores graphics state but not the marked-content stack around Image XObject review',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'] ?? null,
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'] ?? null,
    'marked_content_preserved_after_q_restore' => ($marked['tags'] ?? null) === ['Figure']
        && ($marked['mcids'] ?? null) === [7],
    'marked_content_alt_text_preserved' => ($marked['stack'][0]['alt_text'] ?? null) === 'WordPress Figure Image Review',
    'image_payloads_excluded_from_text' => !str_contains($plainText, 'Marked q Image Payload Noise'),
    'image_payloads_excluded_from_review_json' => !str_contains($encodedReview, $imagePayload),
    'visible_paragraphs_preserved' => $lines === ['Before marked q image', 'After marked q image'],
];

foreach ($checks as $name => $value) {
    if (str_starts_with($name, 'executes_') || !is_bool($value)) {
        continue;
    }

    if ($value !== true) {
        throw new RuntimeException("Failed marked q/Q image XObject smoke check: {$name}");
    }
}

echo '<!-- markerpdf:pdf-image-xobject-marked-q-restore-currentbase '
    . htmlspecialchars(json_encode($checks, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
