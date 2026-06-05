<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$portfolioAttachmentPdf = static function (): array {
    $payload = '<wp-export><post id="portfolio-preflight"/></wp-export>';
    $privatePayload = 'BT /F1 12 Tf 72 720 Td (Portfolio Private Payload Leak) Tj ET';
    $checksum = md5($payload);
    $privateChecksum = md5($privatePayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Portfolio Attachment Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageMode /UseAttachments /Collection 5 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Collection /View /T /D (source.xml) /Schema << /NameField << /Subtype /F /N (Filename) /O 1 /V true >> /DescriptionField << /Subtype /Desc /N (Description) /O 2 /E false >> /BytesField << /Subtype /Size /N (Bytes) /O 3 >> /ModifiedField << /Subtype /ModDate /N (Modified) /O 4 >> /Subject << /Subtype /S /N (Subject) /O 5 >> /Priority << /Subtype /N /N (Priority) /O 6 >> >> /Sort << /S [/Priority /ModifiedField] /A [true false] >> >>\nendobj\n"
        . "6 0 obj\n<< /Names [(source.xml) 10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (legacy-source.xml) /UF (source.xml) /Desc (Portfolio WordPress source) /AFRelationship /Source /CI 30 0 R /EF << /UF 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260605111833Z) >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /CollectionItem /Subject (Current Portfolio Export) /Priority << /Type /CollectionSubitem /D 2 /P (P) >> /PrivateStream 40 0 R >>\nendobj\n"
        . "40 0 obj\n<< /Type /Metadata /Subtype /text#2Fplain /Params << /Size " . strlen($privatePayload) . " /CheckSum <{$privateChecksum}> >> /Length " . strlen($privatePayload) . " >>\nstream\n{$privatePayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $payload, $privatePayload];
};

$encryptedPortfolioAttachmentPdf = static function (): array {
    $payload = '<wp-export><post id="encrypted-portfolio-preflight"/></wp-export>';
    $checksum = md5($payload);

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Collection 5 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
        . "5 0 obj\n<< /Type /Collection /View /T /D (encrypted-source.xml) /Schema << /NameField << /Subtype /F /N (Filename) /O 1 >> /Subject << /Subtype /S /N (Subject) /O 2 >> >> >>\nendobj\n"
        . "6 0 obj\n<< /Names [(encrypted-source.xml) 10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (legacy-encrypted-source.xml) /UF (encrypted-source.xml) /Desc (Encrypted portfolio source) /AFRelationship /Source /CI << /Subject (Encrypted Portfolio Export) >> /EF << /UF 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Filter /Standard /V 4 /StmF /Identity /StrF /StdCF /EFF /Identity /CF << /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 128 >> >> >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 20 0 R >>\n%%EOF";

    return [$pdf, $payload];
};

return [
    'carries catalog Collection schema and sort metadata into attachment preflight' => static function (
        TestRunner $t
    ) use ($portfolioAttachmentPdf): void {
        [$pdf, $payload, $privatePayload] = $portfolioAttachmentPdf();

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
        $t->same('source.xml', $attachment['unicode_filename']);
        $t->same('UF', $attachment['filename_source']);
        $t->same('UF', $attachment['ef_key']);
        $t->same('Portfolio WordPress source', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $portfolio = $attachment['portfolio'] ?? [];
        $t->same('catalog_collection', $portfolio['source'] ?? null);
        $t->same('Collection', $portfolio['type'] ?? null);
        $t->same('T', $portfolio['view'] ?? null);
        $t->same('source.xml', $portfolio['default_document'] ?? null);
        $t->same('Filename', $portfolio['schema']['NameField']['label'] ?? null);
        $t->same('F', $portfolio['schema']['NameField']['subtype'] ?? null);
        $t->same(true, $portfolio['schema']['NameField']['visible'] ?? null);
        $t->same(false, $portfolio['schema']['DescriptionField']['editable'] ?? null);
        $t->same(['Priority', 'ModifiedField'], $portfolio['sort']['keys'] ?? null);
        $t->same([true, false], $portfolio['sort']['ascending'] ?? null);

        $portfolioItem = $attachment['portfolio_item'] ?? [];
        $t->same('Current Portfolio Export', $portfolioItem['Subject'] ?? null);
        $t->same(2, $portfolioItem['Priority']['value'] ?? null);
        $t->same('P2', $portfolioItem['Priority']['display_value'] ?? null);
        $t->same(false, array_key_exists('PrivateStream', $portfolioItem));

        $fields = $attachment['portfolio_field_values'] ?? [];
        $t->same('source.xml', $fields['NameField']['value'] ?? null);
        $t->same('file_spec', $fields['NameField']['source'] ?? null);
        $t->same('text', $fields['NameField']['value_type'] ?? null);
        $t->same('Portfolio WordPress source', $fields['DescriptionField']['value'] ?? null);
        $t->same('file_spec', $fields['DescriptionField']['source'] ?? null);
        $t->same(strlen($payload), $fields['BytesField']['value'] ?? null);
        $t->same('embedded_file_params', $fields['BytesField']['source'] ?? null);
        $t->same('number', $fields['BytesField']['value_type'] ?? null);
        $t->same('D:20260605111833Z', $fields['ModifiedField']['value'] ?? null);
        $t->same('embedded_file_params', $fields['ModifiedField']['source'] ?? null);
        $t->same('Current Portfolio Export', $fields['Subject']['value'] ?? null);
        $t->same('collection_item', $fields['Subject']['source'] ?? null);
        $t->same(2, $fields['Priority']['value'] ?? null);
        $t->same('CollectionSubitem', $fields['Priority']['subitem_type'] ?? null);
        $t->same('P2', $fields['Priority']['display_value'] ?? null);
        $t->same('collection_subitem', $fields['Priority']['source'] ?? null);

        $t->same('Portfolio Attachment Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, $payload));
        $t->true(is_string($encoded) && !str_contains($encoded, $privatePayload));
        $t->true(!str_contains($plainText, '<wp-export>'));
        $t->true(!str_contains($plainText, 'Portfolio Private Payload Leak'));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
    },
    'redacts portfolio preflight metadata when FileSpec strings are encrypted' => static function (
        TestRunner $t
    ) use ($encryptedPortfolioAttachmentPdf): void {
        [$pdf, $payload] = $encryptedPortfolioAttachmentPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same([], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same(11, $attachment['stream_object_id']);
        $t->same('UF', $attachment['ef_key']);
        $t->same(true, $attachment['associated_file'] ?? false);
        $t->same('catalog_af', $attachment['associated_file_source'] ?? null);
        $t->same(false, array_key_exists('filename', $attachment));
        $t->same(false, array_key_exists('unicode_filename', $attachment));
        $t->same(false, array_key_exists('name_key', $attachment));
        $t->same(false, array_key_exists('description', $attachment));
        $t->same(false, array_key_exists('portfolio', $attachment));
        $t->same(false, array_key_exists('portfolio_item', $attachment));
        $t->same(false, array_key_exists('portfolio_field_values', $attachment));
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->same(strlen($payload), $attachment['byte_length']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same(false, $attachment['encrypted_payload_suppressed']);
        $t->same('suppressed_encrypted_strings', $attachment['encryption_policy']['file_spec_strings_policy'] ?? null);
        $t->same('preserved_identity_crypt_filter', $attachment['encryption_policy']['embedded_file_stream_policy'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Encrypted Portfolio Export'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'encrypted-source.xml'));
        $t->true(is_string($encoded) && !str_contains($encoded, $payload));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
    },
];
