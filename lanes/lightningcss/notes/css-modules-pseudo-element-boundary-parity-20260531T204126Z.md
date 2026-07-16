# LightningCSS CSS Modules Pseudo-Element Boundary Parity 2026-05-31T20:41Z

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260531T204126Z`

Source truth:

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream reads: `src/selector.rs` CSS Modules selector serialization / pure selector helpers and `src/css_modules.rs` composes simple-selector validation.
- Native upstream spot-check used the pinned local NAPI artifact at `.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node`: `:host(:global(.wp-block)) .card`, `::slotted(.card)`, and `.card::before:hover` serialize with CSS Modules scoping; `::slotted(.card) .child`, `::slotted(:global(.wp-block)) .card`, `::slotted(.card):hover`, `.card::before .child`, and pseudo-element selectors with `composes` reject.

Implementation:

- `CssModulesTransformer` now validates pseudo-element selector tails before rewriting CSS Modules local/global selectors.
- Valid `:host(:global(...))`, `::slotted(.local)`, and `::before:hover` selectors still scope local classes and preserve composed export metadata.
- Invalid pseudo-element tails inside top-level selectors and nested `:global(...)` / `:local(...)` selectors now fail before any export metadata is emitted.
- `wordpress-css-modules-transformer.php` now covers a build-free block module path with host/slotted public markup selectors plus invalid pseudo-element boundary and composes guards.

Evidence:

- Pre-fix PHP spot-check accepted `::slotted(:global(.wp-block)) .card { color: red }` and emitted `::slotted(.wp-block) .EgL3uq_card{color:red}`; upstream rejects it before printing.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php` passed.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-transformer.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` passed: `1 test files, 257 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 4192 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-transformer.php --self-test` passed: `OK`.
- `git diff --check -- lanes/lightningcss` passed.
- Root harness status: not run - isolated micro-slice.

Status delta:

- Focused `CssModulesTransformerTest.php` moves from `246` to `257` assertions.
- Full LightningCSS PHP evidence moves from `4181` to `4192 pass / 0 fail`.
- Conservative mapped coverage remains `2078 / 3532`; this deepens the already represented CSS Modules local/global selector and composes cluster.

Dependency closure:

- No new support component is needed. This reuses the lane-local CSS Modules selector scanner, CSS identifier parser, composes validation, `NestingTransformer`, and `CssMinifier` output path. No Node, Rust, WASM, browser service, or external CSS parser is required at runtime.

Non-overlap:

- This does not repeat accepted CSS Modules selector-list validation, nested local/global precedence, escaped identifiers/specifiers, functional `:local()` composes rejection, pure-mode no-check comments, `unusedSymbols`, source-index compose flattening, import graph behavior, or bundled dependency diagnostics. It only closes pseudo-element selector-tail parity around local/global selectors and composes guards.
