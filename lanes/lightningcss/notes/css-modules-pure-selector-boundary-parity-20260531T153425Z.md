# LightningCSS CSS Modules Pure Selector Boundary Parity 2026-05-31T15:34Z

Source truth:

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream area: pure CSS Modules selector checks in `src/lib.rs` around `pure_css_module_options`.
- Upstream behavior: pure mode rejects selector-list arms that do not include a local class or id, rejects bare global/type/attribute selectors, accepts local class/id selectors even when nested in relational pseudo-class arguments such as `:has(.my-class)`, and allows explicitly marked global selectors after `/* cssmodules-pure-no-check */`.

Implementation:

- `CssModulesTransformer::transform()` now accepts a `pure` option.
- Pure mode validates each top-level selector-list arm for a local class or id while honoring existing `:global(...)` / `:local(...)` mode precedence.
- `cssmodules-pure-no-check` comments are consumed as opt-out markers and are not emitted in the transformed CSS.
- `wordpress-css-modules-transformer.php` now smokes a build-free block stylesheet path that rejects an unmarked pure global selector while accepting the upstream opt-out marker for intentionally public WordPress markup.

Evidence:

- Baseline focused CSS Modules gate before this slice: `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 80 assertions, 0 failures`.
- Focused after fix: `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 94 assertions, 0 failures`.
- Full LightningCSS lane after fix: `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 1932 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-css-modules-transformer.php --self-test` => `OK`.
- Root harness status: not run - isolated micro-slice.

Dependency closure:

- No new support component is needed. This reuses the existing native CSS Modules selector scanner, comment stripping, top-level selector-list splitting, `NestingTransformer`, and `CssMinifier`.

Non-overlap:

- This does not repeat accepted dashed-ident bundle graph behavior, functional `:local()` composes rejection, local/global selector-list validation, nested-composes lowering, view-transition identifier scoping, missing-export bundler flattening, or CSS Modules import graph behavior. It only adds the upstream pure-mode local/global selector boundary subset.
