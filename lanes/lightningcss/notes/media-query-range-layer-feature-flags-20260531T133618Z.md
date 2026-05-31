# Media Query Range Layer Feature Flags

Slice: `lightningcss-media-query-range-layer-parity-20260531T133618Z`

Base: `39b47e3d7563ca406403433b251e48bb5e25f850`

## Source Truth

- Upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream cases:
  - `src/lib.rs::test_media` `test_with_printer_options` for `@media (width < 256px) or (hover: none)` with `PrinterOptions.targets.include = Features::MediaRangeSyntax`, expected to lower the simple range to `not (min-width: 256px)` even without browser targets.
  - `src/targets.rs::Targets::should_compile`, where `include` forces compilation and `exclude` suppresses target-driven compilation unless the feature is explicitly included.

## Native Behavior

- `TransitionPrefixer::targetOptions()` now recognizes `include` and `exclude` entries for `MediaRangeSyntax`, `MediaIntervalSyntax`, and grouped `MediaQueries`.
- Simple range syntax can be forced or suppressed independently from browser target thresholds.
- Interval range syntax can be forced or suppressed independently from browser target thresholds.
- The focused PHP assertions exercise the behavior inside `@layer` wrappers so layered block-theme CSS preserves upstream media-range fallback placement.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `1 test files, 233 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 1529 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
  - Result: exited 0 and emitted Safari/Firefox target fallbacks plus forced feature-flag fallback output.
- `git diff --check -- lanes/lightningcss`
  - Result: passed.

## Coverage Delta

- PHP assertion delta: `+4` (`1525 -> 1529`).
- Conservative mapped coverage delta: `+1` (`1130 / 3532 -> 1131 / 3532`) for the explicit upstream `MediaRangeSyntax` include printer-options case.
- Additional `MediaIntervalSyntax` include/exclude assertions are counted inside the same media-query feature-flag cluster.

## Non-overlap

This avoids accepted media range target-threshold fallbacks, resolution media-query prefixes, cascade-layer merge/minifier behavior, custom-media import-tail scanner rework, bundle import-prelude diagnostics, CSS Modules composes delimiter validation, and CSSOM inset/shorthand behavior. The remaining media-query work is validation/recovery and broader parser parity, not another include/exclude feature-flag slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing native `CssMinifier`, `MediaQueryParser`, `TransitionPrefixer` target option parser, and scanner helpers. No upstream binary, browser service, parser generator, or external CSS engine is required.
