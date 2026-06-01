# Media Query Range Layer Mixed List Parity

Slice: `lightningcss-media-query-range-layer-parity-20260601T210535Z`
Base accepted HEAD: `4891303774c9ca404591d2f3a4d35bc9e197e3fb`
Upstream source truth: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Behavior

This slice covers mixed media-query lists where an impossible `not all` or explicit `all` branch is paired with a range-syntax branch inside cascade layers and layered `@import` tails.

Pinned upstream native binding output:

```text
@layer blocks{@media not all,(min-width:240px){.foo{color:#ff0}}}
@layer blocks{@media not all,(min-width:100px) and (max-width:200px){.foo{color:#ff0}}}
@import "blocks/query.css" layer(theme.blocks) not all,(min-width:240px);@layer theme.blocks{.foo{color:#ff0}}
@import "blocks/query.css" layer(theme.blocks) not all,(min-width:100px) and (max-width:200px);@layer theme.blocks{.foo{color:#ff0}}
```

`src/media_query.rs` keeps each `MediaList` entry during serialization and only lowers the range or interval `QueryFeature` branch when legacy targets require `min-`/`max-` syntax. The PHP tests now assert the same behavior for parser list minification, range lowering, layered `@media`, and layered `@import` media tails.

## Files

- `tests/MediaQueryParserTest.php`: adds mixed `all`/`not all` media-list parser and range-lowering assertions.
- `tests/TransitionPrefixerTest.php`: adds layered block and layered import-tail range fallback assertions for mixed media lists.
- `examples/wordpress-media-range-layer-mixed-list.php`: adds a WordPress block-query smoke for layered media and import tails.
- `UPSTREAM_TEST_MANIFEST.json` and `lane-status.json`: record the focused evidence and keep conservative mapped coverage unchanged.

## Verification

```text
php -l lanes/lightningcss/tests/MediaQueryParserTest.php && php -l lanes/lightningcss/tests/TransitionPrefixerTest.php && php -l lanes/lightningcss/examples/wordpress-media-range-layer-mixed-list.php
No syntax errors detected in lanes/lightningcss/tests/MediaQueryParserTest.php
No syntax errors detected in lanes/lightningcss/tests/TransitionPrefixerTest.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-media-range-layer-mixed-list.php

php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php
2 test files, 2231 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-media-range-layer-mixed-list.php --self-test
OK

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 9100 assertions, 0 failures
```

Final JSON validation and `git diff --check -- lanes/lightningcss` were run after note/status/manifest edits and passed.

## Counting

`phpPass` moves `9084 -> 9100` from the full LightningCSS lane run. Mapped coverage remains `2439 / 3532` because this deepens the already represented media-query range/layer and import-tail clusters.

## Dependency Closure

No new support component is needed. The slice reuses the existing PHP `MediaQueryParser` and `TransitionPrefixer` paths and the existing example self-test pattern.

## Non-overlap

This does not repeat the accepted source-map skipped VLQ, CSSOM nested layer statement path, legacy text `@supports`, color-mix, selector pseudo target-prefixing, or `calc(infinity)` media range clusters. It specifically covers mixed `all`/`not all` media-list preservation while range branches lower inside layer/import-tail contexts.
