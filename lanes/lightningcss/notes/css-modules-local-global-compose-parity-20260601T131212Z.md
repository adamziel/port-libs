# CSS Modules Local Global Compose Parity

- Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T131212Z`.
- Source truth: pinned `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream oracle: the native upstream NAPI artifact in `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node` was run against strict and forgiving selector cases.

## Source Truth

- Strict `:local(> .child)`, `:global(~ .legacy)`, and `.card:not(:local(+ .child))` throw `Invalid empty selector` upstream because CSS Modules local/global mode pseudos take a selector, not a relative selector.
- Forgiving selector-list contexts drop invalid relative local/global mode pseudo branches instead of exporting their classes:
  - `.card:is(:local(> .child), .ok)` becomes `.EgL3uq_card.EgL3uq_ok{color:red}` with no `child` export.
  - `.card:where(:global(~ .sibling), .ok)` becomes `.EgL3uq_card:where(.EgL3uq_ok){color:red}`.
  - `.card:has(:global(~ .sibling), .ok)` becomes `.EgL3uq_card:has(.EgL3uq_ok){color:red}`.
  - `.card:nth-child(odd of :local(+ .sibling), .ok)` becomes `.EgL3uq_card:nth-child(odd of .EgL3uq_ok){color:red}`.

## Implementation

- `CssModulesTransformer::assertCssModulesFunctionalSelector()` now rejects relative-leading `>`, `+`, and `~` arguments in `:local()` and `:global()` with the upstream `Invalid empty selector` diagnostic.
- Existing forgiving selector-list handling now drops those invalid branches and avoids merging candidate local exports from the dropped selector.
- The focused CSS Modules test preserves `composes` metadata while proving dropped relative branches do not export `child`, `legacy`, or `row`.
- `wordpress-css-modules-local-global-diagnostics.php` now exercises the user-visible WordPress path for a dropped local branch in `:is()` plus strict relative `:local()` diagnostics.

## Evidence

- Red-first PHP spot-check before the fix: `:local(> .child)` serialized as `>.EgL3uq_child{color:red}`, and `.card:is(:local(> .child), .ok)` serialized as `.EgL3uq_card:is(>.EgL3uq_child,.EgL3uq_ok){color:red}` with a `child` export.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-local-global-diagnostics.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 588 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-local-global-diagnostics.php --self-test` => `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 7994 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` => passed.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused CSS Modules test evidence moves from `580` to `588` assertions.
- Full LightningCSS PHP evidence moves from `7986` to `7994 pass / 0 fail`.
- Conservative mapped coverage remains `2392 / 3532`; this is an upstream-backed refinement inside the existing CSS Modules local/global/composes surface.

## Dependency Closure

No new support component is needed. This reuses the lane-local CSS Modules selector scanner, functional pseudo validation, forgiving selector-list branch handling, and existing export/composes metadata pipeline. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

## Non-Overlap

This does not repeat accepted transparent single `:is()` unwrapping, terminal pseudo elements, selector comments, invalid escaped selector newline handling, language pseudos, `:host` / `::slotted` / `::cue`, nested composes diagnostics, source-index bundle composes, source-map parity, bundle/import graph parity, media-query parity, CSSOM read/write work, custom at-rule visitor work, property-value minification, or target-prefixing. It only closes relative-leading CSS Modules `:local()` / `:global()` selector parity in strict and forgiving selector contexts while preserving compose metadata.
