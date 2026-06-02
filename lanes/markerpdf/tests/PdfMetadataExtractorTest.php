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

$pdfWithXrefStreamTrailerMetadata = static function (): array {
    $xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current XRef XMP Title</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Current xref stream metadata review</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>2024-06-02T08:30:00-04:00</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
    $compressedXmp = gzcompress($xmp);
    if (!is_string($compressedXmp)) {
        throw new RuntimeException('Unable to compress xref-stream XMP fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (Current xref metadata body) Tj ET';
    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
        return $offset;
    };

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(5, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream");
    $addObject(6, 0, '<< /Title (Current Info Title) /Author (Current XRef Author) /Producer (Current XRef Producer) /ModDate (D:20240602112233Z) >>');
    $addObject(8, 0, '<< /Title (Stale Trailer Title) /Author (Stale Trailer Author) /Producer (Stale Trailer Producer) >>');

    $stalePermanent = 'Stale Permanent';
    $currentPermanent = 'Current Permanent';
    $currentChanging = 'Current Changing';
    $pdf .= "trailer\n<< /Root 1 0 R /Info 8 0 R /ID [(Stale\\040Permanent) <" . strtoupper(bin2hex('Stale Changing')) . ">] >>\n";

    $xrefOffset = strlen($pdf);
    $rows = '';
    for ($objectNumber = 0; $objectNumber < 10; $objectNumber++) {
        $rows .= pack('N', $objectNumber === 9 ? $xrefOffset : ($offsets[$objectNumber] ?? 0));
    }
    $compressedXref = gzcompress($rows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress xref-stream metadata fixture.');
    }

    $pdf .= "9 0 obj\n"
        . '<< /Type /XRef /Size 10 /Root 1 0 R /Info 6 0 R /ID [(Current\040Permanent) <' . strtoupper(bin2hex($currentChanging)) . '>] /W [0 4 0] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return [$pdf, $currentPermanent, $currentChanging, $stalePermanent];
};

$pdfWithXrefStreamEncryptedMetadata = static function (): array {
    $xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current XRef Encrypted XMP Title</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Current encrypted xref stream metadata review</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>2024-06-02T08:30:00-04:00</xmp:CreateDate>'
        . '<xmp:MetadataDate>2024-06-02T12:45:00Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
    $compressedXmp = gzcompress($xmp);
    $compressedProfile = gzcompress('Current encrypted OutputIntent profile bytes');
    if (!is_string($compressedXmp) || !is_string($compressedProfile)) {
        throw new RuntimeException('Unable to compress xref-stream encrypted metadata fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (Encrypted xref stream visible leak) Tj ET';
    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R /OutputIntents [9 0 R] >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(5, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream");
    $addObject(6, 0, '<< /Title (Current Encrypted Info Title) /Author (Current Encrypted Author) /Producer (Current Encrypted Producer) /CreationDate (D:20240602112233Z) >>');
    $addObject(7, 0, '<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length ' . strlen($compressedProfile) . " >>\nstream\n{$compressedProfile}\nendstream");
    $addObject(8, 0, '<< /Title (Stale Trailer Info Title) /Author (Stale Trailer Author) >>');
    $addObject(9, 0, '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Encrypted XRef sRGB) /Info (Encrypted XRef PDF/A) /DestOutputProfile 7 0 R >>');
    $addObject(10, 0, '<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -64 /EncryptMetadata false >>');

    $currentPermanent = 'XRef Encrypted Permanent';
    $currentChanging = 'XRef Encrypted Changing';
    $pdf .= "trailer\n<< /Root 1 0 R /Info 8 0 R /ID [(Stale\\040Permanent) <" . strtoupper(bin2hex('Stale Changing')) . ">] >>\n";

    $xrefOffset = strlen($pdf);
    $rows = '';
    for ($objectNumber = 0; $objectNumber < 12; $objectNumber++) {
        $rows .= pack('N', $objectNumber === 11 ? $xrefOffset : ($offsets[$objectNumber] ?? 0));
    }
    $compressedXref = gzcompress($rows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress xref-stream encrypted metadata xref rows.');
    }

    $pdf .= "11 0 obj\n"
        . '<< /Type /XRef /Size 12 /Root 1 0 R /Info 6 0 R /Encrypt 10 0 R /ID [(XRef\040Encrypted\040Permanent) <' . strtoupper(bin2hex($currentChanging)) . '>] /W [0 4 0] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return [$pdf, $currentPermanent, $currentChanging];
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
    'decodes undeclared Windows-1252 XMP packet bytes and falls back to Info authors' => static function (TestRunner $t) use ($pdfWithMetadata): void {
        $xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
            . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
            . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            . '<rdf:Description rdf:about=""'
            . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
            . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
            . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
            . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Caf' . chr(0xe9) . ' ' . chr(0x93) . 'Review' . chr(0x94) . ' Packet</rdf:li></rdf:Alt></dc:title>'
            . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">WordPress import ' . chr(0x96) . ' encoded metadata</rdf:li></rdf:Alt></dc:description>'
            . '<pdf:Keywords>caf' . chr(0xe9) . ', wp' . chr(0x96) . 'migration</pdf:Keywords>'
            . '<xmp:CreatorTool>InDesign' . chr(0x99) . ' Exporter</xmp:CreatorTool>'
            . '<xmp:CreateDate>2024-06-02T07:15:00-04:00</xmp:CreateDate>'
            . '</rdf:Description>'
            . '</rdf:RDF>'
            . '</x:xmpmeta>'
            . '<?xpacket end="w"?>';
        $info = '<< /Title (Legacy Encoded Title) /Author (' . chr(0x95) . 'ukasz Editor; Site Owner) /Producer (Info Producer) >>';
        $pdf = $pdfWithMetadata($xmp, $info);

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Café “Review” Packet', $metadata['title']);
        $t->same('WordPress import – encoded metadata', $metadata['description']);
        $t->same(['café', 'wp–migration'], $metadata['keywords']);
        $t->same('InDesign™ Exporter', $metadata['creator_tool']);
        $t->same(['Łukasz Editor', 'Site Owner'], $metadata['authors']);
        $t->same('Info Producer', $metadata['producer']);
        $t->same('Windows-1252', $metadata['xmp']['packet_encoding']);
        $t->true($metadata['xmp']['encoding_fallback']);
        $t->same('2024-06-02T11:15:00Z', $metadata['created_at_utc']);
        $t->same('Visible PDF Body', $plainText);
        $t->true(!str_contains($plainText, 'Café'));
        $t->true(!str_contains($plainText, 'Legacy Encoded Title'));
    },
    'keeps XMP and DocInfo metadata distinct from catalog destination names' => static function (TestRunner $t): void {
        $xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
            . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
            . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            . '<rdf:Description rdf:about=""'
            . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
            . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
            . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Destination Boundary XMP Title</rdf:li></rdf:Alt></dc:title>'
            . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Navigation names are review metadata</rdf:li></rdf:Alt></dc:description>'
            . '<xmp:CreateDate>2026-06-02T12:41:55Z</xmp:CreateDate>'
            . '</rdf:Description>'
            . '</rdf:RDF>'
            . '</x:xmpmeta>'
            . '<?xpacket end="w"?>';
        $compressedXmp = gzcompress($xmp);
        if (!is_string($compressedXmp)) {
            throw new RuntimeException('Unable to compress destination metadata boundary fixture.');
        }

        $content = 'BT /F1 12 Tf 72 720 Td (Destination Metadata Body) Tj ET';
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyAppendix [4 0 R /Fit] /LegacyStale [99 0 R /Fit] >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Title (DocInfo Destination Title) /Author (Metadata Owner; Site Editor) /Producer (DocInfo Producer) >>\nendobj\n"
            . "8 0 obj\n<< /Kids [9 0 R 10 0 R 8 0 R] >>\nendobj\n"
            . "9 0 obj\n<< /Limits [(A) (M)] /Names [(Chapter One) [3 0 R /FitH 640] 12 0 R 13 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Limits [(N) (Z)] /Names [(Review Deck) 14 0 R (Stale Review) [99 0 R /XYZ 1 2 3]] >>\nendobj\n"
            . "12 0 obj\n<FEFF0049006E00640069007200650063007400200044006500730074>\nendobj\n"
            . "13 0 obj\n<< /D [4 0 R /FitR 10 20 300 740] >>\nendobj\n"
            . "14 0 obj\n[3 0 R /XYZ 144 null 0]\nendobj\n"
            . "trailer\n<< /Root 1 0 R /Info 7 0 R >>\n%%EOF";

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $destinations = $metadata['document_destinations'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info', 'catalog'], $metadata['source']);
        $t->same('Destination Boundary XMP Title', $metadata['title']);
        $t->same(['Metadata Owner', 'Site Editor'], $metadata['authors']);
        $t->same('DocInfo Producer', $metadata['producer']);
        $t->same(['names_dests', 'legacy_dests'], $destinations['source']);
        $t->same(4, $destinations['count']);
        $t->same(2, $destinations['page_count']);
        $t->same(['Chapter One', 'Indirect Dest', 'Review Deck', 'LegacyAppendix'], $destinations['names']);
        $t->same(2, $destinations['unresolved_count']);
        $t->same('Chapter One', $destinations['destinations'][0]['name']);
        $t->same(0, $destinations['destinations'][0]['page']);
        $t->same(1, $destinations['destinations'][0]['page_number']);
        $t->same('FitH', $destinations['destinations'][0]['view_mode']);
        $t->same(['top' => 640.0], $destinations['destinations'][0]['view_parameters']);
        $t->same('Indirect Dest', $destinations['destinations'][1]['name']);
        $t->same(1, $destinations['destinations'][1]['page']);
        $t->same('FitR', $destinations['destinations'][1]['view_mode']);
        $t->same(['left' => 10.0, 'bottom' => 20.0, 'right' => 300.0, 'top' => 740.0], $destinations['destinations'][1]['view_parameters']);
        $t->same('Review Deck', $destinations['destinations'][2]['name']);
        $t->same('XYZ', $destinations['destinations'][2]['view_mode']);
        $t->same(['left' => 144.0, 'top' => null, 'zoom' => null], $destinations['destinations'][2]['view_parameters']);
        $t->same('legacy_dests', $destinations['destinations'][3]['source']);
        $t->same('Fit', $destinations['destinations'][3]['view_mode']);
        $t->same('Destination Metadata Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Review'));
        $t->true(!str_contains($plainText, 'Chapter One'));
        $t->true(!str_contains($plainText, 'Destination Boundary XMP Title'));
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
    'uses current xref stream trailer for XMP Info and ID metadata' => static function (TestRunner $t) use ($pdfWithXrefStreamTrailerMetadata): void {
        [$pdf, $permanentId, $changingId, $stalePermanent] = $pdfWithXrefStreamTrailerMetadata();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info', 'trailer_id'], $metadata['source']);
        $t->same('Current XRef XMP Title', $metadata['title']);
        $t->same('Current xref stream metadata review', $metadata['description']);
        $t->same(['Current XRef Author'], $metadata['authors']);
        $t->same('Current XRef Producer', $metadata['producer']);
        $t->same('2024-06-02T08:30:00-04:00', $metadata['created_at']);
        $t->same('2024-06-02T12:30:00Z', $metadata['created_at_utc']);
        $t->same('D:20240602112233Z', $metadata['modified_at']);
        $t->same('2024-06-02T11:22:33Z', $metadata['modified_at_utc']);
        $t->same(bin2hex($permanentId), $metadata['trailer_ids']['permanent']['hex']);
        $t->same(hash('sha256', $permanentId), $metadata['document_fingerprint']);
        $t->same(bin2hex($changingId), $metadata['trailer_ids']['changing']['hex']);
        $t->true($metadata['trailer_ids']['changed_since_creation']);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Trailer Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, $stalePermanent));
        $t->same('Current xref metadata body', $plainText);
        $t->true(!str_contains($plainText, 'Current XRef XMP Title'));
    },
    'uses current xref stream trailer encryption before XMP dates and OutputIntent review' => static function (TestRunner $t) use ($pdfWithXrefStreamEncryptedMetadata): void {
        [$pdf, $permanentId, $changingId] = $pdfWithXrefStreamEncryptedMetadata();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $policy = $metadata['encryption']['metadata_source_policy'] ?? [];

        $t->same(['encryption', 'xmp', 'trailer_id'], $metadata['source']);
        $t->same('xref_stream_trailer_encrypt', $metadata['encryption']['source']);
        $t->same(false, $metadata['encryption']['encrypt_metadata']);
        $t->same('preserved_unencrypted_by_encrypt_metadata_false', $policy['xmp_stream_policy'] ?? null);
        $t->same('suppressed_encrypted_document_strings', $policy['info_dictionary_policy'] ?? null);
        $t->same('suppressed_encrypted_stream_or_strings', $policy['output_intents_policy'] ?? null);
        $t->same(['info', 'output_intents'], $policy['suppressed_sources'] ?? []);
        $t->same(['xmp'], $policy['preserved_sources'] ?? []);
        $t->same('Current XRef Encrypted XMP Title', $metadata['title']);
        $t->same('Current encrypted xref stream metadata review', $metadata['description']);
        $t->same('2024-06-02T08:30:00-04:00', $metadata['created_at']);
        $t->same('2024-06-02T12:30:00Z', $metadata['created_at_utc']);
        $t->same('2024-06-02T12:45:00Z', $metadata['metadata_date']);
        $t->same('2024-06-02T12:45:00Z', $metadata['metadata_date_utc']);
        $t->same([], $metadata['info']);
        $t->same([], $metadata['output_intents']);
        $t->true(!isset($metadata['pdfa']));
        $t->same(bin2hex($permanentId), $metadata['trailer_ids']['permanent']['hex']);
        $t->same(hash('sha256', $permanentId), $metadata['document_fingerprint']);
        $t->same(bin2hex($changingId), $metadata['trailer_ids']['changing']['hex']);
        $t->same('', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Current Encrypted Info Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Encrypted XRef sRGB'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Encrypted xref stream visible leak'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'DEADBEEF') && !str_contains($encoded, 'CAFEFEED'));
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
    'keeps OutputIntent associated FileSpec metadata review-only' => static function (TestRunner $t) use ($xmpPacket): void {
        $rootProfile = 'Root PDF/A ICC profile bytes';
        $associatedProfile = 'Associated FileSpec ICC bytes should not become PDF/A root metadata';
        $sourcePayload = '<wp-export><post id="1455"/></wp-export>';
        $previewPayload = 'Rendered preview attachment bytes';
        $sourceChecksum = strtoupper(hash('md5', $sourcePayload));
        $compressedRootProfile = gzcompress($rootProfile);
        $compressedAssociatedProfile = gzcompress($associatedProfile);
        $sourceXmp = gzcompress($xmpPacket([
            'title' => 'Associated Source XMP Title',
            'description' => 'Nested FileSpec metadata should stay review-only',
        ]));
        $previewXmp = gzcompress($xmpPacket([
            'title' => 'Associated Preview XMP Title',
            'description' => 'Inline associated FileSpec metadata should stay review-only',
        ]));
        if (
            !is_string($compressedRootProfile)
            || !is_string($compressedAssociatedProfile)
            || !is_string($sourceXmp)
            || !is_string($previewXmp)
        ) {
            throw new RuntimeException('Unable to compress OutputIntent associated metadata fixture.');
        }

        $content = 'BT /F1 12 Tf 72 720 Td (OutputIntent Associated Body) Tj ET';
        $pdf = "%PDF-2.0\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OutputIntents [9 0 R] >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($sourceXmp) . " >>\nstream\n{$sourceXmp}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($previewXmp) . " >>\nstream\n{$previewXmp}\nendstream\nendobj\n"
            . "7 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($compressedRootProfile) . " >>\nstream\n{$compressedRootProfile}\nendstream\nendobj\n"
            . "8 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($compressedAssociatedProfile) . " >>\nstream\n{$compressedAssociatedProfile}\nendstream\nendobj\n"
            . "9 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Root sRGB) /Info (Root PDF/A profile) /DestOutputProfile 7 0 R /AF [10 0 R << /Type /Filespec /F (preview.pdf) /Desc (Rendered PDF preview) /AFRelationship /Alternative /Metadata 6 0 R /OutputIntents [13 0 R] /EF << /F 15 0 R >> >> 99 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Original WordPress export) /AFRelationship /Source /Metadata 5 0 R /OutputIntents [13 0 R] /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
            . "13 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Associated sRGB) /Info (Nested associated PDF/A) /DestOutputProfile 8 0 R >>\nendobj\n"
            . "15 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fpdf /Length " . strlen($previewPayload) . " >>\nstream\n{$previewPayload}\nendstream\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF";

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $text = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['output_intents'], $metadata['source']);
        $t->same('OutputIntent Associated Body', $text);
        $t->same(1, count($metadata['output_intents']));
        $t->same('Root sRGB', $metadata['output_intents'][0]['output_condition_identifier']);
        $t->same([
            'has_output_intent' => true,
            'output_condition_identifiers' => ['Root sRGB'],
            'profile_sha256' => [hash('sha256', $rootProfile)],
        ], $metadata['pdfa']);
        $t->same(2, count($metadata['output_intents'][0]['associated_files']));

        $source = $metadata['output_intents'][0]['associated_files'][0];
        $t->same('output_intent_associated_files', $source['source']);
        $t->same(true, $source['associated_file']);
        $t->same(0, $source['associated_file_index']);
        $t->same('source.xml', $source['filename']);
        $t->same('Original WordPress export', $source['description']);
        $t->same('Source', $source['relationship']);
        $t->same('text/xml', $source['mime_type']);
        $t->same(10, $source['file_spec_object']);
        $t->same(11, $source['embedded_file_object']);
        $t->same(strlen($sourcePayload), $source['declared_size']);
        $t->same(strlen($sourcePayload), $source['size']);
        $t->same(strtolower($sourceChecksum), $source['checksum']);
        $t->same(hash('md5', $sourcePayload), $source['computed_checksum']);
        $t->same(true, $source['checksum_matches']);
        $t->same(hash('sha256', $sourcePayload), $source['content_sha256']);
        $t->same('Metadata', $source['metadata_review']['Type']);
        $t->same('XML', $source['metadata_review']['Subtype']);
        $t->same('GTS_PDFA1', $source['output_intents_review'][0]['S']);
        $t->same('Associated sRGB', $source['output_intents_review'][0]['OutputConditionIdentifier']);
        $t->true(!array_key_exists('content', $source));

        $preview = $metadata['output_intents'][0]['associated_files'][1];
        $t->same('preview.pdf', $preview['filename']);
        $t->same('Rendered PDF preview', $preview['description']);
        $t->same('Alternative', $preview['relationship']);
        $t->same('application/pdf', $preview['mime_type']);
        $t->same(null, $preview['file_spec_object']);
        $t->same(15, $preview['embedded_file_object']);
        $t->same(strlen($previewPayload), $preview['size']);
        $t->same('Metadata', $preview['metadata_review']['Type']);
        $t->same('GTS_PDFA1', $preview['output_intents_review'][0]['S']);
        $t->true(!array_key_exists('content', $preview));

        $t->true(is_string($encoded) && !str_contains($encoded, 'Associated Source XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Associated Preview XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, $sourcePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $previewPayload));
        $t->true(!str_contains($text, 'Associated Source XMP Title'));
        $t->true(!str_contains($text, 'wp-export'));
        $t->true(!str_contains($text, 'Associated FileSpec ICC bytes'));
    },
    'keeps catalog PieceInfo private Metadata and OutputIntents from document metadata roots' => static function (TestRunner $t) use ($xmpPacket): void {
        $privateXmp = $xmpPacket([
            'title' => 'PieceInfo Private XMP Title',
            'description' => 'Application-private XMP packet should stay review-only',
        ]);
        $compressedXmp = gzcompress($privateXmp);
        $compressedProfile = gzcompress('PieceInfo private ICC bytes should not be promoted');
        if (!is_string($compressedXmp) || !is_string($compressedProfile)) {
            throw new RuntimeException('Unable to compress PieceInfo metadata boundary fixture.');
        }

        $content = 'BT /F1 12 Tf 72 720 Td (PieceInfo Boundary Body) Tj ET';
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PieceInfo << /WPMetadata << /LastModified (D:20260602105000Z) /Private << /Workflow (metadata-boundary) /ReviewFlag true /Metadata 5 0 R /OutputIntents [9 0 R] >> >> >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
            . "7 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($compressedProfile) . " >>\nstream\n{$compressedProfile}\nendstream\nendobj\n"
            . "9 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (PieceInfo sRGB) /Info (PieceInfo PDF/A) /DestOutputProfile 7 0 R >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF";

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $text = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same([], $metadata['output_intents']);
        $t->true(!isset($metadata['title']));
        $t->true(!isset($metadata['pdfa']));
        $t->same('D:20260602105000Z', $metadata['catalog']['piece_info']['WPMetadata']['last_modified']);
        $t->same('metadata-boundary', $metadata['catalog']['piece_info']['WPMetadata']['private']['Workflow']);
        $t->same(true, $metadata['catalog']['piece_info']['WPMetadata']['private']['ReviewFlag']);
        $t->same('Metadata', $metadata['catalog']['piece_info']['WPMetadata']['private']['Metadata']['Type']);
        $t->same('XML', $metadata['catalog']['piece_info']['WPMetadata']['private']['Metadata']['Subtype']);
        $t->same('GTS_PDFA1', $metadata['catalog']['piece_info']['WPMetadata']['private']['OutputIntents'][0]['S']);
        $t->same('PieceInfo sRGB', $metadata['catalog']['piece_info']['WPMetadata']['private']['OutputIntents'][0]['OutputConditionIdentifier']);
        $t->same('PieceInfo Boundary Body', $text);
        $t->true(is_string($encoded) && !str_contains($encoded, 'PieceInfo Private XMP Title'));
        $t->true(!str_contains($text, 'PieceInfo private ICC bytes'));
    },
    'reviews catalog Collection schema with associated FileSpec rows as metadata' => static function (TestRunner $t) use ($xmpPacket): void {
        $sourcePayload = '<wp-export><post id="1628"/></wp-export>';
        $previewPayload = '{"preview":"metadata-schema"}';
        $sourceChecksum = strtoupper(hash('md5', $sourcePayload));
        $previewChecksum = str_repeat('0a', 16);
        $fileXmp = gzcompress($xmpPacket([
            'title' => 'Associated Collection XMP Title',
            'description' => 'Attachment-local XMP should stay review-only',
        ]));
        $iccProfile = gzcompress('Associated Collection ICC bytes should stay nested');
        if (!is_string($fileXmp) || !is_string($iccProfile)) {
            throw new RuntimeException('Unable to compress collection schema associated metadata fixture.');
        }

        $content = 'BT /F1 12 Tf 72 720 Td (Collection Schema Metadata Body) Tj ET';
        $pdf = "%PDF-2.0\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageMode /UseAttachments /Collection 5 0 R /AF [10 0 R << /Type /Filespec /F (preview.json) /Desc (Rendered preview JSON) /AFRelationship /Alternative /CI << /Subject (Preview JSON) /Priority << /Type /CollectionSubitem /D 1 /P (P) >> /ReviewDate (D:20260602162500Z) /Stale (ignored) >> /Metadata 31 0 R /OutputIntents [40 0 R] /EF << /F 21 0 R >> >> 99 0 R] >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /Collection /View /T /D (source-unicode.xml) /Schema << /NameField << /Subtype /F /N (Filename) /O 1 >> /DescriptionField << /Subtype /Desc /N (Description) /O 2 /V true /E false >> /BytesField << /Subtype /Size /N (Bytes) /O 3 >> /Subject << /Subtype /S /N (Subject) /O 4 >> /Priority << /Subtype /N /N (Priority) /O 5 >> /ReviewDate << /Subtype /D /N (Reviewed) /O 6 >> >> /Sort << /S [/Priority /ReviewDate] /A [true false] >> >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (legacy-source.xml) /UF (source-unicode.xml) /Desc (Original WordPress export) /AFRelationship /Source /CI 30 0 R /Metadata 31 0 R /OutputIntents [40 0 R] /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260602162400Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
            . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($previewPayload) . " /CheckSum <{$previewChecksum}> >> /Length " . strlen($previewPayload) . " >>\nstream\n{$previewPayload}\nendstream\nendobj\n"
            . "30 0 obj\n<< /Subject (Migration Source) /Priority << /Type /CollectionSubitem /D 2 /P (P) >> /ReviewDate (D:20260602162600Z) /Stale (not in schema) >>\nendobj\n"
            . "31 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($fileXmp) . " >>\nstream\n{$fileXmp}\nendstream\nendobj\n"
            . "40 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Associated Collection sRGB) /Info (Nested collection PDF/A) /DestOutputProfile 41 0 R >>\nendobj\n"
            . "41 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($iccProfile) . " >>\nstream\n{$iccProfile}\nendstream\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF";

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $text = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('Collection Schema Metadata Body', $text);
        $t->same('UseAttachments', $metadata['page_mode']);
        $collection = $metadata['catalog']['collection'];
        $t->same('catalog_collection', $collection['source']);
        $t->same('Collection', $collection['type']);
        $t->same('T', $collection['view']);
        $t->same('source-unicode.xml', $collection['default_document']);
        $t->same(['Priority', 'ReviewDate'], $collection['sort']['keys']);
        $t->same([true, false], $collection['sort']['ascending']);
        $t->same(['NameField', 'DescriptionField', 'BytesField', 'Subject', 'Priority', 'ReviewDate'], array_keys($collection['schema']));
        $t->same('N', $collection['schema']['Priority']['subtype']);
        $t->same('Priority', $collection['schema']['Priority']['label']);
        $t->same(2, count($collection['associated_files']));

        $source = $collection['associated_files'][0];
        $t->same('catalog_collection_associated_files', $source['source']);
        $t->same(true, $source['associated_file']);
        $t->same(0, $source['associated_file_index']);
        $t->same('source-unicode.xml', $source['filename']);
        $t->same('legacy-source.xml', $source['platform_filename']);
        $t->same('Original WordPress export', $source['description']);
        $t->same('Source', $source['relationship']);
        $t->same('text/xml', $source['mime_type']);
        $t->same(10, $source['file_spec_object']);
        $t->same(11, $source['embedded_file_object']);
        $t->same(strlen($sourcePayload), $source['declared_size']);
        $t->same(true, $source['checksum_matches']);
        $t->same('Migration Source', $source['collection_item']['Subject']);
        $t->same('source-unicode.xml', $source['collection_field_values']['NameField']['value']);
        $t->same('file_spec', $source['collection_field_values']['NameField']['source']);
        $t->same(strlen($sourcePayload), $source['collection_field_values']['BytesField']['value']);
        $t->same('embedded_file_params', $source['collection_field_values']['BytesField']['source']);
        $t->same('P2', $source['collection_field_values']['Priority']['display_value']);
        $t->same('number', $source['collection_field_values']['Priority']['value_type']);
        $t->same('D:20260602162600Z', $source['collection_field_values']['ReviewDate']['value']);
        $t->true(!array_key_exists('Stale', $source['collection_field_values']));
        $t->same('Associated Collection sRGB', $source['output_intents_review'][0]['OutputConditionIdentifier']);
        $t->true(!array_key_exists('content', $source));

        $preview = $collection['associated_files'][1];
        $t->same('preview.json', $preview['filename']);
        $t->same('Alternative', $preview['relationship']);
        $t->same('Rendered preview JSON', $preview['description']);
        $t->same('application/json', $preview['mime_type']);
        $t->same(false, $preview['checksum_matches']);
        $t->same('P1', $preview['collection_field_values']['Priority']['display_value']);
        $t->same('Preview JSON', $preview['collection_field_values']['Subject']['value']);
        $t->same('Associated Collection sRGB', $preview['output_intents_review'][0]['OutputConditionIdentifier']);
        $t->true(!array_key_exists('content', $preview));

        $t->same([], $metadata['output_intents']);
        $t->true(!isset($metadata['pdfa']));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Associated Collection XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Associated Collection ICC bytes'));
        $t->true(is_string($encoded) && !str_contains($encoded, $sourcePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $previewPayload));
        $t->true(!str_contains($text, '<wp-export>'));
        $t->true(!str_contains($text, 'metadata-schema'));
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
    'extracts structure tree language alternate and expansion review metadata without visible text leakage' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf '
            . '/DocSection << /MCID 0 >> BDC 72 720 Td (Visible heading glyphs) Tj EMC '
            . '/Lead << /MCID 1 >> BDC 72 704 Td (Visible body glyphs) Tj EMC ET';
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /PageLayout /SinglePage /PageMode /UseOutlines /ViewerPreferences << /DisplayDocTitle true /Direction /L2R /PrintScaling /None >> /MarkInfo << /Marked true >> /StructTreeRoot 20 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "20 0 obj\n<< /Type /StructTreeRoot /RoleMap 24 0 R /Namespaces [25 0 R] /K [21 0 R 22 0 R] >>\nendobj\n"
            . "21 0 obj\n<< /Type /StructElem /S /Doc#53ection /Pg 3 0 R /Lang (fr-CA) /T (Resume Section) /ID (sec-1) /C [/chapter /featured] /R 2 /K << /Type /MCR /Pg 3 0 R /MCID 0 >> >>\nendobj\n"
            . "22 0 obj\n<< /Type /StructElem /S /Lead /Pg 3 0 R /NS 25 0 R /Alt (Accessible abstract summary) /ActualText (Expanded actual text should stay review) /E (Content Management System) /K 1 >>\nendobj\n"
            . "24 0 obj\n<< /Doc#53ection /Sect /Lead /P >>\nendobj\n"
            . "25 0 obj\n<< /Type /Namespace /NS (https://example.test/wp-structure) /RoleMap << /Lead /P >> >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF";

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $structure = $metadata['structure_tree'] ?? [];
        $elements = $structure['elements'] ?? [];

        $t->same(['catalog'], $metadata['source']);
        $t->same('en-US', $metadata['language']);
        $t->same('SinglePage', $metadata['page_layout']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('L2R', $metadata['viewer_preferences']['direction']);
        $t->same('catalog_struct_tree_root', $structure['source'] ?? null);
        $t->same(20, $structure['root_object'] ?? null);
        $t->same('en-US', $structure['root_language'] ?? null);
        $t->same(true, $structure['catalog_language_fallback'] ?? null);
        $t->same(['DocSection' => 'Sect', 'Lead' => 'P'], $structure['role_map'] ?? []);
        $t->same(['fr-CA', 'en-US'], $structure['languages'] ?? []);
        $t->same(2, $structure['element_count'] ?? null);
        $t->same(1, $structure['page_count'] ?? null);
        $t->same('https://example.test/wp-structure', $structure['namespaces'][0]['namespace'] ?? null);
        $t->same(['Lead' => 'P'], $structure['namespaces'][0]['role_map'] ?? []);

        $section = $elements[0] ?? [];
        $t->same(21, $section['object'] ?? null);
        $t->same('DocSection', $section['raw_role'] ?? null);
        $t->same('Sect', $section['role'] ?? null);
        $t->same(true, $section['role_mapped'] ?? null);
        $t->same('fr-CA', $section['language'] ?? null);
        $t->same(false, $section['language_inherited'] ?? null);
        $t->same('Resume Section', $section['title'] ?? null);
        $t->same('sec-1', $section['id'] ?? null);
        $t->same(['chapter', 'featured'], $section['classes'] ?? []);
        $t->same(2, $section['revision'] ?? null);
        $t->same(3, $section['page_object'] ?? null);
        $t->same(0, $section['page'] ?? null);
        $t->same(1, $section['page_number'] ?? null);
        $t->same([0], $section['mcids'] ?? []);
        $t->same(0, $section['marked_content'][0]['mcid'] ?? null);

        $lead = $elements[1] ?? [];
        $t->same(22, $lead['object'] ?? null);
        $t->same('Lead', $lead['raw_role'] ?? null);
        $t->same('P', $lead['role'] ?? null);
        $t->same(true, $lead['role_mapped'] ?? null);
        $t->same('en-US', $lead['language'] ?? null);
        $t->same(true, $lead['language_inherited'] ?? null);
        $t->same('Accessible abstract summary', $lead['alternate_text'] ?? null);
        $t->same('Expanded actual text should stay review', $lead['actual_text'] ?? null);
        $t->same('Content Management System', $lead['expansion_text'] ?? null);
        $t->same('https://example.test/wp-structure', $lead['namespace']['namespace'] ?? null);
        $t->same([1], $lead['mcids'] ?? []);
        $t->same(1, $lead['marked_content'][0]['mcid'] ?? null);
        $t->same(3, $lead['marked_content'][0]['page_object'] ?? null);
        $t->same(0, $lead['marked_content'][0]['page'] ?? null);

        $t->same("Visible heading glyphs\nVisible body glyphs", $plainText);
        $t->true(!str_contains($plainText, 'Accessible abstract summary'));
        $t->true(!str_contains($plainText, 'Expanded actual text should stay review'));
        $t->true(!str_contains($plainText, 'Content Management System'));
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
    'bounds viewer preference values and resolves indirect preference operands' => static function (TestRunner $t) use ($pdfWithCatalogReview): void {
        $extraObjects = "7 0 obj\n"
            . "<< /HideToolbar 8 0 R /HideWindowUI false /DisplayDocTitle true /Direction 9 0 R"
            . " /NonFullScreenPageMode /UseAttachments /ViewArea /CropBox /ViewClip /BleedBox /PrintArea /MediaBox /PrintClip /Bogus"
            . " /PrintScaling /Invalid /Duplex 10 0 R /PrintPageRange 11 0 R /NumCopies 12 0 R /Enforce 13 0 R >>\n"
            . "endobj\n"
            . "8 0 obj\ntrue\nendobj\n"
            . "9 0 obj\n/R2L\nendobj\n"
            . "10 0 obj\n/DuplexFlipShortEdge\nendobj\n"
            . "11 0 obj\n[1 2 5 6]\nendobj\n"
            . "12 0 obj\n4\nendobj\n"
            . "13 0 obj\n[ /PrintScaling /Bogus /Duplex /Print#50ageRange ]\nendobj\n";
        $pdf = $pdfWithCatalogReview(
            ' /ViewerPreferences 7 0 R',
            'Bounded Viewer Preferences',
            $extraObjects
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $viewerPreferences = $metadata['viewer_preferences'];

        $t->same([
            'hide_toolbar' => true,
            'hide_window_ui' => false,
            'display_doc_title' => true,
            'direction' => 'R2L',
            'view_area' => 'CropBox',
            'view_clip' => 'BleedBox',
            'print_area' => 'MediaBox',
            'duplex' => 'DuplexFlipShortEdge',
            'print_page_range' => [1, 2, 5, 6],
            'enforce' => ['PrintScaling', 'Duplex', 'PrintPageRange'],
            'num_copies' => 4,
        ], $viewerPreferences);
        $t->true(!array_key_exists('non_full_screen_page_mode', $viewerPreferences));
        $t->true(!array_key_exists('print_clip', $viewerPreferences));
        $t->true(!array_key_exists('print_scaling', $viewerPreferences));
        $t->same('Bounded Viewer Preferences', (new PdfTextExtractor())->extractPlainText($pdf));
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
        $t->same(4, $encryption['standard_permissions']['effective_revision']);
        $t->same(false, $encryption['standard_permissions']['reserved_bits_valid']);
        $t->same('malformed_reserved_bits_review', $encryption['standard_permissions']['permission_word_status']);
        $t->same('FFFFF0C0', $encryption['standard_permissions']['reserved_bits']['expected_set_mask_hex']);
        $t->same('00000003', $encryption['standard_permissions']['reserved_bits']['expected_clear_mask_hex']);
        $t->same(['reserved_bits_7_8_clear', 'reserved_bits_13_32_clear'], $encryption['standard_permissions']['reserved_bits']['violations']);
        $t->same(strlen($permsBytes), $encryption['perms']['bytes']);
        $t->same(hash('sha256', $permsBytes), $encryption['perms']['sha256']);
        $t->true($encryption['requires_password_for_content_extraction']);
        $t->true($encryption['review_only']);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $t->true(is_string($encoded) && !str_contains($encoded, 'DEADBEEF') && !str_contains($encoded, 'CAFEFEED'));
    },
    'summarizes public-key recipient envelopes without exposing recipient bytes' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Public-key encrypted cleartext leak) Tj ET';
        $recipientOne = 'CMS_RECIPIENT_ONE_PERMISSION_BYTES_SHOULD_NOT_LEAK';
        $recipientTwo = 'CMS_RECIPIENT_TWO_PERMISSION_BYTES_SHOULD_NOT_LEAK';
        $recipientOneHex = strtoupper(bin2hex($recipientOne));
        $recipientTwoHex = strtoupper(bin2hex($recipientTwo));
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Filter /Adobe.PubSec /SubFilter /adbe.pkcs7.s4 /V 2 /Length 128 /Recipients [<{$recipientOneHex}> 6 0 R] /EncryptMetadata true >>\nendobj\n"
            . "6 0 obj\n<{$recipientTwoHex}>\nendobj\n"
            . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $encryption = $metadata['encryption'];
        $review = $encryption['public_key_recipient_review'];
        $list = $review['recipient_lists'][0];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('Adobe.PubSec', $encryption['filter']);
        $t->same('adbe.pkcs7.s4', $encryption['subfilter']);
        $t->same('public_key_security_handler', $review['source']);
        $t->same('encryption_dictionary_recipients', $review['recipient_source_policy']);
        $t->same(2, $review['recipient_count']);
        $t->same(strlen($recipientOne) + strlen($recipientTwo), $review['recipient_bytes']);
        $t->same([hash('sha256', $recipientOne), hash('sha256', $recipientTwo)], $review['recipient_sha256']);
        $t->same('encryption_dictionary_recipients', $list['source']);
        $t->same(2, $list['recipient_count']);
        $t->same(0, $list['unresolved_recipient_count']);
        $t->same(strlen($recipientOne), $list['recipients'][0]['bytes']);
        $t->same(hash('sha256', $recipientTwo), $list['recipients'][1]['sha256']);
        $t->same('pkcs7_recipient_envelope', $list['recipients'][0]['permission_source']);
        $t->same(false, $review['permissions_decoded']);
        $t->same('cms_pkcs7_permission_decode_unavailable', $review['permission_decode_status']);
        $t->same(true, $review['requires_private_key_for_permission_review']);
        $t->same(false, $review['recipient_bytes_exposed']);
        $t->same(false, $review['recipient_certificates_exposed']);
        $t->same(false, $review['executes_cms_parse']);
        $t->same(false, $review['executes_decryption']);
        $t->true(is_string($encoded) && !str_contains($encoded, $recipientOne) && !str_contains($encoded, $recipientTwo));
        $t->true(is_string($encoded) && !str_contains($encoded, $recipientOneHex) && !str_contains($encoded, $recipientTwoHex));
    },
    'prioritizes encryption before XMP Info and OutputIntent metadata boundaries' => static function (TestRunner $t) use ($xmpPacket): void {
        $xmp = $xmpPacket([
            'title' => 'Encrypted XMP Review Title',
            'description' => 'Cleartext-looking encrypted metadata must not win',
        ]);
        $compressedXmp = gzcompress($xmp);
        $compressedProfile = gzcompress('Encrypted ICC profile bytes should not be trusted');
        if (!is_string($compressedXmp) || !is_string($compressedProfile)) {
            throw new RuntimeException('Unable to compress encrypted metadata priority fixture.');
        }

        $pdfFactory = static function (string $encryptMetadataValue) use ($compressedXmp, $compressedProfile): string {
            $encryptedContent = 'BT /F1 12 Tf 72 720 Td (Encrypted metadata priority leak) Tj ET';

            return "%PDF-1.7\n"
                . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R /OutputIntents [9 0 R] >>\nendobj\n"
                . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
                . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
                . "4 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
                . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
                . "6 0 obj\n<< /Title (Encrypted Info Title) /Author (Encrypted Info Author) /Producer (Encrypted Info Producer) >>\nendobj\n"
                . "7 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($compressedProfile) . " >>\nstream\n{$compressedProfile}\nendstream\nendobj\n"
                . "9 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Encrypted sRGB) /Info (Encrypted PDF/A) /DestOutputProfile 7 0 R >>\nendobj\n"
                . "10 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -64 /EncryptMetadata {$encryptMetadataValue} >>\nendobj\n"
                . "trailer\n<< /Root 1 0 R /Info 6 0 R /Encrypt 10 0 R >>\n%%EOF";
        };

        $encryptedMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdfFactory('true'));
        $encryptedEncoded = json_encode($encryptedMetadata, JSON_UNESCAPED_SLASHES);

        $t->same(['encryption'], $encryptedMetadata['source']);
        $t->same([], $encryptedMetadata['xmp']);
        $t->same([], $encryptedMetadata['info']);
        $t->same([], $encryptedMetadata['output_intents']);
        $t->true(!isset($encryptedMetadata['title']));
        $t->true(!isset($encryptedMetadata['pdfa']));
        $t->same(['xmp', 'info', 'output_intents'], $encryptedMetadata['encryption']['metadata_source_policy']['suppressed_sources']);
        $t->same([], $encryptedMetadata['encryption']['metadata_source_policy']['preserved_sources']);
        $t->same('suppressed_encrypted_metadata_stream', $encryptedMetadata['encryption']['metadata_source_policy']['xmp_stream_policy']);
        $t->same('suppressed_encrypted_document_strings', $encryptedMetadata['encryption']['metadata_source_policy']['info_dictionary_policy']);
        $t->same('suppressed_encrypted_stream_or_strings', $encryptedMetadata['encryption']['metadata_source_policy']['output_intents_policy']);
        $t->same('', (new PdfTextExtractor())->extractPlainText($pdfFactory('true')));
        $t->true(is_string($encryptedEncoded) && !str_contains($encryptedEncoded, 'Encrypted XMP Review Title'));
        $t->true(is_string($encryptedEncoded) && !str_contains($encryptedEncoded, 'Encrypted Info Title'));
        $t->true(is_string($encryptedEncoded) && !str_contains($encryptedEncoded, 'Encrypted sRGB'));

        $unencryptedMetadataStream = (new PdfMetadataExtractor())->extractDocumentMetadata($pdfFactory('false'));
        $unencryptedEncoded = json_encode($unencryptedMetadataStream, JSON_UNESCAPED_SLASHES);

        $t->same(['encryption', 'xmp'], $unencryptedMetadataStream['source']);
        $t->same('Encrypted XMP Review Title', $unencryptedMetadataStream['title']);
        $t->same('Cleartext-looking encrypted metadata must not win', $unencryptedMetadataStream['description']);
        $t->same(['info', 'output_intents'], $unencryptedMetadataStream['encryption']['metadata_source_policy']['suppressed_sources']);
        $t->same(['xmp'], $unencryptedMetadataStream['encryption']['metadata_source_policy']['preserved_sources']);
        $t->same('preserved_unencrypted_by_encrypt_metadata_false', $unencryptedMetadataStream['encryption']['metadata_source_policy']['xmp_stream_policy']);
        $t->same([], $unencryptedMetadataStream['info']);
        $t->same([], $unencryptedMetadataStream['output_intents']);
        $t->true(!isset($unencryptedMetadataStream['pdfa']));
        $t->true(is_string($unencryptedEncoded) && !str_contains($unencryptedEncoded, 'Encrypted Info Title'));
        $t->true(is_string($unencryptedEncoded) && !str_contains($unencryptedEncoded, 'Encrypted sRGB'));
    },
];
