<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfPagePropertyExtractor;

$pageResourceInheritanceCurrentBaseCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode focused resource inheritance CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceInheritanceCurrentBaseCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceInheritanceCurrentBasePdf = static function (): string {
    $pageContent = 'q /LegacyOuter Do Q q /ExplicitOuter Do Q';
    $legacyOuter = 'q /LegacyNested Do Q';
    $legacyNested = 'BT /F1 12 Tf 12 24 Td (Legacy nested form inherited resources) Tj ET';
    $explicitOuter = 'q /LegacyNested Do Q BT /F1 12 Tf 12 24 Td (Explicit form local resources) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 180 80] /Length " . strlen($legacyOuter) . " >>\nstream\n{$legacyOuter}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 180 80] /Length " . strlen($legacyNested) . " >>\nstream\n{$legacyNested}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "8 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 180 80] /Resources << /Font << /F1 9 0 R >> >> /Length " . strlen($explicitOuter) . " >>\nstream\n{$explicitOuter}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 7 0 R >> /XObject << /LegacyOuter 5 0 R /LegacyNested 6 0 R /ExplicitOuter 8 0 R >> >>\nendobj\n"
        . "%%EOF";
};

$pageResourceNestedCategoryCurrentBasePdf = static function () use ($pageResourceInheritanceCurrentBaseCMap): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /CurrentForm Do Q';
    $privateForm = 'BT /F1 12 Tf 12 24 Td (Private nested XObject leak) Tj ET';
    $currentForm = 'BT /F1 12 Tf 12 24 Td (Current inherited form text) Tj ET';
    $privateCMap = $pageResourceInheritanceCurrentBaseCMap([
        '41' => 'Private nested font leak',
    ]);
    $currentCMap = $pageResourceInheritanceCurrentBaseCMap([
        '41' => 'Current inherited font text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PrivateNestedFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($privateCMap) . " >>\nstream\n{$privateCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($privateForm) . " >>\nstream\n{$privateForm}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CurrentInheritedFont /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($currentCMap) . " >>\nstream\n{$currentCMap}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Properties << /Private << /Font << /F1 5 0 R >> /XObject << /CurrentForm 7 0 R >> >> >> /Font << /F1 8 0 R >> /XObject << /CurrentForm 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($currentForm) . " >>\nstream\n{$currentForm}\nendstream\nendobj\n"
        . "%%EOF";
};

$pageResourceTopLevelParentCurrentBasePdf = static function () use ($pageResourceInheritanceCurrentBaseCMap): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /InheritedForm Do Q';
    $currentForm = 'BT /F1 12 Tf 12 24 Td (Top level parent form text) Tj ET';
    $privateForm = 'BT /F1 12 Tf 12 24 Td (Nested decoy parent form leak) Tj ET';
    $currentCMap = $pageResourceInheritanceCurrentBaseCMap([
        '41' => 'Top level parent font text',
    ]);
    $privateCMap = $pageResourceInheritanceCurrentBaseCMap([
        '41' => 'Nested decoy parent font leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /PieceInfo << /WPReview << /Private << /Parent 99 0 R >> /ReviewOnly true >> >> /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /TopLevelParentFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($currentCMap) . " >>\nstream\n{$currentCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($currentForm) . " >>\nstream\n{$currentForm}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NestedPrivateParentFont /Encoding /Identity-H /ToUnicode 11 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($privateForm) . " >>\nstream\n{$privateForm}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /InheritedForm 7 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Length " . strlen($privateCMap) . " >>\nstream\n{$privateCMap}\nendstream\nendobj\n"
        . "99 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 8 0 R >> /XObject << /InheritedForm 9 0 R >> >> >>\nendobj\n"
        . "%%EOF";
};

$pageResourceIndirectNullCurrentBasePdf = static function () use ($pageResourceInheritanceCurrentBaseCMap): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /InheritedNullForm Do Q';
    $inheritedForm = 'BT /F1 12 Tf 12 24 Td (Indirect null inherited form text) Tj ET';
    $emptyResourceContent = 'q /InheritedNullForm Do Q';
    $inheritedCMap = $pageResourceInheritanceCurrentBaseCMap([
        '41' => 'Indirect null inherited font text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources 12 0 R /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources 13 0 R /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($emptyResourceContent) . " >>\nstream\n{$emptyResourceContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /IndirectNullInherited /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($inheritedCMap) . " >>\nstream\n{$inheritedCMap}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($inheritedForm) . " >>\nstream\n{$inheritedForm}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 7 0 R >> /XObject << /InheritedNullForm 9 0 R >> >>\nendobj\n"
        . "12 0 obj\nnull\nendobj\n"
        . "13 0 obj\n<< >>\nendobj\n"
        . "%%EOF";
};

$pageResourceGenerationBoundaryCurrentBasePdf = static function () use ($pageResourceInheritanceCurrentBaseCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /SharedForm Do Q';
    $parentForm = 'BT /F1 12 Tf 12 24 Td (Parent generation form leak) Tj ET';
    $staleForm = 'BT /F1 12 Tf 12 24 Td (Stale generation form leak) Tj ET';
    $parentCMap = $pageResourceInheritanceCurrentBaseCMap([
        '41' => 'Parent generation font leak',
    ]);
    $staleCMap = $pageResourceInheritanceCurrentBaseCMap([
        '41' => 'Stale generation font leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources 12 1 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ParentGenerationFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($parentCMap) . " >>\nstream\n{$parentCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($parentForm) . " >>\nstream\n{$parentForm}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /StaleGenerationFont /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($staleCMap) . " >>\nstream\n{$staleCMap}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /SharedForm 7 0 R >> >>\nendobj\n"
        . "12 0 obj\n<< /Font << /F1 8 0 R >> /XObject << /SharedForm 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($staleForm) . " >>\nstream\n{$staleForm}\nendstream\nendobj\n"
        . "%%EOF";
};

$pageResourceEscapedTypeLineageCurrentBasePdf = static function () use ($pageResourceInheritanceCurrentBaseCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /EscapedForm Do Q';
    $form = 'BT /F1 12 Tf 12 24 Td (Escaped type inherited form text) Tj ET';
    $cmap = $pageResourceInheritanceCurrentBaseCMap([
        '41' => 'Escaped type inherited font text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pa#67es /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /P#61ge /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /EscapedPageTypeFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($form) . " >>\nstream\n{$form}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /EscapedForm 7 0 R >> >>\nendobj\n"
        . "%%EOF";
};

$pageResourceParentGenerationCurrentBasePdf = static function () use ($pageResourceInheritanceCurrentBaseCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /StaleParentForm Do Q';
    $staleForm = 'BT /F1 12 Tf 12 24 Td (Stale parent generation form leak) Tj ET';
    $staleCMap = $pageResourceInheritanceCurrentBaseCMap([
        '41' => 'Stale parent generation font leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 1 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /StaleParentGenerationFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($staleCMap) . " >>\nstream\n{$staleCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Length " . strlen($staleForm) . " >>\nstream\n{$staleForm}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /StaleParentForm 7 0 R >> >>\nendobj\n"
        . "%%EOF";
};

$pageResourceKidGenerationCurrentBasePdf = static function () use ($pageResourceInheritanceCurrentBaseCMap): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td <42> Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
    $cmap = $pageResourceInheritanceCurrentBaseCMap([
        '41' => 'Current kid generation inherited text',
        '42' => 'Stale kid generation resource leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 1 R 8 0 R] /Count 2 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /KidGenerationInherited /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 9 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> >>\nendobj\n"
        . "%%EOF";
};

$pageResourceKidGenerationAllStaleCurrentBasePdf = static function () use ($pageResourceInheritanceCurrentBaseCMap): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td <42> Tj ET';
    $cmap = $pageResourceInheritanceCurrentBaseCMap([
        '42' => 'All stale kid generation fallback leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 1 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /AllStaleKidGeneration /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> >>\nendobj\n"
        . "%%EOF";
};

$pageResourceKidsPathNoParentCurrentBasePdf = static function () use ($pageResourceInheritanceCurrentBaseCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /BranchForm Do Q q /RootForm Do Q';
    $branchForm = 'BT /F1 12 Tf 12 24 Td (Catalog path inherited form text) Tj ET';
    $rootForm = 'BT /F1 12 Tf 12 24 Td (Root resource form leak) Tj ET';
    $branchCMap = $pageResourceInheritanceCurrentBaseCMap([
        '41' => 'Catalog path inherited font text',
    ]);
    $rootCMap = $pageResourceInheritanceCurrentBaseCMap([
        '41' => 'Root resource font leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [10 0 R] /Count 1 /Resources 30 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 20 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CatalogPathInherited /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($branchCMap) . " >>\nstream\n{$branchCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($branchForm) . " >>\nstream\n{$branchForm}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /RootResourceLeak /Encoding /Identity-H /ToUnicode 11 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($rootForm) . " >>\nstream\n{$rootForm}\nendstream\nendobj\n"
        . "11 0 obj\n<< /Length " . strlen($rootCMap) . " >>\nstream\n{$rootCMap}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /BranchForm 7 0 R >> >>\nendobj\n"
        . "30 0 obj\n<< /Font << /F1 8 0 R >> /XObject << /RootForm 9 0 R >> >>\nendobj\n"
        . "%%EOF";
};

$pageResourceTrailerRootGenerationMismatchCurrentBasePdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Stale trailer root resource text) Tj ET q /StaleRootForm Do Q';
    $form = 'BT /F1 12 Tf 12 24 Td (Stale trailer root inherited form) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($form) . " >>\nstream\n{$form}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /StaleRootForm 6 0 R >> >>\nendobj\n"
        . "trailer\n<< /Root 1 1 R >>\n%%EOF";
};

$pageResourceFormNullCurrentBasePdf = static function () use ($pageResourceInheritanceCurrentBaseCMap): string {
    $content = 'q /DirectNullForm Do Q q /IndirectNullForm Do Q q /ExplicitEmptyForm Do Q';
    $directNullForm = 'q /InheritedNestedForm Do Q';
    $indirectNullForm = 'q /InheritedNestedForm Do Q';
    $explicitEmptyForm = 'q /InheritedNestedForm Do Q';
    $nestedForm = 'BT /F1 12 Tf 12 24 Td <41> Tj ET';
    $cmap = $pageResourceInheritanceCurrentBaseCMap([
        '41' => 'Null form inherited nested text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Resources null /Length " . strlen($directNullForm) . " >>\nstream\n{$directNullForm}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Resources 12 0 R /Length " . strlen($indirectNullForm) . " >>\nstream\n{$indirectNullForm}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Resources 13 0 R /Length " . strlen($explicitEmptyForm) . " >>\nstream\n{$explicitEmptyForm}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($nestedForm) . " >>\nstream\n{$nestedForm}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NullFormInherited /Encoding /Identity-H /ToUnicode 11 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 9 0 R >> /XObject << /DirectNullForm 5 0 R /IndirectNullForm 6 0 R /ExplicitEmptyForm 7 0 R /InheritedNestedForm 8 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "12 0 obj\nnull\nendobj\n"
        . "13 0 obj\n<< >>\nendobj\n"
        . "%%EOF";
};

$pageResourceFormPropertiesCurrentBasePdf = static function (): string {
    $content = '/Span /SharedActual BDC BT /F1 12 Tf 72 720 Td (Page glyph noise) Tj ET EMC '
        . 'q /ActualForm Do Q '
        . 'BT /F1 12 Tf 72 650 Td (After form glyph) Tj ET';
    $formContent = '/Span /SharedActual BDC BT /F1 12 Tf 12 24 Td (Form glyph noise) Tj ET EMC '
        . '/Span /FormOnly BDC BT /F1 12 Tf 12 12 Td (Alt glyph noise) Tj ET EMC';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 7 0 R >> /XObject << /ActualForm 5 0 R >> /Properties << /SharedActual << /ActualText (Page resource ActualText) >> >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 200 80] /Resources << /Font << /F1 7 0 R >> /Properties << /SharedActual << /ActualText (Form local ActualText) >> /FormOnly << /Alt (Form local Alt text) >> >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$pageResourceDirectEntryBoundaryCurrentBasePdf = static function () use ($pageResourceInheritanceCurrentBaseCMap): string {
    $content = 'BT /Fvalid 12 Tf 72 720 Td <41> Tj T* /Span /GoodActual BDC <42> Tj EMC ET q /ValidForm Do Q';
    $formContent = 'BT /Fvalid 12 Tf 12 24 Td <43> Tj ET';
    $cmap = $pageResourceInheritanceCurrentBaseCMap([
        '41' => 'Valid inherited direct-entry font text',
        '42' => 'Valid inherited direct-entry actual text glyph',
        '43' => 'Valid inherited direct-entry form text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ValidInheritedEntryFont /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /ActualText (Valid inherited direct-entry actual text) >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /BadArray [99 0 R] /BadName /Helvetica /BadString (Font decoy review leak) /Fvalid 5 0 R >> "
        . "/XObject << /BadArray [6 0 R] /BadName /Image /ValidForm 6 0 R >> "
        . "/Properties << /BadArray [7 0 R] /BadName /Artifact /GoodActual 7 0 R >> "
        . "/ColorSpace << /CS1 /DeviceRGB /CS2 [/Indexed /DeviceRGB 0 <00>] >> >>\nendobj\n"
        . "%%EOF";
};

$pageResourceIndirectWrapperCurrentBasePdf = static function () use ($pageResourceInheritanceCurrentBaseCMap): string {
    $content = 'BT /Fwrapped 12 Tf 72 720 Td <41> Tj T* /Span /WrappedActual BDC <42> Tj EMC ET q /WrappedForm Do Q';
    $formContent = 'BT /Fwrapped 12 Tf 12 24 Td <43> Tj ET';
    $cmap = $pageResourceInheritanceCurrentBaseCMap([
        '41' => 'Wrapped inherited font text',
        '42' => 'Wrapped inherited actual text glyph',
        '43' => 'Wrapped inherited form text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 12 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WrappedInheritedFont /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /ActualText (Wrapped inherited actual text) >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font 14 0 R /XObject 16 0 R /Properties 18 0 R >>\nendobj\n"
        . "12 0 obj\n10 0 R\nendobj\n"
        . "14 0 obj\n15 0 R\nendobj\n"
        . "15 0 obj\n<< /Fwrapped 5 0 R >>\nendobj\n"
        . "16 0 obj\n17 0 R\nendobj\n"
        . "17 0 obj\n<< /WrappedForm 6 0 R >>\nendobj\n"
        . "18 0 obj\n19 0 R\nendobj\n"
        . "19 0 obj\n<< /WrappedActual 7 0 R >>\nendobj\n"
        . "%%EOF";
};

return [
    'uses inherited page resources for legacy Form XObjects that omit Resources without merging explicit form resources' => static function (TestRunner $t) use ($pageResourceInheritanceCurrentBasePdf): void {
        $pdf = $pageResourceInheritanceCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $expected = [
            'Legacy nested form inherited resources',
            'Explicit form local resources',
        ];
        $plainText = $extractor->extractPlainText($pdf);
        $styledPages = $extractor->extractStyledTextPages($pdf);
        $styledLines = array_map(
            static fn (array $block): string => implode('', array_column($block['lines'][0]['spans'], 'text')),
            $styledPages[0]['blocks'] ?? []
        );

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same($expected, $styledLines);
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, substr_count($plainText, 'Legacy nested form inherited resources'));
        $t->same(false, str_contains($plainText, 'LegacyNested'));
        $t->same(false, str_contains($plainText, 'LegacyOuter'));
    },
    'uses top-level inherited resource categories before nested decoy dictionaries' => static function (TestRunner $t) use ($pageResourceNestedCategoryCurrentBasePdf): void {
        $pdf = $pageResourceNestedCategoryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $expected = [
            'Current inherited font text',
            'Current inherited form text',
        ];
        $plainText = $extractor->extractPlainText($pdf);
        $styledPages = $extractor->extractStyledTextPages($pdf);
        $styledLines = array_map(
            static fn (array $block): string => implode('', array_column($block['lines'][0]['spans'], 'text')),
            $styledPages[0]['blocks'] ?? []
        );

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same($expected, $styledLines);
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(false, str_contains($plainText, 'Private nested font leak'));
        $t->same(false, str_contains($plainText, 'Private nested XObject leak'));
    },
    'uses top-level page Parent before nested decoy Parent keys for inherited resources' => static function (TestRunner $t) use ($pageResourceTopLevelParentCurrentBasePdf): void {
        $pdf = $pageResourceTopLevelParentCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $expected = [
            'Top level parent font text',
            'Top level parent form text',
        ];
        $plainText = $extractor->extractPlainText($pdf);
        $styledPages = $extractor->extractStyledTextPages($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resourceMetadata = $boundary[0]['resources'] ?? [];
        $styledLines = array_map(
            static fn (array $block): string => implode('', array_column($block['lines'][0]['spans'], 'text')),
            $styledPages[0]['blocks'] ?? []
        );

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same($expected, $styledLines);
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(2, $resourceMetadata['resource_owner_object'] ?? null);
        $t->same(10, $resourceMetadata['resource_object'] ?? null);
        $t->same(true, $resourceMetadata['inherited'] ?? null);
        $t->same(['Font', 'XObject'], $resourceMetadata['categories'] ?? null);
        $t->same(['F1'], $resourceMetadata['font_names'] ?? null);
        $t->same(['InheritedForm'], $resourceMetadata['xobject_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Nested decoy parent font leak'));
        $t->same(false, str_contains($plainText, 'Nested decoy parent form leak'));
    },
    'inherits ancestor resources through indirect null page Resources but keeps indirect empty dictionaries explicit' => static function (TestRunner $t) use ($pageResourceIndirectNullCurrentBasePdf): void {
        $pdf = $pageResourceIndirectNullCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);

        $t->same([
            'Indirect null inherited font text',
            'Indirect null inherited form text',
        ], $extractor->extractTextLines($pdf));
        $t->same("Indirect null inherited font text\nIndirect null inherited form text", $plainText);
        $t->same(1, substr_count($plainText, 'Indirect null inherited form text'));
        $t->same(true, $boundary[0]['resources']['inherited'] ?? null);
        $t->same(2, $boundary[0]['resources']['resource_owner_object'] ?? null);
        $t->same(10, $boundary[0]['resources']['resource_object'] ?? null);
        $t->same(['Font', 'XObject'], $boundary[0]['resources']['categories'] ?? null);
        $t->same(['F1'], $boundary[0]['resources']['font_names'] ?? null);
        $t->same(['InheritedNullForm'], $boundary[0]['resources']['xobject_names'] ?? null);
        $t->same(false, $boundary[1]['resources']['inherited'] ?? null);
        $t->same(4, $boundary[1]['resources']['resource_owner_object'] ?? null);
        $t->same(13, $boundary[1]['resources']['resource_object'] ?? null);
        $t->same([], $boundary[1]['resources']['categories'] ?? null);
    },
    'fails closed on generation-mismatched page Resources references before stale resource reuse or parent inheritance' => static function (TestRunner $t) use ($pageResourceGenerationBoundaryCurrentBasePdf): void {
        $pdf = $pageResourceGenerationBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resourceMetadata = $boundary[0]['resources'] ?? [];

        $t->same(['A'], $extractor->extractTextLines($pdf));
        $t->same(['A'], $extractor->extractTextRuns($pdf));
        $t->same('A', $plainText);
        $t->same("A\n", $extractor->naiveGetText($pdf));
        $t->same('unresolved_or_malformed', $resourceMetadata['status'] ?? null);
        $t->same(false, $resourceMetadata['resolved'] ?? null);
        $t->same(3, $resourceMetadata['resource_owner_object'] ?? null);
        $t->same(12, $resourceMetadata['resource_object'] ?? null);
        $t->same(1, $resourceMetadata['resource_generation'] ?? null);
        $t->same(false, $resourceMetadata['inherited'] ?? null);
        $t->same([], $resourceMetadata['categories'] ?? null);
        $t->same(false, str_contains($plainText, 'Stale generation font leak'));
        $t->same(false, str_contains($plainText, 'Stale generation form leak'));
        $t->same(false, str_contains($plainText, 'Parent generation font leak'));
        $t->same(false, str_contains($plainText, 'Parent generation form leak'));
    },
    'inherits resources through escaped page tree Type names before WordPress text extraction' => static function (TestRunner $t) use ($pageResourceEscapedTypeLineageCurrentBasePdf): void {
        $pdf = $pageResourceEscapedTypeLineageCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $expected = [
            'Escaped type inherited font text',
            'Escaped type inherited form text',
        ];
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resourceMetadata = $boundary[0]['resources'] ?? [];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(true, $resourceMetadata['inherited'] ?? null);
        $t->same(2, $resourceMetadata['resource_owner_object'] ?? null);
        $t->same(10, $resourceMetadata['resource_object'] ?? null);
        $t->same(['Font', 'XObject'], $resourceMetadata['categories'] ?? null);
        $t->same(['F1'], $resourceMetadata['font_names'] ?? null);
        $t->same(['EscapedForm'], $resourceMetadata['xobject_names'] ?? null);
    },
    'fails closed on generation-mismatched page Parent references before stale resource inheritance' => static function (TestRunner $t) use ($pageResourceParentGenerationCurrentBasePdf): void {
        $pdf = $pageResourceParentGenerationCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);

        $t->same(['A'], $extractor->extractTextLines($pdf));
        $t->same(['A'], $extractor->extractTextRuns($pdf));
        $t->same('A', $plainText);
        $t->same("A\n", $extractor->naiveGetText($pdf));
        $t->same([], $boundary);
        $t->same(false, str_contains($plainText, 'Stale parent generation font leak'));
        $t->same(false, str_contains($plainText, 'Stale parent generation form leak'));
    },
    'skips generation-mismatched page tree Kids before inherited resource lookup' => static function (TestRunner $t) use ($pageResourceKidGenerationCurrentBasePdf): void {
        $pdf = $pageResourceKidGenerationCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resourceMetadata = $boundary[0]['resources'] ?? [];

        $t->same(['Current kid generation inherited text'], $extractor->extractTextLines($pdf));
        $t->same(['Current kid generation inherited text'], $extractor->extractTextRuns($pdf));
        $t->same('Current kid generation inherited text', $plainText);
        $t->same("Current kid generation inherited text\n", $extractor->naiveGetText($pdf));
        $t->same(1, count($boundary));
        $t->same(true, $resourceMetadata['inherited'] ?? null);
        $t->same(2, $resourceMetadata['resource_owner_object'] ?? null);
        $t->same(10, $resourceMetadata['resource_object'] ?? null);
        $t->same(['Font'], $resourceMetadata['categories'] ?? null);
        $t->same(['F1'], $resourceMetadata['font_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Stale kid generation resource leak'));
    },
    'blocks fallback page scanning when page tree Kids all reference missing generations' => static function (TestRunner $t) use ($pageResourceKidGenerationAllStaleCurrentBasePdf): void {
        $pdf = $pageResourceKidGenerationAllStaleCurrentBasePdf();
        $extractor = new PdfTextExtractor();

        $t->same([], $extractor->extractTextLines($pdf));
        $t->same([], $extractor->extractTextRuns($pdf));
        $t->same('', $extractor->extractPlainText($pdf));
        $t->same('', $extractor->naiveGetText($pdf));
        $t->same([], (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf));
    },
    'inherits resources from the catalog Kids path when a reachable page omits Parent' => static function (TestRunner $t) use ($pageResourceKidsPathNoParentCurrentBasePdf): void {
        $pdf = $pageResourceKidsPathNoParentCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $expected = [
            'Catalog path inherited font text',
            'Catalog path inherited form text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, count($boundary));
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(10, $resources['resource_owner_object'] ?? null);
        $t->same(20, $resources['resource_object'] ?? null);
        $t->same(['Font', 'XObject'], $resources['categories'] ?? null);
        $t->same(['F1'], $resources['font_names'] ?? null);
        $t->same(['BranchForm'], $resources['xobject_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Root resource font leak'));
        $t->same(false, str_contains($plainText, 'Root resource form leak'));
        $t->same(false, str_contains($plainText, 'RootForm'));
    },
    'blocks page-resource review when trailer Root generation does not resolve to the current catalog' => static function (TestRunner $t) use ($pageResourceTrailerRootGenerationMismatchCurrentBasePdf): void {
        $pdf = $pageResourceTrailerRootGenerationMismatchCurrentBasePdf();
        $extractor = new PdfTextExtractor();

        $t->same([], $extractor->extractTextLines($pdf));
        $t->same([], $extractor->extractTextRuns($pdf));
        $t->same('', $extractor->extractPlainText($pdf));
        $t->same('', $extractor->naiveGetText($pdf));
        $t->same(0, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same([], $extractor->extractPageLabels($pdf));
        $t->same([], (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf));
        $t->same([], (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf));
    },
    'inherits invoking page resources for direct and indirect null Form XObject Resources while empty dictionaries stay explicit' => static function (TestRunner $t) use ($pageResourceFormNullCurrentBasePdf): void {
        $pdf = $pageResourceFormNullCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Null form inherited nested text',
            'Null form inherited nested text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(2, substr_count($plainText, 'Null form inherited nested text'));
        $t->same(false, str_contains($plainText, 'InheritedNestedForm'));
        $t->same(false, str_contains($plainText, 'ExplicitEmptyForm'));
    },
    'resolves form-local marked-content Properties without leaking page property names' => static function (TestRunner $t) use ($pageResourceFormPropertiesCurrentBasePdf): void {
        $pdf = $pageResourceFormPropertiesCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $expected = [
            'Page resource ActualText',
            'Form local ActualText',
            'Form local Alt text',
            'After form glyph',
        ];
        $plainText = $extractor->extractPlainText($pdf);
        $styledPages = $extractor->extractStyledTextPages($pdf);
        $styledLines = array_map(
            static fn (array $block): string => implode('', array_column($block['lines'][0]['spans'], 'text')),
            $styledPages[0]['blocks'] ?? []
        );

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same($expected, $styledLines);
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, substr_count($plainText, 'Page resource ActualText'));
        $t->same(1, substr_count($plainText, 'Form local ActualText'));
        $t->same(false, str_contains($plainText, 'Page glyph noise'));
        $t->same(false, str_contains($plainText, 'Form glyph noise'));
        $t->same(false, str_contains($plainText, 'Alt glyph noise'));
    },
    'excludes malformed direct inherited resource entries from page review metadata while preserving valid resources' => static function (TestRunner $t) use ($pageResourceDirectEntryBoundaryCurrentBasePdf): void {
        $pdf = $pageResourceDirectEntryBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resourceMetadata = $boundary[0]['resources'] ?? [];
        $expected = [
            'Valid inherited direct-entry font text',
            'Valid inherited direct-entry actual text',
            'Valid inherited direct-entry form text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(true, $resourceMetadata['inherited'] ?? null);
        $t->same(2, $resourceMetadata['resource_owner_object'] ?? null);
        $t->same(10, $resourceMetadata['resource_object'] ?? null);
        $t->same(['Font', 'XObject', 'Properties', 'ColorSpace'], $resourceMetadata['categories'] ?? null);
        $t->same(['Fvalid'], $resourceMetadata['font_names'] ?? null);
        $t->same(['ValidForm'], $resourceMetadata['xobject_names'] ?? null);
        $t->same(['GoodActual'], $resourceMetadata['properties_names'] ?? null);
        $t->same(['CS1', 'CS2'], $resourceMetadata['color_space_names'] ?? null);
        $t->same(false, in_array('BadArray', $resourceMetadata['font_names'] ?? [], true));
        $t->same(false, in_array('BadName', $resourceMetadata['xobject_names'] ?? [], true));
        $t->same(false, in_array('BadString', $resourceMetadata['font_names'] ?? [], true));
        $t->same(false, str_contains($plainText, 'Font decoy review leak'));
        $t->same(false, str_contains($plainText, 'BadArray'));
        $t->same(false, str_contains($plainText, 'BadName'));
    },
    'resolves indirect resource dictionary and category wrappers before inherited page text extraction' => static function (TestRunner $t) use ($pageResourceIndirectWrapperCurrentBasePdf): void {
        $pdf = $pageResourceIndirectWrapperCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resourceMetadata = $boundary[0]['resources'] ?? [];
        $expected = [
            'Wrapped inherited font text',
            'Wrapped inherited actual text',
            'Wrapped inherited form text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(true, $resourceMetadata['inherited'] ?? null);
        $t->same(2, $resourceMetadata['resource_owner_object'] ?? null);
        $t->same(10, $resourceMetadata['resource_object'] ?? null);
        $t->same(0, $resourceMetadata['resource_generation'] ?? null);
        $t->same(['Font', 'XObject', 'Properties'], $resourceMetadata['categories'] ?? null);
        $t->same(['Fwrapped'], $resourceMetadata['font_names'] ?? null);
        $t->same(['WrappedForm'], $resourceMetadata['xobject_names'] ?? null);
        $t->same(['WrappedActual'], $resourceMetadata['properties_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Fwrapped'));
        $t->same(false, str_contains($plainText, 'WrappedForm'));
    },
];
