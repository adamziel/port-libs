# Media Query Range Layer Parity 2026-06-01 09:51Z

Slice: `lightningcss-media-query-range-layer-parity-20260601T095151Z`

Base: `c4086662a04e6ef1ef746773f2a19994bf04a926`

## Source truth

- Pinned upstream manifest: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream `src/values/calc.rs` parses and serializes `round()`, `rem()`, `mod()`, `hypot()`, and `abs()` as math functions.
- Upstream `src/lib.rs::test_calc` includes focused declaration parity for these functions, including:
  - `round(22px, 5px)` -> `20px`
  - `round(up, 22px, 5px)` -> `25px`
  - `rem(18px, 5px)` -> `3px`
  - `mod(18px, 5px)` -> `3px`
  - `hypot(1px, 2px)` comparable-unit folding
  - `abs(-1px)` -> `1px`

## Delta

- `MediaQueryParser` now accepts and folds comparable advanced CSS math functions in media range values before range fallback lowering.
- Legacy range fallbacks inside `@layer` now lower rounded media intervals such as `round(22px, 5px) <= width <= round(up, 22px, 5px)` to `(min-width:20px) and (max-width:25px)`.
- Mixed-unit advanced math functions remain unresolved but valid for length media values, e.g. `round(22px, 5vw)` serializes through fallback as `round(22px,5vw)`.
- Unsupported typed media values remain rejected; `resolution >= round(2dppx, 1dppx)` is still invalid.
- WordPress media range examples now include the rounded range fallback smoke, and the existing range example expectation was refreshed for unitless math lowered through a length context.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php`
  - `1 test files, 559 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 1189 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 7270 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-media-calc-range-layer-prefixer.php --self-test`
  - pass
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test`
  - pass

## Non-overlap

This does not repeat accepted media range normalization, negated range fallback, decimal/scientific range serialization, env() range values, ratio math range lowering, resolution x/dppx prefix clones, include/exclude target flags, invalid condition-function guards, CSS Modules, CSSOM, bundle/import graph, source-map, color/property-value, or target-prefix browser-boundary slices. It only extends media range/layer lowering to advanced CSS math functions already represented in upstream calc behavior.

## Dependency closure

No new support component is needed. The slice reuses the existing native PHP media query parser, math-value comparators, range fallback lowering, target prefixer, and example self-test harness.

## Next

Remaining media-query work should focus on unmapped parser recovery or CSSOM/import graph interactions, not another range/layer math-function variant unless upstream adds a new unsupported function family.
