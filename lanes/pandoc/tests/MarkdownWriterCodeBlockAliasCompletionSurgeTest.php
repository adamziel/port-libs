<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$codeBlock = static fn (array $attrs): AstNode => new AstNode('code_block', $attrs);
$listItem = static fn (array $children): AstNode => new AstNode('list_item', [], $children);
$bulletList = static fn (array $children): AstNode => new AstNode('bullet_list', [], $children);
$blockquote = static fn (array $children): AstNode => new AstNode('blockquote', [], $children);

$cases = [
    'literal alias renders indented code block' => [
        'document' => $document([$codeBlock(['literal' => "echo literal\necho second"])]),
        'expected' => "    echo literal\n    echo second",
    ],
    'code alias renders fenced info class' => [
        'document' => $document([$codeBlock(['code' => 'echo code;', 'classes' => ['php']])]),
        'expected' => "```php\necho code;\n```",
    ],
    'value alias renders explicit info string fence' => [
        'document' => $document([$codeBlock(['value' => 'wp option get siteurl', 'info' => 'bash session'])]),
        'expected' => "``` bash session\nwp option get siteurl\n```",
    ],
    'value alias lengthens forced backtick fence' => [
        'document' => $document([$codeBlock(['value' => "alpha\n```\nbeta"])]),
        'expected' => "````\nalpha\n```\nbeta\n````",
        'options' => ['fencedCodeBlocks' => true],
    ],
    'content alias lengthens tilde info fence' => [
        'document' => $document([$codeBlock(['content' => 'alpha ~~~ beta', 'classes' => ['text']])]),
        'expected' => "~~~~text\nalpha ~~~ beta\n~~~~",
        'options' => ['fencedCodeBlockStyle' => 'tilde'],
    ],
    'string alias survives commonmark html fallback' => [
        'document' => $document([$codeBlock(['string' => 'echo <tag> & more', 'id' => 'src'])]),
        'expected' => '<pre><code id="src">echo &lt;tag&gt; &amp; more</code></pre>',
        'options' => ['format' => 'commonmark'],
    ],
    'value alias renders initial bullet list code block' => [
        'document' => $document([$bulletList([$listItem([$codeBlock(['value' => 'echo list'])])])]),
        'expected' => '-     echo list',
    ],
    'content alias renders multiline initial bullet list code block' => [
        'document' => $document([$bulletList([$listItem([$codeBlock(['content' => "echo one\necho two"])])])]),
        'expected' => "-     echo one\n      echo two",
    ],
    'literal alias renders blockquote code block' => [
        'document' => $document([$blockquote([$codeBlock(['literal' => 'echo quote'])])]),
        'expected' => '>     echo quote',
    ],
    'string alias renders literate haskell code block' => [
        'document' => $document([$codeBlock(['string' => 'main = putStrLn "hi"', 'classes' => ['haskell']])]),
        'expected' => '< main = putStrLn "hi"',
        'options' => ['literateHaskell' => true],
    ],
];

$tests = [
    'records markdown writer code block alias completion mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(10, count($cases));
        },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer code block alias completion ' . $label] =
        static function (TestRunner $t) use ($case): void {
            $markdown = (new MarkdownWriter($case['options'] ?? []))->write($case['document']);

            $t->same($case['expected'], $markdown);
        };
}

return $tests;
