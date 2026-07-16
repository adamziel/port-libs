<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class TextCleaner
{
    public function replaceBullets(string $text): string
    {
        return preg_replace('/(^|[\n ])[•●○■▪▫–—]( )/u', '$1-$2', $text) ?? $text;
    }

    public function cleanupText(string $text): string
    {
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        $text = preg_replace("/(\n\s){3,}/", "\n\n", $text) ?? $text;

        return str_replace("\xc2\xa0", ' ', $text);
    }

    public function cleanForMarkdown(string $text): string
    {
        return $this->cleanupText($this->replaceBullets($text));
    }
}
