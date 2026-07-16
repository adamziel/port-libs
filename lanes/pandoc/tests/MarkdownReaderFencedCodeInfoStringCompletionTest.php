<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$nodeTypes = static fn (AstNode $document): array => array_map(
    static fn (AstNode $node): string => $node->type,
    $document->children
);

$invalidBacktickInfoCases = [
    'single trailing backtick at eof' => [
        'markdown' => "``` info`",
        'types' => ['paragraph'],
        'paragraphNeedle' => 'info',
    ],
    'balanced backtick info at eof' => [
        'markdown' => "``` `info`",
        'types' => ['paragraph'],
        'paragraphNeedle' => 'info',
    ],
    'inline backtick before eof' => [
        'markdown' => "``` lang`x\nbody",
        'types' => ['paragraph'],
        'paragraphNeedle' => 'body',
    ],
    'inline backtick before later backtick fence' => [
        'markdown' => "``` lang `x`\nbody\n```",
        'types' => ['paragraph', 'code_block'],
        'paragraphNeedle' => 'body',
    ],
    'indented inline backtick before later backtick fence' => [
        'markdown' => "   ``` lang `x`\nbody\n   ```",
        'types' => ['paragraph', 'code_block'],
        'paragraphNeedle' => 'body',
    ],
    'long backtick fence with info backtick' => [
        'markdown' => "```` lang `x`\nbody\n````",
        'types' => ['paragraph', 'code_block'],
        'paragraphNeedle' => 'body',
    ],
];

$tildeBacktickInfoCases = [
    'tilde info contains backtick' => [
        'markdown' => "~~~ lang `x`\nbody\n~~~",
        'info' => 'lang `x`',
        'class' => 'lang',
        'text' => 'body',
    ],
    'indented tilde info contains backtick' => [
        'markdown' => "   ~~~ lang `x`\n body\n   ~~~",
        'info' => 'lang `x`',
        'class' => 'lang',
        'text' => 'body',
    ],
    'tilde info begins with backtick token' => [
        'markdown' => "~~~ `lang` data\nbody\n~~~",
        'info' => '`lang` data',
        'class' => '`lang`',
        'text' => 'body',
    ],
    'long tilde fence keeps shorter tilde and backtick payload' => [
        'markdown' => "~~~~ lang `x`\n~~~\n```\n~~~~",
        'info' => 'lang `x`',
        'class' => 'lang',
        'text' => "~~~\n```",
    ],
    'long tilde fence keeps shorter tilde payload line' => [
        'markdown' => "~~~~ lang `x`\nbody\n~~~\n~~~~",
        'info' => 'lang `x`',
        'class' => 'lang',
        'text' => "body\n~~~",
    ],
    'five tilde fence info contains backtick' => [
        'markdown' => "~~~~~ php `tick`\ncode\n~~~~~",
        'info' => 'php `tick`',
        'class' => 'php',
        'text' => 'code',
    ],
];

return [
    'maps commonmark backtick fence info-string backtick rejection cases' =>
        static function (TestRunner $t) use ($invalidBacktickInfoCases, $nodeTypes): void {
            $mapped = 0;
            foreach ($invalidBacktickInfoCases as $name => $case) {
                $document = (new MarkdownReader(['format' => 'commonmark']))->read($case['markdown']);
                $paragraph = $document->children[0] ?? new AstNode('missing');
                $trailing = $document->children[1] ?? null;

                $t->same($case['types'], $nodeTypes($document), $name);
                $t->same('paragraph', $paragraph->type, $name);
                $t->contains($case['paragraphNeedle'], (string) $paragraph->attr('text', ''), $name);
                if ($trailing instanceof AstNode) {
                    $t->same('code_block', $trailing->type, $name);
                    $t->same('', (string) $trailing->attr('text', ''), $name);
                }
                $mapped++;
            }

            $t->same(6, $mapped);
        },
    'maps commonmark tilde fence info-string backtick acceptance cases' =>
        static function (TestRunner $t) use ($tildeBacktickInfoCases): void {
            $mapped = 0;
            foreach ($tildeBacktickInfoCases as $name => $case) {
                $document = (new MarkdownReader(['format' => 'commonmark']))->read($case['markdown']);
                $code = $document->children[0] ?? new AstNode('missing');

                $t->same(['code_block'], array_map(static fn (AstNode $node): string => $node->type, $document->children), $name);
                $t->same('code_block', $code->type, $name);
                $t->same($case['text'], (string) $code->attr('text', ''), $name);
                $t->same($case['info'], (string) $code->attr('info', ''), $name);
                $t->same([$case['class']], $code->attr('classes', []), $name);
                $mapped++;
            }

            $t->same(6, $mapped);
        },
    'records commonmark fenced code info-string completion mapped-case count' =>
        static function (TestRunner $t) use ($invalidBacktickInfoCases, $tildeBacktickInfoCases): void {
            $t->same(12, count($invalidBacktickInfoCases) + count($tildeBacktickInfoCases));
        },
];
