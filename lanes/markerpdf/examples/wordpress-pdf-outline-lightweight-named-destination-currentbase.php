<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$introContent = 'BT /F1 12 Tf 72 720 Td (Lightweight named destination intro body) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (Lightweight named destination appendix body) Tj ET';
$outlineMetadata = '<x:xmpmeta>Named destination outline metadata stays review only</x:xmpmeta>';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Lightweight Named Destination Chapter) /Parent 5 0 R /Dest /NamedIntro /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Lightweight Named Destination Appendix) /Parent 5 0 R /Prev 6 0 R /Dest (NamedAppendix) /Metadata 40 0 R >>\nendobj\n"
    . "20 0 obj\n<< /Names [(NamedAppendix) [4 0 R /XYZ 64 null 0] (NamedIntro) [3 0 R /FitH 720]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Metadata /Subtype /XML /Length " . strlen($outlineMetadata) . " >>\nstream\n{$outlineMetadata}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$textExtractor = new PdfTextExtractor();
$outlineMetadataBoundary = $textExtractor->extractOutlineMetadata($pdf);
$documentMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$encodedBoundary = json_encode($outlineMetadataBoundary, JSON_UNESCAPED_SLASHES);
$encodedDocumentMetadata = json_encode($documentMetadata, JSON_UNESCAPED_SLASHES);

if (($outlineMetadataBoundary['pdf_toc'][0]['page'] ?? null) !== 0
    || ($outlineMetadataBoundary['pdf_toc'][1]['page'] ?? null) !== 1
) {
    throw new RuntimeException('Expected lightweight pdf_toc named destinations before WordPress outline rendering.');
}
if (($documentMetadata['document_outline']['resolved_destination_count'] ?? null) !== 2) {
    throw new RuntimeException('Expected named outline destinations to stay resolved in review metadata.');
}
if (!str_contains($plainText, 'Lightweight named destination intro body')
    || !str_contains($plainText, 'Lightweight named destination appendix body')
) {
    throw new RuntimeException('Expected visible page text to remain importable.');
}
if (str_contains($plainText, 'Lightweight Named Destination Chapter')
    || str_contains($plainText, 'Lightweight Named Destination Appendix')
    || str_contains($plainText, 'Named destination outline metadata stays review only')
    || !is_string($encodedBoundary)
    || !is_string($encodedDocumentMetadata)
    || str_contains($encodedBoundary, 'Named destination outline metadata stays review only')
    || str_contains($encodedDocumentMetadata, 'Named destination outline metadata stays review only')
) {
    throw new RuntimeException('Expected outline/navigation metadata to stay out of WordPress visible text and metadata payload output.');
}

$summary = [
    'support_component' => 'native-pdf-outline-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'lightweight pdf_toc resolves catalog named destinations before WordPress outline review output',
    'toc_count' => count($outlineMetadataBoundary['pdf_toc']),
    'toc_pages' => array_column($outlineMetadataBoundary['pdf_toc'], 'page'),
    'resolved_destination_count' => $documentMetadata['document_outline']['resolved_destination_count'] ?? null,
    'outline_metadata_payload_hidden' => !str_contains($plainText, 'Named destination outline metadata stays review only')
        && !str_contains($encodedBoundary, 'Named destination outline metadata stays review only')
        && !str_contains($encodedDocumentMetadata, 'Named destination outline metadata stays review only'),
    'visible_text' => $plainText,
];

echo '<!-- markerpdf-outline-lightweight-named-destination-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($outlineMetadataBoundary['pdf_toc'] as $tocRow) {
    $item = [
        'markerTitle' => $tocRow['title'],
        'markerLevel' => $tocRow['level'],
        'markerPageIndex' => $tocRow['page'],
        'markerSource' => 'markerpdf-pdf-toc',
    ];

    echo '<li data-marker-pdf-toc="'
        . htmlspecialchars(json_encode($item, JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">'
        . htmlspecialchars($tocRow['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
