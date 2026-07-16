# CSS Modules Global Unused-Symbol Compose Parity 2026-05-31T20:30Z

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260531T203002Z`

Source truth:

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream area: `src/lib.rs::test_unused_symbols`, `src/selector.rs::is_unused`, and CSS Modules global/local selector printing.
- Native NAPI spot-check at the pinned cache confirmed `unusedSymbols: ["legacy"]` keeps `:global(.legacy) .card` after CSS Modules scoping, while pruning the local `.legacy` export/rule and preserving the local `.card` export.

Implementation:

- `CssModulesTransformer` now uses a selector-specific unused-symbol set after CSS Modules scoping.
- Selector pruning matches scoped local/export names, and no longer treats raw public global class names as unused local symbols just because the text matches an `unusedSymbols` entry.
- At-rule and custom-property pruning still use the broader upstream-compatible unused-symbol set, so keyframes, counter styles, dashed idents, and custom properties keep their accepted pruning behavior.
- `wordpress-css-modules-unused-symbols.php` now smokes a public legacy block selector wrapping a surviving composed local class.

Evidence:

- Pre-fix PHP spot-check for `:global(.legacy) .card { color: red } .legacy { color: blue } .card { color: green }` with `unusedSymbols: ["legacy"]` emitted only `.EgL3uq_card{color:green}`.
- Pinned native upstream NAPI emitted `.legacy .EgL3uq_card{color:red}.EgL3uq_card{color:green}` with only the `card` export.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php && php -l lanes/lightningcss/tests/CssModulesTransformerTest.php && php -l lanes/lightningcss/examples/wordpress-css-modules-unused-symbols.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` passed: `1 test files, 251 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 4155 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-unused-symbols.php --self-test` passed: `OK`.
- `git diff --check -- lanes/lightningcss` passed.
- Root harness status: not run - isolated micro-slice.

Status delta:

- Focused CSS Modules evidence moves from `246` to `251` assertions.
- Full LightningCSS PHP evidence moves from `4150` to `4155 pass / 0 fail`.
- Conservative mapped coverage remains `2078 / 3532`; this deepens the already mapped CSS Modules `unusedSymbols` cluster rather than adding a new denominator row.

Dependency closure:

No new support component is needed. This reuses the lane-local CSS Modules transformer, selector scanner, export metadata model, and minifier/nesting pipeline. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

Non-overlap:

This does not repeat accepted CSS Modules local/global selector-list validation, functional `:local()` composes rejection, escaped selector/composes identifiers, declaration-priority `composes`, pure no-check/license handling, animation/keyframes scoping, counter-style/list-style scoping, grid, container, scope, dashed-ident, view-transition, content-hash/project-root hashing, dependency flattening, bundled repeated source-index composes, or the broad accepted unusedSymbols pruning slice. It only fixes the local/global boundary where `unusedSymbols` must prune scoped local symbols without deleting matching public `:global(...)` selectors, and proves surviving local `composes` metadata remains intact.
