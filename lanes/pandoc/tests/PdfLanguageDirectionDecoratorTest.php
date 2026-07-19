<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PdfLanguageDirectionDecorator;
use PortLibs\Pandoc\WordPressBlockWriter;

$paragraph = static function (string $text, array $attrs = []): AstNode {
    return new AstNode('paragraph', array_replace($attrs, ['text' => $text]), [new AstNode('text', ['text' => $text])]);
};

return [
    'decorates Arabic Persian and Hebrew blocks with rtl without changing text' => static function (TestRunner $t) use ($paragraph): void {
        $source = [
            $paragraph('مرحبا بالعالم 2026.'),
            $paragraph('، ۱۴۰۵ گزارش فارسی'),
            $paragraph('שלום 42, עולם.'),
        ];
        $decorated = (new PdfLanguageDirectionDecorator())->decorate($source);

        foreach ($decorated as $index => $block) {
            $t->same('rtl', $block->attr('htmlAttributes')['dir'] ?? null, 'block ' . $index);
            $t->same('rtl', $block->attr('attributes')['dir'] ?? null, 'portable attr ' . $index);
            $t->same($source[$index]->attr('text'), $block->attr('text'), 'text ' . $index);
        }

        $document = new AstNode('document', [], $decorated);
        $wordpress = (new WordPressBlockWriter())->write($document);
        $t->same(3, substr_count($wordpress, '<p dir="rtl">'));
    },
    'uses the first strong character for mixed bidi punctuation and numbers' => static function (TestRunner $t) use ($paragraph): void {
        $decorated = (new PdfLanguageDirectionDecorator())->decorate([
            $paragraph('2026 — שלום (release 4)'),
            $paragraph('Release 4 — שלום'),
            $paragraph('1234 + 5678'),
            $paragraph('縦書きの日本語'),
        ]);

        $t->same('rtl', $decorated[0]->attr('htmlAttributes')['dir'] ?? null);
        $t->same(null, $decorated[1]->attr('htmlAttributes')['dir'] ?? null);
        $t->same(null, $decorated[2]->attr('htmlAttributes')['dir'] ?? null);
        $t->same(null, $decorated[3]->attr('htmlAttributes')['dir'] ?? null);
    },
    'preserves explicit direction and tagged language provenance' => static function (TestRunner $t) use ($paragraph): void {
        $decorated = (new PdfLanguageDirectionDecorator())->decorate([
            $paragraph('שלום', [
                'attributes' => ['lang' => 'he', 'dir' => 'ltr'],
                'htmlAttributes' => ['lang' => 'he', 'dir' => 'ltr'],
            ]),
            $paragraph('متن فارسی', [
                'attributes' => ['lang' => 'fa'],
                'htmlAttributes' => ['lang' => 'fa'],
            ]),
        ]);

        $t->same('ltr', $decorated[0]->attr('htmlAttributes')['dir'] ?? null);
        $t->same('he', $decorated[0]->attr('htmlAttributes')['lang'] ?? null);
        $t->same('rtl', $decorated[1]->attr('htmlAttributes')['dir'] ?? null);
        $t->same('fa', $decorated[1]->attr('htmlAttributes')['lang'] ?? null);
    },
    'leaves code ltr and decorates directional list and table descendants' => static function (TestRunner $t) use ($paragraph): void {
        $codeText = 'echo "مرحبا";';
        $listItem = new AstNode('list_item', [], [$paragraph('פריט ראשון')]);
        $cell = new AstNode('table_cell', ['text' => 'مقدار'], [new AstNode('text', ['text' => 'مقدار'])]);
        $source = [
            new AstNode('code_block', ['text' => $codeText]),
            new AstNode('bullet_list', [], [$listItem]),
            new AstNode('table', [], [new AstNode('table_body', [], [new AstNode('table_row', [], [$cell])])]),
        ];
        $decorated = (new PdfLanguageDirectionDecorator())->decorate($source);

        $t->same(null, $decorated[0]->attr('htmlAttributes')['dir'] ?? null);
        $t->same($codeText, $decorated[0]->attr('text'));
        $t->same('rtl', $decorated[1]->attr('htmlAttributes')['dir'] ?? null);
        $t->same('rtl', $decorated[1]->children()[0]->attr('htmlAttributes')['dir'] ?? null);
        $decoratedCell = $decorated[2]->children()[0]->children()[0]->children()[0];
        $t->same('rtl', $decoratedCell->attr('htmlAttributes')['dir'] ?? null);
    },
];
