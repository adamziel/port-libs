<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;

$attachmentAnnotationPresentationPdf = static function (): array {
    $sourcePayload = '<wp-export><post id="annotation-presentation"/></wp-export>';
    $hiddenPayload = '{"review":"hidden-attachment"}';
    $sourceChecksum = md5($sourcePayload);
    $hiddenChecksum = md5($hiddenPayload);
    $hiddenFileSpec = '<< /Type /Filespec /F (hidden-review.json) /Desc (Hidden reviewer packet) /AFRelationship /Supplement /EF << /F 11 0 R >> >>';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Annots [8 0 R 10 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Presented source attachment) /AFRelationship /Source /EF << /F 5 0 R >> >>\nendobj\n"
        . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260605070300Z) >> /Length " . strlen($sourcePayload) . " >>\n"
        . "stream\n{$sourcePayload}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(source.xml) 4 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [72 700 92 720] /F 4 /Name /Paperclip /Contents (Visible attachment marker) /FS 4 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [120 700 140 720] /F 38 /Name /PushPin /Contents (Hidden attachment marker) /FS {$hiddenFileSpec} >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($hiddenPayload) . " /CheckSum <{$hiddenChecksum}> /ModDate (D:20260605070301Z) >> /Length " . strlen($hiddenPayload) . " >>\n"
        . "stream\n{$hiddenPayload}\nendstream\nendobj\n"
        . "%%EOF\n";

    return [$pdf, $sourcePayload, $hiddenPayload, $sourceChecksum, $hiddenChecksum];
};

return [
    'carries FileAttachment annotation icon and visibility flags through attachment preflight' => static function (
        TestRunner $t
    ) use ($attachmentAnnotationPresentationPdf): void {
        [$pdf, $sourcePayload, $hiddenPayload, $sourceChecksum, $hiddenChecksum] = $attachmentAnnotationPresentationPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(2, $summary['attachment_count']);
        $t->same(['source.xml', 'hidden-review.json'], $summary['filenames']);

        $source = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $source['source']);
        $t->same('source.xml', $source['filename']);
        $t->same(true, $source['file_attachment_annotation']);
        $t->same('page_annotation', $source['file_attachment_annotation_source']);
        $t->same(8, $source['annotation_object_id']);
        $t->same(4, $source['annotation_flags']);
        $t->same(['print'], $source['annotation_flag_names']);
        $t->same('visible', $source['annotation_visibility']);
        $t->same(true, $source['annotation_visible']);
        $t->same(false, $source['annotation_hidden']);
        $t->same(true, $source['annotation_printable']);
        $t->same(false, $source['annotation_no_view']);
        $t->same('Paperclip', $source['annotation_icon']);
        $t->same('standard_file_attachment_icon', $source['annotation_icon_status']);
        $t->same($sourceChecksum, $source['checksum_hex']);
        $t->same(true, $source['checksum_matches']);

        $hidden = $summary['attachments'][1];
        $t->same('file-attachment-annotation', $hidden['source']);
        $t->same('hidden-review.json', $hidden['filename']);
        $t->same('Supplement', $hidden['relationship']);
        $t->same('supplemental_representation', $hidden['relationship_role']);
        $t->same(10, $hidden['annotation_object_id']);
        $t->same(38, $hidden['annotation_flags']);
        $t->same(['hidden', 'print', 'no_view'], $hidden['annotation_flag_names']);
        $t->same('hidden', $hidden['annotation_visibility']);
        $t->same(false, $hidden['annotation_visible']);
        $t->same(true, $hidden['annotation_hidden']);
        $t->same(true, $hidden['annotation_printable']);
        $t->same(true, $hidden['annotation_no_view']);
        $t->same('PushPin', $hidden['annotation_icon']);
        $t->same('standard_file_attachment_icon', $hidden['annotation_icon_status']);
        $t->same($hiddenChecksum, $hidden['checksum_hex']);
        $t->same(true, $hidden['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $source));
        $t->same(false, array_key_exists('bytes', $hidden));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($encoded) && !str_contains($encoded, $sourcePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $hiddenPayload));
    },
];
