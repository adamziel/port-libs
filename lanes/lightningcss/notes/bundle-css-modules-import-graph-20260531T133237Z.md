# LightningCSS Bundle CSS Modules Import Graph 2026-05-31T13:32Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T133237Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Static source reads:
  - `src/bundler.rs` collects CSS Modules dependencies from `composes ... from` declarations before ordinary `@import` dependencies.
  - `src/bundler.rs::order()` preserves the first CSS Modules dependency instance, but ordinary file imports still preserve the last browser-evaluated import position.
  - `src/bundler.rs::inline()` hoists CSS Modules dependencies before scanning import rules and rejects external modules referenced by CSS Modules `from` clauses.
  - `src/bundler.rs::test_css_module` covers bundled CSS Modules imports, `composes: x from "./b.css"`, recursive local composes export flattening, and dependency path resolution.

## Native Delta

- `CssBundler::bundleCssModules()` now enables bounded native CSS Modules bundling on top of the existing resolver/import graph.
- CSS Modules `composes` dependency specifiers are resolved through the same resolver callback as `@import`.
- CSS Modules dependency stylesheets are ordered before ordinary import dependencies, matching upstream bundler graph ordering.
- Entry exports resolve dependency references into composed local class names, including nested local composes from the dependency export.
- External CSS Modules `from` references throw `CssBundleException` kind `referenced-external-module-with-css-module-from`.
- `wordpress-bundle-import-graph.php` now smokes a WordPress block card module whose composed token stylesheet is emitted before a normal theme import.

## Evidence

- `php -l lanes/lightningcss/src/CssBundler.php`
- `php -l lanes/lightningcss/tests/CssBundlerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 69 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 1533 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php` => exits 0 and prints `css-modules: dependency graph resolved`
- `git diff --check -- lanes/lightningcss` => exits 0

## Coverage Delta

- Full LightningCSS PHP evidence: `1525` to `1533` assertions.
- Conservative mapped coverage: `1130 / 3532` to `1133 / 3532`.
- Counted upstream behaviors:
  - CSS Modules composes-from dependencies resolve before ordinary imports.
  - Dependency export local composes flatten into the importing entry export.
  - External CSS Modules from references are rejected with a dedicated bundle error.

## Non-Overlap

This slice avoids accepted bundle import-prelude diagnostics, external-import-after-bundled-import ordering, media/layer/supports wrapping and merging, repeated import last-position behavior, custom-media sharing, license-comment preservation, CSS Modules selector/composes grammar-only checks, source-map VLQ offsets, CSSOM shorthand work, target prefixing, media-range, and custom at-rule visitor slices.

## Dependency Closure

No new support component is needed. This reuses the existing native `CssBundler`, `CssModulesTransformer`, `CssMinifier`, path resolver, and in-memory file map. Remaining CSS Modules follow-up gaps are exact upstream content hashing/project-root hashing and dashed-ident variable dependency references.
