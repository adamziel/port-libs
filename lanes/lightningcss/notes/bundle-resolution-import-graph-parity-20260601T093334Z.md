# LightningCSS Bundle Resolution Import Graph Parity - 2026-06-01T09:33Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T093334Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Native oracle: `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node`.
- Targeted upstream `bundleAsync({ minify: true })` probes confirmed:
  - `@import url(/* generated */ blocks/card.css);` fails before resolver traversal with `Unexpected token BadUrl("/* generated */ blocks/card.css")` at `/entry.css:1:8`.
  - `@import url(/*a*/ "pkg:card.css" /*b*/);` fails before resolver traversal with `Unexpected token BadUrl("/*a*/ \"pkg:card.css\" /*b*/")` at `/entry.css:1:8`.
  - `@import url(blocks/card hero.css);`, `@import url(blocks/card(hero).css);`, and backslash-newline raw URL sources produce upstream BadUrl diagnostics at the import token boundary.
  - Quoted `url("blocks/card.css" extra)`, `url("blocks/card.css" "theme.css")`, and `url("blocks/card.css", screen)` report `Ident`, `String`, and `Comma` unexpected-token diagnostics at column 30.
  - Token-valid raw URL text such as `url(/*x*/blocks/card.css)` is preserved literally as the resolver specifier instead of being treated as CSS comments.

## Native Delta

- `CssBundler` now reports malformed raw `url(...)` import sources as upstream-style `BadUrl` diagnostics at the `@import` token boundary before resolver reads.
- Quoted `url(...)` import sources now reject trailing identifier/string/comma tokens with upstream token names and source locations.
- Raw `url(...)` import parsing now skips only CSS whitespace around the source. It no longer strips comment-shaped text, so token-valid specifiers like `/*x*/blocks/card.css` reach the resolver literally.
- `wordpress-bundle-import-graph.php` now includes a user-visible smoke for whitespace-preserved imports and a rejected comment-shaped URL import source.

## Evidence

- Red-first PHP behavior before this patch resolved comment-stripped `url(/* generated */ blocks/card.css)` imports and reported BadUrl column 9 during the first local parser attempt; upstream rejects before resolver traversal at column 8.
- `php -l lanes/lightningcss/src/CssBundler.php` - no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` - no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` - no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` - `1 test files, 677 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 7192 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` - passed, including `commented-url-import: rejected-before-resolution`, `bad-quoted-url-import: rejected-before-resolution`, and `bad-url-import: rejected-before-resolution`.
- `git diff --check -- lanes/lightningcss` - passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence moved `7172 -> 7192` assertions.
- Conservative mapped coverage remains `2365 / 3532`; this deepens the already represented bundle/import graph source-token and diagnostics cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the native PHP `CssBundler`, resolver callback boundary, CSS tokenizer helpers, `CssBundleException` diagnostics, and the existing WordPress bundle import-graph smoke. It does not require Node, Rust, WASM, network resolution, browser services, external package managers, or credentialed providers at runtime.

## Non-Overlap

This does not repeat accepted literal-null import source handling, surrogate escape handling, CRLF hex escapes, escaped import delimiters, import media/supports/layer parsing, external import ordering, source-map remapping, CSS Modules dependency graph handling, media-query, CSSOM, target-prefixing, property-value, or custom-at-rule clusters. The patch is limited to upstream `url(...)` import source tokenization and resolver-boundary diagnostics.

## Follow-Up

Remaining bundle/import graph parity work includes resolver diagnostic ordering edges that do not depend on upstream parallel callback order and source-map remapping gaps through CSS Modules dependency imports not already covered by current PHP tests.
