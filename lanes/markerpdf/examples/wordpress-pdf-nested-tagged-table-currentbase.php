<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\TaggedTableStructureExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

function markerpdf_wordpress_nested_tagged_table_pdf(): string
{
    $content = "BT /F1 12 Tf\n"
        . "/OuterHead << /MCID 0 >> BDC 72 720 Td (Scope) Tj EMC\n"
        . "/OuterHead << /MCID 1 >> BDC 108 0 Td (State) Tj EMC\n"
        . "/OuterCell << /MCID 2 >> BDC -108 -30 Td (Posts) Tj EMC\n"
        . "/OuterCell << /MCID 3 >> BDC 108 0 Td (Review packet) Tj EMC\n"
        . "/InnerHead << /MCID 4 >> BDC 16 -22 Td (Inner scope) Tj EMC\n"
        . "/InnerHead << /MCID 5 >> BDC 96 0 Td (Inner state) Tj EMC\n"
        . "/InnerCell << /MCID 6 >> BDC -96 -22 Td (Media) Tj EMC\n"
        . "/InnerCell << /MCID 7 >> BDC 96 0 Td (Ready) Tj EMC\n"
        . "ET";

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 30 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "30 0 obj\n<< /Type /StructTreeRoot /RoleMap << /OuterTable /Table /OuterRow /TR /OuterHead /TH /OuterCell /TD /InnerTable /Table /InnerRow /TR /InnerHead /TH /InnerCell /TD >> /ParentTree 31 0 R /K 40 0 R >>\nendobj\n"
        . "31 0 obj\n<< /Nums [0 [42 0 R 43 0 R 46 0 R 47 0 R 50 0 R 51 0 R 53 0 R 54 0 R]] >>\nendobj\n"
        . "40 0 obj\n<< /Type /StructElem /S /OuterTable /Pg 3 0 R /K [41 0 R 45 0 R] >>\nendobj\n"
        . "41 0 obj\n<< /Type /StructElem /S /OuterRow /Pg 3 0 R /K [42 0 R 43 0 R] >>\nendobj\n"
        . "42 0 obj\n<< /Type /StructElem /S /OuterHead /Pg 3 0 R /K 0 >>\nendobj\n"
        . "43 0 obj\n<< /Type /StructElem /S /OuterHead /Pg 3 0 R /K 1 >>\nendobj\n"
        . "45 0 obj\n<< /Type /StructElem /S /OuterRow /Pg 3 0 R /K [46 0 R 47 0 R] >>\nendobj\n"
        . "46 0 obj\n<< /Type /StructElem /S /OuterCell /Pg 3 0 R /K 2 >>\nendobj\n"
        . "47 0 obj\n<< /Type /StructElem /S /OuterCell /Pg 3 0 R /ActualText (CUSTOM_GLYPH_LEAK_SHOULD_NOT_RENDER) /K [3 48 0 R] >>\nendobj\n"
        . "48 0 obj\n<< /Type /StructElem /S /InnerTable /Pg 3 0 R /K [49 0 R 52 0 R] >>\nendobj\n"
        . "49 0 obj\n<< /Type /StructElem /S /InnerRow /Pg 3 0 R /K [50 0 R 51 0 R] >>\nendobj\n"
        . "50 0 obj\n<< /Type /StructElem /S /InnerHead /Pg 3 0 R /K 4 >>\nendobj\n"
        . "51 0 obj\n<< /Type /StructElem /S /InnerHead /Pg 3 0 R /K 5 >>\nendobj\n"
        . "52 0 obj\n<< /Type /StructElem /S /InnerRow /Pg 3 0 R /K [53 0 R 54 0 R] >>\nendobj\n"
        . "53 0 obj\n<< /Type /StructElem /S /InnerCell /Pg 3 0 R /K 6 >>\nendobj\n"
        . "54 0 obj\n<< /Type /StructElem /S /InnerCell /Pg 3 0 R /K 7 >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
}

$pdf = markerpdf_wordpress_nested_tagged_table_pdf();
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$extracted = (new TaggedTableStructureExtractor())->extract($pdf);
$record = $extracted['tables'][0] ?? null;
if (!is_array($record)) {
    throw new RuntimeException('Nested tagged table smoke did not produce a WordPress table block.');
}

$wordpressBlock = (string) $record['wordpress_block'];
if (!str_contains($wordpressBlock, 'data-markerpdf-nested-table="true"')) {
    throw new RuntimeException('Nested tagged table smoke did not preserve the nested table marker.');
}
if (str_contains($wordpressBlock, 'CUSTOM_GLYPH_LEAK_SHOULD_NOT_RENDER')) {
    throw new RuntimeException('Nested tagged table smoke leaked custom glyph ActualText into visible output.');
}

echo $wordpressBlock . "\n";
echo '<!-- ' . json_encode([
    'scenario' => 'wordpress-pdf-nested-tagged-table-currentbase',
    'native_boundary' => 'catalog StructTreeRoot nested Table children become one WordPress table block with an inner table in the parent cell',
    'table_count' => $metadata['structure_tree']['tagged_tables']['table_count'] ?? null,
    'nested_table_count' => $metadata['structure_tree']['tagged_tables']['nested_table_count'] ?? null,
    'top_level_table_objects' => $metadata['structure_tree']['tagged_tables']['top_level_table_objects'] ?? [],
    'nested_table_objects' => $metadata['structure_tree']['tagged_tables']['nested_table_objects'] ?? [],
    'replace_texts' => $record['replace_texts'] ?? [],
    'nested_wordpress_table_preserved' => str_contains($wordpressBlock, 'data-markerpdf-nested-table="true"'),
    'custom_glyph_leak_rejected' => !str_contains($wordpressBlock, 'CUSTOM_GLYPH_LEAK_SHOULD_NOT_RENDER'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES) . " -->\n";
