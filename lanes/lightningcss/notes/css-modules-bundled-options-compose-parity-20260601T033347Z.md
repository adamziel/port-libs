# CSS Modules Bundled Options Compose Parity - 2026-06-01T03:33Z

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T033347Z`

Accepted base: `86e2d14305df2668712f30216ab52d92b6b533a7`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/lib.rs::test_css_modules` and `test_pseudo_replacement` show CSS Modules `pure`, `unused_symbols`, and `pseudo_classes` behavior applied through the same parser/printer path that produces local/global/composes export metadata.
- `src/css_modules.rs::handle_composes` resolves source-index module composes by importing the referenced module export and extending its composed references, so bundled CSS Modules options must be applied consistently to imported module sources before export graph resolution.

## Implementation

- `CssBundler::cssModuleTransformOptions()` now forwards `pure`, `unusedSymbols` / `unused_symbols`, and `pseudoClasses` / `pseudo_classes` into every `CssModulesTransformer` invocation in the import graph.
- Bundled CSS Modules now scope pseudo replacement classes in dependency and entry modules, prune unused local symbols from both sides of a compose graph, and reject impure dependency selectors when `pure` is enabled.
- `wordpress-css-modules-bundler-composes.php` now smokes the forwarded advanced options alongside the existing source-index compose and animation/custom-ident option coverage.

## Verification

- `php -l lanes/lightningcss/src/CssBundler.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-bundler-composes.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` -> 1 test file, 526 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> 1 test file, 376 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-css-modules-bundler-composes.php` -> printed `css-module-advanced-options: forwarded`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> 13 test files, 5798 assertions, 0 failures.
- `git diff --check -- lanes/lightningcss` -> passed.

## Status Delta

- `lane-status.json` `phpPass`: `5793 -> 5798`.
- Conservative mapped coverage remains `2320 / 3532`; this deepens the represented CSS Modules local/global/composes and bundler option graph clusters rather than adding a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the native PHP CSS Modules transformer, bundled import graph, source-index compose resolver, selector pseudo replacement, unused-symbol pruning, and pure-mode validation. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

## Non-Overlap

This does not repeat accepted nested-composes rejection, direct transformer pseudo replacement, direct unused-symbol pruning, direct pure-mode validation, animation/custom-ident bundler option propagation, grid/container bundler option propagation, source-index duplicate compose preservation, missing-export bundling, source-map, CSSOM, media-query, property-value, custom at-rule, or target-prefixing slices. The stale May 25 `CustomMediaTransformer.php` rework note is unrelated to this current CSS Modules bundle option graph behavior.
