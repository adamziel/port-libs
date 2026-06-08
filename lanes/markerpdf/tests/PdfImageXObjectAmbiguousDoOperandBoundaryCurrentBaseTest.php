<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

function markerpdf_image_xobject_ambiguous_do_operand_boundary_pdf(): array
{
    $pageContent = "BT /F1 12 Tf 72 720 Td (Before ambiguous image Do operand) Tj ET\n"
        . "q 20 0 0 10 72 690 cm /Decoy#20Image /Hero#20Image Do Q\n"
        . 'BT /F1 12 Tf 72 650 Td (After ambiguous image Do operand) Tj ET';

    $payloads = [
        'Decoy Image' => 'BT /F1 12 Tf 72 720 Td (Decoy Image Payload Noise) Tj ET',
        'Hero Image' => 'BT /F1 12 Tf 72 720 Td (Hero Image Payload Noise) Tj ET',
    ];

    $compressed = [];
    foreach ($payloads as $name => $payload) {
        $bytes = gzcompress($payload);
        if (!is_string($bytes)) {
            throw new RuntimeException("Unable to compress {$name} fixture payload.");
        }
        $compressed[$name] = $bytes;
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 8 0 R >> /XObject << /Decoy#20Image 5 0 R /Hero#20Image 6 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Decoy Image']) . " >>\nstream\n{$compressed['Decoy Image']}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Hero Image']) . " >>\nstream\n{$compressed['Hero Image']}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $payloads];
}

return [
    'rejects ambiguous multi-name Image XObject Do operands as unpainted review-only boundaries' => static function (TestRunner $t): void {
        [$pdf, $payloads] = markerpdf_image_xobject_ambiguous_do_operand_boundary_pdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['page_count']);
        $t->same(2, $review['image_xobject_count']);
        $t->same(0, $review['invoked_image_xobject_count']);
        $t->same(2, $review['uninvoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        foreach (['Decoy Image', 'Hero Image'] as $name) {
            $entry = $entriesByName[$name];
            $malformed = $entry['malformed_do_operands'][0];

            $t->same(false, $entry['invoked']);
            $t->same(0, $entry['invocation_count']);
            $t->same(1, $entry['malformed_do_operand_count']);
            $t->same(true, $entry['malformed_do_operand_review_only']);
            $t->same('reject_malformed_image_xobject_do_operands', $entry['malformed_do_operand_policy']);
            $t->same('ambiguous_do_resource_operands', $malformed['reason']);
            $t->same(1, $malformed['expected_operand_count']);
            $t->same(2, $malformed['operand_count']);
            $t->same(null, $malformed['resource_operand_index']);
            $t->same([0, 1], $malformed['resource_operand_indexes']);
            $t->same(['/Decoy Image', '/Hero Image'], $malformed['name_operands']);
            $t->same(['Decoy Image', 'Hero Image'], $malformed['resource_names']);
            $t->same(['name', 'name'], $malformed['operand_types']);
            $t->same(['/Decoy#20Image', '/Hero#20Image'], $malformed['operand_previews']);
            $t->same([[20.0, 0.0, 0.0, 10.0, 72.0, 690.0]], $malformed['matrices']);
            $t->same([[72.0, 690.0, 92.0, 700.0]], $malformed['bboxes']);
            $t->same([[72.0, 690.0, 92.0, 700.0]], $malformed['visible_bboxes']);
            $t->same(false, $malformed['paints_image']);
            $t->same(false, $malformed['payload_in_visible_text']);
            $t->same(true, $malformed['review_only']);
            $t->same(false, $entry['payload_in_visible_text']);
            $t->same(hash('sha256', $payloads[$name]), $entry['decoded_sha256']);
        }

        $t->same(
            ['Before ambiguous image Do operand', 'After ambiguous image Do operand'],
            $extractor->extractTextLines($pdf)
        );
        $t->same("Before ambiguous image Do operand\nAfter ambiguous image Do operand", $plainText);

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        foreach ($payloads as $payload) {
            $t->true(!str_contains($plainText, $payload));
            $t->true(!str_contains($encoded, $payload));
        }
    },
];
