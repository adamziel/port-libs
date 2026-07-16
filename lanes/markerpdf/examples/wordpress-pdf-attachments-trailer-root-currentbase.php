<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentNamePayload = "Title,Status\nCurrent Root NameTree,Ready\n";
$currentPagePayload = '<wp-page><attachment role="current-root-page-af"/></wp-page>';
$staleNamePayload = "Title,Status\nStale Orphan NameTree,Ignore\n";
$stalePagePayload = '<wp-page><attachment role="stale-orphan-page-af"/></wp-page>';

$pdf = "%PDF-2.0\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};
$addEmbeddedFile = static function (
    int $fileSpecObject,
    int $streamObject,
    string $filename,
    string $description,
    string $relationship,
    string $payload,
    string $modDate
) use ($addObject): void {
    $checksum = md5($payload);
    $addObject($fileSpecObject, "<< /Type /Filespec /F ({$filename}) /Desc ({$description}) /AFRelationship /{$relationship} /EF << /F {$streamObject} 0 R >> >>");
    $addObject($streamObject, "<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate ({$modDate}) >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream");
};

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 4 0 R >> >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /AF [50 0 R] >>');
$addObject(4, '<< /Names [(stale-orphan-nametree.xml) 5 0 R] >>');
$addEmbeddedFile(5, 6, 'stale-orphan-nametree.xml', 'Stale orphan catalog name tree rows', 'Alternative', $staleNamePayload, 'D:20260605032900Z');
$addEmbeddedFile(50, 51, 'stale-orphan-page.xml', 'Stale orphan page associated file', 'Alternative', $stalePagePayload, 'D:20260605033000Z');

$addObject(10, '<< /Type /Catalog /Pages 11 0 R /Names << /EmbeddedFiles 12 0 R >> >>');
$addObject(11, '<< /Type /Pages /Kids [13 0 R] /Count 1 >>');
$addObject(12, '<< /Names [(current-root-nametree.xml) 14 0 R] >>');
$addObject(13, '<< /Type /Page /Parent 11 0 R /MediaBox [0 0 612 792] /AF [30 0 R] >>');
$addEmbeddedFile(14, 15, 'current-root-nametree.xml', 'Current trailer Root name tree rows', 'Data', $currentNamePayload, 'D:20260605033100Z');
$addEmbeddedFile(30, 31, 'current-root-page.xml', 'Current trailer Root page associated file', 'Source', $currentPagePayload, 'D:20260605033200Z');

$xrefOffset = strlen($pdf);
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$pdf .= "xref\n0 52\n" . $xrefRow(0, 65535, 'f');
for ($objectNumber = 1; $objectNumber <= 51; $objectNumber++) {
    $pdf .= isset($offsets[$objectNumber])
        ? $xrefRow($offsets[$objectNumber])
        : $xrefRow(0, 0, 'f');
}
$pdf .= "trailer\n<< /Size 52 /Root 10 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$nameTree = $summary['attachments'][0] ?? null;
$page = $summary['attachments'][1] ?? null;
if (!is_array($nameTree)
    || !is_array($page)
    || ($summary['attachment_count'] ?? null) !== 2
    || ($nameTree['filename'] ?? null) !== 'current-root-nametree.xml'
    || ($nameTree['source'] ?? null) !== 'embedded-files-name-tree'
    || ($page['filename'] ?? null) !== 'current-root-page.xml'
    || ($page['source'] ?? null) !== 'page-associated-file'
    || ($page['page_object_id'] ?? null) !== 13
    || str_contains($summaryJson, 'stale-orphan-nametree.xml')
    || str_contains($summaryJson, 'stale-orphan-page.xml')
    || str_contains($summaryJson, $currentNamePayload)
    || str_contains($summaryJson, $currentPagePayload)
    || str_contains($summaryJson, $staleNamePayload)
    || str_contains($summaryJson, $stalePagePayload)
) {
    throw new RuntimeException('Expected attachment preflight to use latest trailer Root catalog and omit orphan catalog payloads.');
}

echo "<!-- markerpdf-pdf-attachments-trailer-root-smoke " . htmlspecialchars(json_encode([
    'native_boundary' => 'latest trailer Root catalog before orphan catalog attachment preflight rows',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'attachment_count' => $summary['attachment_count'],
    'filenames' => $summary['filenames'],
    'trailer_root_catalog_selected' => ($page['page_object_id'] ?? null) === 13,
    'orphan_catalog_attachments_excluded' => !str_contains($summaryJson, 'stale-orphan-nametree.xml')
        && !str_contains($summaryJson, 'stale-orphan-page.xml'),
    'page_af_from_root_pages_selected' => ($page['source'] ?? null) === 'page-associated-file',
    'payload_bytes_omitted' => !str_contains($summaryJson, $currentNamePayload)
        && !str_contains($summaryJson, $currentPagePayload)
        && !str_contains($summaryJson, $staleNamePayload)
        && !str_contains($summaryJson, $stalePagePayload),
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($summary['attachments'] as $attachment) {
    echo '<li data-marker-attachment-sha256="'
        . htmlspecialchars((string) $attachment['sha256'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">'
        . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
