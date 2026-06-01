# CSSOM Declaration Background Size Token Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T152842Z`

## Source Truth

- Upstream commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream files checked:
  - `tests/test_cssom.rs` for `DeclarationBlock::{get,set,remove}` read/write/remove behavior.
  - `src/properties/background.rs` for `Background::parse`: after parsing `BackgroundPosition`, upstream optionally parses `/ BackgroundSize`, then continues the loop for image/repeat/attachment/origin/clip tokens.

## Behavior

The PHP `DeclarationBlock` parser now treats slash-delimited `background-size` as only the size component. It resumes parsing later background tokens, so declarations such as:

```css
background: red url(hero.jpg) 20px 10px / cover no-repeat fixed border-box content-box
```

read, set, and remove these longhands independently:

- `background-size: cover`
- `background-repeat: no-repeat`
- `background-attachment: fixed`
- `background-origin: border-box`
- `background-clip: content-box`

Before this slice, the PHP parser absorbed the trailing tokens into `background-size` (`cover no-repeat fixed border-box content-box`), which corrupted CSSOM longhand removal and rewrite output.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - Result: `1 test files, 1295 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-background-longhand-removal-cssom.php --self-test`
  - Result: `OK`

## Status Delta

- Focused assertions: `1287 -> 1295` (`+8`)
- `lane-status.json` `phpPass`: `8399 -> 8407`
- Mapped upstream coverage remains conservative at `2393 / 3532`; this deepens the already represented CSSOM DeclarationBlock cluster.
- Full upstream Rust, Node, and WASM runners were not run in this isolated micro-slice.

## Non-Overlap

This slice does not repeat accepted bundle/import graph, source-map, media-query, CSS Modules, custom-at-rule, target-prefixing, property-value color/font/grid, or prior CSSOM priority/background-position-only work. It is bounded to background shorthand slash-size token boundaries in CSSOM declaration read/write/remove paths.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `DeclarationBlock` tokenizer and background longhand composer.
