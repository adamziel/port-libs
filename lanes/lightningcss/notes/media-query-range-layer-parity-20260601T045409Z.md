# Media Query Range Layer Parity - Comment Trivia

Slice: `lightningcss-media-query-range-layer-parity-20260601T045409Z`

Base accepted HEAD: `e817cf28276645ddc830afdbe15659359b9f073a`

## Upstream Source Truth

Pinned manifest commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.

Targeted upstream reads:

```sh
sed -n '386,470p' /home/claude/port-libs/.upstream-cache/lightningcss/src/parser.rs
sed -n '1,220p' /home/claude/port-libs/.upstream-cache/lightningcss/src/media_query.rs
```

LightningCSS parses `@import` sources and modifiers before handing the remaining media tail to `MediaList::parse`. That path uses cssparser token streams, where block comments are trivia and act as token separators rather than identifier text.

Red-first focused probes before the implementation showed:

- `MediaQueryParser::minifyList('screen/* migration */and (width >= 240px)')` failed with `Invalid media query condition operand: screen/* migration */`.
- Commented boolean/range media conditions such as `(hover)/* stale */or/* breakpoint */(100px <= width <= 200px)` failed before normalization.
- A layered import media tail like `@import "card.css" layer(theme.blocks) screen/* migration */and (width >= 240px);` failed in `CssBundler` before dependency content was read.

## Implementation

- `MediaQueryParser::minifyList()` now strips CSS block comments as whitespace before media-list splitting, validation, and range normalization.
- The comment stripper preserves quoted strings and escaped characters so URL/string-like media feature values are not rewritten.
- `MediaQueryParserTest.php` covers direct parser parity for comment-separated `and`, `or`, list commas, leading/trailing comments, and EOF comments.
- `CssBundlerTest.php` covers layered `@import` media tails with comment-separated `and` and comma list items, plus repeated import dedupe after canonical media range normalization.
- `wordpress-media-range-layer-import-graph.php` now smokes commented WordPress-style layered media range imports without Node/WASM.

## Verification

```sh
php -l lanes/lightningcss/src/MediaQueryParser.php
php -l lanes/lightningcss/tests/MediaQueryParserTest.php
php -l lanes/lightningcss/tests/CssBundlerTest.php
php -l lanes/lightningcss/examples/wordpress-media-range-layer-import-graph.php
php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/CssBundlerTest.php
php lanes/lightningcss/examples/wordpress-media-range-layer-import-graph.php --self-test
php tools/run-tests.php lanes/lightningcss/tests
git diff --check -- lanes/lightningcss
```

Results:

- PHP lint passed for all changed PHP files.
- Focused media parser and bundler tests: `2 test files, 961 assertions, 0 failures`.
- WordPress media range layered import example smoke passed.
- Full LightningCSS lane: `13 test files, 6066 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` passed.

## Coverage And Handoff Notes

Full lane `phpPass` moves from `6059` to `6066`. Conservative mapped coverage remains `2345 / 3532` because this deepens already represented media-query and bundle/import graph behavior rather than adding a new upstream denominator row.

Dependency closure: no new support component is needed. This reuses the native PHP `MediaQueryParser`, `CssBundler`, and import graph validation path.

Non-overlap: the stale May 25 custom-media rework note targets an older import-tail conflict and remains unrelated. This patch avoids the already accepted normal `@media` comment-token minifier slice by fixing the public media-query parser and bundled layered `@import` media-tail path directly; it also avoids the accepted resolution, target fallback, all/not-all pruning, escaped identifier, and range syntax clusters.
