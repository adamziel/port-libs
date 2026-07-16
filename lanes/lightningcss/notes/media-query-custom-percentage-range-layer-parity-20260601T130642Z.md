# Custom Media Percentage Range Layer Parity

Micro-slice: `lightningcss-media-query-range-layer-parity-20260601T130642Z`
Base accepted HEAD: `96d5510e066bd7782f01bbae271bcdda6b59ec3e`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream source read: `src/media_query.rs` `MediaFeatureValue::parse_unknown` accepts ratio, number, length, resolution, `env()`, and identifiers for unknown media features, but does not accept standalone percentage tokens or math functions containing percentage tokens.
- Upstream native addon oracle against `lightningcss.linux-x64-gnu.node` rejected:
  - `@media (theme-breakpoint >= 50%) {.a{color:yellow}}`
  - `@media (--wp-breakpoint >= 50%) {.a{color:yellow}}`
  - `@layer blocks { @media (50% <= theme-breakpoint <= 75%) {.a{color:yellow}} }`
  - `@media (theme-breakpoint >= max(10%, 20%)) {.a{color:yellow}}`

## Red-First Gap

Before this change, the PHP media-query parser accepted custom and unknown media feature percentages as strict range values:

```text
(theme-breakpoint >= 50%) => (theme-breakpoint>=50%)
(--wp-breakpoint >= 50%) => (--wp-breakpoint>=50%)
(50% <= theme-breakpoint <= 75%) => (50%<=theme-breakpoint<=75%)
```

That diverged from upstream and also allowed invalid custom percentage ranges through layer-wrapped minification and target-prefix fallback paths.

## Implementation

- Tightened strict unknown/custom media range validation to reject percentage tokens, including percentage-bearing math functions.
- Kept error-recovery mode permissive so minifier recovery can preserve malformed CSS where existing recovery tests expect it.
- Added focused invalid parser assertions for custom feature range, custom property feature range, chained range, equality value, and percentage math-function range forms.
- Added layer-wrapped invalid media assertions and a WordPress-relevant target-prefix example guard.

## Verification

```text
php -l lanes/lightningcss/src/MediaQueryParser.php
No syntax errors detected in lanes/lightningcss/src/MediaQueryParser.php

php -l lanes/lightningcss/tests/MediaQueryParserTest.php
No syntax errors detected in lanes/lightningcss/tests/MediaQueryParserTest.php

php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php

php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php
1 test files, 627 assertions, 0 failures

php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php
1 test files, 2010 assertions, 0 failures

php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php
1 test files, 1298 assertions, 0 failures

php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/CssMinifierTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php
3 test files, 3935 assertions, 0 failures

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 7977 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test
passed with the custom percentage range guard returning invalid-media-query

php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json OK\n";'
lane-status json OK

git diff --check -- lanes/lightningcss
passed with no output
```

## Status Delta

- Full LightningCSS PHP lane evidence moves from `13 files / 7963 assertions / 0 failures` to `13 files / 7977 assertions / 0 failures`.
- `lane-status.json` `phpPass` moves from `7963` to `7977`.
- Conservative mapped coverage remains `2392 / 3532`; this deepens the existing media-query range/layer behavior cluster rather than claiming a new upstream denominator row.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `MediaQueryParser`, existing minifier recovery path, `TransitionPrefixer` target fallback path, and the existing WordPress media range/layer example harness. The local upstream native addon was used only as an oracle.

## Non-Overlap

This does not repeat accepted scientific-notation ranges, environment variable ranges, typed length/resolution ranges, explicit `not` recovery, resolution `x` unit serialization, range target fallbacks, import media range tails, custom feature case-sensitivity, or layer range normalization. It is limited to strict upstream rejection of percentage tokens in unknown/custom media feature values across direct parser, layer minifier, and target-prefix fallback entry points.

## Next Task

Continue with non-overlapping media-query parity such as additional custom media parser recovery boundaries, or pivot to source-map, bundle/import graph, CSSOM, CSS Modules, custom at-rule, property/value, selector, or target-prefix behavior if another worker owns the remaining media range gaps.
