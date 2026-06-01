# LightningCSS Media Query Unresolved `sign()` Range/Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260601T135909Z`

Base accepted HEAD: `bb0d0539e37bd38885b4e91058393f13bda6b370`

## Source Truth

- Pinned upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Pinned upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Targeted upstream native-addon probes showed:
  - `@media (width >= sign(calc(1em + 2px)))` minifies to `(width>=sign(1em + 2px))`.
  - Firefox 60 lowering emits `(min-width:sign(1em + 2px))`.
  - `sign(max(1em, 2px))`, unknown feature names, and custom dashed feature names follow the same unresolved length-like serialization.
  - Known ratio/number/resolution feature values with unresolved length-like `sign()` remain invalid, as do `sign(var(...))`, `sign(env(...))`, percent values, and resolution units.

## Red-First

Before the patch, PHP rejected the focused unresolved `sign()` cases with `Invalid media query range value`:

- `(width >= sign(calc(1em + 2px)))`
- `(width >= sign(max(1em, 2px)))`
- `(theme-breakpoint >= sign(calc(1em + 2px)))`
- `(--wp-breakpoint >= sign(calc(1em + 2px)))`

## Implementation

- `MediaQueryParser` now admits unresolved `sign()` only for length-like media range values on `length` and `unknown` feature types.
- The parser serializes `sign(calc(1em + 2px))` as `sign(1em + 2px)` and compacts nested function commas for `sign(max(1em, 2px))`.
- Existing folded numeric `sign()` behavior is preserved for ratio/number/unitless cases.
- Invalid unresolved `sign()` guards are preserved for typed ratio/resolution/number features, `var()`, `env()`, percentages, and resolution units.
- The WordPress media range/layer example now covers legacy Firefox fallback output and the unresolved-sign invalid guard.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php`
  - `1 test files, 670 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test`
  - passed
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 8176 assertions, 0 failures`
- Final handoff verification also includes changed PHP lint and `git diff --check -- lanes/lightningcss`.

## Status Delta

- `phpPass`: `8160 -> 8176`
- Mapped coverage remains conservatively unchanged at `2393 / 3532`; this deepens the already represented media-query range/layer cluster.

## Non-Overlap

This slice does not repeat the accepted resolution x-unit media serialization cluster. It implements the unresolved mixed-unit `sign()` follow-up explicitly left open by the prior media-query range/layer note.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP media-query parser, minifier, target fallback lowering, and example smoke path.

## Follow-Up

Full upstream Rust, Node, and WASM LightningCSS runners remain unexecuted in this isolated micro-slice.
