<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentEfStreamGenerationBoundaryPdf = static function (): array {
    $referencedPayload = '<wp-export><post id="referenced-generation-zero-ef"/></wp-export>';
    $decoyPayload = '<wp-export><post id="decoy-generation-one-ef"/></wp-export>';
    $referencedChecksum = md5($referencedPayload);
    $decoyChecksum = md5($decoyPayload);
    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Embedded File Stream Generation Body) Tj ET';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 6 0 R /Names << /EmbeddedFiles 2 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Names [(generation-boundary.xml) 4 1 R] >>\nendobj\n"
        . "4 1 obj\n<< /Type /Filespec /F (generation-boundary.xml) /Desc (Current FileSpec with stale-generation EF stream reference) /AFRelationship /Source /EF << /F 5 0 R >> >>\nendobj\n"
        . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($referencedPayload) . " /CheckSum <{$referencedChecksum}> /ModDate (D:20260608113747Z) >> /Length " . strlen($referencedPayload) . " >>\n"
        . "stream\n{$referencedPayload}\nendstream\nendobj\n"
        . "5 1 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($decoyPayload) . " /CheckSum <{$decoyChecksum}> /ModDate (D:20260608113847Z) >> /Length " . strlen($decoyPayload) . " >>\n"
        . "stream\n{$decoyPayload}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Pages /Kids [7 0 R] /Count 1 >>\nendobj\n"
        . "7 0 obj\n<< /Type /Page /Parent 6 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >> /Contents 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "%%EOF\n";

    return [$pdf, $referencedPayload, $decoyPayload, $referencedChecksum, $decoyChecksum];
};

return [
    'keeps fallback EmbeddedFile EF stream references generation-exact before attachment summaries' => static function (
        TestRunner $t
    ) use ($attachmentEfStreamGenerationBoundaryPdf): void {
        [$pdf, $referencedPayload, $decoyPayload, $referencedChecksum, $decoyChecksum] = $attachmentEfStreamGenerationBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(['generation-boundary.xml'], $summary['filenames']);
        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('generation-boundary.xml', $attachment['name_key']);
        $t->same('generation-boundary.xml', $attachment['filename']);
        $t->same('Current FileSpec with stale-generation EF stream reference', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same(4, $attachment['file_spec_object_id']);
        $t->same(5, $attachment['stream_object_id']);
        $t->same(strlen($referencedPayload), $attachment['byte_length']);
        $t->same(hash('sha256', $referencedPayload), $attachment['sha256']);
        $t->same($referencedChecksum, $attachment['checksum_hex']);
        $t->same($referencedChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260608113747Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('generation-boundary.xml', $file['filename']);
        $t->same($referencedPayload, $file['content']);
        $t->same(strlen($referencedPayload), $file['size']);
        $t->same(hash('sha256', $referencedPayload), $file['content_sha256']);
        $t->same($referencedChecksum, $file['checksum']);
        $t->same(true, $file['checksum_matches']);

        $t->same('Embedded File Stream Generation Body', $plainText);
        foreach ([$referencedPayload, $decoyPayload, $decoyChecksum, 'decoy-generation-one-ef'] as $hidden) {
            $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $hidden));
            $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, $decoyPayload));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
