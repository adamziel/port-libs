<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\DokuWikiReader;
use PortLibs\Pandoc\PandocConverter;

$read = static fn (string $source): AstNode => (new DokuWikiReader())->read($source);

$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if (in_array($node->type, ['space', 'softbreak', 'linebreak'], true)) {
        return ' ';
    }

    return implode('', array_map($plainText, $node->children));
};

$types = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

return [
    'maps core dokuwiki blocks and inline styles' => static function (TestRunner $t) use ($read, $plainText, $types): void {
        $document = $read(<<<'DOKU'
====== Title ======

This is **bold** and //emph// and __under__ and ''code''.

  * first
  * second with [[https://example.com|Example]]
  - one
  - two

^ Name ^ Value ^
| A | B |
| C | D |

<code javascript>
console.log("x")
</code>
DOKU);

        $paragraph = $document->children[1];
        $bullet = $document->children[2];
        $ordered = $document->children[3];
        $table = $document->children[4];
        $code = $document->children[5];

        $t->same('dokuwiki', $document->attr('sourceFormat'));
        $t->same(['heading', 'paragraph', 'bullet_list', 'ordered_list', 'table', 'code_block'], $types($document));
        $t->same('Title', $plainText($document->children[0]));
        $t->same(['text', 'strong', 'text', 'emph', 'text', 'underline', 'text', 'code', 'text'], $types($paragraph));
        $t->same('This is bold and emph and under and code.', $plainText($paragraph));

        $t->same('first', $plainText($bullet->children[0]));
        $t->same('second with Example', $plainText($bullet->children[1]));
        $t->same('link', $bullet->children[1]->children[0]->children[1]->type);
        $t->same('https://example.com', $bullet->children[1]->children[0]->children[1]->attr('url'));
        $t->same('one', $plainText($ordered->children[0]));
        $t->same('two', $plainText($ordered->children[1]));
        $t->same('default', $ordered->attr('style'));

        $t->same('Name', $plainText($table->children[0]->children[0]->children[0]));
        $t->same('Value', $plainText($table->children[0]->children[0]->children[1]));
        $t->same('A', $plainText($table->children[1]->children[0]->children[0]));
        $t->same('D', $plainText($table->children[1]->children[1]->children[1]));

        $t->same('code_block', $code->type);
        $t->same(["javascript"], $code->attr('classes'));
        $t->same("console.log(\"x\")\n", $code->attr('text'));
    },

    'reads dokuwiki through converter and writes native html outputs' => static function (TestRunner $t): void {
        $source = <<<'DOKU'
====== Media ======

Image {{https://example.com/a.png?200x100|Alt}} and [[https://example.com]].
DOKU;

        $document = PandocConverter::read($source, 'dokuwiki');
        $native = PandocConverter::write($document, 'native');
        $html = PandocConverter::write($document, 'html');

        $t->same('dokuwiki', $document->attr('sourceFormat'));
        $t->contains('Header 1', $native);
        $t->contains('Image ( "" , [  ] , [ ( "height" , "100" ) , ( "query" , "?200x100" ) , ( "width" , "200" ) ] ) [ Str "Alt" ] ( "https://example.com/a.png" , "" )', $native);
        $t->contains('Link ( "" , [  ] , [  ] ) [ Str "https://example.com" ] ( "https://example.com" , "" )', $native);
        $t->contains('<h1>Media</h1>', $html);
        $t->contains('<img', $html);
        $t->contains('alt="Alt"', $html);
        $t->contains('<a href="https://example.com">https://example.com</a>', $html);
    },
];
