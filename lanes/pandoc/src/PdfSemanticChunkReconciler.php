<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

use Generator;
use PortLibs\MarkerPDF\PdfDocumentFacts;
use PortLibs\MarkerPDF\PdfPageFacts;
use RuntimeException;

/**
 * Turn arbitrary bounded extraction ranges into deterministic semantic passes.
 *
 * PDF extraction and PDF interpretation have different useful chunk sizes. A
 * resumable importer may collect one or two pages per request, while the
 * document reader needs enough adjacent pages to distinguish page furniture,
 * continued lists, columns, captions, and prose repairs. Feeding extraction
 * ranges directly to PdfReader therefore makes the AST depend on the request
 * size.
 *
 * This reconciler verifies the durable source/page provenance, buffers a fixed
 * number of pages, and gives every PdfReader pass the same immutable complete
 * document profile. The semantic partition is consequently independent of the
 * extraction partition. `semanticDocuments()` is streaming so a caller can
 * persist each resulting AST part before requesting the next one; `reconcile()`
 * is the convenience form for callers which need one in-memory document.
 */
final class PdfSemanticChunkReconciler
{
    public const DEFAULT_SEMANTIC_WINDOW_PAGES = 8;

    /** @var array<string, mixed> */
    private array $stats = [];

    /**
     * @param array<string, mixed> $readerOptions
     */
    public function __construct(
        private readonly array $readerOptions = [],
        private readonly int $semanticWindowPages = self::DEFAULT_SEMANTIC_WINDOW_PAGES
    ) {
        if ($semanticWindowPages < 2 || $semanticWindowPages > 32) {
            throw new RuntimeException('PDF semantic windows must contain between 2 and 32 pages.');
        }
    }

    /**
     * Reconcile a complete ordered stream of bounded facts ranges.
     *
     * The input iterable is intentionally consumed lazily. Production callers
     * can deserialize one persisted range at a time instead of constructing a
     * complete PdfDocumentFacts object.
     *
     * @param iterable<PdfDocumentFacts> $factRanges
     */
    public function reconcile(string $pdfBytes, iterable $factRanges): AstNode
    {
        $blocks = [];
        $semanticDocuments = 0;
        $familiesById = [];
        $continuationsById = [];
        foreach ($this->semanticDocuments($pdfBytes, $factRanges) as $document) {
            array_push($blocks, ...$document->children());
            $documentMetadata = $document->attr('meta', []);
            if (is_array($documentMetadata)) {
                foreach (is_array($documentMetadata['pdfLogicalTableFamilies'] ?? null)
                    ? $documentMetadata['pdfLogicalTableFamilies']
                    : [] as $family) {
                    if (!is_array($family) || !is_string($family['id'] ?? null) || $family['id'] === '') {
                        continue;
                    }
                    $familiesById[$family['id']] = $this->unionLogicalTableFamily(
                        $familiesById[$family['id']] ?? null,
                        $family
                    );
                }
                foreach (is_array($documentMetadata['pdfTableContinuations'] ?? null)
                    ? $documentMetadata['pdfTableContinuations']
                    : [] as $continuation) {
                    if (!is_array($continuation)
                        || !is_string($continuation['id'] ?? null)
                        || $continuation['id'] === '') {
                        continue;
                    }
                    $continuationsById[$continuation['id']] = $this->unionTableContinuation(
                        $continuationsById[$continuation['id']] ?? null,
                        $continuation
                    );
                }
            }
            $semanticDocuments++;
        }

        $families = array_values($familiesById);
        usort($families, static fn (array $left, array $right): int =>
            ((int) ($left['firstPage'] ?? 0) <=> (int) ($right['firstPage'] ?? 0))
                ?: ((string) ($left['id'] ?? '') <=> (string) ($right['id'] ?? ''))
        );
        $continuations = array_values($continuationsById);
        usort($continuations, static fn (array $left, array $right): int =>
            ((int) ($left['firstPage'] ?? 0) <=> (int) ($right['firstPage'] ?? 0))
                ?: ((string) ($left['id'] ?? '') <=> (string) ($right['id'] ?? ''))
        );
        $tableCounts = $this->logicalTableCounts($blocks);
        $this->stats['logicalTableFamilies'] = count($families);
        $this->stats['logicalTables'] = $tableCounts['logical'];
        $this->stats['physicalTables'] = $tableCounts['physical'];

        $metadata = [
            'pdfDetectedTables' => $tableCounts['physical'],
            'pdfLogicalTableCount' => $tableCounts['logical'],
            'pdfLogicalTableFamilies' => $families,
            'pdfLogicalTableFamilyCount' => count($families),
            'pdfLogicalTableInstanceCount' => array_sum(array_map(
                static fn (array $family): int => count(is_array($family['instances'] ?? null) ? $family['instances'] : []),
                $families
            )),
            'pdfLogicalTableFamilyPhysicalParts' => array_sum(array_map(
                static fn (array $family): int => max(0, (int) ($family['physicalParts'] ?? 0)),
                $families
            )),
            'pdfTableContinuations' => $continuations,
            'pdfSemanticChunkReconciliation' => array_replace($this->stats, [
                'semanticDocuments' => $semanticDocuments,
            ]),
        ];

        return new AstNode('document', ['meta' => $metadata], $blocks);
    }

    /**
     * Yield deterministic, bounded PdfReader results suitable for incremental
     * publication. Each yielded document owns one disjoint source-page range.
     *
     * @param iterable<PdfDocumentFacts> $factRanges
     * @return Generator<int, AstNode>
     */
    public function semanticDocuments(string $pdfBytes, iterable $factRanges): Generator
    {
        $this->stats = [
            'schemaVersion' => 1,
            'semanticWindowPages' => $this->semanticWindowPages,
            'maxBufferedPages' => 0,
            'maxInputRangePages' => 0,
            'semanticPasses' => 0,
            'processedPages' => 0,
            'pageProvenanceDigest' => null,
            'loadedWholeDocument' => false,
        ];

        /** @var list<PdfPageFacts> $pageBuffer */
        $pageBuffer = [];
        $windowStartPage = 1;
        $nextPage = 1;
        $totalPages = null;
        $source = null;
        $profile = null;
        $profileDigest = null;
        $structure = [];
        $diagnostics = [];
        $unassignedAnnotations = [];
        $pageProvenance = hash_init('sha256');
        $sawRange = false;
        /** @var array<string, true> $windowProviderParts */
        $windowProviderParts = [];

        foreach ($factRanges as $range) {
            if (!$range instanceof PdfDocumentFacts) {
                throw new RuntimeException('PDF semantic reconciliation requires PdfDocumentFacts ranges.');
            }
            $sawRange = true;
            $rangePages = $range->pages();
            $rangePageCount = count($rangePages);
            if ($rangePageCount < 1) {
                throw new RuntimeException('PDF semantic reconciliation received an empty facts range.');
            }
            if ($rangePageCount > $this->semanticWindowPages) {
                throw new RuntimeException(sprintf(
                    'PDF extraction ranges must remain bounded to at most %d pages before semantic reconciliation.',
                    $this->semanticWindowPages
                ));
            }
            $this->stats['maxInputRangePages'] = max(
                (int) $this->stats['maxInputRangePages'],
                $rangePageCount
            );

            $rangeSource = $range->source();
            $rangeInventory = $range->inventory();
            $rangeTotalPages = max(0, (int) ($rangeInventory['totalPages'] ?? 0));
            $rangeProfile = $range->structure()['documentProfile'] ?? null;
            if (!is_array($rangeProfile)
                || ($rangeProfile['complete'] ?? null) !== true
                || !is_string($rangeProfile['profileDigest'] ?? null)
                || preg_match('/^[a-f0-9]{64}$/', $rangeProfile['profileDigest']) !== 1) {
                throw new RuntimeException('PDF semantic reconciliation requires one complete immutable document profile.');
            }

            if ($source === null) {
                $rangeData = $range->toArray();
                $source = $rangeSource;
                $totalPages = $rangeTotalPages;
                $profile = $rangeProfile;
                $profileDigest = $rangeProfile['profileDigest'];
                $structure = $range->structure();
                $diagnostics = $range->diagnostics();
                $unassignedAnnotations = is_array($rangeData['unassignedAnnotations'] ?? null)
                    ? $rangeData['unassignedAnnotations']
                    : [];

                if ($totalPages < 1
                    || (int) ($profile['totalPages'] ?? 0) !== $totalPages
                    || !is_string($source['sha256'] ?? null)
                    || !hash_equals(hash('sha256', $pdfBytes), $source['sha256'])
                    || (int) ($source['byteLength'] ?? -1) !== strlen($pdfBytes)
                    || !hash_equals((string) ($profile['sourceSha256'] ?? ''), $source['sha256'])) {
                    throw new RuntimeException('PDF semantic reconciliation source, inventory, and profile evidence did not agree.');
                }
                $this->stats['sourceSha256'] = $source['sha256'];
                $this->stats['profileDigest'] = $profileDigest;
                $this->stats['totalPages'] = $totalPages;
                unset($rangeData);
            } elseif ($rangeSource !== $source
                || $rangeTotalPages !== $totalPages
                || $rangeProfile !== $profile
                || !hash_equals((string) $profileDigest, (string) $rangeProfile['profileDigest'])) {
                throw new RuntimeException('PDF semantic reconciliation ranges did not share immutable source/profile evidence.');
            }

            $declaredPageNumbers = array_values(array_map(
                static fn (mixed $page): int => (int) $page,
                is_array($rangeInventory['pageNumbers'] ?? null) ? $rangeInventory['pageNumbers'] : []
            ));
            $actualPageNumbers = array_map(
                static fn (PdfPageFacts $page): int => $page->pageNumber(),
                $rangePages
            );
            if ($declaredPageNumbers !== $actualPageNumbers
                || (int) ($rangeInventory['startPage'] ?? 0) !== $actualPageNumbers[0]
                || (int) ($rangeInventory['endPage'] ?? 0) !== $actualPageNumbers[$rangePageCount - 1]) {
                throw new RuntimeException('PDF semantic reconciliation range inventory did not match its page facts.');
            }

            foreach ($rangePages as $page) {
                if ($page->pageNumber() !== $nextPage) {
                    throw new RuntimeException(sprintf(
                        'PDF semantic reconciliation expected page %d but received page %d.',
                        $nextPage,
                        $page->pageNumber()
                    ));
                }
                $this->appendVerifiedPageProvenance($pageProvenance, $page, (string) $source['sha256']);
                foreach (explode('+', $range->provider()) as $providerPart) {
                    if ($providerPart !== '') {
                        $windowProviderParts[$providerPart] = true;
                    }
                }
                $pageBuffer[] = $page;
                $this->stats['maxBufferedPages'] = max(
                    (int) $this->stats['maxBufferedPages'],
                    count($pageBuffer)
                );
                $nextPage++;

                if (count($pageBuffer) !== $this->semanticWindowPages) {
                    continue;
                }

                $windowEndPage = $nextPage - 1;
                $document = $this->readSemanticWindow(
                    $pdfBytes,
                    implode('+', array_keys($windowProviderParts)),
                    $source,
                    (int) $totalPages,
                    $windowStartPage,
                    $windowEndPage,
                    $pageBuffer,
                    $structure,
                    $profile,
                    $diagnostics,
                    $unassignedAnnotations
                );
                $this->stats['semanticPasses']++;
                $this->stats['processedPages'] += count($pageBuffer);
                $pageBuffer = [];
                $windowProviderParts = [];
                $windowStartPage = $nextPage;
                yield $document;
            }
            unset($rangePages);
        }

        if (!$sawRange || $source === null || $profile === null || $totalPages === null) {
            throw new RuntimeException('PDF semantic reconciliation received no facts ranges.');
        }
        if ($nextPage !== $totalPages + 1) {
            throw new RuntimeException(sprintf(
                'PDF semantic reconciliation stopped at page %d of %d.',
                $nextPage - 1,
                $totalPages
            ));
        }
        if ($pageBuffer !== []) {
            $document = $this->readSemanticWindow(
                $pdfBytes,
                implode('+', array_keys($windowProviderParts)),
                $source,
                $totalPages,
                $windowStartPage,
                $totalPages,
                $pageBuffer,
                $structure,
                $profile,
                $diagnostics,
                $unassignedAnnotations
            );
            $this->stats['semanticPasses']++;
            $this->stats['processedPages'] += count($pageBuffer);
            $pageBuffer = [];
            yield $document;
        }

        $this->stats['pageProvenanceDigest'] = hash_final($pageProvenance);
        $this->stats['loadedWholeDocument'] = $totalPages <= $this->semanticWindowPages;
    }

    /** @return array<string, mixed> */
    public function stats(): array
    {
        return $this->stats;
    }

    /**
     * @param resource|\HashContext $digest
     */
    private function appendVerifiedPageProvenance(mixed $digest, PdfPageFacts $page, string $sourceSha256): void
    {
        $pageNumber = $page->pageNumber();
        $pageObject = $page->pageObject();
        $lineIds = [];
        $seenLineIds = [];
        foreach ($page->text()['lines'] ?? [] as $line) {
            if (!is_array($line)
                || !is_string($line['id'] ?? null)
                || $line['id'] === ''
                || (int) ($line['page'] ?? 0) !== $pageNumber) {
                throw new RuntimeException('PDF semantic reconciliation found a text line without stable page provenance.');
            }
            $provenance = is_array($line['provenance'] ?? null) ? $line['provenance'] : [];
            if ((int) ($provenance['page'] ?? 0) !== $pageNumber
                || ($pageObject !== null
                    && isset($provenance['pageObject'])
                    && (int) $provenance['pageObject'] !== $pageObject)) {
                throw new RuntimeException('PDF semantic reconciliation found contradictory line/page provenance.');
            }
            if (isset($seenLineIds[$line['id']])) {
                throw new RuntimeException('PDF semantic reconciliation found duplicate stable line provenance on one page.');
            }
            $seenLineIds[$line['id']] = true;
            $lineIds[] = $line['id'];
        }

        $payload = json_encode([
            'sourceSha256' => $sourceSha256,
            'pageNumber' => $pageNumber,
            'pageObject' => $pageObject,
            'lineIds' => $lineIds,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        hash_update($digest, is_string($payload) ? $payload : serialize($lineIds));
    }

    /**
     * @param array<string, mixed> $source
     * @param list<PdfPageFacts> $pages
     * @param array<string, mixed> $structure
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $diagnostics
     * @param array<string, list<array<string, mixed>>> $unassignedAnnotations
     */
    private function readSemanticWindow(
        string $pdfBytes,
        string $provider,
        array $source,
        int $totalPages,
        int $startPage,
        int $endPage,
        array $pages,
        array $structure,
        array $profile,
        array $diagnostics,
        array $unassignedAnnotations
    ): AstNode {
        $structure['documentProfile'] = $profile;
        $facts = new PdfDocumentFacts(
            $provider,
            $source,
            [
                'totalPages' => $totalPages,
                'startPage' => $startPage,
                'endPage' => $endPage,
                'pageNumbers' => range($startPage, $endPage),
                'hasMorePages' => $endPage < $totalPages,
                'nextPage' => $endPage < $totalPages ? $endPage + 1 : null,
            ],
            $pages,
            $structure,
            $diagnostics,
            $unassignedAnnotations
        );

        return (new PdfReader(array_replace(
            $this->readerOptions,
            ['pdfDocumentFacts' => $facts]
        )))->read($pdfBytes);
    }

    /**
     * Metadata is keyed by deterministic public IDs before it is combined.
     * This keeps retries or future overlapping semantic reads from duplicating
     * one family while preserving a stable ordered instance inventory.
     *
     * @param array<string,mixed>|null $existing
     * @param array<string,mixed> $incoming
     * @return array<string,mixed>
     */
    private function unionLogicalTableFamily(?array $existing, array $incoming): array
    {
        if ($existing === null) {
            return $incoming;
        }
        $pages = array_values(array_unique(array_map(
            static fn (mixed $page): int => (int) $page,
            [
                ...(is_array($existing['pages'] ?? null) ? $existing['pages'] : []),
                ...(is_array($incoming['pages'] ?? null) ? $incoming['pages'] : []),
            ]
        )));
        sort($pages, SORT_NUMERIC);
        $instancesById = [];
        foreach ([
            ...(is_array($existing['instances'] ?? null) ? $existing['instances'] : []),
            ...(is_array($incoming['instances'] ?? null) ? $incoming['instances'] : []),
        ] as $instance) {
            if (!is_array($instance) || !is_string($instance['id'] ?? null) || $instance['id'] === '') {
                continue;
            }
            $instancesById[$instance['id']] ??= $instance;
        }
        $instances = array_values($instancesById);
        usort($instances, static fn (array $left, array $right): int =>
            ((int) ($left['page'] ?? 0) <=> (int) ($right['page'] ?? 0))
                ?: ((string) ($left['id'] ?? '') <=> (string) ($right['id'] ?? ''))
        );
        $minimumOverlap = min(
            (float) ($existing['minimumOverlap'] ?? 1.0),
            (float) ($incoming['minimumOverlap'] ?? 1.0)
        );

        return array_replace($existing, [
            'pages' => $pages,
            'firstPage' => $pages === [] ? null : $pages[0],
            'lastPage' => $pages === [] ? null : $pages[array_key_last($pages)],
            'instances' => $instances,
            'instanceCount' => count($instances),
            'physicalParts' => array_sum(array_map(
                static fn (array $instance): int => max(0, (int) ($instance['physicalParts'] ?? 0)),
                $instances
            )),
            'minimumOverlap' => $minimumOverlap,
        ]);
    }

    /**
     * @param array<string,mixed>|null $existing
     * @param array<string,mixed> $incoming
     * @return array<string,mixed>
     */
    private function unionTableContinuation(?array $existing, array $incoming): array
    {
        if ($existing === null) {
            return $incoming;
        }
        $pages = array_values(array_unique(array_map(
            static fn (mixed $page): int => (int) $page,
            [
                ...(is_array($existing['pages'] ?? null) ? $existing['pages'] : []),
                ...(is_array($incoming['pages'] ?? null) ? $incoming['pages'] : []),
            ]
        )));
        sort($pages, SORT_NUMERIC);

        return array_replace($existing, [
            'pages' => $pages,
            'physicalParts' => count($pages),
            'firstPage' => $pages === [] ? null : $pages[0],
            'lastPage' => $pages === [] ? null : $pages[array_key_last($pages)],
        ]);
    }

    /**
     * @param list<AstNode> $nodes
     * @return array{physical:int,logical:int}
     */
    private function logicalTableCounts(array $nodes): array
    {
        $physical = 0;
        $standalone = 0;
        $logicalIds = [];
        $visit = function (array $children) use (&$visit, &$physical, &$standalone, &$logicalIds): void {
            foreach ($children as $node) {
                if (!$node instanceof AstNode) {
                    continue;
                }
                if ($node->type === 'table') {
                    $physical++;
                    $familyId = $node->attr('pdfLogicalTableFamilyId');
                    $continuationId = $node->attr('pdfLogicalTableId');
                    if (is_string($familyId) && $familyId !== '') {
                        $logicalIds['family:' . $familyId] = true;
                    } elseif (is_string($continuationId) && $continuationId !== '') {
                        $logicalIds['continuation:' . $continuationId] = true;
                    } else {
                        $standalone++;
                    }
                }
                $visit($node->children());
            }
        };
        $visit($nodes);

        return [
            'physical' => $physical,
            'logical' => count($logicalIds) + $standalone,
        ];
    }
}
