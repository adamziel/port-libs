<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpLangMarkInfoPacket = static function (): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current XMP MarkInfo Catalog Title</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Catalog language and MarkInfo stay review metadata</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>2026-06-02T21:28:15Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

return [
    'reviews current xref XMP language and catalog MarkInfo without visible text leakage' => static function (
        TestRunner $t
    ) use ($xmpLangMarkInfoPacket): void {
        $xmp = $xmpLangMarkInfoPacket();
        $compressedXmp = gzcompress($xmp);
        if (!is_string($compressedXmp)) {
            throw new RuntimeException('Unable to compress XMP MarkInfo fixture.');
        }

        $content = 'BT /F1 12 Tf 72 720 Td (Current XMP Lang MarkInfo Body) Tj ET';
        $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale XMP Lang MarkInfo Body) Tj ET';
        $pdf = "%PDF-2.0\n";
        $offsets = [];
        $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
            $offsets[$objectNumber] = strlen($pdf);
            $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
        };

        $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 5 0 R /MarkInfo 12 0 R /ViewerPreferences << /DisplayDocTitle true /Direction /L2R >> >>');
        $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
        $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
        $addObject(4, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
        $addObject(5, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream");
        $addObject(6, 0, '<< /Author (Current MarkInfo Author; Data Liberation Team) /Producer (Current MarkInfo Producer) >>');
        $addObject(12, 0, '<< /Marked true /UserProperties true /Suspects true >>');

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
            throw new RuntimeException('Unable to compress XMP MarkInfo xref stream.');
        }

        $pdf .= "90 0 obj\n"
            . '<< /Type /XRef /Size 91 /Root 1 0 R /Info 6 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
            . "stream\n{$compressedXref}\nendstream\nendobj\n"
            . "startxref\n{$xrefOffset}\n%%EOF\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 50 0 R /MarkInfo << /Marked false /UserProperties false /Suspects false >> >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Author (Stale MarkInfo Author) /Producer (Stale MarkInfo Producer) >>\nendobj\n"
            . "50 0 obj\n<< /Type /Metadata /Subtype /XML /Length 46 >>\nstream\n<stale>Stale XMP MarkInfo Catalog Title</stale>\nendstream\nendobj\n";

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info', 'catalog'], $metadata['source']);
        $t->same('Current XMP MarkInfo Catalog Title', $metadata['title']);
        $t->same('Catalog language and MarkInfo stay review metadata', $metadata['description']);
        $t->same(['Current MarkInfo Author', 'Data Liberation Team'], $metadata['authors']);
        $t->same('en-US', $metadata['language']);
        $t->same('en-US', $metadata['catalog']['language']);
        $t->same('Current XMP Lang MarkInfo Body', $plainText);
        $t->same([
            'source' => 'catalog_mark_info',
            'review_only' => true,
            'visible_text_source' => false,
            'object_number' => 12,
            'marked' => true,
            'user_properties' => true,
            'suspects' => true,
        ], $metadata['mark_info'] ?? null);
        $t->same($metadata['mark_info'] ?? null, $metadata['catalog']['mark_info'] ?? null);
        $t->same([
            'display_doc_title' => true,
            'direction' => 'L2R',
        ], $metadata['viewer_preferences']);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale XMP MarkInfo Catalog Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale MarkInfo Author'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'de-DE'));
        $t->true(!str_contains($plainText, 'Stale XMP Lang MarkInfo Body'));
        $t->true(!str_contains($plainText, 'Current XMP MarkInfo Catalog Title'));
        $t->true(!str_contains($plainText, 'UserProperties'));
        $t->true(!str_contains($plainText, 'Suspects'));
    },
];
