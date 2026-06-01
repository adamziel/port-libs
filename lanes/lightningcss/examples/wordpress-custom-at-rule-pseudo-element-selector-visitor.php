<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@marker list;

.wp-block-list li::before,
#toc::part(icon) {
  color: var(--wp--preset--color--accent);
}
CSS;

$seenPseudoElements = [];
$result = (new CustomAtRuleTransformer())->transform($css, [
    'marker' => [
        'prelude' => '<custom-ident>',
    ],
], [
    'Rule' => [
        'custom' => [
            'marker' => static fn (): array => [
                'type' => 'style',
                'value' => [
                    'selectors' => [
                        [
                            ['type' => 'class', 'name' => 'wp-block-list'],
                            ['type' => 'pseudo-element', 'kind' => 'marker'],
                        ],
                    ],
                    'declarations' => [
                        'declarations' => [
                            ['property' => 'color', 'raw' => 'yellow'],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'Selector' => static function (array $selector) use (&$seenPseudoElements): array {
        foreach ($selector as &$component) {
            if (($component['type'] ?? null) !== 'pseudo-element') {
                continue;
            }

            $label = (string) ($component['kind'] ?? '');
            if (isset($component['names']) && is_array($component['names'])) {
                $label .= ':' . implode('|', $component['names']);
            }
            $seenPseudoElements[] = $label;

            if (($component['kind'] ?? null) === 'before') {
                $component['kind'] = 'marker';
            }
        }
        unset($component);

        return $selector;
    },
]);

$expected = '.wp-block-list::marker{color:#ff0}.wp-block-list li::marker,#toc::part(icon){color:var(--wp--preset--color--accent)}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected pseudo-element selector visitor output:\n{$result}\n");
        exit(1);
    }
    if (!in_array('before', $seenPseudoElements, true) || !in_array('part:icon', $seenPseudoElements, true)) {
        fwrite(STDERR, "Unexpected pseudo-element visitor AST:\n" . json_encode($seenPseudoElements) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
