<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@media (hover) {
  .wp-block-card__cta {
    color: red;
  }
}

@supports (display: grid) {
  .wp-block-card {
    display: grid;
  }
}
CSS;

$visitor = CustomAtRuleTransformer::composeVisitors([
    [
        'MediaQuery' => static function (array $query): ?array {
            $condition = $query['condition'] ?? null;
            if (
                is_array($condition)
                && ($condition['type'] ?? null) === 'feature'
                && ($condition['value']['type'] ?? null) === 'boolean'
                && ($condition['value']['name'] ?? null) === 'hover'
            ) {
                return ['raw' => '(hover: hover)'];
            }

            return null;
        },
    ],
    [
        'SupportsCondition' => static function (array $condition): ?array {
            if (
                ($condition['type'] ?? null) === 'declaration'
                && ($condition['propertyId']['property'] ?? null) === 'display'
                && ($condition['value'] ?? null) === 'grid'
            ) {
                $condition['value'] = 'subgrid';

                return $condition;
            }

            return null;
        },
    ],
]);

$result = (new CustomAtRuleTransformer())->transform($css, [], $visitor);

if (($argv[1] ?? null) === '--self-test') {
    $expected = '@media (hover:hover){.wp-block-card__cta{color:red}}@supports (display:subgrid){.wp-block-card{display:grid}}';
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected custom at-rule condition visitor output:\n{$result}\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
