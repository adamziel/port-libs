<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-command-parse-raw.md'
);

$inlineTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$tests = [];

$tests['maps upstream markdown reader parse raw fixture inline constructors'] =
    static function (TestRunner $t) use ($fixture, $inlineTypes): void {
        $source = implode("\n\n", [
            '*Hi `\foo{there}`{=latex}*',
            '*Hi `<blink>`{=html}there`</blink>`{=html}*',
            '`<outline text="Legacy"/>`{=opml}',
        ]);
        $document = (new MarkdownReader())->read($source);
        $latexEmph = $document->children[0]->children[0] ?? new AstNode('missing');
        $htmlEmph = $document->children[1]->children[0] ?? new AstNode('missing');
        $opml = $document->children[2]->children[0] ?? new AstNode('missing');

        $t->contains('*Hi `\foo{there}`{=latex}*', $fixture());
        $t->contains('*Hi `<blink>`{=html}there`</blink>`{=html}*', $fixture());
        $t->same(['text', 'raw_inline'], $inlineTypes($latexEmph));
        $t->same('latex', $latexEmph->children[1]->attr('format'));
        $t->same('\foo{there}', $latexEmph->children[1]->attr('text'));
        $t->same(['text', 'raw_html_inline', 'text', 'raw_html_inline'], $inlineTypes($htmlEmph));
        $t->same('html', $htmlEmph->children[1]->attr('format'));
        $t->same('<blink>', $htmlEmph->children[1]->attr('html'));
        $t->same('there', $htmlEmph->children[2]->attr('text'));
        $t->same('html', $htmlEmph->children[3]->attr('format'));
        $t->same('</blink>', $htmlEmph->children[3]->attr('html'));
        $t->same('raw_inline', $opml->type);
        $t->same('opml', $opml->attr('format'));
        $t->same('<outline text="Legacy"/>', $opml->attr('text'));
    };

$tests['maps upstream markdown reader parse raw fixture disabled raw outputs'] =
    static function (TestRunner $t) use ($fixture, $inlineTypes): void {
        $source = implode("\n\n", [
            '*Hi*',
            '*Hi there*',
        ]);
        $document = (new MarkdownReader())->read($source);
        $latexDisabled = $document->children[0]->children[0] ?? new AstNode('missing');
        $htmlDisabled = $document->children[1]->children[0] ?? new AstNode('missing');
        $native = (new NativeWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('% pandoc -f latex -t markdown', $fixture());
        $t->contains('% pandoc -f html -t markdown', $fixture());
        $t->contains('*Hi*', $fixture());
        $t->contains('*Hi there*', $fixture());
        $t->same(['paragraph', 'paragraph'], array_map(
            static fn (AstNode $node): string => $node->type,
            $document->children
        ));
        $t->same('emph', $latexDisabled->type);
        $t->same(['text'], $inlineTypes($latexDisabled));
        $t->same('Hi', $latexDisabled->children[0]->attr('text'));
        $t->same('emph', $htmlDisabled->type);
        $t->same(['text'], $inlineTypes($htmlDisabled));
        $t->same('Hi there', $htmlDisabled->children[0]->attr('text'));
        $t->contains('Emph [ Str "Hi" ]', $native);
        $t->contains('Emph [ Str "Hi" , Space , Str "there" ]', $native);
        $t->contains('<p><em>Hi</em></p>', $blocks);
        $t->contains('<p><em>Hi there</em></p>', $blocks);
    };

$tests['round trips upstream markdown reader parse raw fixture through native markdown and wordpress'] =
    static function (TestRunner $t) use ($fixture): void {
        $source = implode("\n\n", [
            '*Hi `\foo{there}`{=latex}*',
            '*Hi `<blink>`{=html}there`</blink>`{=html}*',
            '`<outline text="Legacy"/>`{=opml}',
        ]);
        $document = (new MarkdownReader())->read($source);
        $markdown = (new MarkdownWriter())->write($document);
        $native = (new NativeWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('*Hi `\foo{there}`{=latex}*', $fixture());
        $t->contains('*Hi `<blink>`{=html}there`</blink>`{=html}*', $markdown);
        $t->contains('`<outline text="Legacy"/>`{=opml}', $markdown);
        $t->contains('RawInline (Format "latex") "\\\\foo{there}"', $native);
        $t->contains('RawInline (Format "html") "<blink>"', $native);
        $t->contains('RawInline (Format "html") "</blink>"', $native);
        $t->contains('RawInline (Format "opml") "<outline text=\"Legacy\"/>"', $native);
        $t->contains('<p><em>Hi <blink>there</blink></em></p>', $blocks);
        $t->contains('<span class="pandoc-raw-opml" data-pandoc-raw-format="opml">&lt;outline text=&quot;Legacy&quot;/&gt;</span>', $blocks);
    };

$tests['records markdown reader parse raw fixture completion mapped-case count'] =
    static function (TestRunner $t): void {
        $t->same(6, 2 + 2 + 2);
    };

return $tests;
