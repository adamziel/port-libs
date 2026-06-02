<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'uses top-level current catalog language viewer preferences and trailer ID metadata' => static function (TestRunner $t): void {
        $currentContent = 'BT /F1 12 Tf 72 720 Td (Current ID Lang Viewer Body) Tj ET';
        $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale ID Lang Viewer Body) Tj ET';
        $currentPermanentId = 'Current Permanent';
        $currentChangingId = 'Current Changing';
        $stalePermanentId = 'Stale Permanent';

        $pdf = "%PDF-1.7\n";
        $offsets = [];
        $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
            $offsets[$objectNumber] = strlen($pdf);
            $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
        };

        $addObject(
            1,
            0,
            '<< /Type /Catalog'
            . ' /PieceInfo << /WPImport << /Lang (stale-nested-lang)'
            . ' /ViewerPreferences << /Direction /L2R /NumCopies 99 >> >> >>'
            . ' /Pages 2 0 R /Lang (en-US) /PageLayout /TwoPageRight /PageMode /UseOutlines'
            . ' /ViewerPreferences 7 0 R >>'
        );
        $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
        $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
        $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
        $addObject(
            7,
            0,
            '<< /Review << /Direction /L2R /NumCopies 99 >>'
            . ' /DisplayDocTitle true /Direction /R2L /PrintScaling /None'
            . ' /PrintPageRange [1 2] /NumCopies 2 /Enforce [ /PrintScaling /Direction /Bogus ] >>'
        );

        $pdf .= "trailer\n<< /Root 1 0 R /ID [(Stale Permanent) (Stale Changing)] >>\n";
        $xrefOffset = strlen($pdf);
        $rows = '';
        for ($objectNumber = 0; $objectNumber < 11; $objectNumber++) {
            $rows .= pack(
                'CNn',
                isset($offsets[$objectNumber]) || $objectNumber === 10 ? 1 : 0,
                $objectNumber === 10 ? $xrefOffset : ($offsets[$objectNumber] ?? 0),
                $objectNumber === 0 ? 65535 : 0
            );
        }

        $compressedXref = gzcompress($rows);
        if (!is_string($compressedXref)) {
            throw new RuntimeException('Unable to compress trailer ID language viewer-preference xref stream.');
        }

        $pdf .= "10 0 obj\n"
            . '<< /Type /XRef /Size 11 /Root 1 0 R /ID [(Current\040Permanent) <'
            . strtoupper(bin2hex($currentChangingId))
            . '>] /W [1 4 2] /Filter /FlateDecode /Length '
            . strlen($compressedXref)
            . " >>\nstream\n{$compressedXref}\nendstream\nendobj\n"
            . "startxref\n{$xrefOffset}\n%%EOF\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Lang (de-DE)"
            . " /ViewerPreferences << /DisplayDocTitle false /Direction /L2R >> >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n";

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog', 'trailer_id'], $metadata['source']);
        $t->same('en-US', $metadata['language']);
        $t->same('en-US', $metadata['catalog']['language']);
        $t->same('TwoPageRight', $metadata['page_layout']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same([
            'display_doc_title' => true,
            'direction' => 'R2L',
            'print_scaling' => 'None',
            'print_page_range' => [1, 2],
            'enforce' => ['PrintScaling', 'Direction'],
            'num_copies' => 2,
        ], $metadata['viewer_preferences']);
        $t->same($metadata['viewer_preferences'], $metadata['catalog']['viewer_preferences']);
        $t->same('trailer_id', $metadata['trailer_ids']['source']);
        $t->same(2, $metadata['trailer_ids']['id_count']);
        $t->same(bin2hex($currentPermanentId), $metadata['trailer_ids']['permanent']['hex']);
        $t->same(bin2hex($currentChangingId), $metadata['trailer_ids']['changing']['hex']);
        $t->same(hash('sha256', $currentPermanentId), $metadata['document_fingerprint']);
        $t->same('trailer_id_permanent', $metadata['document_fingerprint_source']);
        $t->same('Current ID Lang Viewer Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-nested-lang'));
        $t->true(is_string($encoded) && !str_contains($encoded, $stalePermanentId));
        $t->true(is_string($encoded) && !str_contains($encoded, 'de-DE'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale ID Lang Viewer Body'));
        $t->true(!str_contains($plainText, 'Stale ID Lang Viewer Body'));
        $t->true(!str_contains($plainText, 'stale-nested-lang'));
    },
];
