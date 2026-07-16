<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

function markerpdf_image_xobject_do_operand_boundary_pdf(): array
{
    $pageContent = "BT /F1 12 Tf 72 720 Td (Before image Do operand boundary) Tj ET\n"
        . "q 16 0 0 8 72 690 cm 99 /Prefix#20Operand#20Image Do Q\n"
        . "q 14 0 0 7 104 690 cm /Suffix#20Operand#20Image 99 Do Q\n"
        . "BT /F1 12 Tf /Text#20Object#20Image Do 72 675 Td (Text object Do is not a paint) Tj ET\n"
        . "BX q 10 0 0 5 128 690 cm /Compatibility#20Image Do Q EX\n"
        . "q 12 0 0 6 150 690 cm /Valid#20Image Do Q\n"
        . 'BT /F1 12 Tf 72 650 Td (After image Do operand boundary) Tj ET';

    $payloads = [
        'Prefix Operand Image' => 'BT /F1 12 Tf 72 720 Td (Prefix Operand Image Payload Noise) Tj ET',
        'Suffix Operand Image' => 'BT /F1 12 Tf 72 720 Td (Suffix Operand Image Payload Noise) Tj ET',
        'Text Object Image' => 'BT /F1 12 Tf 72 720 Td (Text Object Image Payload Noise) Tj ET',
        'Compatibility Image' => 'BT /F1 12 Tf 72 720 Td (Compatibility Image Payload Noise) Tj ET',
        'Valid Image' => 'BT /F1 12 Tf 72 720 Td (Valid Do Operand Image Payload Noise) Tj ET',
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
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Prefix#20Operand#20Image 5 0 R /Suffix#20Operand#20Image 6 0 R /Text#20Object#20Image 7 0 R /Compatibility#20Image 8 0 R /Valid#20Image 9 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Prefix Operand Image']) . " >>\nstream\n{$compressed['Prefix Operand Image']}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Suffix Operand Image']) . " >>\nstream\n{$compressed['Suffix Operand Image']}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Text Object Image']) . " >>\nstream\n{$compressed['Text Object Image']}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Compatibility Image']) . " >>\nstream\n{$compressed['Compatibility Image']}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Valid Image']) . " >>\nstream\n{$compressed['Valid Image']}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $payloads];
}

return [
    'records malformed Image XObject Do operands as unpainted review-only boundaries' => static function (TestRunner $t): void {
        [$pdf, $payloads] = markerpdf_image_xobject_do_operand_boundary_pdf();
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
        $t->same(5, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(4, $review['uninvoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $prefix = $entriesByName['Prefix Operand Image'];
        $t->same(false, $prefix['invoked']);
        $t->same(0, $prefix['invocation_count']);
        $t->same(1, $prefix['malformed_do_operand_count']);
        $t->same(true, $prefix['malformed_do_operand_review_only']);
        $t->same('reject_malformed_image_xobject_do_operands', $prefix['malformed_do_operand_policy']);
        $t->same('extra_do_operands', $prefix['malformed_do_operands'][0]['reason']);
        $t->same(2, $prefix['malformed_do_operands'][0]['operand_count']);
        $t->same(1, $prefix['malformed_do_operands'][0]['resource_operand_index']);
        $t->same(['/Prefix Operand Image'], $prefix['malformed_do_operands'][0]['name_operands']);
        $t->same(['number', 'name'], $prefix['malformed_do_operands'][0]['operand_types']);
        $t->same(['99', '/Prefix#20Operand#20Image'], $prefix['malformed_do_operands'][0]['operand_previews']);
        $t->same([[16.0, 0.0, 0.0, 8.0, 72.0, 690.0]], $prefix['malformed_do_operands'][0]['matrices']);
        $t->same([[72.0, 690.0, 88.0, 698.0]], $prefix['malformed_do_operands'][0]['bboxes']);
        $t->same(false, $prefix['payload_in_visible_text']);
        $t->same(hash('sha256', $payloads['Prefix Operand Image']), $prefix['decoded_sha256']);

        $suffix = $entriesByName['Suffix Operand Image'];
        $t->same(false, $suffix['invoked']);
        $t->same(0, $suffix['invocation_count']);
        $t->same(1, $suffix['malformed_do_operand_count']);
        $t->same(0, $suffix['malformed_do_operands'][0]['resource_operand_index']);
        $t->same(['/Suffix Operand Image'], $suffix['malformed_do_operands'][0]['name_operands']);
        $t->same(['name', 'number'], $suffix['malformed_do_operands'][0]['operand_types']);
        $t->same(['Suffix Operand Image'], $suffix['malformed_do_operands'][0]['resource_names']);
        $t->same([[104.0, 690.0, 118.0, 697.0]], $suffix['malformed_do_operands'][0]['bboxes']);
        $t->same(false, $suffix['payload_in_visible_text']);
        $t->same(hash('sha256', $payloads['Suffix Operand Image']), $suffix['decoded_sha256']);

        $textObject = $entriesByName['Text Object Image'];
        $t->same(false, $textObject['invoked']);
        $t->same(0, $textObject['invocation_count']);
        $t->same(0, $textObject['malformed_do_operand_count']);
        $t->same([], $textObject['malformed_do_operands']);

        $compatibility = $entriesByName['Compatibility Image'];
        $t->same(false, $compatibility['invoked']);
        $t->same(0, $compatibility['invocation_count']);
        $t->same(0, $compatibility['malformed_do_operand_count']);
        $t->same([], $compatibility['malformed_do_operands']);

        $valid = $entriesByName['Valid Image'];
        $t->same(true, $valid['invoked']);
        $t->same(1, $valid['invocation_count']);
        $t->same(0, $valid['malformed_do_operand_count']);
        $t->same([], $valid['malformed_do_operands']);
        $t->same([[12.0, 0.0, 0.0, 6.0, 150.0, 690.0]], $valid['invocation_matrices']);
        $t->same([150.0, 690.0, 162.0, 696.0], $valid['image_unit_bbox']);
        $t->same(hash('sha256', $payloads['Valid Image']), $valid['decoded_sha256']);

        $t->same(
            ['Before image Do operand boundary', 'Text object Do is not a paint', 'After image Do operand boundary'],
            $extractor->extractTextLines($pdf)
        );
        $t->same(
            "Before image Do operand boundary\nText object Do is not a paint\nAfter image Do operand boundary",
            $plainText
        );

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        foreach ($payloads as $payload) {
            $t->true(!str_contains($plainText, $payload));
            $t->true(!str_contains($encoded, $payload));
        }
    },
];
