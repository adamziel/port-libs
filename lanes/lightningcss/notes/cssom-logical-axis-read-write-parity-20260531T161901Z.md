# CSSOM Logical Axis Read Write Parity 2026-05-31T16:19Z

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T161901Z`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/declaration.rs::DeclarationBlock::{get,set,remove}` defines shared CSSOM behavior for shorthand reads, longhand writes into compatible shorthands, priority buckets, logical-vs-physical category boundaries, and shorthand splitting on removal.
- `src/properties/margin_padding.rs` defines `size_shorthand!` logical axis groups for `margin-block`, `margin-inline`, `padding-block`, `padding-inline`, `scroll-margin-block`, `scroll-margin-inline`, `scroll-padding-block`, `scroll-padding-inline`, `inset-block`, and `inset-inline`.
- `src/properties/mod.rs` marks the corresponding start/end longhands as logical category properties, separate from physical top/right/bottom/left longhands.

## Native PHP Delta

- `DeclarationBlock::getProperty()` now reads logical axis longhands from axis shorthands and composes axis shorthands from matching start/end longhands only when priority flags match.
- `DeclarationBlock::setProperty()` now updates same-priority logical axis shorthands in place when setting a compatible axis longhand, while preserving upstream physical fallback boundaries by appending after later physical declarations.
- `DeclarationBlock::removeProperty()` now removes logical axis shorthands with their start/end longhands and splits containing axis shorthands into surviving logical longhands when removing a single axis longhand.
- `examples/wordpress-logical-spacing-cssom.php` now self-tests block/theme spacing edits that read theme spacing tokens from logical shorthands, update the shorthand path, preserve physical fallbacks, and remove one side without Node.

## Evidence

- Baseline focused check before this slice: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` => `1 test files, 301 assertions, 0 failures`.
- Focused check after implementation: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` => `1 test files, 320 assertions, 0 failures`.
- Full lane check: `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 2111 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-logical-spacing-cssom.php --self-test` => `OK`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

- This does not repeat accepted SourceProvider, escaped CSS Modules identifier, env()/var() visitor, text-decoration CSSOM, unknown media range, SourceMap project-root, outline, background, border, transition, scroll-snap physical rect, or inset physical rect CSSOM slices.
- The only visible LightningCSS rework note is the stale 2026-05-25 `CustomMediaTransformer` import-tail conflict against base `02383337`; current accepted lane status already records later custom-media integrations, so this slice stayed on the assigned DeclarationBlock CSSOM behavior.

## Dependency Closure

No new support component is needed. This reuses the bounded native `DeclarationBlock` declaration parser, priority bucket partitioning, top-level whitespace splitter, and CSSOM serializer.
