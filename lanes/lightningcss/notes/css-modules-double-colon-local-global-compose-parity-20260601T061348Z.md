# CSS Modules double-colon local/global compose parity

Slice: `lightningcss-css-modules-local-global-compose-parity-20260601T061348Z`

Base: `e62cd70f8878634e62c625c3c5a18ef1e32398d5`

## Source truth

Pinned upstream LightningCSS commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.

Local native NAPI oracle checks against `.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node` confirm:

- `::global(.foo)` and `::local(.foo)` serialize as raw pseudo-elements and export no CSS Modules class.
- `.card::global(.foo)` and `.card::local(.foo)` scope only `.card`.
- `::global .foo` and `::local .foo` keep the pseudo-element raw and scope the descendant class.
- Pure mode rejects function-only double-colon selectors because the selector has no local class or ID.
- `composes` remains valid only for a simple local class selector and rejects selectors containing these pseudo-elements.

The upstream selector parser only treats the single-colon CSS Modules pseudo names as module mode switches; the double-colon form is ordinary selector syntax.

## Change

`CssModulesTransformer` now guards CSS Modules pseudo detection so the second colon in `::global` and `::local` cannot be interpreted as a module mode switch. Raw selector pseudo-function scanning also treats double-colon `::global()` and `::local()` as opaque pseudo-functions so their arguments are not scoped.

The focused test adds local/global double-colon pseudo-element coverage across output serialization, export metadata, pure mode, and `composes` validation. The WordPress example covers the same block-style path with deterministic hash output and invalid-composes diagnostics.

## Verification

- `php -l lanes/lightningcss/src/CssModulesTransformer.php && php -l lanes/lightningcss/tests/CssModulesTransformerTest.php && php -l lanes/lightningcss/examples/wordpress-css-modules-double-colon-pseudos.php`
  - `No syntax errors detected` for all changed/new PHP files.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php`
  - `1 test files, 429 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 6414 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-double-colon-pseudos.php --self-test`
  - `OK`.
- `git diff --check -- lanes/lightningcss`
  - clean.

Focused CSS Modules assertion delta: `418 -> 429` (`+11`). Full lane evidence moves `6403 -> 6414` assertions.

## Non-overlap

This slice does not repeat the accepted single-colon `:local()` / `:global()` mode-switch behavior, escaped pseudo-name parsing, comment-delimited composes handling, invalid-composes declaration-name guards, CSS Modules bundle dependency metadata, CSSOM, source-map, bundle/import graph, media-query, custom at-rule, property-value, or target-prefix slices. It closes the double-colon pseudo-element boundary inside the already represented CSS Modules local/global/composes cluster.

## Dependency closure

No new support component is required. The implementation reuses the existing selector scanner, CSS identifier decoder, CSS Modules export/composes validator, focused PHP test harness, and example self-test path. The native NAPI module was used only as a local oracle, not as runtime support.
