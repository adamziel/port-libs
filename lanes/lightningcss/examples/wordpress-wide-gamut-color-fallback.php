<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.has-muted-a98 {
  background-color: color(a98-rgb 0.44091 0.49971 0.37408);
}

.wp-block-cover.has-muted-rec2020 {
  background-color: color(rec2020 0.42210 0.47580 0.35605);
}

.wp-block-cover.has-muted-xyz {
  background-color: color(xyz-d65 0.21661 0.14602 0.59452);
}
CSS;

$expected = '.wp-block-cover.has-muted-a98{background-color:#6a805d;background-color:color(a98-rgb .44091 .49971 .37408)}.wp-block-cover.has-muted-rec2020{background-color:#728765;background-color:color(rec2020 .4221 .4758 .35605)}.wp-block-cover.has-muted-xyz{background-color:#7654cd;background-color:color(xyz .21661 .14602 .59452)}';
$actual = (new TransitionPrefixer())->prefixForTargets($css, ['chrome' => 90]);

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected prefixed CSS:\n{$actual}\n");
    exit(1);
}

echo $actual . PHP_EOL;
