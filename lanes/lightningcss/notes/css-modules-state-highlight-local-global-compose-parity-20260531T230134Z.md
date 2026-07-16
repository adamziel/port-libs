# LightningCSS CSS Modules State/Highlight Local Global Compose Parity 2026-05-31T23:01Z

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260531T230134Z`

Source truth:

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream reads:
  - `src/selector.rs`, where `:state()` parses a `CustomIdent`, `::highlight()` parses a highlight `CustomIdent`, and CSS Modules `:global()` temporarily removes `dest.css_module` while serializing its selector.
  - `src/css_modules.rs::CssModule::handle_composes`, preserving the existing local `composes` export path.
- Native pinned NAPI oracle confirmed:
  - `.card:state(open)` emits `.EgL3uq_card:state(EgL3uq_open)` and exports `open`.
  - `.card::highlight(focus-ring)` emits `.EgL3uq_card::highlight(EgL3uq_focus-ring)` and exports `focus-ring`.
  - `:global(.legacy:state(public)) .card` and `:global(.legacy::highlight(public-ring)) .card` leave `public` / `public-ring` unscoped while still scoping `.card`.

Implementation:

- `CssModulesTransformer` now recognizes CSS Modules selector custom-ident functions for one-colon `:state(...)` and two-colon `::highlight(...)`.
- Local-mode selector custom identifiers are scoped/exported through the existing CSS Modules pattern and identifier escaping path.
- Global-mode selector custom identifiers are validated and serialized without local scoping, matching upstream `:global(...)` behavior.
- `::highlight(...)` now participates in pseudo-element tail validation, so selectors like `.card::highlight(foo) .title` reject before output.
- The new WordPress example models editor state and highlight selectors for block CSS while preserving a local `composes: reset` class list.

Evidence:

- Red-first PHP spot-check before the implementation emitted `.EgL3uq_card:state(open)` and did not export `open`.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 315 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 4709 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-state-highlight.php --self-test` => `OK`.
- Root harness status: not run - isolated micro-slice.

Status delta:

- Focused CSS Modules assertions move from `308` to `315`.
- Full LightningCSS PHP evidence moves from `4702` to `4709 pass / 0 fail`.
- Conservative mapped coverage remains `2174 / 3532`; this deepens the already represented CSS Modules local/global/composes cluster rather than claiming a new denominator row.

Dependency closure:

- No new support component is needed. This reuses the lane-local CSS Modules selector scanner, custom-ident scoping, export metadata model, pseudo-element boundary validation, and WordPress example harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

Non-overlap:

- This does not repeat accepted CSS Modules selector-list validation, escaped `:local` / `:global` pseudo names, escaped class/composes identifiers, escaped dependency specifiers, functional `:local()` composes rejection, pseudo-element `:host` / `::slotted` boundaries, host-context raw selector preservation, attribute selector serialization, invalid composes fallback, pure no-check/license handling, unusedSymbols pruning, animation/grid/container/scope/dashed-ident/view-transition/content-hash handling, source-index compose flattening, or bundle dependency diagnostics.
