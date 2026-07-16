# CSSOM Text Emphasis Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T170256Z`

## Source Truth

- Upstream LightningCSS commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/mod.rs` defines `text-emphasis-style`, `text-emphasis-color`, `text-emphasis`, and their WebKit-prefixed forms.
- `src/properties/text.rs` defines `TextEmphasisStyle` keyword/string parsing, `TextEmphasis` shorthand defaults, and shorthand serialization: style defaults to `none`, color defaults to `currentColor`, `currentColor` is omitted, and style `none` serializes as `none`.
- `text-emphasis-position` is a separate longhand in upstream and is intentionally preserved as a direct property, not part of the `text-emphasis` shorthand removal set.

## Lane Delta

- Added `DeclarationBlock` CSSOM get/set/remove support for `text-emphasis`, `text-emphasis-style`, `text-emphasis-color`, `-webkit-text-emphasis`, `-webkit-text-emphasis-style`, and `-webkit-text-emphasis-color`.
- Added focused DeclarationBlock tests for shorthand reads, longhand composition, important mismatch behavior, prefixed separation, priority-bucket fallback, and longhand/shorthand removals.
- Added `examples/wordpress-text-emphasis-cssom.php` to cover a WordPress annotation/emphasis mark workflow using preset color variables without Node/WASM.
- Updated lane-local status and manifest evidence. Conservative mapped coverage remains `1539 / 3532` because this deepens the already represented DeclarationBlock CSSOM cluster.

## Verification Evidence

- Red-first: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` failed before the source change at `1 test files, 432 assertions, 3 failures` on the new text-emphasis read/write/remove assertions.
- After implementation: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` passed at `1 test files, 451 assertions, 0 failures`.
- Full lane focused run: `php tools/run-tests.php lanes/lightningcss/tests` passed at `13 test files, 2512 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-text-emphasis-cssom.php --self-test` printed `OK`.
- PHP lint passed for `lanes/lightningcss/src/DeclarationBlock.php`, `lanes/lightningcss/tests/DeclarationBlockTest.php`, and `lanes/lightningcss/examples/wordpress-text-emphasis-cssom.php`.
- JSON validation passed for `lanes/lightningcss/lane-status.json` and `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/lightningcss` passed with no output.

## Dependency Closure

No new support component is needed. The slice reuses the existing `DeclarationBlock` parser, top-level token splitting, priority partitioning, and CSSOM shorthand removal helpers.

## Non-Overlap

This slice does not touch accepted flex, text-decoration, font, border-radius, mask-border, source-map, CSS Modules, target-prefixing, or bundler behavior. It is scoped to the upstream text-emphasis CSSOM declaration cluster.
