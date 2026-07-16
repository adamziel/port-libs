<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validPayload = '<wp-export><post id="structelem-associated-smoke"/></wp-export>';
$decoyPayload = '<wp-export><post id="structelem-associated-smoke-decoy"/></wp-export>';
$validChecksum = md5($validPayload);
$decoyChecksum = md5($decoyPayload);
$content = 'BT /F1 12 Tf /ArticleTitle << /MCID 0 >> BDC 72 720 Td (StructElem Associated File Smoke) Tj EMC ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 4 /Contents 5 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (structelem-source.xml) /Desc (StructElem associated source export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608151218Z) >> /Length " . strlen($validPayload) . " >>\nstream\n{$validPayload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /Filespec /F (structelem-decoy.xml) /Desc (Malformed StructElem trailing AF decoy) /AFRelationship /Alternative /EF << /F 13 0 R >> >>\nendobj\n"
    . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($decoyPayload) . " /CheckSum <{$decoyChecksum}> >> /Length " . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /StructTreeRoot /RoleMap << /ArticleTitle /H1 /PrivateAttachment /P >> /K [21 0 R 22 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Type /StructElem /S /ArticleTitle /T (Tagged source handoff) /Pg 3 0 R /AF [10 0 R] /K 0 >>\nendobj\n"
    . "22 0 obj\n<< /Type /StructElem /S /PrivateAttachment /T (Malformed trailing associated file) /Pg 3 0 R /AF [12 0 R] 10 0 R /K 0 >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$attachment = $summary['attachments'][0] ?? null;
$file = $files[0] ?? null;
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '';
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';

if (!is_array($attachment)
    || !is_array($file)
    || ($summary['attachment_count'] ?? null) !== 1
    || count($files) !== 1
    || ($attachment['source'] ?? null) !== 'structure-associated-file'
    || ($attachment['structure_associated_file'] ?? null) !== true
    || ($attachment['structure_role'] ?? null) !== 'ArticleTitle'
    || ($attachment['filename'] ?? null) !== 'structelem-source.xml'
    || ($attachment['checksum_matches'] ?? null) !== true
    || array_key_exists('bytes', $attachment)
    || ($file['source'] ?? null) !== 'structure_element_associated_files'
    || ($file['content'] ?? null) !== $validPayload
    || str_contains($summaryJson, $validPayload)
    || str_contains($summaryJson, $decoyPayload)
    || str_contains($summaryJson, 'structelem-decoy.xml')
    || str_contains($filesJson, $decoyPayload)
    || str_contains($filesJson, 'structelem-decoy.xml')
    || str_contains($plainText, '<wp-export>')
    || str_contains($plainText, 'structelem-source.xml')
    || str_contains($plainText, 'Tagged source handoff')
) {
    throw new RuntimeException('Expected StructElem /AF attachment boundary to expose only safe review metadata.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf:attachment-structelem-af-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-structure-associated-file-review',
    'native_boundary' => 'StructElem /AF FileSpec rows are included in attachment review while malformed trailing /AF operands are rejected',
    'source_truth' => [
        'upstream_marker_pdf_searchable_text_extraction_excludes_attachment_payloads',
        'pdf_2_structure_element_associated_files',
        'pdf_filespec_embedded_file_checksum_review',
    ],
    'attachment_count' => $summary['attachment_count'],
    'embedded_file_count' => count($files),
    'filename' => $attachment['filename'],
    'source' => $attachment['source'],
    'structure_role' => $attachment['structure_role'],
    'structure_title' => $attachment['structure_title'],
    'relationship' => $attachment['relationship'],
    'checksum_matches' => $attachment['checksum_matches'],
    'malformed_structelem_af_rejected' => !str_contains($summaryJson, 'structelem-decoy.xml')
        && !str_contains($filesJson, 'structelem-decoy.xml'),
    'payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $attachment),
    'payload_text_excluded_from_visible_text' => !str_contains($plainText, '<wp-export>'),
    'executes_python_or_models' => $summary['executes_python_or_models'],
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'],
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

$storageName = (string) ($attachment['filename_storage_name'] ?? $attachment['filename']);
echo '<!-- wp:file {"href":"media/' . htmlspecialchars($storageName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars($storageName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n";
