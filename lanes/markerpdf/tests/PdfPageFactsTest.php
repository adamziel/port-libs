<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\NativePdfFactsProvider;
use PortLibs\MarkerPDF\PdfDocumentFacts;
use PortLibs\MarkerPDF\PdfPageFacts;

$pageFactsPdf = static function (): string {
    $pageOneContent = "q 40 0 0 30 20 40 cm /Im1 Do Q\n"
        . "q 0.2 0.4 0.6 rg 20 90 120 12 re f Q\n"
        . "BT /F1 12 Tf 72 520 Td (Alpha) Tj 0 -18 Td (Beta) Tj ET";
    $pageTwoContent = "q 1 0 0 1 80 300 cm /Fm1 Do Q\n"
        . "BT /F1 14 Tf 72 500 Td (Gamma) Tj ET";
    $formContent = "BT /F1 10 Tf 2 15 Td (Chart label) Tj ET";
    $imageBytes = "\xff\x00\x00";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 5 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 400 600] /CropBox [10 20 390 580] "
        . "/Resources << /Font << /F1 7 0 R >> /XObject << /Im1 8 0 R >> >> "
        . "/Annots [9 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 500 700] /Rotate 90 "
        . "/Resources << /Font << /F1 7 0 R >> /XObject << /Fm1 10 0 R >> >> /Contents 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB "
        . "/BitsPerComponent 8 /Length " . strlen($imageBytes) . " >>\nstream\n{$imageBytes}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 510 140 530] "
        . "/A << /S /URI /URI (https://example.com) >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 100 40] "
        . "/Resources << /Font << /F1 7 0 R >> >> /Length " . strlen($formContent) . " >>\n"
        . "stream\n{$formContent}\nendstream\nendobj\n"
        . "%%EOF";
};

$allFactIds = static function (PdfPageFacts $page): array {
    $text = $page->text();
    $graphics = $page->graphics();
    $annotations = $page->annotations();
    $rows = array_merge(
        $text['lines'],
        $text['runs'],
        $text['spans'],
        $graphics['filledRectangles'],
        $graphics['images'],
        $graphics['forms'],
        $page->issues(),
        ...array_values($annotations)
    );

    return array_column($rows, 'id');
};

return [
    'collects provider-neutral page text geometry graphics and annotations' => static function (
        TestRunner $t
    ) use ($pageFactsPdf, $allFactIds): void {
        $pdf = $pageFactsPdf();
        $facts = (new NativePdfFactsProvider())->extract($pdf);

        $t->same('native-php-v1', $facts->provider());
        $t->same(hash('sha256', $pdf), $facts->source()['sha256']);
        $t->same(strlen($pdf), $facts->source()['byteLength']);
        $t->same([1, 2], $facts->inventory()['pageNumbers']);
        $t->same(2, count($facts->pages()));

        $pageOne = $facts->page(1);
        $t->true($pageOne instanceof PdfPageFacts);
        $t->same(3, $pageOne->pageObject());
        $t->same([10.0, 20.0, 390.0, 580.0], $pageOne->geometry()['bbox']);
        $t->same(['Alpha', 'Beta'], array_column($pageOne->text()['lines'], 'text'));
        $t->true(count($pageOne->text()['spans']) >= 2);
        $t->same(1, count($pageOne->graphics()['filledRectangles']));
        $t->same(1, count($pageOne->graphics()['images']));
        $t->same(1, count($pageOne->annotations()['links']));

        $pageTwo = $facts->page(2);
        $t->true($pageTwo instanceof PdfPageFacts);
        $t->same(90, $pageTwo->geometry()['rotation']);
        $t->same(1, count($pageTwo->graphics()['forms']));

        $ids = array_merge($allFactIds($pageOne), $allFactIds($pageTwo));
        $t->same(count($ids), count(array_unique($ids)));
        foreach ($ids as $id) {
            $t->true(is_string($id) && preg_match('/^[a-z-]+-[a-f0-9]{24}$/', $id) === 1);
        }
    },
    'round trips page facts through stable JSON without source bytes' => static function (
        TestRunner $t
    ) use ($pageFactsPdf): void {
        $pdf = $pageFactsPdf();
        $facts = (new NativePdfFactsProvider())->extract($pdf);
        $json = $facts->toJson();
        $restored = PdfDocumentFacts::fromJson($json);

        $t->same($facts->toArray(), $restored->toArray());
        $t->true(!str_contains($json, base64_encode($pdf)));
        $t->same(PdfDocumentFacts::SCHEMA_VERSION, $restored->toArray()['schemaVersion']);
        $t->same(PdfPageFacts::SCHEMA_VERSION, $restored->page(1)?->toArray()['schemaVersion']);
    },
    'uses the same fact IDs when a page is extracted in a resumed range' => static function (
        TestRunner $t
    ) use ($pageFactsPdf, $allFactIds): void {
        $pdf = $pageFactsPdf();
        $provider = new NativePdfFactsProvider();
        $complete = $provider->extract($pdf);
        $resumed = $provider->extract($pdf, ['startPage' => 2, 'maxPages' => 1]);

        $t->same([2], $resumed->inventory()['pageNumbers']);
        $t->same(true, $resumed->inventory()['hasMorePages'] === false);
        $t->same($allFactIds($complete->page(2)), $allFactIds($resumed->page(2)));
    },
    'rejects incompatible or malformed serialized facts' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn (): PdfPageFacts => PdfPageFacts::fromArray([]));
        $t->throws(InvalidArgumentException::class, static fn (): PdfDocumentFacts => PdfDocumentFacts::fromArray([
            'schemaVersion' => 999,
        ]));
        $t->throws(InvalidArgumentException::class, static fn (): PdfDocumentFacts => new PdfDocumentFacts(
            'native-php-v1',
            ['sha256' => 'not-a-digest', 'byteLength' => 1],
            [],
            []
        ));
    },
];
