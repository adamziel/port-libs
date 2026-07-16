<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceLookupLineageCurrentBasePdf = static function (): string {
    $branchContent = "BT /Fbranch 12 Tf 72 720 Td (Branch inherited resource text) Tj ET\n"
        . "q /BranchForm Do Q";
    $blockedContent = "BT /Fbranch 12 Tf 72 720 Td (Malformed page raw text) Tj ET\n"
        . "q /BranchForm Do Q";
    $branchForm = 'BT /Fbranch 12 Tf 12 24 Td (Branch inherited form text) Tj ET';
    $rootForm = 'BT /Froot 12 Tf 12 24 Td (Root resource form leak) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [10 0 R] /Count 2 /Resources 30 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R 4 0 R] /Count 2 /Resources 20 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 10 0 R /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 10 0 R /Resources << /Font << /Fbranch 7 0 R >> /XObject << /BranchForm 8 0 R >> >> 99 0 R /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($branchContent) . " >>\nstream\n{$branchContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($blockedContent) . " >>\nstream\n{$blockedContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "8 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($branchForm) . " >>\nstream\n{$branchForm}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>\nendobj\n"
        . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($rootForm) . " >>\nstream\n{$rootForm}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Font << /Fbranch 7 0 R >> /XObject << /BranchForm 8 0 R >> >>\nendobj\n"
        . "30 0 obj\n<< /Font << /Froot 9 0 R >> /XObject << /RootForm 11 0 R >> >>\nendobj\n"
        . "%%EOF";
};

return [
    'records page-tree resource lookup lineage for inherited branch resources without merging root decoys' => static function (TestRunner $t) use ($pageResourceLookupLineageCurrentBasePdf): void {
        $pdf = $pageResourceLookupLineageCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same([
            'Branch inherited resource text',
            'Branch inherited form text',
            'Malformed page raw text',
        ], $extractor->extractTextLines($pdf));
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(10, $resources['resource_owner_object'] ?? null);
        $t->same(20, $resources['resource_object'] ?? null);
        $t->same([3, 10], $resources['resource_lookup_objects'] ?? null);
        $t->same(['Font', 'XObject'], $resources['categories'] ?? null);
        $t->same(['Fbranch'], $resources['font_names'] ?? null);
        $t->same(['BranchForm'], $resources['xobject_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Root resource form leak'));
        $t->same(false, in_array(2, $resources['resource_lookup_objects'] ?? [], true));
    },
    'records malformed page resource lookup lineage before parent fallback is blocked' => static function (TestRunner $t) use ($pageResourceLookupLineageCurrentBasePdf): void {
        $pdf = $pageResourceLookupLineageCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[1]['resources'] ?? [];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('unresolved_or_malformed', $resources['status'] ?? null);
        $t->same(false, $resources['resolved'] ?? null);
        $t->same(4, $resources['resource_owner_object'] ?? null);
        $t->same(false, $resources['inherited'] ?? null);
        $t->same([4], $resources['resource_lookup_objects'] ?? null);
        $t->same([], $resources['categories'] ?? null);
        $t->same(1, substr_count($plainText, 'Malformed page raw text'));
        $t->same(false, str_contains($plainText, 'Root resource form leak'));
        $malformedOffset = strpos($plainText, 'Malformed page raw text');
        $afterMalformedPage = $malformedOffset === false ? '' : substr($plainText, $malformedOffset);
        $t->same(false, str_contains($afterMalformedPage, 'Branch inherited form text'));
    },
];
