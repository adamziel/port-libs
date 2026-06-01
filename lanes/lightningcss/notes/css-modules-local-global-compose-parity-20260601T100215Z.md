# LightningCSS CSS Modules Local Global Compose Parity

Slice: `lightningcss-css-modules-local-global-compose-parity-20260601T100215Z`

Source truth:
- Upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Direct upstream Node NAPI oracle used `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node`.
- Upstream rejects escaped CSS Modules pseudo-function opening delimiters such as `:global\(.legacy) .card`, `:global\28 .legacy) .card`, `.card:global\(.legacy)`, and `:local\(.card)` with `Unexpected token CloseParenthesis`.
- Upstream still accepts escaped close parentheses inside identifiers, including `:global(.wp\)button) .card`, `:local(.card\)wide)`, and `composes: card\)wide`.

Pre-change probe:
- The PHP port previously accepted `:global\(.legacy) .card { color: red }` and scoped it as ordinary local selector text before composes/export processing.
- That differed from upstream, which treats the escaped opening parenthesis as not starting a CSS Modules pseudo function and then reports the unmatched raw close parenthesis.

Implementation:
- `CssModulesTransformer` now validates unexpected raw close parentheses in selectors before selector rewriting and export composition.
- The validator respects quoted strings, CSS escapes, and attribute selector contents, so escaped close-parenthesis identifiers remain valid.
- `CssModulesTransformerTest` now covers the valid escaped close-parenthesis identifier/compose path and five invalid escaped `:local`/`:global` opening-delimiter forms.
- The WordPress CSS Modules local/global diagnostics smoke now includes the escaped `:global\(` rejection.

Verification:
- `php -l lanes/lightningcss/src/CssModulesTransformer.php && php -l lanes/lightningcss/tests/CssModulesTransformerTest.php && php -l lanes/lightningcss/examples/wordpress-css-modules-local-global-diagnostics.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> `1 test files, 514 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-local-global-diagnostics.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 7301 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> passed.

Status delta:
- Full LightningCSS lane assertions moved from `7293` to `7301` with `0` failures.
- `lanes/lightningcss/lane-status.json` `phpPass` updated to `7301`; mapped denominator remains `2365 / 3532`.

Dependency closure:
- No new support component is needed. This slice reuses the existing native PHP selector scanner, CSS escape handling, CSS Modules export composer, and diagnostics example harness.

Non-overlap:
- This is a bounded CSS Modules parser parity cluster for escaped `:local`/`:global` delimiters and compose preservation.
- It does not touch the accepted bundle/import graph, source-map, CSSOM read/write, target-prefixing, media query, custom at-rule visitor, terminal pseudo-element, escaped newline, selector comment, or escaped custom-ident compose surfaces.
- Root harness was not run; this isolated micro-slice used lane-focused verification only.
