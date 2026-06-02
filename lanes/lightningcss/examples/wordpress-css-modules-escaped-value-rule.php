<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.card {
  composes: base;
  composes: wp-block-card from global;
  color: red;
}

@v\61 lue compact: (max-width: 37.4375em);

.base {
  color: blue;
}
CSS;

try {
    (new CssModulesTransformer())->transform($css, [
        'hash' => 'BlockA',
    ]);
    $actual = 'accepted';
} catch (InvalidArgumentException $exception) {
    $actual = $exception->getMessage();
}

$expected = 'The @value rule is deprecated';

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules escaped @value output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual . PHP_EOL;
