<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$encryptedPageLabelsPreviewPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Encrypted page label preview leak) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "10 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Nums [0 << /P (Secret-) /S /D /St 9 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Filter /Standard /V 1 /R 2 /P -44 /O <00010203> /U <04050607> >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 30 0 R >>\n%%EOF\n";
};

return [
    'blocks encrypted catalog PageLabels fallback in preview metadata' => static function (
        TestRunner $t
    ) use ($encryptedPageLabelsPreviewPdf): void {
        $pdf = $encryptedPageLabelsPreviewPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();
        $security = (new PdfSecurityPreflight())->analyze($pdf);

        $labels = $extractor->extractPageLabels($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = $preview->pageLabels($pdf);
        $imagePlan = $preview->getPageImagePlan($pdf, 1);

        $t->true($extractor->isEncrypted($pdf));
        $t->same([], $labels);
        $t->same('', $extractor->extractPlainText($pdf));
        $t->same(1, $summary['page_count']);
        $t->same(['1'], $previewLabels);
        $t->same('1', $summary['pages'][0]['page_label'] ?? null);
        $t->same('1', $imagePlan['page_label'] ?? null);
        $t->true($security['encrypted']);
        $t->same(false, $security['content_extraction_allowed']);
        $t->same('blocked_without_decryption', $security['text_extraction_policy']);
        $t->true(!in_array('Secret-9', $previewLabels, true));
        $t->true(!str_contains(json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '', 'Secret-9'));
    },
];
