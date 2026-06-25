<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\LatexWriter;

return [
    'preserves latex note and backlink anchors with duplicate diagnostics' => static function (TestRunner $t): void {
        $text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
        $note = static fn (array $attrs, string $body): AstNode => new AstNode('note', $attrs, [
            new AstNode('plain', [], [$text($body)]),
        ]);

        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Review '),
                $note(['label' => 'review-note'], 'First'),
                $text(' then '),
                $note(['label' => 'review-note'], 'Second'),
                $text(' and '),
                $note([], 'Generated'),
            ]),
        ]);

        $latex = (new LatexWriter())->write($document);

        $t->contains('\protect\hypertarget{fnref-review-note}{}\footnote{\protect\hypertarget{fn-review-note}{}First}', $latex);
        $t->contains('\protect\hypertarget{fnref-review-note-2}{}\footnote{\protect\hypertarget{fn-review-note-2}{}Second}', $latex);
        $t->contains('\protect\hypertarget{fnref-3}{}\footnote{\protect\hypertarget{fn-3}{}Generated}', $latex);
        $t->contains('% pandoc-note-anchor duplicate source=label:review-note original=fn-review-note resolved=fn-review-note-2', $latex);
    },
    'groups latex endnotes with stable note and backlink anchors when enabled' => static function (TestRunner $t): void {
        $text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
        $note = static fn (array $attrs, string $body): AstNode => new AstNode('note', $attrs, [
            new AstNode('plain', [], [$text($body)]),
        ]);

        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('First '),
                $note([], 'Generated footnote'),
                $text(' then '),
                $note(['noteClass' => 'endnote', 'label' => 'review-end'], 'Grouped endnote'),
                $text(' after '),
                $note([], 'Trailing generated note'),
            ]),
        ]);

        $latex = (new LatexWriter(['groupEndnotes' => true]))->write($document);

        $t->contains('\protect\hypertarget{fnref-1}{}\footnote{\protect\hypertarget{fn-1}{}Generated footnote}', $latex);
        $t->contains('\protect\hypertarget{fnref-review-end}{}\endnote{\protect\hypertarget{fn-review-end}{}Grouped endnote}', $latex);
        $t->contains('\protect\hypertarget{fnref-3}{}\footnote{\protect\hypertarget{fn-3}{}Trailing generated note}', $latex);
        $t->contains("\n\n" . '\theendnotes', $latex);
    },
];
