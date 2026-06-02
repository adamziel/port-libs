# LightningCSS CSS Modules Local/Global Compose Parity

## Scope

- Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T234904Z`.
- Targeted upstream source truth: pinned LightningCSS commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, focused on `src/lib.rs::test_css_modules` and `src/properties/css_modules.rs::Composes::parse`.
- Local native addon spot checks against `lightningcss.linux-x64-gnu.node` confirmed that leading license comments before the first CSS Modules rule are preserved, while `/*! ... */` comments inside removed `composes` declarations are token separators and are not hoisted into output CSS.

## Native Changes

- `CssModulesTransformer::stripComments()` now preserves license comments only while scanning the leading top-level comment run.
- Internal `/*! ... */` comments inside local, global, and dependency `composes` declaration values now flow through the existing separator handling instead of being collected as top-level preserved comments.
- The existing `cssmodules-pure-no-check` marker remains special-cased and does not disable leading license comment eligibility.
- A leading license comment before the first rule still serializes before the transformed CSS Modules output, matching upstream behavior.

## WordPress Scenario

- Updated `examples/wordpress-css-modules-commented-composes-property.php`.
- The smoke now models WordPress block/theme CSS Modules generated with migration/source-token license comments inside local, global, and dependency `composes` values. The comments must not leak into the built CSS after those `composes` declarations are consumed.

## Red-First Evidence

- Before the fix, PHP serialized `.test { composes: foo /*!x*/ } .foo { color: red }` with a leaked `/*!x*/` comment before `.EgL3uq_test{}`.
- The pinned native addon serializes the same case as `.EgL3uq_test{}.EgL3uq_foo{color:red}` and still preserves an actual leading license comment such as `/*! Theme module license */`.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php`
  - `1 test files, 713 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-css-modules-commented-composes-property.php --self-test`
  - `OK`
- `php -l lanes/lightningcss/src/CssModulesTransformer.php`
  - `No syntax errors detected in lanes/lightningcss/src/CssModulesTransformer.php`
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/CssModulesTransformerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-css-modules-commented-composes-property.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-css-modules-commented-composes-property.php`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `14 test files, 9198 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss`
  - passed with no output

## Coverage And Non-Overlap

- `phpPass` moves `9186 -> 9198`.
- Conservative mapped coverage remains `2439 / 3532`; this deepens the existing CSS Modules local/global/composes mapping rather than claiming a new manifest unit.
- This does not repeat accepted `:has(:scope)` selector elision, pseudo-element branch parity, escaped composes-property comments, ordinary comments as separators, invalid composes math, bundle option propagation, CSSOM logical border-radius, source-map, media-query, target-prefixing, custom at-rule, or property/value work.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP CSS Modules transformer and existing test/example harness. The direct native addon check was used only as a local oracle against the pinned upstream cache. Full upstream Rust/Node/WASM runners were not executed; the existing missing dependency blocker remains `uvu`/`detect-libc` for Node and `napi-wasm`/`wasm-opt` for WASM.

## Follow-Up

There is broader exact-output parity debt around semicolon preservation when `composes` is removed from rules that still contain declarations. This slice intentionally leaves that larger formatter issue for a separate, upstream-backed batch because the current behavior cluster is limited to internal license-comment handling in consumed `composes` declarations.
