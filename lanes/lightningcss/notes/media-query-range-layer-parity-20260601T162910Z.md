## Media Query Range Layer Parity 2026-06-01T162910Z

Source truth:
- Upstream LightningCSS commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/lib.rs::test_media` and `src/media_query.rs` for range fallback, resolution prefix, and nested at-rule serialization behavior.
- Native binding probes confirmed that statement at-rules before a following `@media` block still allow that `@media` prelude to lower modern range syntax and clone legacy resolution prefixes.

Implemented behavior:
- `TransitionPrefixer` now splits leading top-level statement at-rules from the following block prelude before rewrite dispatch.
- This fixes cases such as `@layer reset, blocks; @media (width >= 240px) { ... }` and `@import ...; @media (width >= 240px) { ... }`, where the previous combined prelude skipped media range fallback rewriting.
- Regression coverage includes simple range fallback, interval range fallback, resolution prefix cloning, and import-before-media statement handling.

Focused evidence:
- `php -l lanes/lightningcss/src/TransitionPrefixer.php`: pass.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`: pass.
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`: pass.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`: `1 test files, 1384 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test`: pass.
- `php tools/run-tests.php lanes/lightningcss/tests`: `13 test files, 8698 assertions, 0 failures`.

Status delta:
- Full lane `phpPass` moves `8694 -> 8698`.
- Mapped upstream coverage remains `2398 / 3532`.
- No root harness, Rust, Node, or WASM upstream runners were executed for this isolated slice.

Dependency closure:
- No new support component is needed. The fix reuses the existing PHP media-query parser, target option resolution, and prefixer traversal.

Next task:
- Continue with non-overlapping LightningCSS media-query/parser recovery parity, especially malformed import/media condition recovery and remaining CSSOM/media-list read-write surfaces.
