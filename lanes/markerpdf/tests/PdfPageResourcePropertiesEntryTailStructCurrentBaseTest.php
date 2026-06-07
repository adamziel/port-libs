<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourcePropertiesEntryTailStructPdf = static function (bool $directProperty): string {
    $content = 'BT /F1 12 Tf '
        . '/Body /BodyProp BDC 72 700 Td (Body malformed property leak) Tj EMC '
        . '/Title /TitleProp BDC 72 720 Td (Title structure first) Tj EMC ET';
    $bodyProperty = $directProperty
        ? '<< /MCID 1 >> 99 0 R'
        : '23 0 R 99 0 R';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> /Properties << /TitleProp 21 0 R /BodyProp {$bodyProperty} >> >>\nendobj\n"
        . "21 0 obj\n22 0 R\nendobj\n"
        . "22 0 obj\n<< /MCID 0 >>\nendobj\n";

    if (!$directProperty) {
        $pdf .= "23 0 obj\n24 0 R\nendobj\n"
            . "24 0 obj\n<< /MCID 1 >>\nendobj\n";
    }

    return $pdf
        . "20 0 obj\n<< /Type /StructTreeRoot /ParentTree 30 0 R /K [25 0 R 26 0 R] >>\nendobj\n"
        . "25 0 obj\n<< /Type /StructElem /S /H1 /P 20 0 R /Pg 3 0 R /K 0 >>\nendobj\n"
        . "26 0 obj\n<< /Type /StructElem /S /P /P 20 0 R /Pg 3 0 R /K 1 >>\nendobj\n"
        . "30 0 obj\n<< /Nums [0 [25 0 R 26 0 R]] >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'rejects tailed inherited Properties references before StructTree MCID ordering' => static function (
        TestRunner $t
    ) use ($pageResourcePropertiesEntryTailStructPdf): void {
        $pdf = $pageResourcePropertiesEntryTailStructPdf(false);
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $taggedText = array_column($extractor->extractTaggedContent($pdf), 'text');
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];

        $t->same(['Title structure first'], $extractor->extractTextLines($pdf));
        $t->same(['Title structure first'], $extractor->extractTextRuns($pdf));
        $t->same(['Title structure first'], $taggedText);
        $t->same('Title structure first', $plainText);
        $t->same("Title structure first\n", $extractor->naiveGetText($pdf));
        $t->same(1, count($boundary));
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(2, $resources['resource_owner_object'] ?? null);
        $t->same(10, $resources['resource_object'] ?? null);
        $t->same(['Font', 'Properties'], $resources['categories'] ?? null);
        $t->same(['F1'], $resources['font_names'] ?? null);
        $t->same(['TitleProp'], $resources['properties_names'] ?? null);
        $t->same([0, 1], $boundary[0]['parent_tree']['mcids'] ?? null);
        $t->same(false, str_contains($plainText, 'Body malformed property leak'));
        $t->same(false, in_array('Body malformed property leak', $taggedText, true));
        $t->same(false, str_contains($plainText, 'BodyProp'));
    },
    'rejects tailed direct Properties dictionaries before StructTree MCID ordering' => static function (
        TestRunner $t
    ) use ($pageResourcePropertiesEntryTailStructPdf): void {
        $pdf = $pageResourcePropertiesEntryTailStructPdf(true);
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $taggedText = array_column($extractor->extractTaggedContent($pdf), 'text');
        $resources = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf)[0]['resources'] ?? [];

        $t->same(['Title structure first'], $extractor->extractTextLines($pdf));
        $t->same(['Title structure first'], $extractor->extractTextRuns($pdf));
        $t->same(['Title structure first'], $taggedText);
        $t->same('Title structure first', $plainText);
        $t->same("Title structure first\n", $extractor->naiveGetText($pdf));
        $t->same(['TitleProp'], $resources['properties_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Body malformed property leak'));
        $t->same(false, in_array('Body malformed property leak', $taggedText, true));
        $t->same(false, str_contains($plainText, 'BodyProp'));
    },
];
