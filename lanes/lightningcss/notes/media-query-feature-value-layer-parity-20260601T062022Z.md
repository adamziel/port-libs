# LightningCSS Media Feature Value Layer Parity - 2026-06-01 06:20 UTC

Slice: `lightningcss-media-query-range-layer-parity-20260601T062022Z`

Upstream source truth:
- `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`
- `src/media_query.rs` `MediaFeatureValue::parse_known()`, `parse_unknown()`, and `check_type()` parse known media feature values by expected type, allow `env()` as unknown, and reject mismatched plain values such as numeric `hover`, numeric `prefers-color-scheme`, or `var()` in typed media feature values.

Implemented behavior:
- `MediaQueryParser::validateDiscreteMediaFeature()` now validates non-range media feature values by known feature family instead of only special-casing `grid`.
- Identifier-valued features such as `hover`, `pointer`, `orientation`, `display-mode`, and `prefers-color-scheme` reject numeric, ratio, and length values but continue to allow identifiers and `env()`.
- Plain length, integer, number, ratio, resolution, boolean, and unknown custom feature values share the existing range-value parser constraints where they match upstream value families.
- Cascade-layer minification rejects invalid media feature values before emitting a block.

Focused evidence:
- Pre-change probe on accepted base accepted invalid values:
  - `(hover: 1) => (hover:1)`
  - `(prefers-color-scheme: 10) => (prefers-color-scheme:10)`
  - `(width: var(--theme-breakpoint)) => (width:var(--theme-breakpoint))`
- `php -l lanes/lightningcss/src/MediaQueryParser.php` - no syntax errors
- `php -l lanes/lightningcss/tests/MediaQueryParserTest.php` - no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-media-feature-value-layer.php` - no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php` - `1 test files, 481 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-media-feature-value-layer.php --self-test` - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 6462 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss` - no output

Non-overlap:
- Does not touch the accepted empty media-list/trailing comma slice, negated custom media range case preservation, explicit media-type tail validation, range target fallbacks, or resolution prefix fallback logic.

Dependency closure:
- No new support component is needed. This uses the existing native PHP media query parser and CSS minifier paths.

Next task:
- Continue with non-overlapping media query parity around parser recovery, custom media expansion boundaries, or target-prefix interactions not covered by the current range/value/layer tests.
