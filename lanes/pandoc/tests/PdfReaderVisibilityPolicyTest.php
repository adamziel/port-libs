<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PdfReader;

$pdfWithPage = static function (string $content, string $resources = '<< >>'): string {
    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
        . "/CropBox [0 0 612 792] /Resources {$resources} /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . ">>\nstream\n{$content}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'keeps nonvisible accessibility replacements out of output under a complete visibility policy' => static function (
        TestRunner $t
    ) use ($pdfWithPage): void {
        $resources = '<< /ExtGState << /Zero << /ca 0 /CA 0 >> /Opaque << /ca 1 /CA 1 >> >> >>';
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm '
            . '/Zero gs 0 Tr /Span << /ActualText (SECRET-ACCESSIBILITY-ONLY) >> BDC (hidden) Tj EMC T* '
            . '/Opaque gs (Visible body) Tj ET';
        $document = (new PdfReader())->read($pdfWithPage($content, $resources));
        $plain = PandocConverter::write($document, 'plain');
        $meta = $document->attr('meta');

        $t->contains('Visible body', $plain);
        $t->true(!str_contains($plain, 'SECRET-ACCESSIBILITY-ONLY'));
        $t->same(true, $meta['pdfTextVisibilityComplete'] ?? null);
        $t->same(true, $meta['pdfSemanticTextComplete'] ?? null);
        $t->same('separate-from-visible-output', $meta['pdfAccessibilityTextDisposition']['policy'] ?? null);
        $t->same(1, $meta['pdfAccessibilityTextDisposition']['suppressedReplacementRuns'] ?? null);
        $t->same(1, $meta['pdfAccessibilityTextDisposition']['visibleOutputRuns'] ?? null);
    },

    'prevents semantic certification when retained text has unresolved soft-mask visibility' => static function (
        TestRunner $t
    ) use ($pdfWithPage): void {
        $resources = '<< /ExtGState << /Masked << /ca 1 /CA 1 /SMask << /S /Alpha >> >> >> >>';
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm /Masked gs (Review visibility) Tj ET';
        $document = (new PdfReader())->read($pdfWithPage($content, $resources));
        $plain = PandocConverter::write($document, 'plain');
        $meta = $document->attr('meta');

        $t->contains('Review visibility', $plain);
        $t->same(false, $meta['pdfTextVisibilityComplete'] ?? null);
        $t->same(false, $meta['pdfSemanticTextComplete'] ?? null);
        $t->true(in_array('text-visibility-unresolved', $meta['pdfLimitReasons'] ?? [], true));
        $t->true(in_array(
            'ext-gstate-opacity-or-soft-mask',
            $meta['pdfTextVisibility']['unresolvedReasons'] ?? [],
            true
        ));
    },
];
