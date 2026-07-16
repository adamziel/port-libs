<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$findFirstNode = null;
$findFirstNode = static function (AstNode $node, string $type) use (&$findFirstNode): AstNode {
    if ($node->type === $type) {
        return $node;
    }

    foreach ($node->children as $child) {
        $match = $findFirstNode($child, $type);
        if ($match->type === $type) {
            return $match;
        }
    }

    return new AstNode('missing');
};

$assertAbbreviation = static function (
    TestRunner $t,
    string $markdown,
    string $expectedTerm,
    string $expectedTitle,
    string $context
) use ($findFirstNode): void {
    $document = (new MarkdownReader())->read($markdown);
    $span = $findFirstNode($document, 'span');
    $attributes = $span->attr('attributes', []);
    $htmlAttributes = $span->attr('htmlAttributes', []);

    $t->same('span', $span->type, $context . ' node');
    $t->same(['abbr'], $span->attr('classes'), $context . ' class');
    $t->same($expectedTitle, is_array($attributes) ? ($attributes['title'] ?? null) : null, $context . ' title');
    $t->same($expectedTitle, is_array($htmlAttributes) ? ($htmlAttributes['title'] ?? null) : null, $context . ' html title');
    $t->same($expectedTerm, $span->children[0]->attr('text'), $context . ' term');
    $t->same(1, count($document->children), $context . ' removes definition');
};

$punctuationCases = [
    'bang' => ['\\!', '!'],
    'double quote' => ['\\"', '"'],
    'hash' => ['\\#', '#'],
    'dollar' => ['\\$', '$'],
    'percent' => ['\\%', '%'],
    'ampersand' => ['\\&', '&'],
    'apostrophe' => ["\\'", "'"],
    'open paren' => ['\\(', '('],
    'close paren' => ['\\)', ')'],
    'asterisk' => ['\\*', '*'],
    'plus' => ['\\+', '+'],
    'comma' => ['\\,', ','],
    'minus' => ['\\-', '-'],
    'period' => ['\\.', '.'],
    'slash' => ['\\/', '/'],
    'colon' => ['\\:', ':'],
    'semicolon' => ['\\;', ';'],
    'less than' => ['\\<', '<'],
    'equals' => ['\\=', '='],
    'greater than' => ['\\>', '>'],
    'question' => ['\\?', '?'],
    'at sign' => ['\\@', '@'],
    'caret' => ['\\^', '^'],
    'underscore' => ['\\_', '_'],
    'backtick' => ['\\`', '`'],
    'open bracket' => ['\\[', '['],
    'close bracket' => ['\\]', ']'],
    'open brace' => ['\\{', '{'],
    'pipe' => ['\\|', '|'],
    'close brace' => ['\\}', '}'],
    'tilde' => ['\\~', '~'],
];

$nestedLabelCases = [
    'release packet' => 'release [candidate] packet',
    'audit note' => 'audit [trail] note',
    'migration source' => 'migration [batch] source',
    'entity catalog' => 'entity [catalog] record',
    'link target' => 'link [target] record',
    'image source' => 'image [source] record',
    'title review' => 'title [review] source',
    'wordpress handoff' => 'wordpress [handoff] source',
    'html entity' => 'html [entity] source',
    'escaped label' => 'escaped [label] source',
    'reference map' => 'reference [map] source',
    'shortcut ref' => 'shortcut [ref] source',
    'collapsed ref' => 'collapsed [ref] source',
];

$tests = [];

foreach ($punctuationCases as $name => [$escaped, $literal]) {
    $tests['maps pandoc markdown abbreviation escaped label punctuation ' . $name] =
        static function (TestRunner $t) use ($assertAbbreviation, $escaped, $literal, $name): void {
            $sourceTerm = 'abbr' . $escaped . 'term';
            $expectedTerm = 'abbr' . $literal . 'term';
            $title = 'Escaped label ' . $name;
            $markdown = 'Before ' . $expectedTerm . ' after.' . "\n\n" . '*[' . $sourceTerm . ']: ' . $title;

            $assertAbbreviation($t, $markdown, $expectedTerm, $title, $name);
        };
}

foreach ($punctuationCases as $name => [$escaped, $literal]) {
    $tests['maps pandoc markdown abbreviation escaped title punctuation ' . $name] =
        static function (TestRunner $t) use ($assertAbbreviation, $escaped, $literal, $name): void {
            $term = 'title-' . str_replace(' ', '-', $name);
            $sourceTitle = 'Title ' . $escaped . ' marker';
            $expectedTitle = 'Title ' . $literal . ' marker';
            $markdown = 'Before ' . $term . ' after.' . "\n\n" . '*[' . $term . ']: ' . $sourceTitle;

            $assertAbbreviation($t, $markdown, $term, $expectedTitle, $name);
        };
}

foreach ($nestedLabelCases as $name => $term) {
    $tests['maps pandoc markdown abbreviation nested bracket label ' . $name] =
        static function (TestRunner $t) use ($assertAbbreviation, $term, $name): void {
            $title = 'Nested abbreviation ' . $name;
            $markdown = 'Before ' . $term . ' after.' . "\n\n" . '*[' . $term . ']: ' . $title;

            $assertAbbreviation($t, $markdown, $term, $title, $name);
        };
}

$tests['writes wordpress abbreviation escaped definition attributes'] =
    static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("Import C++ and label]term.\n\n*[C\\+\\+]: C\\+\\+ Language\n*[label\\]term]: Label \\] Term");
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<abbr title="C++ Language">C++</abbr>', $blocks);
        $t->contains('<abbr title="Label ] Term">label]term</abbr>', $blocks);
    };

$tests['records pandoc markdown abbreviation definition surge mapped-case count'] =
    static function (TestRunner $t) use ($punctuationCases, $nestedLabelCases): void {
        $t->same(75, count($punctuationCases) + count($punctuationCases) + count($nestedLabelCases));
    };

return $tests;
