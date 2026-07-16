# LightningCSS Media Query Range Layer Sign Parity - 2026-06-01 12:29 UTC

Slice: `lightningcss-media-query-range-layer-parity-20260601T122951Z`

Base accepted HEAD: `bc90f87db2ed4ad7ae3d007cb6eabda51a9348d1`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine source reads used `git show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/media_query.rs` and `src/lib.rs::test_media` from the upstream cache because the cache worktree has local compatibility edits.
- Upstream native-addon probes at the pinned cache showed concrete `sign()` media range values fold to unitless `-1`, `0`, or `1` inside `@layer`, and legacy `MediaRangeSyntax` fallbacks lower those folded values to `min-` / `max-` features.
- The same probes showed concrete unitless `abs()`, `round()`, `rem()`, `mod()`, and `hypot()` results remain unitless in length-feature ranges instead of gaining `px`.
- Upstream rejects typed mismatches such as `width >= sign(10%)`, `width >= sign(10dppx)`, `color >= sign(10)`, `resolution >= sign(10dppx)`, and `aspect-ratio >= sign(10px)`.

## Red-First Evidence

Before this slice, PHP rejected concrete `sign()` range values and serialized folded unitless math-function results like `abs(-2)` and `hypot(3, 4)` as length values with appended `px` in width range fallbacks. That diverged from upstream layered media range output such as:

```text
@layer blocks{@media (width>=1){...}}
@layer blocks{@media (min-width:1){...}}
@layer blocks{@media (min-width:5){...}}
```

## Implementation

- `MediaQueryParser` now folds concrete `sign()` arguments to unitless `-1`, `0`, or `1` for compatible length, number, unknown/custom, and ratio media features.
- The range parser now rejects invalid typed `sign()` operands instead of passing unresolved concrete mismatches through.
- Unitless concrete `abs()`, `round()`, `rem()`, `mod()`, and `hypot()` range values now stay unitless when upstream does.
- Existing legacy lowering now carries these folded values through layered `@media` fallbacks and vendor numeric/range feature aliases.
- `wordpress-media-range-layer-prefixer.php` now includes layered block-query examples for `sign()`, unitless `abs()`, unitless `hypot()`, custom unknown ranges, and invalid percentage `sign()` guards.

## Verification

- `php -l lanes/lightningcss/src/MediaQueryParser.php` - no syntax errors.
- `php -l lanes/lightningcss/tests/MediaQueryParserTest.php` - no syntax errors.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` - no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php` - no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php` - `2 test files, 1896 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test` - exited `0`.
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 7815 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` - no whitespace errors.

Root harness was not run; this was an isolated LightningCSS micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `7771 -> 7815`.
- Conservative mapped coverage remains `2392 / 3532`; this deepens the already represented upstream media-query range/layer cluster.
- Full upstream Rust/Node/WASM runners were not executed.

## Non-Overlap

This slice does not repeat accepted numeric calc range, percentage length range, resolution x/equality range, unknown range, negated range, import media range, condition-function validation, or target-prefix-only media fallback slices. It is limited to concrete `sign()` folding and unitless concrete math-function serialization through layered media range fallbacks.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `MediaQueryParser`, `TransitionPrefixer`, `CssMinifier`, focused lane test harness, and the existing WordPress media range layer example.

## Follow-Up

Upstream also preserves unresolved mixed-unit `sign()` expressions such as `sign(calc(1em + 2px))`; this slice intentionally ports the concrete folded parity cluster and leaves unresolved mixed-unit sign serialization for a later media-query math expression slice.
