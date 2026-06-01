# LightningCSS Media Query Range Layer Recovery Parity - 2026-06-01 06:54 UTC

Slice: `lightningcss-media-query-range-layer-parity-20260601T065457Z`

Upstream source truth:
- `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`
- `src/media_query.rs` `MediaCondition::parse_with_flags()` recovers media-condition parse errors when `ParserOptions.error_recovery` is enabled by warning and preserving an unknown token list instead of aborting the whole stylesheet.
- `src/lib.rs::test_media` includes the upstream `error_recovery_test("@media unknown(foo) {}")` case. This slice extends the existing PHP recovery mapping to nested condition-function positions reached through `not`, `and`, and `or` groups inside layered media rules.

Implemented behavior:
- `CssMinifier::findUnsupportedTopLevelConditionFunctionOffset()` now detects recoverable unsupported media condition functions in nested boolean condition contexts, not only at depth 0.
- The scanner preserves valid media feature value functions such as `calc()` in ranges because it only treats nested function tokens as condition functions when they follow condition-start, `not`, `and`, or `or` context.
- Allowed container condition functions continue to pass through without treating their inner value syntax as media/container condition functions.

Focused evidence:
- Pre-change probe on accepted base:
  - `minifyWithErrorRecovery("@layer blocks { @media (not unknown(foo)) { ... } } .ok { ... }", "layer.css")` threw `InvalidArgumentException: Media query negation must be followed by a parenthesized condition`.
- `php -l lanes/lightningcss/src/CssMinifier.php` - no syntax errors
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` - no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-recovery.php` - no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` - `1 test files, 1827 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-media-range-layer-recovery.php --self-test` - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 6554 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss` - no output

Status delta:
- Full-lane PHP assertions move from `6552` to `6554`.
- Conservative mapped denominator coverage remains `2360 / 3532` because this deepens the already represented media-query error-recovery cluster rather than adding a new upstream inventory row.

Non-overlap:
- Does not repeat accepted empty media-list/trailing comma handling, negated custom range case preservation, explicit media-type tail validation, range target fallbacks, value-first resolution conversion, resolution prefix fallback logic, media feature typed value validation, or CSS Modules/source-map/bundle/property-value/CSSOM/custom-at-rule work.

Dependency closure:
- No new support component is needed. This reuses the native PHP `CssMinifier` recovery scanner, media-query parser/minifier paths, and WordPress example self-test harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

Next task:
- Continue with non-overlapping media-query parser recovery/custom-media expansion boundaries or target-prefix interactions not covered by the current range/value/layer tests.
