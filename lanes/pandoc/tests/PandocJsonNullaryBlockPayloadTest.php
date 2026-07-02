<?php

declare(strict_types=1);

use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'regenerates stale and empty nullary block payload members from json and native readers' => static function (TestRunner $t): void {
        $topRule = ['t' => 'HorizontalRule', 'c' => ['stale-rule'], 'reviewQueue' => 'top-rule-source'];
        $topNull = ['t' => 'Null', 'c' => [], 'reviewQueue' => 'top-null-source'];
        $quoteRule = ['t' => 'HorizontalRule', 'c' => [], 'reviewQueue' => 'quote-rule-source'];
        $quoteNull = ['t' => 'Null', 'c' => ['stale-null'], 'reviewQueue' => 'quote-null-source'];
        $metaRule = ['t' => 'HorizontalRule', 'c' => ['stale-meta-rule'], 'reviewQueue' => 'meta-rule-source'];
        $metaNull = ['t' => 'Null', 'c' => [], 'reviewQueue' => 'meta-null-source'];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [
                'reviewBlocks' => ['t' => 'MetaBlocks', 'c' => [
                    $metaRule,
                    $metaNull,
                ], 'reviewQueue' => 'meta-blocks-source'],
            ],
            'blocks' => [
                $topRule,
                $topNull,
                ['t' => 'BlockQuote', 'c' => [
                    $quoteRule,
                    $quoteNull,
                ], 'reviewQueue' => 'quote-wrapper-source'],
            ],
        ];
        $expectedBlocks = [
            ['t' => 'HorizontalRule'],
            ['t' => 'Null'],
            ['t' => 'BlockQuote', 'c' => [
                ['t' => 'HorizontalRule'],
                ['t' => 'Null'],
            ]],
        ];
        $expectedMetaBlocks = [
            ['t' => 'HorizontalRule'],
            ['t' => 'Null'],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $quote = $document->children[2];
            $provenance = $document->attr('metaConstructorProvenance');

            $t->same(['horizontal_rule', 'null_block', 'blockquote'], array_map(static fn ($node): string => $node->type, $document->children), "{$source} nullary block node types");
            $t->same($topRule, $document->children[0]->attr('native'), "{$source} keeps stale top rule provenance");
            $t->same($topNull, $document->children[1]->attr('native'), "{$source} keeps empty top null provenance");
            $t->same($quoteRule, $quote->children[0]->attr('native'), "{$source} keeps empty nested rule provenance");
            $t->same($quoteNull, $quote->children[1]->attr('native'), "{$source} keeps stale nested null provenance");
            $t->same($packet['meta']['reviewBlocks'], $provenance['/reviewBlocks']['native'] ?? null, "{$source} keeps metadata source provenance");

            foreach ([
                'json writer' => (new PandocJsonWriter())->toArray($document),
                'native writer' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($expectedBlocks, $encoded['blocks'], "{$source} {$writer} regenerates nullary blocks");
                $t->same('MetaBlocks', $encoded['meta']['reviewBlocks']['t'] ?? null, "{$source} {$writer} keeps metadata constructor");
                $t->same($expectedMetaBlocks, $encoded['meta']['reviewBlocks']['c'] ?? null, "{$source} {$writer} regenerates metadata nullary blocks");
                $t->same(false, array_key_exists('reviewQueue', $encoded['meta']['reviewBlocks'] ?? []), "{$source} {$writer} drops stale metadata wrapper sidecar");
            }
        }
    },
];
