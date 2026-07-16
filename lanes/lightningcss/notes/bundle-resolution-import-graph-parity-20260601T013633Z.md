# Bundle Resolution Import Graph Parity - 2026-06-01

## Slice

`lightningcss-bundle-resolution-import-graph-parity-20260601T013633Z`

## Source Truth

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Relevant upstream behavior:
  - `src/parser.rs` only accepts `@import` in `TopLevelRuleParser` while the top-level parser state is still in the import prelude.
  - Nested rule parsing does not special-case `@import`; invalid nested at-rules are reported through `BasicParseErrorKind::AtRuleInvalid`.
  - `src/error.rs` renders that parser path as `Unknown at rule: @import`.

## Native Delta

`CssBundler` now scans parsed top-level block contents before graph resolution and rejects nested `@import` rules inside `@media`, `@layer`, and style blocks. The scanner preserves existing string/comment handling, CSS escape decoding, and block delimiter handling so quoted `@import` text remains valid declaration content.

The new guard throws a `parser-error` with the upstream-aligned message, source file, line, and column before any nested import dependency is read.

## Focused Evidence

- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`
  - `1 test files, 476 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 5405 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test`
  - exits `0`, including `nested-import: rejected-before-read`
- `php -l lanes/lightningcss/src/CssBundler.php`
  - no syntax errors
- `php -l lanes/lightningcss/tests/CssBundlerTest.php`
  - no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
  - no syntax errors
- `git diff --check -- lanes/lightningcss`
  - no output

## Status Delta

- Focused `CssBundlerTest.php`: `457 -> 476` assertions.
- Full LightningCSS lane: `5386 -> 5405` assertions.
- Conservative mapped coverage: `2289 -> 2290 / 3532`.
- `phpPass`: `5386 -> 5405`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP bundle parser, source reader callbacks, and diagnostic location helpers.

## Non-Overlap

This does not repeat the accepted top-level `@import supports()` pre-resolution validation. That accepted slice handles invalid import modifier conditions at top level; this one handles invalid nested `@import` at-rule placement and proves no nested dependency read occurs.

## Follow-Up

Full upstream Rust, Node, and WASM runners were not executed in this isolated micro-slice. Continue bundle/import graph parity with source-map import boundaries, dynamic resolver path identity, and remaining parser recovery cases.
