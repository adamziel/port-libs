<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PdfReader;

return [
    'korean table fixture binds identical same-page list targets without consuming a literal star' => static function (
        TestRunner $t
    ): void {
        $path = dirname(__DIR__, 3)
            . '/pandoc-showcase/samples/'
            . 'pdf-layout-unstructured-korean-table-korean-text-with-tables.pdf';
        $document = (new PdfReader([
            'maxTextBytes' => 100000,
            'pdfRepairProseText' => true,
            'pdfGeometryTables' => true,
        ]))->read(file_get_contents($path) ?: '');
        $meta = $document->attr('meta');
        $disposition = is_array($meta['pdfSourceDisposition'] ?? null)
            ? $meta['pdfSourceDisposition']
            : [];
        $diagnostics = is_array($meta['pdfSourceOrderProofDiagnostics'] ?? null)
            ? $meta['pdfSourceOrderProofDiagnostics']
            : [];
        $plain = PandocConverter::write($document, 'plain');
        $blocks = PandocConverter::write($document, 'blocks');

        $t->same(true, $meta['pdfSourceBindingComplete'] ?? null);
        $t->same(null, $meta['pdfSourceBindingFailureReason'] ?? null);
        $t->same(2, $meta['pdfGeometryTables'] ?? null);
        $t->same(2, $meta['pdfLogicalTableCount'] ?? null);
        $t->same('geometry', $meta['pdfTableReconstruction'] ?? null);
        $t->true(in_array(
            'unique-standalone-marker-prefix-restoration',
            array_column($diagnostics, 'recoveryMethod'),
            true
        ));
        $t->same(65, $disposition['sourceOccurrenceCount'] ?? null);
        $t->same(65, $disposition['resolvedOccurrenceCount'] ?? null);
        $t->same(0, $disposition['unresolvedOccurrenceCount'] ?? null);
        $t->same(61, $disposition['dispositionCounts']['boundary-repair'] ?? null);
        $t->same(4, $disposition['dispositionCounts']['semantic-structure'] ?? null);
        $t->same(2563, $disposition['sourceSignificantCharacterBytes'] ?? null);
        $t->same(
            $disposition['sourceSignificantCharacterBytes'] ?? null,
            $disposition['emittedSignificantCharacterBytes'] ?? null
        );

        $nodeCounts = [];
        $visit = static function (AstNode $node) use (&$visit, &$nodeCounts): void {
            $nodeCounts[$node->type] = ($nodeCounts[$node->type] ?? 0) + 1;
            foreach ($node->children() as $child) {
                $visit($child);
            }
        };
        foreach ($document->children() as $block) {
            $visit($block);
        }
        $t->same(2, $nodeCounts['table'] ?? 0);
        $t->same(14, $nodeCounts['table_row'] ?? 0);
        $t->same(52, $nodeCounts['table_cell'] ?? 0);
        $t->same(5, $nodeCounts['list_item'] ?? 0);

        // This star has no structural target and must remain literal. The
        // restored table keeps its fuel label and value in separate cells,
        // while the compact LPG source text remains conserved.
        $t->contains('* 삼성카드의 포인트', $plain);
        $t->contains('모든 주유소', $plain);
        $t->contains('LPG충전소 포함', $plain);
        $t->true(
            !str_contains($blocks, '<p>0</p>'),
            'Wholly off-page rotated one-point runs must not become visible paragraphs.'
        );
    },
];
