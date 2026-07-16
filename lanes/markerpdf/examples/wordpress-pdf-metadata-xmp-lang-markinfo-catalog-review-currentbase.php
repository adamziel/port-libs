<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
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

$compressedXmp = gzcompress($xmp);
if (!is_string($compressedXmp)) {
    throw new RuntimeException('Unable to compress XMP MarkInfo smoke fixture.');
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
    throw new RuntimeException('Unable to compress XMP MarkInfo smoke xref stream.');
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
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$markInfo = $metadata['mark_info'] ?? [];

if (($metadata['title'] ?? null) !== 'Current XMP MarkInfo Catalog Title') {
    throw new RuntimeException('Expected current XMP title to win.');
}
if (($metadata['language'] ?? null) !== 'en-US') {
    throw new RuntimeException('Expected current catalog language.');
}
if (($markInfo['marked'] ?? null) !== true || ($markInfo['user_properties'] ?? null) !== true || ($markInfo['suspects'] ?? null) !== true) {
    throw new RuntimeException('Expected current catalog MarkInfo flags.');
}
if (
    !is_string($encoded)
    || str_contains($encoded, 'Stale XMP MarkInfo Catalog Title')
    || str_contains($encoded, 'Stale MarkInfo Author')
    || str_contains($plainText, 'Stale XMP Lang MarkInfo Body')
    || str_contains($plainText, 'Current XMP MarkInfo Catalog Title')
) {
    throw new RuntimeException('Expected stale metadata and XMP payloads to stay out of WordPress output.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-metadata-xmp-lang-markinfo-catalog-review-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-catalog-markinfo-review',
    'native_boundary' => 'current xref-selected catalog /Metadata XMP, /Lang, /ViewerPreferences, and /MarkInfo review metadata before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title' => $metadata['title'] ?? null,
    'language' => $metadata['language'] ?? null,
    'mark_info' => $markInfo,
    'viewer_preferences' => $metadata['viewer_preferences'] ?? [],
    'visible_text' => $plainText,
    'stale_catalog_excluded' => is_string($encoded) && !str_contains($encoded, 'de-DE'),
    'xmp_payload_excluded_from_visible_text' => !str_contains($plainText, 'Current XMP MarkInfo Catalog Title'),
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:catalog-markinfo-review ' . $htmlJson([
    'source' => $markInfo['source'] ?? null,
    'object_number' => $markInfo['object_number'] ?? null,
    'marked' => $markInfo['marked'] ?? null,
    'user_properties' => $markInfo['user_properties'] ?? null,
    'suspects' => $markInfo['suspects'] ?? null,
    'review_only' => $markInfo['review_only'] ?? null,
    'visible_text_source' => $markInfo['visible_text_source'] ?? null,
]) . " -->\n";
