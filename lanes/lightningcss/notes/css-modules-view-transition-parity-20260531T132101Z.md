# LightningCSS CSS Modules View Transition Parity 2026-05-31T13:21Z

Source truth:

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream area: `src/lib.rs::test_css_modules` view-transition custom-ident cases covering `view-transition-name`, `view-transition-class`, `view-transition-group`, `@view-transition { types: ... }`, `:active-view-transition-type(...)`, and `::view-transition-*()` selector arguments.

Implementation:

- `CssModulesTransformer` now scopes local custom identifiers in CSS Modules view-transition declaration values and `@view-transition` type descriptors.
- Selector rewriting now scopes custom identifiers inside `:active-view-transition-type()` and `::view-transition-group()`, `::view-transition-new()`, `::view-transition-image-pair()`, and `::view-transition-old()` arguments.
- Export metadata keeps the upstream shape: newly scoped custom idents are exported with `isReferenced: false`, matching upstream `CustomIdent` printer behavior, while existing `composes` behavior remains unchanged.
- `wordpress-css-modules-transformer.php` now demonstrates a block card module with scoped view-transition names/classes/types plus dependency/global `composes` metadata.

Evidence:

- Red-first focused run before implementation: `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 51 assertions, 2 failures`.
- Focused after fix: `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 55 assertions, 0 failures`.
- Full LightningCSS lane: `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 1388 assertions, 0 failures`.
- PHP lint passed for `CssModulesTransformer.php`, `CssModulesTransformerTest.php`, and `wordpress-css-modules-transformer.php`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-css-modules-transformer.php` exits 0.
- `git diff --check -- lanes/lightningcss` passes.

Dependency closure:

- No new support component is needed. This reuses the existing bounded CSS Modules selector/declaration scanners and `NestingTransformer`/`CssMinifier` output path.

Next task:

- Continue CSS Modules parity with animation/keyframes, counter-style/list-style, grid/custom-property references, and dependency composition through bundler graphs.
