# CSS Modules Nth-Child Formula Local/Global Diagnostics

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T122250Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream NAPI oracle from `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node` confirmed:
  - `.card:nth-child(:local(.foo)) { color: red }` rejects with `Unexpected token Colon`.
  - `.card:nth-child(2n + :global(.foo)) { color: red }` rejects with `Unexpected token Colon`.
  - `.card:nth-child(2n of :local(.foo)) { color: red }` remains valid and scopes the selector after `of`.

## Implementation

- `CssModulesTransformer` now validates the formula segment of `:nth-child()` and `:nth-last-child()` before minifying it.
- The validation only applies before the optional `of` selector list, so existing valid `:local()` and `:global()` selector rewriting after `of` is preserved.
- `CssModulesTransformerTest` adds exact diagnostic coverage, including escaped `:local`/`:global` names and a preceding `composes` rule that must not turn the invalid formula into emitted CSS.
- `wordpress-css-modules-nth-child-formula.php` now self-tests both valid WordPress block nth-child selectors and invalid mode-pseudo diagnostics.

## Verification

- Red-first PHP probe before the patch accepted `.card:nth-child(:local(.foo)) { color: red }` as raw formula text.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php && php -l lanes/lightningcss/tests/CssModulesTransformerTest.php && php -l lanes/lightningcss/examples/wordpress-css-modules-nth-child-formula.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> `1 test files, 571 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-nth-child-formula.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 7698 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> passed.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused CSS Modules assertions: `567 -> 571`.
- Full LightningCSS lane assertions: `7694 -> 7698`.
- `lane-status.json` `phpPass`: `7694 -> 7698`.
- Conservative mapped coverage remains `2374 / 3532`; this deepens the already represented CSS Modules local/global/composes cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP CSS Modules selector scanner, `nth-child` formula minifier, local/global mode-pseudo detector, CSS escape decoder, export metadata model, and existing WordPress example self-test harness.

## Non-Overlap

This does not repeat accepted nth-child selector-list rewriting after `of`, forgiving selector-list filtering, quoted `:lang()` and `:dir()` parsing, escaped local/global delimiter diagnostics, terminal pseudo-element handling, unknown at-rule preservation, composes-before-nested ordering, source-map, bundle/import graph, CSSOM, media-query, custom-at-rule, property/value, or target-prefixing slices. It is limited to upstream diagnostics for CSS Modules mode pseudos in the formula segment of nth-child pseudo-classes.

## Next Task

Continue with non-overlapping CSS Modules selector diagnostics, CSS Modules source-map/import graph behavior, or another current-priority LightningCSS parity gap.
