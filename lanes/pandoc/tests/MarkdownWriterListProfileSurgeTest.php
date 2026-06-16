<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$plain = static fn (array $children): AstNode => new AstNode('plain', [], $children);
$plainText = static fn (string $value): AstNode => $plain([$text($value)]);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
$codeBlock = static fn (string $value, array $attrs = []): AstNode => new AstNode(
    'code_block',
    array_replace(['text' => $value], $attrs)
);
$listItem = static fn (array $children, array $attrs = []): AstNode => new AstNode('list_item', $attrs, $children);
$bulletList = static fn (array $items): AstNode => new AstNode('bullet_list', [], $items);
$orderedList = static fn (array $items, array $attrs = []): AstNode => new AstNode('ordered_list', $attrs, $items);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);

$portableProfiles = ['commonmark', 'gfm', 'markdown_github', 'markdown_strict'];
$cases = [];

$addProfileCase = static function (
    string $label,
    AstNode $doc,
    string $expected,
    array $options
) use (&$cases): void {
    $cases[$label] = [
        'document' => $doc,
        'expected' => $expected,
        'options' => $options,
    ];
};

foreach ($portableProfiles as $profile) {
    $options = ['format' => $profile];
    $prefix = $profile . ' list profile ';
    $taskListsEnabled = in_array($profile, ['gfm', 'markdown_github'], true);

    $addProfileCase(
        $prefix . 'default auto marker falls back to decimal period',
        $document([$orderedList([$listItem([$plainText('auto')])], ['style' => 'default', 'start' => 4])]),
        '4.  auto',
        $options
    );
    $addProfileCase(
        $prefix . 'default auto one paren falls back to decimal one paren',
        $document([$orderedList([$listItem([$plainText('auto')])], ['style' => 'default', 'delimiter' => 'one_paren', 'start' => 4])]),
        '4)  auto',
        $options
    );
    $addProfileCase(
        $prefix . 'lower alpha falls back to decimal period',
        $document([$orderedList([$listItem([$plainText('alpha')])], ['style' => 'lower_alpha', 'start' => 2])]),
        '2.  alpha',
        $options
    );
    $addProfileCase(
        $prefix . 'upper alpha rollover falls back to decimal period',
        $document([$orderedList([$listItem([$plainText('upper')])], ['style' => 'upper_alpha', 'start' => 28])]),
        '28. upper',
        $options
    );
    $addProfileCase(
        $prefix . 'lower roman falls back to decimal period',
        $document([$orderedList([$listItem([$plainText('roman')])], ['style' => 'lower_roman', 'start' => 4])]),
        '4.  roman',
        $options
    );
    $addProfileCase(
        $prefix . 'upper roman one paren falls back to decimal one paren',
        $document([$orderedList([$listItem([$plainText('roman')])], ['style' => 'upper_roman', 'delimiter' => 'one_paren', 'start' => 9])]),
        '9)  roman',
        $options
    );
    $addProfileCase(
        $prefix . 'decimal two parens falls back to period delimiter',
        $document([$orderedList([$listItem([$plainText('decimal')])], ['delimiter' => 'two_parens', 'start' => 3])]),
        '3.  decimal',
        $options
    );
    $addProfileCase(
        $prefix . 'lower alpha two parens falls back to decimal period',
        $document([$orderedList([$listItem([$plainText('alpha two')])], ['style' => 'lower_alpha', 'delimiter' => 'two_parens', 'start' => 2])]),
        '2.  alpha two',
        $options
    );
    $addProfileCase(
        $prefix . 'numbered example falls back to decimal period',
        $document([$orderedList([$listItem([$plainText('example')])], ['style' => 'example', 'start' => 5])]),
        '5.  example',
        $options
    );
    $addProfileCase(
        $prefix . 'labeled numbered example falls back to decimal period',
        $document([$orderedList([$listItem([$plainText('labeled')], ['exampleLabel' => 'review-a'])], ['style' => 'example', 'start' => 5])]),
        '5.  labeled',
        $options
    );
    $addProfileCase(
        $prefix . 'adjacent fancy lists get separator after decimal fallback',
        $document([
            $orderedList([$listItem([$plainText('alpha')])], ['style' => 'lower_alpha', 'start' => 2]),
            $orderedList([$listItem([$plainText('roman')])], ['style' => 'upper_roman', 'start' => 4]),
        ]),
        "2.  alpha\n\n<!-- -->\n\n4.  roman",
        $options
    );
    $addProfileCase(
        $prefix . 'nested fallback ordered child remains inside bullet item',
        $document([$bulletList([$listItem([
            $plainText('parent'),
            $orderedList([$listItem([$plainText('child')])], ['style' => 'lower_alpha', 'start' => 3]),
        ])])]),
        "- parent\n  3.  child",
        $options
    );
    $addProfileCase(
        $prefix . 'nested fallback ordered child remains inside ordered item',
        $document([$orderedList([$listItem([
            $plainText('parent'),
            $orderedList([$listItem([$plainText('child')])], ['style' => 'default', 'start' => 7]),
        ])], ['style' => 'upper_alpha', 'start' => 2])]),
        "2.  parent\n    7.  child",
        $options
    );
    $addProfileCase(
        $prefix . 'loose fancy list uses portable decimal markers',
        $document([$orderedList([$listItem([$plainText('one')]), $listItem([$plainText('two')])], ['style' => 'lower_roman', 'start' => 4, 'loose' => true])]),
        "4.  one\n\n5.  two",
        $options
    );
    $addProfileCase(
        $prefix . 'loose fancy item in tight list keeps portable boundary',
        $document([$orderedList([
            $listItem([$plainText('one')]),
            $listItem([$plainText('two')], ['loose' => true]),
            $listItem([$plainText('three')]),
        ], ['style' => 'upper_alpha', 'start' => 4])]),
        "4.  one\n\n5.  two\n6.  three",
        $options
    );
    $addProfileCase(
        $prefix . 'task list item inherits decimal fallback marker',
        $document([$orderedList([$listItem([$plainText('done')], ['taskChecked' => true])], ['style' => 'lower_alpha', 'start' => 2])]),
        $taskListsEnabled ? '2.  [x] done' : '<ol start="2" type="a"><li><input type="checkbox" checked="" /><p>done</p></li></ol>',
        $options
    );
    $addProfileCase(
        $prefix . 'task list continuation indents under decimal fallback marker',
        $document([$orderedList([$listItem([$plain([$text('todo'), new AstNode('softbreak'), $text('continued')])], ['taskChecked' => false])], ['style' => 'upper_alpha', 'start' => 2])]),
        $taskListsEnabled ? "2.  [ ] todo\n    continued" : '<ol start="2" type="A"><li><input type="checkbox" /><p>todo<br />continued</p></li></ol>',
        $options
    );
    $addProfileCase(
        $prefix . 'paragraph continuation indents under decimal fallback marker',
        $document([$orderedList([$listItem([$plainText('alpha'), $paragraph('beta')])], ['style' => 'default', 'start' => 2])]),
        "2.  alpha\n\n    beta",
        $options
    );
    $addProfileCase(
        $prefix . 'fenced code continuation indents under decimal fallback marker',
        $document([$orderedList([$listItem([$plainText('parent'), $codeBlock('echo', ['classes' => ['php']])])], ['style' => 'lower_roman', 'start' => 2])]),
        "2.  parent\n    ```php\n    echo\n    ```",
        $options
    );
    $addProfileCase(
        $prefix . 'softbreak continuation indents under decimal fallback marker',
        $document([$orderedList([$listItem([$plain([$text('alpha'), new AstNode('softbreak'), $text('beta')])])], ['style' => 'default', 'start' => 4])]),
        "4.  alpha\n    beta",
        $options
    );
    $addProfileCase(
        $prefix . 'portable decimal one paren remains available',
        $document([$orderedList([$listItem([$plainText('item')])], ['style' => 'decimal', 'delimiter' => 'one_paren', 'start' => 12])]),
        '12) item',
        $options
    );
}

foreach (['commonmark+fancy_lists', 'gfm+fancy_lists', 'markdown_strict+fancy_lists'] as $format) {
    $options = ['format' => $format];
    $prefix = $format . ' list profile ';

    $addProfileCase(
        $prefix . 'keeps lower alpha marker',
        $document([$orderedList([$listItem([$plainText('alpha')])], ['style' => 'lower_alpha', 'start' => 2])]),
        'b.  alpha',
        $options
    );
    $addProfileCase(
        $prefix . 'keeps default auto marker',
        $document([$orderedList([$listItem([$plainText('auto')])], ['style' => 'default', 'start' => 4])]),
        '#.  auto',
        $options
    );
    $addProfileCase(
        $prefix . 'keeps two parens delimiter',
        $document([$orderedList([$listItem([$plainText('decimal')])], ['style' => 'decimal', 'delimiter' => 'two_parens', 'start' => 3])]),
        '(3) decimal',
        $options
    );
}

foreach (['commonmark+example_lists', 'gfm+example_lists', 'markdown_strict+example_lists'] as $format) {
    $addProfileCase(
        $format . ' list profile keeps numbered example marker',
        $document([$orderedList([$listItem([$plainText('example')], ['exampleLabel' => 'profile-a'])], ['style' => 'example'])]),
        '(@profile-a) example',
        ['format' => $format]
    );
}

$tests = [
    'records markdown writer list profile surge mapped case count' => static function (TestRunner $t) use ($cases): void {
        $t->same(96, count($cases));
    },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer list profile surge ' . $label] =
        static function (TestRunner $t) use ($case): void {
            $markdown = (new MarkdownWriter($case['options']))->write($case['document']);

            $t->same($case['expected'], $markdown);
        };
}

return $tests;
