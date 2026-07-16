<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentNamePayload = "Title,Status\nGeneration One NameTree,Ready\n";
$currentCatalogPayload = '<wp-export><post id="generation-one-catalog-af"/></wp-export>';
$staleNamePayload = "Title,Status\nGeneration Zero NameTree,Ignore\n";
$staleCatalogPayload = '<wp-export><post id="generation-zero-catalog-af"/></wp-export>';

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber][$generation] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$addEmbeddedFile = static function (
    int $fileSpecObject,
    int $streamObject,
    int $generation,
    string $filename,
    string $description,
    string $relationship,
    string $payload,
    string $modDate
) use ($addObject): void {
    $checksum = md5($payload);
    $addObject(
        $fileSpecObject,
        $generation,
        "<< /Type /Filespec /F ({$filename}) /Desc ({$description}) /AFRelationship /{$relationship} /EF << /F {$streamObject} {$generation} R >> >>"
    );
    $addObject(
        $streamObject,
        $generation,
        "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate ({$modDate}) >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream"
    );
};

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 3 0 R >> /AF [7 0 R] >>');
$addObject(2, 0, '<< /Type /Pages /Kids [] /Count 0 >>');
$addObject(3, 0, '<< /Names [(generation-zero-name.csv) 4 0 R] >>');
$addEmbeddedFile(4, 5, 0, 'generation-zero-name.csv', 'Stale generation-zero name tree rows', 'Alternative', $staleNamePayload, 'D:20260605194800Z');
$addEmbeddedFile(7, 8, 0, 'generation-zero-catalog.csv', 'Stale generation-zero catalog AF rows', 'Alternative', $staleCatalogPayload, 'D:20260605194900Z');

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n0 9\n" . $xrefRow(0, 65535, 'f');
for ($objectNumber = 1; $objectNumber <= 8; $objectNumber++) {
    $pdf .= isset($offsets[$objectNumber][0])
        ? $xrefRow($offsets[$objectNumber][0])
        : $xrefRow(0, 0, 'f');
}
$pdf .= "trailer\n<< /Size 9 /Root 1 0 R >>\n";

$addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R /Names << /EmbeddedFiles 3 1 R >> /AF [7 0 R 7 1 R] >>');
$addObject(2, 1, '<< /Type /Pages /Kids [] /Count 0 >>');
$addObject(3, 1, '<< /Names [(generation-zero-name.csv) 4 0 R (generation-one-name.csv) 4 1 R] >>');
$addEmbeddedFile(4, 5, 1, 'generation-one-name.csv', 'Current generation-one name tree rows', 'Data', $currentNamePayload, 'D:20260605195000Z');
$addEmbeddedFile(7, 8, 1, 'generation-one-catalog.csv', 'Current generation-one catalog AF rows', 'Source', $currentCatalogPayload, 'D:20260605195100Z');

$latestXrefOffset = strlen($pdf);
$pdf .= "xref\n1 1\n" . $xrefRow(0, 1, 'n')
    . "trailer\n<< /Size 9 /Root 1 1 R /Prev {$previousXrefOffset} >>\n"
    . "startxref\n{$latestXrefOffset}\n%%EOF\n";

$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);

$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$firstFile = $files[0] ?? null;
$catalogFile = $files[1] ?? null;

if (!is_array($firstFile)
    || !is_array($catalogFile)
    || count($files) !== 2
    || ($firstFile['name'] ?? null) !== 'generation-one-name.csv'
    || ($catalogFile['filename'] ?? null) !== 'generation-one-catalog.csv'
    || ($catalogFile['associated_file_index'] ?? null) !== 1
    || ($summary['attachment_count'] ?? null) !== 2
    || ($summary['filenames'] ?? []) !== ['generation-one-name.csv', 'generation-one-catalog.csv']
    || str_contains($filesJson, 'generation-zero-name.csv')
    || str_contains($summaryJson, 'generation-zero-catalog.csv')
    || str_contains($metadataJson, 'Stale generation-zero')
    || str_contains($summaryJson, $currentNamePayload)
    || str_contains($summaryJson, $currentCatalogPayload)
) {
    throw new RuntimeException('Expected generation-exact embedded-file extraction and attachment summary.');
}

echo '<!-- markerpdf:embedded-files-generation-boundary ' . htmlspecialchars(json_encode([
    'native_boundary' => 'generation-exact EmbeddedFiles FileSpec and EmbeddedFile stream references',
    'embedded_file_count' => count($files),
    'attachment_count' => $summary['attachment_count'],
    'filenames' => $summary['filenames'],
    'catalog_associated_file_index' => $catalogFile['associated_file_index'],
    'stale_generation_name_tree_excluded' => !str_contains($filesJson, 'generation-zero-name.csv')
        && !str_contains($metadataJson, 'generation-zero-name.csv'),
    'stale_generation_catalog_af_excluded' => !str_contains($filesJson, 'generation-zero-catalog.csv')
        && !str_contains($metadataJson, 'generation-zero-catalog.csv'),
    'payload_bytes_omitted_from_summary' => !str_contains($summaryJson, $currentNamePayload)
        && !str_contains($summaryJson, $currentCatalogPayload),
    'executes_python_or_models' => $summary['executes_python_or_models'],
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'],
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo '<!-- wp:file {"href":"media/' . htmlspecialchars((string) $firstFile['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars((string) $firstFile['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars((string) $firstFile['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n";
