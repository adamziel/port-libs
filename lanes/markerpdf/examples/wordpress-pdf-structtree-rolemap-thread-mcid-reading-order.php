<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf '
    . '/BodyCopy /BodyProp BDC 72 704 Td (Body glyph noise) Tj EMC '
    . '/Chap#54itle << /MCID 0 >> BDC 72 720 Td (Chapter heading glyphs) Tj EMC '
    . '/Illustration << /MCID 2 /Alt (Figure dashboard alt text) >> BDC q /Im1 Do Q EMC '
    . '/Artifact << /MCID 9 >> BDC 72 688 Td (Artifact footer noise) Tj EMC ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Threads [40 0 R] /MarkInfo << /Marked true >> /StructTreeRoot 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> /Properties << /BodyProp 30 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /StructTreeRoot /RoleMap 24 0 R /K [21 0 R 22 0 R 23 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Type /StructElem /S /Chap#54itle /Pg 3 0 R /K 0 >>\nendobj\n"
    . "22 0 obj\n<< /Type /StructElem /S /BodyAlias /Pg 3 0 R /K << /Type /MCR /MCID 1 >> >>\nendobj\n"
    . "23 0 obj\n<< /Type /StructElem /S /Illustration /Pg 3 0 R /K [<< /Type /MCR /Pg 3 0 R /MCID 2 >>] >>\nendobj\n"
    . "24 0 obj\n<< /Chap#54itle /H1 /BodyAlias /BodyCopy /BodyCopy /P /Illustration /Figure >>\nendobj\n"
    . "30 0 obj\n<< /MCID 1 /ActualText (Threaded paragraph replacement) >>\nendobj\n"
    . "40 0 obj\n<< /Type /Thread /F 41 0 R /I << /Title (Thread should not erase MCIDs) >> >>\nendobj\n"
    . "41 0 obj\n<< /Type /Bead /T 40 0 R /P 3 0 R /R [60 696 250 710] /N 42 0 R /V 42 0 R >>\nendobj\n"
    . "42 0 obj\n<< /Type /Bead /T 40 0 R /P 3 0 R /R [60 714 250 730] /N 41 0 R /V 41 0 R >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$tagged = $extractor->extractTaggedContent($pdf);
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$expected = [
    'Chapter heading glyphs',
    'Threaded paragraph replacement',
    'Figure dashboard alt text',
];

echo '<!-- markerpdf-structtree-rolemap-thread-mcid-reading-order ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-structtree-rolemap-mcid-plus-catalog-threads',
    'native_boundary' => 'catalog /StructTreeRoot /RoleMap MCID replay takes precedence over catalog /Threads bead fallback before Gutenberg rendering',
    'used_structure_tree_order' => $lines === $expected,
    'role_map_chain_resolved' => array_column($tagged, 'role') === ['H1', 'P', 'Figure'],
    'thread_beads_preserved_mcid_replay' => str_contains($plainText, 'Threaded paragraph replacement')
        && !str_contains($plainText, 'Body glyph noise'),
    'used_figure_alt_text' => str_contains($plainText, 'Figure dashboard alt text'),
    'excluded_artifact_mcid' => !str_contains($plainText, 'Artifact footer noise'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
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
