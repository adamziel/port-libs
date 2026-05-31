# CSSOM Logical Size Read/Write Parity - 2026-05-31

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/declaration.rs::DeclarationBlock::set()` scans declarations from the end and stops in-place replacement when a later declaration has a longhand in the same logical group but the opposite physical/logical category.
- `src/properties/mod.rs` assigns `width`/`height` and `block-size`/`inline-size` to the `Size` logical group, and similarly assigns min/max physical and logical size properties to `MinSize` and `MaxSize`.

## Ported Behavior

- `DeclarationBlock::setProperty()` now applies that upstream append-after-conflict rule to `width`/`height` versus `block-size`/`inline-size`, plus the min/max-size equivalents.
- A logical size declaration before a later physical fallback is no longer overwritten in place; the new logical declaration is appended after the physical fallback, preserving cascade parity.
- Existing direct reads and removals remain generic; this slice only changes the write path for upstream logical-group conflict ordering.

## Evidence

- Added focused `DeclarationBlockTest.php` assertions for `block-size`, `inline-size`, `min-inline-size`, and `max-height` write ordering across physical/logical fallbacks, plus direct read and priority-bucket behavior.
- Added `examples/wordpress-logical-size-cssom.php --self-test` for block-theme cover/query sizing overrides that must preserve physical fallbacks.

## Dependency Closure

- No new support component is needed. The slice reuses the existing DeclarationBlock parser/serializer and upstream manifest inventory.

## Non-Overlap

- Does not touch the stale May 25 `CustomMediaTransformer.php` rework note, custom-media import-tail handling, accepted background/logical-spacing/inset CSSOM shorthands, source maps, CSS Modules, media queries, prefixing, or bundler behavior.
- Conservative mapped coverage remains inside the already represented DeclarationBlock CSSOM cluster; full-lane PHP assertion count increases through focused native tests.
