<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

interface PdfFactsProvider
{
    public function providerId(): string;

    /**
     * Extract lossless, serializable source facts without deciding document
     * structure or producing output blocks.
     *
     * @param array<string, mixed> $options
     */
    public function extract(string $pdfBytes, array $options = []): PdfDocumentFacts;
}
