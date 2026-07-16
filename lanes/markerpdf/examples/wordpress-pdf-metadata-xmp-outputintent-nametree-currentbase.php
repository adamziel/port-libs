<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmpPacket = static function (string $title, string $description): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>2026-06-02T21:02:00-04:00</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-02T21:05:30-04:00</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$documentXmp = $xmpPacket('Current Document XMP NameTree Boundary Title', 'Document-level XMP should remain authoritative');
$nameTreeXmp = $xmpPacket('Hidden NameTree XMP Title', 'Name-tree XMP packet is review-only');
$staleNameTreeXmp = $xmpPacket('Stale Hidden NameTree XMP Title', 'Stale name-tree XMP packet must not be selected');

$documentXmpStream = gzcompress($documentXmp);
$nameTreeXmpStream = gzcompress($nameTreeXmp);
$staleNameTreeXmpStream = gzcompress($staleNameTreeXmp);
$rootProfile = 'Current document root ICC profile bytes';
$nameTreeProfile = 'Current name-tree ICC profile bytes should stay review metadata';
$staleProfile = 'Stale name-tree ICC profile bytes should not be selected';
$rootProfileStream = gzcompress($rootProfile);
$nameTreeProfileStream = gzcompress($nameTreeProfile);
$staleProfileStream = gzcompress($staleProfile);
if (
    !is_string($documentXmpStream)
    || !is_string($nameTreeXmpStream)
    || !is_string($staleNameTreeXmpStream)
    || !is_string($rootProfileStream)
    || !is_string($nameTreeProfileStream)
    || !is_string($staleProfileStream)
) {
    throw new RuntimeException('Unable to compress name-tree XMP OutputIntent smoke streams.');
}

$script = "app.alert('name-tree metadata review only')";
$staleScript = "app.alert('stale name-tree metadata')";
$content = 'BT /F1 12 Tf 72 720 Td (Current XMP OutputIntent NameTree Body) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale XMP OutputIntent NameTree Body) Tj ET';
$pdf = "%PDF-2.0\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Metadata 14 0 R /OutputIntents [9 0 R] /Names << /JavaScript 20 0 R /IDS 30 0 R /Dests 70 0 R >> >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(5, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($nameTreeXmpStream) . " >>\nstream\n{$nameTreeXmpStream}\nendstream");
$addObject(7, 0, '<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length ' . strlen($rootProfileStream) . " >>\nstream\n{$rootProfileStream}\nendstream");
$addObject(8, 0, '<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length ' . strlen($nameTreeProfileStream) . " >>\nstream\n{$nameTreeProfileStream}\nendstream");
$addObject(9, 0, '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Current Document Root sRGB) /Info (Root document PDF/A profile) /DestOutputProfile 7 0 R >>');
$addObject(13, 0, '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (NameTree Review sRGB) /Info (Name-tree attachment-local PDF/A profile) /DestOutputProfile 8 0 R >>');
$addObject(14, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($documentXmpStream) . " >>\nstream\n{$documentXmpStream}\nendstream");
$addObject(20, 0, '<< /Kids [21 0 R 22 0 R] >>');
$addObject(21, 0, '<< /Limits [(metadata) (metadata-z)] /Names [(metadata-action) 40 0 R (z-stale-action) 41 0 R] >>');
$addObject(22, 0, '<< /Limits [(metadata-close) (metadata-close)] /Names [(metadata-close) 42 0 R] >>');
$addObject(30, 0, '<< /Limits [(review-id) (review-id)] /Names [(review-id) << /Type /DeveloperExtension /Metadata 5 0 R /OutputIntents [13 0 R] >>] >>');
$addObject(40, 0, "<< /S /JavaScript /JS ({$script}) /Metadata 5 0 R /OutputIntents [13 0 R] >>");
$addObject(41, 0, "<< /S /JavaScript /JS ({$staleScript}) /Metadata 50 0 R /OutputIntents [53 0 R] >>");
$addObject(42, 0, '<< /S /JavaScript /JS <' . strtoupper(bin2hex("\xfe\xff\0a\0p\0p\0.\0a\0l\0e\0r\0t\0(\0'\0m\0e\0t\0a\0d\0a\0t\0a\0-\0c\0l\0o\0s\0e\0'\0)")) . '> /Metadata 5 0 R /OutputIntents [13 0 R] >>');
$addObject(50, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleNameTreeXmpStream) . " >>\nstream\n{$staleNameTreeXmpStream}\nendstream");
$addObject(52, 0, '<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length ' . strlen($staleProfileStream) . " >>\nstream\n{$staleProfileStream}\nendstream");
$addObject(53, 0, '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Stale NameTree Review sRGB) /Info (Stale name-tree attachment-local PDF/A profile) /DestOutputProfile 52 0 R >>');
$addObject(60, 0, '<< /Title (Current Info Fallback Title) /Author (Current Info Author) /Producer (Current Info Producer) >>');
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
    throw new RuntimeException('Unable to compress name-tree XMP OutputIntent xref smoke stream.');
}

$pdf .= "90 0 obj\n"
    . '<< /Type /XRef /Size 91 /Root 1 0 R /Info 60 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 50 0 R /OutputIntents [53 0 R] /Names << /JavaScript 80 0 R /IDS 81 0 R >> >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /S /JavaScript /JS ({$staleScript}) /Metadata 50 0 R /OutputIntents [53 0 R] >>\nendobj\n"
    . "60 0 obj\n<< /Title (Stale Info Fallback Title) /Author (Stale Info Author) >>\nendobj\n"
    . "80 0 obj\n<< /Names [(stale-metadata-action) 40 0 R] >>\nendobj\n"
    . "81 0 obj\n<< /Names [(stale-review-id) 41 0 R] >>\nendobj\n";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$nameTrees = $metadata['document_name_trees'] ?? [];
$javaScriptEntry = $nameTrees['trees']['JavaScript']['entries'][0] ?? [];

if (($metadata['title'] ?? null) !== 'Current Document XMP NameTree Boundary Title') {
    throw new RuntimeException('Expected document XMP title to remain authoritative.');
}
if (($metadata['pdfa']['output_condition_identifiers'] ?? []) !== ['Current Document Root sRGB']) {
    throw new RuntimeException('Expected only root OutputIntent to contribute document PDF/A metadata.');
}
if (($javaScriptEntry['metadata_review']['object_number'] ?? null) !== 5) {
    throw new RuntimeException('Expected name-tree action metadata stream review.');
}
if (($javaScriptEntry['output_intents_review']['output_condition_identifiers'] ?? []) !== ['NameTree Review sRGB']) {
    throw new RuntimeException('Expected name-tree OutputIntent review summary.');
}
if (
    !is_string($encoded)
    || str_contains($encoded, 'Hidden NameTree XMP Title')
    || str_contains($encoded, $nameTreeProfile)
    || str_contains($encoded, $script)
    || str_contains($encoded, 'Stale Hidden NameTree XMP Title')
    || str_contains($encoded, 'Stale NameTree Review sRGB')
    || str_contains($plainText, 'Hidden NameTree XMP Title')
    || str_contains($plainText, 'NameTree Review sRGB')
    || str_contains($plainText, 'Stale XMP OutputIntent NameTree Body')
) {
    throw new RuntimeException('Expected name-tree XMP, ICC, action, and stale payloads to stay review-only.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-metadata-xmp-outputintent-nametree-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-catalog-name-tree-metadata-review',
    'native_boundary' => 'catalog /Names value dictionaries summarize nested /Metadata XMP and /OutputIntents without promotion or action execution',
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'document_title' => $metadata['title'] ?? null,
    'root_pdfa_identifiers' => $metadata['pdfa']['output_condition_identifiers'] ?? [],
    'name_tree_names' => $nameTrees['tree_names'] ?? [],
    'javascript_names' => $nameTrees['trees']['JavaScript']['names'] ?? [],
    'metadata_review_object' => $javaScriptEntry['metadata_review']['object_number'] ?? null,
    'metadata_review_fields' => $javaScriptEntry['metadata_review']['xmp_summary']['field_names'] ?? [],
    'metadata_payload_included' => $javaScriptEntry['metadata_review']['xmp_summary']['payload_included'] ?? null,
    'name_tree_pdfa_identifiers' => $javaScriptEntry['output_intents_review']['output_condition_identifiers'] ?? [],
    'name_tree_profile_hashes' => $javaScriptEntry['output_intents_review']['profile_sha256'] ?? [],
    'visible_text' => $plainText,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:metadata-review ' . $htmlJson([
    'document_title' => $metadata['title'] ?? null,
    'document_pdfa' => $metadata['pdfa'] ?? [],
    'name_tree_entry' => [
        'name' => $javaScriptEntry['name'] ?? null,
        'action_type' => $javaScriptEntry['action_type'] ?? null,
        'executes_action' => $javaScriptEntry['executes_action'] ?? null,
        'metadata_review' => [
            'object_number' => $javaScriptEntry['metadata_review']['object_number'] ?? null,
            'bytes' => $javaScriptEntry['metadata_review']['bytes'] ?? null,
            'xmp_summary' => $javaScriptEntry['metadata_review']['xmp_summary'] ?? [],
        ],
        'output_intents_review' => $javaScriptEntry['output_intents_review'] ?? [],
    ],
]) . " -->\n";
