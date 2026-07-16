# LightningCSS CSS Modules Nth Type/Column Compose Parity

## Scope

- Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T164103Z`.
- Targeted upstream source truth: pinned LightningCSS commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, focused on CSS Modules selector rewriting and upstream selector parser behavior for nth-formula pseudo classes.
- Local upstream NAPI spot checks against the pinned `lightningcss.linux-x64-gnu.node` confirmed:
  - `.card:nth-of-type(2n+1)` and `.card:nth-col(2n+1)` serialize formulas as `odd` while preserving the `button` export's local `composes: card` metadata.
  - Uppercase and escaped function names such as `:NTH-OF-TYPE(...)` and `:nth-\6f f-type(...)` serialize to canonical lowercase pseudo names.
  - `:local(...)` and `:global(...)` inside `nth-of-type` or `nth-last-of-type` formulas reject with `Unexpected token Colon`.
  - Selector tokens inside `nth-col` formulas reject before compose export emission, including `Unexpected token Delim('.')` and `Unexpected token WhiteSpace(" ")`.

## Native Changes

- `CssModulesTransformer` now treats `:nth-of-type()`, `:nth-last-of-type()`, `:nth-col()`, and `:nth-last-col()` as formula-bearing pseudo functions instead of passing them through the raw pseudo-function path.
- Only `:nth-child()` and `:nth-last-child()` keep Selectors 4 `of <selector-list>` handling. The type and column variants validate their full argument as an upstream formula and reject CSS Modules mode pseudos inside that formula.
- Serialized nth formula pseudo names now use the decoded canonical function name, matching upstream case and escape normalization.

## WordPress Scenario

- Updated `examples/wordpress-css-modules-nth-child-formula.php`.
- The smoke now covers block CSS Modules where child, type, and column nth formulas are minified while a `button` export still flattens its composed local `card` class.
- The same smoke records upstream diagnostics for invalid local/global formula tokens and invalid selector-like formula tokens.

## Verification

- Baseline before editing: `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php`
  - `1 test files, 684 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php`
  - `1 test files, 692 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-css-modules-nth-child-formula.php --self-test`
  - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 8784 assertions, 0 failures`
- `php -l lanes/lightningcss/src/CssModulesTransformer.php`
  - no syntax errors
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php`
  - no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-css-modules-nth-child-formula.php`
  - no syntax errors
- `git diff --check -- lanes/lightningcss`
  - passed with no output

## Coverage And Non-Overlap

- Focused CssModulesTransformer assertions moved `684 -> 692` (`+8`).
- Full LightningCSS lane verification passed at `13 test files, 8784 assertions, 0 failures`; `lane-status.json` records `phpPass` as the verified current-lane count.
- Conservative mapped coverage remains `2398 / 3532`; this deepens the already represented CSS Modules local/global/composes selector-function cluster rather than claiming a new manifest unit.
- This does not repeat accepted dangling local/global selector branch handling, page descriptor composes, page margin-box composes, container-name scoping, bundle import graph, source-map, CSSOM, media-query, target-prefixing, custom-at-rule, or property/value work.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP CSS Modules selector scanner, nth formula validator, compose export metadata, test harness, and example self-test path. Full upstream Rust/Node/WASM runners were not executed in this isolated batch.
