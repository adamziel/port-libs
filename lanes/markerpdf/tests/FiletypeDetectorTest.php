<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\FiletypeDetector;
use PortLibs\MarkerPDF\MarkerSettings;

$makeTempFile = static function (string $bytes, string $suffix = '.bin'): string {
    $path = sys_get_temp_dir() . '/markerpdf-filetype-' . bin2hex(random_bytes(4)) . $suffix;
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Unable to write temporary markerPDF filetype fixture.');
    }

    return $path;
};

return [
    'maps marker pdf utils find_filetype for real pdf fixture bytes' => static function (TestRunner $t): void {
        $path = __DIR__ . '/../fixtures/wordpress-import-content.pdf';

        $t->same('pdf', (new FiletypeDetector())->findFiletype($path));
    },
    'returns other when upstream filetype guess has no usable kind' => static function (TestRunner $t) use ($makeTempFile): void {
        $path = $makeTempFile("plain text is not a marker-supported document\n", '.txt');
        try {
            $detector = new FiletypeDetector();

            $t->same('other', $detector->findFiletype($path));
            $t->same('other', $detector->findFiletype($path . '.missing'));
            $t->same('other', $detector->findFiletypeFromBytes(''));
        } finally {
            unlink($path);
        }
    },
    'treats any guessed pdf mimetype as pdf before consulting supported filetypes' => static function (TestRunner $t): void {
        $detector = new FiletypeDetector(new MarkerSettings([
            'SUPPORTED_FILETYPES' => ['application/x-pdf' => 'custom-pdf'],
        ]));

        $t->same('pdf', $detector->filetypeFromMimeType('application/x-pdf'));
        $t->same('pdf', $detector->filetypeFromMimeType('APPLICATION/PDF'));
    },
    'maps non-pdf mimetypes through marker settings and rejects unsupported guesses' => static function (TestRunner $t): void {
        $detector = new FiletypeDetector(new MarkerSettings([
            'SUPPORTED_FILETYPES' => [
                'application/pdf' => 'pdf',
                'application/epub+zip' => 'epub',
            ],
        ]));

        $t->same('epub', $detector->filetypeFromMimeType('application/epub+zip'));
        $t->same('other', $detector->filetypeFromMimeType('image/png'));
        $t->same('other', $detector->findFiletypeFromBytes("\x89PNG\r\n\x1a\nnot a supported marker document"));
    },
    'drives a WordPress upload preflight from file magic rather than extension text' => static function (TestRunner $t) use ($makeTempFile): void {
        $pdf = $makeTempFile("%PDF-1.7\n% WordPress export\n%%EOF", '.notpdf');
        $docxLikeZip = $makeTempFile("PK\x03\x04fake docx payload", '.pdf');
        try {
            $detector = new FiletypeDetector();

            $t->same('pdf', $detector->findFiletype($pdf));
            $t->same('other', $detector->findFiletype($docxLikeZip));
        } finally {
            unlink($pdf);
            unlink($docxLikeZip);
        }
    },
];
