<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@media (prefers-color-scheme: dark) {
  .wp-block-card {
    background: black;
    color: white;
  }
}
CSS;

$result = (new CustomAtRuleTransformer())->transform($css, [], [
    'Rule' => [
        'media' => static function (array $media): ?array {
            $query = $media['value']['query']['mediaQueries'][0] ?? null;
            $condition = is_array($query) ? ($query['condition'] ?? null) : null;
            $feature = is_array($condition) ? ($condition['value'] ?? null) : null;
            if (
                !is_array($feature)
                || ($feature['type'] ?? null) !== 'plain'
                || ($feature['name'] ?? null) !== 'prefers-color-scheme'
                || ($feature['value']['value'] ?? null) !== 'dark'
            ) {
                return null;
            }

            $clonedRules = [];
            foreach ($media['value']['rules'] as &$rule) {
                if (($rule['type'] ?? null) !== 'style') {
                    continue;
                }

                $darkSelectors = [];
                foreach ($rule['value']['selectors'] as &$selector) {
                    $darkSelectors[] = [
                        ['type' => 'type', 'name' => 'html'],
                        ['type' => 'attribute', 'name' => 'theme', 'operation' => ['operator' => 'equal', 'value' => 'dark']],
                        ['type' => 'combinator', 'value' => 'descendant'],
                        ...$selector,
                    ];
                    array_unshift(
                        $selector,
                        ['type' => 'type', 'name' => 'html'],
                        [
                            'type' => 'pseudo-class',
                            'kind' => 'not',
                            'selectors' => [[
                                ['type' => 'attribute', 'name' => 'theme', 'operation' => ['operator' => 'equal', 'value' => 'light']],
                            ]],
                        ],
                        ['type' => 'combinator', 'value' => 'descendant']
                    );
                }
                unset($selector);

                $clone = $rule;
                $clone['value']['selectors'] = $darkSelectors;
                $clonedRules[] = $clone;
            }
            unset($rule);

            return [$media, ...$clonedRules];
        },
    ],
]);

if (($argv[1] ?? null) === '--self-test') {
    $expected = '@media (prefers-color-scheme:dark){html:not([theme=light]) .wp-block-card{background:#000;color:#fff}}html[theme=dark] .wp-block-card{background:#000;color:#fff}';
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected custom at-rule attribute selector output:\n{$result}\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
