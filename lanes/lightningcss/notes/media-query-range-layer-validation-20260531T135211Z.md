# Media Query Range Layer Validation

Slice: `lightningcss-media-query-range-layer-parity-20260531T135211Z`

Base: `f45c4dff3200fbbe1797b337ba6f15c6b2197784`

## Source Truth

- Upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream cases: `src/lib.rs::test_media` error cases immediately after the `MediaRangeSyntax` printer-options case.
- Focused mapped behavior:
  - invalid comma media feature lists;
  - bare nesting selectors in media query lists;
  - invalid min-/range values such as `min-width: hi`, `width >= hi`, `width >= 2/1`, and interval endpoints with bare identifiers;
  - invalid range feature names such as `min-width` in range position and `scan` comparisons;
  - invalid discrete feature range syntax such as `prefers-color-scheme = dark`;
  - invalid `grid` feature values outside `0` and `1`;
  - unsupported top-level condition functions such as `unknown(foo)`;
  - empty condition brackets, including `screen and ()`.

## Native Behavior

- `MediaQueryParser` validates media query range values before minifying or lowering them, so invalid range syntax is rejected instead of being normalized into plausible CSS.
- Layered block CSS uses the same parser path, so `@layer blocks { @media (min-width: hi) { ... } }` is rejected before shipping.
- Existing non-media call sites remain supported: import `layer(...)` and `supports(...)` modifiers, container value-first `calc(...) <= height` normalization, custom-media import tails, and styled-jsx placeholder recovery.
- `wordpress-media-layer-minifier.php --self-test` now checks both valid layered media minification and invalid layered media rejection.

## Verification

- `php -l lanes/lightningcss/src/CssMinifier.php`
- `php -l lanes/lightningcss/src/MediaQueryParser.php`
- `php -l lanes/lightningcss/tests/MediaQueryParserTest.php`
- `php -l lanes/lightningcss/examples/wordpress-media-layer-minifier.php`
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php`
  - Result: `1 test files, 63 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/CssMinifierTest.php lanes/lightningcss/tests/CustomMediaTransformerTest.php lanes/lightningcss/tests/NestingTransformerTest.php`
  - Result: `4 test files, 945 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 1641 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-media-layer-minifier.php --self-test`
  - Result: exited 0 and emitted compact layered block CSS.
- `git diff --check -- lanes/lightningcss`
  - Result: passed.

## Coverage Delta

- PHP assertion delta: `+22` (`1619 -> 1641`).
- Conservative mapped coverage delta: `+16` (`1164 / 3532 -> 1180 / 3532`) for the focused upstream `src/lib.rs::test_media` invalid media query cluster.

## Non-overlap

This avoids repeating accepted media range target-threshold fallbacks, media range include/exclude feature flags, resolution media-query prefixes, cascade-layer merge/minifier behavior, custom-media import-tail scanner rework, bundle import-prelude diagnostics, CSS Modules composes delimiter validation, and CSSOM shorthand behavior.

## Dependency Closure

No new support component is needed. The slice reuses the existing native `MediaQueryParser`, `CssMinifier`, import scanner, container-query minifier, custom-media transformer, and nesting recovery paths. No upstream binary, browser service, parser generator, or external CSS engine is required.
