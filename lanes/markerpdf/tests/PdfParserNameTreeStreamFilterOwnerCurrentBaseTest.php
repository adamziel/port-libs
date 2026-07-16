<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$parserNameTreeStreamFilterOwnerCurrentBasePdf = static function (): array {
    $payload = "app.alert('current stream review');\n"
        . "endstream\nendobj\n"
        . "99 0 obj\n<< /S /JavaScript /JS (fake name-tree stream owner leak) >>\nendobj\n";
    $compressedPayload = gzcompress($payload, 0);
    if (!is_string($compressedPayload) || !str_contains($compressedPayload, "endstream\nendobj\n99 0 obj")) {
        throw new RuntimeException('Unable to build focused name-tree stream-filter owner fixture.');
    }

    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current name tree stream filter owner page) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale name tree stream filter owner page) Tj ET';

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (?int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
        "%010d %05d %s \n",
        $offset ?? 0,
        $generation,
        $state
    );

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Names << /JavaScript 20 0 R >> >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 5 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(20, 0, '<< /Names [(review-js) 40 0 R] >>');
    $addObject(40, 0, '<< /S /JavaScript /JS 41 0 R >>');
    $addObject(41, 0, '<< /Filter /FlateDecode /Length ' . strlen($compressedPayload) . " >>\nstream\n{$compressedPayload}\nendstream");

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 42\n" . $xrefRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 41; $objectNumber++) {
        $pdf .= isset($offsets[$objectNumber . ':0'])
            ? $xrefRow($offsets[$objectNumber . ':0'])
            : $xrefRow(0, 65535, 'f');
    }
    $pdf .= "trailer\n<< /Size 42 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /JavaScript 70 0 R >> >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Names [(stale-js) 71 0 R] >>\nendobj\n"
        . "40 0 obj\n<< /S /JavaScript /JS (stale detached action) >>\nendobj\n"
        . "41 0 obj\n<< /Length 25 >>\nstream\nstale detached payload\nendstream\nendobj\n"
        . "70 0 obj\n<< /Names [(stale-detached-js) 40 0 R] >>\nendobj\n";

    return [$pdf, $payload];
};

return [
    'keeps filtered name-tree payload streams bound to current xref owners before metadata review' => static function (TestRunner $t) use ($parserNameTreeStreamFilterOwnerCurrentBasePdf): void {
        [$pdf, $payload] = $parserNameTreeStreamFilterOwnerCurrentBasePdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $tree = $metadata['document_name_trees']['trees']['JavaScript'] ?? [];
        $entry = $tree['entries'][0] ?? [];
        $source = $entry['javascript_source'] ?? [];

        $t->same('Current name tree stream filter owner page', $plainText);
        $t->same(['JavaScript'], $metadata['document_name_trees']['tree_names'] ?? []);
        $t->same(['review-js'], $tree['names'] ?? []);
        $t->same('JavaScript', $entry['action_type'] ?? null);
        $t->same(false, $entry['executes_action'] ?? null);
        $t->same(false, $entry['javascript_payload_included'] ?? null);
        $t->same('stream', $source['source_type'] ?? null);
        $t->same(41, $source['object'] ?? null);
        $t->same(strlen($payload), $source['bytes'] ?? null);
        $t->same(hash('sha256', $payload), $source['sha256'] ?? null);
        $t->same(['FlateDecode'], $source['filters'] ?? []);
        $t->same(false, $source['payload_included'] ?? null);
        $t->true(is_string($encoded));
        $t->true(!str_contains((string) $encoded, "app.alert('current stream review')"));
        $t->true(!str_contains((string) $encoded, 'fake name-tree stream owner leak'));
        $t->true(!str_contains((string) $encoded, '99 0 obj'));
        $t->true(!str_contains((string) $encoded, 'stale detached action'));
        $t->true(!str_contains((string) $encoded, 'stale-detached-js'));
        $t->true(!str_contains($plainText, 'Stale name tree stream filter owner page'));
        $t->true(!str_contains($plainText, 'current stream review'));
    },
];
