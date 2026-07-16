<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$content = 'BT /F1 12 Tf '
    . '/BodyCopy /BodyProp BDC 72 704 Td (Body glyph noise) Tj EMC '
    . '/Chap#54itle << /MCID 0 >> BDC 72 720 Td (Chapter heading glyphs) Tj EMC '
    . '/Illustration << /MCID 2 /Alt (Figure dashboard alt text) >> BDC q /Im1 Do Q EMC '
    . '/Artifact << /MCID 9 >> BDC 72 688 Td (Artifact footer noise) Tj EMC ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> /Properties << /BodyProp 30 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /StructTreeRoot /RoleMap 24 0 R /K [21 0 R 22 0 R 23 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Type /StructElem /S /Chap#54itle /Pg 3 0 R /K 0 >>\nendobj\n"
    . "22 0 obj\n<< /Type /StructElem /S /BodyCopy /Pg 3 0 R /K << /Type /MCR /MCID 1 >> >>\nendobj\n"
    . "23 0 obj\n<< /Type /StructElem /S /Illustration /Pg 3 0 R /K [<< /Type /MCR /Pg 3 0 R /MCID 2 >>] >>\nendobj\n"
    . "24 0 obj\n<< /Chap#54itle /H1 /BodyCopy /P /Illustration /Figure >>\nendobj\n"
    . "30 0 obj\n<< /MCID 1 /ActualText (Mapped paragraph replacement) >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$tagged = $extractor->extractTaggedContent($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-structtree-rolemap-tagged-content-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /StructTreeRoot /RoleMap plus MCID tagged content before Gutenberg block review',
    'mapped_roles' => array_column($tagged, 'role'),
    'raw_roles' => array_column($tagged, 'raw_role'),
    'used_actual_text_after_structure_order' => str_contains($plainText, 'Mapped paragraph replacement')
        && !str_contains($plainText, 'Body glyph noise'),
    'used_figure_alt_text' => str_contains($plainText, 'Figure dashboard alt text'),
    'excluded_artifact_mcid' => !str_contains($plainText, 'Artifact footer noise'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($tagged as $row) {
    $role = (string) ($row['role'] ?? '');
    $text = htmlspecialchars((string) $row['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if ($role === 'H1') {
        echo "<!-- wp:heading {\"level\":1} -->\n";
        echo "<h1>{$text}</h1>\n";
        echo "<!-- /wp:heading -->\n\n";
        continue;
    }

    if ($role === 'Figure') {
        echo "<!-- wp:paragraph -->\n";
        echo "<p><strong>Figure:</strong> {$text}</p>\n";
        echo "<!-- /wp:paragraph -->\n\n";
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo "<p>{$text}</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
