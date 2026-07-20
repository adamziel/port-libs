<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);

return [
    'showcase quality signature ignores list and definition wrapper paragraphs' => static function (TestRunner $t) use ($root): void {
        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg($root . '/tools/build-pandoc-showcase.php')
            . ' --verify-quality-signature 2>&1';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        $t->same(0, $exitCode, implode("\n", $output));
        $result = json_decode(implode("\n", $output), true);
        $t->true(is_array($result), 'Expected the showcase quality verifier to return JSON.');
        $t->same(true, $result['ok'] ?? null);
        $t->same($result['baseline'] ?? null, $result['wordpress'] ?? null);
        $t->same(1.0, isset($result['score']) ? (float) $result['score'] : null);
        $t->same('pass', $result['completeGeometryStatus'] ?? null);
        $t->same('review', $result['partialGeometryStatus'] ?? null);
        $t->same('review', $result['scanGeometryStatus'] ?? null);
        $t->same(20, $result['fragmentationMetrics']['veryShortParagraphs'] ?? null);
        $t->same('review', $result['fragmentationStatus'] ?? null);
        $t->same(
            ['extract-media-pdf-image-unanchored:42'],
            $result['unanchoredMediaProblems'] ?? null
        );
        $t->same(
            ['extract-media-pdf-image-placement-unavailable:43'],
            $result['unavailableMediaProblems'] ?? null
        );
        $t->same(
            ['extract-media-pdf-image-missing-page-occurrence:pdf-image-p2-n1'],
            $result['missingPageOccurrenceProblems'] ?? null
        );
        $t->same([], $result['unimportantUnavailableMediaProblems'] ?? null);

        $renderPlan = is_array($result['pdfRenderPlanProbe'] ?? null)
            ? $result['pdfRenderPlanProbe']
            : [];
        $t->same(true, $renderPlan['ok'] ?? null);
        $t->same(97, $renderPlan['requestCount'] ?? null, 'The independent caps should retain 96 page images and the following Form request.');
        $t->same(96, $renderPlan['pageRequestCount'] ?? null);
        $t->same('pdfjs-whole-page-raster', $renderPlan['firstRequestMethod'] ?? null);
        $t->same(42, $renderPlan['formObjectAfterPages'] ?? null, 'Whole-page requests must precede optional Form crops.');
        $t->same(true, $renderPlan['coreRequestsPreserved'] ?? null, 'Static routing and anchors must not alter signed page-request fields.');
        $t->same(true, $renderPlan['stable'] ?? null, 'The mixed page/Form plan must be deterministic.');
        $t->same('First proven page-two heading.', $renderPlan['firstFollowingText'] ?? null);
        $t->same('Last proven page-three paragraph.', $renderPlan['lastPagePrecedingText'] ?? null);

        $fixtures = is_array($result['pdfRenderFixtures'] ?? null)
            ? $result['pdfRenderFixtures']
            : [];
        $vdl = is_array($fixtures['vdl'] ?? null) ? $fixtures['vdl'] : [];
        $t->same(1, $vdl['count'] ?? null);
        $t->same(1, $vdl['firstPage'] ?? null);
        $t->same(null, $vdl['firstPrecedingText'] ?? null);
        $t->same('Script formatting example p.1', $vdl['firstFollowingText'] ?? null, 'VDL page one should be inserted before its first proven text block.');

        $motograph = is_array($fixtures['motograph'] ?? null) ? $fixtures['motograph'] : [];
        $t->same(46, $motograph['count'] ?? null);
        $t->same(2, $motograph['firstPage'] ?? null);
        $t->same(47, $motograph['lastPage'] ?? null);
        $t->same(null, $motograph['firstFollowingText'] ?? null);
        $t->same($motograph['lastDocumentText'] ?? null, $motograph['firstPrecedingText'] ?? null, 'Motograph page two should follow the final proven page-one block.');
        $t->same($motograph['lastDocumentText'] ?? null, $motograph['lastPrecedingText'] ?? null, 'Motograph page forty-seven should retain the same exact page-one boundary.');

        $mineru = is_array($fixtures['mineru'] ?? null) ? $fixtures['mineru'] : [];
        $t->same(8, $mineru['count'] ?? null);
        $t->same(1, $mineru['firstPage'] ?? null);
        $t->same(8, $mineru['lastPage'] ?? null);
        $t->same(null, $mineru['firstPrecedingText'] ?? null);
        $t->same(null, $mineru['firstFollowingText'] ?? null);
        $t->same(null, $mineru['lastPrecedingText'] ?? null);
        $t->same(null, $mineru['lastFollowingText'] ?? null, 'Textless MinerU pages must append without guessed anchors.');

        foreach (['vdl' => $vdl, 'motograph' => $motograph, 'mineru' => $mineru] as $name => $fixture) {
            $t->same(true, $fixture['routingStable'] ?? null, "{$name} page requests should retain their exact sample route.");
            $t->same(true, $fixture['coreRequestsPreserved'] ?? null, "{$name} signed request fields should remain byte-for-byte stable.");
            $t->same(true, $fixture['stable'] ?? null, "{$name} page anchors should be deterministic.");
        }
    },
];
