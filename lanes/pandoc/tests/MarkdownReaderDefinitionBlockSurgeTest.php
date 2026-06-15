<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $inlineText($child);
    }

    return $text;
};

$listItemText = static function (AstNode $item) use ($inlineText): string {
    $parts = [];
    foreach ($item->children as $child) {
        if ($child->type === 'bullet_list' || $child->type === 'ordered_list' || $child->type === 'definition_list') {
            continue;
        }

        $parts[] = trim($inlineText($child));
    }

    return trim(implode(' ', array_filter($parts, static fn (string $part): bool => $part !== '')));
};

$blockSummary = static function (AstNode $node) use (&$blockSummary, $inlineText, $listItemText): array {
    $summary = ['type' => $node->type];
    if ($node->type === 'paragraph' || $node->type === 'plain') {
        $summary['text'] = trim($inlineText($node));
    } elseif ($node->type === 'heading') {
        $summary['level'] = $node->attr('level');
        $summary['text'] = (string) $node->attr('text', '');
    } elseif ($node->type === 'code_block') {
        $summary['text'] = (string) $node->attr('text', '');
        $classes = $node->attr('classes', []);
        if ($classes !== []) {
            $summary['classes'] = $classes;
        }
    } elseif ($node->type === 'line_block') {
        $summary['lines'] = array_map(
            static fn (AstNode $line): string => (string) $line->attr('text', ''),
            $node->children
        );
    } elseif ($node->type === 'blockquote') {
        $summary['text'] = trim($inlineText($node));
    } elseif ($node->type === 'bullet_list' || $node->type === 'ordered_list') {
        $summary['items'] = array_map($listItemText, $node->children);
        if ($node->type === 'bullet_list' && $node->attr('taskList') === true) {
            $summary['taskList'] = true;
            $summary['tasks'] = array_map(
                static fn (AstNode $item): ?bool => $item->attr('taskChecked', null),
                $node->children
            );
        }
        if ($node->type === 'ordered_list') {
            $summary['start'] = $node->attr('start');
            $summary['style'] = $node->attr('style');
            $summary['delimiter'] = $node->attr('delimiter');
        }
    } elseif ($node->type === 'raw_html') {
        $summary['html'] = (string) $node->attr('html', '');
    }

    return $summary;
};

$cases = [];
$addCase = static function (string $name, string $source, array $children) use (&$cases): void {
    $cases[$name] = [
        'source' => $source,
        'children' => $children,
    ];
};

foreach ([':' => 'colon', '~' => 'tilde'] as $marker => $markerName) {
    for ($level = 1; $level <= 6; $level++) {
        $text = ucfirst($markerName) . ' definition heading ' . $level;
        $addCase(
            "{$markerName} marker ATX heading level {$level}",
            $marker . ' ' . str_repeat('#', $level) . ' ' . $text,
            [['type' => 'heading', 'level' => $level, 'text' => $text]]
        );
    }

    foreach (['---', '***', '___'] as $rule) {
        $addCase(
            "{$markerName} marker thematic break {$rule}",
            $marker . ' ' . $rule,
            [['type' => 'horizontal_rule']]
        );
    }

    foreach ([
        ['backtick php fence', '```php', 'echo 1;', '```', ['php']],
        ['tilde js fence', '~~~ js', 'const ok = true;', '~~~', ['js']],
        ['attribute python fence', '``` {.python}', 'print(1)', '```', ['python']],
        ['multiword info fence', '~~~ review source', 'payload', '~~~', ['review']],
    ] as [$label, $open, $body, $close, $classes]) {
        $addCase(
            "{$markerName} marker {$label}",
            $marker . ' ' . $open . "\n    " . $body . "\n    " . $close,
            [['type' => 'code_block', 'text' => $body, 'classes' => $classes]]
        );
    }

    foreach ([
        ['two line line block', "| first\n    | second", ['first', 'second']],
        ['lazy line block continuation', "| first\n     continuation", ['first continuation']],
        ['three line line block', "| alpha\n    | beta\n    | gamma", ['alpha', 'beta', 'gamma']],
        ['second line lazy continuation', "| alpha\n    | beta\n     gamma", ['alpha', 'beta gamma']],
    ] as [$label, $body, $lines]) {
        $addCase(
            "{$markerName} marker {$label}",
            $marker . ' ' . $body,
            [['type' => 'line_block', 'lines' => $lines]]
        );
    }

    foreach ([
        ['plain blockquote continuation', "> quote\n    continuation", 'quote continuation'],
        ['strong blockquote continuation', "> **strong** quote\n    continuation", 'strong quote continuation'],
        ['link blockquote continuation', "> [linked](/review) quote\n    continuation", 'linked quote continuation'],
        ['code blockquote continuation', "> `code` quote\n    continuation", 'code quote continuation'],
    ] as [$label, $body, $text]) {
        $addCase(
            "{$markerName} marker {$label}",
            $marker . ' ' . $body,
            [['type' => 'blockquote', 'text' => $text]]
        );
    }

    foreach ([
        ['dash bullet continuation', "- item\n    continuation", ['type' => 'bullet_list', 'items' => ['item continuation']]],
        ['plus bullet continuation', "+ item\n    continuation", ['type' => 'bullet_list', 'items' => ['item continuation']]],
        ['decimal ordered continuation', "1. item\n    continuation", ['type' => 'ordered_list', 'items' => ['item continuation'], 'start' => 1, 'style' => 'decimal', 'delimiter' => 'period']],
        ['default ordered continuation', "#. item\n    continuation", ['type' => 'ordered_list', 'items' => ['item continuation'], 'start' => 1, 'style' => 'default', 'delimiter' => 'default']],
        ['checked task continuation', "- [x] task\n    continuation", ['type' => 'bullet_list', 'items' => ['task continuation'], 'taskList' => true, 'tasks' => [true]]],
    ] as [$label, $body, $expected]) {
        $addCase(
            "{$markerName} marker {$label}",
            $marker . ' ' . $body,
            [$expected]
        );
    }

    foreach ([
        ['html comment raw block', '<!-- review -->', '<!-- review -->'],
        ['processing instruction raw block', '<?review instruction?>', '<?review instruction?>'],
        ['cdata raw block', '<![CDATA[review]]>', '<![CDATA[review]]>'],
        ['script raw block', '<script type="text/plain">review</script>', '<script type="text/plain">review</script>'],
        ['section raw block continuation', "<section>\n    body\n    </section>", "<section>\nbody\n</section>"],
    ] as [$label, $body, $html]) {
        $addCase(
            "{$markerName} marker {$label}",
            $marker . ' ' . $body,
            [['type' => 'raw_html', 'html' => $html]]
        );
    }
}

$tests = [];

foreach ($cases as $name => $case) {
    $tests['maps upstream markdown definition marker block surge ' . $name] =
        static function (TestRunner $t) use ($case, $blockSummary): void {
            $markdown = "Review term\n" . $case['source'];
            $document = (new MarkdownReader())->read($markdown);
            $definitionList = $document->children[0] ?? new AstNode('missing');
            $item = $definitionList->children[0] ?? new AstNode('missing');
            $term = $item->children[0] ?? new AstNode('missing');
            $definition = $item->children[1] ?? new AstNode('missing');

            $t->same(1, count($document->children), $markdown);
            $t->same('definition_list', $definitionList->type, $markdown);
            $t->same('definition_item', $item->type, $markdown);
            $t->same('Review term', $item->attr('term'), $markdown);
            $t->same('term', $term->type, $markdown);
            $t->same('definition', $definition->type, $markdown);
            $t->same($case['children'], array_map($blockSummary, $definition->children), $markdown);
        };
}

$tests['records markdown definition marker block surge mapped-case count'] = static function (TestRunner $t) use ($cases): void {
    $t->same(62, count($cases));
};

$tests['round trips definition marker block starts through markdown and wordpress handoff'] =
    static function (TestRunner $t): void {
        $markdown = implode("\n", [
            'Review term',
            ': # Definition Heading',
            '    Follow-up paragraph.',
            '',
            'Code term',
            ': ```php',
            '    echo 1;',
            '    ```',
            '',
            'Quote term',
            '~ > quoted',
            '    continuation',
        ]);
        $document = (new MarkdownReader())->read($markdown);
        $roundTrip = (new MarkdownReader())->read((new MarkdownWriter())->write($document));
        $blocks = (new WordPressBlockWriter())->write($roundTrip);

        $t->same(['definition_list'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same('heading', $document->children[0]->children[0]->children[1]->children[0]->type);
        $t->same('code_block', $document->children[0]->children[1]->children[1]->children[0]->type);
        $t->same('blockquote', $document->children[0]->children[2]->children[1]->children[0]->type);
        $t->same('Definition Heading', $roundTrip->children[0]->children[0]->children[1]->children[0]->attr('text'));
        $t->same('echo 1;', $roundTrip->children[0]->children[1]->children[1]->children[0]->attr('text'));
        $t->contains('<dl><dt>Review term</dt><dd><h1 id="definition-heading">Definition Heading</h1>Follow-up paragraph.</dd>', $blocks);
        $t->contains('<pre class="wp-block-code"><code class="language-php">echo 1;', $blocks);
        $t->contains('<dt>Quote term</dt><dd><blockquote><p>quoted', $blocks);
        $t->contains('continuation</p></blockquote></dd></dl>', $blocks);
    };

return $tests;
