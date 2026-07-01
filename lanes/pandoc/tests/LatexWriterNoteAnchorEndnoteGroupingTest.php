<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\LatexWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'preserves latex note anchors across grouped endnotes and duplicate labels' => static function (TestRunner $t): void {
        $text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
        $space = static fn (): AstNode => new AstNode('space');
        $note = static fn (array $attrs, string $body): AstNode => new AstNode('note', $attrs, [
            new AstNode('plain', [], [$text($body)]),
        ]);

        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Topic'),
                $space(),
                $note(['label' => 'topic'], 'Foot topic'),
                $space(),
                $text('duplicate'),
                $space(),
                $note(['noteLabel' => 'topic', 'sourceType' => 'endnote'], 'End duplicate'),
                $space(),
                $text('identifier'),
                $space(),
                $note(['identifier' => 'imported-end', 'noteType' => 'endnote'], 'Identifier end'),
                $space(),
                $text('fallback'),
                $space(),
                $note(['label' => 'bad label'], 'Generated fallback'),
            ]),
        ]);

        $latex = (new LatexWriter(['groupEndnotes' => true]))->write($document);
        $legacyConstructorLatex = (new LatexWriter(null, ['groupEndnotes' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same($latex, $legacyConstructorLatex);
        $t->contains('\protect\hypertarget{fnref-topic}{}\footnote{\protect\hypertarget{fn-topic}{}Foot topic}', $latex);
        $t->contains('\protect\hypertarget{fnref-topic-2}{}\endnote{\protect\hypertarget{fn-topic-2}{}End duplicate}', $latex);
        $t->contains('\protect\hypertarget{fnref-imported-end}{}\endnote{\protect\hypertarget{fn-imported-end}{}Identifier end}', $latex);
        $t->contains('\protect\hypertarget{fnref-4}{}\footnote{\protect\hypertarget{fn-4}{}Generated fallback}', $latex);
        $t->contains("\n\n" . '\theendnotes', $latex);
        $t->contains('% pandoc-note-anchor duplicate source=label:topic original=fn-topic resolved=fn-topic-2', $latex);

        $topicRef = strpos($latex, '\protect\hypertarget{fnref-topic}{}');
        $duplicateRef = strpos($latex, '\protect\hypertarget{fnref-topic-2}{}');
        $identifierRef = strpos($latex, '\protect\hypertarget{fnref-imported-end}{}');
        $fallbackRef = strpos($latex, '\protect\hypertarget{fnref-4}{}');
        $endnotes = strpos($latex, "\n\n" . '\theendnotes');
        $diagnostic = strpos($latex, '% pandoc-note-anchor duplicate source=label:topic');

        $t->true(is_int($topicRef) && is_int($duplicateRef) && $topicRef < $duplicateRef, 'duplicate note anchor should follow original anchor');
        $t->true(is_int($duplicateRef) && is_int($identifierRef) && $duplicateRef < $identifierRef, 'identifier endnote anchor should keep document order');
        $t->true(is_int($identifierRef) && is_int($fallbackRef) && $identifierRef < $fallbackRef, 'generated fallback anchor should keep document order');
        $t->true(is_int($endnotes) && is_int($diagnostic) && $endnotes < $diagnostic, 'duplicate diagnostics should follow grouped endnote flush');

        $t->contains('<sup id="fnref-topic" data-pandoc-note-label="topic"><a href="#fn-topic" role="doc-noteref">1</a></sup>', $blocks);
        $t->contains('<sup id="fnref-topic-2" data-pandoc-note-label="topic"><a href="#fn-topic-2" role="doc-noteref">2</a></sup>', $blocks);
        $t->contains('<li id="fn-topic" data-pandoc-note-label="topic">Foot topic <a href="#fnref-topic" class="footnote-back" role="doc-backlink" aria-label="Back to content">Back</a></li>', $blocks);
        $t->contains('<li id="fn-topic-2" data-pandoc-note-label="topic">End duplicate <a href="#fnref-topic-2" class="footnote-back" role="doc-backlink" aria-label="Back to content">Back</a></li>', $blocks);
        $t->contains('<sup id="fnref-4"><a href="#fn-4" role="doc-noteref">4</a></sup>', $blocks);
        json_encode([$latex, $blocks], JSON_THROW_ON_ERROR);
    },
];
