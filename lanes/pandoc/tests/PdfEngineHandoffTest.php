<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    return (new MarkdownReader())->read(<<<'MARKDOWN'
---
title: PDF Handoff Packet
author: [Migration Desk, Content Reviewer]
date: 2026-06-04
---

Reviewer formula $E = mc^2$ keeps raw source \cite{packet}.
MARKDOWN);
};

return [
    'plans latex pdf engine handoff without executing a tex engine' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'xelatex',
            'outputPath' => 'build/review-packet.pdf',
            'engineOptions' => ['-file-line-error'],
        ]);

        $t->same('pandoc-pdf-engine-handoff', $plan['kind']);
        $t->same('pdf', $plan['target']);
        $t->same(false, $plan['willExecute']);
        $t->same('xelatex', $plan['engine']);
        $t->same('latex', $plan['engineFamily']);
        $t->same('latex', $plan['intermediateFormat']);
        $t->same('build/review-packet.tex', $plan['sourceFile']);
        $t->same('build/review-packet.pdf', $plan['outputFile']);
        $t->same(['xelatex', '-halt-on-error', '-interaction=nonstopmode', '-file-line-error', 'build/review-packet.tex'], $plan['argv']);
        $t->contains('Reviewer formula $E = mc^2$ keeps raw source \\cite{packet}.', (string) $plan['sourceBytes']);
        $t->same(hash('sha256', (string) $plan['sourceBytes']), $plan['sourceSha256']);
        $t->same('PDF Handoff Packet', $plan['metadata']['title']);
        $t->same(['Migration Desk', 'Content Reviewer'], $plan['metadata']['author']);
        $t->same('2026-06-04', $plan['metadata']['date']);
        $t->contains('pdf-engine-not-executed', implode(',', $plan['diagnostics']));
        $t->contains('intermediate-source-rendered:latex', implode(',', $plan['diagnostics']));
    },

    'maps pandoc pdf engine families to bounded intermediate handoff argv' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $typst = $handoff->plan($document(), [
            'engine' => '/usr/local/bin/typst',
            'outputPath' => 'pdf/review.pdf',
            'source' => '#set page(width: 8.5in, height: 11in)',
            'engineOptions' => ['--font-path=fonts'],
        ]);
        $weasy = $handoff->plan($document(), [
            'engine' => 'weasyprint',
            'outputPath' => 'review.pdf',
            'source' => '<!doctype html><title>Review</title>',
        ]);
        $prince = $handoff->plan($document(), [
            'engine' => 'prince',
            'outputPath' => 'review.pdf',
            'source' => '<!doctype html><title>Review</title>',
        ]);
        $roff = $handoff->plan($document(), [
            'engine' => 'pdfroff',
            'outputPath' => 'review.pdf',
            'source' => '.TL' . "\n" . 'Review',
        ]);
        $context = $handoff->plan($document(), [
            'engine' => 'context',
            'outputPath' => 'context/review.pdf',
        ]);

        $t->same('typst', $typst['engine']);
        $t->same('typst', $typst['intermediateFormat']);
        $t->same(['--font-path=fonts'], $typst['engineOptions']);
        $t->same(['/usr/local/bin/typst', 'compile', '--font-path=fonts', 'pdf/review.typ', 'pdf/review.pdf'], $typst['argv']);
        $t->same('html', $weasy['engineFamily']);
        $t->same('html5', $weasy['intermediateFormat']);
        $t->same(['weasyprint', 'review.html', 'review.pdf'], $weasy['argv']);
        $t->same(['prince', 'review.html', '-o', 'review.pdf'], $prince['argv']);
        $t->same('roff', $roff['engineFamily']);
        $t->same('ms', $roff['intermediateFormat']);
        $t->same(['pdfroff', '-o', 'review.pdf', 'review.ms'], $roff['argv']);
        $t->same('context', $context['intermediateFormat']);
        $t->same(null, $context['sourceBytes']);
        $t->contains('intermediate-writer-pending:context', implode(',', $context['diagnostics']));
    },

    'fake runner validates staged source and pdf-like output bytes' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'lualatex', 'outputPath' => 'review.pdf']);
        $pdfBytes = "%PDF-1.7\n% fake bounded handoff\n%%EOF\n";
        $result = $handoff->fakeRun($plan, [
            'stdout' => 'fake runner accepted argv',
            'files' => [
                'review.tex' => (string) $plan['sourceBytes'],
                'review.pdf' => $pdfBytes,
            ],
        ]);

        $t->same(true, $result['ok']);
        $t->same('ok', $result['status']);
        $t->same(null, $result['reason']);
        $t->same('lualatex', $result['engine']);
        $t->same(strlen($pdfBytes), $result['bytes']);
        $t->same(hash('sha256', (string) $plan['sourceBytes']), $result['sourceSha256']);
        $t->same(hash('sha256', $pdfBytes), $result['pdfSha256']);
        $t->same('fake runner accepted argv', $result['stdout']);
        $t->contains('fake-runner-no-execution', implode(',', $result['diagnostics']));
    },

    'fake runner reports missing output non pdf bytes source mismatch and engine failures' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'pdflatex', 'outputPath' => 'review.pdf']);

        $missing = $handoff->fakeRun($plan);
        $nonPdf = $handoff->fakeRun($plan, ['files' => ['review.pdf' => '<html>not a pdf</html>']]);
        $mismatch = $handoff->fakeRun($plan, [
            'files' => [
                'review.tex' => 'changed source',
                'review.pdf' => "%PDF-1.7\n%%EOF\n",
            ],
        ]);
        $engineFailure = $handoff->fakeRun($plan, [
            'exitCode' => 2,
            'stderr' => 'missing font',
            'files' => ['review.tex' => (string) $plan['sourceBytes']],
        ]);

        $t->same(false, $missing['ok']);
        $t->same('missing-pdf-output', $missing['reason']);
        $t->same('non-pdf-output', $nonPdf['reason']);
        $t->same('source-mismatch', $mismatch['reason']);
        $t->same('engine-exit-2', $engineFailure['reason']);
        $t->same('missing font', $engineFailure['stderr']);
    },

    'rejects unsafe pdf handoff engine path and option inputs' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();

        $t->throws(InvalidArgumentException::class, static fn (): array => $handoff->plan($document(), ['engine' => 'unknown-pdf-engine']));
        $t->throws(InvalidArgumentException::class, static fn (): array => $handoff->plan($document(), ['outputPath' => '/tmp/review.pdf']));
        $t->throws(InvalidArgumentException::class, static fn (): array => $handoff->plan($document(), ['sourcePath' => '../review.tex']));
        $t->throws(InvalidArgumentException::class, static fn (): array => $handoff->plan($document(), ['engineOptions' => ["bad\0option"]]));
        $t->throws(InvalidArgumentException::class, static fn (): array => $handoff->plan(new AstNode('paragraph')));
    },
];
