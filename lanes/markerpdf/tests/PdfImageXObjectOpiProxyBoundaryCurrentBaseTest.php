<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'image XObject boundary review records OPI proxy dictionaries as review-only metadata' => static function (TestRunner $t): void {
        $pageContent = 'BT /F1 12 Tf 72 740 Td (Attachment intro) Tj ET '
            . 'q 24 0 0 12 72 690 cm /Proxy#20Image Do Q '
            . 'BT /F1 12 Tf 72 660 Td (Attachment outro) Tj ET';
        $imagePayload = 'BT /F1 12 Tf 72 720 Td (WordPress OPI image payload noise) Tj ET';

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 800] /Resources << /Font << /F1 4 0 R >> /XObject << /Proxy#20Image 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /OPI << /1#2E3 7 0 R /Private << /1#2E3 << /F (Nested OPI Decoy) >> >> >> /Length " . strlen($imagePayload) . " >>\nstream\n{$imagePayload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /OPI /Version 1.3 /F (highres-wordpress-hero.tif) /ImageType /proxy /IncludedImageDimensions [640 480] /CropRect [10 20 300 240] /Position [0 0 24 0 24 12 0 12] /Resolution [300 300] /Overprint true >>\nendobj\n"
            . "xref\n0 8\n0000000000 65535 f \ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);

        $t->same("Attachment intro\nAttachment outro", $plainText);
        $t->true(!str_contains($plainText, 'WordPress OPI image payload noise'));
        $t->true(!str_contains($plainText, 'highres-wordpress-hero.tif'));
        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(1, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $entry = $review['entries'][0];
        $encodedReview = json_encode($review, JSON_THROW_ON_ERROR);
        $t->same('Proxy Image', $entry['resource_name']);
        $t->same(hash('sha256', $imagePayload), $entry['decoded_sha256']);
        $t->same(false, $entry['payload_in_visible_text']);
        $t->true(!str_contains($encodedReview, 'WordPress OPI image payload noise'));
        $t->true(!str_contains($encodedReview, 'Nested OPI Decoy'));
        $t->same(true, $entry['opi_proxy_present']);
        $t->same(true, $entry['opi_proxy_review_only']);
        $t->same(false, $entry['opi_proxy_payload_in_visible_text']);

        $opi = $entry['opi_proxy_review'];
        $t->same(true, $opi['present']);
        $t->same(true, $opi['resolved']);
        $t->same(1, $opi['entry_count']);
        $t->same(false, $opi['payload_in_visible_text']);
        $t->same(true, $opi['review_only']);
        $t->same([], $opi['unresolved_versions']);

        $opiEntry = $opi['entries'][0];
        $t->same('1.3', $opiEntry['version']);
        $t->same(true, $opiEntry['resolved']);
        $t->same('OPI', $opiEntry['type']);
        $t->same(1.3, $opiEntry['version_value']);
        $t->same('highres-wordpress-hero.tif', $opiEntry['file_specification']);
        $t->same('proxy', $opiEntry['image_type']);
        $t->same([640.0, 480.0], $opiEntry['included_image_dimensions']);
        $t->same([10.0, 20.0, 300.0, 240.0], $opiEntry['crop_rect']);
        $t->same([0.0, 0.0, 24.0, 0.0, 24.0, 12.0, 0.0, 12.0], $opiEntry['position']);
        $t->same([300.0, 300.0], $opiEntry['resolution']);
        $t->same(true, $opiEntry['overprint']);
        $t->same(false, $opiEntry['payload_in_visible_text']);
        $t->same(true, $opiEntry['review_only']);
    },
];
