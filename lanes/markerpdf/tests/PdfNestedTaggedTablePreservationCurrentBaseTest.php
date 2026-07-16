<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TaggedTableStructureExtractor;
use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\TableGeometry;
use PortLibs\Pandoc\WordPressBlockWriter;

$nestedTaggedTablePdf = static function (): string {
    $content = "BT /F1 12 Tf\n"
        . "/OuterHead << /MCID 0 >> BDC 72 720 Td (Scope) Tj EMC\n"
        . "/OuterHead << /MCID 1 >> BDC 108 0 Td (State) Tj EMC\n"
        . "/OuterCell << /MCID 2 >> BDC -108 -30 Td (Posts) Tj EMC\n"
        . "/OuterCell << /MCID 3 >> BDC 108 0 Td (Review packet) Tj EMC\n"
        . "/InnerHead << /MCID 4 >> BDC 16 -22 Td (Inner scope) Tj EMC\n"
        . "/InnerHead << /MCID 5 >> BDC 96 0 Td (Inner state) Tj EMC\n"
        . "/InnerCell << /MCID 6 >> BDC -96 -22 Td (Media) Tj EMC\n"
        . "/InnerCell << /MCID 7 >> BDC 96 0 Td (Ready) Tj EMC\n"
        . "ET";

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 30 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "30 0 obj\n<< /Type /StructTreeRoot /RoleMap << /OuterTable /Table /OuterRow /TR /OuterHead /TH /OuterCell /TD /InnerTable /Table /InnerRow /TR /InnerHead /TH /InnerCell /TD >> /ParentTree 31 0 R /K 40 0 R >>\nendobj\n"
        . "31 0 obj\n<< /Nums [0 [42 0 R 43 0 R 46 0 R 47 0 R 50 0 R 51 0 R 53 0 R 54 0 R]] >>\nendobj\n"
        . "40 0 obj\n<< /Type /StructElem /S /OuterTable /Pg 3 0 R /T (Outer tagged table) /K [41 0 R 45 0 R] >>\nendobj\n"
        . "41 0 obj\n<< /Type /StructElem /S /OuterRow /Pg 3 0 R /K [42 0 R 43 0 R] >>\nendobj\n"
        . "42 0 obj\n<< /Type /StructElem /S /OuterHead /Pg 3 0 R /K 0 >>\nendobj\n"
        . "43 0 obj\n<< /Type /StructElem /S /OuterHead /Pg 3 0 R /K 1 >>\nendobj\n"
        . "45 0 obj\n<< /Type /StructElem /S /OuterRow /Pg 3 0 R /K [46 0 R 47 0 R] >>\nendobj\n"
        . "46 0 obj\n<< /Type /StructElem /S /OuterCell /Pg 3 0 R /K 2 >>\nendobj\n"
        . "47 0 obj\n<< /Type /StructElem /S /OuterCell /Pg 3 0 R /ActualText (CUSTOM_GLYPH_LEAK_SHOULD_NOT_RENDER) /K [3 48 0 R] >>\nendobj\n"
        . "48 0 obj\n<< /Type /StructElem /S /InnerTable /Pg 3 0 R /T (Nested tagged table) /K [49 0 R 52 0 R] >>\nendobj\n"
        . "49 0 obj\n<< /Type /StructElem /S /InnerRow /Pg 3 0 R /K [50 0 R 51 0 R] >>\nendobj\n"
        . "50 0 obj\n<< /Type /StructElem /S /InnerHead /Pg 3 0 R /K 4 >>\nendobj\n"
        . "51 0 obj\n<< /Type /StructElem /S /InnerHead /Pg 3 0 R /K 5 >>\nendobj\n"
        . "52 0 obj\n<< /Type /StructElem /S /InnerRow /Pg 3 0 R /K [53 0 R 54 0 R] >>\nendobj\n"
        . "53 0 obj\n<< /Type /StructElem /S /InnerCell /Pg 3 0 R /K 6 >>\nendobj\n"
        . "54 0 obj\n<< /Type /StructElem /S /InnerCell /Pg 3 0 R /K 7 >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

$nestedTaggedTablePdfTextPage = static function (): array {
    $line = static fn (string $text, array $bbox): array => [
        'bbox' => $bbox,
        'spans' => [[
            'text' => $text,
            'bbox' => $bbox,
            'font' => [
                'name' => 'Helvetica',
                'flags' => 0,
                'weight' => 400,
                'size' => 11,
            ],
        ]],
    ];

    $block = static fn (string $text, array $bbox): array => [
        'bbox' => $bbox,
        'lines' => [$line($text, $bbox)],
    ];

    return [
        'page' => 0,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'rotation' => 0,
        'blocks' => [
            $block('Nested tagged table import', [72.0, 48.0, 340.0, 66.0]),
            $block('Scope', [72.0, 100.0, 140.0, 118.0]),
            $block('State', [180.0, 100.0, 250.0, 118.0]),
            $block('Posts', [72.0, 132.0, 140.0, 150.0]),
            $block('Review packet', [180.0, 132.0, 320.0, 150.0]),
            $block('Inner scope', [196.0, 164.0, 290.0, 182.0]),
            $block('Inner state', [292.0, 164.0, 390.0, 182.0]),
            $block('Media', [196.0, 196.0, 260.0, 214.0]),
            $block('Ready', [292.0, 196.0, 360.0, 214.0]),
            $block('After nested table.', [72.0, 250.0, 250.0, 268.0]),
        ],
    ];
};

$nestedTaggedTablePandocAst = static function (): AstNode {
    $inner = new AstNode('table', [
        'caption' => 'Nested tagged table',
        'alignments' => ['left', 'left'],
    ], [
        new AstNode('table_head', [], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Inner scope', 'header' => true], [new AstNode('text', ['text' => 'Inner scope'])]),
                new AstNode('table_cell', ['text' => 'Inner state', 'header' => true], [new AstNode('text', ['text' => 'Inner state'])]),
            ]),
        ]),
        new AstNode('table_body', [], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Media'], [new AstNode('text', ['text' => 'Media'])]),
                new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
            ]),
        ]),
    ]);

    return new AstNode('table', [
        'caption' => 'Outer tagged table',
        'alignments' => ['left', 'left'],
    ], [
        new AstNode('table_head', [], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Scope', 'header' => true], [new AstNode('text', ['text' => 'Scope'])]),
                new AstNode('table_cell', ['text' => 'State', 'header' => true], [new AstNode('text', ['text' => 'State'])]),
            ]),
        ]),
        new AstNode('table_body', [], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
                new AstNode('table_cell', ['text' => 'Review packet'], [
                    new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Review packet'])]),
                    $inner,
                ]),
            ]),
        ]),
    ]);
};

return [
    'preserves nested tagged PDF tables through WordPress table insertion' => static function (TestRunner $t) use (
        $nestedTaggedTablePdf,
        $nestedTaggedTablePdfTextPage,
        $nestedTaggedTablePandocAst
    ): void {
        $pdf = $nestedTaggedTablePdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $taggedTables = $metadata['structure_tree']['tagged_tables'] ?? [];
        $elements = $metadata['structure_tree']['elements'] ?? [];
        $elementsByObject = [];
        foreach ($elements as $element) {
            if (is_array($element) && isset($element['object']) && is_int($element['object'])) {
                $elementsByObject[$element['object']] = $element;
            }
        }

        $t->same(2, $taggedTables['table_count'] ?? null);
        $t->same(1, $taggedTables['nested_table_count'] ?? null);
        $t->same([40], $taggedTables['top_level_table_objects'] ?? null);
        $t->same([48], $taggedTables['nested_table_objects'] ?? null);

        $outerSummary = $taggedTables['tables'][0] ?? [];
        $nestedSummary = $taggedTables['tables'][1] ?? [];
        $nestedLink = $taggedTables['nested_tables'][0] ?? [];
        $t->same(40, $outerSummary['struct_object'] ?? null);
        $t->same(1, $outerSummary['nested_table_count'] ?? null);
        $t->same(true, $outerSummary['unambiguous'] ?? null);
        $t->same([48], $outerSummary['nested_table_objects'] ?? null);
        $t->same([0, 1, 2, 3, 4, 5, 6, 7], $outerSummary['descendant_mcids'] ?? null);
        $t->same(48, $nestedSummary['struct_object'] ?? null);
        $t->same(47, $nestedSummary['parent_cell_object'] ?? null);
        $t->same(47, $nestedLink['parent_cell_object'] ?? null);
        $t->same(48, $nestedLink['nested_table_object'] ?? null);
        $t->same([4, 5, 6, 7], $nestedLink['nested_table_mcids'] ?? null);

        $parentCell = $elementsByObject[47] ?? [];
        $t->same([48], $parentCell['child_structure_objects'] ?? null);
        $t->same([3], $parentCell['mcids'] ?? null);
        $t->same([3, 4, 5, 6, 7], $parentCell['descendant_mcids'] ?? null);
        $t->same('CUSTOM_GLYPH_LEAK_SHOULD_NOT_RENDER', $parentCell['actual_text'] ?? null);

        $taggedContent = (new PdfTextExtractor())->extractTaggedContent($pdf);
        $t->same(
            ['Scope', 'State', 'Posts', 'Review packet', 'Inner scope', 'Inner state', 'Media', 'Ready'],
            array_column($taggedContent, 'text')
        );

        $pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
        $t->same(1, count($pageReviews));
        $t->same(2, $pageReviews[0]['structure_tagged_table_count'] ?? null);
        $t->same(1, $pageReviews[0]['structure_nested_tagged_table_count'] ?? null);
        $t->same(47, $pageReviews[0]['structure_tagged_tables'][1]['parent_cell_object'] ?? null);

        $extracted = (new TaggedTableStructureExtractor())->extract($pdf);
        $t->same(1, count($extracted['tables']));
        $tableRecord = $extracted['tables'][0];
        $html = $tableRecord['html'];
        $t->contains('data-markerpdf-source="tagged-pdf"', $html);
        $t->contains('data-markerpdf-nested-table="true"', $html);
        $t->contains('<p>Review packet</p><table', $html);
        $t->contains('<th data-markerpdf-struct-object="50">Inner scope</th>', $html);
        $t->contains('<td data-markerpdf-struct-object="54">Ready</td>', $html);
        $t->contains('<!-- wp:table -->', $tableRecord['wordpress_block']);
        $t->same(false, str_contains($html, 'CUSTOM_GLYPH_LEAK_SHOULD_NOT_RENDER'));
        $t->same(
            ['Scope', 'State', 'Posts', 'Review packet', 'Inner scope', 'Inner state', 'Media', 'Ready'],
            $tableRecord['replace_texts']
        );

        $path = sys_get_temp_dir() . '/markerpdf-nested-tagged-table-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% nested tagged table supplied fixture\n%%EOF");
        try {
            $converted = (new SuppliedDocumentConverter())->convert(
                $path,
                [$nestedTaggedTablePdfTextPage()],
                [
                    'metadata' => ['languages' => ['English']],
                    'tagged_tables' => $extracted['tables'],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $t->contains('<table data-markerpdf-source="tagged-pdf"', $converted['text']);
        $t->contains('data-markerpdf-nested-table="true"', $converted['text']);
        $t->contains('<p>Review packet</p><table', $converted['text']);
        $t->contains('After nested table.', $converted['text']);
        $t->same(false, str_contains($converted['text'], 'CUSTOM_GLYPH_LEAK_SHOULD_NOT_RENDER'));
        $t->same(['tagged-table-structure'], $converted['metadata']['supplied_boundaries']);
        $t->same(1, $converted['metadata']['tagged_tables']['inserted_tables'] ?? null);
        $t->same(8, $converted['metadata']['tagged_tables']['tables'][0]['removed_text_count'] ?? null);

        $outerAstTable = $nestedTaggedTablePandocAst();
        $packet = TableGeometry::reviewPacket($outerAstTable, ['idPrefix' => 'Nested Tagged PDF']);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$outerAstTable]));
        $t->same(1, $packet['summary']['nestedTableCount'] ?? null);
        $t->contains('<p>Review packet</p><table', $blocks);
        $t->contains('Inner scope', $blocks);
        $t->contains('Ready', $blocks);
    },
];
