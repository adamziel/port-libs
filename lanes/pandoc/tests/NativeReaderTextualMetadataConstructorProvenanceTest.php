<?php

declare(strict_types=1);

use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'preserves textual native metadata constructor payloads for pandoc json writer' => static function (TestRunner $t): void {
        $native = <<<'NATIVE'
Pandoc Meta {unMeta = fromList [("title", MetaInlines [Str "Native", Space, Emph [Str "metadata"]]), ("review", MetaMap (fromList [("queue", MetaString "native-text"), ("approved", MetaBool True)])), ("flags", MetaList [MetaString "json-native", MetaBool False]), ("body", MetaBlocks [Para [Str "nested"]])]} [Para [Str "Body"]]
NATIVE;
        $expectedTitle = ['t' => 'MetaInlines', 'c' => [
            ['t' => 'Str', 'c' => 'Native'],
            ['t' => 'Space'],
            ['t' => 'Emph', 'c' => [
                ['t' => 'Str', 'c' => 'metadata'],
            ]],
        ]];
        $expectedReview = ['t' => 'MetaMap', 'c' => [
            'queue' => ['t' => 'MetaString', 'c' => 'native-text'],
            'approved' => ['t' => 'MetaBool', 'c' => true],
        ]];
        $expectedFlags = ['t' => 'MetaList', 'c' => [
            ['t' => 'MetaString', 'c' => 'json-native'],
            ['t' => 'MetaBool', 'c' => false],
        ]];
        $expectedBody = ['t' => 'MetaBlocks', 'c' => [
            ['t' => 'Para', 'c' => [
                ['t' => 'Str', 'c' => 'nested'],
            ]],
        ]];

        $document = (new NativeReader())->read($native);
        $encoded = (new PandocJsonWriter())->toArray($document);
        $roundTrip = (new NativeReader())->read(json_encode($encoded, JSON_THROW_ON_ERROR));
        $meta = $document->attr('meta');
        $nativeValues = $document->attr('metaNativeValues');
        $provenance = $document->attr('metaConstructorProvenance');

        $t->same('pandoc-native-text', $document->attr('nativeFormat'));
        $t->same('Native metadata', $meta['title']);
        $t->same('Native', $meta['titleInlines'][0]->attr('text'));
        $t->same(' ', $meta['titleInlines'][1]->attr('text'));
        $t->same('emph', $meta['titleInlines'][2]->type);
        $t->same('metadata', $meta['titleInlines'][2]->children[0]->attr('text'));
        $t->same(['queue' => 'native-text', 'approved' => true], $meta['review']);
        $t->same(['json-native', false], $meta['flags']);
        $t->same('paragraph', $meta['body'][0]->type);
        $t->same($expectedTitle, $nativeValues['title']);
        $t->same($expectedReview, $nativeValues['review']);
        $t->same($expectedFlags, $nativeValues['flags']);
        $t->same($expectedBody, $nativeValues['body']);
        $t->same('MetaMap', $provenance['/review']['constructor']);
        $t->same($expectedReview['c']['queue'], $provenance['/review/queue']['native']);
        $t->same('MetaList', $provenance['/flags']['constructor']);
        $t->same($expectedFlags['c'][1], $provenance['/flags/1']['native']);
        $t->same($expectedTitle, $encoded['meta']['title']);
        $t->same($expectedReview, $encoded['meta']['review']);
        $t->same($expectedFlags, $encoded['meta']['flags']);
        $t->same($expectedBody, $encoded['meta']['body']);
        $t->same($expectedReview, $roundTrip->attr('meta')['review']);
        $t->same($expectedBody, $roundTrip->attr('meta')['body']);
    },
];
