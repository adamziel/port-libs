# LightningCSS Bundle Duplicate Supports Import Graph 2026-05-31T15:40Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T154019Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted source reads:
  - `src/bundler.rs::load_file()` merges duplicate imports by OR-ing supports only when the existing stylesheet already has a supports condition.
  - A duplicate import with no supports condition leaves the stylesheet unconditional, whether the unconditional occurrence is before or after a supports-gated import.
  - `src/bundler.rs::add_css_module_dep()` loads `composes ... from` dependencies with the parent rule conditions, so a CSS Modules dependency can make a same-file supports-gated `@import` unconditional.

## Native Delta

- `CssBundler::mergeImportRule()` now preserves an already-unconditional stylesheet when a later duplicate import adds `supports(...)`.
- Duplicate supports-gated then unconditional imports still clear the supports wrapper.
- CSS Modules `composes ... from` dependencies for the same stylesheet as a supports-gated `@import` now emit the dependency CSS unwrapped while preserving composed export metadata.
- `wordpress-bundle-import-graph.php` now smokes a block module that both imports a token module behind `supports(color: red)` and composes from that same token module; the bundled dependency remains unconditional like upstream.

## Evidence

- Red-first probes before implementation:
  - duplicate unconditional-plus-supports import emitted `@supports (color:red){.b{color:green}}.a{color:red}` instead of unwrapped `.b`.
  - same-file CSS Modules composes dependency emitted `@supports (color:red){.tok_token{color:green}}.entry_entry{color:red}`.
- `php -l lanes/lightningcss/src/CssBundler.php` => no syntax errors
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` => no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` => no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 78 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 1923 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php` => exits 0 and prints `css-modules: dependency graph resolved`
- `git diff --check -- lanes/lightningcss` => exits 0

## Coverage Delta

- Full LightningCSS PHP evidence: `1918` to `1923` assertions.
- Conservative mapped coverage: `1311 / 3532` to `1313 / 3532`.
- Counted upstream behaviors:
  - duplicate imports keep a stylesheet unconditional when any occurrence has no `supports()` condition;
  - CSS Modules `composes ... from` dependencies clear a duplicate same-file supports wrapper while preserving bundle graph export resolution.

## Non-Overlap

This slice avoids accepted resolver callback ordering, default relative resolution, import-prelude diagnostics, external import ordering, media/layer/supports wrapping for first imports, repeated media OR merging, repeated supports OR merging, last import graph position, custom-media sharing, license-comment preservation, CSS Modules dashed-ident bundle graphs, missing dependency exports, functional-local composes validation, source-map offsets, CSSOM shorthand work, target prefixing, media-range, and custom at-rule visitor slices.

## Dependency Closure

No new support component is needed. This reuses the existing native `CssBundler`, `CssModulesTransformer`, in-memory resolver/file map, and `CssMinifier`. Remaining bundle follow-up gaps include deeper source-map/bundler integration, filesystem/read callback parity, exact CSS Modules content hashing, and broader custom parser visitor integration through the bundle graph.
