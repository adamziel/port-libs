<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpPacket = static function (array $overrides = []): string {
    $title = $overrides['title'] ?? 'WordPress Import Handbook';
    $description = $overrides['description'] ?? 'Native XMP metadata for editorial review';
    $createDate = $overrides['create_date'] ?? '2024-05-01T10:20:30Z';
    $modifyDate = $overrides['modify_date'] ?? '2024-05-02T11:21:31Z';
    $metadataDate = $overrides['metadata_date'] ?? null;

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
        . '<xmp:CreateDate>' . htmlspecialchars($createDate, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:ModifyDate>' . htmlspecialchars($modifyDate, ENT_XML1) . '</xmp:ModifyDate>'
        . ($metadataDate === null ? '' : '<xmp:MetadataDate>' . htmlspecialchars((string) $metadataDate, ENT_XML1) . '</xmp:MetadataDate>')
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

$pdfWithCatalogReview = static function (string $catalogExtras, string $bodyText, string $extraObjects = ''): string {
    $pageContent = "BT /F1 12 Tf 72 720 Td ({$bodyText}) Tj ET";

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R{$catalogExtras} >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . $extraObjects
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
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
    'normalizes XMP and Info date timezones for WordPress metadata review' => static function (TestRunner $t) use ($xmpPacket, $pdfWithMetadata): void {
        $info = '<< /Title (Legacy Date Title) /CreationDate (D:20240602112233-03\'15\') /ModDate (D:20240602112233+05\'45\') >>';
        $pdf = $pdfWithMetadata($xmpPacket([
            'create_date' => '2024-05-01T10:20:30-07:30',
            'modify_date' => '2024-05-02T11:21:31+05:45',
            'metadata_date' => '2024-05-03T00:00:00Z',
        ]), $info);

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('2024-05-01T10:20:30-07:30', $metadata['created_at']);
        $t->same('2024-05-01T17:50:30Z', $metadata['created_at_utc']);
        $t->same('2024-05-02T11:21:31+05:45', $metadata['modified_at']);
        $t->same('2024-05-02T05:36:31Z', $metadata['modified_at_utc']);
        $t->same('2024-05-03T00:00:00Z', $metadata['metadata_date']);
        $t->same('2024-05-03T00:00:00Z', $metadata['metadata_date_utc']);

        $infoOnlyPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
            . "6 0 obj\n{$info}\nendobj\n"
            . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
        $infoOnly = (new PdfMetadataExtractor())->extractDocumentMetadata($infoOnlyPdf);

        $t->same(['info'], $infoOnly['source']);
        $t->same('D:20240602112233-03\'15\'', $infoOnly['created_at']);
        $t->same('2024-06-02T14:37:33Z', $infoOnly['created_at_utc']);
        $t->same('D:20240602112233+05\'45\'', $infoOnly['modified_at']);
        $t->same('2024-06-02T05:37:33Z', $infoOnly['modified_at_utc']);

        $timezoneFreePdf = $pdfWithMetadata($xmpPacket([
            'create_date' => '2024-05-01T10:20:30',
            'modify_date' => '2024-05-02T11:21:31',
        ]), '<< /Title (Timezone Free) /CreationDate (D:20240602112233) >>');
        $timezoneFree = (new PdfMetadataExtractor())->extractDocumentMetadata($timezoneFreePdf);

        $t->same('2024-05-01T10:20:30', $timezoneFree['created_at']);
        $t->true(!array_key_exists('created_at_utc', $timezoneFree));
        $t->same('2024-05-02T11:21:31', $timezoneFree['modified_at']);
        $t->true(!array_key_exists('modified_at_utc', $timezoneFree));
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
        $t->same('2024-06-02T11:22:33Z', $metadata['created_at_utc']);
    },
    'decodes PDFDocEncoding Info strings for WordPress metadata review' => static function (TestRunner $t): void {
        $title = 'WordPress' . chr(0x80) . ' PDF ' . chr(0x93) . chr(0x94) . ' Import ' . chr(0xa0);
        $author = chr(0x95) . 'ukasz Editor; Data' . chr(0x92) . 'Team';
        $subject = strtoupper(bin2hex('Review ' . chr(0x8d) . 'quotes' . chr(0x8e) . ' ' . chr(0x8a) . ' minus'));
        $keywords = 'wp' . chr(0x8b) . 'percent, caf' . chr(0xe9) . '; ' . chr(0x9b) . 'odz';
        $creator = 'Native' . chr(0x81) . 'Metadata';
        $producer = 'Fixture' . chr(0x85) . 'Writer';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
            . "6 0 obj\n<< /Title ({$title}) /Author ({$author}) /Subject <{$subject}> /Keywords ({$keywords}) /Creator ({$creator}) /Producer ({$producer}) >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);

        $t->same(['info'], $metadata['source']);
        $t->same('WordPress• PDF ﬁﬂ Import €', $metadata['title']);
        $t->same(['Łukasz Editor', 'Data™Team'], $metadata['authors']);
        $t->same('Review “quotes” − minus', $metadata['description']);
        $t->same(['wp‰percent', 'café', 'łodz'], $metadata['keywords']);
        $t->same('Native†Metadata', $metadata['creator_tool']);
        $t->same('Fixture–Writer', $metadata['producer']);
        $t->same('WordPress• PDF ﬁﬂ Import €', $metadata['info']['Title']);
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
    'extracts catalog language and indirect viewer preferences for WordPress review' => static function (TestRunner $t) use ($pdfWithCatalogReview): void {
        $lang = strtoupper(bin2hex("\xfe\xff\x00e\x00s\x00-\x00M\x00X"));
        $viewerPreferences = "7 0 obj\n"
            . "<< /HideToolbar true /HideMenubar false /HideWindowUI true /FitWindow true /CenterWindow false /DisplayDocTitle true"
            . " /NonFullScreenPageMode /UseOC /Direction /R2L /ViewArea /CropBox /ViewClip /BleedBox /PrintArea /TrimBox /PrintClip /ArtBox"
            . " /PrintScaling /None /Duplex /DuplexFlipLongEdge /PickTrayByPDFSize true /PrintPageRange [1 2 5 6] /NumCopies 3 /Enforce [ /PrintScaling /Duplex ] >>\n"
            . "endobj\n";
        $pdf = $pdfWithCatalogReview(
            " /Lang <{$lang}> /PageLayout /TwoPageRight /PageMode /UseOutlines /ViewerPreferences 7 0 R",
            'Catalog Language Import',
            $viewerPreferences
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $text = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(['catalog'], $metadata['source']);
        $t->same('es-MX', $metadata['language']);
        $t->same('es-MX', $metadata['catalog']['language']);
        $t->true(!isset($metadata['languages']));
        $t->same('TwoPageRight', $metadata['page_layout']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same([
            'hide_toolbar' => true,
            'hide_menubar' => false,
            'hide_window_ui' => true,
            'fit_window' => true,
            'center_window' => false,
            'display_doc_title' => true,
            'pick_tray_by_pdf_size' => true,
            'non_full_screen_page_mode' => 'UseOC',
            'direction' => 'R2L',
            'view_area' => 'CropBox',
            'view_clip' => 'BleedBox',
            'print_area' => 'TrimBox',
            'print_clip' => 'ArtBox',
            'print_scaling' => 'None',
            'duplex' => 'DuplexFlipLongEdge',
            'print_page_range' => [1, 2, 5, 6],
            'enforce' => ['PrintScaling', 'Duplex'],
            'num_copies' => 3,
        ], $metadata['viewer_preferences']);
        $t->same($metadata['viewer_preferences'], $metadata['catalog']['viewer_preferences']);
        $t->same('Catalog Language Import', $text);
        $t->true(!str_contains($text, 'HideToolbar'));
    },
    'extracts direct viewer preferences and escaped preference names' => static function (TestRunner $t) use ($pdfWithCatalogReview): void {
        $pdf = $pdfWithCatalogReview(
            ' /Lang (en-US) /PageLayout /SinglePage /PageMode /FullScreen'
            . ' /ViewerPreferences << /DisplayDocTitle false /Direction /L2R /PrintScaling /AppDefault /Enforce [ /Print#53caling ] >>',
            'Direct Viewer Preferences'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);

        $t->same(['catalog'], $metadata['source']);
        $t->same('en-US', $metadata['language']);
        $t->same('SinglePage', $metadata['page_layout']);
        $t->same('FullScreen', $metadata['page_mode']);
        $t->same([
            'display_doc_title' => false,
            'direction' => 'L2R',
            'print_scaling' => 'AppDefault',
            'enforce' => ['PrintScaling'],
        ], $metadata['viewer_preferences']);
        $t->same('Direct Viewer Preferences', (new PdfTextExtractor())->extractPlainText($pdf));
    },
    'extracts Standard encryption permission metadata without decrypting content' => static function (TestRunner $t): void {
        $encryptedContent = 'BT /F1 12 Tf 72 720 Td (Encrypted cleartext leak) Tj ET';
        $permsBytes = "perm-check-16-by";
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -62956 /EncryptMetadata false"
            . " /CF << /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >> /EmbeddedFiles << /CFM /V2 /AuthEvent /EFOpen /Length 5 >> >>"
            . " /StmF /StdCF /StrF /StdCF /EFF /EmbeddedFiles /Perms <" . strtoupper(bin2hex($permsBytes)) . "> >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $encryption = $metadata['encryption'];

        $t->same(['encryption'], $metadata['source']);
        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->true($encryption['is_encrypted']);
        $t->same('trailer_encrypt', $encryption['source']);
        $t->same(5, $encryption['object_number']);
        $t->same('Standard', $encryption['filter']);
        $t->same(4, $encryption['version']);
        $t->same(4, $encryption['revision']);
        $t->same(128, $encryption['key_length_bits']);
        $t->same('security_handler_crypt_filters', $encryption['algorithm']);
        $t->same('standard_handler_revision_4', $encryption['revision_label']);
        $t->same(false, $encryption['encrypt_metadata']);
        $t->same('StdCF', $encryption['stream_filter']);
        $t->same('StdCF', $encryption['string_filter']);
        $t->same('EmbeddedFiles', $encryption['embedded_file_filter']);
        $t->same('AESV2', $encryption['crypt_filters']['StdCF']['method']);
        $t->same('DocOpen', $encryption['crypt_filters']['StdCF']['auth_event']);
        $t->same(16, $encryption['crypt_filters']['StdCF']['key_length_bytes']);
        $t->same('V2', $encryption['crypt_filters']['EmbeddedFiles']['method']);
        $t->same('EFOpen', $encryption['crypt_filters']['EmbeddedFiles']['auth_event']);
        $t->same(5, $encryption['crypt_filters']['EmbeddedFiles']['key_length_bytes']);
        $t->same(-62956, $encryption['standard_permissions']['signed']);
        $t->same(4294904340, $encryption['standard_permissions']['unsigned']);
        $t->same('FFFF0A14', $encryption['standard_permissions']['hex']);
        $t->same([
            'print',
            'copy_or_extract',
            'extract_for_accessibility',
            'high_quality_print',
        ], $encryption['standard_permissions']['allowed']);
        $t->same([
            'modify_contents',
            'add_or_modify_annotations',
            'fill_form_fields',
            'assemble_document',
        ], $encryption['standard_permissions']['denied']);
        $t->same('high_resolution', $encryption['standard_permissions']['print_quality']);
        $t->same(strlen($permsBytes), $encryption['perms']['bytes']);
        $t->same(hash('sha256', $permsBytes), $encryption['perms']['sha256']);
        $t->true($encryption['requires_password_for_content_extraction']);
        $t->true($encryption['review_only']);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $t->true(is_string($encoded) && !str_contains($encoded, 'DEADBEEF') && !str_contains($encoded, 'CAFEFEED'));
    },
];
