<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$acroFormRootStreamBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm root stream boundary body) Tj ET';
    $acroFormStreamPayload = 'BT /F1 12 Tf 72 680 Td (AcroForm root stream payload leak) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) /Length " . strlen($acroFormStreamPayload) . " >>\nstream\n{$acroFormStreamPayload}\nendstream\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (streamroot.leak) /TU (Stream root field label) /TM (stream-root-export) /V (Stream root field value must not surface) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'rejects stream objects as AcroForm root dictionaries before field repair' => static function (
        TestRunner $t
    ) use ($acroFormRootStreamBoundaryPdf): void {
        $pdf = $acroFormRootStreamBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(false, $form['need_appearances']);
        $t->same([], $form['fields']);
        $t->same([], $form['calculation_order']);
        $t->same([], $form['calculation_order_review']);

        foreach ([
            'streamroot.leak',
            'Stream root field label',
            'stream-root-export',
            'Stream root field value must not surface',
        ] as $fieldLeak) {
            $t->true(is_string($encoded) && !str_contains($encoded, $fieldLeak));
            $t->true(!str_contains($visibleText, $fieldLeak));
        }

        $t->true(str_contains($visibleText, 'Visible AcroForm root stream boundary body'));
        $t->true(!str_contains($visibleText, 'AcroForm root stream payload leak'));
    },
];
