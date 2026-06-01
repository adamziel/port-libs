# LightningCSS CSS Modules Local/Global Compose Parity

## Scope

- Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T150914Z`.
- Targeted upstream source truth: pinned LightningCSS commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, focused on `src/lib.rs::test_css_modules`, `src/css_modules.rs::CssModule::handle_composes`, and selector parser behavior around CSS Modules `:local(...)` / `:global(...)` mode pseudos.
- Local upstream NAPI spot checks against `lightningcss.linux-x64-gnu.node` confirmed:
  - `:local(.foo >)` and `:global(.foo +)` reject with `Invalid dangling combinator in selector`.
  - `:local(, .foo)` rejects with `Invalid empty selector`.
  - Forgiving selectors drop invalid mode-pseudo branches, e.g. `.card:is(:local(.drop >), .kept)` serializes through the `.kept` branch only.

## Native Changes

- `CssModulesTransformer` now validates dangling top-level combinators in selector fragments before serializing local/global mode-pseudo contents.
- Functional `:local(...)` and `:global(...)` now match upstream's leading-empty branch diagnostic before selector-list comma diagnostics.
- Forgiving selector lists (`:is`, `:where`, `:has`, and `nth-child(... of ...)`) now drop invalid dangling local/global branches instead of emitting invalid selectors like `.foo>`.
- Strict selectors such as `:not(:local(.foo >))` now raise the upstream dangling-combinator diagnostic.

## WordPress Scenario

- Added `examples/wordpress-css-modules-dangling-local-global.php`.
- The smoke models block CSS Modules generated during migration/build cleanup where a stale local/global branch is dropped from a forgiving selector, while the surviving `button` export still flattens its local `composes` class list.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php`
  - `1 test files, 641 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-css-modules-dangling-local-global.php --self-test`
  - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 8337 assertions, 0 failures`
- `php -l lanes/lightningcss/src/CssModulesTransformer.php && php -l lanes/lightningcss/tests/CssModulesTransformerTest.php && php -l lanes/lightningcss/examples/wordpress-css-modules-dangling-local-global.php`
  - all three files reported no syntax errors
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status ok\n";'`
  - `lane-status ok`
- `git diff --check -- lanes/lightningcss`
  - passed with no output

## Coverage And Non-Overlap

- `phpPass` moves `8327 -> 8337`.
- Conservative mapped coverage remains `2393 / 3532`; this deepens the already represented CSS Modules local/global/composes cluster rather than claiming a new manifest unit.
- This does not repeat accepted CSS Modules selector-list validation, relative local/global branch dropping, escaped pseudo handling, pseudo-element boundaries, host-context behavior, invalid composes fallback, dependency composes, bundle import graph, CSSOM, source-map, media-query, target-prefixing, custom-at-rule, or property-value work.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP CSS Modules selector scanner, existing compose export metadata, test harness, and example self-test path. Full upstream Rust/Node/WASM runners were not executed in this isolated batch.
