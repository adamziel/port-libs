<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceCommentReferenceCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode comment-delimited resource reference CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceCommentReferenceCurrentBaseCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceCommentReferencePdf = static function () use ($pageResourceCommentReferenceCMap): string {
    $inheritedContent = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /InheritedForm Do Q q /StaleForm Do Q';
    $localContent = 'BT /F2 12 Tf 72 680 Td <42> Tj ET q /LocalForm Do Q q /StaleForm Do Q';
    $inheritedForm = 'BT /F1 12 Tf 12 24 Td <43> Tj ET';
    $localForm = 'BT /F2 12 Tf 12 24 Td <44> Tj ET';
    $staleForm = 'BT /F1 12 Tf 12 24 Td <45> Tj ET';
    $cMap = $pageResourceCommentReferenceCMap([
        '41' => 'Comment inherited font text',
        '42' => 'Comment local font text',
        '43' => 'Comment inherited form text',
        '44' => 'Comment local form text',
        '45' => 'Comment split stale resource leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 /Resources 10 % object/generation split by PDF comment\n 0 % generation/R split by PDF comment\n R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /PieceInfo << /WPReview << /Private << /Resources 30 0 R >> >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources 20 % local object/generation split by PDF comment\n 0 % local generation/R split by PDF comment\n R /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($inheritedContent) . " >>\nstream\n{$inheritedContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($localContent) . " >>\nstream\n{$localContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CommentResourceFont /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($cMap) . " >>\nstream\n{$cMap}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Length " . strlen($inheritedForm) . " >>\nstream\n{$inheritedForm}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 7 0 R >> /XObject << /InheritedForm 9 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Length " . strlen($localForm) . " >>\nstream\n{$localForm}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Length " . strlen($staleForm) . " >>\nstream\n{$staleForm}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Font << /F2 7 0 R >> /XObject << /LocalForm 11 0 R >> >>\nendobj\n"
        . "30 0 obj\n<< /Font << /F1 7 0 R >> /XObject << /StaleForm 12 0 R >> >>\nendobj\n"
        . "%%EOF";
};

$pageResourceCommentReferenceWrapperPdf = static function () use ($pageResourceCommentReferenceCMap): string {
    $content = 'BT /Fwrap 12 Tf 72 720 Td <41> Tj ET q /WrappedForm Do Q q /StaleForm Do Q';
    $wrappedForm = 'BT /Fwrap 12 Tf 12 24 Td <42> Tj ET';
    $staleForm = 'BT /Fwrap 12 Tf 12 24 Td <43> Tj ET';
    $cMap = $pageResourceCommentReferenceCMap([
        '41' => 'Comment wrapper inherited font text',
        '42' => 'Comment wrapper inherited form text',
        '43' => 'Comment wrapper stale resource leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 12 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /PieceInfo << /WPReview << /Private << /Resources 30 0 R >> >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CommentWrapperFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cMap) . " >>\nstream\n{$cMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Length " . strlen($wrappedForm) . " >>\nstream\n{$wrappedForm}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Length " . strlen($staleForm) . " >>\nstream\n{$staleForm}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /Fwrap 5 0 R >> /XObject << /WrappedForm 7 0 R >> >>\nendobj\n"
        . "12 0 obj\n10 % wrapper object/generation split by PDF comment\n 0 % wrapper generation/R split by PDF comment\n R\nendobj\n"
        . "30 0 obj\n<< /Font << /Fwrap 5 0 R >> /XObject << /StaleForm 8 0 R >> >>\nendobj\n"
        . "%%EOF";
};

return [
    'treats PDF comments as whitespace inside page Resources references before inherited lookup' => static function (TestRunner $t) use ($pageResourceCommentReferencePdf): void {
        $pdf = $pageResourceCommentReferencePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $firstResources = $boundary[0]['resources'] ?? [];
        $secondResources = $boundary[1]['resources'] ?? [];
        $expected = [
            'Comment inherited font text',
            'Comment inherited form text',
            'Comment local font text',
            'Comment local form text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(2, count($boundary));
        $t->same(true, $firstResources['inherited'] ?? null);
        $t->same(2, $firstResources['resource_owner_object'] ?? null);
        $t->same(10, $firstResources['resource_object'] ?? null);
        $t->same(0, $firstResources['resource_generation'] ?? null);
        $t->same(['Font', 'XObject'], $firstResources['categories'] ?? null);
        $t->same(['F1'], $firstResources['font_names'] ?? null);
        $t->same(['InheritedForm'], $firstResources['xobject_names'] ?? null);
        $t->same(false, $secondResources['inherited'] ?? null);
        $t->same(4, $secondResources['resource_owner_object'] ?? null);
        $t->same(20, $secondResources['resource_object'] ?? null);
        $t->same(0, $secondResources['resource_generation'] ?? null);
        $t->same(['Font', 'XObject'], $secondResources['categories'] ?? null);
        $t->same(['F2'], $secondResources['font_names'] ?? null);
        $t->same(['LocalForm'], $secondResources['xobject_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Comment split stale'));
        $t->same(false, str_contains($plainText, 'StaleForm'));
    },
    'resolves comment-delimited page Resources wrapper objects before inherited lookup' => static function (TestRunner $t) use ($pageResourceCommentReferenceWrapperPdf): void {
        $pdf = $pageResourceCommentReferenceWrapperPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $expected = [
            'Comment wrapper inherited font text',
            'Comment wrapper inherited form text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, count($boundary));
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(2, $resources['resource_owner_object'] ?? null);
        $t->same(10, $resources['resource_object'] ?? null);
        $t->same(0, $resources['resource_generation'] ?? null);
        $t->same(['Font', 'XObject'], $resources['categories'] ?? null);
        $t->same(['Fwrap'], $resources['font_names'] ?? null);
        $t->same(['WrappedForm'], $resources['xobject_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Comment wrapper stale resource leak'));
        $t->same(false, str_contains($plainText, 'StaleForm'));
    },
];
