<?php

declare(strict_types=1);

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-bare-uri-raw-html-anchor.md'
);

return [
    'keeps upstream bare URI inside raw html anchor as plain text' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'markdown+autolink_bare_uris+raw_html']))
                ->read($fixture());

            $t->same('paragraph', $document->children[0]->type);
            $t->same(['raw_html_inline', 'text', 'raw_html_inline'], array_map(
                static fn ($inline): string => $inline->type,
                $document->children[0]->children
            ));
            $t->same('<a href="http://foo.bar.baz">', $document->children[0]->children[0]->attr('html'));
            $t->same('http://foo.bar.baz', $document->children[0]->children[1]->attr('text'));
            $t->same('</a>', $document->children[0]->children[2]->attr('html'));
        },

    'serializes upstream bare URI raw html anchor fixture through native handoff' =>
        static function (TestRunner $t) use ($fixture): void {
            $native = (new NativeWriter())->write(
                (new MarkdownReader(['format' => 'markdown+autolink_bare_uris+raw_html']))->read($fixture())
            );

            $t->contains('RawInline (Format "html") "<a href=\\"http://foo.bar.baz\\">"', $native);
            $t->contains('Str "http://foo.bar.baz"', $native);
            $t->contains('RawInline (Format "html") "</a>"', $native);
        },

    'records upstream bare URI raw html anchor fixture literal' =>
        static function (TestRunner $t) use ($fixture): void {
            $t->same('<a href="http://foo.bar.baz">http://foo.bar.baz</a>', trim($fixture()));
        },
];
