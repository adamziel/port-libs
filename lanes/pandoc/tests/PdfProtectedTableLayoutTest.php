<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PdfReader;
use PortLibs\MarkerPDF\NativePdfFactsProvider;

/**
 * Build a small, structurally valid PDF without relying on a licensed corpus
 * artifact. Page rotation belongs to the page dictionary so the rotation
 * control exercises the same inherited page geometry as ordinary PDFs.
 *
 * @param list<array{content: string, rotate?: int}> $pages
 */
$protectedTablePdf = static function (array $pages): string {
    $pageObjects = [];
    $contentObjects = [];
    $kids = [];
    $nextObject = 4;
    foreach ($pages as $page) {
        $pageObject = $nextObject++;
        $contentObject = $nextObject++;
        $kids[] = "{$pageObject} 0 R";
        $rotation = (int) ($page['rotate'] ?? 0);
        $rotateEntry = $rotation === 0 ? '' : " /Rotate {$rotation}";
        $pageObjects[] = "{$pageObject} 0 obj\n"
            . "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792]{$rotateEntry} "
            . "/Resources << /Font << /F1 3 0 R >> >> /Contents {$contentObject} 0 R >>\n"
            . "endobj\n";
        $content = $page['content'];
        $contentObjects[] = "{$contentObject} 0 obj\n"
            . '<< /Length ' . strlen($content) . ">>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . '2 0 obj' . "\n<< /Type /Pages /Kids [" . implode(' ', $kids) . '] /Count ' . count($kids) . ">>\nendobj\n"
        . "3 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . implode('', $pageObjects)
        . implode('', $contentObjects)
        . "trailer << /Root 1 0 R >>\n%%EOF";
};

/** @return list<AstNode> */
$protectedTableNodes = static function (AstNode $document): array {
    return array_values(array_filter(
        $document->children(),
        static fn (AstNode $node): bool => $node->type === 'table'
    ));
};

/** @return list<list<string>> */
$protectedTableRows = static function (AstNode $table): array {
    $rows = [];
    foreach ($table->children() as $section) {
        foreach ($section->children() as $row) {
            $rows[] = array_map(
                static fn (AstNode $cell): string => (string) $cell->attr('text', ''),
                $row->children()
            );
        }
    }

    return $rows;
};

$protectedTableReader = static fn (string $pdf): AstNode => (new PdfReader([
    'pdfGeometryTables' => true,
    'pdfRepairProseText' => true,
]))->read($pdf);

$protectedTableFactsReader = static function (string $pdf): AstNode {
    $options = [
        'pdfGeometryTables' => true,
        'pdfRepairProseText' => true,
    ];
    $facts = (new NativePdfFactsProvider())->extract($pdf, $options);

    return (new PdfReader($options + ['pdfDocumentFacts' => $facts]))->read($pdf);
};

$rotatedTableContent = 'BT /F1 12 Tf '
    . '1 0 0 1 72 720 Tm (Product) Tj 1 0 0 1 250 720 Tm (Qty) Tj 1 0 0 1 340 720 Tm (Total) Tj '
    . '1 0 0 1 72 704 Tm (Apples) Tj 1 0 0 1 250 704 Tm (2) Tj 1 0 0 1 340 704 Tm ($4.00) Tj '
    . '1 0 0 1 72 688 Tm (Pears) Tj 1 0 0 1 250 688 Tm (3) Tj 1 0 0 1 340 688 Tm ($6.00) Tj '
    . 'ET';

$inPageRotatedTableContent = 'BT /F1 12 Tf '
    . '1 0 0 1 72 740 Tm (This paragraph appears before the rotated table and remains fully readable.) Tj '
    . '0 1 -1 0 260 180 Tm (Product) Tj 0 1 -1 0 260 300 Tm (Qty) Tj 0 1 -1 0 260 400 Tm (Total) Tj '
    . '0 1 -1 0 276 180 Tm (Apples) Tj 0 1 -1 0 276 300 Tm (2) Tj 0 1 -1 0 276 400 Tm ($4.00) Tj '
    . '0 1 -1 0 292 180 Tm (Pears) Tj 0 1 -1 0 292 300 Tm (3) Tj 0 1 -1 0 292 400 Tm ($6.00) Tj '
    . '1 0 0 1 72 80 Tm (This paragraph appears after the rotated table and also remains readable.) Tj '
    . 'ET';

$twoTablesContent = 'BT /F1 12 Tf '
    . '1 0 0 1 72 736 Tm (Item) Tj 1 0 0 1 230 736 Tm (Qty) Tj 1 0 0 1 330 736 Tm (Price) Tj '
    . '1 0 0 1 72 720 Tm (Paper) Tj 1 0 0 1 230 720 Tm (4) Tj 1 0 0 1 330 720 Tm ($8.00) Tj '
    . '1 0 0 1 72 704 Tm (Ink) Tj 1 0 0 1 230 704 Tm (2) Tj 1 0 0 1 330 704 Tm ($12.00) Tj '
    . '1 0 0 1 72 624 Tm (Region) Tj 1 0 0 1 230 624 Tm (Orders) Tj 1 0 0 1 330 624 Tm (Revenue) Tj '
    . '1 0 0 1 72 608 Tm (North) Tj 1 0 0 1 230 608 Tm (12) Tj 1 0 0 1 330 608 Tm ($120.00) Tj '
    . '1 0 0 1 72 592 Tm (South) Tj 1 0 0 1 230 592 Tm (9) Tj 1 0 0 1 330 592 Tm ($90.00) Tj '
    . 'ET';

$continuedTablePageOne = 'BT /F1 12 Tf '
    . '1 0 0 1 72 720 Tm (Account) Tj 1 0 0 1 250 720 Tm (Units) Tj 1 0 0 1 340 720 Tm (Balance) Tj '
    . '1 0 0 1 72 704 Tm (Alpha) Tj 1 0 0 1 250 704 Tm (10) Tj 1 0 0 1 340 704 Tm ($100.00) Tj '
    . '1 0 0 1 72 688 Tm (Beta) Tj 1 0 0 1 250 688 Tm (20) Tj 1 0 0 1 340 688 Tm ($200.00) Tj '
    . 'ET';
$continuedTablePageTwo = 'BT /F1 12 Tf '
    . '1 0 0 1 72 720 Tm (Account) Tj 1 0 0 1 250 720 Tm (Units) Tj 1 0 0 1 340 720 Tm (Balance) Tj '
    . '1 0 0 1 72 704 Tm (Gamma) Tj 1 0 0 1 250 704 Tm (30) Tj 1 0 0 1 340 704 Tm ($300.00) Tj '
    . '1 0 0 1 72 688 Tm (Delta) Tj 1 0 0 1 250 688 Tm (40) Tj 1 0 0 1 340 688 Tm ($400.00) Tj '
    . 'ET';

/** @param list<array{label:string,header:list<string>,rows:list<list<string>>}> $sections */
$compositeTablePage = static function (array $sections): string {
    $commands = ['BT /F1 12 Tf'];
    $top = 744;
    foreach ($sections as $index => $section) {
        if ($index > 0) {
            $commands[] = sprintf('1 0 0 1 72 %d Tm (%s) Tj', $top + 24, $section['label']);
        }
        foreach ([$section['header'], ...$section['rows']] as $rowIndex => $row) {
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
$compositeTablePageOne = $compositeTablePage([
    ['label' => 'Contact section', 'header' => ['Contact', 'Phone', 'Email'], 'rows' => [['Ada', '100', 'a@example.test'], ['Bo', '200', 'b@example.test']]],
    ['label' => 'Account section', 'header' => ['Account', 'Units', 'Balance'], 'rows' => [['Alpha', '10', '$100.00'], ['Beta', '20', '$200.00']]],
    ['label' => 'Product section', 'header' => ['Product', 'Qty', 'Total'], 'rows' => [['Paper', '4', '$8.00'], ['Ink', '2', '$12.00']]],
    ['label' => 'Message section', 'header' => ['Message', 'Tax', 'Due'], 'rows' => [['Thanks', '$2.00', '$22.00'], ['Regards', '$1.00', '$11.00']]],
]);
$compositeTablePageTwo = $compositeTablePage([
    ['label' => 'Account section', 'header' => ['Account', 'Units', 'Balance'], 'rows' => [['Gamma', '30', '$300.00'], ['Delta', '40', '$400.00']]],
    ['label' => 'Product section', 'header' => ['Product', 'Qty', 'Total'], 'rows' => [['Pens', '3', '$9.00'], ['Tape', '5', '$15.00']]],
    ['label' => 'Notes section', 'header' => ['Notes', 'Paid', 'Status'], 'rows' => [['Ready', '$24.00', 'Open'], ['Sent', '$24.00', 'Closed']]],
]);
$compositeTableBarrierPageTwo = $compositeTablePage([
    ['label' => 'Account section', 'header' => ['Account', 'Units', 'Balance'], 'rows' => [['Gamma', '30', '$300.00'], ['Delta', '40', '$400.00']]],
    [
        'label' => 'This explanatory prose sentence is a semantic barrier between otherwise similar table sections.',
        'header' => ['Product', 'Qty', 'Total'],
        'rows' => [['Pens', '3', '$9.00'], ['Tape', '5', '$15.00']],
    ],
    ['label' => 'Notes section', 'header' => ['Notes', 'Paid', 'Status'], 'rows' => [['Ready', '$24.00', 'Open'], ['Sent', '$24.00', 'Closed']]],
]);

$taggedSpanningTablePdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td '
        . '/Span << /MCID 0 >> BDC (Region) Tj EMC T* '
        . '/Span << /MCID 1 >> BDC (Quarter) Tj EMC T* '
        . '/Span << /MCID 2 >> BDC (Q1) Tj EMC T* '
        . '/Span << /MCID 3 >> BDC (Q2) Tj EMC T* '
        . '/Span << /MCID 4 >> BDC (North) Tj EMC T* '
        . '/Span << /MCID 5 >> BDC (10) Tj EMC T* '
        . '/Span << /MCID 6 >> BDC (12) Tj EMC ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . ">>\nstream\n{$content}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R /K 9 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Nums [0 [15 0 R 16 0 R 17 0 R 18 0 R 19 0 R 20 0 R 21 0 R]] >>\nendobj\n"
        . "9 0 obj\n<< /Type /StructElem /S /Table /K [10 0 R 11 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /StructElem /S /THead /K [12 0 R 13 0 R] >>\nendobj\n"
        . "11 0 obj\n<< /Type /StructElem /S /TBody /K 14 0 R >>\nendobj\n"
        . "12 0 obj\n<< /Type /StructElem /S /TR /K [15 0 R 16 0 R] >>\nendobj\n"
        . "13 0 obj\n<< /Type /StructElem /S /TR /K [17 0 R 18 0 R] >>\nendobj\n"
        . "14 0 obj\n<< /Type /StructElem /S /TR /K [19 0 R 20 0 R 21 0 R] >>\nendobj\n"
        . "15 0 obj\n<< /Type /StructElem /S /TH /A << /O /Table /RowSpan 2 /Scope /Column >> /ActualText (Region) /K << /Type /MCR /MCID 0 >> >>\nendobj\n"
        . "16 0 obj\n<< /Type /StructElem /S /TH /A << /O /Table /ColSpan 2 /Scope /Column >> /ActualText (Quarter) /K << /Type /MCR /MCID 1 >> >>\nendobj\n"
        . "17 0 obj\n<< /Type /StructElem /S /TH /A << /O /Table /Scope /Column >> /ActualText (Q1) /K << /Type /MCR /MCID 2 >> >>\nendobj\n"
        . "18 0 obj\n<< /Type /StructElem /S /TH /A << /O /Table /Scope /Column >> /ActualText (Q2) /K << /Type /MCR /MCID 3 >> >>\nendobj\n"
        . "19 0 obj\n<< /Type /StructElem /S /TH /A << /O /Table /Scope /Row >> /ActualText (North) /K << /Type /MCR /MCID 4 >> >>\nendobj\n"
        . "20 0 obj\n<< /Type /StructElem /S /TD /ActualText (10) /K << /Type /MCR /MCID 5 >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /StructElem /S /TD /ActualText (12) /K << /Type /MCR /MCID 6 >> >>\nendobj\n"
        . "trailer << /Root 1 0 R >>\n%%EOF";
};

return [
    'protects a true table on a page rotated through inherited PDF geometry' => static function (
        TestRunner $t
    ) use ($protectedTablePdf, $protectedTableFactsReader, $protectedTableNodes, $protectedTableRows, $rotatedTableContent): void {
        $document = $protectedTableFactsReader($protectedTablePdf([
            ['content' => $rotatedTableContent, 'rotate' => 90],
        ]));
        $tables = $protectedTableNodes($document);
        $meta = $document->attr('meta', []);

        $t->same(1, count($tables));
        $t->same(1, $meta['pdfDetectedTables'] ?? null);
        $t->same(1, $meta['pdfGeometryTables'] ?? null);
        $t->same(90, $meta['pdfDocumentLayoutProfile']['pageEvidence']['1']['rotation'] ?? null);
        $t->same([
            ['Product', 'Qty', 'Total'],
            ['Apples', '2', '$4.00'],
            ['Pears', '3', '$6.00'],
        ], $protectedTableRows($tables[0]));
    },

    'recognizes a table rotated inside a page without losing surrounding prose' => static function (
        TestRunner $t
    ) use ($protectedTablePdf, $protectedTableFactsReader, $protectedTableNodes, $protectedTableRows, $inPageRotatedTableContent): void {
        $document = $protectedTableFactsReader($protectedTablePdf([
            ['content' => $inPageRotatedTableContent],
        ]));
        $tables = $protectedTableNodes($document);
        $meta = $document->attr('meta', []);

        $t->same(['paragraph', 'table', 'paragraph'], array_map(
            static fn (AstNode $node): string => $node->type,
            $document->children()
        ));
        $t->same('This paragraph appears before the rotated table and remains fully readable.', $document->children[0]->attr('text'));
        $t->same('This paragraph appears after the rotated table and also remains readable.', $document->children[2]->attr('text'));
        $t->same(1, $meta['pdfDetectedTables'] ?? null);
        $t->same(0, $meta['pdfDetectedCodeBlocks'] ?? null);
        $t->same([
            ['Product', 'Qty', 'Total'],
            ['Apples', '2', '$4.00'],
            ['Pears', '3', '$6.00'],
        ], $protectedTableRows($tables[0]));
    },

    'keeps two separated true tables as two ordered table regions on one page' => static function (
        TestRunner $t
    ) use ($protectedTablePdf, $protectedTableReader, $protectedTableNodes, $protectedTableRows, $twoTablesContent): void {
        $document = $protectedTableReader($protectedTablePdf([
            ['content' => $twoTablesContent],
        ]));
        $tables = $protectedTableNodes($document);
        $meta = $document->attr('meta', []);

        $t->same(['table', 'table'], array_map(static fn (AstNode $node): string => $node->type, $document->children()));
        $t->same(2, count($tables));
        $t->same(2, $meta['pdfDetectedTables'] ?? null);
        $t->same(2, $meta['pdfLogicalTableCount'] ?? null);
        $t->same(0, $meta['pdfLogicalTableFamilyCount'] ?? null, 'Two unrelated tables are not a composite family.');
        $t->same(2, $meta['pdfGeometryTables'] ?? null);
        $t->same([
            ['Item', 'Qty', 'Price'],
            ['Paper', '4', '$8.00'],
            ['Ink', '2', '$12.00'],
        ], $protectedTableRows($tables[0]));
        $t->same([
            ['Region', 'Orders', 'Revenue'],
            ['North', '12', '$120.00'],
            ['South', '9', '$90.00'],
        ], $protectedTableRows($tables[1]));
    },

    'links a repeated multi-section table family without merging its physical tables' => static function (
        TestRunner $t
    ) use (
        $protectedTablePdf,
        $protectedTableReader,
        $protectedTableNodes,
        $protectedTableRows,
        $compositeTablePageOne,
        $compositeTablePageTwo
    ): void {
        $document = $protectedTableReader($protectedTablePdf([
            ['content' => $compositeTablePageOne],
            ['content' => $compositeTablePageTwo],
        ]));
        $tables = $protectedTableNodes($document);
        $meta = $document->attr('meta', []);

        $t->same(7, count($tables), 'All seven physical sections must remain independently editable.');
        $t->same(7, $meta['pdfDetectedTables'] ?? null);
        $t->same(1, $meta['pdfLogicalTableCount'] ?? null);
        $t->same(1, $meta['pdfLogicalTableFamilyCount'] ?? null);
        $t->same(2, $meta['pdfLogicalTableInstanceCount'] ?? null);
        $t->same(7, $meta['pdfLogicalTableFamilyPhysicalParts'] ?? null);
        $family = $meta['pdfLogicalTableFamilies'][0] ?? [];
        $t->same([1, 2], $family['pages'] ?? null);
        $t->same([4, 3], array_map(
            static fn (array $instance): int => (int) ($instance['physicalParts'] ?? 0),
            $family['instances'] ?? []
        ));
        $t->same([
            ['Account', 'Units', 'Balance'],
            ['Product', 'Qty', 'Total'],
        ], $family['commonHeaders'] ?? null);
        $t->same([
            ['Contact', 'Phone', 'Email'],
            ['Account', 'Units', 'Balance'],
            ['Product', 'Qty', 'Total'],
            ['Message', 'Tax', 'Due'],
            ['Account', 'Units', 'Balance'],
            ['Product', 'Qty', 'Total'],
            ['Notes', 'Paid', 'Status'],
        ], array_map(
            static fn (AstNode $table): array => $protectedTableRows($table)[0],
            $tables
        ));
        $familyId = $family['id'] ?? null;
        $t->true(is_string($familyId) && preg_match('/^pdf-table-family-[a-f0-9]{20}$/', $familyId) === 1);
        foreach ($tables as $table) {
            $t->same($familyId, $table->attr('pdfLogicalTableFamilyId'));
        }
        $blocks = PandocConverter::write($document, 'blocks');
        $t->same(7, substr_count($blocks, '<!-- wp:table -->'));
        $t->contains('data-pdf-logical-table-family-id="' . $familyId . '"', $blocks);
    },

    'does not link repeated table headers across an intervening prose barrier' => static function (
        TestRunner $t
    ) use (
        $protectedTablePdf,
        $protectedTableReader,
        $protectedTableNodes,
        $compositeTablePageOne,
        $compositeTableBarrierPageTwo
    ): void {
        $document = $protectedTableReader($protectedTablePdf([
            ['content' => $compositeTablePageOne],
            ['content' => $compositeTableBarrierPageTwo],
        ]));
        $tables = $protectedTableNodes($document);
        $meta = $document->attr('meta', []);

        $t->same(7, count($tables));
        $t->same(7, $meta['pdfDetectedTables'] ?? null);
        $t->same(7, $meta['pdfLogicalTableCount'] ?? null);
        $t->same(0, $meta['pdfLogicalTableFamilyCount'] ?? null);
        foreach ($tables as $table) {
            $t->same(null, $table->attr('pdfLogicalTableFamilyId'));
        }
        $t->contains('This explanatory prose sentence is a semantic barrier', PandocConverter::write($document, 'blocks'));
    },

    'does not link composite table families across an eight page semantic window boundary' => static function (
        TestRunner $t
    ) use (
        $protectedTablePdf,
        $protectedTableReader,
        $protectedTableNodes,
        $compositeTablePageOne,
        $compositeTablePageTwo
    ): void {
        $pages = [];
        for ($page = 1; $page <= 7; $page++) {
            $pages[] = ['content' => sprintf(
                'BT /F1 12 Tf 1 0 0 1 72 720 Tm (Ordinary prose on page %d remains outside table families.) Tj ET',
                $page
            )];
        }
        $pages[] = ['content' => $compositeTablePageOne];
        $pages[] = ['content' => $compositeTablePageTwo];
        $document = $protectedTableReader($protectedTablePdf($pages));
        $tables = $protectedTableNodes($document);
        $meta = $document->attr('meta', []);

        $t->same(7, count($tables));
        $t->same(7, $meta['pdfLogicalTableCount'] ?? null);
        $t->same(0, $meta['pdfLogicalTableFamilyCount'] ?? null);
        foreach ($tables as $table) {
            $t->same(null, $table->attr('pdfLogicalTableFamilyId'));
        }
    },

    'preserves repeated headers and ordered cells across a multipage table continuation' => static function (
        TestRunner $t
    ) use (
        $protectedTablePdf,
        $protectedTableReader,
        $protectedTableNodes,
        $protectedTableRows,
        $continuedTablePageOne,
        $continuedTablePageTwo
    ): void {
        $document = $protectedTableReader($protectedTablePdf([
            ['content' => $continuedTablePageOne],
            ['content' => $continuedTablePageTwo],
        ]));
        $tables = $protectedTableNodes($document);
        $meta = $document->attr('meta', []);

        $t->same(['table', 'table'], array_map(static fn (AstNode $node): string => $node->type, $document->children()));
        $t->same(2, count($tables), 'Each physical page should retain one editable table region.');
        $t->same(2, $meta['pdfDetectedTables'] ?? null);
        $t->same(1, $meta['pdfLogicalTableCount'] ?? null);
        $t->same(0, $meta['pdfLogicalTableFamilyCount'] ?? null, 'A repeated-header continuation is not a composite family.');
        $t->same(2, $meta['pdfGeometryTables'] ?? null);
        $t->same(['Account', 'Units', 'Balance'], $protectedTableRows($tables[0])[0]);
        $t->same(['Account', 'Units', 'Balance'], $protectedTableRows($tables[1])[0]);
        $t->same([
            ['Alpha', '10', '$100.00'],
            ['Beta', '20', '$200.00'],
            ['Gamma', '30', '$300.00'],
            ['Delta', '40', '$400.00'],
        ], [
            ...array_slice($protectedTableRows($tables[0]), 1),
            ...array_slice($protectedTableRows($tables[1]), 1),
        ]);
        $logicalId = $tables[0]->attr('pdfLogicalTableId');
        $t->true(is_string($logicalId) && preg_match('/^pdf-table-[a-f0-9]{20}$/', $logicalId) === 1);
        $t->same($logicalId, $tables[1]->attr('pdfLogicalTableId'));
        $t->same('start', $tables[0]->attr('pdfTableContinuation'));
        $t->same('end', $tables[1]->attr('pdfTableContinuation'));
        $t->same(false, $tables[0]->attr('pdfTableRepeatedHeader'));
        $t->same(true, $tables[1]->attr('pdfTableRepeatedHeader'));
        $t->same([1, 2], $meta['pdfTableContinuations'][0]['pages'] ?? null);
        $t->same(['Account', 'Units', 'Balance'], $meta['pdfTableContinuations'][0]['repeatedHeader'] ?? null);
    },

    'preserves tagged PDF row and column spans without moving cells into prose' => static function (
        TestRunner $t
    ) use ($taggedSpanningTablePdf, $protectedTableReader, $protectedTableNodes, $protectedTableRows): void {
        $document = $protectedTableReader($taggedSpanningTablePdf());
        $tables = $protectedTableNodes($document);
        $meta = $document->attr('meta', []);
        $blocks = PandocConverter::write($document, 'blocks');

        $t->same(['table'], array_map(static fn (AstNode $node): string => $node->type, $document->children()));
        $t->same(1, count($tables));
        $t->same('tagged', $meta['pdfTableReconstruction'] ?? null);
        $t->same([
            ['Region', 'Quarter'],
            ['Q1', 'Q2'],
            ['North', '10', '12'],
        ], $protectedTableRows($tables[0]));
        $t->same(2, $tables[0]->children[0]->children[0]->children[0]->attr('rowspan'));
        $t->same(2, $tables[0]->children[0]->children[0]->children[1]->attr('colspan'));
        $t->contains('rowspan="2"', $blocks);
        $t->contains('colspan="2"', $blocks);
        $t->contains('scope="row"', $blocks);
    },
];
