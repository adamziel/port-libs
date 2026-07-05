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
    'indexes pandoc yaml alias provenance by metadata path' => static function (TestRunner $t): void {
        $review = YamlMetadataReview::fromMarkdown(implode("\n", [
            '---',
            'title: Alias Review Packet',
            'defaults_: &defaults {status: queued, labels: [migration, review]}',
            'source-uri_: &source_uri https://example.test/export#alias-review',
            'review:',
            '  <<: *defaults',
            '  source-uri: *source_uri',
            'aliases:',
            '  defaults-copy: *defaults',
            '...',
            '',
            '# Body',
        ]));

        $meta = $review['meta'];
        $summary = $review['summary'];
        $byPath = $review['provenanceByPath'];

        $t->same('Alias Review Packet', $meta['title']);
        $t->same('queued', $meta['review']['status']);
        $t->same(['migration', 'review'], $meta['review']['labels']);
        $t->same('https://example.test/export#alias-review', $meta['review']['source-uri']);
        $t->same(['status' => 'queued', 'labels' => ['migration', 'review']], $meta['aliases']['defaults-copy']);
        $t->same('clean', $summary['reviewStatus']);
        $t->same(3, $summary['aliasCount']);
        $t->same([], $review['diagnosticsByPath']);

        foreach ([
            '/review/<<' => '*defaults',
            '/review/source-uri' => '*source_uri',
            '/aliases/defaults-copy' => '*defaults',
        ] as $path => $alias) {
            $entries = array_values(array_filter(
                $byPath[$path] ?? [],
                static fn (array $entry): bool => ($entry['category'] ?? '') === 'alias'
            ));
            $t->same(1, count($entries), 'expected one YAML alias provenance entry at ' . $path);
            $t->same($alias, $entries[0]['alias'] ?? null);
            $t->same('true', $entries[0]['resolved'] ?? null);
        }
    },
    'records pandoc yaml metadata native constructors by path' => static function (TestRunner $t): void {
        $review = YamlMetadataReview::fromMarkdown(implode("\n", [
            '---',
            'foobar_: this should be ignored',
            'foo:',
            '  bar_: as should this',
            'int: 7',
            'float: 1.5',
            'scientific: 3.7e-5',
            'bool: true',
            'more: False',
            'nothing: null',
            'empty: []',
            'nested:',
            '  int: 8',
            '  empty: []',
            'array:',
            '  - foo: bar',
            '  - bool: True',
            '...',
        ]));

        $meta = $review['meta'];
        $constructors = $review['constructorProvenance'];

        $t->same([], $meta['foo'] ?? null);
        $t->same('7', $meta['int'] ?? null);
        $t->same('1.5', $meta['float'] ?? null);
        $t->same('3.7e-5', $meta['scientific'] ?? null);
        $t->same(true, $meta['bool'] ?? null);
        $t->same(false, $meta['more'] ?? null);
        $t->same('', $meta['nothing'] ?? null);
        $t->same([], $meta['empty'] ?? null);
        $t->same('8', $meta['nested']['int'] ?? null);
        $t->same('bar', $meta['array'][0]['foo'] ?? null);
        $t->same(true, $meta['array'][1]['bool'] ?? null);

        $t->same('MetaMap', $constructors['/foo']['native']['t'] ?? null);
        $t->same('MetaInlines', $constructors['/int']['native']['t'] ?? null);
        $t->same('Str', $constructors['/int']['native']['c'][0]['t'] ?? null);
        $t->same('7', $constructors['/int']['native']['c'][0]['c'] ?? null);
        $t->same('MetaBool', $constructors['/bool']['native']['t'] ?? null);
        $t->same(true, $constructors['/bool']['native']['c'] ?? null);
        $t->same('MetaBool', $constructors['/more']['native']['t'] ?? null);
        $t->same(false, $constructors['/more']['native']['c'] ?? null);
        $t->same('MetaString', $constructors['/nothing']['native']['t'] ?? null);
        $t->same('', $constructors['/nothing']['native']['c'] ?? null);
        $t->same('MetaList', $constructors['/empty']['native']['t'] ?? null);
        $t->same('MetaMap', $constructors['/nested']['native']['t'] ?? null);
        $t->same('MetaList', $constructors['/nested/empty']['native']['t'] ?? null);
        $t->same('MetaList', $constructors['/array']['native']['t'] ?? null);
        $t->same('MetaMap', $constructors['/array/0']['native']['t'] ?? null);
        $t->same('MetaInlines', $constructors['/array/0/foo']['native']['t'] ?? null);
        $t->same('MetaBool', $constructors['/array/1/bool']['native']['t'] ?? null);
    },
];
