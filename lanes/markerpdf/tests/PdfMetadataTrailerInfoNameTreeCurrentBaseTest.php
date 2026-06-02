<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'preserves current trailer Info review values and catalog name-tree review metadata' => static function (TestRunner $t): void {
        $currentContent = 'BT /F1 12 Tf 72 720 Td (Current Trailer Info NameTree Body) Tj ET';
        $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale Trailer Info NameTree Body) Tj ET';
        $script = "app.alert('metadata review only')";
        $staleScript = "app.alert('stale metadata action')";

        $pdf = "%PDF-2.0\n";
        $offsets = [];
        $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
            $offsets[$objectNumber] = strlen($pdf);
            $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
        };

        $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Names << /JavaScript 20 0 R /URLS 30 0 R /Dests 70 0 R >> >>');
        $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
        $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>');
        $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
        $addObject(20, 0, '<< /Kids [21 0 R 22 0 R] >>');
        $addObject(21, 0, '<< /Limits [(a) (m)] /Names [(import-init) 40 0 R (z-stale-js) 41 0 R] >>');
        $addObject(22, 0, '<< /Limits [(n) (z)] /Names [(review-close) 42 0 R] >>');
        $addObject(30, 0, '<< /Kids [31 0 R] >>');
        $addObject(31, 0, '<< /Limits [(source-url) (source-url)] /Names [(source-url) 50 0 R (zz-stale-url) 51 0 R] >>');
        $addObject(40, 0, "<< /S /JavaScript /JS ({$script}) >>");
        $addObject(41, 0, "<< /S /JavaScript /JS ({$staleScript}) >>");
        $addObject(42, 0, '<< /S /JavaScript /JS <' . strtoupper(bin2hex("\xfe\xff\0a\0p\0p\0.\0a\0l\0e\0r\0t\0(\0'\0r\0e\0v\0i\0e\0w\0-\0c\0l\0o\0s\0e\0'\0)")) . '> >>');
        $addObject(50, 0, '<< /S /URI /URI (https://example.test/current-import-source) >>');
        $addObject(51, 0, '<< /S /URI /URI (https://example.test/stale-import-source) >>');
        $addObject(60, 0, '<< /Title (Current Trailer Info NameTree Title) /Author (Current Trailer Author) /Producer (Current Trailer Producer) /Trapped /False /ImportRevision 46 /ImportFlags [/WordPress /MetadataReview] /CustomReview << /Stage (current-base) /Safe true >> >>');
        $addObject(70, 0, '<< /Names [(Review Start) [3 0 R /FitH 700]] >>');

        $xrefOffset = strlen($pdf);
        $rows = '';
        for ($objectNumber = 0; $objectNumber < 91; $objectNumber++) {
            if ($objectNumber === 0 || (!isset($offsets[$objectNumber]) && $objectNumber !== 90)) {
                $rows .= pack('CNn', 0, 0, $objectNumber === 0 ? 65535 : 0);
                continue;
            }

            $rows .= pack('CNn', 1, $objectNumber === 90 ? $xrefOffset : $offsets[$objectNumber], 0);
        }

        $compressedXref = gzcompress($rows);
        if (!is_string($compressedXref)) {
            throw new RuntimeException('Unable to compress trailer Info name-tree xref stream.');
        }

        $pdf .= "90 0 obj\n"
            . '<< /Type /XRef /Size 91 /Root 1 0 R /Info 60 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
            . "stream\n{$compressedXref}\nendstream\nendobj\n"
            . "startxref\n{$xrefOffset}\n%%EOF\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /JavaScript 80 0 R /URLS 81 0 R >> >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
            . "40 0 obj\n<< /S /JavaScript /JS ({$staleScript}) >>\nendobj\n"
            . "50 0 obj\n<< /S /URI /URI (https://example.test/stale-detached-source) >>\nendobj\n"
            . "60 0 obj\n<< /Title (Stale Trailer Info NameTree Title) /Author (Stale Trailer Author) /Trapped /True /ImportRevision 99 >>\nendobj\n"
            . "80 0 obj\n<< /Names [(stale-appended-js) 40 0 R] >>\nendobj\n"
            . "81 0 obj\n<< /Names [(stale-appended-url) 50 0 R] >>\nendobj\n";

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $trailerInfoReview = $metadata['trailer_info_review'] ?? [];
        $nameTrees = $metadata['document_name_trees'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same('Current Trailer Info NameTree Title', $metadata['title']);
        $t->same('Current Trailer Author', $metadata['info']['Author']);
        $t->same('Current Trailer Info NameTree Body', $plainText);
        $t->same('False', $trailerInfoReview['Trapped'] ?? null);
        $t->same(46, $trailerInfoReview['ImportRevision'] ?? null);
        $t->same(['WordPress', 'MetadataReview'], $trailerInfoReview['ImportFlags'] ?? null);
        $t->same('current-base', $trailerInfoReview['CustomReview']['Stage'] ?? null);
        $t->same(true, $trailerInfoReview['CustomReview']['Safe'] ?? null);
        $t->same(['JavaScript', 'URLS'], $nameTrees['tree_names'] ?? []);
        $t->same(['import-init', 'review-close'], $nameTrees['trees']['JavaScript']['names'] ?? []);
        $t->same(['source-url'], $nameTrees['trees']['URLS']['names'] ?? []);
        $t->same(false, $nameTrees['payload_included'] ?? null);
        $t->same(false, $nameTrees['trees']['JavaScript']['entries'][0]['executes_action'] ?? null);
        $t->same('JavaScript', $nameTrees['trees']['JavaScript']['entries'][0]['action_type'] ?? null);
        $t->same(false, $nameTrees['trees']['JavaScript']['entries'][0]['javascript_payload_included'] ?? null);
        $t->same('URI', $nameTrees['trees']['URLS']['entries'][0]['action_type'] ?? null);
        $t->same(['Review Start'], $metadata['document_destinations']['names'] ?? []);
        $t->true(is_string($encoded) && !str_contains($encoded, $script));
        $t->true(is_string($encoded) && !str_contains($encoded, $staleScript));
        $t->true(is_string($encoded) && !str_contains($encoded, 'https://example.test/current-import-source'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'https://example.test/stale-import-source'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Trailer Info NameTree Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-appended-js'));
        $t->true(!str_contains($plainText, 'Stale Trailer Info NameTree Body'));
        $t->true(!str_contains($plainText, 'metadata review only'));
    },
];
