<?php

declare(strict_types=1);

use PortLibs\Pandoc\PdfReader;

return [
    'CDC later image paint reconciles only after its exact clipped artifact graph binds' => static function (
        TestRunner $t
    ): void {
        $path = dirname(__DIR__, 3)
            . '/pandoc-showcase/samples/'
            . 'pdf-cdc-hand-hygiene-brochure-cdc-handhygiene-brochure.pdf';
        $bytes = file_get_contents($path);
        $t->true(is_string($bytes) && $bytes !== '');
        $document = (new PdfReader([
            'maxTextBytes' => 100000,
            'pdfRepairProseText' => true,
            'pdfGeometryTables' => true,
            'pdfCollectImagePlacements' => true,
        ]))->read(is_string($bytes) ? $bytes : '');
        $meta = $document->attr('meta');
        $visibility = $meta['pdfTextVisibility'] ?? [];
        $reconciliation = $meta['pdfTextVisibilityReconciliation'] ?? [];

        $t->same(false, $visibility['complete'] ?? null, 'Raw extraction must remain conservative.');
        $t->same(false, $meta['pdfTextVisibilityRawComplete'] ?? null);
        $t->same(true, $meta['pdfTextVisibilityComplete'] ?? null);
        $t->same(true, $meta['pdfSemanticTextComplete'] ?? null);
        $t->true(!in_array('text-visibility-unresolved', $meta['pdfLimitReasons'] ?? [], true));
        $t->same(true, $reconciliation['complete'] ?? null);
        $t->same(true, $reconciliation['reconciled'] ?? null);
        $t->same(1, $reconciliation['riskCount'] ?? null);
        $t->same(1, $reconciliation['reconciledRiskCount'] ?? null);
        $t->same(0, $reconciliation['unresolvedRiskCount'] ?? null);

        $risks = $visibility['laterPaintRisks'] ?? [];
        $t->same(1, count($risks));
        $risk = $risks[0];
        $t->same(1, $risk['page'] ?? null);
        $t->same(34, $risk['sourceOccurrenceIndex'] ?? null);
        $t->same(['start' => 0, 'end' => 9], $risk['sourceRange'] ?? null);
        $t->same(362, $risk['textOperation'] ?? null);
        $t->same(2174, $risk['paintOperation'] ?? null);
        $t->same('Do', $risk['paintOperator'] ?? null);
        $t->same('Im26', $risk['paintResource'] ?? null);
        $t->same(232, $risk['paintObject'] ?? null);
        $t->same('Image', $risk['paintSubtype'] ?? null);

        $proofs = $meta['pdfClippedDisplayArtifactMediaAnchorProofs'] ?? [];
        $t->same(0, $meta['pdfClippedDisplayArtifactMediaAnchorProofTruncatedCount'] ?? null);
        $t->same(1, count($proofs));
        $proof = $proofs[0];
        $t->same(2, $proof['version'] ?? null);
        $t->same($risk['id'], $proof['laterPaintRiskId'] ?? null);
        $t->same($risk['riskDigest'], $proof['laterPaintRiskDigest'] ?? null);
        $t->same($risk['paintOperation'], $proof['laterPaintOperation'] ?? null);
        $t->same($risk['paintResource'], $proof['laterPaintResource'] ?? null);
        $t->same($risk['paintObject'], $proof['laterPaintObject'] ?? null);
        $t->same('pdf-image-p1-n28-o232', $proof['laterPaintPlacementId'] ?? null);

        $placements = array_values(array_filter(
            $meta['pdfImagePlacements'] ?? [],
            static fn (array $placement): bool =>
                ($placement['id'] ?? null) === ($proof['laterPaintPlacementId'] ?? null)
        ));
        $t->same(1, count($placements));
        $t->same(true, $placements[0]['visible'] ?? null);
        $t->same(true, $placements[0]['placementEligible'] ?? null);
        $t->same($risk['paintBounds'], $placements[0]['bbox'] ?? null);
    },
];
