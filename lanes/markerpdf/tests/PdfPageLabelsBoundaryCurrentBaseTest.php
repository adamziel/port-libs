<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelBoundaryPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Opening page imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Body page imported) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Chapter page imported) Tj ET',
        13 => 'BT /F1 12 Tf 72 720 Td (Continued page imported) Tj ET',
    ];

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R 5 0 R 6 0 R] /Count 4 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 12 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 13 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    foreach ($contents as $objectNumber => $content) {
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return $pdf
        . "20 0 obj\n<< /Limits [1 2] /Kids [21 0 R 22 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /Nums [0 << /S /r /P (stale-front-) /St 6 >> 1 << /S /D /P (Body ) /St 5 >>] >>\nendobj\n"
        . "22 0 obj\n<< /Nums [2 << /S /D /P (Chapter ) /St 9 >> 3 << /S /D /P (stale-back-) /St 99 >>] >>\nendobj\n"
        . "%%EOF\n";
};

$alphabeticPageLabelBoundaryPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Appendix Z imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Appendix AA imported) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Appendix BB imported) Tj ET',
    ];

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R 5 0 R] /Count 3 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 12 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    foreach ($contents as $objectNumber => $content) {
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return $pdf
        . "20 0 obj\n<< /Nums [0 << /S /A /P (App-) /St 26 >>] >>\nendobj\n"
        . "%%EOF\n";
};

$indirectOperandPageLabelBoundaryPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Front matter imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Front matter continued) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Appendix imported) Tj ET',
    ];

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R 5 0 R] /Count 3 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 12 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    foreach ($contents as $objectNumber => $content) {
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return $pdf
        . "20 0 obj\n<< /Nums [0 30 0 R 2 34 0 R] >>\nendobj\n"
        . "30 0 obj\n<< /S 31 0 R /P 32 0 R /St 33 0 R >>\nendobj\n"
        . "31 0 obj\n/r\nendobj\n"
        . "32 0 obj\n(Front )\nendobj\n"
        . "33 0 obj\n4\nendobj\n"
        . "34 0 obj\n<< /S 35 0 R /P 36 0 R /St 37 0 R >>\nendobj\n"
        . "35 0 obj\n/A\nendobj\n"
        . "36 0 obj\n(App-)\nendobj\n"
        . "37 0 obj\n26\nendobj\n"
        . "%%EOF\n";
};

$transitiveIndirectOperandPageLabelBoundaryPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Nested front matter imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Nested front matter continued) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Nested appendix imported) Tj ET',
    ];

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R 5 0 R] /Count 3 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 12 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    foreach ($contents as $objectNumber => $content) {
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return $pdf
        . "20 0 obj\n<< /Nums 29 0 R >>\nendobj\n"
        . "29 0 obj\n30 0 R\nendobj\n"
        . "30 0 obj\n[0 << /P 31 0 R /S 33 0 R /St 35 0 R >> 2 << /P 37 0 R /S 39 0 R /St 41 0 R >>]\nendobj\n"
        . "31 0 obj\n32 0 R\nendobj\n"
        . "32 0 obj\n(Nested Front )\nendobj\n"
        . "33 0 obj\n34 0 R\nendobj\n"
        . "34 0 obj\n/r\nendobj\n"
        . "35 0 obj\n36 0 R\nendobj\n"
        . "36 0 obj\n4\nendobj\n"
        . "37 0 obj\n38 0 R\nendobj\n"
        . "38 0 obj\n(Nested App-)\nendobj\n"
        . "39 0 obj\n40 0 R\nendobj\n"
        . "40 0 obj\n/A\nendobj\n"
        . "41 0 obj\n42 0 R\nendobj\n"
        . "42 0 obj\n26\nendobj\n"
        . "%%EOF\n";
};

$indirectLimitOperandPageLabelBoundaryPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Opening fallback imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Body indirect limit imported) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Appendix indirect limit imported) Tj ET',
        13 => 'BT /F1 12 Tf 72 720 Td (Appendix continued imported) Tj ET',
    ];

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R 5 0 R 6 0 R] /Count 4 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 12 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 13 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    foreach ($contents as $objectNumber => $content) {
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return $pdf
        . "20 0 obj\n<< /Limits [30 0 R 31 0 R] /Kids [21 0 R 22 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /Nums [0 << /P (stale-front-) /S /D /St 90 >> 1 << /P (Body ) /S /D /St 4 >>] >>\nendobj\n"
        . "22 0 obj\n<< /Nums [2 << /P (App-) /S /A /St 26 >> 3 << /P (stale-back-) /S /D /St 99 >>] >>\nendobj\n"
        . "30 0 obj\n1\nendobj\n"
        . "31 0 obj\n2\nendobj\n"
        . "%%EOF\n";
};

$escapedNamePageLabelBoundaryPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Escaped page imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Named page imported) Tj ET',
    ];

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PieceInfo << /PageLabels 40 0 R >> /Page#4Cabels 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    foreach ($contents as $objectNumber => $content) {
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return $pdf
        . "20 0 obj\n<< /#4Eums [0 << /#53 /#44 /#50 (Real ) /#53t 7 >> 1 << /#50 (Named-) >>] >>\nendobj\n"
        . "40 0 obj\n<< /Nums [0 << /S /D /P (stale-private-) /St 99 >> 1 << /S /D /P (stale-nested-) /St 100 >>] >>\nendobj\n"
        . "%%EOF\n";
};

$pdfDocEncodingPageLabelBoundaryPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Encoded prefix page imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Hex prefix page imported) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Indirect prefix page imported) Tj ET',
    ];
    $literalPrefix = 'WP' . chr(0x80) . '-Import' . chr(0x81) . ' ';
    $hexPrefix = strtoupper(bin2hex('Review ' . chr(0x8d) . 'PDF' . chr(0x8e)));
    $indirectPrefix = 'Appendix' . chr(0x93) . chr(0x94) . ' ';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R 5 0 R] /Count 3 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 12 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    foreach ($contents as $objectNumber => $content) {
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return $pdf
        . "20 0 obj\n<< /Nums [0 << /S /D /P ({$literalPrefix}) /St 3 >> 1 << /P <{$hexPrefix}> >> 2 << /S /A /P 31 0 R /St 26 >>] >>\nendobj\n"
        . "31 0 obj\n({$indirectPrefix})\nendobj\n"
        . "%%EOF\n";
};

$generationBoundaryPageLabelPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Current cover imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Current body imported) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Current appendix imported) Tj ET',
    ];

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R 5 0 R] /Count 3 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 12 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    foreach ($contents as $objectNumber => $content) {
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return $pdf
        . "20 0 obj\n<< /Nums [0 30 0 R 1 31 0 R] >>\nendobj\n"
        . "30 0 obj\n<< /P (Cover-) >>\nendobj\n"
        . "30 1 obj\n<< /S /D /P (stale-high-generation-) /St 99 >>\nendobj\n"
        . "31 0 obj\n<< /S /D /P (Body ) /St 4 >>\nendobj\n"
        . "%%EOF\n";
};

$indirectKeyPageLabelBoundaryPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Opening fallback imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Indirect front imported) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Indirect body imported) Tj ET',
        13 => 'BT /F1 12 Tf 72 720 Td (Indirect appendix imported) Tj ET',
    ];

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R 5 0 R 6 0 R] /Count 4 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 12 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 13 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    foreach ($contents as $objectNumber => $content) {
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return $pdf
        . "20 0 obj\n<< /Limits [1 3] /Nums [30 0 R << /S /r /P (Front ) /St 2 >> 31 0 R 34 0 R [2 << /P (nested-stale-) /S /D /St 77 >>] << /P (array-value-stale-) /S /D /St 88 >> 32 0 R << /S /A /P (App-) /St 26 >> 33 0 R << /P (stale-back-) /S /D /St 99 >>] >>\nendobj\n"
        . "30 0 obj\n1\nendobj\n"
        . "30 1 obj\n0\nendobj\n"
        . "31 0 obj\n2\nendobj\n"
        . "31 1 obj\n0\nendobj\n"
        . "32 0 obj\n3\nendobj\n"
        . "33 0 obj\n4\nendobj\n"
        . "34 0 obj\n<< /S /D /P (Body ) /St 8 >>\nendobj\n"
        . "%%EOF\n";
};

$indirectNumsArrayPageLabelBoundaryPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Indirect array cover imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Indirect array body imported) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Indirect array appendix imported) Tj ET',
    ];

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R 5 0 R] /Count 3 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 12 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    foreach ($contents as $objectNumber => $content) {
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return $pdf
        . "20 0 obj\n<< /Nums 30 0 R >>\nendobj\n"
        . "30 0 obj\n[0 << /P (Cover-) >> 1 << /P (Body ) /S /D /St 7 >> 2 << /P (App-) /S /A /St 26 >>]\nendobj\n"
        . "30 1 obj\n[0 << /P (stale-array-) /S /D /St 99 >>]\nendobj\n"
        . "%%EOF\n";
};

$tokenBoundaryPageLabelPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Top-level cover imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Top-level body imported) Tj ET',
    ];

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    foreach ($contents as $objectNumber => $content) {
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return $pdf
        . "20 0 obj\n<< /Kids [21 0 R << /Private [22 0 R] /Comment (22 0 R) >>] >>\nendobj\n"
        . "20 1 obj\n<< /Nums [0 << /P (stale-root-) /S /D /St 99 >>] >>\nendobj\n"
        . "21 0 obj\n<< /Nums [0 << /P (Cover-) >> 1 << /P (Body ) /S /D /St 4 >> [1 << /P (nested-stale-) /S /D /St 77 >>] (1 0 R)] >>\nendobj\n"
        . "22 0 obj\n<< /Nums [1 << /P (kid-stale-) /S /D /St 66 >>] >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'keeps parent PageLabels Limits across indirect kid number-tree boundaries' => static function (TestRunner $t) use ($pageLabelBoundaryPdf): void {
        $pdf = $pageLabelBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);

        $t->same(['1', 'Body 5', 'Chapter 9', 'Chapter 10'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, array_column($summary['pages'], 'page_label'));
        $t->same(['Opening page imported', 'Body page imported', 'Chapter page imported', 'Continued page imported'], array_column($entries, 'text'));
        $t->true(!in_array('stale-front-vi', $labels, true));
        $t->true(!in_array('stale-back-99', $labels, true));
        $t->same('Chapter 10', $preview->getPageImagePlan($pdf, 4)['page_label']);
    },
    'keeps PDF alphabetic PageLabels repeated-letter style aligned with preview metadata' => static function (TestRunner $t) use ($alphabeticPageLabelBoundaryPdf): void {
        $pdf = $alphabeticPageLabelBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same(['App-Z', 'App-AA', 'App-BB'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $previewLabels);
        $t->same(['Appendix Z imported', 'Appendix AA imported', 'Appendix BB imported'], array_column($entries, 'text'));
        $t->true(!in_array('App-AB', $previewLabels, true));
        $t->same('App-BB', $preview->getPageImagePlan($pdf, 3)['page_label']);
    },
    'resolves indirect PageLabels style prefix and start operands for preview metadata' => static function (TestRunner $t) use ($indirectOperandPageLabelBoundaryPdf): void {
        $pdf = $indirectOperandPageLabelBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same(['Front iv', 'Front v', 'App-Z'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $previewLabels);
        $t->same(['Front matter imported', 'Front matter continued', 'Appendix imported'], array_column($entries, 'text'));
        $t->true(!in_array('', $previewLabels, true));
        $t->same('App-Z', $preview->getPageImagePlan($pdf, 3)['page_label']);
    },
    'resolves transitive indirect PageLabels operands for preview metadata' => static function (TestRunner $t) use ($transitiveIndirectOperandPageLabelBoundaryPdf): void {
        $pdf = $transitiveIndirectOperandPageLabelBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same(['Nested Front iv', 'Nested Front v', 'Nested App-Z'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $previewLabels);
        $t->same(['Nested front matter imported', 'Nested front matter continued', 'Nested appendix imported'], array_column($entries, 'text'));
        $t->true(!in_array('1', $previewLabels, true));
        $t->same('Nested App-Z', $preview->getPageImagePlan($pdf, 3)['page_label']);
    },
    'resolves indirect PageLabels Limits operands before kid boundary filtering' => static function (TestRunner $t) use ($indirectLimitOperandPageLabelBoundaryPdf): void {
        $pdf = $indirectLimitOperandPageLabelBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same(['1', 'Body 4', 'App-Z', 'App-AA'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $previewLabels);
        $t->same(['Opening fallback imported', 'Body indirect limit imported', 'Appendix indirect limit imported', 'Appendix continued imported'], array_column($entries, 'text'));
        $t->true(!in_array('stale-front-90', $labels, true));
        $t->true(!in_array('stale-back-99', $previewLabels, true));
        $t->same('App-AA', $preview->getPageImagePlan($pdf, 4)['page_label']);
    },
    'keeps escaped catalog PageLabels names above nested private decoys' => static function (TestRunner $t) use ($escapedNamePageLabelBoundaryPdf): void {
        $pdf = $escapedNamePageLabelBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same(['Real 7', 'Named-'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $previewLabels);
        $t->same(['Escaped page imported', 'Named page imported'], array_column($entries, 'text'));
        $t->true(!in_array('stale-private-99', $labels, true));
        $t->true(!in_array('stale-nested-100', $previewLabels, true));
        $t->same('Named-', $preview->getPageImagePlan($pdf, 2)['page_label']);
    },
    'decodes PDFDocEncoding PageLabels prefixes before WordPress page metadata' => static function (TestRunner $t) use ($pdfDocEncodingPageLabelBoundaryPdf): void {
        $pdf = $pdfDocEncodingPageLabelBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');
        $expectedLabels = ["WP\u{2022}-Import\u{2020} 3", "Review \u{201C}PDF\u{201D}", "Appendix\u{FB01}\u{FB02} Z"];

        $t->same($expectedLabels, $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $previewLabels);
        $t->same(['Encoded prefix page imported', 'Hex prefix page imported', 'Indirect prefix page imported'], array_column($entries, 'text'));
        $t->true(!in_array('WP' . chr(0x80) . '-Import' . chr(0x81) . ' 3', $previewLabels, true));
        $t->same("Appendix\u{FB01}\u{FB02} Z", $preview->getPageImagePlan($pdf, 3)['page_label']);
    },
    'keeps generation-exact indirect PageLabels dictionaries before WordPress page metadata' => static function (TestRunner $t) use ($generationBoundaryPageLabelPdf): void {
        $pdf = $generationBoundaryPageLabelPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same(['Cover-', 'Body 4', 'Body 5'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $previewLabels);
        $t->same(['Current cover imported', 'Current body imported', 'Current appendix imported'], array_column($entries, 'text'));
        $t->true(!in_array('stale-high-generation-99', $labels, true));
        $t->true(!in_array('stale-high-generation-100', $previewLabels, true));
        $t->same('Body 5', $preview->getPageImagePlan($pdf, 3)['page_label']);
    },
    'resolves indirect PageLabels Nums keys by exact generation before WordPress page metadata' => static function (TestRunner $t) use ($indirectKeyPageLabelBoundaryPdf): void {
        $pdf = $indirectKeyPageLabelBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same(['1', 'Front ii', 'Body 8', 'App-Z'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $previewLabels);
        $t->same(['Opening fallback imported', 'Indirect front imported', 'Indirect body imported', 'Indirect appendix imported'], array_column($entries, 'text'));
        $t->true(!in_array('nested-stale-77', $labels, true));
        $t->true(!in_array('array-value-stale-88', $previewLabels, true));
        $t->true(!in_array('stale-back-99', $labels, true));
        $t->same('App-Z', $preview->getPageImagePlan($pdf, 4)['page_label']);
    },
    'resolves indirect PageLabels Nums arrays by exact generation for preview metadata' => static function (TestRunner $t) use ($indirectNumsArrayPageLabelBoundaryPdf): void {
        $pdf = $indirectNumsArrayPageLabelBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same(['Cover-', 'Body 7', 'App-Z'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $previewLabels);
        $t->same(['Indirect array cover imported', 'Indirect array body imported', 'Indirect array appendix imported'], array_column($entries, 'text'));
        $t->true(!in_array('stale-array-99', $labels, true));
        $t->true(!in_array('stale-array-100', $previewLabels, true));
        $t->same('App-Z', $preview->getPageImagePlan($pdf, 3)['page_label']);
    },
    'keeps PageLabels root kids and nums at top-level token boundaries' => static function (TestRunner $t) use ($tokenBoundaryPageLabelPdf): void {
        $pdf = $tokenBoundaryPageLabelPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same(['Cover-', 'Body 4'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $previewLabels);
        $t->same(['Top-level cover imported', 'Top-level body imported'], array_column($entries, 'text'));
        $t->true(!in_array('stale-root-99', $labels, true));
        $t->true(!in_array('stale-root-100', $previewLabels, true));
        $t->true(!in_array('kid-stale-66', $labels, true));
        $t->true(!in_array('nested-stale-77', $previewLabels, true));
        $t->same('Body 4', $preview->getPageImagePlan($pdf, 2)['page_label']);
    },
];
