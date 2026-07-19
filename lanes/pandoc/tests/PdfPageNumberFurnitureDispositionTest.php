<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PdfReader;
use PortLibs\Pandoc\PdfSourceDispositionLedger;

$paragraph = static fn (string $text): AstNode => new AstNode('paragraph', [], [
    new AstNode('text', ['text' => $text]),
]);

$layout = static fn (
    string $id,
    int $sourceIndex,
    int $page,
    string $text,
    float $x1,
    float $y1,
    float $x2,
    float $y2
): array => [
    'id' => $id,
    'page' => $page,
    'sourceStream' => $page,
    'text' => $text,
    'x1' => $x1,
    'y1' => $y1,
    'x2' => $x2,
    'y2' => $y2,
    'fontSize' => max(1.0, $y2 - $y1),
    'sourcePdfGlobalSourceIndex' => $sourceIndex,
    'sourcePdfExactSourceIndex' => $sourceIndex,
    'sourcePdfExactSourceStart' => 0,
    'sourcePdfExactSourceEnd' => strlen(preg_replace('/\s+/u', '', $text) ?? $text),
];

return [
    'binds only signed exact repeated bottom-slot page-number removals' => static function (
        TestRunner $t
    ) use ($paragraph, $layout): void {
        $reader = new PdfReader();
        $setSourceSha256 = (function (string $sha256): void {
            $this->sourceSha256 = $sha256;
        })->bindTo($reader, PdfReader::class);
        $removeFurniture = (function (array $records): array {
            return $this->removeRepeatedPdfPageNumberRecords($records);
        })->bindTo($reader, PdfReader::class);
        $proofs = (function (): array {
            return $this->removedPageNumberFurnitureProofsBySourceId;
        })->bindTo($reader, PdfReader::class);
        $explicitDispositions = (function (array $items, array $blocks): array {
            return $this->explicitPdfSourceDispositions($items, $blocks);
        })->bindTo($reader, PdfReader::class);
        $proofMatches = (function (array $proof, string $id, array $items): bool {
            return $this->pdfRepeatedPageNumberFurnitureProofMatchesSourceItems(
                $proof,
                $id,
                $items
            );
        })->bindTo($reader, PdfReader::class);
        $t->true($setSourceSha256 instanceof Closure);
        $t->true($removeFurniture instanceof Closure);
        $t->true($proofs instanceof Closure);
        $t->true($explicitDispositions instanceof Closure);
        $t->true($proofMatches instanceof Closure);
        $setSourceSha256(str_repeat('9', 64));

        $records = [
            ['text' => 'Page one body.', 'layout' => $layout(
                'body-one',
                0,
                1,
                'Page one body.',
                72.0,
                650.0,
                300.0,
                664.0
            )],
            ['text' => '1', 'layout' => $layout('folio-one', 1, 1, '1', 300.0, 20.0, 308.0, 30.0)],
            ['text' => 'Page two body.', 'layout' => $layout(
                'body-two',
                2,
                2,
                'Page two body.',
                72.0,
                650.0,
                300.0,
                664.0
            )],
            ['text' => '2', 'layout' => $layout('folio-two', 3, 2, '2', 300.0, 20.0, 308.0, 30.0)],
        ];
        $kept = $removeFurniture($records);
        $t->same(['Page one body.', 'Page two body.'], array_column($kept, 'text'));
        $receipts = $proofs();
        $t->same(['folio-one', 'folio-two'], array_keys($receipts));

        $source = [];
        foreach ($records as $record) {
            $recordLayout = $record['layout'];
            $source[] = [
                'id' => $recordLayout['id'],
                'page' => $recordLayout['page'],
                'stream' => $recordLayout['sourceStream'],
                'text' => $record['text'],
                'sourceGeometry' => [
                    'page' => $recordLayout['page'],
                    'stream' => $recordLayout['sourceStream'],
                    'x1' => $recordLayout['x1'],
                    'y1' => $recordLayout['y1'],
                    'x2' => $recordLayout['x2'],
                    'y2' => $recordLayout['y2'],
                    'orientation' => 'horizontal',
                ],
            ];
        }
        foreach ($receipts as $id => $proof) {
            $t->true($proofMatches($proof, $id, $source), $id);
        }

        $blocks = [$paragraph('Page one body.'), $paragraph('Page two body.')];
        $dispositions = $explicitDispositions($source, $blocks);
        $t->same('running-furniture', $dispositions['folio-one']['disposition'] ?? null);
        $t->same('running-furniture', $dispositions['folio-two']['disposition'] ?? null);
        $binding = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
            $source,
            $blocks,
            $dispositions
        );
        $t->same(true, $binding['complete']);
        $t->same(null, $binding['failureReason']);

        $tamperedDigest = $receipts['folio-one'];
        $tamperedDigest['proofDigest'] = str_repeat('0', 64);
        $t->same(false, $proofMatches($tamperedDigest, 'folio-one', $source));
        $tamperedPeer = $receipts['folio-one'];
        $tamperedPeer['peers'][1]['bounds']['x1'] += 1.0;
        $payload = $tamperedPeer;
        unset($payload['proofDigest']);
        $tamperedPeer['proofDigest'] = hash('sha256', json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        ) ?: '');
        $t->same(false, $proofMatches($tamperedPeer, 'folio-one', $source));

        $singlePageReader = new PdfReader();
        $singleRemove = (function (array $records): array {
            return $this->removeRepeatedPdfPageNumberRecords($records);
        })->bindTo($singlePageReader, PdfReader::class);
        $singleProofs = (function (): array {
            return $this->removedPageNumberFurnitureProofsBySourceId;
        })->bindTo($singlePageReader, PdfReader::class);
        $t->same(array_column(array_slice($records, 0, 2), 'text'), array_column(
            $singleRemove(array_slice($records, 0, 2)),
            'text'
        ));
        $t->same([], $singleProofs());

        $ambiguous = $records;
        array_splice($ambiguous, 2, 0, [[
            'text' => '9',
            'layout' => $layout('folio-one-duplicate', 4, 1, '9', 304.0, 20.0, 312.0, 30.0),
        ]]);
        $ambiguousReader = new PdfReader();
        $ambiguousRemove = (function (array $records): array {
            return $this->removeRepeatedPdfPageNumberRecords($records);
        })->bindTo($ambiguousReader, PdfReader::class);
        $ambiguousProofs = (function (): array {
            return $this->removedPageNumberFurnitureProofsBySourceId;
        })->bindTo($ambiguousReader, PdfReader::class);
        $t->same(array_column($ambiguous, 'text'), array_column($ambiguousRemove($ambiguous), 'text'));
        $t->same([], $ambiguousProofs());
    },
];
