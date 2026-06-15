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

$cases = [
    '01 dash bullet interrupts paragraph' => [
        'markdown' => "Lead\n- dash item",
        'listType' => 'bullet_list',
        'items' => ['dash item'],
    ],
    '02 plus bullet interrupts paragraph' => [
        'markdown' => "Lead\n+ plus item",
        'listType' => 'bullet_list',
        'items' => ['plus item'],
    ],
    '03 asterisk bullet interrupts paragraph' => [
        'markdown' => "Lead\n* asterisk item",
        'listType' => 'bullet_list',
        'items' => ['asterisk item'],
    ],
    '04 one-space indented bullet interrupts paragraph' => [
        'markdown' => "Lead\n - one-space item",
        'listType' => 'bullet_list',
        'items' => ['one-space item'],
    ],
    '05 two-space indented bullet interrupts paragraph' => [
        'markdown' => "Lead\n  - two-space item",
        'listType' => 'bullet_list',
        'items' => ['two-space item'],
    ],
    '06 three-space indented bullet interrupts paragraph' => [
        'markdown' => "Lead\n   - three-space item",
        'listType' => 'bullet_list',
        'items' => ['three-space item'],
    ],
    '07 tab-padded plus bullet interrupts paragraph' => [
        'markdown' => "Lead\n+\ttab padded item",
        'listType' => 'bullet_list',
        'items' => ['tab padded item'],
    ],
    '08 spaced asterisk bullet interrupts paragraph' => [
        'markdown' => "Lead\n*    spaced item",
        'listType' => 'bullet_list',
        'items' => ['spaced item'],
    ],
    '09 unchecked task bullet interrupts paragraph' => [
        'markdown' => "Lead\n- [ ] unchecked task",
        'listType' => 'bullet_list',
        'items' => ['unchecked task'],
        'tasks' => [false],
        'taskList' => true,
    ],
    '10 checked task bullet interrupts paragraph' => [
        'markdown' => "Lead\n- [X] checked task",
        'listType' => 'bullet_list',
        'items' => ['checked task'],
        'tasks' => [true],
        'taskList' => true,
    ],
    '11 empty task marker interrupts paragraph' => [
        'markdown' => "Lead\n- [ ]",
        'listType' => 'bullet_list',
        'items' => [''],
        'tasks' => [false],
        'taskList' => true,
    ],
    '12 bullet lazy continuation remains inside interrupted list' => [
        'markdown' => "Lead\n- item\n  continuation",
        'listType' => 'bullet_list',
        'items' => ['item continuation'],
    ],
    '13 bullet two lazy continuations remain inside interrupted list' => [
        'markdown' => "Lead\n- item\n  continuation\n  finish",
        'listType' => 'bullet_list',
        'items' => ['item continuation finish'],
    ],
    '14 bullet loose continuation remains inside interrupted list' => [
        'markdown' => "Lead\n- item\n\n  second paragraph",
        'listType' => 'bullet_list',
        'items' => ['item second paragraph'],
        'loose' => true,
    ],
    '15 bullet nested bullet remains inside interrupted list' => [
        'markdown' => "Lead\n- item\n  - nested",
        'listType' => 'bullet_list',
        'items' => ['item'],
        'nested' => ['parent' => 0, 'type' => 'bullet_list', 'items' => ['nested']],
    ],
    '16 bullet four-space nested bullet remains inside interrupted list' => [
        'markdown' => "Lead\n- item\n    - nested four",
        'listType' => 'bullet_list',
        'items' => ['item'],
        'nested' => ['parent' => 0, 'type' => 'bullet_list', 'items' => ['nested four']],
    ],
    '17 bullet nested ordered list remains inside interrupted list' => [
        'markdown' => "Lead\n- item\n  1. nested ordered",
        'listType' => 'bullet_list',
        'items' => ['item'],
        'nested' => ['parent' => 0, 'type' => 'ordered_list', 'items' => ['nested ordered'], 'start' => 1],
    ],
    '18 bullet nested task list remains inside interrupted list' => [
        'markdown' => "Lead\n- item\n  - [x] nested task",
        'listType' => 'bullet_list',
        'items' => ['item'],
        'nested' => ['parent' => 0, 'type' => 'bullet_list', 'items' => ['nested task'], 'tasks' => [true], 'taskList' => true],
    ],
    '19 bullet second item stays in interrupted list' => [
        'markdown' => "Lead\n- first\n- second",
        'listType' => 'bullet_list',
        'items' => ['first', 'second'],
    ],
    '20 blank between bullet items makes interrupted list loose' => [
        'markdown' => "Lead\n- first\n\n- second",
        'listType' => 'bullet_list',
        'items' => ['first', 'second'],
        'loose' => true,
    ],
    '21 paragraph after interrupted bullet remains separate' => [
        'markdown' => "Lead\n- item\n\nAfter",
        'blocks' => ['paragraph', 'bullet_list', 'paragraph'],
        'listType' => 'bullet_list',
        'items' => ['item'],
        'after' => 'After',
    ],
    '22 thematic break after interrupted bullet remains separate' => [
        'markdown' => "Lead\n- item\n---",
        'blocks' => ['paragraph', 'bullet_list', 'horizontal_rule'],
        'listType' => 'bullet_list',
        'items' => ['item'],
    ],
    '23 strong inline survives paragraph-interrupting bullet' => [
        'markdown' => "Lead\n- **strong** item",
        'listType' => 'bullet_list',
        'items' => ['strong item'],
    ],
    '24 code inline survives paragraph-interrupting bullet' => [
        'markdown' => "Lead\n- `code` item",
        'listType' => 'bullet_list',
        'items' => ['code item'],
    ],
    '25 link inline survives paragraph-interrupting bullet' => [
        'markdown' => "Lead\n- [linked](/target) item",
        'listType' => 'bullet_list',
        'items' => ['linked item'],
    ],
    '26 escaped punctuation survives paragraph-interrupting bullet' => [
        'markdown' => "Lead\n- escaped \\* item",
        'listType' => 'bullet_list',
        'items' => ['escaped * item'],
    ],
    '27 decimal ordered period interrupts paragraph from one' => [
        'markdown' => "Lead\n1. decimal item",
        'listType' => 'ordered_list',
        'items' => ['decimal item'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
    ],
    '28 decimal ordered paren interrupts paragraph from one' => [
        'markdown' => "Lead\n1) paren item",
        'listType' => 'ordered_list',
        'items' => ['paren item'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'one_paren',
    ],
    '29 zero-padded ordered period interrupts paragraph from one' => [
        'markdown' => "Lead\n01. zero padded",
        'listType' => 'ordered_list',
        'items' => ['zero padded'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
    ],
    '30 zero-padded ordered paren interrupts paragraph from one' => [
        'markdown' => "Lead\n001) zero padded paren",
        'listType' => 'ordered_list',
        'items' => ['zero padded paren'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'one_paren',
    ],
    '31 two-space indented ordered period interrupts paragraph' => [
        'markdown' => "Lead\n  1. indented ordered",
        'listType' => 'ordered_list',
        'items' => ['indented ordered'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
    ],
    '32 three-space indented ordered paren interrupts paragraph' => [
        'markdown' => "Lead\n   1) indented ordered paren",
        'listType' => 'ordered_list',
        'items' => ['indented ordered paren'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'one_paren',
    ],
    '33 tab-padded ordered period interrupts paragraph' => [
        'markdown' => "Lead\n1.\ttab ordered",
        'listType' => 'ordered_list',
        'items' => ['tab ordered'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
    ],
    '34 ordered task item interrupts paragraph' => [
        'markdown' => "Lead\n1. [ ] ordered task",
        'listType' => 'ordered_list',
        'items' => ['ordered task'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
        'tasks' => [false],
    ],
    '35 ordered continuation remains inside interrupted list' => [
        'markdown' => "Lead\n1. item\n   continuation",
        'listType' => 'ordered_list',
        'items' => ['item continuation'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
    ],
    '36 ordered nested bullet remains inside interrupted list' => [
        'markdown' => "Lead\n1. item\n   - nested bullet",
        'listType' => 'ordered_list',
        'items' => ['item'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
        'nested' => ['parent' => 0, 'type' => 'bullet_list', 'items' => ['nested bullet']],
    ],
    '37 ordered nested ordered remains inside interrupted list' => [
        'markdown' => "Lead\n1. item\n   1. nested ordered",
        'listType' => 'ordered_list',
        'items' => ['item'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
        'nested' => ['parent' => 0, 'type' => 'ordered_list', 'items' => ['nested ordered'], 'start' => 1],
    ],
    '38 ordered second item stays in interrupted list' => [
        'markdown' => "Lead\n1. first\n2. second",
        'listType' => 'ordered_list',
        'items' => ['first', 'second'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
    ],
    '39 blank between ordered items makes interrupted list loose' => [
        'markdown' => "Lead\n1. first\n\n2. second",
        'listType' => 'ordered_list',
        'items' => ['first', 'second'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
        'loose' => true,
    ],
    '40 ordered loose continuation remains inside interrupted list' => [
        'markdown' => "Lead\n1. item\n\n   second paragraph",
        'listType' => 'ordered_list',
        'items' => ['item second paragraph'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
        'loose' => true,
    ],
    '41 paragraph after interrupted ordered list remains separate' => [
        'markdown' => "Lead\n1. item\n\nAfter",
        'blocks' => ['paragraph', 'ordered_list', 'paragraph'],
        'listType' => 'ordered_list',
        'items' => ['item'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
        'after' => 'After',
    ],
    '42 pandoc default ordered period interrupts paragraph' => [
        'markdown' => "Lead\n#. default ordered",
        'listType' => 'ordered_list',
        'items' => ['default ordered'],
        'start' => 1,
        'style' => 'default',
        'delimiter' => 'default',
    ],
    '43 pandoc default ordered paren interrupts paragraph' => [
        'markdown' => "Lead\n#) default ordered paren",
        'listType' => 'ordered_list',
        'items' => ['default ordered paren'],
        'start' => 1,
        'style' => 'default',
        'delimiter' => 'default',
    ],
    '44 pandoc numbered example interrupts paragraph' => [
        'markdown' => "Lead\n(@) example item",
        'listType' => 'ordered_list',
        'items' => ['example item'],
        'start' => 1,
        'style' => 'example',
        'delimiter' => 'two_parens',
    ],
    '45 pandoc labeled numbered example interrupts paragraph' => [
        'markdown' => "Lead\n(@review) labeled example",
        'listType' => 'ordered_list',
        'items' => ['labeled example'],
        'start' => 1,
        'style' => 'example',
        'delimiter' => 'two_parens',
    ],
    '46 pandoc upper-alpha ordered marker interrupts paragraph from one' => [
        'markdown' => "Lead\nA.  alpha item",
        'listType' => 'ordered_list',
        'items' => ['alpha item'],
        'start' => 1,
        'style' => 'upper_alpha',
        'delimiter' => 'period',
    ],
    '47 pandoc lower-alpha ordered marker interrupts paragraph from one' => [
        'markdown' => "Lead\na)  lower alpha item",
        'listType' => 'ordered_list',
        'items' => ['lower alpha item'],
        'start' => 1,
        'style' => 'lower_alpha',
        'delimiter' => 'one_paren',
    ],
    '48 pandoc upper-roman ordered marker interrupts paragraph from one' => [
        'markdown' => "Lead\nI.  roman item",
        'listType' => 'ordered_list',
        'items' => ['roman item'],
        'start' => 1,
        'style' => 'upper_roman',
        'delimiter' => 'period',
    ],
    '49 pandoc lower-roman ordered marker interrupts paragraph from one' => [
        'markdown' => "Lead\ni.  lower roman item",
        'listType' => 'ordered_list',
        'items' => ['lower roman item'],
        'start' => 1,
        'style' => 'lower_roman',
        'delimiter' => 'period',
    ],
    '50 pandoc parenthesized ordered marker interrupts paragraph from one' => [
        'markdown' => "Lead\n(1) parenthesized item",
        'listType' => 'ordered_list',
        'items' => ['parenthesized item'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'two_parens',
    ],
];

$tests = [];

foreach ($cases as $name => $case) {
    $tests['maps upstream markdown reader block/list paragraph interruption surge ' . $name] = static function (TestRunner $t) use ($case, $listItemText): void {
        $document = (new MarkdownReader())->read($case['markdown']);
        $blockTypes = array_map(static fn (AstNode $node): string => $node->type, $document->children);
        $expectedBlockTypes = $case['blocks'] ?? ['paragraph', $case['listType']];

        $t->same($expectedBlockTypes, $blockTypes);
        $t->same('Lead', $document->children[0]->attr('text'));

        $list = $document->children[1];
        $t->same($case['listType'], $list->type);
        $t->same((bool) ($case['loose'] ?? false), (bool) $list->attr('loose'));
        $t->same($case['items'], array_map($listItemText, $list->children));

        if (isset($case['start'])) {
            $t->same($case['start'], $list->attr('start'));
        }
        if (isset($case['style'])) {
            $t->same($case['style'], $list->attr('style'));
        }
        if (isset($case['delimiter'])) {
            $t->same($case['delimiter'], $list->attr('delimiter'));
        }
        if (isset($case['taskList'])) {
            $t->same($case['taskList'], (bool) $list->attr('taskList'));
        }
        if (isset($case['tasks'])) {
            $t->same($case['tasks'], array_map(
                static fn (AstNode $item): ?bool => $item->attr('taskChecked', null),
                $list->children
            ));
        }
        if (isset($case['nested'])) {
            $nestedCase = $case['nested'];
            $parent = $list->children[$nestedCase['parent']];
            $nested = null;
            foreach ($parent->children as $child) {
                if ($child->type === $nestedCase['type']) {
                    $nested = $child;
                    break;
                }
            }

            $t->true($nested instanceof AstNode, 'Expected nested list to remain inside interrupted list item');
            if ($nested instanceof AstNode) {
                $t->same($nestedCase['type'], $nested->type);
                $t->same($nestedCase['items'], array_map($listItemText, $nested->children));
                if (isset($nestedCase['start'])) {
                    $t->same($nestedCase['start'], $nested->attr('start'));
                }
                if (isset($nestedCase['taskList'])) {
                    $t->same($nestedCase['taskList'], (bool) $nested->attr('taskList'));
                }
                if (isset($nestedCase['tasks'])) {
                    $t->same($nestedCase['tasks'], array_map(
                        static fn (AstNode $item): ?bool => $item->attr('taskChecked', null),
                        $nested->children
                    ));
                }
            }
        }
        if (isset($case['after'])) {
            $t->same($case['after'], $document->children[2]->attr('text'));
        }
    };
}

$makeTableCaptionSurgeTable = static function (string $syntax, int $tableCaptionSurgeCaseNumber): string {
    $rowLabel = 'A' . str_pad((string) $tableCaptionSurgeCaseNumber, 3, '0', STR_PAD_LEFT);

    return match ($syntax) {
        'pipe' => implode("\n", [
            '| Term | Count |',
            '|:-----|------:|',
            '| ' . $rowLabel . ' | ' . $tableCaptionSurgeCaseNumber . ' |',
        ]),
        'grid' => implode("\n", [
            '+----------+-------+',
            '| Term     | Count |',
            '+==========+=======+',
            '| ' . str_pad($rowLabel, 8) . ' | ' . str_pad((string) $tableCaptionSurgeCaseNumber, 5) . ' |',
            '+----------+-------+',
        ]),
        'simple' => implode("\n", [
            sprintf('%-12s  %5s', 'Term', 'Count'),
            '------------  -----',
            sprintf('%-12s  %5s', $rowLabel, (string) $tableCaptionSurgeCaseNumber),
        ]),
    };
};

$makeTableCaptionSurgeMarkdown = static function (array $case) use ($makeTableCaptionSurgeTable): string {
    $caption = $case['marker'] . ' [' . $case['shortCaption'] . '] ' . $case['caption']
        . ' {#' . $case['id'] . ' .surge .' . $case['caseClass']
        . ' ' . $case['attributeSource'] . '}';
    $table = $makeTableCaptionSurgeTable($case['syntax'], $case['number']);

    return $case['position'] === 'leading'
        ? $caption . "\n\n" . $table
        : $table . "\n\n" . $caption;
};

$tableCaptionSurgeCases = [];
$tableCaptionSurgeCaseNumber = 1;
foreach (['pipe', 'grid', 'simple'] as $syntax) {
    foreach (['trailing', 'leading'] as $position) {
        foreach ([':', 'Table:', 'Caption:'] as $marker) {
            for ($variant = 1; $variant <= 3; $variant++) {
                $caseId = str_pad((string) $tableCaptionSurgeCaseNumber, 3, '0', STR_PAD_LEFT);
                $attributeSet = match ($variant) {
                    1 => [
                        'source' => 'data-source="upstream-' . $caseId . '" lang="en"',
                        'attributes' => ['data-source' => 'upstream-' . $caseId, 'lang' => 'en'],
                        'html' => 'data-source="upstream-' . $caseId . '" lang="en"',
                    ],
                    2 => [
                        'source' => 'role="table" title="Review table ' . $caseId . '"',
                        'attributes' => ['role' => 'table', 'title' => 'Review table ' . $caseId],
                        'html' => 'role="table" title="Review table ' . $caseId . '"',
                    ],
                    default => [
                        'source' => 'aria-label="Review table ' . $caseId . '" dir="ltr"',
                        'attributes' => ['aria-label' => 'Review table ' . $caseId, 'dir' => 'ltr'],
                        'html' => 'aria-label="Review table ' . $caseId . '" dir="ltr"',
                    ],
                };
                $tableCaptionSurgeCases[] = [
                    'number' => $tableCaptionSurgeCaseNumber,
                    'id' => 'md-table-caption-surge-' . $caseId,
                    'caseClass' => 'case-' . $caseId,
                    'attributeSource' => $attributeSet['source'],
                    'attributes' => $attributeSet['attributes'],
                    'htmlAttributeFragment' => $attributeSet['html'],
                    'syntax' => $syntax,
                    'position' => $position,
                    'marker' => $marker,
                    'caption' => 'Review *caption* ' . $caseId,
                    'shortCaption' => 'Queue ' . $caseId,
                    'rowLabel' => 'A' . $caseId,
                    'name' => sprintf(
                        'maps upstream markdown table caption surge case %s %s %s %s',
                        $caseId,
                        $syntax,
                        $position,
                        strtolower(rtrim($marker, ':')) ?: 'colon'
                    ),
                ];
                $tableCaptionSurgeCaseNumber++;
            }
        }
    }
}

foreach ($tableCaptionSurgeCases as $case) {
    $tests[$case['name']] = static function (TestRunner $t) use ($case, $makeTableCaptionSurgeMarkdown): void {
        $document = (new MarkdownReader())->read($makeTableCaptionSurgeMarkdown($case));
        $table = $document->children[0] ?? new AstNode('missing');
        $captionInlines = $table->attr('captionInlines', []);
        $shortCaptionInlines = $table->attr('shortCaptionInlines', []);
        $attributes = $table->attr('attributes', []);
        $htmlAttributes = $table->attr('htmlAttributes', []);
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(1, count($document->children));
        $t->same('table', $table->type);
        $t->same($case['caption'], $table->attr('caption'));
        $t->same($case['shortCaption'], $table->attr('shortCaption'));
        $t->same($case['id'], $table->attr('id'));
        $t->same(['surge', $case['caseClass']], $table->attr('classes'));
        $t->same($case['attributes'], $attributes);
        $t->same($case['id'], $htmlAttributes['id'] ?? null);
        $t->same('surge ' . $case['caseClass'], $htmlAttributes['class'] ?? null);
        $t->same(['text', 'emph', 'text'], array_map(static fn (AstNode $node): string => $node->type, $captionInlines));
        $t->same('caption', $captionInlines[1]->children[0]->attr('text'));
        $t->same(['text'], array_map(static fn (AstNode $node): string => $node->type, $shortCaptionInlines));
        $t->same($case['shortCaption'], $shortCaptionInlines[0]->attr('text'));
        $t->same('Term', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same($case['rowLabel'], $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same((string) $case['number'], $table->children[1]->children[0]->children[1]->attr('text'));
        $t->contains(
            ': [' . $case['shortCaption'] . '] ' . $case['caption']
                . ' {#' . $case['id'] . ' .surge .' . $case['caseClass']
                . ' ' . $case['attributeSource'] . '}',
            $markdown
        );
        $t->contains(
            '<figure class="wp-block-table" data-pandoc-short-caption="' . $case['shortCaption'] . '">',
            $blocks
        );
        $t->contains(
            '<table id="' . $case['id'] . '" class="surge ' . $case['caseClass']
                . '" ' . $case['htmlAttributeFragment'] . '>',
            $blocks
        );
        $t->contains(
            '<figcaption class="wp-element-caption">Review <em>caption</em> '
                . str_pad((string) $case['number'], 3, '0', STR_PAD_LEFT)
                . '</figcaption>',
            $blocks
        );
    };
}


return $tests;
