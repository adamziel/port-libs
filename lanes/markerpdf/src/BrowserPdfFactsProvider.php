<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use RuntimeException;

/**
 * Adds bounded PDF.js observations to native page facts when a browser was
 * available. Native facts remain authoritative fallback evidence and are
 * never discarded by an absent, partial, stale, or malformed handoff.
 */
final class BrowserPdfFactsProvider implements PdfFactsProvider
{
    private const HANDOFF_SCHEMA_VERSION = 1;
    private const MAX_HANDOFF_BYTES = 4_194_304;
    private const MAX_PAGES = 2_000;
    private const MAX_SPANS_PER_PAGE = 100_000;
    private const MAX_STRUCTURE_NODES_PER_PAGE = 50_000;

    public function __construct(private readonly ?PdfFactsProvider $fallback = null)
    {
    }

    public function providerId(): string
    {
        return 'pdfjs-v1';
    }

    /**
     * @param array<string, mixed> $options
     */
    public function extract(string $pdfBytes, array $options = []): PdfDocumentFacts
    {
        $handoff = $options['browserFacts'] ?? null;
        unset($options['browserFacts']);
        $fallback = $this->fallback ?? new NativePdfFactsProvider();
        $native = $fallback->extract($pdfBytes, $options);
        if (!is_array($handoff)) {
            return $this->withStatus($native, 'unavailable', 'No browser facts were supplied.', 0, 0);
        }

        try {
            $validated = $this->validatedHandoff($handoff, $native);
        } catch (RuntimeException $error) {
            return $this->withStatus($native, 'rejected', $error->getMessage(), 0, 0);
        }

        $data = $native->toArray();
        $pagesByNumber = [];
        foreach ($validated['pages'] as $browserPage) {
            $pagesByNumber[$browserPage['pageNumber']] = $browserPage;
        }
        $applied = 0;
        foreach ($data['pages'] as &$page) {
            $pageNumber = $page['pageNumber'];
            $browserPage = $pagesByNumber[$pageNumber] ?? null;
            if (!is_array($browserPage)) {
                continue;
            }
            $spans = [];
            foreach ($browserPage['spans'] as $index => $span) {
                $spans[] = $this->decorateSpan($span, $native->source()['sha256'], $pageNumber, $index);
            }
            $page['text']['browser'] = [
                'provider' => $this->providerId(),
                'viewport' => $browserPage['viewport'],
                'spans' => $spans,
                'markedContent' => $browserPage['markedContent'],
                'styles' => $browserPage['styles'],
            ];
            $page['structure']['browser'] = [
                'provider' => $this->providerId(),
                'tree' => $browserPage['structure'],
            ];
            $applied++;
        }
        unset($page);

        $provided = count($validated['pages']);
        $status = $applied === 0 ? 'unavailable' : ($applied === count($native->pages()) ? 'applied' : 'partial');
        $reason = $status === 'applied'
            ? 'Browser text and structure facts were attached to every selected page.'
            : ($status === 'partial'
                ? 'Browser facts were attached where available; remaining pages retain native facts only.'
                : 'The browser handoff did not contain facts for the selected page range.');
        $data['provider'] = $applied > 0
            ? $native->provider() . '+' . $this->providerId()
            : $native->provider();
        $data['diagnostics']['browserFacts'] = $this->status($status, $reason, $provided, $applied, $validated['failures']);

        return PdfDocumentFacts::fromArray($data);
    }

    private function withStatus(
        PdfDocumentFacts $native,
        string $status,
        string $reason,
        int $provided,
        int $applied
    ): PdfDocumentFacts {
        $data = $native->toArray();
        $data['diagnostics']['browserFacts'] = $this->status($status, $reason, $provided, $applied, []);

        return PdfDocumentFacts::fromArray($data);
    }

    /**
     * @param list<array<string, mixed>> $failures
     * @return array<string, mixed>
     */
    private function status(string $status, string $reason, int $provided, int $applied, array $failures): array
    {
        return [
            'provider' => $this->providerId(),
            'status' => $status,
            'reason' => $reason,
            'providedPages' => $provided,
            'appliedPages' => $applied,
            'failures' => $failures,
        ];
    }

    /**
     * @param array<string, mixed> $handoff
     * @return array{pages:list<array<string,mixed>>,failures:list<array<string,mixed>>}
     */
    private function validatedHandoff(array $handoff, PdfDocumentFacts $native): array
    {
        $encoded = json_encode($handoff, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        if (!is_string($encoded) || strlen($encoded) > self::MAX_HANDOFF_BYTES) {
            throw new RuntimeException('The browser facts handoff exceeded its safe serialized size.');
        }
        if (($handoff['schemaVersion'] ?? null) !== self::HANDOFF_SCHEMA_VERSION
            || ($handoff['provider'] ?? null) !== $this->providerId()
        ) {
            throw new RuntimeException('The browser facts handoff used an unsupported schema or provider.');
        }
        if (($handoff['sourceSha256'] ?? null) !== $native->source()['sha256']) {
            throw new RuntimeException('The browser facts did not match the selected PDF.');
        }
        if (($handoff['pageCount'] ?? null) !== $native->inventory()['totalPages']) {
            throw new RuntimeException('The browser and server resolved different PDF page counts.');
        }
        $pages = $handoff['pages'] ?? null;
        if (!is_array($pages) || count($pages) > self::MAX_PAGES) {
            throw new RuntimeException('The browser facts page list was invalid or too large.');
        }
        $validatedPages = [];
        $seenPages = [];
        foreach ($pages as $page) {
            if (!is_array($page)) {
                throw new RuntimeException('One browser facts page was malformed.');
            }
            $pageNumber = $page['pageNumber'] ?? null;
            if (!is_int($pageNumber) || $pageNumber < 1 || $pageNumber > $native->inventory()['totalPages'] || isset($seenPages[$pageNumber])) {
                throw new RuntimeException('One browser facts page number was invalid or duplicated.');
            }
            $seenPages[$pageNumber] = true;
            $spans = $page['spans'] ?? null;
            $viewport = $page['viewport'] ?? null;
            $markedContent = $page['markedContent'] ?? null;
            $styles = $page['styles'] ?? null;
            $structure = $page['structure'] ?? null;
            if (!is_array($spans) || count($spans) > self::MAX_SPANS_PER_PAGE
                || !is_array($viewport) || !is_array($markedContent) || !is_array($styles)
                || ($structure !== null && !is_array($structure))
            ) {
                throw new RuntimeException('One browser facts page exceeded its bounded text or structure shape.');
            }
            foreach ($spans as $span) {
                $this->validateSpan($span);
            }
            if ($this->countStructureNodes($structure) > self::MAX_STRUCTURE_NODES_PER_PAGE) {
                throw new RuntimeException('One browser structure tree exceeded its safe node limit.');
            }
            $validatedPages[] = [
                'pageNumber' => $pageNumber,
                'viewport' => $viewport,
                'spans' => array_values($spans),
                'markedContent' => array_values($markedContent),
                'styles' => $styles,
                'structure' => $structure,
            ];
        }
        $failures = [];
        foreach ($handoff['failures'] ?? [] as $failure) {
            if (is_array($failure) && is_int($failure['pageNumber'] ?? null) && is_string($failure['reason'] ?? null)) {
                $failures[] = [
                    'pageNumber' => $failure['pageNumber'],
                    'reason' => substr($failure['reason'], 0, 500),
                ];
            }
        }

        return ['pages' => $validatedPages, 'failures' => $failures];
    }

    private function validateSpan(mixed $span): void
    {
        if (!is_array($span) || !is_string($span['text'] ?? null) || strlen($span['text']) > 262_144
            || !is_string($span['direction'] ?? null) || !is_array($span['transform'] ?? null)
            || count($span['transform']) !== 6 || !is_string($span['fontName'] ?? null)
            || !is_bool($span['hasEol'] ?? null)
        ) {
            throw new RuntimeException('One browser text span was malformed or too large.');
        }
        foreach (array_merge($span['transform'], [$span['width'] ?? null, $span['height'] ?? null]) as $number) {
            if ((!is_int($number) && !is_float($number)) || !is_finite((float) $number)) {
                throw new RuntimeException('One browser text span contained invalid geometry.');
            }
        }
    }

    private function countStructureNodes(mixed $value, int $depth = 0): int
    {
        if ($value === null || !is_array($value)) {
            return 0;
        }
        if ($depth > 100) {
            return self::MAX_STRUCTURE_NODES_PER_PAGE + 1;
        }
        $count = 1;
        foreach ($value as $child) {
            if (is_array($child)) {
                $count += $this->countStructureNodes($child, $depth + 1);
                if ($count > self::MAX_STRUCTURE_NODES_PER_PAGE) {
                    return $count;
                }
            }
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $span
     * @return array<string, mixed>
     */
    private function decorateSpan(array $span, string $sourceHash, int $pageNumber, int $index): array
    {
        $identity = json_encode([$sourceHash, $pageNumber, $index, $span], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

        return [
            'id' => 'browser-span-' . substr(hash('sha256', is_string($identity) ? $identity : serialize($span)), 0, 24),
            'provenance' => [
                'provider' => $this->providerId(),
                'kind' => 'span',
                'page' => $pageNumber,
                'index' => $index,
            ],
        ] + $span;
    }
}
