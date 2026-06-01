# CSS Modules Escaped View-Transition Function Parity

Slice: `lightningcss-css-modules-local-global-compose-parity-20260601T112044Z`

Source truth:

- Pinned upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Native oracle command used `lightningcss.linux-x64-gnu.node` with `cssModules: true` and confirmed escaped selector-function names canonicalize before CSS Modules argument scoping:
  - `:active-view-transition-\74 ype(card, page)` emits `:active-view-transition-type(<scoped-card>,<scoped-page>)`.
  - `::view-transition-\67 roup(card.thumb)` emits `::view-transition-group(<scoped-card>.<scoped-thumb>)`.
  - `::view-transition-\6f ld(public-card)` inside `:global(...)` emits canonical `::view-transition-old(public-card)`.
- The same oracle rejects `:local(...)` / `:global(...)` mode pseudos inside escaped view-transition selector-function arguments.

Implementation:

- `CssModulesTransformer::viewTransitionSelectorFunctionAt()` now decodes the selector-function identifier with the existing CSS identifier reader, returns the raw open-parenthesis offset, and emits the canonical lower-case upstream pseudo function name.
- This preserves composes exports while making escaped pseudo-function names flow through the existing argument scoping and invalid local/global guard.
- `wordpress-css-modules-view-transition-guards.php` now covers escaped function names in the WordPress view-transition smoke path.

Verification:

- `php -l lanes/lightningcss/src/CssModulesTransformer.php` - pass
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` - pass
- `php -l lanes/lightningcss/examples/wordpress-css-modules-view-transition-guards.php` - pass
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` - `1 test files, 544 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-css-modules-view-transition-guards.php --self-test` - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 7533 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss` - pass

Status delta:

- `phpPass`: `7526 -> 7533`
- Conservative mapped coverage remains `2369 / 3532`; this deepens the already represented CSS Modules local/global/composes and view-transition selector-function cluster.
- Full upstream Rust/Node/WASM runners were not executed for this isolated micro-slice.

Dependency closure:

- No new support component is needed. The patch reuses the existing CSS escape/identifier tokenizer in the native PHP CSS Modules transformer.

Non-overlap:

- Avoided the already accepted CSS Modules escaped custom-ident declaration/argument scoping slice; this slice is specifically escaped selector-function names and their local/global compose guard behavior.
