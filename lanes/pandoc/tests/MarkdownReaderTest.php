<?php

declare(strict_types=1);

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'reads markdown blocks into a small shared ast' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("# Title\n\nA paragraph over\nmultiple lines.\n\n- One\n- Two");
        $t->same('document', $document->type);
        $t->same('heading', $document->children[0]->type);
        $t->same('paragraph', $document->children[1]->type);
        $t->same('bullet_list', $document->children[2]->type);
        $t->same('list_item', $document->children[2]->children[0]->type);
    },
    'maps pandoc markdown inline mark semantics into ast nodes' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read('A *migrated* **post** with [`source`](https://example.test/source) and `code`.');
        $paragraph = $document->children[0];

        $t->same('paragraph', $paragraph->type);
        $t->same('emph', $paragraph->children[1]->type);
        $t->same('migrated', $paragraph->children[1]->children[0]->attr('text'));
        $t->same('strong', $paragraph->children[3]->type);
        $t->same('post', $paragraph->children[3]->children[0]->attr('text'));
        $t->same('link', $paragraph->children[5]->type);
        $t->same('https://example.test/source', $paragraph->children[5]->attr('url'));
        $t->same('code', $paragraph->children[7]->type);
    },
    'maps upstream inline code containing list marker text' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("1. `#. x`\n2. `x``#. x`\n- `- x`\n- `x``- x`");
        $ordered = $document->children[0];
        $bullet = $document->children[1];

        $t->same('ordered_list', $ordered->type);
        $t->same('#. x', $ordered->children[0]->children[0]->attr('text'));
        $t->same('x``#. x', $ordered->children[1]->children[0]->attr('text'));
        $t->same('bullet_list', $bullet->type);
        $t->same('- x', $bullet->children[0]->children[0]->attr('text'));
        $t->same('x``- x', $bullet->children[1]->children[0]->attr('text'));
    },
    'maps upstream indented backtick fenced code command example' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("  ```haskell\n  let x = y\nin y\n   ```");
        $code = $document->children[0];

        $t->same('code_block', $code->type);
        $t->same(['haskell'], $code->attr('classes'));
        $t->same("let x = y\nin y", $code->attr('text'));
    },
    'maps upstream indented tilde fenced code attributes example' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(" ~~~ {.haskell}\n  let x = y\n in y +\ny +\n y\n~~~");
        $code = $document->children[0];

        $t->same('code_block', $code->type);
        $t->same(['haskell'], $code->attr('classes'));
        $t->same(" let x = y\nin y +\ny +\ny", $code->attr('text'));
    },
    'groups ordered lists as a list block' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("3. Export WXR\n4. Convert Markdown\n5. Publish blocks");
        $list = $document->children[0];

        $t->same('ordered_list', $list->type);
        $t->same(3, $list->attr('start'));
        $t->same('Convert Markdown', $list->children[1]->attr('text'));
    },
    'maps upstream markdown nested list item shape' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("* a\n* b\n* c\n    * d");
        $list = $document->children[0];
        $nested = $list->children[2]->children[1];

        $t->same('bullet_list', $list->type);
        $t->same('c', $list->children[2]->children[0]->attr('text'));
        $t->same('bullet_list', $nested->type);
        $t->same('d', $nested->children[0]->children[0]->attr('text'));
    },
    'maps upstream markdown definition lists without blank space' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("foo1\n  :  bar\n\nfoo2\n  : bar2\n  : bar3\n");
        $list = $document->children[0];

        $t->same('definition_list', $list->type);
        $t->same('foo1', $list->children[0]->attr('term'));
        $t->same('bar', $list->children[0]->children[1]->children[0]->attr('text'));
        $t->same('foo2', $list->children[1]->attr('term'));
        $t->same('bar2', $list->children[1]->children[1]->children[0]->attr('text'));
        $t->same('bar3', $list->children[1]->children[2]->children[0]->attr('text'));
    },
    'maps upstream markdown definition marker at column zero' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("foo\n: bar\n");
        $list = $document->children[0];

        $t->same('definition_list', $list->type);
        $t->same('foo', $list->children[0]->children[0]->attr('text'));
        $t->same('bar', $list->children[0]->children[1]->children[0]->attr('text'));
    },
    'maps upstream markdown loose first definition paragraph' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("foo1\n\n  :  bar\n\nfoo2\n\n  : bar2\n  : bar3\n");
        $firstDefinition = $document->children[0]->children[0]->children[1];
        $secondItem = $document->children[0]->children[1];

        $t->same('definition', $firstDefinition->type);
        $t->true((bool) $firstDefinition->attr('loose'));
        $t->same('bar', $firstDefinition->children[0]->attr('text'));
        $t->same(false, (bool) $secondItem->children[1]->attr('loose'));
        $t->same('bar2', $secondItem->children[1]->children[0]->attr('text'));
        $t->true((bool) $secondItem->children[2]->attr('loose'));
        $t->same('bar3', $secondItem->children[2]->children[0]->attr('text'));
    },
    'maps upstream markdown lazy definition continuations' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("foo1\n  :  bar\nbaz\n  : bar2\n");
        $item = $document->children[0]->children[0];

        $t->same('definition_item', $item->type);
        $t->same(3, count($item->children));
        $t->same('bar baz', $item->children[1]->children[0]->attr('text'));
        $t->same('bar2', $item->children[2]->children[0]->attr('text'));
    },
    'maps upstream markdown paragraph continuation inside definition' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("foo1\n  : bar\n\n    baz\n");
        $definition = $document->children[0]->children[0]->children[1];

        $t->same('definition', $definition->type);
        $t->same(2, count($definition->children));
        $t->same('bar', $definition->children[0]->attr('text'));
        $t->same('baz', $definition->children[1]->attr('text'));
    },
    'maps upstream markdown blank before second definition' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("foo1\n  :  bar\n\nfoo2\n  : bar2\n\n  : bar3\n");
        $secondItem = $document->children[0]->children[1];

        $t->same('foo2', $secondItem->attr('term'));
        $t->same(3, count($secondItem->children));
        $t->same('bar2', $secondItem->children[1]->children[0]->attr('text'));
        $t->same('bar3', $secondItem->children[2]->children[0]->attr('text'));
    },
    'maps upstream markdown list inside definition' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("foo\n:   - bar\n");
        $definition = $document->children[0]->children[0]->children[1];

        $t->same('definition', $definition->type);
        $t->same('bullet_list', $definition->children[0]->type);
        $t->same('bar', $definition->children[0]->children[0]->children[0]->attr('text'));
    },
    'maps upstream testsuite simple block quote paragraphs' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("> This is a block quote.\n> It is pretty short.");
        $quote = $document->children[0];

        $t->same('blockquote', $quote->type);
        $t->same('paragraph', $quote->children[0]->type);
        $t->same('This is a block quote. It is pretty short.', $quote->children[0]->attr('text'));
    },
    'maps upstream testsuite block quote with code list and nested quotes' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("> Code in a block quote:\n> \n>     sub status {\n>         print \"working\";\n>     }\n> \n> A list:\n> \n> 1. item one\n> 2. item two\n>\n> Nested block quotes:\n>\n> > nested\n>\n>>  nested\n>");
        $quote = $document->children[0];

        $t->same('blockquote', $quote->type);
        $t->same('paragraph', $quote->children[0]->type);
        $t->same('code_block', $quote->children[1]->type);
        $t->same("sub status {\n    print \"working\";\n}", $quote->children[1]->attr('text'));
        $t->same('ordered_list', $quote->children[3]->type);
        $t->same('item two', $quote->children[3]->children[1]->attr('text'));
        $t->same('blockquote', $quote->children[5]->type);
        $t->same('nested', $quote->children[5]->children[0]->attr('text'));
        $t->same('blockquote', $quote->children[6]->type);
        $t->same('nested', $quote->children[6]->children[0]->attr('text'));
    },
    'keeps upstream testsuite lazy quote marker inside paragraph' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("This should not be a block quote: 2\n> 1.");
        $paragraph = $document->children[0];

        $t->same(1, count($document->children));
        $t->same('paragraph', $paragraph->type);
        $t->same('This should not be a block quote: 2 > 1.', $paragraph->attr('text'));
    },
    'writes wordpress block output from ast' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("# Title\n\nParagraph with **strong** text and [source](https://example.test).\n\n- One\n- Two\n\n3. First\n4. Second");
        $blocks = (new WordPressBlockWriter())->write($document);
        $t->contains('<!-- wp:heading {"level":1} -->', $blocks);
        $t->contains('<p>Paragraph with <strong>strong</strong> text and <a href="https://example.test">source</a>.</p>', $blocks);
        $t->contains('<!-- wp:list -->', $blocks);
        $t->contains('<ul><li>One</li><li>Two</li></ul>', $blocks);
        $t->contains('<!-- wp:list {"ordered":true,"start":3} -->', $blocks);
        $t->contains('<ol start="3"><li>First</li><li>Second</li></ol>', $blocks);
    },
    'writes nested wordpress list markup from upstream-shaped ast' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("* a\n* b\n* c\n    * d");
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<ul><li>a</li><li>b</li><li>c<ul><li>d</li></ul></li></ul>', $blocks);
    },
    'writes wordpress definition list html from upstream-shaped ast' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("Plugin\n: Stable release\n\nChecklist\n:   - Verify imports");
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<!-- wp:html -->', $blocks);
        $t->contains('<dl><dt>Plugin</dt><dd>Stable release</dd><dt>Checklist</dt><dd><ul><li>Verify imports</li></ul></dd></dl>', $blocks);
    },
    'writes wordpress definition paragraphs from import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<dt>Import note</dt><dd>Keep the archive URL attached and mention reviewer follow-up.</dd>', $blocks);
        $t->contains('<dt>Cleanup pass</dt><dd><p>Check legacy shortcodes after block conversion.</p><p>Record manual remediation notes.</p></dd>', $blocks);
    },
    'writes wordpress code block markup for migration snippets' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<!-- wp:code -->', $blocks);
        $t->contains('<pre class="wp-block-code"><code class="language-php">do_shortcode(&#039;[legacy-gallery]&#039;);</code></pre>', $blocks);
    },
    'writes wordpress quote block markup for migration reviewer notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<!-- wp:quote -->', $blocks);
        $t->contains('<blockquote class="wp-block-quote"><p>Reviewer note: keep the archive URL attached to the imported post.</p></blockquote>', $blocks);
    },
    'escapes wordpress block inline html while preserving marks' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read('Use **<unsafe>** and `x < y`.');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<strong>&lt;unsafe&gt;</strong>', $blocks);
        $t->contains('<code>x &lt; y</code>', $blocks);
    },
];
