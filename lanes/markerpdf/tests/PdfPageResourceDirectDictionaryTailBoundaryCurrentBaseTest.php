<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceDirectDictionaryTailCMap = static function (array $entries): string {
    $body = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . count($entries) . " beginbfchar\n";

    foreach ($entries as $sourceHex => $text) {
        $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', (string) $text);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode direct resource dictionary tail CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceDirectDictionaryTailCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceDirectDictionaryTailPdf = static function () use ($pageResourceDirectDictionaryTailCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /TailForm Do Q';
    $form = 'BT /F1 12 Tf 12 24 Td (Direct dictionary tail form leak) Tj ET';
    $cmap = $pageResourceDirectDictionaryTailCMap([
        '41' => 'Direct dictionary tail font leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> /XObject << /TailForm 7 0 R >> >> 99 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DirectResourceTail /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($form) . " >>\nstream\n{$form}\nendstream\nendobj\n"
        . "99 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /TailForm 7 0 R >> >>\nendobj\n"
        . "%%EOF";
};

return [
    'fails closed on direct page Resources dictionaries with non-name trailing tokens' => static function (TestRunner $t) use ($pageResourceDirectDictionaryTailPdf): void {
        $pdf = $pageResourceDirectDictionaryTailPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];

        $t->same(['A'], $extractor->extractTextLines($pdf));
        $t->same(['A'], $extractor->extractTextRuns($pdf));
        $t->same('A', $plainText);
        $t->same("A\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, count($boundary));
        $t->same('unresolved_or_malformed', $resources['status'] ?? null);
        $t->same(false, $resources['resolved'] ?? null);
        $t->same(false, $resources['inherited'] ?? null);
        $t->same(3, $resources['resource_owner_object'] ?? null);
        $t->same([], $resources['categories'] ?? null);
        $t->same(false, str_contains($plainText, 'Direct dictionary tail font leak'));
        $t->same(false, str_contains($plainText, 'Direct dictionary tail form leak'));
        $t->same(false, str_contains($plainText, 'TailForm'));
    },
];
