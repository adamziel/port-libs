<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;

$pdfWithNameTree = static function (): string {
    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names 8 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [5 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Dests 9 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Kids [10 0 R 11 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Limits [(intro) (wp export)] /Names [(intro) [5 0 R /XYZ 72 700 1] <7770206578706f7274> 12 0 R] >>\nendobj\n"
        . "11 0 obj\n<< /Limits [(media) (media)] /Names [(media) 13 0 R] >>\nendobj\n"
        . "12 0 obj\n[4 0 R /FitH 640]\nendobj\n"
        . "13 0 obj\n<< /D [4 0 R /FitR 10 20 300 740] >>\nendobj\n"
        . "%%EOF\n";
};

$pdfWithLegacyDests = static function (): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Dests << /Chapter#201 [3 0 R /Fit] /Appendix 8 0 R /NumberDest [1 /XYZ null 720 0] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "8 0 obj\n<< /D [4 0 R /FitV 120] >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'extracts catalog names tree destinations with page object indices and fit coordinates' => static function (TestRunner $t) use ($pdfWithNameTree): void {
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdfWithNameTree());

        $t->same(
            [
                [
                    'name' => 'intro',
                    'page' => 0,
                    'page_object_id' => 5,
                    'fit' => 'XYZ',
                    'coordinates' => ['left' => 72.0, 'top' => 700.0, 'zoom' => 1.0],
                    'source' => 'names-tree',
                ],
                [
                    'name' => 'wp export',
                    'page' => 1,
                    'page_object_id' => 4,
                    'fit' => 'FitH',
                    'coordinates' => ['top' => 640.0],
                    'source' => 'names-tree',
                ],
                [
                    'name' => 'media',
                    'page' => 1,
                    'page_object_id' => 4,
                    'fit' => 'FitR',
                    'coordinates' => ['left' => 10.0, 'bottom' => 20.0, 'right' => 300.0, 'top' => 740.0],
                    'source' => 'names-tree',
                ],
            ],
            $destinations
        );
    },
    'extracts legacy catalog Dests dictionaries and PDF name escapes' => static function (TestRunner $t) use ($pdfWithLegacyDests): void {
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdfWithLegacyDests());

        $t->same(['Chapter 1', 'Appendix', 'NumberDest'], array_column($destinations, 'name'));
        $t->same([0, 1, 1], array_column($destinations, 'page'));
        $t->same([3, 4, null], array_column($destinations, 'page_object_id'));
        $t->same(['Fit', 'FitV', 'XYZ'], array_column($destinations, 'fit'));
        $t->same([], $destinations[0]['coordinates']);
        $t->same(['left' => 120.0], $destinations[1]['coordinates']);
        $t->same(['left' => null, 'top' => 720.0, 'zoom' => 0.0], $destinations[2]['coordinates']);
        $t->same(['legacy-dests', 'legacy-dests', 'legacy-dests'], array_column($destinations, 'source'));
    },
    'prefers names tree entries over duplicate legacy Dests entries' => static function (TestRunner $t): void {
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests << /Names [(same) [3 0 R /FitH 700]] >> >> /Dests << /same [4 0 R /FitV 80] >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "%%EOF\n";

        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);

        $t->same(1, count($destinations));
        $t->same('same', $destinations[0]['name']);
        $t->same(0, $destinations[0]['page']);
        $t->same('names-tree', $destinations[0]['source']);
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
    },
    'honors destination name-tree Limits before WordPress named destination import' => static function (TestRunner $t): void {
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /DeckStart [4 0 R /Fit] /LegacyOnly [4 0 R /FitV 90] >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Limits [(A) (Zzz)] /Kids [9 0 R 10 0 R 11 0 R] >>\nendobj\n"
            . "9 0 obj\n<< /Limits [(A) (Mzz)] /Names [(DeckStart) [3 0 R /FitH 700] (DeckReview) 12 0 R (Z Stale Deck) [4 0 R /Fit]] >>\nendobj\n"
            . "10 0 obj\n<< /Limits [(N) (Zzz)] /Names [(DeckStart) 13 0 R (ZedTarget) [4 0 R /Fit]] >>\nendobj\n"
            . "11 0 obj\n<< /Limits [(ZebraAppendix) (Zzz)] /Names [(A Stale Deck) [4 0 R /Fit] (ZebraAppendix) [4 0 R /FitBH 600]] >>\nendobj\n"
            . "12 0 obj\n<< /D [3 0 R /XYZ 72 690 0] >>\nendobj\n"
            . "13 0 obj\n<< /D [4 0 R /FitV 80] >>\nendobj\n"
            . "%%EOF\n";

        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);

        $t->same(['DeckStart', 'DeckReview', 'ZedTarget', 'ZebraAppendix', 'LegacyOnly'], array_column($destinations, 'name'));
        $t->same([0, 0, 1, 1, 1], array_column($destinations, 'page'));
        $t->same(['FitH', 'XYZ', 'Fit', 'FitBH', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 690.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same([], $destinations[2]['coordinates']);
        $t->same(['top' => 600.0], $destinations[3]['coordinates']);
        $t->same(['left' => 90.0], $destinations[4]['coordinates']);
        $t->true(!in_array('Z Stale Deck', array_column($destinations, 'name'), true));
        $t->true(!in_array('A Stale Deck', array_column($destinations, 'name'), true));
    },
    'honors indirect object generations before WordPress named destination import' => static function (TestRunner $t): void {
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] /LegacyStale 30 1 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Kids [9 0 R 10 1 R 11 0 R] >>\nendobj\n"
            . "9 0 obj\n<< /Limits [(A) (Mzz)] /Names [(CurrentDirect) [3 0 R /FitH 710] 16 1 R 17 1 R 12 1 R [4 0 R /Fit] (BadPageGen) [4 1 R /Fit] (BadDestDictGen) 14 1 R] >>\nendobj\n"
            . "10 0 obj\n<< /Limits [(N) (Zzz)] /Names [(WrongKidGen) [4 0 R /Fit]] >>\nendobj\n"
            . "11 0 obj\n<< /Limits [(N) (Zzz)] /Names [(ReviewOk) 15 0 R] >>\nendobj\n"
            . "12 0 obj\n(MismatchedName)\nendobj\n"
            . "14 0 obj\n<< /D [4 0 R /FitR 1 2 3 4] >>\nendobj\n"
            . "15 0 obj\n<< /D [4 0 R /XYZ 72 640 0] >>\nendobj\n"
            . "16 1 obj\n(IndirectCurrent)\nendobj\n"
            . "17 1 obj\n<< /D [4 0 R /FitBH 600] >>\nendobj\n"
            . "30 0 obj\n<< /D [4 0 R /FitH 300] >>\nendobj\n"
            . "%%EOF\n";

        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $names = array_column($destinations, 'name');

        $t->same(['CurrentDirect', 'IndirectCurrent', 'ReviewOk', 'LegacyOk'], $names);
        $t->same([0, 1, 1, 1], array_column($destinations, 'page'));
        $t->same(['FitH', 'FitBH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['top' => 710.0], $destinations[0]['coordinates']);
        $t->same(['top' => 600.0], $destinations[1]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[2]['coordinates']);
        $t->same(['left' => 120.0], $destinations[3]['coordinates']);
        $t->true(!in_array('WrongKidGen', $names, true));
        $t->true(!in_array('MismatchedName', $names, true));
        $t->true(!in_array('BadPageGen', $names, true));
        $t->true(!in_array('BadDestDictGen', $names, true));
        $t->true(!in_array('LegacyStale', $names, true));
    },
    'decodes PDFDocEncoding name-tree keys before WordPress named destination metadata' => static function (TestRunner $t): void {
        $bulletName = 'wp' . chr(0x80) . ' review';
        $quoteName = 'Deck ' . chr(0x8d) . 'draft' . chr(0x8e);
        $euroName = 'Budget ' . chr(0xa0);
        $quoteNameHex = strtoupper(bin2hex($quoteName));

        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Names [({$bulletName}) [3 0 R /FitH 610] <{$quoteNameHex}> [4 0 R /XYZ 72 640 0] ({$euroName}) 9 0 R] >>\nendobj\n"
            . "9 0 obj\n<< /D [4 0 R /FitBV 140] >>\nendobj\n"
            . "%%EOF\n";

        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);

        $t->same(['wp' . "\u{2022}" . ' review', 'Deck ' . "\u{201c}" . 'draft' . "\u{201d}", 'Budget ' . "\u{20ac}"], array_column($destinations, 'name'));
        $t->same([0, 1, 1], array_column($destinations, 'page'));
        $t->same(['FitH', 'XYZ', 'FitBV'], array_column($destinations, 'fit'));
        $t->same(['top' => 610.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 140.0], $destinations[2]['coordinates']);
    },
    'skips malformed destination values and rejects non PDF bytes' => static function (TestRunner $t): void {
        $extractor = new PdfNamedDestinationExtractor();
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests << /Names [(ok) [3 0 R /Fit] (bad) /NotAnArray (empty) []] >> >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "%%EOF\n";

        $destinations = $extractor->extractNamedDestinations($pdf);

        $t->same(1, count($destinations));
        $t->same('ok', $destinations[0]['name']);
        $t->throws(InvalidArgumentException::class, static fn (): array => $extractor->extractNamedDestinations('not a pdf'));
    },
];
