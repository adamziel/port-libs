## CSS Modules Forgiving Local/Global Selector Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T035014Z`

Source truth:
- Pinned upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Local NAPI oracle confirmed that invalid nested `:local()` / `:global()` selector-list arguments are dropped in forgiving selector-list contexts: `:is()`, `:where()`, `:has()`, and `nth-child()` / `nth-last-child()` `of` lists.
- The same oracle confirmed that strict contexts still reject: top-level `:local(.a, .b)`, top-level `:global(.a, .b)`, and `:not(:global(.a, .b))`.

Implementation:
- `CssModulesTransformer` now rewrites forgiving selector-list functions by attempting each selector independently and omitting invalid selector-list entries without exporting dropped local symbols.
- `nth-child()` and `nth-last-child()` now handle `of` selector lists with the same forgiving behavior, including the upstream empty-list serialization when every candidate is dropped.
- Existing strict validation remains in place for direct `:local()` / `:global()` pseudos and `:not()`.
- Compose metadata is preserved for surviving simple local classes in the same stylesheet.

Verification:
- Red-first PHP spot check before the fix threw on `.card:nth-child(odd of :global(.wp, .x)) { color: red }`, while upstream emitted `.EgL3uq_card:nth-child(odd of ){color:red}`.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php` passed.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-forgiving-selectors.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` passed: `1 test files, 383 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-forgiving-selectors.php --self-test` passed: `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 5862 assertions, 0 failures`.

Status delta:
- `lane-status.json` updated from `5855` to `5862` PHP assertions.
- Conservative mapped coverage remains `2320 / 3532`; this deepens the existing CSS Modules local/global/composes cluster rather than claiming a new manifest denominator unit.

Dependency closure:
- No new support component is needed. This reuses the lane-local CSS Modules transformer, selector scanner, minifier pipeline, PHP test harness, and example self-test harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

Non-overlap:
- This does not repeat accepted CSS Modules selector-list validation, functional-local composes rejection, escaped identifiers/specifiers, declaration priority, animation/keyframes, counter-style/list-style, grid, container, scope, dashed idents, view-transition, host-context, attribute selectors, invalid composes fallback, unused symbols, nested unused pruning, `@nest`, commented composes property handling, or bundled CSS Modules option plumbing.
- The stale CustomMedia import-tail rework note in the main handoff directory remains unrelated to this CSS Modules slice.
