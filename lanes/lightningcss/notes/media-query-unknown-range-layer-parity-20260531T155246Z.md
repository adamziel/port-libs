# LightningCSS Unknown Media Range Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260531T155246Z`

## Source Truth

- Upstream: `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine source: `src/media_query.rs`.
- Evidence:
  - `MediaFeatureName::value_type` returns `MediaFeatureType::Unknown` for custom and unknown media feature names.
  - `MediaFeatureType::allows_ranges()` returns true for `Unknown`.
  - `MediaFeatureValue::parse_unknown` accepts ratio, number, length, resolution, environment variable, and ident values.
  - `QueryFeature::parse_value_first` rejects interval ranges whose two comparison operators point in opposite directions.

## Red-First Probe

Before implementation, the focused PHP gate failed on the new upstream-backed cases:

```bash
php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php
```

Result: `2 test files, 425 assertions, 3 failures`.

Failures:

- Unknown media feature ranges such as `(theme-breakpoint >= 2)` were rejected.
- Mixed-direction intervals such as `(100px < width > 200px)` were accepted.
- Layered target fallback lowering rejected unknown ranges before prefixing.

## Implementation

- `MediaQueryParser` now separates known media feature typing from upstream `Unknown` media feature typing.
- Unknown/custom media features accept range syntax and single-token unknown values, including numbers, ratios, lengths, resolutions, `env(...)`, `var(...)`, and identifiers.
- Known ident/boolean features such as `scan`, `prefers-color-scheme`, and `grid` still reject range syntax.
- Legacy `min-` / `max-` colon syntax is only treated as a range for known range-capable features; unknown `min-*` feature names stay plain features, matching upstream parsing.
- Interval comparisons now reject mixed directions before minification or layered target fallback lowering.
- The WordPress media range layer example now covers custom block-theme breakpoint media features and mixed-interval rejection without Node.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `2 test files, 438 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 2039 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
  - Result: exits `0` and emits expected layered media range fallback output plus two `invalid-media-query` guards.
- `php -l lanes/lightningcss/src/MediaQueryParser.php && php -l lanes/lightningcss/tests/MediaQueryParserTest.php && php -l lanes/lightningcss/tests/TransitionPrefixerTest.php && php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
  - Result: no syntax errors.
- `php -r '$files=["lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json","lanes/lightningcss/lane-status.json"]; foreach ($files as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file, " ok\n"; }'`
  - Result: both JSON files decode successfully.
- `git diff --check -- lanes/lightningcss`
  - Result: passes.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted typed media ranges, invalid known range validation, resolution vendor-prefix emission, resolution `x` unit serialization, include/exclude media feature flags, cascade-layer merging, custom-media import-tail scanner behavior, CSS Modules, source-map, bundler, CSSOM, target alpha-color fallback, or custom at-rule visitor slices. It only closes upstream `Unknown` media feature range parsing/fallback behavior and mixed interval rejection.

## Dependency Closure

No new support component is needed. This reuses the native `MediaQueryParser`, `TransitionPrefixer`, `CssMinifier`, and existing lane-local CSS scanner/minifier paths. No upstream binary, browser service, parser generator, or external CSS engine is required.
