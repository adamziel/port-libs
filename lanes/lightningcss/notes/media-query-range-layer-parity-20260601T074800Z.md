# LightningCSS Media Query Range Layer Recovery Parity - 2026-06-01 07:48 UTC

Slice: `lightningcss-media-query-range-layer-parity-20260601T074800Z`

Upstream source truth:
- `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`
- `src/media_query.rs` `MediaCondition::parse_with_flags()` recovers invalid media conditions when `ParserOptions.error_recovery` is enabled.
- `src/media_query.rs` `QueryFeature::parse_name_first()` warns with `ParserError::InvalidMediaQuery` and preserves parsed name-first feature values when `error_recovery` is enabled and the parsed value does not match the feature's expected type. The value-first range path remains strict.

Implemented behavior:
- `MediaQueryParser::minifyList()` now has an internal recovery flag for invalid name-first media feature values.
- Strict parsing still rejects invalid typed media feature values such as `(hover: 1)` and `(min-width: hi)`.
- Recovery-mode value-first invalid ranges such as `(2/1 <= width)` still reject instead of being preserved.
- `CssMinifier::minifyWithErrorRecovery()` enables that recovery mode for `@media` preludes, preserves the layered media blocks, and emits `InvalidMediaQuery` warnings at the recovered feature locations.
- Existing unsupported media condition recovery remains intact, including newline-preserving omission of non-recoverable invalid at-rules.
- The WordPress media range recovery example now covers an invalid media feature value inside a cascade layer next to an omitted unsupported condition function and a valid range-calc rule.

Focused evidence:
- `php -l lanes/lightningcss/src/MediaQueryParser.php` - no syntax errors
- `php -l lanes/lightningcss/src/CssMinifier.php` - no syntax errors
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` - no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-recovery.php` - no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` - `1 test files, 1915 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php` - `1 test files, 497 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-media-range-layer-recovery.php --self-test` - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 6797 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss` - no output

Status delta:
- Full-lane PHP assertions move from `6793` to `6797`.
- Conservative mapped denominator coverage remains `2360 / 3532` because this deepens the already represented media-query error-recovery cluster rather than adding a new upstream inventory row.

Non-overlap:
- Does not repeat accepted empty media-list/trailing comma handling, negated custom range preservation, explicit media-type tail validation, target range fallbacks, typed value strict validation, unsupported condition-function omission, CSS Modules, source-map, bundle/import, CSSOM, custom-at-rule, property-value, or target-prefixing work.

Dependency closure:
- No new support component is needed. This reuses the native PHP media-query parser, CSS minifier recovery scanner, and lane example self-test harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

Next task:
- Continue with non-overlapping media-query parser recovery/custom-media expansion boundaries, JSON/CSSOM/source-map interactions, or target-prefix behavior not covered by the current range/value/layer tests.
