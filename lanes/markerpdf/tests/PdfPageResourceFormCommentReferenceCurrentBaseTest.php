<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceFormCommentReferenceCMap = static function (array $entries, string $name): string {
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
            throw new RuntimeException('Unable to encode form comment-reference resource CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /{$name} defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceFormCommentReferencePdf = static function () use ($pageResourceFormCommentReferenceCMap): string {
    $pageContent = 'BT /Fpage 12 Tf 72 720 Td <41> Tj ET q /Comment#20Form Do Q q /Null#20Wrapped#20Form Do Q';
    $localForm = 'BT /Flocal 10 Tf 12 24 Td <42> Tj ET';
    $nullWrappedForm = 'BT /Fpage 10 Tf 12 24 Td <43> Tj ET q /Inherited#20Nested Do Q';
    $nestedForm = 'BT /Fpage 9 Tf 6 12 Td <44> Tj ET';
    $pageCMap = $pageResourceFormCommentReferenceCMap([
        '41' => 'Page inherited form-comment font text',
        '43' => 'Null-wrapper form inherited page font text',
        '44' => 'Null-wrapper form inherited nested text',
    ], 'PageResourceFormCommentReferencePageCMap');
    $localCMap = $pageResourceFormCommentReferenceCMap([
        '42' => 'Comment-delimited form local resource text',
    ], 'PageResourceFormCommentReferenceLocalCMap');

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PageCommentFormFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageCMap) . " >>\nstream\n{$pageCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LocalCommentFormFont /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($localCMap) . " >>\nstream\n{$localCMap}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /Fpage 5 0 R >> /XObject << /Comment#20Form 20 0 R /Null#20Wrapped#20Form 21 0 R /Inherited#20Nested 22 0 R >> >>\nendobj\n"
        . "20 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Resources 30 % form resource object/generation split by PDF comment\n 0 % form generation/R split by PDF comment\n R /Length " . strlen($localForm) . " >>\nstream\n{$localForm}\nendstream\nendobj\n"
        . "21 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Resources 31 0 R /Length " . strlen($nullWrappedForm) . " >>\nstream\n{$nullWrappedForm}\nendstream\nendobj\n"
        . "22 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 180 60] /Length " . strlen($nestedForm) . " >>\nstream\n{$nestedForm}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Font << /Flocal 7 0 R >> >>\nendobj\n"
        . "31 0 obj\n32 % null-wrapper object/generation split by PDF comment\n 0 % null-wrapper generation/R split by PDF comment\n R\nendobj\n"
        . "32 0 obj\nnull\nendobj\n"
        . "%%EOF";
};

return [
    'resolves comment-delimited Form XObject Resources and inherits through null wrappers' => static function (
        TestRunner $t
    ) use ($pageResourceFormCommentReferencePdf): void {
        $pdf = $pageResourceFormCommentReferencePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $expected = [
            'Page inherited form-comment font text',
            'Comment-delimited form local resource text',
            'Null-wrapper form inherited page font text',
            'Null-wrapper form inherited nested text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, count($boundary));
        $t->same('resolved', $resources['status'] ?? null);
        $t->same(true, $resources['resolved'] ?? null);
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(2, $resources['resource_owner_object'] ?? null);
        $t->same(10, $resources['resource_object'] ?? null);
        $t->same(0, $resources['resource_generation'] ?? null);
        $t->same(['Font', 'XObject'], $resources['categories'] ?? null);
        $t->same(['Fpage'], $resources['font_names'] ?? null);
        $t->same(['Comment Form', 'Null Wrapped Form', 'Inherited Nested'], $resources['xobject_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Flocal'));
        $t->same(false, str_contains($plainText, 'Comment Form'));
        $t->same(false, str_contains($plainText, 'Null Wrapped Form'));
        $t->same(false, str_contains($plainText, 'PageResourceFormCommentReference'));
    },
];
