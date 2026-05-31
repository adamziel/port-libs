# LightningCSS Bundle Dashed Ident Import Graph 2026-05-31T15:05Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T150526Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Static source reads:
  - `src/lib.rs::test_css_modules` covers `dashed_idents: true`, local custom property declaration/reference scoping, and `var(--color from "./b.css")` dependency references.
  - `src/bundler.rs::load_file()` collects dashed-ident variable references when CSS Modules `dashed_idents` is enabled.
  - `src/bundler.rs::add_css_module_dep()` resolves those file specifiers into source indexes, and CSS Modules printing rewrites source-indexed dashed references to the dependency stylesheet hash.
  - `src/bundler.rs::order()` and `inline()` hoist CSS Modules dependencies before ordinary `@import` dependencies.

## Native Delta

- `CssModulesTransformer` now accepts `dashedIdents` / `dashed_idents`, scopes local custom property declarations and `var()` / `env()` references, and records file-backed dashed references.
- `CssBundler::bundleCssModules()` resolves those recorded dashed references through the existing resolver/import graph, loads dependency stylesheets before ordinary imports, and replaces dashed-reference placeholders with dependency-scoped custom property names.
- `wordpress-bundle-import-graph.php` now smokes a block module consuming a token custom property from a bundled CSS module dependency.

## Evidence

- Red-first focused gate before implementation: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `2 test files, 145 assertions, 2 failures`.
- `php -l lanes/lightningcss/src/CssBundler.php`
- `php -l lanes/lightningcss/src/CssModulesTransformer.php`
- `php -l lanes/lightningcss/tests/CssBundlerTest.php`
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `2 test files, 150 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 1765 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php` => exits 0 and prints `css-modules: dependency graph resolved`
- `git diff --check -- lanes/lightningcss` => exits 0

## Coverage Delta

- Full LightningCSS PHP evidence: `1759` to `1765` assertions.
- Conservative mapped coverage: `1232 / 3532` to `1234 / 3532`.
- Counted upstream behaviors:
  - CSS Modules dashed custom-property declarations and local `var()` / `env()` references are scoped and exported in `dashedIdents` mode.
  - File-backed dashed references such as `var(--bg from "./tokens.css")` resolve through the bundle graph and hoist dependency stylesheets before ordinary imports.

## Non-Overlap

This slice avoids accepted resolver callback ordering, default relative resolution, import prelude diagnostics, external import ordering, media/layer/supports wrapping and merging, repeated import last-position behavior, custom-media sharing, CSS Modules `composes from` dependencies, CSS Modules missing-export behavior, source-map offsets, CSSOM shorthand work, target prefixing, media-range, and custom at-rule visitor slices.

## Dependency Closure

No new support component is needed. This reuses the existing native `CssBundler`, `CssModulesTransformer`, in-memory resolver/file map, and `CssMinifier`. Remaining CSS Modules support gaps include exact upstream content-hash/project-root hashing and broader dashed-ident grammar beyond the bounded `var()` / `env()` graph path.
