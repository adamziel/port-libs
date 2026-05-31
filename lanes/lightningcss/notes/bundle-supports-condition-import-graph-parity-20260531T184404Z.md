# LightningCSS Bundle Supports Condition Import Graph Parity 2026-05-31T18:44Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T184404Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream reads:
  - `src/bundler.rs::combine_supports()` and `Bundler::load_file()` combine parent and child `@import` `supports()` conditions through `SupportsCondition::and`.
  - `src/bundler.rs::load_file()` OR-merges repeated imports of the same file through `SupportsCondition::or`.
  - `src/rules/supports.rs::SupportsCondition::and()`, `or()`, and `needs_parens()` preserve nested `or`, `and`, and `not` operands when serializing under a different parent operator.

## Native Delta

- `CssBundler` now wraps nested `supports()` operands when composing parent-child and repeated `@import` graph conditions, preserving upstream AST precedence instead of flattening string fragments.
- `CssMinifier` now determines a supports logical operator before normalizing operands, so a leading nested `or` under `and`, or leading nested `and` under `or`, keeps its required grouping in final bundled CSS.
- `wordpress-bundle-import-graph.php` now models a block-theme import graph with nested supports-gated layout imports and verifies grouped output without Node/WASM.

## Evidence

- Red check before fix: `CssBundler` emitted `@supports (display:flex) or (display:grid) and (color:red)` for a parent import guarded by `supports((display:flex) or (display:grid))` and a child import guarded by `supports(color:red)`, losing the upstream grouping around the parent `or` condition.
- `php -l lanes/lightningcss/src/CssBundler.php` => no syntax errors.
- `php -l lanes/lightningcss/src/CssMinifier.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 183 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` => `1 test files, 1132 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 3113 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php` => exits 0 and prints `supports-import-graph: grouped`.
- `git diff --check -- lanes/lightningcss` => passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `3108` to `3113` assertions.
- Conservative mapped coverage remains `1689 / 3532`; this deepens the already represented `src/bundler.rs::test_bundle` import graph/supports-condition cluster instead of claiming a new denominator row.

## Dependency Closure

No new support component is needed. This reuses native PHP `CssBundler`, `CssMinifier`, the existing supports-condition scanner/minifier helpers, the bounded resolver/import graph model, and the existing WordPress bundle smoke. No Node, Rust, WASM, browser service, package resolver, or external credentialed provider is introduced.

## Non-Overlap

This slice avoids accepted source-map source collection/remapping, file-backed CSS Modules graph resolution, parent-relative imports, escaped specifier decoding, import modifier ordering, duplicate unconditional supports imports, external import ordering diagnostics, media-type boolean conjunction, CSSOM declaration work, target prefixing, property-value minifier clusters, and custom at-rule visitor clusters. It only fixes support-condition grouping when the already mapped import graph composes parent-child or repeated `supports()` conditions.
