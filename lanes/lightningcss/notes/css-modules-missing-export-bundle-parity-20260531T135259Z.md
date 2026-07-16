# LightningCSS CSS Modules Missing Export Bundle Parity 2026-05-31T13:52Z

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine read: `src/bundler.rs::test_css_module`.
- Upstream behavior: a resolved CSS Modules dependency stylesheet is still bundled, but a `composes: missing from "./dep.css"` reference is omitted from the flattened entry export when the dependency stylesheet does not export `missing`.

## Native Change

- `CssBundler::resolveCssModuleReferences()` now distinguishes unresolved external/file references from resolved dependency stylesheets with missing exports.
- Resolved-but-missing dependency exports are skipped, matching upstream `CssModule::handle_composes()` source-index behavior.
- `wordpress-bundle-import-graph.php` now models a block module that composes one existing dependency class and one missing dependency class; only the existing dependency class appears in exported metadata.

## Evidence

- Red-first focused run after adding the assertion: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 71 assertions, 1 failures`; failure showed the stale dependency compose reference retained in `exports.card.composes`.
- After fix focused run: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 71 assertions, 0 failures`.
- Full lane run: `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 1621 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` => exits 0 and prints `css-modules: dependency graph resolved`.
- Final checks: PHP lint passed for `CssBundler.php`, `CssBundlerTest.php`, and `wordpress-bundle-import-graph.php`; `git diff --check -- lanes/lightningcss` passed; changed lane JSON files decode successfully.

## Coverage Delta

- PHP focused assertions: `1619 -> 1621`.
- Conservative mapped coverage: `1164 / 3532 -> 1165 / 3532`.
- Expected dashboard movement: LightningCSS `phpPass +2`, `benchmarkDenominator.mapped +1`.

## Dependency Closure

No new support component is needed. This reuses the existing native `CssBundler`, `CssModulesTransformer`, in-memory resolver/path graph, and CSS module export metadata flattening.

## Non-Overlap

This avoids accepted CSS Modules selector/composes delimiter validation, nested local selector composes, view-transition scoping, and previous dependency graph ordering/export flattening/external rejection slices. Follow-up CSS Modules gaps are exact project-root/content hashing and dashed-ident dependency references.
