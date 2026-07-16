<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineStructureNavigationPdf = static function (): array {
    $pageText = 'BT /F1 12 Tf /ChapterTitle << /MCID 0 >> BDC 72 720 Td (Visible outline structure navigation text) Tj EMC ET';
    $payload = '<wp-export><post id="outline-se-navigation"/></wp-export>';
    $checksum = strtoupper(hash('md5', $payload));

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /MarkInfo << /Marked true >> /StructTreeRoot 50 0 R /Outlines 40 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 7 0 R >> >> /Contents 30 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Outlines /First 41 0 R /Last 41 0 R /Count 1 >>\nendobj\n"
        . "41 0 obj\n<< /Title (Navigation outline SE review) /Parent 40 0 R /A 42 0 R /SE 60 0 R /F 3 >>\nendobj\n"
        . "42 0 obj\n<< /S /GoTo /D [3 0 R /FitH 720] /Next 43 0 R >>\nendobj\n"
        . "43 0 obj\n<< /S /URI /URI (https://example.com/outline-se-navigation-review) >>\nendobj\n"
        . "50 0 obj\n<< /Type /StructTreeRoot /RoleMap << /ChapterTitle /H1 >> /ParentTree 55 0 R /K [60 0 R] >>\nendobj\n"
        . "55 0 obj\n<< /Nums [0 [60 0 R]] >>\nendobj\n"
        . "60 0 obj\n<< /Type /StructElem /S /ChapterTitle /P 50 0 R /Pg 3 0 R /Lang (en-GB) /T (Navigation Outline Structure Title) /ID (outline-navigation-se-1) /Alt (Navigation Outline Alt Summary) /AF [70 0 R] /K << /Type /MCR /Pg 3 0 R /MCID 0 >> >>\nendobj\n"
        . "70 0 obj\n<< /Type /Filespec /F (outline-navigation-source.xml) /Desc (Navigation outline source payload) /AFRelationship /Source /EF << /F 71 0 R >> >>\nendobj\n"
        . "71 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $payload];
};

return [
    'carries outline SE structure metadata into navigation review rows and action rows' => static function (
        TestRunner $t
    ) use ($outlineStructureNavigationPdf): void {
        [$pdf] = $outlineStructureNavigationPdf();

        $extractor = new PdfOutlineExtractor();
        $metadata = $extractor->getNavigationReviewMetadata($pdf);
        $directRows = $extractor->getOutlineStructureDestinationPageContext($pdf);
        $outline = $metadata['outline'][0] ?? [];
        $structure = $outline['structure_element'] ?? [];
        $actions = $metadata['outline_action_review_actions'] ?? [];

        $t->true(in_array('outline', $metadata['source'], true));
        $t->true(in_array('outline_actions', $metadata['source'], true));
        $t->true(in_array('tagged_content', $metadata['source'], true));
        $t->same('Navigation outline SE review', $outline['title'] ?? null);
        $t->same(41, $outline['outline_object'] ?? null);
        $t->same(60, $outline['structure_element_object'] ?? null);
        $t->same('ChapterTitle', $outline['structure_element_raw_role'] ?? null);
        $t->same('H1', $outline['structure_element_role'] ?? null);
        $t->same([0], $outline['structure_element_mcids'] ?? []);
        $t->same(1, $outline['structure_element_associated_file_count'] ?? null);
        $t->same('outline_item_structure_element', $structure['source'] ?? null);
        $t->same(true, $structure['review_only'] ?? null);
        $t->same(false, $structure['visible_text_source'] ?? null);
        $t->same(false, $structure['payload_included'] ?? null);
        $t->same('Navigation Outline Structure Title', $structure['title'] ?? null);
        $t->same('Navigation Outline Alt Summary', $structure['alternate_text'] ?? null);
        $t->same('outline-navigation-source.xml', $structure['associated_files'][0]['filename'] ?? null);
        $t->same(true, $structure['associated_files'][0]['checksum_matches'] ?? null);
        $t->same(1, count($directRows));
        $t->same(41, $directRows[0]['outline_object'] ?? null);
        $t->same(60, $directRows[0]['structure_element_object'] ?? null);
        $t->same('H1', $directRows[0]['structure_element_role'] ?? null);
        $t->same([0], $directRows[0]['structure_element_mcids'] ?? []);

        $t->same(['GoTo', 'URI'], array_column($actions, 'action_type'));
        $t->same(['local-destination', 'review-uri'], array_column($actions, 'safety'));
        foreach ($actions as $action) {
            $t->same(41, $action['outline_object'] ?? null);
            $t->same(60, $action['outline_structure_element_object'] ?? null);
            $t->same('H1', $action['outline_structure_element_role'] ?? null);
            $t->same([0], $action['outline_structure_element_mcids'] ?? []);
            $t->same(1, $action['outline_structure_element_associated_file_count'] ?? null);
            $t->same('outline_item_structure_element', $action['outline_structure_element']['source'] ?? null);
            $t->same(false, $action['outline_structure_element']['payload_included'] ?? null);
        }
        $t->same('https://example.com/outline-se-navigation-review', $actions[1]['uri'] ?? null);
        $t->same(false, $actions[1]['executes_on_import'] ?? null);
    },
    'keeps navigation outline SE metadata and associated payload out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($outlineStructureNavigationPdf): void {
        [$pdf, $payload] = $outlineStructureNavigationPdf();

        $metadata = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same('Visible outline structure navigation text', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, $payload));
        $t->true(!str_contains($plainText, 'Navigation outline SE review'));
        $t->true(!str_contains($plainText, 'Navigation Outline Structure Title'));
        $t->true(!str_contains($plainText, 'Navigation Outline Alt Summary'));
        $t->true(!str_contains($plainText, 'outline-navigation-source.xml'));
        $t->true(!str_contains($plainText, 'outline-se-navigation-review'));
        $t->true(!str_contains($plainText, '<wp-export>'));
    },
];
