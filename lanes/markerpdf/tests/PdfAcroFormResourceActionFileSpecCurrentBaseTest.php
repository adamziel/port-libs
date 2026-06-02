<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$fieldsByName = static function (array $fields): array {
    $indexed = [];
    foreach ($fields as $field) {
        $indexed[$field['name']] = $field;
    }

    return $indexed;
};

$acroFormResourceActionFileSpecPdf = static function (): array {
    $utf16Hex = static function (string $value): string {
        $encoded = iconv('UTF-8', 'UTF-16BE', $value);
        if (!is_string($encoded)) {
            throw new RuntimeException('Unable to encode UTF-16BE fixture string.');
        }

        return '<FEFF' . strtoupper(bin2hex($encoded)) . '>';
    };

    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm FileSpec resource body) Tj ET';
    $submitPayload = 'Submitted payload blocked';
    $relatedPayload = 'Related stylesheet blocked';
    $importPayload = 'Imported FDF payload blocked';
    $launchPayload = 'Launch helper payload blocked';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R 20 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R 14 0 R 18 0 R] /DA (/Body 10 Tf 0 0 0 rg) /DR 30 0 R >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (article.title) /V (Review value) /DA (/Body 10 Tf 0 0 0 rg) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 360 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Btn /T (actions.submit_filespec) /Ff 65536 /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 190 624] /P 3 0 R /F 4 /A << /S /SubmitForm /F 40 0 R /Fields [6 0 R] /Flags 36 >> >>\nendobj\n"
        . "14 0 obj\n<< /FT /Btn /T (actions.import_filespec) /Ff 65536 /Kids [16 0 R] /AA << /U << /S /ImportData /F 50 0 R >> >> >>\nendobj\n"
        . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [200 600 318 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "18 0 obj\n<< /FT /Btn /T (actions.launch_filespec) /Ff 65536 /Kids [20 0 R] >>\nendobj\n"
        . "20 0 obj\n<< /Subtype /Widget /Parent 18 0 R /Rect [328 600 446 624] /P 3 0 R /F 4 /A << /S /Launch /F 60 0 R /Win << /F 61 0 R /O (open) /P (--review-only) /D (C:\\\\blocked) >> /NewWindow true >> >>\nendobj\n"
        . "30 0 obj\n<< /Font << /Body 31 0 R >> >>\nendobj\n"
        . "31 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "40 0 obj\n<< /Type /Filespec /FS /URL /F (https://example.test/fallback-export.fdf) /UF " . $utf16Hex('https://example.test/current-export.xfdf') . " /Desc (Current submit endpoint) /AFRelationship /FormData /EF << /F 41 0 R >> /RF << /F [(review-style.css) 42 0 R] >> >>\nendobj\n"
        . "41 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fvnd.adobe.xfdf /Params << /Size " . strlen($submitPayload) . " /CheckSum (submit-checksum) /ModDate (D:20260602194000Z) >> /Length " . strlen($submitPayload) . " >>\nstream\n{$submitPayload}\nendstream\nendobj\n"
        . "42 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcss /Params << /Size " . strlen($relatedPayload) . " /CheckSum (related-checksum) >> /Length " . strlen($relatedPayload) . " >>\nstream\n{$relatedPayload}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Filespec /F (review-import.fdf) /Desc (ImportData source) /AFRelationship /Data /EF << /F 51 0 R >> >>\nendobj\n"
        . "51 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fvnd.fdf /Params << /Size " . strlen($importPayload) . " /CheckSum (import-checksum) >> /Length " . strlen($importPayload) . " >>\nstream\n{$importPayload}\nendstream\nendobj\n"
        . "60 0 obj\n<< /Type /Filespec /F (fallback-launch.exe) /UF (launch-current.exe) /Desc (Launch helper) /EF << /F 62 0 R >> >>\nendobj\n"
        . "61 0 obj\n<< /Type /Filespec /F (win-fallback.exe) /UF (win-current.exe) >>\nendobj\n"
        . "62 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Foctet-stream /Params << /Size " . strlen($launchPayload) . " /CheckSum (launch-checksum) >> /Length " . strlen($launchPayload) . " >>\nstream\n{$launchPayload}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $submitPayload, $relatedPayload, $importPayload, $launchPayload];
};

return [
    'keeps AcroForm resource action FileSpec dictionaries review only at current base' => static function (TestRunner $t) use ($acroFormResourceActionFileSpecPdf, $fieldsByName): void {
        [$pdf, $submitPayload, $relatedPayload, $importPayload, $launchPayload] = $acroFormResourceActionFileSpecPdf();

        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

        $title = $fields['article.title'];
        $submit = $fields['actions.submit_filespec']['widgets'][0]['actions'][0];
        $import = $fields['actions.import_filespec']['actions'][0];
        $launch = $fields['actions.launch_filespec']['widgets'][0]['actions'][0];

        $t->same('acroform', $form['default_resources']['source']);
        $t->same(30, $form['default_resources']['object']);
        $t->same('Helvetica', $form['default_resources']['fonts']['Body']['base_font']);
        $t->same('Body', $title['default_appearance']['font_resource']);
        $t->same(true, $title['default_appearance']['font_resource_resolved']);

        $t->same('SubmitForm', $submit['action_type']);
        $t->same('https://example.test/current-export.xfdf', $submit['target']);
        $t->same('https', $submit['target_scheme']);
        $t->same(false, $submit['executes_action']);
        $t->same(false, $submit['submits_pdf_on_import']);
        $t->same(false, $submit['field_value_review']['payload_text_exposed']);
        $submitSpec = $submit['file_spec'];
        $t->same('acroform_action_filespec_review_boundary', $submitSpec['source']);
        $t->same(40, $submitSpec['file_spec_object']);
        $t->same('Filespec', $submitSpec['type']);
        $t->same('URL', $submitSpec['file_system']);
        $t->same('https://example.test/current-export.xfdf', $submitSpec['filename']);
        $t->same('https://example.test/fallback-export.fdf', $submitSpec['platform_filenames']['F']);
        $t->same('Current submit endpoint', $submitSpec['description']);
        $t->same('FormData', $submitSpec['relationship']);
        $t->same(1, $submitSpec['embedded_file_count']);
        $t->same([41], $submitSpec['embedded_file_objects']);
        $t->same(false, $submitSpec['embedded_payload_text_exposed']);
        $submitEmbedded = $submitSpec['embedded_files'][0];
        $t->same('F', $submitEmbedded['key']);
        $t->same(41, $submitEmbedded['object']);
        $t->same('application/vnd.adobe.xfdf', $submitEmbedded['subtype']);
        $t->same(strlen($submitPayload), $submitEmbedded['decoded_length_bytes']);
        $t->same(hash('sha256', $submitPayload), $submitEmbedded['decoded_sha256']);
        $t->same('submit-checksum', $submitEmbedded['params']['check_sum']);
        $t->same('D:20260602194000Z', $submitEmbedded['params']['mod_date']);
        $t->same(false, $submitEmbedded['content_returned']);
        $t->same(1, $submitSpec['related_file_count']);
        $related = $submitSpec['related_files'][0];
        $t->same('F', $related['key']);
        $t->same('review-style.css', $related['filename']);
        $t->same(42, $related['embedded_file']['object']);
        $t->same(hash('sha256', $relatedPayload), $related['embedded_file']['decoded_sha256']);

        $t->same('ImportData', $import['action_type']);
        $t->same('review-import.fdf', $import['target']);
        $t->same(false, $import['imports_form_data']);
        $t->same('Data', $import['file_spec']['relationship']);
        $t->same(51, $import['file_spec']['embedded_file_objects'][0]);
        $t->same(hash('sha256', $importPayload), $import['file_spec']['embedded_files'][0]['decoded_sha256']);

        $t->same('Launch', $launch['action_type']);
        $t->same('launch-current.exe', $launch['target']);
        $t->same('Win', $launch['target_platform']);
        $t->same('open', $launch['operation']);
        $t->same('--review-only', $launch['parameters']);
        $t->same('C:\\blocked', $launch['default_directory']);
        $t->same(true, $launch['new_window']);
        $t->same(false, $launch['executes_action']);
        $t->same('launch-current.exe', $launch['file_spec']['filename']);
        $t->same(62, $launch['file_spec']['embedded_file_objects'][0]);
        $t->same(hash('sha256', $launchPayload), $launch['file_spec']['embedded_files'][0]['decoded_sha256']);
        $t->same('win-current.exe', $launch['platform_file_spec']['filename']);
        $t->same(61, $launch['platform_file_spec']['file_spec_object']);

        $t->same('Visible AcroForm FileSpec resource body', $visibleText);
        foreach ([
            'current-export.xfdf',
            'review-import.fdf',
            'launch-current.exe',
            'win-current.exe',
            $submitPayload,
            $relatedPayload,
            $importPayload,
            $launchPayload,
        ] as $blockedText) {
            $t->same(false, str_contains($visibleText, $blockedText));
        }
    },
];
