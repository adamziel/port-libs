# LightningCSS Media Query Range Layer Import Grammar Parity - 2026-06-01 16:03 UTC

Slice: `lightningcss-media-query-range-layer-parity-20260601T155336Z`

Base accepted HEAD: `57d8e6e255e0f04075a11bb6231bd0b9bffc3ac4`

## Source Truth

- Upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Upstream parser: `src/parser.rs` parses `@import` as source, optional `layer`, optional `supports`, then the media list.
- Upstream printer: `src/rules/import.rs` emits a space before both `supports(...)` and non-empty media query tails.
- Native binding probe confirmed:
  - `@import "a.css" layer supports(display:grid) (width >= 240px);` -> `@import "a.css" layer supports(display:grid) (min-width:240px);`
  - `@import "a.css" supports(display:grid) layer;` preserves bare `layer` as a media type.
  - `@import "a.css" supports(display:grid) layer(foo);` errors with `Unexpected token Function("layer")`.
  - duplicate function modifiers such as `layer(foo) layer(bar)` and `supports(...) supports(...)` error as unexpected tokens.

## Red-First Evidence

Before this slice, the PHP minifier and prefixer accepted late or duplicate import modifiers that upstream rejects:

```text
@import "blocks/query.css" supports(display:grid) layer(theme.blocks) (min-width:240px);
@import "a.css" layer(foo) layer(bar);
@import "a.css" supports(display:grid) supports(color:red);
```

The minifier also serialized `supports(display:grid)(width>=240px)` without the upstream space between the supports condition and a parenthesized media tail.

## Implementation

- `CssMinifier::minifyImportStatement()` now consumes only the source-truth import modifier slots. A late bare `layer` falls through to media parsing, while late `layer(...)` and duplicate `supports(...)` function tails are rejected by the media parser.
- `CssMinifier::serializeImportParts()` now always separates import parts with a space, matching upstream import printing for `supports(...) (media)`.
- `TransitionPrefixer::rewriteImportMediaRangeTail()` now consumes at most one `layer` and then one `supports` modifier before treating the rest as media.
- Added focused assertions for:
  - anonymous `layer` plus `supports(...)` plus range fallback in an import media tail;
  - bare `layer` media tails after `supports` or after an anonymous layer modifier;
  - comma media-list range fallback after a layer import;
  - invalid `supports(...) layer(...)` order;
  - duplicate import function modifiers.
- Updated the directly coupled custom-media expectation for the upstream import spacing.
- Extended the WordPress media range layer example with the valid anonymous-layer import tail, comma media-list import tail, and invalid layer-after-supports guard.

## Verification

- `php -l lanes/lightningcss/src/CssMinifier.php` - no syntax errors.
- `php -l lanes/lightningcss/src/TransitionPrefixer.php` - no syntax errors.
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` - no syntax errors.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` - no syntax errors.
- `php -l lanes/lightningcss/tests/CustomMediaTransformerTest.php` - no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php` - no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` - `1 test files, 2069 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` - `1 test files, 1371 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/CustomMediaTransformerTest.php` - `1 test files, 47 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` - `1 test files, 809 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test` - exited 0.
- `git diff --check -- lanes/lightningcss` - no whitespace errors.

## Status Delta

- `lane-status.json` `phpPass`: `8550 -> 8559`.
- Mapped coverage remains `2398 / 3532`; this deepens the already represented media-query range/layer import-tail cluster.

## Non-Overlap

This slice does not repeat range value validation, resolution equality clone syntax, x/dppx unit conversion, typed/unknown range lowering, selector prefixing, CSSOM, CSS Modules, bundle source-map, or parser recovery clusters. It is limited to upstream import modifier grammar and layered import media-tail range fallback.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `CssMinifier`, `TransitionPrefixer`, `MediaQueryParser`, the existing focused test harness, and the existing WordPress media range layer example.

## Next Task

Continue with non-overlapping media-query import graph parity, especially dependency media serialization, CSSOM media read/write, custom-media import tail combinations, or source-map/CSS Modules clusters not already represented by current accepted coverage.
