# LightningCSS CSS Modules Escaped Local/Global Pseudo Compose Parity 2026-05-31T22:30Z

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260531T223014Z`

Source truth:

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream area: `src/selector.rs` CSS Modules functional pseudo parsing and `src/css_modules.rs::CssModule::handle_composes`.
- Native pinned NAPI spot-check using `lightningcss.linux-x64-gnu.node` confirmed CSS escaped pseudo names decode before CSS Modules handling: `:glo\62 al(.wp-block) .card` prints `.wp-block .EgL3uq_card`, `:lo\63 al(.card)` prints `.EgL3uq_card`, bare escaped `:global` / `:local` reject as ambiguous CSS module classes, and a simple `.button { composes: base }` keeps local compose metadata beside an escaped global selector rule.

Implementation:

- `CssModulesTransformer` now detects CSS Modules `:local(...)` and `:global(...)` by reading the CSS identifier token after `:` and comparing the decoded name, instead of matching only literal source text.
- Escaped functional pseudos now unwrap into local/global mode before selector rewriting, pure-mode validation, and pseudo-element boundary checks.
- Escaped bare `:local` / `:global` pseudo names now hit the existing functional-syntax guard instead of passing through as inert custom pseudos.
- `wordpress-css-modules-escaped-pseudos.php` models build-free block CSS where escaped pseudo names appear in migration output while local and global `composes` exports still flatten for runtime class lists.

Evidence:

- Red-first PHP spot-check before the fix scoped `.wp-block` inside `:glo\62 al(...)`, emitted `:glo\62 al(.EgL3uq_wp-block)`, and exported a bogus `wp-block` local.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 304 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 4657 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-escaped-pseudos.php --self-test` => `OK`.

Status delta:

- Full LightningCSS PHP evidence moves from `4649` to `4657 pass / 0 fail`.
- Conservative mapped coverage remains `2167 / 3532`; this deepens the already represented CSS Modules local/global/composes selector cluster rather than adding a new denominator row.

Dependency closure:

- No new support component is needed. This reuses the lane-local CSS Modules selector scanner, CSS identifier escape decoder, pure-mode validator, `NestingTransformer`, `CssMinifier`, and example self-test harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

Non-overlap:

- This does not repeat accepted CSS Modules selector-list validation, nested global/local precedence for literal pseudos, escaped class/composes identifiers, escaped dependency specifiers, functional `:local()` composes rejection, pure no-check/license handling, attribute selector serialization, pseudo-element boundaries, host-context behavior, unusedSymbols pruning, grid/scope/container/dashed-ident/view-transition/content-hash handling, source-index compose flattening, or bundle dependency diagnostics. It only closes CSS-escaped spellings of the `:local` and `:global` pseudo names before local/global/composes export handling.
