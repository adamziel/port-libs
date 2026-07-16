<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$metadataCatalogOutlineAssociatedSecurityBundlePdf = static function (): array {
    $rootXmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Associated Security Bundle Title</rdf:li></rdf:Alt></dc:title>'
        . '<xmp:CreateDate>2026-06-02T22:08:00Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';

    $rootXmpStream = gzcompress($rootXmp);
    $profileBytes = 'Associated security bundle PDF/A profile bytes';
    $profileStream = gzcompress($profileBytes);
    if (!is_string($rootXmpStream) || !is_string($profileStream)) {
        throw new RuntimeException('Unable to compress associated security bundle fixture streams.');
    }

    $catalogPayload = '<wp-export><post id="catalog-security-bundle"/></wp-export>';
    $targetPayload = '<wp-page-target id="outline-security-associated"/>';
    $catalogChecksum = strtoupper(hash('md5', $catalogPayload));
    $targetChecksum = strtoupper(hash('md5', $targetPayload));
    $introContent = 'BT /F1 12 Tf 72 720 Td (Associated security intro body) Tj ET';
    $targetContent = 'BT /F1 12 Tf 72 720 Td (Associated security target body) Tj ET';
    $signaturePayload = 'ASSOCIATED_SECURITY_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
    $signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R /OutputIntents [9 0 R] /AF [10 0 R] /Outlines 40 0 R /Names << /Dests 50 0 R >> /OpenAction 60 0 R /AcroForm 80 0 R /Perms << /DocMDP 90 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 32 0 R /AF [30 0 R] /Dur 5 /Trans 16 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($rootXmpStream) . " >>\nstream\n{$rootXmpStream}\nendstream\nendobj\n"
        . "7 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($profileStream) . " >>\nstream\n{$profileStream}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Associated Security PDF/A) /Info (Security bundle output intent) /DestOutputProfile 7 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (catalog-security-source.xml) /Desc (Catalog associated security source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($catalogPayload) . " /CheckSum <{$catalogChecksum}> /ModDate (D:20260602220800Z) >> /Length " . strlen($catalogPayload) . " >>\nstream\n{$catalogPayload}\nendstream\nendobj\n"
        . "16 0 obj\n<< /S /Dissolve /D .5 >>\nendobj\n"
        . "30 0 obj\n<< /Type /Filespec /F (outline-security-target.xml) /Desc (Outline security target source) /AFRelationship /Supplement /EF << /F 33 0 R >> >>\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "32 0 obj\n<< /Length " . strlen($targetContent) . " >>\nstream\n{$targetContent}\nendstream\nendobj\n"
        . "33 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($targetPayload) . " /CheckSum <{$targetChecksum}> >> /Length " . strlen($targetPayload) . " >>\nstream\n{$targetPayload}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Outlines /First 41 0 R /Count 1 >>\nendobj\n"
        . "41 0 obj\n<< /Title (Associated Security Outline) /Parent 40 0 R /A 61 0 R >>\nendobj\n"
        . "50 0 obj\n<< /Names [(BundleTarget) [4 0 R /FitH 710]] >>\nendobj\n"
        . "60 0 obj\n<< /S /GoTo /D /BundleTarget /Next [62 0 R] >>\nendobj\n"
        . "61 0 obj\n<< /S /GoTo /D /BundleTarget /Next [63 0 R 64 0 R] >>\nendobj\n"
        . "62 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden open associated review'\\)) >>\nendobj\n"
        . "63 0 obj\n<< /S /URI /URI (https://example.com/associated-outline-review) >>\nendobj\n"
        . "64 0 obj\n<< /S /Launch /F (associated-outline-helper.exe) /Win << /F (associated-outline-helper.exe) /O (open) >> >>\nendobj\n"
        . "80 0 obj\n<< /Fields [81 0 R] /SigFlags 3 >>\nendobj\n"
        . "81 0 obj\n<< /FT /Sig /T (approval.associatedSecurity) /V 90 0 R >>\nendobj\n"
        . "90 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Associated Security Reviewer) /M (D:20260602220800Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [<< /Type /SigRef /TransformMethod /DocMDP /Data 1 0 R /TransformParams << /Type /TransformParams /P 2 /V /1.2 >> >>] >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    $gapStart = strpos($pdf, $signatureContentsToken);
    if ($gapStart === false) {
        throw new RuntimeException('Unable to locate signature contents token in focused fixture.');
    }

    $gapEnd = $gapStart + strlen($signatureContentsToken);
    $pdf = strtr($pdf, [
        'AAAAAAAAAA' => sprintf('%010d', $gapStart),
        'BBBBBBBBBB' => sprintf('%010d', $gapEnd),
        'CCCCCCCCCC' => sprintf('%010d', strlen($pdf) - $gapEnd),
    ]);

    return [$pdf, $catalogPayload, $targetPayload, $profileBytes, $signaturePayload];
};

return [
    'threads catalog metadata and outline target associated files into security action review' => static function (
        TestRunner $t
    ) use ($metadataCatalogOutlineAssociatedSecurityBundlePdf): void {
        [$pdf, $catalogPayload, $targetPayload, $profileBytes, $signaturePayload] = $metadataCatalogOutlineAssociatedSecurityBundlePdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $actionReview = $report['document_action_security_review'];
        $openActionSecurity = $actionReview['cert_permission_open_action_review'];
        $outlineSecurity = $actionReview['outline_action_security_review'];
        $actions = $actionReview['actions'];
        $openActionTargetReview = $navigation['open_action_review_actions'][0]['destination_action_target_page_review']
            ?? $navigation['open_action_review_actions'][0]['target_page_review']
            ?? [];

        $t->same('Associated Security Bundle Title', $metadata['title']);
        $t->same(['Associated Security PDF/A'], $metadata['pdfa']['output_condition_identifiers']);
        $t->same('catalog-security-source.xml', $metadata['pdfa_associated_files']['filenames'][0]);
        $t->same('original_source', $metadata['pdfa_associated_files']['relationship_roles'][0]);
        $t->same(true, $metadata['pdfa_associated_files']['entries'][0]['payload']['checksum_matches']);

        $t->same(['outline', 'outline_actions', 'open_action', 'page_presentations', 'page_review'], $navigation['source']);
        $t->same('Associated Security Outline', $navigation['outline_action_review_actions'][0]['outline_title']);
        $t->same('outline-security-target.xml', $navigation['outline_action_review_actions'][0]['destination_action_target_page_review']['page_associated_files'][0]['filename']);
        $t->same('outline-security-target.xml', $openActionTargetReview['page_associated_files'][0]['filename']);

        $t->same('pdf_document_action_security_review', $actionReview['source']);
        $t->same(5, $actionReview['action_count']);
        $t->same(2, $actionReview['open_action_count']);
        $t->same(3, $actionReview['outline_action_count']);
        $t->same(['GoTo', 'JavaScript', 'GoTo', 'URI', 'Launch'], array_column($actions, 'action_type'));
        $t->same(['catalog_open_action', 'catalog_open_action', 'outline_action', 'outline_action', 'outline_action'], array_column($actions, 'source'));
        $t->same(['signed_signature_present', 'signature_reference_transforms_present', 'unsafe_pdf_actions_present', 'launch_actions_present'], $report['review_reasons']);

        $t->same(1, $openActionSecurity['destination_action_target_page_associated_file_count']);
        $t->same(['outline-security-target.xml'], $openActionSecurity['destination_action_target_page_associated_file_filenames']);
        $t->same(['Supplement'], $openActionSecurity['destination_action_target_page_associated_file_relationships']);
        $t->same(['checksum_matched'], $openActionSecurity['destination_action_target_page_associated_file_checksum_statuses']);

        $t->same(1, $outlineSecurity['destination_action_target_page_associated_file_count']);
        $t->same(['outline-security-target.xml'], $outlineSecurity['destination_action_target_page_associated_file_filenames']);
        $t->same(['Supplement'], $outlineSecurity['destination_action_target_page_associated_file_relationships']);
        $t->same(['checksum_matched'], $outlineSecurity['destination_action_target_page_associated_file_checksum_statuses']);

        foreach ($actions as $action) {
            $t->same(1, $action['destination_action_target_page_associated_file_count']);
            $t->same(['outline-security-target.xml'], $action['destination_action_target_page_associated_file_filenames']);
            $t->same(['Supplement'], $action['destination_action_target_page_associated_file_relationships']);
            $t->same(['checksum_matched'], $action['destination_action_target_page_associated_file_checksum_statuses']);
            $t->same('outline-security-target.xml', $action['destination_action_target_page_associated_files'][0]['filename']);
            $t->same('Supplement', $action['destination_action_target_page_associated_files'][0]['relationship']);
            $t->same(hash('sha256', $targetPayload), $action['destination_action_target_page_associated_files'][0]['content_sha256']);
            $t->same(true, $action['destination_action_target_page_associated_files'][0]['checksum_matches']);
            $t->same(false, array_key_exists('content', $action['destination_action_target_page_associated_files'][0]));
            $t->same(false, $action['executes_action']);
        }

        $encodedReport = json_encode($report, JSON_UNESCAPED_SLASHES);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same("Associated security intro body\nAssociated security target body", $plainText);
        $t->true(is_string($encodedReport) && !str_contains($encodedReport, $targetPayload));
        $t->true(is_string($encodedReport) && !str_contains($encodedReport, $catalogPayload));
        $t->true(is_string($encodedReport) && !str_contains($encodedReport, $signaturePayload));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, $profileBytes));
        $t->true(!str_contains($plainText, 'catalog-security-source.xml'));
        $t->true(!str_contains($plainText, 'outline-security-target.xml'));
        $t->true(!str_contains($plainText, 'hidden open associated review'));
        $t->true(!str_contains($plainText, 'associated-outline-helper.exe'));
    },
];
