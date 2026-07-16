# Custom At-Rule Transform Prelude Visitor Parity - 2026-06-01T05:26:34Z

## Upstream Source Truth

- Pinned upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/values/syntax.rs` derives `Visit` for `ParsedComponent`.
- `src/properties/transform.rs` derives `Visit` for `TransformList` and `Transform`.
- `node/ast.d.ts` exposes `ParsedComponent` variants for `transform-function` and `transform-list` with nested transform values.

This slice maps the visitor shape rather than transform minifier aliasing: custom at-rule transform preludes must run nested value visitors before the custom `Rule` visitor observes the rule prelude.

## Red-First Evidence

Before the implementation, a local probe using `@motion translateX(16px) rotate(90deg)` with `prelude: "<transform-list>"` only recorded the custom rule visitor:

```text
["prelude:translateX(16px) rotate(90deg)"]
```

The `Length` and `Angle` visitors were not reached inside transform preludes.

Baseline focused test before edits:

```text
php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
1 test files, 282 assertions, 0 failures
```

## Implementation

- `CustomAtRuleTransformer` now visits `transform-function` and `transform-list` prelude AST values.
- `translateX()` and `translateY()` length/percentage arguments reuse the existing Length/Percentage visitor path.
- `rotate()`, `rotateX()`, `rotateY()`, and `rotateZ()` angle arguments reuse the existing Angle visitor path.
- The WordPress syntax-components example now includes a transform-list custom at-rule where Length and Angle visitors rewrite the custom property value before output.

## Verification

```text
php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
1 test files, 290 assertions, 0 failures

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 6206 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-custom-at-rule-syntax-components.php --self-test
OK
```

Final lint, JSON, and whitespace gates were also run:

```text
php -l lanes/lightningcss/src/CustomAtRuleTransformer.php
php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
php -l lanes/lightningcss/examples/wordpress-custom-at-rule-syntax-components.php
php -r 'json_decode(file_get_contents("lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "OK\n";'
git diff --check -- lanes/lightningcss
```

## Status Delta

- Focused custom at-rule assertions: `282 -> 290` (`+8`).
- Full LightningCSS lane assertions: `6198 -> 6206` (`+8`).
- Conservative mapped coverage: `2359 -> 2360 / 3532`.
- Root harness: not run; isolated micro-slice.

## Non-Overlap

This does not touch the stale custom-media import-tail rework note and does not repeat accepted literal repetition, token-list, ratio, unit, length-percentage, image/url, env/var, declaration/style/media/supports, rule-exit, token-array, or nested returned-body custom at-rule clusters.

## Dependency Closure

No new support component is needed. The slice reuses the existing `CustomAtRuleTransformer` syntax parser, transform parser/serializer, and existing `Length`/`Angle` visitor normalization.

## Next Task

Continue current-base custom at-rule visitor parity on non-overlapping parser components, or move to the supervisor-prioritized source-map, CSS Modules, bundle/import graph, CSSOM, target-prefixing, media-query, selector, and property/value gaps.
