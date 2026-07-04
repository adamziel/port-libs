<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

/**
 * @return array<string, mixed>
 */
$options = static function (string $format, array $extensions, string $form): array {
    if ($form === 'list') {
        $list = [];
        foreach ($extensions as $name => $enabled) {
            $list[] = ($enabled ? '+' : '-') . $name;
        }

        return ['format' => $format, 'extensions' => $list];
    }

    return ['format' => $format, 'extensions' => $extensions];
};

$findFirst = null;
$findFirst = static function (AstNode $node, string $type) use (&$findFirst): AstNode {
    if ($node->type === $type) {
        return $node;
    }

    foreach ($node->children as $child) {
        $match = $findFirst($child, $type);
        if ($match->type === $type) {
            return $match;
        }
    }

    return new AstNode('missing');
};

$hasNodeType = static function (AstNode $node, string $type) use ($findFirst): bool {
    return $findFirst($node, $type)->type === $type;
};

$inlineTypes = static function (AstNode $document): array {
    $paragraph = $document->children[0] ?? new AstNode('missing');

    return array_map(static fn (AstNode $node): string => $node->type, $paragraph->children);
};

$forms = ['list', 'map'];
$rawHtmlSources = [
    'section block' => '<section data-profile="raw">raw html</section>',
    'comment block' => '<!-- raw profile comment -->',
    'cdata block' => '<![CDATA[<raw>profile</raw>]]>',
];

$rawHtmlDisabledCases = [];
foreach (['markdown', 'commonmark', 'gfm'] as $format) {
    foreach ($forms as $form) {
        foreach ($rawHtmlSources as $name => $source) {
            $rawHtmlDisabledCases[$format . ' ' . $form . ' disables ' . $name] = [
                'options' => $options($format, ['raw_html' => false], $form),
                'markdown' => $source . "\n\nAfter.",
            ];
        }
    }
}

$rawHtmlEnabledCases = [];
foreach (['commonmark-raw_html', 'gfm-raw_html'] as $format) {
    foreach ($forms as $form) {
        foreach (array_slice($rawHtmlSources, 0, 2) as $name => $source) {
            $rawHtmlEnabledCases[$format . ' ' . $form . ' reenables ' . $name] = [
                'options' => $options($format, ['raw_html' => true], $form),
                'markdown' => $source . "\n\nAfter.",
                'raw' => $source,
            ];
        }
    }
}

$rawTexCases = [];
foreach (['markdown_strict'] as $format) {
    foreach ($forms as $form) {
        $rawTexCases[$format . ' ' . $form . ' enables raw tex block'] = [
            'options' => $options($format, ['raw_tex' => true], $form),
            'enabled' => true,
        ];
    }
}
foreach (['commonmark', 'gfm'] as $format) {
    foreach ($forms as $form) {
        $rawTexCases[$format . ' ' . $form . ' leaves unsupported raw tex block literal'] = [
            'options' => $options($format, ['raw_tex' => true], $form),
            'enabled' => false,
        ];
    }
}
foreach (['markdown', 'commonmark_x'] as $format) {
    foreach ($forms as $form) {
        $rawTexCases[$format . ' ' . $form . ' disables raw tex block'] = [
            'options' => $options($format, ['raw_tex' => false], $form),
            'enabled' => false,
        ];
    }
}

$rawBlockPayloads = [
    'html' => ['format' => 'html', 'extensions' => ['raw_attribute' => true, 'raw_html' => true], 'text' => '<aside>raw html</aside>'],
    'markdown' => ['format' => 'markdown', 'extensions' => ['raw_attribute' => true, 'raw_markdown' => true], 'text' => '> raw markdown'],
    'latex' => ['format' => 'latex', 'extensions' => ['raw_attribute' => true, 'raw_tex' => true], 'text' => '\\begin{center}' . "\n" . 'raw tex' . "\n" . '\\end{center}'],
];

$rawBlockCases = [];
foreach (['commonmark', 'gfm'] as $format) {
    foreach ($forms as $form) {
        foreach ($rawBlockPayloads as $name => $payload) {
            $enabled = $name !== 'latex';
            $rawBlockCases[$format . ' ' . $form . ' ' . ($enabled ? 'enables' : 'leaves unsupported') . ' raw fenced block ' . $name] = [
                'options' => $options($format, $payload['extensions'], $form),
                'rawFormat' => $payload['format'],
                'text' => $payload['text'],
                'enabled' => $enabled,
            ];
        }
    }
}
foreach (['markdown', 'commonmark_x'] as $format) {
    foreach ($forms as $form) {
        $rawBlockCases[$format . ' ' . $form . ' disables raw attribute fenced block'] = [
            'options' => $options($format, ['raw_attribute' => false], $form),
            'rawFormat' => 'html',
            'text' => '<aside>raw html</aside>',
            'enabled' => false,
        ];
    }
}
foreach ($forms as $form) {
    $rawBlockCases['markdown ' . $form . ' disables raw markdown fenced block'] = [
        'options' => $options('markdown', ['raw_markdown' => false], $form),
        'rawFormat' => 'markdown',
        'text' => '> raw markdown',
        'enabled' => false,
    ];
}

$rawInlineCases = [];
foreach (['commonmark', 'gfm'] as $format) {
    foreach ($forms as $form) {
        foreach ($rawBlockPayloads as $name => $payload) {
            $enabled = $name !== 'latex';
            $rawInlineCases[$format . ' ' . $form . ' ' . ($enabled ? 'enables' : 'leaves unsupported') . ' raw inline ' . $name] = [
                'options' => $options($format, $payload['extensions'], $form),
                'rawFormat' => $payload['format'],
                'text' => str_replace("\n", ' ', $payload['text']),
                'enabled' => $enabled,
            ];
        }
    }
}
foreach (['markdown', 'commonmark_x'] as $format) {
    foreach ($forms as $form) {
        $rawInlineCases[$format . ' ' . $form . ' disables raw attribute inline'] = [
            'options' => $options($format, ['raw_attribute' => false], $form),
            'rawFormat' => 'html',
            'text' => '<span>raw html</span>',
            'enabled' => false,
        ];
    }
}
foreach ($forms as $form) {
    $rawInlineCases['markdown ' . $form . ' disables raw markdown inline'] = [
        'options' => $options('markdown', ['raw_markdown' => false], $form),
        'rawFormat' => 'markdown',
        'text' => '**raw markdown**',
        'enabled' => false,
    ];
}

$codeAttributeCases = [];
foreach (['commonmark', 'gfm', 'markdown_strict'] as $format) {
    foreach ($forms as $form) {
        $codeAttributeCases[$format . ' ' . $form . ' enables fenced code attributes'] = [
            'options' => $options($format, ['fenced_code_attributes' => true], $form),
            'enabled' => true,
        ];
    }
}
foreach (['markdown', 'commonmark_x'] as $format) {
    foreach ($forms as $form) {
        $codeAttributeCases[$format . ' ' . $form . ' disables fenced code attributes'] = [
            'options' => $options($format, ['fenced_code_attributes' => false], $form),
            'enabled' => false,
        ];
    }
}

return [
    'maps upstream markdown reader raw html extension option overrides for comments cdata and blocks' =>
        static function (TestRunner $t) use ($rawHtmlDisabledCases, $rawHtmlEnabledCases, $hasNodeType): void {
            foreach ($rawHtmlDisabledCases as $name => $case) {
                $document = (new MarkdownReader($case['options']))->read($case['markdown']);

                $t->same(false, $hasNodeType($document, 'raw_html'), $name);
                $t->same('paragraph', ($document->children[0] ?? new AstNode('missing'))->type, $name);
            }

            foreach ($rawHtmlEnabledCases as $name => $case) {
                $document = (new MarkdownReader($case['options']))->read($case['markdown']);
                $raw = $document->children[0] ?? new AstNode('missing');

                $t->same('raw_html', $raw->type, $name);
                $t->same($case['raw'], $raw->attr('html'), $name);
            }
        },

    'maps upstream markdown reader raw tex block extension option overrides' =>
        static function (TestRunner $t) use ($rawTexCases): void {
            $markdown = '\\begin{center}' . "\n" . 'raw tex profile' . "\n" . '\\end{center}';

            foreach ($rawTexCases as $name => $case) {
                $document = (new MarkdownReader($case['options']))->read($markdown);
                $first = $document->children[0] ?? new AstNode('missing');

                $t->same($case['enabled'] ? 'raw_tex' : 'paragraph', $first->type, $name);
                if ($case['enabled']) {
                    $t->contains('raw tex profile', $first->attr('tex'), $name);
                }
            }
        },

    'maps upstream markdown reader raw attribute fenced block and raw family option overrides' =>
        static function (TestRunner $t) use ($rawBlockCases): void {
            foreach ($rawBlockCases as $name => $case) {
                $document = (new MarkdownReader($case['options']))->read(implode("\n", [
                    '```{=' . $case['rawFormat'] . '}',
                    $case['text'],
                    '```',
                    '',
                    'After.',
                ]));
                $first = $document->children[0] ?? new AstNode('missing');

                $t->same($case['enabled'] ? 'raw_block' : 'code_block', $first->type, $name);
                if ($case['enabled']) {
                    $t->same($case['rawFormat'], $first->attr('format'), $name);
                    $t->same($case['text'], $first->attr('text'), $name);
                } else {
                    $t->same('{=' . $case['rawFormat'] . '}', $first->attr('info'), $name);
                }
            }
        },

    'maps upstream markdown reader raw inline attribute and raw family option overrides' =>
        static function (TestRunner $t) use ($rawInlineCases, $findFirst, $inlineTypes): void {
            foreach ($rawInlineCases as $name => $case) {
                $document = (new MarkdownReader($case['options']))->read(
                    'Before `' . $case['text'] . '`{=' . $case['rawFormat'] . '} after.'
                );
                $expectedType = in_array($case['rawFormat'], ['html', 'html4', 'html5', 'xhtml'], true)
                    ? 'raw_html_inline'
                    : 'raw_inline';
                $raw = $findFirst($document, $expectedType);

                $t->same($case['enabled'] ? $expectedType : 'missing', $raw->type, $name);
                if ($case['enabled']) {
                    $t->same($case['rawFormat'], $raw->attr('format'), $name);
                    $t->same($case['text'], $raw->attr('text'), $name);
                } else {
                    $t->same(true, in_array('code', $inlineTypes($document), true), $name);
                }
            }
        },

    'maps upstream markdown reader fenced code attribute option overrides' =>
        static function (TestRunner $t) use ($codeAttributeCases): void {
            foreach ($codeAttributeCases as $name => $case) {
                $document = (new MarkdownReader($case['options']))->read(implode("\n", [
                    '```{#profile-code .php data-review="profile"}',
                    'echo 1;',
                    '```',
                ]));
                $code = $document->children[0] ?? new AstNode('missing');

                $t->same('code_block', $code->type, $name);
                $t->same('echo 1;', $code->attr('text'), $name);
                if ($case['enabled']) {
                    $t->same('profile-code', $code->attr('id'), $name);
                    $t->same(['php'], $code->attr('classes'), $name);
                    $t->same(['data-review' => 'profile'], $code->attr('attributes'), $name);
                } else {
                    $t->same(null, $code->attr('id', null), $name);
                    $t->same(['{#profile-code'], $code->attr('classes'), $name);
                    $t->same([], $code->attr('attributes'), $name);
                }
            }
        },

    'records upstream markdown reader raw code profile override surge mapped-case count' =>
        static function (
            TestRunner $t
        ) use ($rawHtmlDisabledCases, $rawHtmlEnabledCases, $rawTexCases, $rawBlockCases, $rawInlineCases, $codeAttributeCases): void {
            $t->same(
                82,
                count($rawHtmlDisabledCases)
                    + count($rawHtmlEnabledCases)
                    + count($rawTexCases)
                    + count($rawBlockCases)
                    + count($rawInlineCases)
                    + count($codeAttributeCases)
            );
        },
];
