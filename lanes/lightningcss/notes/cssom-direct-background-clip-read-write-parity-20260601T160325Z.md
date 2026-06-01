# LightningCSS CSSOM Direct Background Clip Read/Write Parity

Slice: `lightningcss-cssom-declaration-read-write-parity-20260601T160325Z`

## Source Truth

- Pinned upstream commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream property mapping: `src/properties/mod.rs` registers `background-clip`, `-webkit-background-clip`, and `-moz-background-clip` through the `BackgroundClip` property with vendor prefixes.
- Upstream enum/serialization: `src/properties/background.rs` `BackgroundClip` serializes keyword values as lowercase CSS idents, including `border-box`, `padding-box`, `content-box`, `border`, and `text`.
- Native addon probe at the pinned cache confirmed direct authored values such as `Text`, `Padding-Box`, and `Content-Box` print as lowercase `text`, `padding-box`, and `content-box`.

## Behavior Added

- `DeclarationBlock` now normalizes direct `background-clip`, `-webkit-background-clip`, and `-moz-background-clip` CSSOM values through read/write paths.
- Known background-clip keywords are canonicalized case-insensitively, including comma-separated layer lists.
- Unknown/unparsed values such as `var(--wp--custom--clip)` are preserved instead of over-normalized.
- Custom properties with similar names remain case-preserving and unaffected.
- `wordpress-background-cssom.php` now smokes WebKit text-clipping CSSOM read, set, and remove paths for block cover text clipping. The example expected output also matches current default-repeat shorthand serialization.

## Verification

- `php -l lanes/lightningcss/src/DeclarationBlock.php && php -l lanes/lightningcss/tests/DeclarationBlockTest.php && php -l lanes/lightningcss/examples/wordpress-background-cssom.php` -> passed.
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 1312 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 8634 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-background-cssom.php --self-test` -> `OK`.
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "LightningCSS JSON OK\n";'` -> `LightningCSS JSON OK`.
- `git diff --check -- lanes/lightningcss` -> passed.

## Status Delta

- Full LightningCSS lane evidence moves from `8621` to `8634` PHP assertions.
- This slice adds one focused DeclarationBlock case; measured full-lane assertion growth is `+13` because the local runner counts structured `same()` comparisons by nested comparison units.
- Conservative mapped coverage remains `2398 / 3532` because this deepens the already represented DeclarationBlock CSSOM cluster.

## Non-Overlap

This slice avoids the already accepted background shorthand/longhand CSSOM, background-size token-boundary CSSOM, background-clip target-prefixing, prefixed text-overflow CSSOM, direct enum CSSOM, transform CSSOM, mask CSSOM, border CSSOM, grid CSSOM, CSS Modules, source-map, bundle/import graph, custom-at-rule, media-query, and property-value clusters. It only covers direct `background-clip` declaration CSSOM normalization for prefixed and unprefixed declarations.

## Dependency Closure

No new support component is needed. The existing `DeclarationBlock` parser, top-level list splitter, and CSSOM get/set/remove serialization paths are reused.

## Follow-Up

Full upstream Rust, Node, and WASM LightningCSS runners remain unexecuted in this isolated micro-slice.
