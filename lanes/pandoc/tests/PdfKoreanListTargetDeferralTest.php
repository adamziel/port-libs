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
        $plain = PandocConverter::write($document, 'plain');

        $t->same(true, $meta['pdfSourceBindingComplete'] ?? null);
        $t->same(null, $meta['pdfSourceBindingFailureReason'] ?? null);
        $t->same(67, $disposition['sourceOccurrenceCount'] ?? null);
        $t->same(67, $disposition['resolvedOccurrenceCount'] ?? null);
        $t->same(0, $disposition['unresolvedOccurrenceCount'] ?? null);
        $t->same(55, $disposition['dispositionCounts']['emitted'] ?? null);
        $t->same(12, $disposition['dispositionCounts']['semantic-structure'] ?? null);
        $t->same(2557, $disposition['sourceSignificantCharacterBytes'] ?? null);
        $t->same(
            $disposition['sourceSignificantCharacterBytes'] ?? null,
            $disposition['emittedSignificantCharacterBytes'] ?? null
        );

        $listItemCount = 0;
        $visit = static function (AstNode $node) use (&$visit, &$listItemCount): void {
            if ($node->type === 'list_item') {
                $listItemCount++;
            }
            foreach ($node->children() as $child) {
                $visit($child);
            }
        };
        foreach ($document->children() as $block) {
            $visit($block);
        }
        $t->same(12, $listItemCount);

        // This star has no structural target and must remain literal. The two
        // compact joins independently revalidate through their exact source
        // text edges after list-marker deferral.
        $t->contains('* 삼성카드의 포인트', $plain);
        $t->contains('주유모든 주유소', $plain);
        $t->contains('LPG충전소 포함', $plain);
    },
];
