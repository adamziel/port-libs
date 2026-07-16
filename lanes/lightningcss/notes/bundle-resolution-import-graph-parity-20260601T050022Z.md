# LightningCSS Bundle Resolution Import Graph Parity 2026-06-01

## Source Truth

- Upstream source: `parcel-bundler/lightningcss` pinned at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Static references: `src/lib.rs::test_import`, `src/bundler.rs`, and `node/test/bundle.test.mjs`.
- Rework context: inspected the stale May 25 lane rework note for import media-tail handling. This slice addresses the same scanner boundary on the current accepted base without replaying the stale patch.

## Behavior

Generated CSS may escape import-prelude identifiers such as `url`, `layer`, and `supports`. Native custom-media resolution now decodes those CSS identifiers before deciding where an `@import` media tail begins. That preserves custom-media-looking tokens inside escaped `supports(...)` guards while resolving only the actual media tail aliases.

Before the fix, this probe rewrote the escaped supports guard:

```text
@custom-media --wide (min-width: 782px);
@import "tokens.css" s\75pports((--wide)) screen;
```

to `supports(min-width:782px) screen`, which incorrectly treated the supports condition as the media tail. The current behavior preserves `supports(--wide)` and resolves trailing media aliases such as `screen and (--wide)`.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/CustomMediaTransformerTest.php` -> `1 test files, 41 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-custom-media-transformer.php` -> exit 0; emitted escaped-import WordPress query-card CSS with `supports((--wp-wide))` preserved and media-tail aliases resolved
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 6111 assertions, 0 failures`
- `php -l lanes/lightningcss/src/CustomMediaTransformer.php` -> no syntax errors
- `php -l lanes/lightningcss/tests/CustomMediaTransformerTest.php` -> no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-custom-media-transformer.php` -> no syntax errors
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'` -> `json ok`
- `git diff --check -- lanes/lightningcss` -> passed

## Status Delta

- Focused PHP assertions: `6110 -> 6111`.
- Conservative mapped coverage remains `2348 / 3532`; this deepens the already represented bundle/custom-media import-tail cluster.
- Full upstream Rust/Node/WASM runners were not executed for this isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing `CustomMediaTransformer` scanner, CSS escape decoder, media-query resolver, `CssMinifier`, and native bundler post-processing path.

## Non-Overlap

Avoided accepted media-query explicit/layer parsing, source-map raw VLQ offsets, target-prefix supports declaration boundaries, bundle supports-condition grouping, quoted import URL token-boundary diagnostics, and CSS Modules/source-map work. The patch is limited to escaped import modifier scanning before custom-media media-tail resolution.
