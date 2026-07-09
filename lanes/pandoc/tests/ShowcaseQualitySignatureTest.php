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
    },
];
