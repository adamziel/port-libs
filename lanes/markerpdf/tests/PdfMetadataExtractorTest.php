<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpPacket = static function (array $overrides = []): string {
    $title = $overrides['title'] ?? 'WordPress Import Handbook';
    $description = $overrides['description'] ?? 'Native XMP metadata for editorial review';

    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="fr-FR">Titre ignore</rdf:li><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Ada Editor</rdf:li><rdf:li>Data Liberation Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>pdf import</rdf:li><rdf:li>xmp</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>LibreOffice PDF</pdf:Producer>'
        . '<xmp:CreatorTool>WordPress Exporter</xmp:CreatorTool>'
        . '<xmp:CreateDate>2024-05-01T10:20:30Z</xmp:CreateDate>'
        . '<xmp:ModifyDate>2024-05-02T11:21:31Z</xmp:ModifyDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$pdfWithMetadata = static function (string $metadataStream, string $infoDictionary = '', bool $flateXmp = true): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Visible PDF Body) Tj ET';
    $metadataBytes = $flateXmp ? (string) gzcompress($metadataStream) : $metadataStream;
    $metadataFilter = $flateXmp ? ' /Filter /FlateDecode' : '';
    $infoObject = $infoDictionary === ''
        ? ''
        : "6 0 obj\n{$infoDictionary}\nendobj\n";
    $infoTrailer = $infoDictionary === '' ? '' : ' /Info 6 0 R';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Metadata /Subtype /XML{$metadataFilter} /Length " . strlen($metadataBytes) . " >>\nstream\n{$metadataBytes}\nendstream\nendobj\n"
        . $infoObject
        . "trailer\n<< /Root 1 0 R{$infoTrailer} >>\n%%EOF";
};

$pdfWithOutputIntent = static function (): array {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (PDF/A Ready Body) Tj ET';
    $profileBytes = "ICC profile bytes for native PDF/A import review\n";
    $compressedProfile = gzcompress($profileBytes);
    if (!is_string($compressedProfile)) {
        throw new RuntimeException('Unable to compress ICC profile fixture.');
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OutputIntents 8 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($compressedProfile) . " >>\nstream\n{$compressedProfile}\nendstream\nendobj\n"
        . "8 0 obj\n[9 0 R << /Type /OutputIntent /S /GTS_PDFX /OutputConditionIdentifier (Press Proof) /Info (Non PDF/A proof intent) >>]\nendobj\n"
        . "9 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (sRGB IEC61966-2.1) /OutputCondition (sRGB display profile) /RegistryName (http://www.color.org) /Info <FEFF005000440046002F004100200073005200470042> /DestOutputProfile 7 0 R >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $profileBytes];
};

return [
    'extracts catalog XMP metadata before WordPress import review' => static function (TestRunner $t) use ($xmpPacket, $pdfWithMetadata): void {
        $info = '<< /Title (Legacy Title) /Author (Legacy Author) /Keywords (legacy,hidden) /Creator (Legacy Tool) /Producer (Legacy Producer) >>';
        $pdf = $pdfWithMetadata($xmpPacket(), $info);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('WordPress Import Handbook', $metadata['title']);
        $t->same(['Ada Editor', 'Data Liberation Team'], $metadata['authors']);
        $t->same('Native XMP metadata for editorial review', $metadata['description']);
        $t->same(['wordpress', 'pdf import', 'xmp'], $metadata['keywords']);
        $t->same('WordPress Exporter', $metadata['creator_tool']);
        $t->same('LibreOffice PDF', $metadata['producer']);
        $t->same('2024-05-01T10:20:30Z', $metadata['created_at']);
        $t->same('Legacy Title', $metadata['info']['Title']);
        $t->same('WordPress Import Handbook', $metadata['xmp']['title']);
        $t->same('Visible PDF Body', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->true(!str_contains((new PdfTextExtractor())->extractPlainText($pdf), 'WordPress Import Handbook'));
    },
    'uses trailer Info dictionary when XMP metadata is absent' => static function (TestRunner $t): void {
        $subject = strtoupper(bin2hex("\xfe\xff\x00E\x00d\x00i\x00t\x00o\x00r\x00i\x00a\x00l\x00 \x00s\x00u\x00m\x00m\x00a\x00r\x00y"));
        $info = "<< /Title (Editor's \\(PDF\\) import\\040metadata) /Author (Site Owner; Migration Team) /Subject <{$subject}> /Keywords (wordpress, pdf;metadata) /Creator /Native#20Importer /Producer (Fixture Writer) /CreationDate (D:20240602112233Z) >>";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
            . "6 0 obj\n{$info}\nendobj\n"
            . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);

        $t->same(['info'], $metadata['source']);
        $t->same("Editor's (PDF) import metadata", $metadata['title']);
        $t->same(['Site Owner', 'Migration Team'], $metadata['authors']);
        $t->same('Editorial summary', $metadata['description']);
        $t->same(['wordpress', 'pdf', 'metadata'], $metadata['keywords']);
        $t->same('Native Importer', $metadata['creator_tool']);
        $t->same('Fixture Writer', $metadata['producer']);
        $t->same('D:20240602112233Z', $metadata['created_at']);
    },
    'extracts trailer ID array as document fingerprint metadata' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Fingerprint Body) Tj ET';
        $permanentId = "WP PDF\x00ID-A";
        $changingId = 'WP-PDF-ID-B';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Title (Fingerprint Review PDF) /Producer (Fixture Writer) >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R /ID [<00000000000000000000000000000000> <11111111111111111111111111111111>] >>\n"
            . "trailer\n<< /Root 1 0 R /Info 6 0 R /ID [(WP\\040PDF\\000ID-A) <57502d5044462d49442d42>] >>\n%%EOF";

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);

        $t->same(['info', 'trailer_id'], $metadata['source']);
        $t->same('Fingerprint Review PDF', $metadata['title']);
        $t->same('Fingerprint Body', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('trailer_id', $metadata['trailer_ids']['source']);
        $t->same(2, $metadata['trailer_ids']['id_count']);
        $t->true($metadata['trailer_ids']['changed_since_creation']);
        $t->same(bin2hex($permanentId), $metadata['trailer_ids']['permanent']['hex']);
        $t->same(strlen($permanentId), $metadata['trailer_ids']['permanent']['bytes']);
        $t->same(hash('sha256', $permanentId), $metadata['trailer_ids']['permanent']['sha256']);
        $t->same(bin2hex($changingId), $metadata['trailer_ids']['changing']['hex']);
        $t->same(strlen($changingId), $metadata['trailer_ids']['changing']['bytes']);
        $t->same(hash('sha256', $changingId), $metadata['trailer_ids']['changing']['sha256']);
        $t->same(hash('sha256', $permanentId), $metadata['document_fingerprint']);
        $t->same('trailer_id_permanent', $metadata['document_fingerprint_source']);
    },
    'ignores malformed XMP streams while preserving Info metadata fallback' => static function (TestRunner $t) use ($pdfWithMetadata): void {
        $pdf = $pdfWithMetadata(
            '<x:xmpmeta><rdf:RDF><rdf:Description><dc:title>Broken',
            '<< /Title (Fallback Import Title) /Keywords (fallback;review) >>',
            flateXmp: false
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);

        $t->same(['info'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Fallback Import Title', $metadata['title']);
        $t->same(['fallback', 'review'], $metadata['keywords']);
        $t->same('Visible PDF Body', (new PdfTextExtractor())->extractPlainText($pdf));
    },
    'extracts catalog PDF/A output intent metadata for WordPress review' => static function (TestRunner $t) use ($pdfWithOutputIntent): void {
        [$pdf, $profileBytes] = $pdfWithOutputIntent();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $text = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(['output_intents'], $metadata['source']);
        $t->same('PDF/A Ready Body', $text);
        $t->same(2, count($metadata['output_intents']));
        $t->same('GTS_PDFA1', $metadata['output_intents'][0]['subtype']);
        $t->true($metadata['output_intents'][0]['is_pdfa']);
        $t->same('sRGB IEC61966-2.1', $metadata['output_intents'][0]['output_condition_identifier']);
        $t->same('sRGB display profile', $metadata['output_intents'][0]['output_condition']);
        $t->same('http://www.color.org', $metadata['output_intents'][0]['registry_name']);
        $t->same('PDF/A sRGB', $metadata['output_intents'][0]['info']);
        $t->same(7, $metadata['output_intents'][0]['dest_output_profile']['object_number']);
        $t->same(3, $metadata['output_intents'][0]['dest_output_profile']['color_components']);
        $t->same('DeviceRGB', $metadata['output_intents'][0]['dest_output_profile']['alternate_color_space']);
        $t->same(strlen($profileBytes), $metadata['output_intents'][0]['dest_output_profile']['bytes']);
        $t->same(hash('sha256', $profileBytes), $metadata['output_intents'][0]['dest_output_profile']['sha256']);
        $t->same(['FlateDecode'], $metadata['output_intents'][0]['dest_output_profile']['filters']);
        $t->same('GTS_PDFX', $metadata['output_intents'][1]['subtype']);
        $t->true(!$metadata['output_intents'][1]['is_pdfa']);
        $t->same([
            'has_output_intent' => true,
            'output_condition_identifiers' => ['sRGB IEC61966-2.1'],
            'profile_sha256' => [hash('sha256', $profileBytes)],
        ], $metadata['pdfa']);
        $t->true(!str_contains($text, 'ICC profile bytes'));
    },
];
