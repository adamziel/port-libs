<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkScorer;
use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'chunks benchmark text like upstream scoring' => static function (TestRunner $t): void {
        $scorer = new BenchmarkScorer();

        $t->same([], $scorer->chunkText('short text'));
        $t->same(
            ['WordPress PDF import paragraph'],
            $scorer->chunkText('WordPress PDF import paragraph')
        );
    },
    'scores exact extracted text as perfect overlap' => static function (TestRunner $t): void {
        $text = 'WordPress migration exports need stable paragraph text for imported document archives.';

        $t->same(1.0, (new BenchmarkScorer())->scoreText($text, $text));
    },
    'applies rapidfuzz-style indel ratio cutoff for fuzzy benchmark overlap' => static function (TestRunner $t): void {
        $scorer = new BenchmarkScorer();

        $t->same(75.0, $scorer->ratio('abcd', 'abxd'));
        $t->same(0.0, $scorer->ratio('abcd', 'wxyz', 30.0));
    },
    'scores WordPress import output against expected clean content' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-wrapped-content.pdf');
        $t->true(is_string($fixture), 'Fixture should be readable');

        $lines = (new PdfTextExtractor())->extractTextLines($fixture);
        array_shift($lines);
        $markdown = (new MarkdownPostProcessor())->mergeLines($lines);
        $reference = 'Clean hyphenated paragraphs keep WordPress imports readable.';

        $t->same(1.0, (new BenchmarkScorer())->scoreText($markdown, $reference));
    },
    'scores committed upstream multicolcnn surrogate pair above threshold' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/upstream-multicolcnn-surrogate.php';
        $score = (new BenchmarkScorer())->scoreText(
            $fixture['markerExcerpt'],
            $fixture['referenceExcerpt'],
            $fixture['chunkLength'],
        );

        $t->same('multicolcnn.pdf', $fixture['document']);
        $t->same('committed-nougat-output-surrogate', $fixture['referenceKind']);
        $t->true($score >= $fixture['scoreThreshold'], 'Upstream-derived surrogate score did not clear threshold: ' . $score);
    },
    'scores committed upstream switch transformer surrogate pair above upstream threshold' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/upstream-switch-transformers-surrogate.php';
        $score = (new BenchmarkScorer())->scoreText(
            $fixture['markerExcerpt'],
            $fixture['referenceExcerpt'],
            $fixture['chunkLength'],
        );

        $t->same('switch_trans.pdf', $fixture['document']);
        $t->same('committed-nougat-output-surrogate', $fixture['referenceKind']);
        $t->true($score > 0.40, 'Switch surrogate should clear the upstream CI text threshold: ' . $score);
        $t->true($score >= $fixture['scoreThreshold'], 'Upstream-derived switch surrogate score did not clear threshold: ' . $score);
    },
    'scores committed upstream thinkpython surrogate pair above threshold' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/upstream-thinkpython-surrogate.php';
        $score = (new BenchmarkScorer())->scoreText(
            $fixture['markerExcerpt'],
            $fixture['referenceExcerpt'],
            $fixture['chunkLength'],
        );

        $t->same('thinkpython.pdf', $fixture['document']);
        $t->same('committed-nougat-output-surrogate', $fixture['referenceKind']);
        $t->true($score >= $fixture['scoreThreshold'], 'Upstream-derived Think Python surrogate score did not clear threshold: ' . $score);
    },
    'scores committed upstream thinkos surrogate pair above threshold' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/upstream-thinkos-surrogate.php';
        $score = (new BenchmarkScorer())->scoreText(
            $fixture['markerExcerpt'],
            $fixture['referenceExcerpt'],
            $fixture['chunkLength'],
        );

        $t->same('thinkos.pdf', $fixture['document']);
        $t->same('committed-nougat-output-surrogate', $fixture['referenceKind']);
        $t->true($score >= $fixture['scoreThreshold'], 'Upstream-derived Think OS surrogate score did not clear threshold: ' . $score);
    },
];
