<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefClassicRebuildBoundaryCurrentBasePdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale classic rebuild page) Tj T* (Old trailer root leak) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current classic rebuild page) Tj T* (Latest trailer boundary kept) Tj ET';

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 6\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . "trailer\n<< /Size 15 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(10, '<< /Type /Catalog /Pages 11 0 R >>');
    $addObject(11, '<< /Type /Pages /Kids [12 0 R] /Count 1 >>');
    $addObject(12, '<< /Type /Page /Parent 11 0 R /Resources << /Font << /F1 13 0 R >> >> /Contents 14 0 R >>');
    $addObject(13, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(14, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $pdf .= "xref\n"
        . "10 5\n"
        . $xrefRow($offsets[10])
        . $xrefRow($offsets[11])
        . $xrefRow($offsets[12])
        . $xrefRow($offsets[13])
        . $xrefRow($offsets[14])
        . "trailer\n<< /Size 15 /Root 10 0 R >>\n"
        . "startxref\n999999\n%%EOF";

    return $pdf;
};

$xrefClassicRebuildStaleStartxrefCurrentBasePdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale valid startxref page) Tj T* (Earlier trailer root leak) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current stale-pointer rebuild page) Tj T* (Stale startxref pointer repaired) Tj ET';

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 6\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . "trailer\n<< /Size 15 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(10, '<< /Type /Catalog /Pages 11 0 R >>');
    $addObject(11, '<< /Type /Pages /Kids [12 0 R] /Count 1 >>');
    $addObject(12, '<< /Type /Page /Parent 11 0 R /Resources << /Font << /F1 13 0 R >> >> /Contents 14 0 R >>');
    $addObject(13, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(14, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $pdf .= "xref\n"
        . "10 5\n"
        . $xrefRow($offsets[10])
        . $xrefRow($offsets[11])
        . $xrefRow($offsets[12])
        . $xrefRow($offsets[13])
        . $xrefRow($offsets[14])
        . "trailer\n<< /Size 15 /Root 10 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF";

    return $pdf;
};

$xrefClassicRebuildEofBoundedCurrentBasePdf = static function (): string {
    $currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current EOF Bounded XRef Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $trailingXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Trailing Garbage XRef Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current EOF bounded page) Tj T* (Post EOF xref ignored) Tj ET';
    $trailingContent = 'BT /F1 12 Tf 72 720 Td (Trailing garbage xref page) Tj T* (Post EOF root leak) Tj ET';

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(7, '<< /Title (Current EOF Bounded Info Title) /Author (Current Importer) >>');

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 8\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . $xrefRow($offsets[6])
        . $xrefRow($offsets[7])
        . "trailer\n<< /Size 28 /Root 1 0 R /Info 7 0 R >>\n"
        . "startxref\n999999\n%%EOF\n";

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, "<< /Length " . strlen($trailingContent) . " >>\nstream\n{$trailingContent}\nendstream");
    $addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($trailingXmp) . " >>\nstream\n{$trailingXmp}\nendstream");
    $addObject(27, '<< /Title (Trailing Garbage Info Title) /Author (Garbage Importer) >>');

    $pdf .= "xref\n"
        . "20 8\n"
        . $xrefRow($offsets[20])
        . $xrefRow($offsets[21])
        . $xrefRow($offsets[22])
        . $xrefRow($offsets[23])
        . $xrefRow($offsets[24])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[26])
        . $xrefRow($offsets[27])
        . "trailer\n<< /Size 28 /Root 20 0 R /Info 27 0 R >>\n";

    return $pdf;
};

$xrefClassicRebuildEmbeddedFilesCurrentBasePdf = static function (): array {
    $stalePayload = '<wp-export><post id="stale-classic-attachment"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-classic-attachment"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>');
    $addObject(2, '<< /Type /Pages /Kids [] /Count 0 >>');
    $addObject(6, '<< /Names [(stale-source.xml) 10 0 R] >>');
    $addObject(10, '<< /Type /Filespec /F (stale-source.xml) /Desc (Stale classic rebuild attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $addObject(11, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 12\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow(0, 0, 'f')
        . $xrefRow(0, 0, 'f')
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets[6])
        . $xrefRow(0, 0, 'f')
        . $xrefRow(0, 0, 'f')
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets[10])
        . $xrefRow($offsets[11])
        . "trailer\n<< /Size 32 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Names << /EmbeddedFiles 26 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [] /Count 0 >>');
    $addObject(26, '<< /Names [(current-source.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (current-source.xml) /Desc (Current classic rebuild attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $pdf .= "xref\n"
        . "20 12\n"
        . $xrefRow($offsets[20])
        . $xrefRow($offsets[21])
        . $xrefRow(0, 0, 'f')
        . $xrefRow(0, 0, 'f')
        . $xrefRow(0, 0, 'f')
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets[26])
        . $xrefRow(0, 0, 'f')
        . $xrefRow(0, 0, 'f')
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets[30])
        . $xrefRow($offsets[31])
        . "trailer\n<< /Size 32 /Root 20 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF";

    return [$pdf, $currentPayload, strtolower($currentChecksum)];
};

$xrefClassicRebuildCommentKeywordBoundaryCurrentBasePdf = static function (): array {
    $currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Comment-Bounded XRef Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $commentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Comment XRef Decoy Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current comment bounded page) Tj T* (Comment xref ignored) Tj ET';
    $commentContent = 'BT /F1 12 Tf 72 720 Td (Comment xref decoy page) Tj T* (Comment root leak) Tj ET';
    $currentPayload = '<wp-export><post id="current-comment-xref"/></wp-export>';
    $commentPayload = '<wp-export><post id="comment-xref-decoy"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(7, '<< /Title (Current Comment-Bounded Info Title) /Author (Current Comment Importer) >>');
    $addObject(8, '<< /Names [(current-comment-xref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (current-comment-xref.xml) /Desc (Current comment-bounded xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $pdf .= "xref\n"
        . "0 11\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . $xrefRow($offsets[6])
        . $xrefRow($offsets[7])
        . $xrefRow($offsets[8])
        . $xrefRow($offsets[9])
        . $xrefRow($offsets[10])
        . "trailer\n<< /Size 28 /Root 1 0 R /Info 7 0 R >>\n";

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, "<< /Length " . strlen($commentContent) . " >>\nstream\n{$commentContent}\nendstream");
    $addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($commentXmp) . " >>\nstream\n{$commentXmp}\nendstream");
    $addObject(27, '<< /Title (Comment XRef Decoy Info Title) /Author (Comment Decoy Importer) >>');
    $addObject(28, '<< /Names [(comment-xref-decoy.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (comment-xref-decoy.xml) /Desc (Comment xref decoy attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($commentPayload) . " >>\nstream\n{$commentPayload}\nendstream");

    $pdf .= "% xref\n"
        . "20 12\n"
        . $xrefRow($offsets[20])
        . $xrefRow($offsets[21])
        . $xrefRow($offsets[22])
        . $xrefRow($offsets[23])
        . $xrefRow($offsets[24])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[26])
        . $xrefRow($offsets[27])
        . $xrefRow($offsets[28])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[30])
        . $xrefRow($offsets[31])
        . "trailer\n<< /Size 28 /Root 20 0 R /Info 27 0 R >>\n"
        . "startxref\n999999\n%%EOF";

    return [$pdf, $currentPayload, strtolower($currentChecksum)];
};

$xrefClassicRebuildCommentedStartxrefBoundaryCurrentBasePdf = static function (): array {
    $currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Commented Startxref Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $decoyXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Commented Startxref Decoy Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current commented startxref page) Tj T* (Commented startxref ignored) Tj ET';
    $decoyContent = 'BT /F1 12 Tf 72 720 Td (Commented startxref decoy page) Tj T* (Post EOF startxref leak) Tj ET';
    $currentPayload = '<wp-export><post id="current-commented-startxref"/></wp-export>';
    $decoyPayload = '<wp-export><post id="decoy-commented-startxref"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(7, '<< /Title (Current Commented Startxref Info Title) /Author (Current Startxref Importer) >>');
    $addObject(8, '<< /Names [(current-commented-startxref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (current-commented-startxref.xml) /Desc (Current commented startxref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 11\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . $xrefRow($offsets[6])
        . $xrefRow($offsets[7])
        . $xrefRow($offsets[8])
        . $xrefRow($offsets[9])
        . $xrefRow($offsets[10])
        . "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n"
        . "startxref\n999999\n%%EOF\n";

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, "<< /Length " . strlen($decoyContent) . " >>\nstream\n{$decoyContent}\nendstream");
    $addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
    $addObject(27, '<< /Title (Commented Startxref Decoy Info Title) /Author (Decoy Startxref Importer) >>');
    $addObject(28, '<< /Names [(decoy-commented-startxref.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (decoy-commented-startxref.xml) /Desc (Decoy commented startxref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

    $decoyXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "20 12\n"
        . $xrefRow($offsets[20])
        . $xrefRow($offsets[21])
        . $xrefRow($offsets[22])
        . $xrefRow($offsets[23])
        . $xrefRow($offsets[24])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[26])
        . $xrefRow($offsets[27])
        . $xrefRow($offsets[28])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[30])
        . $xrefRow($offsets[31])
        . "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >>\n"
        . "% startxref\n{$decoyXrefOffset}\n%%EOF";

    return [$pdf, $currentPayload, strtolower($currentChecksum), $currentXrefOffset, $decoyXrefOffset];
};

$xrefClassicRebuildArrayDecoyBoundaryCurrentBasePdf = static function (): array {
    $currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Array-Bounded XRef Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $arrayDecoyXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Array Decoy XRef Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current array-bounded page) Tj T* (Array xref skipped) Tj ET';
    $arrayDecoyContent = 'BT /F1 12 Tf 72 720 Td (Array decoy xref page) Tj T* (Array root leak) Tj ET';
    $currentPayload = '<wp-export><post id="current-array-xref"/></wp-export>';
    $arrayDecoyPayload = '<wp-export><post id="array-xref-decoy"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(7, '<< /Title (Current Array-Bounded Info Title) /Author (Current Array Importer) >>');
    $addObject(8, '<< /Names [(current-array-xref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (current-array-xref.xml) /Desc (Current array-bounded xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 11\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . $xrefRow($offsets[6])
        . $xrefRow($offsets[7])
        . $xrefRow($offsets[8])
        . $xrefRow($offsets[9])
        . $xrefRow($offsets[10])
        . "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n";

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, "<< /Length " . strlen($arrayDecoyContent) . " >>\nstream\n{$arrayDecoyContent}\nendstream");
    $addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($arrayDecoyXmp) . " >>\nstream\n{$arrayDecoyXmp}\nendstream");
    $addObject(27, '<< /Title (Array Decoy XRef Info Title) /Author (Array Decoy Importer) >>');
    $addObject(28, '<< /Names [(array-xref-decoy.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (array-xref-decoy.xml) /Desc (Array decoy xref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($arrayDecoyPayload) . " >>\nstream\n{$arrayDecoyPayload}\nendstream");

    $arrayDecoyXrefOffset = strlen($pdf) + strlen("[ /Decoy ");
    $pdf .= "[ /Decoy xref\n"
        . "20 12\n"
        . $xrefRow($offsets[20])
        . $xrefRow($offsets[21])
        . $xrefRow($offsets[22])
        . $xrefRow($offsets[23])
        . $xrefRow($offsets[24])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[26])
        . $xrefRow($offsets[27])
        . $xrefRow($offsets[28])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[30])
        . $xrefRow($offsets[31])
        . "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >> ]\n"
        . "startxref\n999999\n%%EOF";

    return [$pdf, $currentPayload, strtolower($currentChecksum), $currentXrefOffset, $arrayDecoyXrefOffset];
};

$xrefClassicRebuildCompositeStartxrefBoundaryCurrentBasePdf = static function (): array {
    $currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Composite Startxref Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $compositeDecoyXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Composite Startxref Decoy Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current composite startxref page) Tj T* (Composite startxref ignored) Tj ET';
    $compositeDecoyContent = 'BT /F1 12 Tf 72 720 Td (Composite startxref decoy page) Tj T* (Composite startxref leak) Tj ET';
    $currentPayload = '<wp-export><post id="current-composite-startxref"/></wp-export>';
    $compositeDecoyPayload = '<wp-export><post id="decoy-composite-startxref"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, $streamObject($currentContent));
    $addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(7, '<< /Title (Current Composite Startxref Info) /Author (Current Composite Importer) >>');
    $addObject(8, '<< /Names [(current-composite-startxref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (current-composite-startxref.xml) /Desc (Current composite startxref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 11\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . $xrefRow($offsets[6])
        . $xrefRow($offsets[7])
        . $xrefRow($offsets[8])
        . $xrefRow($offsets[9])
        . $xrefRow($offsets[10])
        . "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n"
        . "startxref\n999999\n%%EOF\n";

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 24 0 R >>');
    $addObject(24, $streamObject($compositeDecoyContent));
    $addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($compositeDecoyXmp) . " >>\nstream\n{$compositeDecoyXmp}\nendstream");
    $addObject(27, '<< /Title (Composite Startxref Decoy Info) /Author (Composite Decoy Importer) >>');
    $addObject(28, '<< /Names [(decoy-composite-startxref.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (decoy-composite-startxref.xml) /Desc (Decoy composite startxref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($compositeDecoyPayload) . " >>\nstream\n{$compositeDecoyPayload}\nendstream");

    $compositeDecoyXrefOffset = strlen($pdf) + strlen("[ /Composite ");
    $pdf .= "[ /Composite xref\n"
        . "20 12\n"
        . $xrefRow($offsets[20])
        . $xrefRow($offsets[21])
        . $xrefRow($offsets[22])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[24])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[26])
        . $xrefRow($offsets[27])
        . $xrefRow($offsets[28])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[30])
        . $xrefRow($offsets[31])
        . "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >> startxref\n{$compositeDecoyXrefOffset}\n]\n%%EOF";

    return [$pdf, $currentPayload, strtolower($currentChecksum), $currentXrefOffset, $compositeDecoyXrefOffset];
};

$xrefClassicRebuildNameStartxrefBoundaryCurrentBasePdf = static function (): array {
    $currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Name Startxref Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $nameDecoyXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Name Startxref Decoy Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current name-startxref page) Tj T* (Name startxref ignored) Tj ET';
    $nameDecoyContent = 'BT /F1 12 Tf 72 720 Td (Name startxref decoy page) Tj T* (Name token root leak) Tj ET';
    $currentPayload = '<wp-export><post id="current-name-startxref"/></wp-export>';
    $nameDecoyPayload = '<wp-export><post id="decoy-name-startxref"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, $streamObject($currentContent));
    $addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(7, '<< /Title (Current Name Startxref Info) /Author (Current Name Importer) >>');
    $addObject(8, '<< /Names [(current-name-startxref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (current-name-startxref.xml) /Desc (Current name-startxref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 11\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . $xrefRow($offsets[6])
        . $xrefRow($offsets[7])
        . $xrefRow($offsets[8])
        . $xrefRow($offsets[9])
        . $xrefRow($offsets[10])
        . "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n"
        . "startxref\n999999\n%%EOF\n";

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 24 0 R >>');
    $addObject(24, $streamObject($nameDecoyContent));
    $addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($nameDecoyXmp) . " >>\nstream\n{$nameDecoyXmp}\nendstream");
    $addObject(27, '<< /Title (Name Startxref Decoy Info) /Author (Name Decoy Importer) >>');
    $addObject(28, '<< /Names [(decoy-name-startxref.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (decoy-name-startxref.xml) /Desc (Decoy name-startxref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($nameDecoyPayload) . " >>\nstream\n{$nameDecoyPayload}\nendstream");

    $nameDecoyXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "20 12\n"
        . $xrefRow($offsets[20])
        . $xrefRow($offsets[21])
        . $xrefRow($offsets[22])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[24])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[26])
        . $xrefRow($offsets[27])
        . $xrefRow($offsets[28])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[30])
        . $xrefRow($offsets[31])
        . "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >>\n"
        . "/startxref\n{$nameDecoyXrefOffset}\n%%EOF";

    return [$pdf, $currentPayload, strtolower($currentChecksum), $currentXrefOffset, $nameDecoyXrefOffset];
};

$xrefClassicRebuildNameDelimitedXrefBoundaryCurrentBasePdf = static function (): array {
    $currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Name-Delimited XRef Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $nameDelimitedXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Name Delimited XRef Decoy Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current name-delimited xref page) Tj T* (Name-delimited xref ignored) Tj ET';
    $nameDelimitedContent = 'BT /F1 12 Tf 72 720 Td (Name-delimited xref decoy page) Tj T* (Delimited xref root leak) Tj ET';
    $currentPayload = '<wp-export><post id="current-name-delimited-xref"/></wp-export>';
    $nameDelimitedPayload = '<wp-export><post id="decoy-name-delimited-xref"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, $streamObject($currentContent));
    $addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(7, '<< /Title (Current Name-Delimited XRef Info) /Author (Current Name-Delimited Importer) >>');
    $addObject(8, '<< /Names [(current-name-delimited-xref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (current-name-delimited-xref.xml) /Desc (Current name-delimited xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 11\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . $xrefRow($offsets[6])
        . $xrefRow($offsets[7])
        . $xrefRow($offsets[8])
        . $xrefRow($offsets[9])
        . $xrefRow($offsets[10])
        . "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n";

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 24 0 R >>');
    $addObject(24, $streamObject($nameDelimitedContent));
    $addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($nameDelimitedXmp) . " >>\nstream\n{$nameDelimitedXmp}\nendstream");
    $addObject(27, '<< /Title (Name Delimited XRef Decoy Info) /Author (Name Delimited Decoy Importer) >>');
    $addObject(28, '<< /Names [(decoy-name-delimited-xref.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (decoy-name-delimited-xref.xml) /Desc (Decoy name-delimited xref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($nameDelimitedPayload) . " >>\nstream\n{$nameDelimitedPayload}\nendstream");

    $nameDelimitedXrefOffset = strlen($pdf);
    $pdf .= "xref/Decoy\n"
        . "20 12\n"
        . $xrefRow($offsets[20])
        . $xrefRow($offsets[21])
        . $xrefRow($offsets[22])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[24])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[26])
        . $xrefRow($offsets[27])
        . $xrefRow($offsets[28])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[30])
        . $xrefRow($offsets[31])
        . "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >>\n"
        . "startxref\n999999\n%%EOF";

    return [$pdf, $currentPayload, strtolower($currentChecksum), $currentXrefOffset, $nameDelimitedXrefOffset];
};

$xrefClassicRebuildNameOffsetStartxrefBoundaryCurrentBasePdf = static function (): array {
    $currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Name-Offset XRef Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $nameOffsetXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Name Offset XRef Decoy Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current name-offset xref page) Tj T* (Name-offset startxref repaired) Tj ET';
    $nameOffsetContent = 'BT /F1 12 Tf 72 720 Td (Name-offset xref decoy page) Tj T* (Name-offset root leak) Tj ET';
    $currentPayload = '<wp-export><post id="current-name-offset-xref"/></wp-export>';
    $nameOffsetPayload = '<wp-export><post id="decoy-name-offset-xref"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, $streamObject($currentContent));
    $addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(7, '<< /Title (Current Name-Offset XRef Info) /Author (Current Name-Offset Importer) >>');
    $addObject(8, '<< /Names [(current-name-offset-xref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (current-name-offset-xref.xml) /Desc (Current name-offset xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 11\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . $xrefRow($offsets[6])
        . $xrefRow($offsets[7])
        . $xrefRow($offsets[8])
        . $xrefRow($offsets[9])
        . $xrefRow($offsets[10])
        . "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n";

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 24 0 R >>');
    $addObject(24, $streamObject($nameOffsetContent));
    $addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($nameOffsetXmp) . " >>\nstream\n{$nameOffsetXmp}\nendstream");
    $addObject(27, '<< /Title (Name Offset XRef Decoy Info) /Author (Name Offset Decoy Importer) >>');
    $addObject(28, '<< /Names [(decoy-name-offset-xref.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (decoy-name-offset-xref.xml) /Desc (Decoy name-offset xref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($nameOffsetPayload) . " >>\nstream\n{$nameOffsetPayload}\nendstream");

    $nameOffsetXrefOffset = strlen($pdf);
    $pdf .= "/xref\n"
        . "20 12\n"
        . $xrefRow($offsets[20])
        . $xrefRow($offsets[21])
        . $xrefRow($offsets[22])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[24])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[26])
        . $xrefRow($offsets[27])
        . $xrefRow($offsets[28])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[30])
        . $xrefRow($offsets[31])
        . "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >>\n"
        . "startxref\n" . ($nameOffsetXrefOffset + 1) . "\n%%EOF";

    return [$pdf, $currentPayload, strtolower($currentChecksum), $currentXrefOffset, $nameOffsetXrefOffset + 1];
};

$xrefClassicRebuildLinearizedHintStartxrefBoundaryCurrentBasePdf = static function (): array {
    $currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Hint-Bounded XRef Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $hintDecoyXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Hint Startxref Decoy Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current hint-bounded xref page) Tj T* (Linearized hint startxref ignored) Tj ET';
    $hintDecoyContent = 'BT /F1 12 Tf 72 720 Td (Hint startxref decoy page) Tj T* (Linearized hint root leak) Tj ET';
    $currentPayload = '<wp-export><post id="current-hint-bounded-xref"/></wp-export>';
    $hintDecoyPayload = '<wp-export><post id="decoy-hint-bounded-xref"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, '<< /Linearized 1 /L 0000000000 /H [ 0000000000 0000000000 ] /O 4 /E 0 /N 1 /T 0 >>');
    $addObject(2, '<< /Type /Catalog /Pages 3 0 R /Metadata 7 0 R /Names << /EmbeddedFiles 9 0 R >> >>');
    $addObject(3, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
    $addObject(4, '<< /Type /Page /Parent 3 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R >>');
    $addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(6, $streamObject($currentContent));
    $addObject(7, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(8, '<< /Title (Current Hint-Bounded XRef Info) /Author (Current Hint Importer) >>');
    $addObject(9, '<< /Names [(current-hint-bounded-xref.xml) 10 0 R] >>');
    $addObject(10, '<< /Type /Filespec /F (current-hint-bounded-xref.xml) /Desc (Current hint-bounded xref attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $addObject(11, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n0 12\n";
    for ($objectNumber = 0; $objectNumber <= 11; $objectNumber++) {
        $pdf .= $objectNumber === 0
            ? $xrefRow(0, 65535, 'f')
            : $xrefRow($offsets[$objectNumber] ?? 0, 0, isset($offsets[$objectNumber]) ? 'n' : 'f');
    }
    $pdf .= "trailer\n<< /Size 32 /Root 2 0 R /Info 8 0 R >>\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF\n";

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, $streamObject($hintDecoyContent));
    $addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($hintDecoyXmp) . " >>\nstream\n{$hintDecoyXmp}\nendstream");
    $addObject(27, '<< /Title (Hint Startxref Decoy Info) /Author (Hint Decoy Importer) >>');
    $addObject(28, '<< /Names [(decoy-hint-bounded-xref.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (decoy-hint-bounded-xref.xml) /Desc (Decoy hint-bounded xref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($hintDecoyPayload) . " >>\nstream\n{$hintDecoyPayload}\nendstream");

    $hintDecoyXrefOffset = strlen($pdf);
    $pdf .= "xref\n20 12\n";
    for ($objectNumber = 20; $objectNumber <= 31; $objectNumber++) {
        $pdf .= $xrefRow($offsets[$objectNumber] ?? 0, 0, isset($offsets[$objectNumber]) ? 'n' : 'f');
    }
    $pdf .= "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >>\n";
    $hintStart = strlen($pdf);
    $pdf .= "startxref\n{$hintDecoyXrefOffset}\n%%EOF";
    $hintLength = strlen($pdf) - $hintStart;
    $pdf = str_replace(
        '0000000000 /H [ 0000000000 0000000000',
        sprintf('%010d /H [ %010d %010d', strlen($pdf), $hintStart, $hintLength),
        $pdf
    );

    return [$pdf, $currentPayload, strtolower($currentChecksum), $currentXrefOffset, $hintDecoyXrefOffset, $hintStart, $hintLength];
};

$xrefClassicRebuildMalformedRowBoundaryCurrentBasePdf = static function (): array {
    $currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Malformed-Row XRef Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $decoyXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Malformed Row Decoy Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current malformed-row xref page) Tj T* (Malformed rebuild table skipped) Tj ET';
    $decoyContent = 'BT /F1 12 Tf 72 720 Td (Malformed-row decoy page) Tj T* (Partial xref row leak) Tj ET';
    $currentPayload = '<wp-export><post id="current-malformed-row-xref"/></wp-export>';
    $decoyPayload = '<wp-export><post id="decoy-malformed-row-xref"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, $streamObject($currentContent));
    $addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(7, '<< /Title (Current Malformed-Row XRef Info) /Author (Current Malformed-Row Importer) >>');
    $addObject(8, '<< /Names [(current-malformed-row-xref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (current-malformed-row-xref.xml) /Desc (Current malformed-row xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 11\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . $xrefRow($offsets[6])
        . $xrefRow($offsets[7])
        . $xrefRow($offsets[8])
        . $xrefRow($offsets[9])
        . $xrefRow($offsets[10])
        . "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n";

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, $streamObject($decoyContent));
    $addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
    $addObject(27, '<< /Title (Malformed Row Decoy Info) /Author (Malformed Row Decoy Importer) >>');
    $addObject(28, '<< /Names [(decoy-malformed-row-xref.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (decoy-malformed-row-xref.xml) /Desc (Decoy malformed-row xref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

    $malformedXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "20 12\n"
        . $xrefRow($offsets[20])
        . $xrefRow($offsets[21])
        . $xrefRow($offsets[22])
        . $xrefRow($offsets[23])
        . $xrefRow($offsets[24])
        . "0000000000 broken row inside declared xref subsection\n"
        . $xrefRow($offsets[26])
        . $xrefRow($offsets[27])
        . $xrefRow($offsets[28])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[30])
        . $xrefRow($offsets[31])
        . "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >>\n"
        . "startxref\n999999\n%%EOF";

    return [$pdf, $currentPayload, strtolower($currentChecksum), $currentXrefOffset, $malformedXrefOffset];
};

$xrefClassicRebuildPunctuationRowBoundaryCurrentBasePdf = static function (): array {
    $currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Row-State XRef Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $decoyXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Punctuation Row Decoy Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current row-state xref page) Tj T* (Punctuation row skipped) Tj ET';
    $decoyContent = 'BT /F1 12 Tf 72 720 Td (Punctuation row decoy page) Tj T* (Row-state root leak) Tj ET';
    $currentPayload = '<wp-export><post id="current-row-state-xref"/></wp-export>';
    $decoyPayload = '<wp-export><post id="decoy-row-state-xref"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    $xrefRow = static fn (
        int $offset,
        int $generation = 0,
        string $state = 'n',
        string $suffix = ' '
    ): string => sprintf("%010d %05d %s%s\n", $offset, $generation, $state, $suffix);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, $streamObject($currentContent));
    $addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(7, '<< /Title (Current Row-State XRef Info) /Author (Current Row-State Importer) >>');
    $addObject(8, '<< /Names [(current-row-state-xref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (current-row-state-xref.xml) /Desc (Current row-state xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 11\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1], 0, 'n', ' % valid row comment')
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . $xrefRow($offsets[6])
        . $xrefRow($offsets[7])
        . $xrefRow($offsets[8])
        . $xrefRow($offsets[9])
        . $xrefRow($offsets[10])
        . "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n";

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, $streamObject($decoyContent));
    $addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
    $addObject(27, '<< /Title (Punctuation Row Decoy Info) /Author (Punctuation Row Decoy Importer) >>');
    $addObject(28, '<< /Names [(decoy-row-state-xref.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (decoy-row-state-xref.xml) /Desc (Decoy row-state xref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

    $punctuationXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "20 12\n"
        . $xrefRow($offsets[20], 0, 'n', '.bad')
        . $xrefRow($offsets[21], 0, 'n', '.bad')
        . $xrefRow($offsets[22], 0, 'n', '.bad')
        . $xrefRow($offsets[23], 0, 'n', '.bad')
        . $xrefRow($offsets[24], 0, 'n', '.bad')
        . $xrefRow(0, 65535, 'f', '.bad')
        . $xrefRow($offsets[26], 0, 'n', '.bad')
        . $xrefRow($offsets[27], 0, 'n', '.bad')
        . $xrefRow($offsets[28], 0, 'n', '.bad')
        . $xrefRow(0, 65535, 'f', '.bad')
        . $xrefRow($offsets[30], 0, 'n', '.bad')
        . $xrefRow($offsets[31], 0, 'n', '.bad')
        . "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >>\n"
        . "startxref\n999999\n%%EOF";

    return [$pdf, $currentPayload, strtolower($currentChecksum), $currentXrefOffset, $punctuationXrefOffset];
};

$xrefClassicRebuildCommentOnlyRowsCurrentBasePdf = static function (): array {
    $staleXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Stale Comment-Row XRef Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Comment-Row XRef Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale comment-row xref page) Tj T* (Comment-row root leak) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current comment-row xref page) Tj T* (Comment-only rows skipped) Tj ET';
    $stalePayload = '<wp-export><post id="stale-comment-row-xref"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-comment-row-xref"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, $streamObject($staleContent));
    $addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
    $addObject(27, '<< /Title (Stale Comment-Row XRef Info) /Author (Stale Comment Importer) >>');
    $addObject(28, '<< /Names [(stale-comment-row-xref.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (stale-comment-row-xref.xml) /Desc (Stale comment-row xref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "20 12\n"
        . $xrefRow($offsets[20])
        . $xrefRow($offsets[21])
        . $xrefRow($offsets[22])
        . $xrefRow($offsets[23])
        . $xrefRow($offsets[24])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[26])
        . $xrefRow($offsets[27])
        . $xrefRow($offsets[28])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[30])
        . $xrefRow($offsets[31])
        . "trailer\n<< /Size 64 /Root 20 0 R /Info 27 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, $streamObject($currentContent));
    $addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(7, '<< /Title (Current Comment-Row XRef Info) /Author (Current Comment Importer) >>');
    $addObject(8, '<< /Names [(current-comment-row-xref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (current-comment-row-xref.xml) /Desc (Current comment-row xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 11 % current subsection header comment\n"
        . "% comment before the free row is legal xref whitespace\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . "% comment between xref rows must not terminate the table\n"
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . $xrefRow($offsets[6])
        . $xrefRow($offsets[7])
        . "   % indented comment before EmbeddedFiles rows\n"
        . $xrefRow($offsets[8])
        . $xrefRow($offsets[9])
        . $xrefRow($offsets[10])
        . "trailer\n<< /Size 64 /Root 1 0 R /Info 7 0 R /Prev {$previousXrefOffset} >>\n"
        . "startxref\n999999\n%%EOF";

    return [$pdf, $currentPayload, strtolower($currentChecksum), $previousXrefOffset, $currentXrefOffset];
};

$xrefClassicRebuildTrailingSubsectionBoundaryCurrentBasePdf = static function (): array {
    $staleXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Stale Trailing-Subsection XRef Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Trailing-Subsection XRef Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale trailing-subsection xref page) Tj T* (Malformed trailing subsection leak) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current trailing-subsection xref page) Tj T* (Completed subsection preserved) Tj ET';
    $stalePayload = '<wp-export><post id="stale-trailing-subsection-xref"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-trailing-subsection-xref"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, $streamObject($staleContent));
    $addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
    $addObject(27, '<< /Title (Stale Trailing-Subsection XRef Info) /Author (Stale Importer) >>');
    $addObject(28, '<< /Names [(stale-trailing-subsection-xref.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (stale-trailing-subsection-xref.xml) /Desc (Stale trailing-subsection xref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "20 12\n"
        . $xrefRow($offsets[20])
        . $xrefRow($offsets[21])
        . $xrefRow($offsets[22])
        . $xrefRow($offsets[23])
        . $xrefRow($offsets[24])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[26])
        . $xrefRow($offsets[27])
        . $xrefRow($offsets[28])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[30])
        . $xrefRow($offsets[31])
        . "trailer\n<< /Size 64 /Root 20 0 R /Info 27 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, $streamObject($currentContent));
    $addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(7, '<< /Title (Current Trailing-Subsection XRef Info) /Author (Current Importer) >>');
    $addObject(8, '<< /Names [(current-trailing-subsection-xref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (current-trailing-subsection-xref.xml) /Desc (Current trailing-subsection xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 11\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . $xrefRow($offsets[6])
        . $xrefRow($offsets[7])
        . $xrefRow($offsets[8])
        . $xrefRow($offsets[9])
        . $xrefRow($offsets[10])
        . "40 1\n"
        . "malformed trailing subsection row after completed xref subsection\n"
        . "trailer\n<< /Size 64 /Root 1 0 R /Info 7 0 R /Prev {$previousXrefOffset} >>\n"
        . "startxref\n999999\n%%EOF";

    return [$pdf, $currentPayload, strtolower($currentChecksum), $previousXrefOffset, $currentXrefOffset];
};

$xrefClassicRebuildLiteralStringDecoyBoundaryCurrentBasePdf = static function (): array {
    $currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Literal XRef Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $decoyXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Literal String XRef Decoy Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current literal-string xref page) Tj T* (Literal xref decoy skipped) Tj ET';
    $decoyContent = 'BT /F1 12 Tf 72 720 Td (Literal xref decoy page) Tj T* (String xref root leak) Tj ET';
    $currentPayload = '<wp-export><post id="current-literal-string-xref"/></wp-export>';
    $decoyPayload = '<wp-export><post id="decoy-literal-string-xref"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, $streamObject($currentContent));
    $addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(7, '<< /Title (Current Literal XRef Info) /Author (Current Literal Importer) >>');
    $addObject(8, '<< /Names [(current-literal-string-xref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (current-literal-string-xref.xml) /Desc (Current literal-string xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 11\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . $xrefRow($offsets[6])
        . $xrefRow($offsets[7])
        . $xrefRow($offsets[8])
        . $xrefRow($offsets[9])
        . $xrefRow($offsets[10])
        . "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n";

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, $streamObject($decoyContent));
    $addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
    $addObject(27, '<< /Title (Literal String XRef Decoy Info) /Author (Literal Decoy Importer) >>');
    $addObject(28, '<< /Names [(decoy-literal-string-xref.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (decoy-literal-string-xref.xml) /Desc (Decoy literal-string xref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

    $literalXrefOffset = strlen($pdf) + 1;
    $pdf .= "(xref\n"
        . "20 12\n"
        . $xrefRow($offsets[20])
        . $xrefRow($offsets[21])
        . $xrefRow($offsets[22])
        . $xrefRow($offsets[23])
        . $xrefRow($offsets[24])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[26])
        . $xrefRow($offsets[27])
        . $xrefRow($offsets[28])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[30])
        . $xrefRow($offsets[31])
        . "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >>)\n"
        . "startxref\n{$literalXrefOffset}\n%%EOF";

    return [$pdf, $currentPayload, strtolower($currentChecksum), $currentXrefOffset, $literalXrefOffset];
};

$xrefClassicRebuildStreamTrailerBoundaryCurrentBasePdf = static function (): array {
    $currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Stream-Trailer XRef Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $decoyXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Stream Trailer Decoy Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current stream-trailer xref page) Tj T* (Stream-owned trailer skipped) Tj ET';
    $decoyContent = 'BT /F1 12 Tf 72 720 Td (Stream trailer decoy page) Tj T* (Stream-owned trailer leak) Tj ET';
    $currentPayload = '<wp-export><post id="current-stream-trailer-xref"/></wp-export>';
    $decoyPayload = '<wp-export><post id="decoy-stream-trailer-xref"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, $streamObject($currentContent));
    $addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(7, '<< /Title (Current Stream-Trailer XRef Info) /Author (Current Stream-Trailer Importer) >>');
    $addObject(8, '<< /Names [(current-stream-trailer-xref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (current-stream-trailer-xref.xml) /Desc (Current stream-trailer xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, $streamObject($decoyContent));
    $addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
    $addObject(27, '<< /Title (Stream Trailer Decoy Info) /Author (Decoy Stream-Trailer Importer) >>');
    $addObject(28, '<< /Names [(decoy-stream-trailer-xref.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (decoy-stream-trailer-xref.xml) /Desc (Decoy stream-trailer xref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n0 32\n";
    for ($objectNumber = 0; $objectNumber <= 31; $objectNumber++) {
        $pdf .= $objectNumber === 0
            ? $xrefRow(0, 65535, 'f')
            : $xrefRow($offsets[$objectNumber] ?? 0, 0, isset($offsets[$objectNumber]) ? 'n' : 'f');
    }

    $fakeTrailer = "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >>\n";
    $fakeTrailerOffset = strlen($pdf) + strlen("40 0 obj\n<< /Length " . strlen($fakeTrailer) . " >>\nstream\n");
    $pdf .= "40 0 obj\n<< /Length " . strlen($fakeTrailer) . " >>\nstream\n"
        . $fakeTrailer
        . "endstream\nendobj\n";
    $realTrailerOffset = strlen($pdf);
    $pdf .= "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n"
        . "startxref\n999999\n%%EOF";

    return [$pdf, $currentPayload, strtolower($currentChecksum), $currentXrefOffset, $fakeTrailerOffset, $realTrailerOffset];
};

$xrefClassicRebuildPostStartxrefTrailerBoundaryCurrentBasePdf = static function (): array {
    $currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Post-StartXRef Trailer Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $decoyXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Post StartXRef Trailer Decoy Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current post-startxref trailer page) Tj T* (Late trailer boundary kept) Tj ET';
    $decoyContent = 'BT /F1 12 Tf 72 720 Td (Post startxref trailer decoy page) Tj T* (Late trailer root leak) Tj ET';
    $currentPayload = '<wp-export><post id="current-post-startxref-trailer-xref"/></wp-export>';
    $decoyPayload = '<wp-export><post id="decoy-post-startxref-trailer-xref"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, $streamObject($currentContent));
    $addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(7, '<< /Title (Current Post-StartXRef Trailer Info) /Author (Current Post-StartXRef Importer) >>');
    $addObject(8, '<< /Names [(current-post-startxref-trailer-xref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (current-post-startxref-trailer-xref.xml) /Desc (Current post-startxref trailer attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n0 11\n";
    for ($objectNumber = 0; $objectNumber <= 10; $objectNumber++) {
        $pdf .= $objectNumber === 0
            ? $xrefRow(0, 65535, 'f')
            : $xrefRow($offsets[$objectNumber]);
    }
    $pdf .= "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF\n";

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, $streamObject($decoyContent));
    $addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
    $addObject(27, '<< /Title (Post StartXRef Trailer Decoy Info) /Author (Post StartXRef Decoy Importer) >>');
    $addObject(28, '<< /Names [(decoy-post-startxref-trailer-xref.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (decoy-post-startxref-trailer-xref.xml) /Desc (Decoy post-startxref trailer attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

    $postStartxrefXrefOffset = strlen($pdf);
    $pdf .= "xref\n20 12\n"
        . $xrefRow($offsets[20])
        . $xrefRow($offsets[21])
        . $xrefRow($offsets[22])
        . $xrefRow($offsets[23])
        . $xrefRow($offsets[24])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[26])
        . $xrefRow($offsets[27])
        . $xrefRow($offsets[28])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[30])
        . $xrefRow($offsets[31])
        . "startxref\n{$postStartxrefXrefOffset}\n%%EOF\n"
        . "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >>\n";

    return [$pdf, $currentPayload, strtolower($currentChecksum), $currentXrefOffset, $postStartxrefXrefOffset];
};

$xrefClassicRebuildForwardPrevBoundaryCurrentBasePdf = static function (): array {
    $currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Forward Prev XRef Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $decoyXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Forward Prev Decoy Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current forward-prev xref page) Tj T* (Forward Prev repaired backward) Tj ET';
    $decoyContent = 'BT /F1 12 Tf 72 720 Td (Forward Prev decoy page) Tj T* (Post EOF Prev root leak) Tj ET';
    $currentPayload = '<wp-export><post id="current-forward-prev-xref"/></wp-export>';
    $decoyPayload = '<wp-export><post id="decoy-forward-prev-xref"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, $streamObject($currentContent));
    $addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(7, '<< /Title (Current Forward Prev XRef Info) /Author (Current Forward Prev Importer) >>');
    $addObject(8, '<< /Names [(current-forward-prev-xref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (current-forward-prev-xref.xml) /Desc (Current forward-prev xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 11\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . $xrefRow($offsets[6])
        . $xrefRow($offsets[7])
        . $xrefRow($offsets[8])
        . $xrefRow($offsets[9])
        . $xrefRow($offsets[10])
        . "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "1 1\n"
        . $xrefRow($offsets[1])
        . "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R /Prev 0000000000 >>\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF\n";

    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, $streamObject($decoyContent));
    $addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
    $addObject(7, '<< /Title (Forward Prev Decoy Info) /Author (Forward Prev Decoy Importer) >>');
    $addObject(8, '<< /Names [(decoy-forward-prev-xref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (decoy-forward-prev-xref.xml) /Desc (Forward Prev decoy attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

    $forwardPrevXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 11\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . $xrefRow($offsets[6])
        . $xrefRow($offsets[7])
        . $xrefRow($offsets[8])
        . $xrefRow($offsets[9])
        . $xrefRow($offsets[10])
        . "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n";

    $pdf = str_replace('/Prev 0000000000', '/Prev ' . sprintf('%010d', $forwardPrevXrefOffset), $pdf);

    return [$pdf, $currentPayload, strtolower($currentChecksum), $previousXrefOffset, $currentXrefOffset, $forwardPrevXrefOffset];
};

$xrefClassicRebuildStreamCompositeSkipBoundaryCurrentBasePdf = static function (): array {
    $currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Stream-Composite XRef Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale stream-composite xref page) Tj T* (Stream composite hid current xref) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current stream-composite xref page) Tj T* (Real xref after stream composite selected) Tj ET';
    $currentPayload = '<wp-export><post id="current-stream-composite-xref"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, $streamObject($staleContent));

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "20 5\n"
        . $xrefRow($offsets[20])
        . $xrefRow($offsets[21])
        . $xrefRow($offsets[22])
        . $xrefRow($offsets[23])
        . $xrefRow($offsets[24])
        . "trailer\n<< /Size 64 /Root 20 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $streamCompositeOffset = $addObject(
        40,
        $streamObject('[ stream-owned array without close before the current top-level xref')
    );
    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, $streamObject($currentContent));
    $addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(7, '<< /Title (Current Stream-Composite XRef Info) /Author (Current Stream Importer) >>');
    $addObject(8, '<< /Names [(current-stream-composite-xref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (current-stream-composite-xref.xml) /Desc (Current stream-composite xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 11\n"
        . $xrefRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 10; $objectNumber++) {
        $pdf .= $xrefRow($offsets[$objectNumber]);
    }
    $pdf .= "trailer\n<< /Size 64 /Root 1 0 R /Info 7 0 R /Prev {$previousXrefOffset} >>\n"
        . "startxref\n999999\n%%EOF\n]\n";

    return [$pdf, $currentPayload, strtolower($currentChecksum), $previousXrefOffset, $streamCompositeOffset, $currentXrefOffset];
};

$xrefClassicRebuildMalformedHexOpenerBoundaryCurrentBasePdf = static function (): array {
    $staleXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Stale Malformed Hex XRef Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Malformed Hex XRef Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale malformed-hex xref page) Tj T* (Malformed hex root leak) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current malformed-hex xref page) Tj T* (Malformed hex opener recovered) Tj ET';
    $stalePayload = '<wp-export><post id="stale-malformed-hex-xref"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-malformed-hex-xref"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, $streamObject($staleContent));
    $addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
    $addObject(7, '<< /Title (Stale Malformed Hex XRef Info) /Author (Stale Hex Importer) >>');
    $addObject(8, '<< /Names [(stale-malformed-hex-xref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (stale-malformed-hex-xref.xml) /Desc (Stale malformed-hex xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 11\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . $xrefRow($offsets[6])
        . $xrefRow($offsets[7])
        . $xrefRow($offsets[8])
        . $xrefRow($offsets[9])
        . $xrefRow($offsets[10])
        . "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, $streamObject($currentContent));
    $addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(27, '<< /Title (Current Malformed Hex XRef Info) /Author (Current Hex Importer) >>');
    $addObject(28, '<< /Names [(current-malformed-hex-xref.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (current-malformed-hex-xref.xml) /Desc (Current malformed-hex xref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $malformedHexOpenerOffset = strlen($pdf);
    $pdf .= "<not-a-hex-string before the current classic xref table\n";
    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "20 12\n"
        . $xrefRow($offsets[20])
        . $xrefRow($offsets[21])
        . $xrefRow($offsets[22])
        . $xrefRow($offsets[23])
        . $xrefRow($offsets[24])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[26])
        . $xrefRow($offsets[27])
        . $xrefRow($offsets[28])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[30])
        . $xrefRow($offsets[31])
        . "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >>\n"
        . "startxref\n999999\n%%EOF";

    return [$pdf, $currentPayload, strtolower($currentChecksum), $previousXrefOffset, $malformedHexOpenerOffset, $currentXrefOffset];
};

$xrefClassicRebuildDamagedRootRowCurrentBasePdf = static function (): array {
    $staleXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Stale Damaged Root Row Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Damaged Root Row Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale damaged root-row xref page) Tj T* (Older row offset leak) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current damaged root-row xref page) Tj T* (Root row rebuilt) Tj ET';
    $stalePayload = '<wp-export><post id="stale-damaged-root-row-xref"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-damaged-root-row-xref"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, $streamObject($staleContent));
    $addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
    $addObject(7, '<< /Title (Stale Damaged Root Row Info) /Author (Stale Importer) >>');
    $addObject(8, '<< /Names [(stale-damaged-root-row-xref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (stale-damaged-root-row-xref.xml) /Desc (Stale damaged root-row xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 11\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . $xrefRow($offsets[6])
        . $xrefRow($offsets[7])
        . $xrefRow($offsets[8])
        . $xrefRow($offsets[9])
        . $xrefRow($offsets[10])
        . "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, $streamObject($currentContent));
    $addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(27, '<< /Title (Current Damaged Root Row Info) /Author (Current Importer) >>');
    $addObject(28, '<< /Names [(current-damaged-root-row-xref.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (current-damaged-root-row-xref.xml) /Desc (Current damaged root-row xref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "20 12\n"
        . $xrefRow(0)
        . $xrefRow($offsets[21])
        . $xrefRow($offsets[22])
        . $xrefRow($offsets[23])
        . $xrefRow($offsets[24])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[26])
        . $xrefRow($offsets[27])
        . $xrefRow($offsets[28])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[30])
        . $xrefRow($offsets[31])
        . "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >>\n"
        . "startxref\n999999\n%%EOF";

    return [$pdf, $currentPayload, strtolower($currentChecksum), $previousXrefOffset, $currentXrefOffset, $offsets[20]];
};

return [
    'rebuilds damaged startxref from the latest classic xref trailer boundary before WordPress text extraction' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefClassicRebuildBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current classic rebuild page', 'Latest trailer boundary kept'], $extractor->extractTextLines($pdf));
        $t->same(['Current classic rebuild page', 'Latest trailer boundary kept'], $extractor->extractTextRuns($pdf));
        $t->same("Current classic rebuild page\nLatest trailer boundary kept", $text);
        $t->same("Current classic rebuild page\nLatest trailer boundary kept\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale classic rebuild page'));
        $t->true(!str_contains($text, 'Old trailer root leak'));
        $t->true(!str_contains($text, "\0"));
    },
    'rebuilds stale but valid startxref from the later classic xref trailer boundary before WordPress text extraction' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildStaleStartxrefCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefClassicRebuildStaleStartxrefCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current stale-pointer rebuild page', 'Stale startxref pointer repaired'], $extractor->extractTextLines($pdf));
        $t->same(['Current stale-pointer rebuild page', 'Stale startxref pointer repaired'], $extractor->extractTextRuns($pdf));
        $t->same("Current stale-pointer rebuild page\nStale startxref pointer repaired", $text);
        $t->same("Current stale-pointer rebuild page\nStale startxref pointer repaired\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale valid startxref page'));
        $t->true(!str_contains($text, 'Earlier trailer root leak'));
        $t->true(!str_contains($text, "\0"));
    },
    'bounds classic xref rebuild before trailing EOF garbage tables' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildEofBoundedCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefClassicRebuildEofBoundedCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);

        $t->same(['Current EOF bounded page', 'Post EOF xref ignored'], $extractor->extractTextLines($pdf));
        $t->same(['Current EOF bounded page', 'Post EOF xref ignored'], $extractor->extractTextRuns($pdf));
        $t->same("Current EOF bounded page\nPost EOF xref ignored", $text);
        $t->same("Current EOF bounded page\nPost EOF xref ignored\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current EOF Bounded XRef Title', $metadata['title']);
        $t->same('Current EOF Bounded Info Title', $metadata['info']['Title']);
        $t->same('Current Importer', $metadata['info']['Author']);
        $t->true(!str_contains($text, 'Trailing garbage xref page'));
        $t->true(!str_contains($text, 'Post EOF root leak'));
        $t->true(!str_contains(json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '', 'Trailing Garbage'));
        $t->true(!str_contains($text, "\0"));
    },
    'rebuilds stale classic startxref before EmbeddedFiles name-tree attachment import' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildEmbeddedFilesCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum] = $xrefClassicRebuildEmbeddedFilesCurrentBasePdf();
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('current-source.xml', $file['name']);
        $t->same('current-source.xml', $file['filename']);
        $t->same('Current classic rebuild attachment', $file['description']);
        $t->same('Source', $file['relationship']);
        $t->same('text/xml', $file['mime_type']);
        $t->same(30, $file['file_spec_object']);
        $t->same(31, $file['embedded_file_object']);
        $t->same($currentPayload, $file['content']);
        $t->same(strlen($currentPayload), $file['declared_size']);
        $t->same($currentChecksum, $file['checksum']);
        $t->same(true, $file['checksum_matches']);
        $t->true(!str_contains($encodedFiles, 'stale-source.xml'));
        $t->true(!str_contains($encodedFiles, 'stale-classic-attachment'));
    },
    'rebuilds stale classic startxref before native attachment preflight import' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildEmbeddedFilesCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum] = $xrefClassicRebuildEmbeddedFilesCurrentBasePdf();
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(strlen($currentPayload), $attachmentSummary['total_bytes']);
        $t->same(['current-source.xml'], $attachmentSummary['filenames']);
        $attachment = $attachmentSummary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('current-source.xml', $attachment['name_key']);
        $t->same('current-source.xml', $attachment['filename']);
        $t->same('Current classic rebuild attachment', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(30, $attachment['file_spec_object_id']);
        $t->same(31, $attachment['stream_object_id']);
        $t->same(strlen($currentPayload), $attachment['declared_size']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same(strlen($currentPayload), $attachment['byte_length']);
        $t->same(hash('sha256', $currentPayload), $attachment['sha256']);
        $t->same($currentChecksum, $attachment['checksum_hex']);
        $t->same($currentChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
        $t->true(!str_contains($encodedAttachmentSummary, 'stale-source.xml'));
        $t->true(!str_contains($encodedAttachmentSummary, 'stale-classic-attachment'));
    },
    'skips commented xref keywords during classic rebuild before metadata root selection' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildCommentKeywordBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        [$pdf, $currentPayload, $currentChecksum] = $xrefClassicRebuildCommentKeywordBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';

        $t->same(['Current comment bounded page', 'Comment xref ignored'], $extractor->extractTextLines($pdf));
        $t->same(['Current comment bounded page', 'Comment xref ignored'], $extractor->extractTextRuns($pdf));
        $t->same("Current comment bounded page\nComment xref ignored", $text);
        $t->same("Current comment bounded page\nComment xref ignored\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Comment-Bounded XRef Title', $metadata['title']);
        $t->same('Current Comment-Bounded Info Title', $metadata['info']['Title']);
        $t->same('Current Comment Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-comment-xref.xml', $files[0]['name']);
        $t->same('current-comment-xref.xml', $files[0]['filename']);
        $t->same('Current comment-bounded xref attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->true(!str_contains($text, 'Comment xref decoy page'));
        $t->true(!str_contains($text, 'Comment root leak'));
        $t->true(!str_contains($encodedMetadata, 'Comment XRef Decoy'));
        $t->true(!str_contains($encodedFiles, 'comment-xref-decoy'));
        $t->true(!str_contains($text, "\0"));
    },
    'skips commented startxref tokens before classic rebuild text metadata and attachment selection' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildCommentedStartxrefBoundaryCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $currentXrefOffset, $decoyXrefOffset] = $xrefClassicRebuildCommentedStartxrefBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($currentXrefOffset > 0);
        $t->true($decoyXrefOffset > $currentXrefOffset);
        $t->same(['Current commented startxref page', 'Commented startxref ignored'], $extractor->extractTextLines($pdf));
        $t->same(['Current commented startxref page', 'Commented startxref ignored'], $extractor->extractTextRuns($pdf));
        $t->same("Current commented startxref page\nCommented startxref ignored", $text);
        $t->same("Current commented startxref page\nCommented startxref ignored\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Commented Startxref Title', $metadata['title']);
        $t->same('Current Commented Startxref Info Title', $metadata['info']['Title']);
        $t->same('Current Startxref Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-commented-startxref.xml', $files[0]['name']);
        $t->same('current-commented-startxref.xml', $files[0]['filename']);
        $t->same('Current commented startxref attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->true(!str_contains($text, 'Commented startxref decoy page'));
        $t->true(!str_contains($text, 'Post EOF startxref leak'));
        $t->true(!str_contains($encodedMetadata, 'Commented Startxref Decoy'));
        $t->true(!str_contains($encodedFiles, 'decoy-commented-startxref'));
        $t->true(!str_contains($text, "\0"));
    },
    'skips array-contained xref table decoys during classic rebuild before WordPress imports' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildArrayDecoyBoundaryCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $currentXrefOffset, $arrayDecoyXrefOffset] = $xrefClassicRebuildArrayDecoyBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($currentXrefOffset > 0);
        $t->true($arrayDecoyXrefOffset > $currentXrefOffset);
        $t->same(['Current array-bounded page', 'Array xref skipped'], $extractor->extractTextLines($pdf));
        $t->same(['Current array-bounded page', 'Array xref skipped'], $extractor->extractTextRuns($pdf));
        $t->same("Current array-bounded page\nArray xref skipped", $text);
        $t->same("Current array-bounded page\nArray xref skipped\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Array-Bounded XRef Title', $metadata['title']);
        $t->same('Current Array-Bounded Info Title', $metadata['info']['Title']);
        $t->same('Current Array Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-array-xref.xml', $files[0]['name']);
        $t->same('current-array-xref.xml', $files[0]['filename']);
        $t->same('Current array-bounded xref attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->true(!str_contains($text, 'Array decoy xref page'));
        $t->true(!str_contains($text, 'Array root leak'));
        $t->true(!str_contains($encodedMetadata, 'Array Decoy'));
        $t->true(!str_contains($encodedFiles, 'array-xref-decoy'));
        $t->true(!str_contains($text, "\0"));
    },
    'skips composite-contained startxref tokens before classic rebuild WordPress imports' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildCompositeStartxrefBoundaryCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $currentXrefOffset, $compositeDecoyXrefOffset] = $xrefClassicRebuildCompositeStartxrefBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($currentXrefOffset > 0);
        $t->true($compositeDecoyXrefOffset > $currentXrefOffset);
        $t->same(['Current composite startxref page', 'Composite startxref ignored'], $extractor->extractTextLines($pdf));
        $t->same(['Current composite startxref page', 'Composite startxref ignored'], $extractor->extractTextRuns($pdf));
        $t->same("Current composite startxref page\nComposite startxref ignored", $text);
        $t->same("Current composite startxref page\nComposite startxref ignored\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Composite Startxref Title', $metadata['title']);
        $t->same('Current Composite Startxref Info', $metadata['info']['Title']);
        $t->same('Current Composite Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-composite-startxref.xml', $files[0]['name']);
        $t->same('current-composite-startxref.xml', $files[0]['filename']);
        $t->same('Current composite startxref attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->true(!str_contains($text, 'Composite startxref decoy page'));
        $t->true(!str_contains($text, 'Composite startxref leak'));
        $t->true(!str_contains($encodedMetadata, 'Composite Startxref Decoy'));
        $t->true(!str_contains($encodedFiles, 'decoy-composite-startxref'));
        $t->true(!str_contains($text, "\0"));
    },
    'skips name-token startxref decoys before classic rebuild WordPress imports' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildNameStartxrefBoundaryCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $currentXrefOffset, $nameDecoyXrefOffset] = $xrefClassicRebuildNameStartxrefBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($currentXrefOffset > 0);
        $t->true($nameDecoyXrefOffset > $currentXrefOffset);
        $t->same(['Current name-startxref page', 'Name startxref ignored'], $extractor->extractTextLines($pdf));
        $t->same(['Current name-startxref page', 'Name startxref ignored'], $extractor->extractTextRuns($pdf));
        $t->same("Current name-startxref page\nName startxref ignored", $text);
        $t->same("Current name-startxref page\nName startxref ignored\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Name Startxref Title', $metadata['title']);
        $t->same('Current Name Startxref Info', $metadata['info']['Title']);
        $t->same('Current Name Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-name-startxref.xml', $files[0]['name']);
        $t->same('current-name-startxref.xml', $files[0]['filename']);
        $t->same('Current name-startxref attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->true(!str_contains($text, 'Name startxref decoy page'));
        $t->true(!str_contains($text, 'Name token root leak'));
        $t->true(!str_contains($encodedMetadata, 'Name Startxref Decoy'));
        $t->true(!str_contains($encodedFiles, 'decoy-name-startxref'));
        $t->true(!str_contains($text, "\0"));
    },
    'skips name-delimited xref pseudo-tables before classic rebuild WordPress imports' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildNameDelimitedXrefBoundaryCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $currentXrefOffset, $nameDelimitedXrefOffset] = $xrefClassicRebuildNameDelimitedXrefBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($currentXrefOffset > 0);
        $t->true($nameDelimitedXrefOffset > $currentXrefOffset);
        $t->same(['Current name-delimited xref page', 'Name-delimited xref ignored'], $extractor->extractTextLines($pdf));
        $t->same(['Current name-delimited xref page', 'Name-delimited xref ignored'], $extractor->extractTextRuns($pdf));
        $t->same("Current name-delimited xref page\nName-delimited xref ignored", $text);
        $t->same("Current name-delimited xref page\nName-delimited xref ignored\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Name-Delimited XRef Title', $metadata['title']);
        $t->same('Current Name-Delimited XRef Info', $metadata['info']['Title']);
        $t->same('Current Name-Delimited Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-name-delimited-xref.xml', $files[0]['name']);
        $t->same('current-name-delimited-xref.xml', $files[0]['filename']);
        $t->same('Current name-delimited xref attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->true(!str_contains($text, 'Name-delimited xref decoy page'));
        $t->true(!str_contains($text, 'Delimited xref root leak'));
        $t->true(!str_contains($encodedMetadata, 'Name Delimited XRef Decoy'));
        $t->true(!str_contains($encodedFiles, 'decoy-name-delimited-xref'));
        $t->true(!str_contains($text, "\0"));
    },
    'repairs startxref offsets that point inside name-token xref decoys before WordPress imports' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildNameOffsetStartxrefBoundaryCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $currentXrefOffset, $nameOffsetStartxrefOffset] = $xrefClassicRebuildNameOffsetStartxrefBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($currentXrefOffset > 0);
        $t->true($nameOffsetStartxrefOffset > $currentXrefOffset);
        $t->same(['Current name-offset xref page', 'Name-offset startxref repaired'], $extractor->extractTextLines($pdf));
        $t->same(['Current name-offset xref page', 'Name-offset startxref repaired'], $extractor->extractTextRuns($pdf));
        $t->same("Current name-offset xref page\nName-offset startxref repaired", $text);
        $t->same("Current name-offset xref page\nName-offset startxref repaired\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Name-Offset XRef Title', $metadata['title']);
        $t->same('Current Name-Offset XRef Info', $metadata['info']['Title']);
        $t->same('Current Name-Offset Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-name-offset-xref.xml', $files[0]['name']);
        $t->same('current-name-offset-xref.xml', $files[0]['filename']);
        $t->same('Current name-offset xref attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->true(!str_contains($text, 'Name-offset xref decoy page'));
        $t->true(!str_contains($text, 'Name-offset root leak'));
        $t->true(!str_contains($encodedMetadata, 'Name Offset XRef Decoy'));
        $t->true(!str_contains($encodedFiles, 'decoy-name-offset-xref'));
        $t->true(!str_contains($text, "\0"));
    },
    'skips linearized hint-range startxref tokens during classic rebuild before WordPress imports' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildLinearizedHintStartxrefBoundaryCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $currentXrefOffset, $hintDecoyXrefOffset, $hintStart, $hintLength] = $xrefClassicRebuildLinearizedHintStartxrefBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($currentXrefOffset > 0);
        $t->true($hintDecoyXrefOffset > $currentXrefOffset);
        $t->true($hintStart > $hintDecoyXrefOffset);
        $t->true($hintLength > 0);
        $t->same(['Current hint-bounded xref page', 'Linearized hint startxref ignored'], $extractor->extractTextLines($pdf));
        $t->same(['Current hint-bounded xref page', 'Linearized hint startxref ignored'], $extractor->extractTextRuns($pdf));
        $t->same("Current hint-bounded xref page\nLinearized hint startxref ignored", $text);
        $t->same("Current hint-bounded xref page\nLinearized hint startxref ignored\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Hint-Bounded XRef Title', $metadata['title']);
        $t->same('Current Hint-Bounded XRef Info', $metadata['info']['Title']);
        $t->same('Current Hint Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-hint-bounded-xref.xml', $files[0]['name']);
        $t->same('current-hint-bounded-xref.xml', $files[0]['filename']);
        $t->same('Current hint-bounded xref attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same(['current-hint-bounded-xref.xml'], $attachmentSummary['filenames']);
        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(strlen($currentPayload), $attachmentSummary['total_bytes']);
        $t->true(!str_contains($text, 'Hint startxref decoy page'));
        $t->true(!str_contains($text, 'Linearized hint root leak'));
        $t->true(!str_contains($encodedMetadata, 'Hint Startxref Decoy'));
        $t->true(!str_contains($encodedFiles, 'decoy-hint-bounded-xref'));
        $t->true(!str_contains($encodedAttachmentSummary, 'decoy-hint-bounded-xref'));
        $t->true(!str_contains($text, "\0"));
    },
    'rejects malformed classic xref table rows during rebuild before WordPress imports' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildMalformedRowBoundaryCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $currentXrefOffset, $malformedXrefOffset] = $xrefClassicRebuildMalformedRowBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($currentXrefOffset > 0);
        $t->true($malformedXrefOffset > $currentXrefOffset);
        $t->same(['Current malformed-row xref page', 'Malformed rebuild table skipped'], $extractor->extractTextLines($pdf));
        $t->same(['Current malformed-row xref page', 'Malformed rebuild table skipped'], $extractor->extractTextRuns($pdf));
        $t->same("Current malformed-row xref page\nMalformed rebuild table skipped", $text);
        $t->same("Current malformed-row xref page\nMalformed rebuild table skipped\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Malformed-Row XRef Title', $metadata['title']);
        $t->same('Current Malformed-Row XRef Info', $metadata['info']['Title']);
        $t->same('Current Malformed-Row Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-malformed-row-xref.xml', $files[0]['name']);
        $t->same('current-malformed-row-xref.xml', $files[0]['filename']);
        $t->same('Current malformed-row xref attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(['current-malformed-row-xref.xml'], $attachmentSummary['filenames']);
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
        $t->true(!str_contains($text, 'Malformed-row decoy page'));
        $t->true(!str_contains($text, 'Partial xref row leak'));
        $t->true(!str_contains($encodedMetadata, 'Malformed Row Decoy'));
        $t->true(!str_contains($encodedFiles, 'decoy-malformed-row-xref'));
        $t->true(!str_contains($encodedAttachmentSummary, 'decoy-malformed-row-xref'));
        $t->true(!str_contains($text, "\0"));
    },
    'rejects classic xref rows with punctuation state suffixes during rebuild before WordPress imports' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildPunctuationRowBoundaryCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $currentXrefOffset, $punctuationXrefOffset] = $xrefClassicRebuildPunctuationRowBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($currentXrefOffset > 0);
        $t->true($punctuationXrefOffset > $currentXrefOffset);
        $t->same(['Current row-state xref page', 'Punctuation row skipped'], $extractor->extractTextLines($pdf));
        $t->same(['Current row-state xref page', 'Punctuation row skipped'], $extractor->extractTextRuns($pdf));
        $t->same("Current row-state xref page\nPunctuation row skipped", $text);
        $t->same("Current row-state xref page\nPunctuation row skipped\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Row-State XRef Title', $metadata['title']);
        $t->same('Current Row-State XRef Info', $metadata['info']['Title']);
        $t->same('Current Row-State Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-row-state-xref.xml', $files[0]['name']);
        $t->same('current-row-state-xref.xml', $files[0]['filename']);
        $t->same('Current row-state xref attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(['current-row-state-xref.xml'], $attachmentSummary['filenames']);
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
        $t->true(!str_contains($text, 'Punctuation row decoy page'));
        $t->true(!str_contains($text, 'Row-state root leak'));
        $t->true(!str_contains($encodedMetadata, 'Punctuation Row Decoy'));
        $t->true(!str_contains($encodedFiles, 'decoy-row-state-xref'));
        $t->true(!str_contains($encodedAttachmentSummary, 'decoy-row-state-xref'));
        $t->true(!str_contains($text, "\0"));
    },
    'skips comment-only classic xref lines during rebuild before WordPress imports' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildCommentOnlyRowsCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $previousXrefOffset, $currentXrefOffset] = $xrefClassicRebuildCommentOnlyRowsCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($previousXrefOffset > 0);
        $t->true($currentXrefOffset > $previousXrefOffset);
        $t->same(['Current comment-row xref page', 'Comment-only rows skipped'], $extractor->extractTextLines($pdf));
        $t->same(['Current comment-row xref page', 'Comment-only rows skipped'], $extractor->extractTextRuns($pdf));
        $t->same("Current comment-row xref page\nComment-only rows skipped", $text);
        $t->same("Current comment-row xref page\nComment-only rows skipped\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Comment-Row XRef Title', $metadata['title']);
        $t->same('Current Comment-Row XRef Info', $metadata['info']['Title']);
        $t->same('Current Comment Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-comment-row-xref.xml', $files[0]['name']);
        $t->same('current-comment-row-xref.xml', $files[0]['filename']);
        $t->same('Current comment-row xref attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(['current-comment-row-xref.xml'], $attachmentSummary['filenames']);
        $t->same(strlen($currentPayload), $attachmentSummary['total_bytes']);
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
        $t->true(!str_contains($text, 'Stale comment-row xref page'));
        $t->true(!str_contains($text, 'Comment-row root leak'));
        $t->true(!str_contains($encodedMetadata, 'Stale Comment-Row'));
        $t->true(!str_contains($encodedFiles, 'stale-comment-row-xref'));
        $t->true(!str_contains($encodedAttachmentSummary, 'stale-comment-row-xref'));
        $t->true(!str_contains($text, "\0"));
    },
    'preserves completed classic xref subsections before malformed trailing subsections during rebuild' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildTrailingSubsectionBoundaryCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $previousXrefOffset, $currentXrefOffset] = $xrefClassicRebuildTrailingSubsectionBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($currentXrefOffset > $previousXrefOffset);
        $t->same(['Current trailing-subsection xref page', 'Completed subsection preserved'], $extractor->extractTextLines($pdf));
        $t->same(['Current trailing-subsection xref page', 'Completed subsection preserved'], $extractor->extractTextRuns($pdf));
        $t->same("Current trailing-subsection xref page\nCompleted subsection preserved", $text);
        $t->same("Current trailing-subsection xref page\nCompleted subsection preserved\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Trailing-Subsection XRef Title', $metadata['title']);
        $t->same('Current Trailing-Subsection XRef Info', $metadata['info']['Title']);
        $t->same('Current Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-trailing-subsection-xref.xml', $files[0]['name']);
        $t->same('current-trailing-subsection-xref.xml', $files[0]['filename']);
        $t->same('Current trailing-subsection xref attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(['current-trailing-subsection-xref.xml'], $attachmentSummary['filenames']);
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
        $t->true(!str_contains($text, 'Stale trailing-subsection xref page'));
        $t->true(!str_contains($text, 'Malformed trailing subsection leak'));
        $t->true(!str_contains($encodedMetadata, 'Stale Trailing-Subsection'));
        $t->true(!str_contains($encodedFiles, 'stale-trailing-subsection-xref'));
        $t->true(!str_contains($encodedAttachmentSummary, 'stale-trailing-subsection-xref'));
        $t->true(!str_contains($text, "\0"));
    },
    'skips literal-string xref table decoys when startxref points inside a PDF string before WordPress imports' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildLiteralStringDecoyBoundaryCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $currentXrefOffset, $literalXrefOffset] = $xrefClassicRebuildLiteralStringDecoyBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($currentXrefOffset > 0);
        $t->true($literalXrefOffset > $currentXrefOffset);
        $t->same(['Current literal-string xref page', 'Literal xref decoy skipped'], $extractor->extractTextLines($pdf));
        $t->same(['Current literal-string xref page', 'Literal xref decoy skipped'], $extractor->extractTextRuns($pdf));
        $t->same("Current literal-string xref page\nLiteral xref decoy skipped", $text);
        $t->same("Current literal-string xref page\nLiteral xref decoy skipped\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Literal XRef Title', $metadata['title']);
        $t->same('Current Literal XRef Info', $metadata['info']['Title']);
        $t->same('Current Literal Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-literal-string-xref.xml', $files[0]['name']);
        $t->same('current-literal-string-xref.xml', $files[0]['filename']);
        $t->same('Current literal-string xref attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(['current-literal-string-xref.xml'], $attachmentSummary['filenames']);
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
        $t->true(!str_contains($text, 'Literal xref decoy page'));
        $t->true(!str_contains($text, 'String xref root leak'));
        $t->true(!str_contains($encodedMetadata, 'Literal String XRef Decoy'));
        $t->true(!str_contains($encodedFiles, 'decoy-literal-string-xref'));
        $t->true(!str_contains($encodedAttachmentSummary, 'decoy-literal-string-xref'));
        $t->true(!str_contains($text, "\0"));
    },
    'skips stream-owned trailer dictionaries during classic xref rebuild before WordPress imports' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildStreamTrailerBoundaryCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $currentXrefOffset, $fakeTrailerOffset, $realTrailerOffset] = $xrefClassicRebuildStreamTrailerBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($currentXrefOffset > 0);
        $t->true($fakeTrailerOffset > $currentXrefOffset);
        $t->true($realTrailerOffset > $fakeTrailerOffset);
        $t->same(['Current stream-trailer xref page', 'Stream-owned trailer skipped'], $extractor->extractTextLines($pdf));
        $t->same(['Current stream-trailer xref page', 'Stream-owned trailer skipped'], $extractor->extractTextRuns($pdf));
        $t->same("Current stream-trailer xref page\nStream-owned trailer skipped", $text);
        $t->same("Current stream-trailer xref page\nStream-owned trailer skipped\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Stream-Trailer XRef Title', $metadata['title']);
        $t->same('Current Stream-Trailer XRef Info', $metadata['info']['Title']);
        $t->same('Current Stream-Trailer Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-stream-trailer-xref.xml', $files[0]['name']);
        $t->same('current-stream-trailer-xref.xml', $files[0]['filename']);
        $t->same('Current stream-trailer xref attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(['current-stream-trailer-xref.xml'], $attachmentSummary['filenames']);
        $t->same(strlen($currentPayload), $attachmentSummary['total_bytes']);
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
        $t->true(!str_contains($text, 'Stream trailer decoy page'));
        $t->true(!str_contains($text, 'Stream-owned trailer leak'));
        $t->true(!str_contains($encodedMetadata, 'Stream Trailer Decoy'));
        $t->true(!str_contains($encodedFiles, 'decoy-stream-trailer-xref'));
        $t->true(!str_contains($encodedAttachmentSummary, 'decoy-stream-trailer-xref'));
        $t->true(!str_contains($text, "\0"));
    },
    'rejects classic xref trailers that appear after startxref before WordPress imports' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildPostStartxrefTrailerBoundaryCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $currentXrefOffset, $postStartxrefXrefOffset] = $xrefClassicRebuildPostStartxrefTrailerBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($currentXrefOffset > 0);
        $t->true($postStartxrefXrefOffset > $currentXrefOffset);
        $t->same(['Current post-startxref trailer page', 'Late trailer boundary kept'], $extractor->extractTextLines($pdf));
        $t->same(['Current post-startxref trailer page', 'Late trailer boundary kept'], $extractor->extractTextRuns($pdf));
        $t->same("Current post-startxref trailer page\nLate trailer boundary kept", $text);
        $t->same("Current post-startxref trailer page\nLate trailer boundary kept\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Post-StartXRef Trailer Title', $metadata['title']);
        $t->same('Current Post-StartXRef Trailer Info', $metadata['info']['Title']);
        $t->same('Current Post-StartXRef Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-post-startxref-trailer-xref.xml', $files[0]['name']);
        $t->same('current-post-startxref-trailer-xref.xml', $files[0]['filename']);
        $t->same('Current post-startxref trailer attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(['current-post-startxref-trailer-xref.xml'], $attachmentSummary['filenames']);
        $t->same(strlen($currentPayload), $attachmentSummary['total_bytes']);
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
        $t->true(!str_contains($text, 'Post startxref trailer decoy page'));
        $t->true(!str_contains($text, 'Late trailer root leak'));
        $t->true(!str_contains($encodedMetadata, 'Post StartXRef Trailer Decoy'));
        $t->true(!str_contains($encodedFiles, 'decoy-post-startxref-trailer-xref'));
        $t->true(!str_contains($encodedAttachmentSummary, 'decoy-post-startxref-trailer-xref'));
        $t->true(!str_contains($text, "\0"));
    },
    'skips stream-owned composite tokens before scanning later classic xref tables' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildStreamCompositeSkipBoundaryCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $previousXrefOffset, $streamCompositeOffset, $currentXrefOffset] = $xrefClassicRebuildStreamCompositeSkipBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($previousXrefOffset > 0);
        $t->true($streamCompositeOffset > $previousXrefOffset);
        $t->true($currentXrefOffset > $streamCompositeOffset);
        $t->same(['Current stream-composite xref page', 'Real xref after stream composite selected'], $extractor->extractTextLines($pdf));
        $t->same(['Current stream-composite xref page', 'Real xref after stream composite selected'], $extractor->extractTextRuns($pdf));
        $t->same("Current stream-composite xref page\nReal xref after stream composite selected", $text);
        $t->same("Current stream-composite xref page\nReal xref after stream composite selected\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Stream-Composite XRef Title', $metadata['title']);
        $t->same('Current Stream-Composite XRef Info', $metadata['info']['Title']);
        $t->same('Current Stream Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-stream-composite-xref.xml', $files[0]['name']);
        $t->same('current-stream-composite-xref.xml', $files[0]['filename']);
        $t->same('Current stream-composite xref attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(['current-stream-composite-xref.xml'], $attachmentSummary['filenames']);
        $t->same(strlen($currentPayload), $attachmentSummary['total_bytes']);
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
        $t->true(!str_contains($text, 'Stale stream-composite xref page'));
        $t->true(!str_contains($text, 'Stream composite hid current xref'));
        $t->true(!str_contains($encodedMetadata, 'Stale stream-composite'));
        $t->true(!str_contains($encodedFiles, 'stale-stream-composite'));
        $t->true(!str_contains($encodedAttachmentSummary, 'stale-stream-composite'));
        $t->true(!str_contains($text, "\0"));
    },
    'recovers current classic xref tables after malformed non-hex string openers before WordPress imports' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildMalformedHexOpenerBoundaryCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $previousXrefOffset, $malformedHexOpenerOffset, $currentXrefOffset] = $xrefClassicRebuildMalformedHexOpenerBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($previousXrefOffset > 0);
        $t->true($malformedHexOpenerOffset > $previousXrefOffset);
        $t->true($currentXrefOffset > $malformedHexOpenerOffset);
        $t->same(['Current malformed-hex xref page', 'Malformed hex opener recovered'], $extractor->extractTextLines($pdf));
        $t->same(['Current malformed-hex xref page', 'Malformed hex opener recovered'], $extractor->extractTextRuns($pdf));
        $t->same("Current malformed-hex xref page\nMalformed hex opener recovered", $text);
        $t->same("Current malformed-hex xref page\nMalformed hex opener recovered\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Malformed Hex XRef Title', $metadata['title']);
        $t->same('Current Malformed Hex XRef Info', $metadata['info']['Title']);
        $t->same('Current Hex Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-malformed-hex-xref.xml', $files[0]['name']);
        $t->same('current-malformed-hex-xref.xml', $files[0]['filename']);
        $t->same('Current malformed-hex xref attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(['current-malformed-hex-xref.xml'], $attachmentSummary['filenames']);
        $t->same(strlen($currentPayload), $attachmentSummary['total_bytes']);
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
        $t->true(!str_contains($text, 'Stale malformed-hex xref page'));
        $t->true(!str_contains($text, 'Malformed hex root leak'));
        $t->true(!str_contains($encodedMetadata, 'Stale Malformed Hex'));
        $t->true(!str_contains($encodedFiles, 'stale-malformed-hex-xref'));
        $t->true(!str_contains($encodedAttachmentSummary, 'stale-malformed-hex-xref'));
        $t->true(!str_contains($text, "\0"));
    },
    'repairs damaged explicit classic xref root rows from direct object boundaries before WordPress imports' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildDamagedRootRowCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $previousXrefOffset, $currentXrefOffset, $currentRootOffset] = $xrefClassicRebuildDamagedRootRowCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($previousXrefOffset > 0);
        $t->true($currentRootOffset > $previousXrefOffset);
        $t->true($currentXrefOffset > $currentRootOffset);
        $t->same(['Current damaged root-row xref page', 'Root row rebuilt'], $extractor->extractTextLines($pdf));
        $t->same(['Current damaged root-row xref page', 'Root row rebuilt'], $extractor->extractTextRuns($pdf));
        $t->same("Current damaged root-row xref page\nRoot row rebuilt", $text);
        $t->same("Current damaged root-row xref page\nRoot row rebuilt\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Damaged Root Row Title', $metadata['title']);
        $t->same('Current Damaged Root Row Info', $metadata['info']['Title']);
        $t->same('Current Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-damaged-root-row-xref.xml', $files[0]['name']);
        $t->same('current-damaged-root-row-xref.xml', $files[0]['filename']);
        $t->same('Current damaged root-row xref attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(['current-damaged-root-row-xref.xml'], $attachmentSummary['filenames']);
        $t->same(strlen($currentPayload), $attachmentSummary['total_bytes']);
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
        $t->true(!str_contains($text, 'Stale damaged root-row xref page'));
        $t->true(!str_contains($text, 'Older row offset leak'));
        $t->true(!str_contains($encodedMetadata, 'Stale Damaged Root Row'));
        $t->true(!str_contains($encodedFiles, 'stale-damaged-root-row-xref'));
        $t->true(!str_contains($encodedAttachmentSummary, 'stale-damaged-root-row-xref'));
        $t->true(!str_contains($text, "\0"));
    },
    'repairs forward classic Prev pointers to the prior xref section before WordPress imports' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildForwardPrevBoundaryCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $previousXrefOffset, $currentXrefOffset, $forwardPrevXrefOffset] = $xrefClassicRebuildForwardPrevBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($previousXrefOffset > 0);
        $t->true($currentXrefOffset > $previousXrefOffset);
        $t->true($forwardPrevXrefOffset > $currentXrefOffset);
        $t->same(['Current forward-prev xref page', 'Forward Prev repaired backward'], $extractor->extractTextLines($pdf));
        $t->same(['Current forward-prev xref page', 'Forward Prev repaired backward'], $extractor->extractTextRuns($pdf));
        $t->same("Current forward-prev xref page\nForward Prev repaired backward", $text);
        $t->same("Current forward-prev xref page\nForward Prev repaired backward\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Forward Prev XRef Title', $metadata['title']);
        $t->same('Current Forward Prev XRef Info', $metadata['info']['Title']);
        $t->same('Current Forward Prev Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-forward-prev-xref.xml', $files[0]['name']);
        $t->same('current-forward-prev-xref.xml', $files[0]['filename']);
        $t->same('Current forward-prev xref attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(['current-forward-prev-xref.xml'], $attachmentSummary['filenames']);
        $t->same(strlen($currentPayload), $attachmentSummary['total_bytes']);
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
        $t->true(!str_contains($text, 'Forward Prev decoy page'));
        $t->true(!str_contains($text, 'Post EOF Prev root leak'));
        $t->true(!str_contains($encodedMetadata, 'Forward Prev Decoy'));
        $t->true(!str_contains($encodedFiles, 'decoy-forward-prev-xref'));
        $t->true(!str_contains($encodedAttachmentSummary, 'decoy-forward-prev-xref'));
        $t->true(!str_contains($text, "\0"));
    },
];
