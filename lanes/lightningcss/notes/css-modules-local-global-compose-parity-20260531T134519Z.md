# LightningCSS CSS Modules Local/Global Compose Parity 2026-05-31T13:45Z

Source truth:

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream area: CSS Modules selector parsing/scoping around functional `:local(...)` and `:global(...)`, plus the existing local/global/dependency `composes` export path in `src/lib.rs::test_css_modules`.
- Native oracle spot-check used the pinned local NAPI artifact: direct selector-list arguments such as `.x :global(.foo, .bar)` and `:local(.foo, .bar)` report `Unexpected token Comma`; nested `:local(...)` inside `:global(...)` remains global, e.g. `:global(.wp-block :local(.legacy)) .title` prints `.wp-block .legacy .EgL3uq_title`.

Implementation:

- `CssModulesTransformer` now rejects empty `:local()` / `:global()` arguments and direct top-level selector-list commas inside those functional pseudos, matching the upstream parser boundary.
- Selector rewriting now preserves upstream mode precedence: once a selector fragment is in global mode, nested `:local(...)` is still emitted as global instead of re-exporting the inner class.
- Existing local/global/dependency `composes` metadata handling is unchanged; the new tests prove the local/global selector boundary does not corrupt composed export metadata.
- `wordpress-css-modules-transformer.php` now models a block module selector where legacy public WordPress markup contains an authored `:local(...)` inside a global selector, plus a rejected selector-list `:global(...)` migration error.

Evidence:

- Pre-fix PHP spot-check for `.x :global(.foo, .bar) { color: red }` emitted `.EgL3uq_x .foo,.bar{color:red}` instead of the upstream comma error.
- Focused after fix: `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 73 assertions, 0 failures`.
- Full lane after fix: `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 1584 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-css-modules-transformer.php --self-test` => `OK`.
- PHP lint passed for `CssModulesTransformer.php`, `CssModulesTransformerTest.php`, and `wordpress-css-modules-transformer.php`.
- `git diff --check -- lanes/lightningcss` passed.

Dependency closure:

- No new support component is needed. This reuses the lane-local CSS Modules selector scanner, top-level delimiter parser, `NestingTransformer`, and `CssMinifier` output path.

Next task:

- Continue CSS Modules parity with animation/keyframes, counter-style/list-style, grid/custom-property references, and dependency composition through bundler graphs.
