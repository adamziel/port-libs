<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceDuplicatePatternCurrentBasePdf = static function (): string {
    $pageContent = "BT /F1 12 Tf 72 720 Td (Before duplicate Pattern image) Tj ET\n"
        . "/Pattern cs /Dup#20Tile scn 0 0 20 10 re f\n"
        . "/Pattern cs /Valid#20Tile scn 30 0 20 10 re f\n"
        . 'BT /F1 12 Tf 72 660 Td (After duplicate Pattern image) Tj ET';
    $patternContent = 'q 6 0 0 3 1 2 cm /Tile#20Image Do Q';
    $stalePayload = 'BT /F1 12 Tf 72 720 Td (Stale duplicate Pattern image payload noise) Tj ET';
    $currentPayload = 'BT /F1 12 Tf 72 720 Td (Current duplicate Pattern image payload noise) Tj ET';
    $validPayload = 'BT /F1 12 Tf 72 720 Td (Valid inherited Pattern image payload noise) Tj ET';
    $staleCompressed = gzcompress($stalePayload);
    $currentCompressed = gzcompress($currentPayload);
    $validCompressed = gzcompress($validPayload);
    if (!is_string($staleCompressed) || !is_string($currentCompressed) || !is_string($validCompressed)) {
        throw new RuntimeException('Unable to compress duplicate Pattern image fixture payloads.');
    }

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /Pattern << /Dup#20Tile 11 0 R /Dup#20Tile 12 0 R /Valid#20Tile 13 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 6 /Height 3 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($staleCompressed) . " >>\nstream\n{$staleCompressed}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 6 /Height 3 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($currentCompressed) . " >>\nstream\n{$currentCompressed}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 6 /Height 3 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($validCompressed) . " >>\nstream\n{$validCompressed}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "11 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 20 20] /XStep 20 /YStep 20 /Resources << /XObject << /Tile#20Image 5 0 R >> >> /Length " . strlen($patternContent) . " >>\nstream\n{$patternContent}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 20 20] /XStep 20 /YStep 20 /Resources << /XObject << /Tile#20Image 6 0 R >> >> /Length " . strlen($patternContent) . " >>\nstream\n{$patternContent}\nendstream\nendobj\n"
        . "13 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 20 20] /XStep 20 /YStep 20 /Resources << /XObject << /Tile#20Image 7 0 R >> >> /Length " . strlen($patternContent) . " >>\nstream\n{$patternContent}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects duplicate inherited Pattern resource names before WordPress image review walks stale tiles' => static function (
        TestRunner $t
    ) use ($pageResourceDuplicatePatternCurrentBasePdf): void {
        $pdf = $pageResourceDuplicatePatternCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $propertyExtractor = new PdfPagePropertyExtractor();

        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);
        $metadata = $propertyExtractor->extractPageBoundaryMetadata($pdf);

        $entriesByPayload = [];
        foreach ($review['entries'] as $entry) {
            $entriesByPayload[$entry['decoded_sha256'] ?? ''] = $entry;
        }

        $validHash = hash('sha256', 'BT /F1 12 Tf 72 720 Td (Valid inherited Pattern image payload noise) Tj ET');
        $staleHash = hash('sha256', 'BT /F1 12 Tf 72 720 Td (Stale duplicate Pattern image payload noise) Tj ET');
        $currentHash = hash('sha256', 'BT /F1 12 Tf 72 720 Td (Current duplicate Pattern image payload noise) Tj ET');

        $t->same(1, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same(false, isset($entriesByPayload[$staleHash]));
        $t->same(false, isset($entriesByPayload[$currentHash]));
        $t->same(true, isset($entriesByPayload[$validHash]));

        $valid = $entriesByPayload[$validHash];
        $t->same('Tile Image', $valid['resource_name'] ?? null);
        $t->same('Valid Tile', $valid['pattern_resource_name'] ?? null);
        $t->same(['Valid Tile', 'Tile Image'], $valid['resource_path'] ?? null);
        $t->same(true, $valid['invoked']);
        $t->same(1, $valid['pattern_paint_count'] ?? null);
        $t->same(hash('sha256', 'BT /F1 12 Tf 72 720 Td (Valid inherited Pattern image payload noise) Tj ET'), $valid['decoded_sha256'] ?? null);

        $pageResources = $metadata[0]['resources'] ?? [];
        $t->same([3, 2], $pageResources['resource_lookup_objects'] ?? null);
        $t->same(['Font', 'Pattern'], $pageResources['categories'] ?? null);
        $t->same(['Valid Tile'], $pageResources['pattern_names'] ?? null);

        $t->same(['Before duplicate Pattern image', 'After duplicate Pattern image'], $extractor->extractTextLines($pdf));
        $t->same("Before duplicate Pattern image\nAfter duplicate Pattern image", $plainText);
        $t->same(false, str_contains($plainText, 'duplicate Pattern image payload noise'));
        $t->same(false, str_contains($plainText, 'Valid inherited Pattern image payload noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encoded, 'Stale duplicate Pattern image payload noise'));
        $t->same(false, str_contains($encoded, 'Current duplicate Pattern image payload noise'));
        $t->same(false, str_contains($encoded, 'Valid inherited Pattern image payload noise'));
        $t->same(false, str_contains($encoded, $staleHash));
        $t->same(false, str_contains($encoded, $currentHash));
        $t->same(true, str_contains($encoded, $validHash));
    },
];
