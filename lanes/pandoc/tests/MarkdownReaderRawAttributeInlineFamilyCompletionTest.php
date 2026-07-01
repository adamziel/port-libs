<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$firstInlineOfType = static function (AstNode $node, string $type) use (&$firstInlineOfType): AstNode {
    if ($node->type === $type) {
        return $node;
    }

    foreach ($node->children as $child) {
        $match = $firstInlineOfType($child, $type);
        if ($match->type === $type) {
            return $match;
        }
    }

    return new AstNode('missing');
};

return [
    'maps upstream parse-raw html inline attributes into raw html inline nodes' =>
        static function (TestRunner $t) use ($firstInlineOfType): void {
            $document = (new MarkdownReader())->read('*Hi `<blink>`{=html}there`</blink>`{=html5} and `<span data-x="1">x</span>`{=xhtml-native_spans}*');
            $emph = $document->children[0]->children[0] ?? new AstNode('missing');
            $htmlNodes = array_values(array_filter(
                $emph->children,
                static fn (AstNode $node): bool => $node->type === 'raw_html_inline'
            ));
            $markdown = (new MarkdownWriter())->write($document);
            $native = (new NativeWriter())->write($document);
            $wordpress = (new WordPressBlockWriter())->write($document);
            $jsonRoundTrip = (new PandocJsonReader())->readPacket((new PandocJsonWriter())->toArray($document));
            $roundTripRaw = $firstInlineOfType($jsonRoundTrip, 'raw_html_inline');

            $t->same('emph', $emph->type);
            $t->same(['text', 'raw_html_inline', 'text', 'raw_html_inline', 'text', 'raw_html_inline'], array_map(static fn (AstNode $node): string => $node->type, $emph->children));
            $t->same('html', $htmlNodes[0]->attr('format'));
            $t->same('<blink>', $htmlNodes[0]->attr('html'));
            $t->same('<blink>', $htmlNodes[0]->attr('text'));
            $t->same('html5', $htmlNodes[1]->attr('format'));
            $t->same('</blink>', $htmlNodes[1]->attr('html'));
            $t->same('xhtml-native_spans', $htmlNodes[2]->attr('format'));
            $t->same('<span data-x="1">x</span>', $htmlNodes[2]->attr('html'));
            $t->contains('`<blink>`{=html}', $markdown);
            $t->contains('`</blink>`{=html5}', $markdown);
            $t->contains('`<span data-x="1">x</span>`{=xhtml-native_spans}', $markdown);
            $t->contains('RawInline (Format "html") "<blink>"', $native);
            $t->contains('RawInline (Format "html5") "</blink>"', $native);
            $t->contains('<p><em>Hi <blink>there</blink> and <span data-x="1">x</span></em></p>', $wordpress);
            $t->same('raw_html_inline', $roundTripRaw->type);
            $t->same('html', $roundTripRaw->attr('format'));
            $t->same('<blink>', $roundTripRaw->attr('html'));
        },

    'maps upstream raw attribute tex and markdown inline families into typed raw inline nodes' =>
        static function (TestRunner $t) use ($firstInlineOfType): void {
            $document = (new MarkdownReader())->read(implode("\n\n", [
                'Tex `\textbf{raw}`{=latex+raw_tex} and `\startsection raw`{=context-smart}.',
                'Markdown `**raw**`{=markdown_github} and `> raw`{=commonmark_x+pipe_tables}.',
            ]));
            $texParagraph = $document->children[0] ?? new AstNode('missing');
            $markdownParagraph = $document->children[1] ?? new AstNode('missing');
            $texNodes = array_values(array_filter(
                $texParagraph->children,
                static fn (AstNode $node): bool => $node->type === 'raw_tex_inline'
            ));
            $markdownNodes = array_values(array_filter(
                $markdownParagraph->children,
                static fn (AstNode $node): bool => $node->type === 'raw_markdown'
            ));
            $nativeRoundTrip = (new NativeReader())->read((new NativeWriter())->write($document));
            $roundTripTex = $firstInlineOfType($nativeRoundTrip, 'raw_tex_inline');
            $roundTripMarkdown = $firstInlineOfType($nativeRoundTrip, 'raw_markdown');
            $wordpress = (new WordPressBlockWriter())->write($document);

            $t->same(['text', 'raw_tex_inline', 'text', 'raw_tex_inline', 'text'], array_map(static fn (AstNode $node): string => $node->type, $texParagraph->children));
            $t->same('latex+raw_tex', $texNodes[0]->attr('format'));
            $t->same('\textbf{raw}', $texNodes[0]->attr('tex'));
            $t->same('\textbf{raw}', $texNodes[0]->attr('text'));
            $t->same('context-smart', $texNodes[1]->attr('format'));
            $t->same('\startsection raw', $texNodes[1]->attr('tex'));
            $t->same(['text', 'raw_markdown', 'text', 'raw_markdown', 'text'], array_map(static fn (AstNode $node): string => $node->type, $markdownParagraph->children));
            $t->same('markdown_github', $markdownNodes[0]->attr('format'));
            $t->same('**raw**', $markdownNodes[0]->attr('markdown'));
            $t->same('commonmark_x+pipe_tables', $markdownNodes[1]->attr('format'));
            $t->same('> raw', $markdownNodes[1]->attr('markdown'));
            $t->same('raw_tex_inline', $roundTripTex->type);
            $t->same('latex+raw_tex', $roundTripTex->attr('format'));
            $t->same('raw_markdown', $roundTripMarkdown->type);
            $t->same('markdown_github', $roundTripMarkdown->attr('format'));
            $t->contains('<span class="pandoc-raw-tex">\textbf{raw}</span>', $wordpress);
            $t->contains('Markdown **raw** and &gt; raw.', $wordpress);
        },

    'keeps non pandoc raw attribute inline formats as generic raw inline nodes' =>
        static function (TestRunner $t): void {
            $document = (new MarkdownReader())->read('Legacy `<outline text="Post"/>`{=opml} payload.');
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $raw = $paragraph->children[1] ?? new AstNode('missing');
            $markdown = (new MarkdownWriter())->write($document);
            $wordpress = (new WordPressBlockWriter())->write($document);

            $t->same(['text', 'raw_inline', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
            $t->same('opml', $raw->attr('format'));
            $t->same('<outline text="Post"/>', $raw->attr('text'));
            $t->contains('`<outline text="Post"/>`{=opml}', $markdown);
            $t->contains('<span class="pandoc-raw-opml" data-pandoc-raw-format="opml">&lt;outline text=&quot;Post&quot;/&gt;</span>', $wordpress);
        },

    'records upstream raw attribute inline family completion mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(6, 3 + 2 + 1);
        },
];
