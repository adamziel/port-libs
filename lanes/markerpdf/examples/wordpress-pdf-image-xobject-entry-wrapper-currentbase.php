<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$imagePayload = 'WordPress wrapped image payload noise';
$nestedPayload = 'WordPress nested wrapped image payload noise';
$pageContent = 'BT /F1 12 Tf 72 740 Td (Attachment intro) Tj ET '
    . 'q 20 0 0 10 72 690 cm /Wrapped#20Image Do Q '
    . 'q 30 0 0 15 110 690 cm /Wrapped#20Form Do Q '
    . 'BT /F1 12 Tf 72 660 Td (Attachment outro) Tj ET';
$formContent = 'q 4 0 0 2 1 1 cm /Nested#20Wrapped Do Q';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 800] /Resources << /Font << /F1 4 0 R >> /XObject << /Wrapped#20Image 5 0 R /Wrapped#20Form 7 0 R >> >> /Contents 11 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "5 0 obj\n6 0 R\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length " . strlen($imagePayload) . " >>\nstream\n{$imagePayload}\nendstream\nendobj\n"
    . "7 0 obj\n8 0 R\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 10 10] /Resources << /XObject << /Nested#20Wrapped 9 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length " . strlen($nestedPayload) . " >>\nstream\n{$nestedPayload}\nendstream\nendobj\n"
    . "11 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "xref\n0 12\n0000000000 65535 f \ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);

if (
    $plainText !== "Attachment intro\nAttachment outro"
    || str_contains($plainText, $imagePayload)
    || str_contains($plainText, $nestedPayload)
    || ($review['image_xobject_count'] ?? 0) !== 2
    || ($review['invoked_image_xobject_count'] ?? 0) !== 2
    || ($review['entries'][0]['resource_name'] ?? null) !== 'Wrapped Image'
    || ($review['entries'][0]['object_number'] ?? null) !== 6
    || ($review['entries'][0]['decoded_sha256'] ?? null) !== hash('sha256', $imagePayload)
    || ($review['entries'][1]['resource_path'] ?? null) !== ['Wrapped Form', 'Nested Wrapped']
    || ($review['entries'][1]['parent_form_xobject_object'] ?? null) !== 8
    || ($review['entries'][1]['decoded_sha256'] ?? null) !== hash('sha256', $nestedPayload)
) {
    throw new RuntimeException('Wrapped image XObject resource smoke failed.');
}

$summary = [
    'source' => 'native-pdf-image-xobject-entry-wrapper-currentbase',
    'wordpress_path' => 'searchable PDF attachment import keeps text clean while handing wrapped image XObjects to media review',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'plain_text' => $plainText,
    'image_xobject_count' => $review['image_xobject_count'],
    'resolved_objects' => [
        [
            'resource_name' => $review['entries'][0]['resource_name'],
            'object_number' => $review['entries'][0]['object_number'],
            'decoded_sha256' => $review['entries'][0]['decoded_sha256'],
        ],
        [
            'resource_path' => $review['entries'][1]['resource_path'],
            'object_number' => $review['entries'][1]['object_number'],
            'parent_form_xobject_object' => $review['entries'][1]['parent_form_xobject_object'],
            'decoded_sha256' => $review['entries'][1]['decoded_sha256'],
        ],
    ],
];

echo '<!-- markerpdf-image-xobject-entry-wrapper-currentbase: ok -->' . PHP_EOL;
echo '<pre>' . htmlspecialchars(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '', ENT_QUOTES, 'UTF-8') . '</pre>' . PHP_EOL;
