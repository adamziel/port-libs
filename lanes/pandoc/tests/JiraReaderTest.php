<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\JiraReader;
use PortLibs\Pandoc\PandocConverter;

$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'linebreak') {
        return "\n";
    }

    return implode('', array_map($plainText, $node->children));
};

$nodesOfType = static function (AstNode $node, string $type) use (&$nodesOfType): array {
    $nodes = $node->type === $type ? [$node] : [];
    foreach ($node->children as $child) {
        array_push($nodes, ...$nodesOfType($child, $type));
    }

    return $nodes;
};

return [
    'maps upstream jira reader block unit semantics' => static function (TestRunner $t) use ($plainText, $nodesOfType): void {
        $source = implode("\n", [
            '',
            '',
            'Hello, World!',
            'first',
            'second',
            '',
            'h1. Main',
            '',
            '* foo',
            '* bar',
            '',
            '- minus one',
            '- minus two',
            '',
            '# first',
            '# second',
            '',
            'Regular text.',
            'bq.This is a blockquote',
            'More text.',
            '',
            '| one | two |',
            '| three | four |',
            '',
            '|| one || two ||',
            '| three | four |',
            '| five | six |',
            '',
            '|| language | haskell | lua |',
            '|| type | static | dynamic |',
            '',
            '*tabletest*',
            '||Name|',
            '|Test|',
            '',
            '{panel}',
            'Interviewer: Jane Doe',
            '{panel}',
        ]);
        $document = (new JiraReader())->read($source);
        $blocks = $document->children;
        $tables = $nodesOfType($document, 'table');
        $divs = $nodesOfType($document, 'div');

        $t->same('jira', $document->attr('sourceFormat'));
        $t->same("Hello, World!\nfirst\nsecond", $plainText($blocks[0]));
        $t->same('linebreak', $blocks[0]->children[1]->type);
        $t->same('heading', $blocks[1]->type);
        $t->same(1, $blocks[1]->attr('level'));
        $t->same('Main', $plainText($blocks[1]));
        $t->same('bullet_list', $blocks[2]->type);
        $t->same('foo', $plainText($blocks[2]->children[0]));
        $t->same('bar', $plainText($blocks[2]->children[1]));
        $t->same('bullet_list', $blocks[3]->type);
        $t->same('minus one', $plainText($blocks[3]->children[0]));
        $t->same('ordered_list', $blocks[4]->type);
        $t->same('first', $plainText($blocks[4]->children[0]));
        $t->same('paragraph', $blocks[5]->type);
        $t->same('Regular text.', $plainText($blocks[5]));
        $t->same('blockquote', $blocks[6]->type);
        $t->same('This is a blockquote', $plainText($blocks[6]));
        $t->same('More text.', $plainText($blocks[7]));

        $t->same(4, count($tables));
        $t->same([], $tables[0]->children[0]->children);
        $t->same('one', $tables[0]->children[1]->children[0]->children[0]->attr('text'));
        $t->same('two', $tables[1]->children[0]->children[0]->children[1]->attr('text'));
        $t->same([], $tables[2]->children[0]->children);
        $t->same('language', $tables[2]->children[1]->children[0]->children[0]->attr('text'));
        $t->same('Name', $tables[3]->children[0]->children[0]->children[0]->attr('text'));
        $t->same('Test', $tables[3]->children[1]->children[0]->children[0]->attr('text'));

        $t->same(1, count($divs));
        $t->same(['panel'], $divs[0]->attr('classes'));
        $t->same('Interviewer: Jane Doe', $plainText($divs[0]));
    },

    'maps upstream jira reader inline unit semantics' => static function (TestRunner $t) use ($nodesOfType, $plainText): void {
        $source = implode("\n\n", [
            '*quid pro quo*',
            '_Don\'t_ quote me on this.',
            '-old-',
            '{{this *is* monospace}}',
            'HCO ~3~^-^',
            'Et tu, Brute? ??Caesar??',
            'This is {color:red}red{color}.',
            '{color:#00875A}green{color}',
            '[Example|https://example.org]',
            '[See https://example.com|https://example.com]',
            '[mailto:me@example.org]',
            '[email|mailto:me@example.org]',
            '[^example.txt]',
            '[an example^example.txt]',
            '[~johndoe]',
            '[John Doe|~johndoe]',
            '[x|http://example.com|smart-link]',
            '[x|http://example.com|smart-card]',
            '!https://example.com/image.jpg!',
            '!image.jpg|thumbnail!',
            '!image.gif|align=right, vspace=4, title=Hello!',
            '+the new version+',
            'me &amp; you',
            '20.09-15 2-678',
        ]);
        $document = (new JiraReader())->read($source);
        $paragraphs = $document->children;
        $links = $nodesOfType($document, 'link');
        $images = $nodesOfType($document, 'image');
        $spans = $nodesOfType($document, 'span');

        $t->same('strong', $paragraphs[0]->children[0]->type);
        $t->same('quid pro quo', $plainText($paragraphs[0]));
        $t->same('emph', $paragraphs[1]->children[0]->type);
        $t->same("Don't quote me on this.", $plainText($paragraphs[1]));
        $t->same('strikeout', $paragraphs[2]->children[0]->type);
        $t->same('old', $plainText($paragraphs[2]));
        $t->same('code', $paragraphs[3]->children[0]->type);
        $t->same('this is monospace', $paragraphs[3]->children[0]->attr('text'));
        $t->same('subscript', $paragraphs[4]->children[1]->type);
        $t->same('superscript', $paragraphs[4]->children[2]->type);
        $t->same("Et tu, Brute? \u{2014} Caesar", $plainText($paragraphs[5]));
        $t->same('emph', $paragraphs[5]->children[3]->type);
        $t->same(['color' => 'red'], $spans[0]->attr('attributes'));
        $t->same(['color' => '#00875A'], $spans[1]->attr('attributes'));

        $t->same(10, count($links));
        $t->same('https://example.org', $links[0]->attr('url'));
        $t->same('Example', $plainText($links[0]));
        $t->same('See https://example.com', $plainText($links[1]));
        $t->same('mailto:me@example.org', $links[2]->attr('url'));
        $t->same('me@example.org', $plainText($links[2]));
        $t->same(['attachment'], $links[4]->attr('classes'));
        $t->same('example.txt', $links[4]->attr('url'));
        $t->same(['user-account'], $links[6]->attr('classes'));
        $t->same('~johndoe', $links[6]->attr('url'));
        $t->same(['smart-link'], $links[8]->attr('classes'));
        $t->same(['smart-card'], $links[9]->attr('classes'));

        $t->same(3, count($images));
        $t->same('https://example.com/image.jpg', $images[0]->attr('url'));
        $t->same(['thumbnail'], $images[1]->attr('classes'));
        $t->same('Hello', $images[2]->attr('title'));
        $t->same(['align' => 'right', 'vspace' => '4'], $images[2]->attr('attributes'));
        $t->same('underline', $paragraphs[21]->children[0]->type);
        $t->same('me & you', $plainText($paragraphs[22]));
        $t->same('20.09-15 2-678', $plainText($paragraphs[23]));
    },

    'maps upstream jira reader bare autolink fixture semantics' => static function (TestRunner $t) use ($nodesOfType, $plainText): void {
        $source = implode("\n", [
            'https://pandoc.org by itself should be a link.',
            'With an ampersand: http://example.com/?foo=1&bar=2.',
            'An e-mail address: mailto:nobody@nowhere.invalid.',
            '* In a list?',
            '* http://example.com/',
            'bq. Blockquoted: http://example.com/',
            '{code:java}',
            'Autolink should not occur here: http://example.com/',
            '{code}',
        ]);
        $document = (new JiraReader())->read($source);
        $links = $nodesOfType($document, 'link');
        $codeBlocks = $nodesOfType($document, 'code_block');

        $t->same(5, count($links));
        $t->same('https://pandoc.org', $links[0]->attr('url'));
        $t->same('https://pandoc.org', $plainText($links[0]));
        $t->same('http://example.com/?foo=1&bar=2', $links[1]->attr('url'));
        $t->same('http://example.com/?foo=1&bar=2', $plainText($links[1]));
        $t->same('mailto:nobody@nowhere.invalid', $links[2]->attr('url'));
        $t->same('mailto:nobody@nowhere.invalid', $plainText($links[2]));
        $t->same('http://example.com/', $links[3]->attr('url'));
        $t->same('http://example.com/', $links[4]->attr('url'));
        $t->same(1, count($codeBlocks));
        $t->same('Autolink should not occur here: http://example.com/', $codeBlocks[0]->attr('text'));
    },

    'reads jira through converter and renders shared ast outputs' => static function (TestRunner $t): void {
        $document = PandocConverter::read("*tabletest*\n||Name|\n|Test|\n", 'jira');
        $native = PandocConverter::write($document, 'native');
        $html = PandocConverter::write($document, 'html');
        $blocks = PandocConverter::write($document, 'wordpress');

        $t->same('jira', $document->attr('sourceFormat'));
        $t->contains('Strong [ Str "tabletest" ]', $native);
        $t->contains('Table', $native);
        $t->contains('<strong>tabletest</strong>', $html);
        $t->contains('<th><p>Name</p></th>', $blocks);
    },
];
