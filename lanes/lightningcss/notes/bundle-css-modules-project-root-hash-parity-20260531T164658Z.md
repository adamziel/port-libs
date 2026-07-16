# LightningCSS Bundle CSS Modules Project-Root Hash Parity 2026-05-31T16:46Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T164658Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream reads:
  - `src/bundler.rs::test_css_module` project-root hash fixture where `/foo/bar/a.css` plus `/foo/bar/b.css` and `/x/y/z/a.css` plus `/x/y/z/b.css` emit the same scoped class names when printed with matching `project_root`.
  - `src/bundler.rs::order()` and `inline()` still place imported CSS Modules dependencies before the entry module when the import graph is bundled.
  - `src/css_modules.rs` hashing behavior already ported in this lane supplies the Rust-compatible `[hash]` names.

## Native Delta

- `CssBundler::bundleCssModules()` and `bundleCssModulesWithReader()` now pass the resolved stylesheet filename and optional `projectRoot` / `project_root` option into `CssModulesTransformer`.
- The bundler only passes an explicit `hash` when callers provide `hashes`; otherwise the transformer computes the upstream-compatible filename hash relative to `projectRoot`.
- CSS Modules dashed-ident dependency replacement now reuses the dependency stylesheet's actual exported scoped dashed name, so bundled dependency references stay aligned with `[hash]`, `[name]`, and `[content-hash]` pattern handling.
- `wordpress-bundle-import-graph.php` now smokes a build-free block CSS Modules bundle mounted under two different absolute roots and verifies the emitted class names stay stable relative to the project root.

## Evidence

- Red-first spot-check before implementation:
  `bundleCssModules("/foo/bar/a.css", ..., ["projectRoot" => "/foo/bar"])` emitted `.GbJejW_b{background:#ff0}.Udrzcz_a{background:#fff}` instead of upstream `.dyGcAa_b{background:#ff0}.CK9avG_a{background:#fff}`.
- PHP lint:
  `php -l lanes/lightningcss/src/CssBundler.php && php -l lanes/lightningcss/src/CssModulesTransformer.php && php -l lanes/lightningcss/tests/CssBundlerTest.php && php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` =>
  no syntax errors.
- Focused after fix:
  `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` =>
  `1 test files, 114 assertions, 0 failures`.
- Full LightningCSS lane:
  `php tools/run-tests.php lanes/lightningcss/tests` =>
  `13 test files, 2317 assertions, 0 failures`.
- Example smoke:
  `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php` =>
  exits 0 and prints `css-modules-project-root: stable`.
- Whitespace:
  `git diff --check -- lanes/lightningcss` => exits 0.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence moves from `2313` to `2317 pass / 0 fail`.
- Conservative mapped coverage moves from `1446` to `1447 / 3532` for the upstream `src/bundler.rs::test_css_module` project-root hash import-graph fixture.

## Dependency Closure

No new support component is needed. This reuses the native `CssBundler`, `CssModulesTransformer`, resolver/path graph, `CssMinifier`, and the existing Rust-compatible CSS Modules hash implementation. No Node, Rust, browser service, parser generator, or external resolver package is introduced.

## Non-Overlap

This slice avoids accepted SourceProvider reads, resolver-result shape diagnostics, URL import modifier parsing, ordinary repeated-import order, external-import ordering, custom-media sharing, CSS Modules content-hash transformer parity, escaped specifier decoding, dashed-ident dependency graph behavior, missing-export flattening, pure selector boundaries, and SourceMap project-root normalization. It only wires the already-ported upstream CSS Modules hash behavior through bundled import graph filenames and project roots.
