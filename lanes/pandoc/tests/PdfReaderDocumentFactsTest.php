<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\NativePdfFactsProvider;
use PortLibs\MarkerPDF\PdfDocumentFacts;
use PortLibs\MarkerPDF\PdfDocumentFactsMerger;
use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PdfReader;
use PortLibs\Pandoc\PdfSemanticChunkReconciler;

$readerFactsPdf = static function (): string {
    $first = 'BT /F1 12 Tf 72 720 Td (A complete first-page sentence.) Tj ET';
    $second = 'BT /F1 12 Tf 72 720 Td (A complete second-page sentence.) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 5 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 7 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($first) . " >>\nstream\n{$first}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 7 0 R >> >> /Contents 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($second) . " >>\nstream\n{$second}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

/**
 * Eighteen pages keep 2/8/16-page semantic windows distinct from a complete
 * bounded pass. The only cross-page prose relation starts on page 8 and ends
 * on page 9, exactly across the historical eight-page partition.
 */
$semanticWindowInvariantPdf = static function (): string {
    $pageCount = 18;
    $objects = [
        1 => '',
        2 => '',
        3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    $kids = [];
    $nextObject = 4;
    for ($page = 1; $page <= $pageCount; $page++) {
        $pageObject = $nextObject++;
        $contentObject = $nextObject++;
        $kids[] = $pageObject . ' 0 R';
        $y = 720;
        if ($page === 8) {
            $text = 'A verified cross page semantic relation remains seman-';
            $y = 54;
        } elseif ($page === 9) {
            $text = 'tically connected with stable provenance after the boundary.';
            $y = 738;
        } elseif ($page === 4 || $page === 12) {
            $text = 'Repeated source spelling must retain both stable occurrences.';
            $y = $page === 4 ? 680 : 640;
        } else {
            $text = sprintf('A complete unique sentence appears on source page %02d.', $page);
        }
        $content = sprintf('BT /F1 12 Tf 1 0 0 1 72 %d Tm (%s) Tj ET', $y, $text);
        $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] '
            . '/Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentObject . ' 0 R >>';
        $objects[$contentObject] = '<< /Length ' . strlen($content) . ">>\nstream\n{$content}\nendstream";
    }
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . ' >>';
    ksort($objects, SORT_NUMERIC);

    $pdf = "%PDF-1.4\n";
    foreach ($objects as $number => $body) {
        $pdf .= $number . " 0 obj\n{$body}\nendobj\n";
    }

    return $pdf . "%%EOF\n";
};

/** A page-local source-row to column-major reorder with ordinary neighbors. */
$semanticLocalReorderPdf = static function (): string {
    $dialogue = 'BT /F1 11 Tf '
        . '1 0 0 1 72 760 Tm (A full width introduction brackets the locally reordered dialogue.) Tj '
        . '1 0 0 1 72 720 Tm (MARA: Morning begins.) Tj '
        . '1 0 0 1 340 720 Tm (ELI: Bells answer.) Tj '
        . '1 0 0 1 72 704 Tm (ELI : We should leave.) Tj '
        . '1 0 0 1 340 704 Tm (MARA: The path is clear.) Tj '
        . '1 0 0 1 72 688 Tm (MARA: Then let us go.) Tj '
        . '1 0 0 1 340 688 Tm (ELI: I will follow.) Tj '
        . '1 0 0 1 72 672 Tm (ELI: Bring the lantern.) Tj '
        . '1 0 0 1 340 672 Tm (MARA : It is ready.) Tj '
        . '1 0 0 1 72 632 Tm (A full width closing sentence follows both dialogue columns.) Tj ET';
    $contents = [];
    for ($page = 1; $page <= 6; $page++) {
        $contents[] = $page === 3
            ? $dialogue
            : sprintf(
                'BT /F1 12 Tf 1 0 0 1 72 720 Tm (Ordinary semantic neighbor on page %d.) Tj ET',
                $page
            );
    }

    $objects = [
        1 => '',
        2 => '',
        3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    $kids = [];
    $nextObject = 4;
    foreach ($contents as $content) {
        $pageObject = $nextObject++;
        $contentObject = $nextObject++;
        $kids[] = $pageObject . ' 0 R';
        $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] '
            . '/Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentObject . ' 0 R >>';
        $objects[$contentObject] = '<< /Length ' . strlen($content) . ">>\nstream\n{$content}\nendstream";
    }
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count 6 >>';
    ksort($objects, SORT_NUMERIC);

    $pdf = "%PDF-1.4\n";
    foreach ($objects as $number => $body) {
        $pdf .= $number . " 0 obj\n{$body}\nendobj\n";
    }

    return $pdf . "%%EOF\n";
};

/**
 * Two independent composite table families live in different deterministic
 * semantic windows. This exercises metadata union rather than merely block
 * concatenation in PdfSemanticChunkReconciler.
 */
$semanticTableFamiliesPdf = static function (): string {
    $tablePage = static function (array $sections): string {
        $commands = ['BT /F1 12 Tf'];
        $top = 744;
        foreach ($sections as $index => $header) {
            if ($index > 0) {
                $commands[] = sprintf('1 0 0 1 72 %d Tm (Section %d) Tj', $top + 24, $index + 1);
            }
            foreach ([$header, ['First', '10', '$10.00'], ['Second', '20', '$20.00']] as $rowIndex => $row) {
                $y = $top - ($rowIndex * 16);
                foreach ([72, 250, 390] as $column => $x) {
                    $commands[] = sprintf('1 0 0 1 %d %d Tm (%s) Tj', $x, $y, $row[$column]);
                }
            }
            $top -= 112;
        }
        $commands[] = 'ET';

        return implode(' ', $commands);
    };
    $pageContents = [
        $tablePage([
            ['Contact', 'Phone', 'Email'],
            ['Account', 'Units', 'Balance'],
            ['Product', 'Qty', 'Total'],
        ]),
        $tablePage([
            ['Account', 'Units', 'Balance'],
            ['Product', 'Qty', 'Total'],
            ['Notes', 'Paid', 'Status'],
        ]),
    ];
    for ($page = 3; $page <= 8; $page++) {
        $pageContents[] = sprintf(
            'BT /F1 12 Tf 1 0 0 1 72 720 Tm (Ordinary prose on semantic test page %d.) Tj ET',
            $page
        );
    }
    $pageContents[] = $tablePage([
        ['Office', 'Phone', 'Email'],
        ['Region', 'Orders', 'Revenue'],
        ['Period', 'Rate', 'Amount'],
    ]);
    $pageContents[] = $tablePage([
        ['Region', 'Orders', 'Revenue'],
        ['Period', 'Rate', 'Amount'],
        ['Summary', 'Paid', 'Status'],
    ]);

    $pageObjects = [];
    $contentObjects = [];
    $kids = [];
    $nextObject = 4;
    foreach ($pageContents as $content) {
        $pageObject = $nextObject++;
        $contentObject = $nextObject++;
        $kids[] = $pageObject . ' 0 R';
        $pageObjects[] = $pageObject . " 0 obj\n"
            . "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
            . "/Resources << /Font << /F1 3 0 R >> >> /Contents {$contentObject} 0 R >>\nendobj\n";
        $contentObjects[] = $contentObject . " 0 obj\n<< /Length " . strlen($content)
            . ">>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [" . implode(' ', $kids) . '] /Count ' . count($kids) . ">>\nendobj\n"
        . "3 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . implode('', $pageObjects)
        . implode('', $contentObjects)
        . "trailer << /Root 1 0 R >>\n%%EOF";
};

/**
 * A document-independent tagged/PDF-UA fixture. Every physical page owns a
 * heading, list item, and editable table through explicit MCR /Pg evidence.
 */
$taggedFactsPdf = static function (int $pageCount = 10): string {
    $pageCount = max(1, $pageCount);
    $pageObjects = [];
    $rootKids = [];
    $objects = [
        1 => '',
        2 => '',
        3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        4 => '',
    ];
    for ($page = 1; $page <= $pageCount; $page++) {
        $base = 100 + (($page - 1) * 20);
        $pageObject = $base;
        $contentObject = $base + 1;
        $headingObject = $base + 2;
        $listObject = $base + 3;
        $tableObject = $base + 4;
        $firstRowObject = $base + 5;
        $secondRowObject = $base + 6;
        $cellObjects = [$base + 7, $base + 8, $base + 9, $base + 10];
        $heading = sprintf('Tagged heading %02d', $page);
        $item = sprintf('Tagged item %02d', $page);
        $name = sprintf('Entry %02d', $page);
        $count = (string) ($page * 3);
        $content = "BT /F1 12 Tf 14 TL 72 720 Td "
            . "/Span << /MCID 0 >> BDC ({$heading}) Tj EMC T* "
            . "/Span << /MCID 1 >> BDC ({$item}) Tj EMC T* "
            . "/Span << /MCID 2 >> BDC (Name) Tj EMC T* "
            . "/Span << /MCID 3 >> BDC (Count) Tj EMC T* "
            . "/Span << /MCID 4 >> BDC ({$name}) Tj EMC T* "
            . "/Span << /MCID 5 >> BDC ({$count}) Tj EMC ET";

        $pageObjects[] = $pageObject . ' 0 R';
        array_push($rootKids, $headingObject . ' 0 R', $listObject . ' 0 R', $tableObject . ' 0 R');
        $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /StructParents ' . ($page - 1)
            . ' /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents '
            . $contentObject . ' 0 R >>';
        $objects[$contentObject] = '<< /Length ' . strlen($content) . ">>\nstream\n{$content}\nendstream";
        $objects[$headingObject] = "<< /Type /StructElem /S /H1 /ActualText ({$heading}) /K << /Type /MCR /Pg {$pageObject} 0 R /MCID 0 >> >>";
        $objects[$listObject] = "<< /Type /StructElem /S /LI /A << /O /List /ListNumbering /Disc >> /ActualText ({$item}) /K << /Type /MCR /Pg {$pageObject} 0 R /MCID 1 >> >>";
        $objects[$tableObject] = '<< /Type /StructElem /S /Table /K [' . $firstRowObject . ' 0 R ' . $secondRowObject . ' 0 R] >>';
        $objects[$firstRowObject] = '<< /Type /StructElem /S /TR /K [' . $cellObjects[0] . ' 0 R ' . $cellObjects[1] . ' 0 R] >>';
        $objects[$secondRowObject] = '<< /Type /StructElem /S /TR /K [' . $cellObjects[2] . ' 0 R ' . $cellObjects[3] . ' 0 R] >>';
        $cellTexts = ['Name', 'Count', $name, $count];
        foreach ($cellObjects as $index => $cellObject) {
            $role = $index < 2 ? 'TH' : 'TD';
            $mcid = $index + 2;
            $objects[$cellObject] = "<< /Type /StructElem /S /{$role} /ActualText ({$cellTexts[$index]}) /K << /Type /MCR /Pg {$pageObject} 0 R /MCID {$mcid} >> >>";
        }
    }
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 4 0 R >>';
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageObjects) . '] /Count ' . $pageCount . ' >>';
    $objects[4] = '<< /Type /StructTreeRoot /K [' . implode(' ', $rootKids) . '] >>';
    ksort($objects, SORT_NUMERIC);

    $pdf = "%PDF-1.4\n";
    foreach ($objects as $objectNumber => $body) {
        $pdf .= $objectNumber . " 0 obj\n" . $body . "\nendobj\n";
    }

    return $pdf . "%%EOF\n";
};

$taggedSpanningTablePdf = static function (): string {
    $pageOne = 'BT /F1 12 Tf 14 TL 72 720 Td /Span << /MCID 0 >> BDC (Name) Tj EMC T* /Span << /MCID 1 >> BDC (Count) Tj EMC T* /Span << /MCID 2 >> BDC (Alpha) Tj EMC T* /Span << /MCID 3 >> BDC (1) Tj EMC ET';
    $pageTwo = 'BT /F1 12 Tf 14 TL 72 720 Td /Span << /MCID 0 >> BDC (Beta) Tj EMC T* /Span << /MCID 1 >> BDC (2) Tj EMC T* /Span << /MCID 2 >> BDC (Gamma) Tj EMC T* /Span << /MCID 3 >> BDC (3) Tj EMC ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 9 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R 6 0 R] /Count 2 >>',
        3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
        4 => '<< /Length ' . strlen($pageOne) . ">>\nstream\n{$pageOne}\nendstream",
        5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        6 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 7 0 R >>',
        7 => '<< /Length ' . strlen($pageTwo) . ">>\nstream\n{$pageTwo}\nendstream",
        9 => '<< /Type /StructTreeRoot /K 10 0 R >>',
        10 => '<< /Type /StructElem /S /Table /K [11 0 R 12 0 R 13 0 R 14 0 R] >>',
        11 => '<< /Type /StructElem /S /TR /K [20 0 R 21 0 R] >>',
        12 => '<< /Type /StructElem /S /TR /K [22 0 R 23 0 R] >>',
        13 => '<< /Type /StructElem /S /TR /K [24 0 R 25 0 R] >>',
        14 => '<< /Type /StructElem /S /TR /K [26 0 R 27 0 R] >>',
    ];
    foreach ([
        20 => ['TH', 'Name', 3, 0], 21 => ['TH', 'Count', 3, 1],
        22 => ['TD', 'Alpha', 3, 2], 23 => ['TD', '1', 3, 3],
        24 => ['TD', 'Beta', 6, 0], 25 => ['TD', '2', 6, 1],
        26 => ['TD', 'Gamma', 6, 2], 27 => ['TD', '3', 6, 3],
    ] as $object => [$role, $text, $pageObject, $mcid]) {
        $objects[$object] = "<< /Type /StructElem /S /{$role} /ActualText ({$text}) /K << /Type /MCR /Pg {$pageObject} 0 R /MCID {$mcid} >> >>";
    }
    ksort($objects, SORT_NUMERIC);
    $pdf = "%PDF-1.4\n";
    foreach ($objects as $number => $body) {
        $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
    }

    return $pdf . "%%EOF\n";
};

$pdfReaderOptions = static fn (): array => [
    'pdfRepairProseText' => true,
    'pdfGeometryTables' => true,
    'pdfCollectImagePlacements' => true,
    'pdfCollectFormXObjectPlacements' => true,
];

/**
 * Slice already extracted immutable page facts without recomputing them.
 * The complete document profile deliberately remains attached to every
 * range, matching the resumable importer's bounded semantic passes.
 */
$pdfFactsRange = static function (PdfDocumentFacts $facts, int $startPage, int $endPage): PdfDocumentFacts {
    $data = $facts->toArray();
    $totalPages = max(0, (int) ($data['inventory']['totalPages'] ?? 0));
    if ($startPage < 1 || $endPage < $startPage || $endPage > $totalPages) {
        throw new RuntimeException('The requested PDF test facts range was outside the document inventory.');
    }
    $data['pages'] = array_values(array_filter(
        is_array($data['pages'] ?? null) ? $data['pages'] : [],
        static fn (mixed $page): bool => is_array($page)
            && (int) ($page['pageNumber'] ?? 0) >= $startPage
            && (int) ($page['pageNumber'] ?? 0) <= $endPage
    ));
    $data['inventory'] = array_replace(
        is_array($data['inventory'] ?? null) ? $data['inventory'] : [],
        [
            'startPage' => $startPage,
            'endPage' => $endPage,
            'pageNumbers' => range($startPage, $endPage),
            'hasMorePages' => $endPage < $totalPages,
            'nextPage' => $endPage < $totalPages ? $endPage + 1 : null,
        ]
    );

    return PdfDocumentFacts::fromArray($data);
};

/** Normalize semantic AST only; per-range reader diagnostics live in document metadata. */
$normalizePdfAstValue = static function (mixed $value) use (&$normalizePdfAstValue): mixed {
    if (!is_array($value)) {
        return $value;
    }
    if (array_is_list($value)) {
        return array_map($normalizePdfAstValue, $value);
    }
    ksort($value, SORT_STRING);
    foreach ($value as $key => $item) {
        $value[$key] = $normalizePdfAstValue($item);
    }

    return $value;
};
$normalizePdfAst = static function (
    AstNode $node,
    int $depth = 0
) use (&$normalizePdfAst, $normalizePdfAstValue): array {
    $children = [];
    foreach ($node->children() as $child) {
        $children[] = $normalizePdfAst($child, $depth + 1);
    }
    $attrs = $node->type === 'document' ? [] : $node->baseAttrs();
    if ($depth > 1) {
        // Inline IDs include a reader-local AST path. Exact descendant line
        // IDs/edges and the stable top-level node IDs remain compared.
        unset($attrs['sourceNodeId']);
    }
    // Table-cell proof coordinates are local to each Reader context. Stable
    // source IDs/edges below remain part of this semantic comparison.
    unset(
        $attrs['sourcePdfExactSourceRanges'],
        $attrs['sourcePdfSourceOrderStart'],
        $attrs['sourcePdfSourceOrderEnd']
    );

    return [
        'type' => $node->type,
        // A bounded range necessarily has different extraction diagnostics.
        // Every semantic/block attribute, including page anchors, remains in
        // the comparison because those are part of the import contract.
        'attrs' => $normalizePdfAstValue($attrs),
        'children' => $children,
    ];
};

$topLevelPdfAstTypes = static function (AstNode $document): array {
    $types = [];
    foreach ($document->children() as $block) {
        $types[$block->type] = ($types[$block->type] ?? 0) + 1;
    }
    ksort($types, SORT_STRING);

    return $types;
};

$tests = [
    'runs one unchanged document semantic pass from merged durable page facts' => static function (
        TestRunner $t
    ) use ($readerFactsPdf): void {
        $pdf = $readerFactsPdf();
        $provider = new NativePdfFactsProvider();
        $facts = (new PdfDocumentFactsMerger())->mergeComplete([
            $provider->extract($pdf, ['startPage' => 1, 'maxPages' => 1]),
            $provider->extract($pdf, ['startPage' => 2, 'maxPages' => 1]),
        ]);
        $options = [
            'pdfRepairProseText' => true,
            'pdfGeometryTables' => true,
            'pdfCollectImagePlacements' => true,
            'pdfCollectFormXObjectPlacements' => true,
        ];
        $baseline = (new PdfReader($options))->read($pdf);
        $fromFacts = (new PdfReader($options + ['pdfDocumentFacts' => $facts->toArray()]))->read($pdf);

        $t->same(
            PandocConverter::write($baseline, 'wordpress'),
            PandocConverter::write($fromFacts, 'wordpress')
        );
        $metadata = $fromFacts->attr('meta', []);
        $t->same(true, $metadata['pdfDocumentComplete'] ?? null);
        $t->same([1, 2], $metadata['pdfProcessedPageNumbers'] ?? null);
        $t->same('native-php-v1', $metadata['pdfFactsProvider'] ?? null);
        $t->same(hash('sha256', $pdf), $metadata['pdfFactsSourceSha256'] ?? null);
        $profile = $facts->structure()['documentProfile'] ?? [];
        $t->same($profile, $metadata['pdfDocumentLayoutProfile'] ?? null);
        $t->same($profile['profileDigest'] ?? null, $metadata['pdfDocumentLayoutProfileDigest'] ?? null);
        $t->same(true, $metadata['pdfDocumentLayoutProfileComplete'] ?? null);
        $t->same(
            $baseline->attr('meta', [])['pdfTextFidelity']['sourceTokenCount'] ?? null,
            $metadata['pdfTextFidelity']['sourceTokenCount'] ?? null
        );
    },
    'rejects durable facts when their source digest does not match' => static function (
        TestRunner $t
    ) use ($readerFactsPdf): void {
        $pdf = $readerFactsPdf();
        $facts = (new NativePdfFactsProvider())->extract($pdf);

        $t->throws(
            RuntimeException::class,
            static fn () => (new PdfReader(['pdfDocumentFacts' => $facts]))->read($pdf . "\n% changed")
        );
    },
    'keeps global output stable for multicolumn theatre and formula corpus facts' => static function (TestRunner $t): void {
        $root = dirname(__DIR__, 3);
        $fixtures = [
            $root . '/pandoc-showcase/samples/pdf-layout-unstructured-multicolumn-multi-column-2p.pdf',
            $root . '/pandoc-showcase/samples/pdf-layout-vdl-theatre-script-ASC_script_format_example.pdf',
            $root . '/pandoc-showcase/samples/pdf-layout-docling-code-formula-code_and_formula.pdf',
        ];
        $readerOptions = [
            'pdfRepairProseText' => true,
            'pdfGeometryTables' => true,
            'pdfCollectImagePlacements' => true,
            'pdfCollectFormXObjectPlacements' => true,
        ];
        foreach ($fixtures as $fixture) {
            $pdf = file_get_contents($fixture);
            $t->true(is_string($pdf) && $pdf !== '', 'Expected PDF corpus fixture ' . basename($fixture));
            $provider = new NativePdfFactsProvider();
            $inventory = $provider->extract($pdf, ['maxPages' => 1])->inventory();
            $ranges = [];
            for ($page = 1; $page <= $inventory['totalPages']; $page += 2) {
                $ranges[] = $provider->extract($pdf, [
                    'startPage' => $page,
                    'maxPages' => min(2, $inventory['totalPages'] - $page + 1),
                    'pdfMaxPositionedTextRuns' => 250_000,
                ]);
            }
            $facts = (new PdfDocumentFactsMerger())->mergeComplete($ranges);
            $baseline = (new PdfReader($readerOptions))->read($pdf);
            $fromFacts = (new PdfReader($readerOptions + ['pdfDocumentFacts' => $facts]))->read($pdf);

            $t->same(
                PandocConverter::write($baseline, 'wordpress'),
                PandocConverter::write($fromFacts, 'wordpress'),
                'Global facts output changed for ' . basename($fixture)
            );
        }
    },
    'rejects gaps, mutable profiles, and contradictory page provenance before semantic reconciliation' => static function (
        TestRunner $t
    ) use ($readerFactsPdf, $pdfReaderOptions, $pdfFactsRange): void {
        $pdf = $readerFactsPdf();
        $facts = (new NativePdfFactsProvider())->extract($pdf, $pdfReaderOptions());
        $pageOne = $pdfFactsRange($facts, 1, 1);
        $pageTwo = $pdfFactsRange($facts, 2, 2);

        $t->throws(
            RuntimeException::class,
            static fn () => (new PdfSemanticChunkReconciler($pdfReaderOptions()))->reconcile(
                $pdf,
                [$pageTwo]
            )
        );

        $mutableProfile = $pageTwo->toArray();
        $mutableProfile['structure']['documentProfile']['cueProfile'][] = [
            'key' => 'changed-after-profile-commit',
            'count' => 1,
        ];
        $t->throws(
            RuntimeException::class,
            static fn () => (new PdfSemanticChunkReconciler($pdfReaderOptions()))->reconcile(
                $pdf,
                [$pageOne, PdfDocumentFacts::fromArray($mutableProfile)]
            )
        );

        $contradictoryPage = $pageOne->toArray();
        $contradictoryPage['pages'][0]['text']['lines'][0]['page'] = 2;
        $t->throws(
            RuntimeException::class,
            static fn () => (new PdfSemanticChunkReconciler($pdfReaderOptions()))->reconcile(
                $pdf,
                [PdfDocumentFacts::fromArray($contradictoryPage), $pageTwo]
            )
        );

        $duplicateSourceId = $pageTwo->toArray();
        $duplicateSourceId['pages'][0]['text']['lines'][0]['id'] =
            $pageOne->toArray()['pages'][0]['text']['lines'][0]['id'];
        $t->throws(
            InvalidArgumentException::class,
            static fn () => (new PdfSemanticChunkReconciler($pdfReaderOptions()))->reconcile(
                $pdf,
                [$pageOne, PdfDocumentFacts::fromArray($duplicateSourceId)]
            )
        );
    },
    'uses only source-page-evidenced tagged semantics in a partial facts range' => static function (
        TestRunner $t
    ) use ($taggedFactsPdf, $pdfFactsRange): void {
        $pdf = $taggedFactsPdf(2);
        $facts = (new NativePdfFactsProvider())->extract($pdf, [
            'pdfRepairProseText' => true,
            'pdfGeometryTables' => true,
        ]);
        $pageOne = (new PdfReader(['pdfDocumentFacts' => $pdfFactsRange($facts, 1, 1)]))->read($pdf);
        $pageTwo = (new PdfReader(['pdfDocumentFacts' => $pdfFactsRange($facts, 2, 2)]))->read($pdf);

        foreach ([1 => $pageOne, 2 => $pageTwo] as $page => $document) {
            $blocks = PandocConverter::write($document, 'wordpress');
            $t->same(1, substr_count($blocks, sprintf('Tagged heading %02d', $page)));
            $t->same(1, substr_count($blocks, sprintf('Tagged item %02d', $page)));
            $t->same(1, substr_count($blocks, sprintf('Entry %02d', $page)));
            $t->contains('<!-- wp:heading {"level":1} -->', $blocks);
            $t->contains('<!-- wp:list -->', $blocks);
            $t->contains('<!-- wp:table -->', $blocks);
            $otherPage = $page === 1 ? 2 : 1;
            $t->true(!str_contains($blocks, sprintf('Tagged heading %02d', $otherPage)));
            $t->true(!str_contains($blocks, sprintf('Entry %02d', $otherPage)));
        }
    },
    'slices a multi-page tagged table only at uniquely page-scoped rows' => static function (
        TestRunner $t
    ) use ($taggedSpanningTablePdf, $pdfFactsRange): void {
        $pdf = $taggedSpanningTablePdf();
        $facts = (new NativePdfFactsProvider())->extract($pdf);
        $table = $facts->structure()['taggedStructureBlocks'][0] ?? [];
        $t->same([1, 2], $table['pageNumbers'] ?? null);
        $t->same('multiple-pages', $table['sourceProvenance']['pageScope'] ?? null);
        $pageOne = (new PdfReader(['pdfDocumentFacts' => $pdfFactsRange($facts, 1, 1)]))->read($pdf);
        $pageTwo = (new PdfReader(['pdfDocumentFacts' => $pdfFactsRange($facts, 2, 2)]))->read($pdf);
        $pageOneBlocks = PandocConverter::write($pageOne, 'wordpress');
        $pageTwoBlocks = PandocConverter::write($pageTwo, 'wordpress');

        $t->contains('Alpha', $pageOneBlocks);
        $t->true(!str_contains($pageOneBlocks, 'Beta'));
        $t->true(!str_contains($pageOneBlocks, 'Gamma'));
        $t->contains('Beta', $pageTwoBlocks);
        $t->contains('Gamma', $pageTwoBlocks);
        $t->true(!str_contains($pageTwoBlocks, 'Alpha'));
        $t->same('table', $pageTwo->children()[0]->type);
        $t->same('table_body', $pageTwo->children()[0]->children()[0]->type, 'A continued TD row must not be promoted to a synthetic header.');
    },
    'keeps ambiguous unscoped partial-range tags on the text fallback' => static function (
        TestRunner $t
    ) use ($readerFactsPdf, $pdfFactsRange): void {
        $pdf = $readerFactsPdf();
        $factsData = (new NativePdfFactsProvider())->extract($pdf)->toArray();
        $unscopedHeading = [
            'objectNumber' => 900,
            'role' => 'H1',
            'resolvedRole' => 'H1',
            'kind' => 'block',
            'language' => null,
            'classes' => [],
            'attributes' => [],
            'text' => 'A complete second-page sentence.',
        ];
        $factsData['structure']['taggedStructureBlocks'] = [$unscopedHeading];
        $factsData['structure']['taggedStructureItems'] = [$unscopedHeading];
        $pageTwoFacts = $pdfFactsRange(PdfDocumentFacts::fromArray($factsData), 2, 2);
        $document = (new PdfReader(['pdfDocumentFacts' => $pageTwoFacts]))->read($pdf);
        $blocks = PandocConverter::write($document, 'wordpress');

        $t->same('paragraph', $document->children()[0]->type);
        $t->same(1, substr_count($blocks, 'A complete second-page sentence.'));
        $t->true(!str_contains($blocks, '<!-- wp:heading'));
        $t->true(!str_contains($blocks, 'A complete first-page sentence.'));
    },
];

$chunkFixtures = [
    'multicolumn paragraph flow' => [
        'path' => 'pdf-layout-unstructured-multicolumn-multi-column-2p.pdf',
        'covers' => 'paragraph continuation, independent columns, dehyphenation, and page anchors',
        'requiresMoreThanEightPages' => false,
    ],
    'wrapped list flow' => [
        'path' => 'pdf-layout-unstructured-lists-list-item-example.pdf',
        'covers' => 'a list continued across a physical-page boundary',
        'requiresMoreThanEightPages' => false,
    ],
    'caption and page furniture flow' => [
        'path' => 'pdf-layout-docling-pictures-captions-picture_classification.pdf',
        'covers' => 'captions, adjacent prose, and recurring physical page numbers',
        'requiresMoreThanEightPages' => false,
    ],
    'protected invoice tables' => [
        'path' => 'pdf-quickbooks-invoice-template-quickbooks-invoice-template.pdf',
        'covers' => 'true table regions and surrounding prose',
        'requiresMoreThanEightPages' => false,
        'expectedPhysicalTables' => 7,
        'expectedLogicalTables' => 1,
        'expectedLogicalTableFamilies' => 1,
    ],
    'long technical article' => [
        'path' => 'pdf-tracemonkey-tracemonkey.pdf',
        'covers' => 'real 1/2/8-page partitions with paragraphs, lists, tables, captions, furniture, columns, headings, and code',
        'requiresMoreThanEightPages' => true,
    ],
];

foreach ($chunkFixtures as $label => $fixture) {
    $tests['keeps normalized PDF AST invariant across 1/2/8-page chunks: ' . $label] = static function (
        TestRunner $t
    ) use (
        $fixture,
        $pdfReaderOptions,
        $pdfFactsRange,
        $normalizePdfAst,
        $topLevelPdfAstTypes
    ): void {
        $path = dirname(__DIR__, 3) . '/pandoc-showcase/samples/' . $fixture['path'];
        $pdf = file_get_contents($path);
        $t->true(is_string($pdf) && $pdf !== '', 'Expected PDF chunk-invariance fixture ' . $fixture['path']);

        $options = $pdfReaderOptions();
        $facts = (new NativePdfFactsProvider())->extract($pdf, $options);
        $totalPages = max(0, (int) ($facts->inventory()['totalPages'] ?? 0));
        $t->true($totalPages > 0, 'Expected a nonempty PDF page inventory for ' . $fixture['path']);
        if ($fixture['requiresMoreThanEightPages']) {
            $t->true($totalPages > 8, 'The long fixture must exercise a real eight-page chunk boundary.');
        }

        $profile = $facts->structure()['documentProfile'] ?? null;
        $t->true(is_array($profile), 'Expected one immutable document layout profile for ' . $fixture['path']);
        $t->same(true, $profile['complete'] ?? null, 'Expected a complete layout profile for ' . $fixture['path']);
        $baseline = (new PdfReader($options + ['pdfDocumentFacts' => $facts]))->read($pdf);
        $baselineNormalized = $normalizePdfAst($baseline);
        $baselineHash = hash('sha256', serialize($baselineNormalized));
        $baselineTypes = $topLevelPdfAstTypes($baseline);
        $baselineMetadata = $baseline->attr('meta', []);
        if (isset($fixture['expectedLogicalTableFamilies'])) {
            $t->same($fixture['expectedPhysicalTables'], $baselineMetadata['pdfDetectedTables'] ?? null);
            $t->same($fixture['expectedLogicalTables'], $baselineMetadata['pdfLogicalTableCount'] ?? null);
            $t->same($fixture['expectedLogicalTableFamilies'], $baselineMetadata['pdfLogicalTableFamilyCount'] ?? null);
        }
        $mismatches = [];
        $pageProvenanceDigests = [];

        foreach ([1, 2, 8] as $chunkSize) {
            $physicalRanges = (static function () use (
                $facts,
                $totalPages,
                $chunkSize,
                $pdfFactsRange,
                $profile,
                $fixture,
                $t
            ): Generator {
                for ($startPage = 1; $startPage <= $totalPages; $startPage += $chunkSize) {
                    $endPage = min($totalPages, $startPage + $chunkSize - 1);
                    $rangeFacts = $pdfFactsRange($facts, $startPage, $endPage);
                    $rangeProfile = $rangeFacts->structure()['documentProfile'] ?? [];
                    $t->same(
                        $profile['profileDigest'] ?? null,
                        $rangeProfile['profileDigest'] ?? null,
                        'An extraction range did not receive the immutable full-document profile for ' . $fixture['path']
                    );
                    yield $rangeFacts;
                    unset($rangeFacts);
                }
            })();
            $reconciler = new PdfSemanticChunkReconciler($options);
            $segmented = $reconciler->reconcile($pdf, $physicalRanges);
            $reconciliation = $reconciler->stats();
            $segmentedMetadata = $segmented->attr('meta', []);
            if (isset($fixture['expectedLogicalTableFamilies'])) {
                $t->same($fixture['expectedPhysicalTables'], $segmentedMetadata['pdfDetectedTables'] ?? null);
                $t->same($fixture['expectedLogicalTables'], $segmentedMetadata['pdfLogicalTableCount'] ?? null);
                $t->same($fixture['expectedLogicalTableFamilies'], $segmentedMetadata['pdfLogicalTableFamilyCount'] ?? null);
                $t->same(
                    $baselineMetadata['pdfLogicalTableFamilies'] ?? null,
                    $segmentedMetadata['pdfLogicalTableFamilies'] ?? null,
                    'Logical table-family metadata changed for extraction chunk size ' . $chunkSize
                );
            }
            $t->same(
                min($chunkSize, $totalPages),
                $reconciliation['maxInputRangePages'] ?? null,
                'The reconciler did not retain the requested physical extraction bound for ' . $fixture['path']
            );
            $t->same(
                min(PdfSemanticChunkReconciler::DEFAULT_SEMANTIC_WINDOW_PAGES, $totalPages),
                $reconciliation['maxBufferedPages'] ?? null,
                'The reconciler exceeded its fixed semantic page bound for ' . $fixture['path']
            );
            $t->same(
                (int) ceil($totalPages / PdfSemanticChunkReconciler::DEFAULT_SEMANTIC_WINDOW_PAGES),
                $reconciliation['semanticPasses'] ?? null,
                'The reconciler did not use the deterministic semantic partition for ' . $fixture['path']
            );
            $t->same(
                $profile['profileDigest'] ?? null,
                $reconciliation['profileDigest'] ?? null,
                'The reconciler did not retain the immutable full-document profile for ' . $fixture['path']
            );
            $t->same(
                $totalPages,
                $reconciliation['processedPages'] ?? null,
                'The reconciler did not account for every source page exactly once for ' . $fixture['path']
            );
            $t->true(
                is_string($reconciliation['pageProvenanceDigest'] ?? null)
                    && preg_match('/^[a-f0-9]{64}$/', $reconciliation['pageProvenanceDigest']) === 1,
                'The reconciler did not commit a stable source/page provenance ledger for ' . $fixture['path']
            );
            $pageProvenanceDigests[] = $reconciliation['pageProvenanceDigest'] ?? null;
            if ($totalPages > PdfSemanticChunkReconciler::DEFAULT_SEMANTIC_WINDOW_PAGES) {
                $t->same(
                    false,
                    $reconciliation['loadedWholeDocument'] ?? null,
                    'A long PDF was loaded as one semantic facts object for ' . $fixture['path']
                );
            }
            $segmentedHash = hash('sha256', serialize($normalizePdfAst($segmented)));
            $segmentedTypes = $topLevelPdfAstTypes($segmented);
            if (!hash_equals($baselineHash, $segmentedHash)) {
                $mismatches[] = [
                    'chunkSize' => $chunkSize,
                    'unsegmentedHash' => $baselineHash,
                    'segmentedHash' => $segmentedHash,
                    'unsegmentedBlocks' => $baselineTypes,
                    'segmentedBlocks' => $segmentedTypes,
                ];
            }
            unset($segmented, $reconciler, $physicalRanges);
            gc_collect_cycles();
        }
        $t->same(
            1,
            count(array_unique($pageProvenanceDigests, SORT_STRING)),
            'The source/page provenance ledger changed with extraction chunk size for ' . $fixture['path']
        );
        $t->same(
            [],
            $mismatches,
            sprintf(
                '%s changed across the 1/2/8-page chunk matrix (%s)',
                $fixture['path'],
                $fixture['covers']
            )
        );
    };
}

$tests['keeps tagged heading list and table AST invariant across 1/2/8-page extraction chunks'] = static function (
    TestRunner $t
) use (
    $taggedFactsPdf,
    $pdfReaderOptions,
    $pdfFactsRange,
    $normalizePdfAst,
    $topLevelPdfAstTypes
): void {
    $pdf = $taggedFactsPdf(10);
    $options = $pdfReaderOptions();
    $facts = (new NativePdfFactsProvider())->extract($pdf, $options);
    $profile = $facts->structure()['documentProfile'] ?? null;
    $t->true(is_array($profile) && ($profile['complete'] ?? false) === true);
    $baseline = (new PdfReader($options + ['pdfDocumentFacts' => $facts]))->read($pdf);
    $baselineNormalized = $normalizePdfAst($baseline);
    $baselineTypes = $topLevelPdfAstTypes($baseline);
    $t->same(10, $baselineTypes['heading'] ?? 0);
    $t->same(10, $baselineTypes['bullet_list'] ?? 0);
    $t->same(10, $baselineTypes['table'] ?? 0);

    $hashes = [];
    foreach ([1, 2, 8] as $chunkSize) {
        $ranges = (static function () use ($facts, $chunkSize, $pdfFactsRange): Generator {
            for ($startPage = 1; $startPage <= 10; $startPage += $chunkSize) {
                yield $pdfFactsRange($facts, $startPage, min(10, $startPage + $chunkSize - 1));
            }
        })();
        $reconciler = new PdfSemanticChunkReconciler($options);
        $segmented = $reconciler->reconcile($pdf, $ranges);
        $segmentedNormalized = $normalizePdfAst($segmented);
        $hashes[] = hash('sha256', serialize($segmentedNormalized));
        $t->same(
            $baselineNormalized,
            $segmentedNormalized,
            'Tagged/PDF-UA semantics changed for extraction chunk size ' . $chunkSize
        );
        $t->same(2, $reconciler->stats()['semanticPasses'] ?? null, 'Ten pages must exercise the eight-page semantic boundary.');
    }
    $t->same(1, count(array_unique($hashes, SORT_STRING)));
};

$tests['uses exact Reader edges for a page-local evidenced reorder across semantic windows'] = static function (
    TestRunner $t
) use (
    $semanticLocalReorderPdf,
    $pdfReaderOptions,
    $pdfFactsRange,
    $normalizePdfAst
): void {
    $pdf = $semanticLocalReorderPdf();
    $options = $pdfReaderOptions();
    $facts = (new NativePdfFactsProvider())->extract($pdf, $options);
    $baseline = (new PdfReader($options + ['pdfDocumentFacts' => $facts]))->read($pdf);
    $baselineMetadata = $baseline->attr('meta', []);
    $t->same(
        'evidenced-layout-reorder',
        $baselineMetadata['pdfSourceDisposition']['orderedSignificantCharacterBasis'] ?? null
    );
    $t->same('mapped-occurrence-exact', $baselineMetadata['pdfSourceDisposition']['orderProofStrength'] ?? null);
    $t->same(true, $baselineMetadata['pdfSemanticTextComplete'] ?? null);
    $baselineNormalized = $normalizePdfAst($baseline);
    $baselineNodeIds = array_map(
        static fn (AstNode $block): mixed => $block->attr('sourceNodeId'),
        $baseline->children()
    );
    $digests = [];

    foreach ([2, 4, 6] as $semanticWindowPages) {
        foreach ([1, 2] as $extractionChunkPages) {
            $ranges = (static function () use (
                $facts,
                $extractionChunkPages,
                $pdfFactsRange
            ): Generator {
                for ($startPage = 1; $startPage <= 6; $startPage += $extractionChunkPages) {
                    yield $pdfFactsRange(
                        $facts,
                        $startPage,
                        min(6, $startPage + $extractionChunkPages - 1)
                    );
                }
            })();
            $reconciler = new PdfSemanticChunkReconciler($options, $semanticWindowPages);
            $document = $reconciler->reconcile($pdf, $ranges);
            $metadata = $document->attr('meta', []);
            $sourceEdges = is_array($metadata['pdfSemanticSourceEdges'] ?? null)
                ? $metadata['pdfSemanticSourceEdges']
                : [];
            $sourceLineUseCounts = [];
            foreach ($sourceEdges as $sourceEdge) {
                foreach (is_array($sourceEdge['sourceLineIds'] ?? null) ? $sourceEdge['sourceLineIds'] : [] as $id) {
                    $sourceLineUseCounts[$id] = ($sourceLineUseCounts[$id] ?? 0) + 1;
                }
            }
            $case = sprintf('semantic=%d extraction=%d', $semanticWindowPages, $extractionChunkPages);

            $t->same($baselineNormalized, $normalizePdfAst($document), 'Reordered AST changed for ' . $case);
            $t->same(
                $baselineNodeIds,
                array_map(
                    static fn (AstNode $block): mixed => $block->attr('sourceNodeId'),
                    $document->children()
                ),
                'Stable reordered top-level node IDs changed for ' . $case
            );
            $t->same(count($document->children()), count($sourceEdges), 'Reordered edges were incomplete for ' . $case);
            $t->same(true, $metadata['pdfSemanticSourceEdgeMappingComplete'] ?? null, 'Mapping was incomplete for ' . $case);
            $t->same(0, $reconciler->stats()['sourceEdgeMappingFallbacks'] ?? null, 'Exact edges were not used for ' . $case);
            $t->true(
                count(array_filter($sourceLineUseCounts, static fn (int $count): bool => $count === 2)) >= 4,
                'Split source rows did not retain their two exact reordered destinations for ' . $case
            );
            $digests[] = $metadata['pdfSemanticSourceEdgeDigest'] ?? null;
        }
    }

    $t->same(1, count(array_unique($digests, SORT_STRING)));
};

$tests['fails closed when a Reader source edge exceeds its immutable line bounds'] = static function (
    TestRunner $t
) use ($readerFactsPdf, $pdfReaderOptions): void {
    $pdf = $readerFactsPdf();
    $options = $pdfReaderOptions();
    $facts = (new NativePdfFactsProvider())->extract($pdf, $options);
    $document = (new PdfReader($options + ['pdfDocumentFacts' => $facts]))->read($pdf);
    $blocks = $document->children();
    $first = $blocks[0];
    $attrs = $first->baseAttrs();
    $sourceLineEdges = $attrs['sourceLineEdges'] ?? [];
    $t->true(is_array($sourceLineEdges) && isset($sourceLineEdges[0]['endByte']));
    $sourceLineEdges[0]['endByte']++;
    $attrs['sourceLineEdges'] = $sourceLineEdges;
    $identity = json_encode(
        ['type' => $first->type, 'sourceLineEdges' => $sourceLineEdges],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    $attrs['sourceNodeId'] = 'pdf-source-node-' . substr(hash(
        'sha256',
        is_string($identity) ? $identity : serialize($sourceLineEdges)
    ), 0, 32);
    $blocks[0] = new AstNode($first->type, $attrs, $first->children());
    $tampered = new AstNode('document', ['meta' => $document->attr('meta', [])], $blocks);
    $mappingMethod = new ReflectionMethod(
        PdfSemanticChunkReconciler::class,
        'mapDocumentBlocksToStableSourceEdges'
    );

    $t->same(
        null,
        $mappingMethod->invoke(
            new PdfSemanticChunkReconciler($options),
            $tampered,
            $facts->pages(),
            $facts->source()['sha256']
        )
    );
};

$tests['keeps semantic windows and stable source edges invariant independently of extraction chunks'] = static function (
    TestRunner $t
) use (
    $semanticWindowInvariantPdf,
    $pdfReaderOptions,
    $pdfFactsRange,
    $normalizePdfAst
): void {
    $pdf = $semanticWindowInvariantPdf();
    $options = $pdfReaderOptions();
    $facts = (new NativePdfFactsProvider())->extract($pdf, $options);
    $totalPages = (int) ($facts->inventory()['totalPages'] ?? 0);
    $t->same(18, $totalPages);

    $baseline = (new PdfReader($options + ['pdfDocumentFacts' => $facts]))->read($pdf);
    $baselineNormalized = $normalizePdfAst($baseline);
    $baselinePlain = preg_replace('/\s+/u', ' ', PandocConverter::write($baseline, 'plain')) ?? '';
    $crossPageText = 'A verified cross page semantic relation remains seman- tically connected with stable provenance after the boundary.';
    $t->same(1, substr_count($baselinePlain, $crossPageText));
    $t->same(17, count($baseline->children()));
    $baselineNodeIds = array_map(
        static fn (AstNode $block): mixed => $block->attr('sourceNodeId'),
        $baseline->children()
    );

    $pageEightLineId = $facts->pages()[7]->text()['lines'][0]['id'] ?? null;
    $pageNineLineId = $facts->pages()[8]->text()['lines'][0]['id'] ?? null;
    $pageFourLineId = $facts->pages()[3]->text()['lines'][0]['id'] ?? null;
    $pageTwelveLineId = $facts->pages()[11]->text()['lines'][0]['id'] ?? null;
    $t->true(is_string($pageEightLineId) && $pageEightLineId !== '');
    $t->true(is_string($pageNineLineId) && $pageNineLineId !== '');
    $t->true(is_string($pageFourLineId) && $pageFourLineId !== '');
    $t->true(is_string($pageTwelveLineId) && $pageTwelveLineId !== '');
    $t->true($pageFourLineId !== $pageTwelveLineId, 'Repeated text needs distinct stable source IDs.');
    $sourceEdgeInventories = [];
    $sourceEdgeDigests = [];

    foreach ([2, 8, 16, 18] as $semanticWindowPages) {
        foreach ([1, 2, 8] as $extractionChunkPages) {
            $ranges = (static function () use (
                $facts,
                $totalPages,
                $extractionChunkPages,
                $pdfFactsRange
            ): Generator {
                for ($startPage = 1; $startPage <= $totalPages; $startPage += $extractionChunkPages) {
                    yield $pdfFactsRange(
                        $facts,
                        $startPage,
                        min($totalPages, $startPage + $extractionChunkPages - 1)
                    );
                }
            })();
            $reconciler = new PdfSemanticChunkReconciler($options, $semanticWindowPages);
            $document = $reconciler->reconcile($pdf, $ranges);
            $stats = $reconciler->stats();
            $metadata = $document->attr('meta', []);
            $sourceEdges = is_array($metadata['pdfSemanticSourceEdges'] ?? null)
                ? $metadata['pdfSemanticSourceEdges']
                : [];
            $crossPageEdges = array_values(array_filter(
                $sourceEdges,
                static fn (mixed $edge): bool => is_array($edge)
                    && ($edge['sourceLineIds'] ?? null) === [$pageEightLineId, $pageNineLineId]
            ));
            $repeatedTextEdges = array_values(array_filter(
                $sourceEdges,
                static fn (mixed $edge): bool => is_array($edge)
                    && in_array($edge['sourceLineIds'] ?? null, [[$pageFourLineId], [$pageTwelveLineId]], true)
            ));
            $case = sprintf(
                'semantic=%d extraction=%d',
                $semanticWindowPages,
                $extractionChunkPages
            );

            $t->same($baselineNormalized, $normalizePdfAst($document), 'Normalized AST changed for ' . $case);
            $t->same(
                $baselineNodeIds,
                array_map(
                    static fn (AstNode $block): mixed => $block->attr('sourceNodeId'),
                    $document->children()
                ),
                'Top-level Reader source-node identity changed for ' . $case
            );
            $plain = preg_replace('/\s+/u', ' ', PandocConverter::write($document, 'plain')) ?? '';
            $t->same(1, substr_count($plain, $crossPageText), 'Cross-page relation changed for ' . $case);
            $t->same(17, count($sourceEdges), 'Source-edge inventory did not cover each emitted block for ' . $case);
            $t->same(
                17,
                count(array_unique(array_column($sourceEdges, 'id'), SORT_STRING)),
                'Stable source-edge IDs were duplicated for ' . $case
            );
            $t->same(1, count($crossPageEdges), 'Page 8/9 source edge was not unique for ' . $case);
            $t->same([8, 9], $crossPageEdges[0]['pages'] ?? null, 'Page 8/9 source identity changed for ' . $case);
            $t->same(2, count($repeatedTextEdges), 'Repeated spelling was deduplicated without stable source identity for ' . $case);
            $t->same(
                2,
                count(array_unique(array_column($repeatedTextEdges, 'id'), SORT_STRING)),
                'Repeated spelling did not retain two source-edge IDs for ' . $case
            );
            $t->same(1, $stats['semanticOverlapPages'] ?? null, 'Overlap was not bounded for ' . $case);
            $t->true(
                (int) ($stats['maxSemanticContextPages'] ?? PHP_INT_MAX) <= min(
                    $totalPages,
                    $semanticWindowPages + 2
                ),
                'Semantic context exceeded one page per edge for ' . $case
            );
            $t->true(
                (int) ($stats['maxResidentPageFacts'] ?? PHP_INT_MAX) <= min(
                    $totalPages,
                    $semanticWindowPages + $extractionChunkPages + 1
                ),
                'Resident page facts exceeded extraction plus semantic carry-over bounds for ' . $case
            );
            $t->same(
                min($extractionChunkPages, $totalPages),
                $stats['maxInputRangePages'] ?? null,
                'Extraction range bound changed for ' . $case
            );
            $t->same(
                (int) ceil($totalPages / $semanticWindowPages),
                $stats['semanticPasses'] ?? null,
                'Owned semantic partition changed for ' . $case
            );
            $t->same(0, $stats['sourceEdgeMappingFallbacks'] ?? null, 'Exact Reader edges were not used for ' . $case);
            $t->same(true, $metadata['pdfSemanticSourceEdgeMappingComplete'] ?? null, 'Edge mapping was incomplete for ' . $case);
            $sourceEdgeDigests[] = $metadata['pdfSemanticSourceEdgeDigest'] ?? null;
            $sourceEdgeInventories[] = array_map(
                static fn (array $edge): array => [
                    'id' => $edge['id'] ?? null,
                    'sourceNodeId' => $edge['sourceNodeId'] ?? null,
                    'sourceLineIds' => $edge['sourceLineIds'] ?? null,
                    'sourceSpans' => $edge['sourceSpans'] ?? null,
                    'pages' => $edge['pages'] ?? null,
                    'blockType' => $edge['blockType'] ?? null,
                ],
                $sourceEdges
            );
        }
    }

    $t->same(
        1,
        count(array_unique(array_map('serialize', $sourceEdgeInventories), SORT_STRING)),
        'Stable source-edge identity changed with semantic or extraction partition size.'
    );
    $t->same(
        1,
        count(array_unique($sourceEdgeDigests, SORT_STRING)),
        'Stable source-edge digest changed with semantic or extraction partition size.'
    );
};

$tests['unions deterministic logical table families from separate semantic windows by public ID'] = static function (
    TestRunner $t
) use ($semanticTableFamiliesPdf, $pdfReaderOptions, $pdfFactsRange, $normalizePdfAst): void {
    $pdf = $semanticTableFamiliesPdf();
    $options = $pdfReaderOptions();
    $facts = (new NativePdfFactsProvider())->extract($pdf, $options);
    $baseline = (new PdfReader($options + ['pdfDocumentFacts' => $facts]))->read($pdf);
    $ranges = (static function () use ($facts, $pdfFactsRange): Generator {
        for ($page = 1; $page <= 10; $page++) {
            yield $pdfFactsRange($facts, $page, $page);
        }
    })();
    $reconciler = new PdfSemanticChunkReconciler($options);
    $segmented = $reconciler->reconcile($pdf, $ranges);
    $baselineMetadata = $baseline->attr('meta', []);
    $segmentedMetadata = $segmented->attr('meta', []);

    $t->same(12, $baselineMetadata['pdfDetectedTables'] ?? null);
    $t->same(2, $baselineMetadata['pdfLogicalTableCount'] ?? null);
    $t->same(2, $baselineMetadata['pdfLogicalTableFamilyCount'] ?? null);
    $t->same(4, $baselineMetadata['pdfLogicalTableInstanceCount'] ?? null);
    $t->same(12, $baselineMetadata['pdfLogicalTableFamilyPhysicalParts'] ?? null);
    $t->same([[1, 2], [9, 10]], array_map(
        static fn (array $family): array => $family['pages'] ?? [],
        $baselineMetadata['pdfLogicalTableFamilies'] ?? []
    ));
    $t->same($baselineMetadata['pdfLogicalTableFamilies'] ?? null, $segmentedMetadata['pdfLogicalTableFamilies'] ?? null);
    $t->same(12, $segmentedMetadata['pdfDetectedTables'] ?? null);
    $t->same(2, $segmentedMetadata['pdfLogicalTableCount'] ?? null);
    $t->same(2, $segmentedMetadata['pdfLogicalTableFamilyCount'] ?? null);
    $t->same(2, $reconciler->stats()['semanticPasses'] ?? null);
    $t->same($normalizePdfAst($baseline), $normalizePdfAst($segmented));
};

return $tests;
