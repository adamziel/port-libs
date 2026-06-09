<?php

declare(strict_types=1);

use PortLibs\Pandoc\YamlMetadataReview;

return [
    'indexes pandoc yaml tagged explicit flow key provenance by metadata path' => static function (TestRunner $t): void {
        $review = YamlMetadataReview::fromMarkdown(implode("\n", [
            '%TAG !wp! tag:wordpress.org,2026:meta/',
            '---',
            'title: Provenance Packet',
            'flow-review: {? !wp!key [source, ticket]: !wp!value queued, status: approved}',
            'references:',
            '  - id: edge-ref',
            '    metadata: {? [source, key]: metadata value, status: kept}',
            '...',
            '',
            '# Body',
        ]));

        $meta = $review['meta'];
        $summary = $review['summary'];
        $byPath = $review['provenanceByPath'];

        $t->same('Provenance Packet', $meta['title']);
        $t->same('queued', $meta['flow-review']['[source, ticket]']);
        $t->same('metadata value', $meta['references'][0]['metadata']['[source, key]']);
        $t->same('clean', $summary['reviewStatus']);
        $t->same(2, $summary['tagCount']);
        $t->same(7, $summary['collectionCount']);
        $t->same([], $review['diagnosticsByPath']);

        $flowEntries = $byPath['/flow-review/[source, ticket]'] ?? [];
        $flowTags = array_values(array_filter(
            $flowEntries,
            static fn (array $entry): bool => ($entry['category'] ?? '') === 'tag'
        ));
        $flowCollections = array_values(array_filter(
            $flowEntries,
            static fn (array $entry): bool => ($entry['type'] ?? '') === 'yaml-explicit-key-collection'
        ));

        $t->same(3, count($flowEntries));
        $t->same([
            'tag:wordpress.org,2026:meta/key',
            'tag:wordpress.org,2026:meta/value',
        ], array_column($flowTags, 'normalizedTag'));
        $t->same('collection', $flowCollections[0]['category'] ?? null);
        $t->same('sequence', $flowCollections[0]['kind'] ?? null);
        $t->same('flow', $flowCollections[0]['style'] ?? null);
        $t->same('!wp!key [source, ticket]', $flowCollections[0]['source'] ?? null);
        $t->same('[source, ticket]', $flowCollections[0]['collectionSource'] ?? null);
        $t->same('4', $flowCollections[0]['sourceLine'] ?? null);

        $nestedEntries = $byPath['/references/0/metadata/[source, key]'] ?? [];
        $t->same(1, count($nestedEntries));
        $t->same('yaml-explicit-key-collection', $nestedEntries[0]['type'] ?? null);
        $t->same('7', $nestedEntries[0]['sourceLine'] ?? null);
    },
];
