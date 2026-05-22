<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\CodeBlockDetector;
use PortLibs\MarkerPDF\MarkdownPostProcessor;

return [
    'counts upstream-supported comment prefixes in candidate code lines' => static function (TestRunner $t): void {
        $detector = new CodeBlockDetector();

        $t->same(8, $detector->commentCount([
            '// JavaScript comment',
            '    // indented JavaScript comment',
            '# Python or shell comment',
            '-- SQL comment',
            '/* C block comment',
            '<!-- HTML comment',
            '% LaTeX comment',
            '(* Pascal comment',
            'plain text',
        ]));
    },
    'uses upstream line-length ratio for code-like blocks' => static function (TestRunner $t): void {
        $detector = new CodeBlockDetector();

        $t->true($detector->isCodeLineLength([
            'def render_block():',
            '    return "<!-- wp:code -->"',
            'print(render_block())',
            '# done',
        ]));
        $t->true(!$detector->isCodeLineLength([
            'This imported paragraph is intentionally verbose and prose-like enough to exceed the upstream code line-length heuristic.',
            'It should stay a paragraph in WordPress instead of becoming a code block during the import cleanup phase.',
        ]));
        $t->true(!$detector->isCodeLineLength(['   ', "\t"]));
    },
    'identifies indented comment-heavy text blocks as code' => static function (TestRunner $t): void {
        $detector = new CodeBlockDetector();
        $lines = [
            ['text' => 'def render_block():', 'left' => 72.0, 'fontSize' => 8.0, 'height' => 9.0],
            ['text' => '    # Preserve imported PDF code', 'left' => 96.0, 'fontSize' => 8.0, 'height' => 9.0],
            ['text' => 'html = "<!-- wp:code -->"', 'left' => 96.0, 'fontSize' => 8.0, 'height' => 9.0],
            ['text' => 'return html', 'left' => 96.0, 'fontSize' => 8.0, 'height' => 9.0],
            ['text' => '# emitted by a PDF textbook', 'left' => 72.0, 'fontSize' => 8.0, 'height' => 9.0],
        ];

        $t->true($detector->isCodeBlock($lines));
        $t->true($detector->isCodeBlock($lines, 80, 12.0, 12.0));
        $t->true(!$detector->isCodeBlock($lines, 80, 9.0, 12.0));
        $t->true(!$detector->isCodeBlock($lines, 80, 12.0, 10.0));
    },
    'keeps paragraph-like imports as text blocks' => static function (TestRunner $t): void {
        $detector = new CodeBlockDetector();

        $t->true(!$detector->isCodeBlock([
            ['text' => 'A migrated PDF paragraph can span several short lines.', 'left' => 72.0],
            ['text' => 'It has sentence structure and no code indentation.', 'left' => 72.0],
            ['text' => 'The cleaner should leave this as normal text.', 'left' => 72.0],
            ['text' => 'WordPress will render it as a paragraph block.', 'left' => 72.0],
        ]));
    },
    'classifies and indents code blocks before Gutenberg rendering' => static function (TestRunner $t): void {
        $detector = new CodeBlockDetector();
        $blocks = $detector->identifyCodeBlocks([
            [
                'type' => 'Text',
                'lines' => [
                    ['text' => 'Imported code sample:', 'left' => 72.0],
                ],
            ],
            [
                'type' => 'Text',
                'lines' => [
                    ['text' => '// source: imported PDF sample', 'left' => 72.0, 'right' => 268.0],
                    ['text' => '// target: WordPress code block', 'left' => 72.0, 'right' => 275.0],
                    ['text' => '// cleaner: marker.cleaners.code', 'left' => 72.0, 'right' => 275.0],
                    ['text' => 'function migrate_pdf() {', 'left' => 72.0, 'right' => 184.0],
                    ['text' => '// emit a code block', 'left' => 86.0, 'right' => 198.0],
                    ['text' => 'return true;', 'left' => 86.0, 'right' => 156.0],
                    ['text' => '}', 'left' => 72.0, 'right' => 79.0],
                ],
            ],
        ]);

        $t->same('Text', $blocks[0]['type']);
        $t->same('Code', $blocks[1]['type']);
        $t->same(
            "// source: imported PDF sample\n// target: WordPress code block\n// cleaner: marker.cleaners.code\nfunction migrate_pdf() {\n  // emit a code block\n  return true;\n}\n",
            $detector->indentBlock($blocks[1]['lines'])
        );
        $t->same(
            "\n```\n// source: imported PDF sample\n// target: WordPress code block\n// cleaner: marker.cleaners.code\nfunction migrate_pdf() {\n  // emit a code block\n  return true;\n}\n\n```\n",
            (new MarkdownPostProcessor())->surroundBlock($detector->indentBlock($blocks[1]['lines']), 'Code')
        );
    },
];
