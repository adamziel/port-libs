<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\TextCleaner;

$pdfWithContent = static function (string $content): string {
    return "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'replaces marker upstream bullet characters with markdown list markers' => static function (TestRunner $t): void {
        $cleaner = new TextCleaner();
        $text = "• Alpha\n● Beta\n○ Gamma\n■ Delta\n▪ Epsilon\n▫ Zeta\n– Eta\n— Theta";

        $t->same("- Alpha\n- Beta\n- Gamma\n- Delta\n- Epsilon\n- Zeta\n- Eta\n- Theta", $cleaner->replaceBullets($text));
    },
    'keeps non-list punctuation outside upstream bullet pattern' => static function (TestRunner $t): void {
        $cleaner = new TextCleaner();

        $t->same('word• item', $cleaner->replaceBullets('word• item'));
        $t->same('•item', $cleaner->replaceBullets('•item'));
        $t->same('Sentence - already ascii', $cleaner->replaceBullets('Sentence - already ascii'));
    },
    'cleans repeated whitespace and non-breaking spaces like marker text cleaner' => static function (TestRunner $t): void {
        $cleaner = new TextCleaner();

        $t->same("First\n\nSecond line", $cleaner->cleanupText("First\n\n\n\nSecond\xc2\xa0line"));
        $t->same("First\n\nSecond", $cleaner->cleanupText("First\n \n \n Second"));
    },
    'normalizes extracted PDF bullets for a WordPress list block import' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Migration Checklist) Tj T* (• Preserve headings) Tj T* (● Normalize bullets) Tj T* (— Convert to list blocks) Tj ET';
        $lines = (new PdfTextExtractor())->extractTextLines($pdfWithContent($content));
        $cleaned = (new TextCleaner())->cleanForMarkdown(implode("\n", $lines));

        $t->same("Migration Checklist\n- Preserve headings\n- Normalize bullets\n- Convert to list blocks", $cleaned);
    },
];
