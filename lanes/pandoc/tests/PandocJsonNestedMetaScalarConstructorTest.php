<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'preserves nested singleton metadata scalar constructors through json and native stacks' => static function (TestRunner $t): void {
        $titleNative = ['t' => 'MetaString', 'c' => [['Nested title']], 'reviewQueue' => 'title-nested-source'];
        $draftNative = ['t' => 'MetaBool', 'c' => [[true]], 'reviewQueue' => 'draft-nested-source'];
        $statusNative = ['t' => 'MetaString', 'c' => [[['queued']]], 'reviewQueue' => 'status-nested-source'];
        $flagNative = ['t' => 'MetaBool', 'c' => [[false]], 'reviewQueue' => 'flag-nested-source'];
        $flagsNative = ['t' => 'MetaList', 'c' => [$flagNative], 'reviewQueue' => 'flags-source'];
        $reviewNative = ['t' => 'MetaMap', 'c' => [
            'status' => $statusNative,
            'flags' => $flagsNative,
        ], 'reviewQueue' => 'review-source'];
        $sourceMeta = [
            'title' => $titleNative,
            'draft' => $draftNative,
            'review' => $reviewNative,
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => $sourceMeta,
            'blocks' => [],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $meta = $document->attr('meta');
            $nativeValues = $document->attr('metaNativeValues');

            $t->same($titleNative, $nativeValues['title'] ?? null, "{$source} records nested title native payload");
            $t->same($draftNative, $nativeValues['draft'] ?? null, "{$source} records nested bool native payload");
            $t->same($reviewNative, $nativeValues['review'] ?? null, "{$source} records nested map native payload");

            if ($source === 'json') {
                $t->same('Nested title', $meta['title'], "{$source} unwraps nested MetaString payload");
                $t->same(true, $meta['draft'], "{$source} unwraps nested MetaBool payload");
                $t->same('queued', $meta['review']['items']['status'], "{$source} unwraps nested map MetaString payload");
                $t->same(false, $meta['review']['items']['flags']['items'][0], "{$source} unwraps nested list MetaBool payload");
            } else {
                $t->same($titleNative, $meta['title'], "{$source} keeps raw nested MetaString payload");
                $t->same($draftNative, $meta['draft'], "{$source} keeps raw nested MetaBool payload");
                $t->same($reviewNative, $meta['review'], "{$source} keeps raw nested MetaMap payload");
            }

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($document),
                'native' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($sourceMeta, $encoded['meta'], "{$source} {$writer} writer preserves unchanged nested scalar metadata constructors");
            }

            $editedMeta = $meta;
            $editedMeta['title'] = 'Edited title';
            $editedDocument = new AstNode('document', array_replace($document->attrs, ['meta' => $editedMeta]), $document->children);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($editedDocument),
                'native' => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same(['t' => 'MetaString', 'c' => 'Edited title'], $encoded['meta']['title'], "{$source} {$writer} writer regenerates edited nested title");
                $t->same($draftNative, $encoded['meta']['draft'], "{$source} {$writer} writer preserves unchanged nested bool");
                $t->same($reviewNative, $encoded['meta']['review'], "{$source} {$writer} writer preserves unchanged nested map");
            }
        }

        $directDocument = new AstNode('document', ['meta' => $sourceMeta]);
        $t->same($sourceMeta, (new PandocJsonWriter())->toArray($directDocument)['meta'], 'json writer preserves direct nested scalar metadata constructors');
        $t->same($sourceMeta, json_decode((new NativeWriter())->write($directDocument), true, 512, JSON_THROW_ON_ERROR)['meta'], 'native writer preserves direct nested scalar metadata constructors');
    },
];
