# LightningCSS Bundle Import Graph Namespace Parity

Slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T032839Z`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/parser.rs` defines top-level parser state as `Start < Layers < Imports < Namespaces < Body`. `@import` is allowed while state is `Imports` or earlier, and `@namespace` is allowed while state is `Namespaces` or earlier.
- `src/bundler.rs` parses the importing stylesheet before resolving imports, then inlines imported files before the remaining rules. A native module spot-check at the pinned cache confirmed:
  - `@import "b.css"; @namespace svg "..."; svg|path { ... }` bundles as imported CSS, then `@namespace`, then the namespaced rule.
  - `@import "b.css"; .entry { ... } @namespace svg "...";` rejects with `@namespaces rules must precede all rules aside from @charset, @import, and @layer statements` before reading `b.css`.

## Native Delta

- `CssBundler` now tracks namespace ordering separately from import ordering while scanning top-level items.
- Namespace statements after resolved imports remain in the post-import body so bundled output matches upstream concatenation.
- Namespace statements after ordinary style rules now throw a `CssBundleException` with the upstream namespace ordering diagnostic before resolver/read traversal.
- `CssMinifier` keeps standalone namespace-after-style validation by default, but the bundler calls it in a bundle-only mode that allows upstream-valid AST concatenation where imported rules precede a namespace statement.
- `wordpress-bundle-import-graph.php` now smokes SVG namespace statements after block CSS imports and verifies late namespaces reject before reading the imported block stylesheet.

## Verification

- `php -l lanes/lightningcss/src/CssBundler.php` - passed.
- `php -l lanes/lightningcss/src/CssMinifier.php` - passed.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` - passed.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` - passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` - `1 test files, 524 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` - `1 test files, 1741 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 5764 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` - passed, includes `namespace-after-import: preserved` and `late-namespace: rejected-before-read`.

## Coverage And Closure

- Focused `CssBundlerTest.php` assertion growth: `516 -> 524` (`+8`).
- Full LightningCSS lane assertion growth: `5756 -> 5764` (`+8`).
- Conservative mapped coverage remains `2320 / 3532` because this deepens the existing bundle/import graph parser and resolution cluster.
- Dependency closure: no new support component is needed. The slice reuses native `CssBundler`, `CssMinifier`, resolver/read callbacks, and existing namespace minification logic.

## Non-Overlap

This avoids the stale May 25 `CustomMediaTransformer.php` rework note and does not touch source-map VLQ/import-map behavior, CSS Modules dependency graphs, media range import dedupe, target prefixing, CSSOM, property/value, selector, or custom at-rule visitor work. It is limited to upstream namespace ordering across resolved bundle import graphs.
