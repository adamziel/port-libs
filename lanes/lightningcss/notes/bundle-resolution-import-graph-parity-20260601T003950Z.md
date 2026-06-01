# LightningCSS Bundle Resolution Import Graph Parity - 2026-06-01T00:39Z

## Source Truth

- Upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream files: `src/bundler.rs` and `node/test/bundle.test.mjs`.
- Relevant upstream behavior:
  - `src/bundler.rs::visit_vars()` walks custom `env()` names as dashed-ident references, not only `var()` fallback tokens.
  - `Bundler::load_file()` resolves those CSS Modules dependencies through the source provider.
  - `Bundler::order()` hoists CSS Modules dependency stylesheets before ordinary `@import` dependencies for the same entry stylesheet.

## Native Delta

- Added focused `CssBundlerTest.php` coverage for a CSS Modules block stylesheet whose `env(--wp-card-gap from "pkg:tokens.css", var(--fallback-gap from "pkg:fallback.css"))` references two dependency stylesheets before a normal theme `@import`.
- The test asserts resolver call order, emitted dependency-before-theme order, scoped dashed-ident replacement, export shape, and missing dependency diagnostics at the style-rule location.
- Extended `wordpress-bundle-import-graph.php` with the same block-style `env()` dependency graph smoke.
- No production source change was needed; current native PHP behavior already matched this upstream cluster.

## Evidence

- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` - `1 test files, 422 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` - exits 0 and prints `css-modules-env-dependency: resolved`.

## Status Delta

- Focused `CssBundlerTest.php` assertions move `414 -> 422`.
- Full LightningCSS lane assertions move `5135 -> 5143`.
- Conservative mapped coverage remains `2238 / 3532` because this deepens the already represented CSS Modules dashed-ident dependency/import graph cluster rather than claiming a fresh denominator row.

## Dependency Closure

No new support component is needed. The slice reuses native `CssBundler`, `CssModulesTransformer`, resolver callbacks, dashed-ident dependency replacement, and the existing WordPress bundle example.

## Non-Overlap

- Avoided the stale May 25 `CustomMediaTransformer` rework note; later accepted custom-media import-tail tests already cover that conflict path.
- Did not duplicate accepted CSS Modules imported local collision, recursive composes, file-backed CSS Modules graph, missing-export, content-hash, project-root, or position-try dashed compose slices.
- Did not edit root dashboard/progress files or run Rust, Node, WASM, or root harnesses.

## Follow-Up

Next import-graph work can target remaining resolver/source-map/CSS Modules interactions that are not already represented by dashed-ident dependency traversal, imported local collisions, or file-backed source-provider parity.
