<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$securityAcroFormDssActionAttachmentBundlePdf = static function (): array {
    $visible = 'BT /F1 12 Tf 72 720 Td (Security AcroForm DSS attachment bundle import) Tj ET';
    $signaturePayload = 'ACROFORM_DSS_ACTION_ATTACHMENT_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
    $signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';
    $vriKey = strtoupper(hash('sha1', $signaturePayload));
    $submitPayload = 'SUBMIT_XFDF_ATTACHMENT_BYTES_SHOULD_NOT_LEAK';
    $relatedPayload = 'RELATED_STYLE_ATTACHMENT_BYTES_SHOULD_NOT_LEAK';
    $importPayload = 'IMPORT_FDF_ATTACHMENT_BYTES_SHOULD_NOT_LEAK';
    $launchPayload = 'LAUNCH_HELPER_ATTACHMENT_BYTES_SHOULD_NOT_LEAK';
    $catalogPayload = '<wp-export><post id="signed-bundle"/></wp-export>';
    $globalCertPayload = 'GLOBAL_DSS_CERT_BYTES_SHOULD_NOT_LEAK';
    $vriCertPayload = 'VRI_DSS_CERT_BYTES_SHOULD_NOT_LEAK';
    $ocspPayload = 'DSS_OCSP_BYTES_SHOULD_NOT_LEAK';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /DSS 60 0 R /AF [90 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 11 0 R 13 0 R 15 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 9 0 R 10 0 R 12 0 R 14 0 R] /SigFlags 3 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Sig /T (approval.bundle) /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "9 0 obj\n<< /FT /Tx /T (article.title) /V (Signed bundle title) >>\nendobj\n"
        . "10 0 obj\n<< /FT /Btn /T (actions.submit_bundle) /Ff 65536 /Kids [11 0 R] /AA << /V << /S /SubmitForm /F 40 0 R /Fields [9 0 R] /Flags 36 >> >> >>\nendobj\n"
        . "11 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 590 220 614] /P 3 0 R /F 4 >>\nendobj\n"
        . "12 0 obj\n<< /FT /Btn /T (actions.import_bundle) /Ff 65536 /Kids [13 0 R] /AA << /U << /S /ImportData /F 50 0 R >> >> >>\nendobj\n"
        . "13 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [230 590 378 614] /P 3 0 R /F 4 >>\nendobj\n"
        . "14 0 obj\n<< /FT /Btn /T (actions.launch_bundle) /Ff 65536 /Kids [15 0 R] /AA << /K << /S /Launch /F 70 0 R /Win << /F 71 0 R /O (open) /P (--blocked) >> /NewWindow true >> >> >>\nendobj\n"
        . "15 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [386 590 534 614] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Bundle Reviewer) /M (D:20260602215714Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [31 0 R 33 0 R] >>\nendobj\n"
        . "31 0 obj\n<< /Type /SigRef /TransformMethod /FieldMDP /Data 5 0 R /TransformParams 32 0 R >>\nendobj\n"
        . "32 0 obj\n<< /Type /TransformParams /V /1.2 /Action /Include /Fields [9 0 R (actions.submit_bundle) (actions.import_bundle) (actions.launch_bundle)] >>\nendobj\n"
        . "33 0 obj\n<< /Type /SigRef /TransformMethod /UR3 /Data 1 0 R /TransformParams 34 0 R >>\nendobj\n"
        . "34 0 obj\n<< /Type /TransformParams /V /2.2 /Form [/FillIn /Export] /EF [/Create /Import] /Msg (Attachment actions remain review only) >>\nendobj\n"
        . "40 0 obj\n<< /Type /Filespec /FS /URL /F (https://example.test/export.fdf) /Desc (Submit endpoint attachment) /AFRelationship /FormData /EF << /F 41 0 R >> /RF << /F [(submit-style.css) 42 0 R] >> >>\nendobj\n"
        . "41 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fvnd.adobe.xfdf /Params << /Size " . strlen($submitPayload) . " /CheckSum (submit-md5) >> /Length " . strlen($submitPayload) . " >>\nstream\n{$submitPayload}\nendstream\nendobj\n"
        . "42 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcss /Params << /Size " . strlen($relatedPayload) . " /CheckSum (related-md5) >> /Length " . strlen($relatedPayload) . " >>\nstream\n{$relatedPayload}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Filespec /F (import-review.fdf) /Desc (ImportData attachment) /AFRelationship /Data /EF << /F 51 0 R >> >>\nendobj\n"
        . "51 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fvnd.fdf /Params << /Size " . strlen($importPayload) . " /CheckSum (import-md5) >> /Length " . strlen($importPayload) . " >>\nstream\n{$importPayload}\nendstream\nendobj\n"
        . "60 0 obj\n<< /Type /DSS /Certs [62 0 R] /OCSPs [64 0 R] /VRI << /{$vriKey} 61 0 R >> >>\nendobj\n"
        . "61 0 obj\n<< /Type /VRI /Cert [63 0 R] /OCSP [64 0 R] /TU (D:20260602215714Z) >>\nendobj\n"
        . "62 0 obj\n<< /Length " . strlen($globalCertPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$globalCertPayload}\nendstream\nendobj\n"
        . "63 0 obj\n<< /Length " . strlen($vriCertPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$vriCertPayload}\nendstream\nendobj\n"
        . "64 0 obj\n<< /Length " . strlen($ocspPayload) . " /Subtype /application#2Focsp-response >>\nstream\n{$ocspPayload}\nendstream\nendobj\n"
        . "70 0 obj\n<< /Type /Filespec /F (fallback-launch.exe) /UF (launch-current.exe) /Desc (Launch helper attachment) /AFRelationship /Data /EF << /F 72 0 R >> >>\nendobj\n"
        . "71 0 obj\n<< /Type /Filespec /F (win-fallback.exe) /UF (win-current.exe) /Desc (Platform launch helper) >>\nendobj\n"
        . "72 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Foctet-stream /Params << /Size " . strlen($launchPayload) . " /CheckSum (launch-md5) >> /Length " . strlen($launchPayload) . " >>\nstream\n{$launchPayload}\nendstream\nendobj\n"
        . "90 0 obj\n<< /Type /Filespec /F (signed-source.xml) /Desc (Signed source attachment) /AFRelationship /Source /EF << /F 91 0 R >> >>\nendobj\n"
        . "91 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($catalogPayload) . " /CheckSum <" . strtoupper(hash('md5', $catalogPayload)) . "> >> /Length " . strlen($catalogPayload) . " >>\nstream\n{$catalogPayload}\nendstream\nendobj\n"
        . "%%EOF";

    $gapStart = strpos($pdf, $signatureContentsToken);
    if ($gapStart === false) {
        throw new RuntimeException('Unable to locate signature contents token in focused bundle fixture.');
    }

    $gapEnd = $gapStart + strlen($signatureContentsToken);
    $pdf = strtr($pdf, [
        'AAAAAAAAAA' => sprintf('%010d', $gapStart),
        'BBBBBBBBBB' => sprintf('%010d', $gapEnd),
        'CCCCCCCCCC' => sprintf('%010d', strlen($pdf) - $gapEnd),
    ]);

    return [
        $pdf,
        $signaturePayload,
        $submitPayload,
        $relatedPayload,
        $importPayload,
        $launchPayload,
        $catalogPayload,
        $globalCertPayload,
        $vriCertPayload,
        $ocspPayload,
    ];
};

return [
    'bundles AcroForm action FileSpecs with DSS and signature permission review metadata' => static function (
        TestRunner $t
    ) use ($securityAcroFormDssActionAttachmentBundlePdf): void {
        [
            $pdf,
            $signaturePayload,
            $submitPayload,
            $relatedPayload,
            $importPayload,
            $launchPayload,
            $catalogPayload,
            $globalCertPayload,
            $vriCertPayload,
            $ocspPayload,
        ] = $securityAcroFormDssActionAttachmentBundlePdf();

        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $actionReview = $report['document_action_security_review'];
        $fileSpecReview = $actionReview['action_file_spec_security_review'];
        $actions = $actionReview['actions'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('Security AcroForm DSS attachment bundle import', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('review_required_signature_metadata', $report['import_decision']);
        $t->same([
            'signed_signature_present',
            'signature_reference_transforms_present',
            'document_security_store_present',
            'acroform_actions_present',
            'form_data_actions_present',
            'unsafe_pdf_actions_present',
            'launch_actions_present',
        ], $report['review_reasons']);
        $t->same(['signature_validation', 'signing', 'revocation_check', 'trust_chain_validation', 'pdf_action_execution', 'form_action_execution'], $report['blocked_operations']);

        $t->same(3, $actionReview['action_count']);
        $t->same(3, $actionReview['acroform_field_action_count']);
        $t->same(0, $actionReview['acroform_widget_action_count']);
        $t->same(['SubmitForm', 'ImportData', 'Launch'], $actionReview['action_types']);
        $t->same(['submit-form-action-review', 'import-data-action-review', 'launch-action-review'], $actionReview['safety_labels']);
        $t->same(2, $actionReview['signature_permission_transform_count']);
        $t->same(['FieldMDP', 'UR3'], $actionReview['signature_permission_transform_review']['methods']);
        $t->same(['form', 'embedded_files'], $actionReview['usage_right_categories']);
        $t->same(['Create', 'Import'], $actionReview['signature_permission_transform_review']['usage_rights']['embedded_files']);
        $t->same(2, $actionReview['dss_certificate_count']);
        $t->same([62, 63], $actionReview['dss_certificate_review']['certificate_objects']);
        $t->same([hash('sha256', $globalCertPayload), hash('sha256', $vriCertPayload)], $actionReview['dss_certificate_hashes']);
        $t->same(1, $actionReview['dss_vri_signature_match_count']);

        $t->same('document_action_filespec_security_review', $fileSpecReview['source']);
        $t->same(true, $fileSpecReview['present']);
        $t->same(4, $fileSpecReview['file_spec_count']);
        $t->same([40, 50, 70, 71], $fileSpecReview['file_spec_objects']);
        $t->same(['https://example.test/export.fdf', 'import-review.fdf', 'launch-current.exe', 'win-current.exe'], $fileSpecReview['filenames']);
        $t->same(['FormData', 'Data'], $fileSpecReview['relationships']);
        $t->same(['target_file_spec', 'platform_file_spec'], $fileSpecReview['scopes']);
        $t->same(['acroform_field_action'], $fileSpecReview['action_sources']);
        $t->same(['SubmitForm', 'ImportData', 'Launch'], $fileSpecReview['action_types']);
        $t->same(3, $fileSpecReview['embedded_file_count']);
        $t->same([41, 51, 72], $fileSpecReview['embedded_file_objects']);
        $t->same([hash('sha256', $submitPayload), hash('sha256', $importPayload), hash('sha256', $launchPayload)], $fileSpecReview['embedded_file_hashes']);
        $t->same(1, $fileSpecReview['related_file_count']);
        $t->same([42], $fileSpecReview['related_file_objects']);
        $t->same([hash('sha256', $relatedPayload)], $fileSpecReview['related_file_hashes']);
        $t->same(false, $fileSpecReview['payload_text_exposed']);
        $t->same(false, $fileSpecReview['executes_external_file_launch']);

        $submit = $actions[0];
        $t->same('SubmitForm', $submit['action_type']);
        $t->same('actions.submit_bundle', $submit['field_name']);
        $t->same('https://example.test/export.fdf', $submit['action_file_spec_filename']);
        $t->same('FormData', $submit['action_file_spec_relationship']);
        $t->same([41], $submit['action_embedded_file_objects']);
        $t->same([hash('sha256', $submitPayload)], $submit['action_embedded_file_hashes']);
        $t->same([42], $submit['action_file_spec']['related_file_objects']);
        $t->same([hash('sha256', $relatedPayload)], $submit['action_file_spec']['related_file_hashes']);

        $import = $actions[1];
        $t->same('ImportData', $import['action_type']);
        $t->same('actions.import_bundle', $import['field_name']);
        $t->same('import-review.fdf', $import['action_file_spec_filename']);
        $t->same([51], $import['action_embedded_file_objects']);

        $launch = $actions[2];
        $t->same('Launch', $launch['action_type']);
        $t->same('actions.launch_bundle', $launch['field_name']);
        $t->same(2, $launch['action_file_spec_count']);
        $t->same('launch-current.exe', $launch['action_file_specs'][0]['filename']);
        $t->same('win-current.exe', $launch['action_file_specs'][1]['filename']);
        $t->same([72], $launch['action_embedded_file_objects']);
        $t->same([hash('sha256', $launchPayload)], $launch['action_embedded_file_hashes']);

        foreach ($actions as $action) {
            $t->same(true, $action['action_file_spec_present']);
            $t->same(true, $action['action_file_spec_review_only']);
            $t->same(false, $action['action_file_spec_payload_text_exposed']);
            $t->same('covered_by_all_signature_byte_ranges', $action['signature_byte_range_coverage_status']);
            $t->same(false, $action['executes_action']);
            $t->same(false, $action['executes_rights_enforcement']);
            $t->same(false, $action['executes_trust_chain_validation']);
        }

        $context = $actionReview['dss_certificate_action_permission_review'];
        $t->same(4, $context['action_file_spec_count']);
        $t->same(3, $context['action_embedded_file_count']);
        $t->same([41, 51, 72], $context['action_embedded_file_objects']);
        $t->same(false, $context['executes_pdf_actions']);
        $t->same(false, $context['executes_trust_chain_validation']);

        $t->true(is_string($encoded)
            && !str_contains($encoded, $signaturePayload)
            && !str_contains($encoded, strtoupper(bin2hex($signaturePayload)))
            && !str_contains($encoded, $submitPayload)
            && !str_contains($encoded, $relatedPayload)
            && !str_contains($encoded, $importPayload)
            && !str_contains($encoded, $launchPayload)
            && !str_contains($encoded, $catalogPayload)
            && !str_contains($encoded, $globalCertPayload)
            && !str_contains($encoded, $vriCertPayload)
            && !str_contains($encoded, $ocspPayload));
    },
    'keeps associated attachments and action FileSpec payloads out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($securityAcroFormDssActionAttachmentBundlePdf): void {
        [$pdf, , $submitPayload, $relatedPayload, $importPayload, $launchPayload, $catalogPayload] = $securityAcroFormDssActionAttachmentBundlePdf();

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);

        $t->same(1, count($files));
        $t->same('catalog_associated_files', $files[0]['source']);
        $t->same('signed-source.xml', $files[0]['filename']);
        $t->same('Source', $files[0]['relationship']);
        $t->same(true, $files[0]['associated_file']);
        $t->same(hash('sha256', $catalogPayload), $files[0]['content_sha256']);

        $t->same('Security AcroForm DSS attachment bundle import', $plainText);
        foreach ([
            $submitPayload,
            $relatedPayload,
            $importPayload,
            $launchPayload,
            $catalogPayload,
            'ACROFORM_DSS_ACTION_ATTACHMENT_SIGNATURE_BYTES_SHOULD_NOT_LEAK',
            'GLOBAL_DSS_CERT_BYTES_SHOULD_NOT_LEAK',
            'VRI_DSS_CERT_BYTES_SHOULD_NOT_LEAK',
            'DSS_OCSP_BYTES_SHOULD_NOT_LEAK',
            'https://example.test/export.fdf',
            'import-review.fdf',
            'launch-current.exe',
            'win-current.exe',
        ] as $hiddenText) {
            $t->true(!str_contains($plainText, $hiddenText));
        }
    },
];
