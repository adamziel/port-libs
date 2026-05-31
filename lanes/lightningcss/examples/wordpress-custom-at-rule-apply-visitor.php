<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
--editor-card-theme {
  color: white;
  border: 1px solid #056ef0;
}

.wp-block-card {
  @apply --editor-card-theme;
}
CSS;

$defined = [];
$seenApplyPrelude = null;
$transformer = new CustomAtRuleTransformer();
$result = $transformer->transform($css, [], [
    'Rule' => [
        'style' => static function (array $rule) use (&$defined, &$seenApplyPrelude): array {
            $selector = $rule['value']['selectors'][0] ?? [];
            if (
                count($selector) === 1
                && ($selector[0]['type'] ?? null) === 'type'
                && str_starts_with((string) ($selector[0]['name'] ?? ''), '--')
            ) {
                $defined[$selector[0]['name']] = $rule['value']['declarations'];

                return ['type' => 'ignored'];
            }

            $remaining = [];
            foreach (($rule['value']['rules'] ?? []) as $child) {
                if (($child['type'] ?? null) !== 'unknown' || ($child['value']['name'] ?? null) !== 'apply') {
                    $remaining[] = $child;
                    continue;
                }

                foreach (($child['value']['prelude'] ?? []) as $token) {
                    $seenApplyPrelude = $token;
                    if (($token['type'] ?? null) !== 'dashed-ident' || !isset($defined[$token['value']])) {
                        continue;
                    }

                    $rule['value']['declarations']['declarations'] = [
                        ...($rule['value']['declarations']['declarations'] ?? []),
                        ...($defined[$token['value']]['declarations'] ?? []),
                    ];
                    $rule['value']['declarations']['importantDeclarations'] = [
                        ...($rule['value']['declarations']['importantDeclarations'] ?? []),
                        ...($defined[$token['value']]['importantDeclarations'] ?? []),
                    ];
                }
            }
            $rule['value']['rules'] = $remaining;

            return $rule;
        },
    ],
]);

$expected = '.wp-block-card{color:#fff;border:1px solid #056ef0}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected apply visitor output:\n{$result}\n");
        exit(1);
    }
    if ($seenApplyPrelude !== ['type' => 'dashed-ident', 'value' => '--editor-card-theme']) {
        fwrite(STDERR, "Unexpected apply prelude token: " . json_encode($seenApplyPrelude) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
