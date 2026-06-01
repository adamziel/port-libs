# LightningCSS Media Query Range Layer Import CSSOM Parity

Slice: `lightningcss-media-query-range-layer-parity-20260601T233455Z`
Base accepted HEAD: `3d6fc5c9e346303cd3c099928f4115ca70463840`
Upstream source truth: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Behavior

This slice covers CSSOM-style parsing of top-level and nested `@import` statements whose media tails contain Media Queries 4 range syntax after import source, `layer` / `layer(...)`, and `supports(...)` modifiers.

Pinned upstream source truth:

- `src/parser.rs` parses `@import` as source, optional `layer` / `layer(...)`, optional `supports(...)`, then `MediaList`.
- `src/rules/import.rs` serializes import source and modifiers before writing the media list.
- `src/media_query.rs` serializes `MediaList` entries through `QueryFeature::to_css`, which canonicalizes range syntax such as `(min-width: 48rem)` to `(width>=48rem)`.

Before this slice, `StylesheetParser` normalized block-form `@media` preludes but left statement-form `@import` media tails raw, so CSSOM callers saw:

```text
url("blocks/query.css") layer(theme.blocks) screen and (min-width: 48rem), (hover)
```

The parser now preserves the import source and modifiers while normalizing only the media tail:

```text
url("blocks/query.css") layer(theme.blocks) screen and (width>=48rem),(hover)
```

## Files

- `src/StylesheetParser.php`: normalizes statement-form `@media` and `@import` preludes, adds a bounded import-tail scanner for source/layer/supports/media boundaries, and reuses `MediaQueryParser` for the media tail.
- `tests/StylesheetParserTest.php`: adds layered import media range assertions, including a stripped-comment gap between import modifiers.
- `examples/wordpress-cssom-media-range-layer.php`: adds a WordPress block CSSOM smoke for a top-level layered import before the existing layer/media rule tree.
- `UPSTREAM_TEST_MANIFEST.json` and `lane-status.json`: record focused evidence and keep conservative mapped coverage unchanged.

## Verification

```text
php tools/run-tests.php lanes/lightningcss/tests/StylesheetParserTest.php
1 test files, 54 assertions, 0 failures

php tools/run-tests.php lanes/lightningcss/tests
14 test files, 9148 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-cssom-media-range-layer.php --self-test
OK
```

Final PHP lint, JSON validation, and `git diff --check -- lanes/lightningcss` were run after note/status/manifest edits and passed.

## Counting

`phpPass` moves `9138 -> 9148` from the full LightningCSS lane run. Mapped coverage remains `2439 / 3532` because this deepens already represented media-query range/layer, import-tail, and CSSOM at-rule statement clusters rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. The slice reuses the existing PHP `StylesheetParser`, `CssRule`, and `MediaQueryParser` paths.

## Non-overlap

This does not repeat the accepted mixed all/not-all media-list lowering in `TransitionPrefixer`, layered import-tail minifier behavior, CSSOM nested layer statement source-path work, source-map VLQ parity, CSS Modules selector/composes behavior, custom at-rule visitors, target-prefixing, or upstream runner evidence. It is scoped to CSSOM parser readout of statement-form layered `@import` media range tails.
