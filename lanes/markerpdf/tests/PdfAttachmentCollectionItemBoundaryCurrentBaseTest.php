<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentCollectionItemCurrentBasePdf = static function (): array {
    $payload = '<wp-export><post id="attachment-ci-current"/></wp-export>';
    $privatePayload = 'BT /F1 12 Tf 72 720 Td (Attachment CI Private Payload Leak) Tj ET';
    $checksum = md5($payload);
    $privateChecksum = md5($privatePayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Current Attachment CI Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageMode /UseAttachments /Collection 5 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Collection /View /T /D (source.xml) /Schema << /Subject << /Subtype /S /N (Subject) /O 1 >> /Priority << /Subtype /N /N (Priority) /O 2 >> /ReviewDate << /Subtype /D /N (Reviewed) /O 3 >> >> >>\nendobj\n"
        . "6 0 obj\n<< /Names [(source.xml) 10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Source portfolio attachment) /AFRelationship /Source /CI 30 0 R /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260605104112Z) >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /CollectionItem /Subject (Current WordPress Export) /Priority << /Type /CollectionSubitem /D 2 /P (P) >> /ReviewDate (D:20260605104112Z) /Approved true /PrivateStream 40 0 R >>\nendobj\n"
        . "40 0 obj\n<< /Type /Metadata /Subtype /text#2Fplain /Params << /Size " . strlen($privatePayload) . " /CheckSum <{$privateChecksum}> >> /Length " . strlen($privatePayload) . " >>\nstream\n{$privatePayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $payload, $privatePayload];
};

return [
    'summarizes FileSpec collection item metadata in attachment preflight without payload leakage' => static function (
        TestRunner $t
    ) use ($attachmentCollectionItemCurrentBasePdf): void {
        [$pdf, $payload, $privatePayload] = $attachmentCollectionItemCurrentBasePdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));

        $t->same(1, $summary['attachment_count']);
        $t->same(['source.xml'], $summary['filenames']);
        $t->same(strlen($payload), $summary['total_bytes']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same('source.xml', $attachment['filename']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260605104112Z', $attachment['modified_at']);

        $portfolioItem = $attachment['portfolio_item'] ?? [];
        $t->same('Current WordPress Export', $portfolioItem['Subject'] ?? null);
        $t->same(2, $portfolioItem['Priority']['value'] ?? null);
        $t->same('P', $portfolioItem['Priority']['prefix'] ?? null);
        $t->same('P2', $portfolioItem['Priority']['display_value'] ?? null);
        $t->same('D:20260605104112Z', $portfolioItem['ReviewDate'] ?? null);
        $t->same(true, $portfolioItem['Approved'] ?? null);
        $t->same(4, $attachment['portfolio_item_count'] ?? null);
        $t->same(false, array_key_exists('Type', $portfolioItem));
        $t->same(false, array_key_exists('PrivateStream', $portfolioItem));

        $t->same('Current Attachment CI Body', $plainText);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->true(is_string($encoded) && !str_contains($encoded, $payload));
        $t->true(is_string($encoded) && !str_contains($encoded, $privatePayload));
        $t->true(!str_contains($plainText, '<wp-export>'));
        $t->true(!str_contains($plainText, 'Attachment CI Private Payload Leak'));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
    },
];
