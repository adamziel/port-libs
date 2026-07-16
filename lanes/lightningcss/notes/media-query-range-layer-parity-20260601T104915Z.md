# LightningCSS media query CSSOM range layer parity

Slice: `lightningcss-media-query-range-layer-parity-20260601T104915Z`
Base: `e78f87c2f7c92d5cffd9a2382b41cda8d5262de2`

## Upstream source truth

Pinned upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss` at
manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.

Relevant upstream behavior:

- `src/parser.rs` parses native `@media` block preludes through
  `MediaList::parse` and stores them in `CssRule::Media`, not as raw source
  strings.
- `src/lib.rs::test_media` serializes media feature range syntax into canonical
  range form, e.g. `min-width` features print as `width>=...` after parsing and
  minification.
- Layered rule bodies are parsed recursively, so a media rule inside `@layer`
  still carries the parsed media-query AST before serialization.

## Local change

Before this patch, `StylesheetParser` represented `@media` block preludes as
raw source text. A CSSOM read of a layered WordPress stylesheet therefore
reported `screen and (min-width: 48rem), (hover)` even though the native
media-query parser and minifier already canonicalized the same prelude to
`screen and (width>=48rem),(hover)`.

The patch normalizes only `@media` block preludes through `MediaQueryParser`
when building `CssRule` nodes. Other at-rules, style selectors, declarations,
layer names, and statement at-rules keep their existing parser behavior.

`StylesheetParserTest.php` now covers the existing top-level media rule
canonicalization and a nested `@layer theme.blocks { @media ... }` CSSOM read.
`examples/wordpress-cssom-media-range-layer.php` models WordPress block-theme
tooling that inspects layered responsive styles without Node/WASM.

## Verification

- Pre-change focused baseline:
  `php tools/run-tests.php lanes/lightningcss/tests/StylesheetParserTest.php`
  passed with `1 test files, 28 assertions, 0 failures`.
- `php -l lanes/lightningcss/src/StylesheetParser.php`
  - Passed, no syntax errors.
- `php -l lanes/lightningcss/tests/StylesheetParserTest.php`
  - Passed, no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-cssom-media-range-layer.php`
  - Passed, no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/StylesheetParserTest.php`
  - Passed: `1 test files, 37 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-cssom-media-range-layer.php --self-test`
  - Passed.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Passed: `13 test files, 7442 assertions, 0 failures`.

## Status delta

`lane-status.json` `phpPass` moves from `7433` to `7442`, matching the full
lane-focused PHP assertion count after adding the CSSOM media-range assertions.
No mapped upstream denominator row was added; this is an existing media-query
and CSSOM source cluster refinement.

## Non-overlap

This avoids accepted media range math-function folding, resolution prefixing,
feature flag fallbacks, unknown feature fallbacks, import graph conjunctions,
CSS Modules, source-map, target-prefixing, property-value, and custom at-rule
visitor slices. The patch is limited to CSSOM parser reads of media-query
range preludes inside layered rule trees.

## Dependency closure

No new support component is needed. The implementation reuses the existing
native PHP `StylesheetParser`, `CssRule`, and `MediaQueryParser`; the upstream
cache was used only as source-truth evidence.

## Next

Remaining media-query work should focus on parser recovery or CSSOM/import
graph interactions with new source-truth evidence, not another range math
function variant.
