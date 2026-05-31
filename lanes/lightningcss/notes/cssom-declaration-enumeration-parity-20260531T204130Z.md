# CSSOM Declaration Enumeration Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T204130Z`
Base accepted HEAD: `91b42fe7029899440b4b46f38b3f903a76f3b322`

## Source Truth

- Upstream cache commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream `src/declaration.rs` exposes `DeclarationBlock::len()` as the total normal plus important declaration count.
- Upstream `src/declaration.rs` exposes `DeclarationBlock::iter()` as normal declarations followed by important declarations, preserving order within each bucket.

## Behavior

- Added `DeclarationBlock::length()` for total parsed declaration count.
- Added `DeclarationBlock::item()` for zero-based property-name enumeration in the same normal-then-important order used by upstream iteration and this port's CSSOM read buckets.
- The focused test covers duplicate properties, custom-property case preservation, out-of-range `null`, and negative index rejection.
- Added `wordpress-cssom-declaration-enumeration.php` as a WordPress-facing smoke for editor tooling that enumerates declaration names before removing or rewriting priority declarations.

## Verification

- Before edit method probe: `php -r 'require "tools/bootstrap.php"; ... method_exists(...)'` reported `length-missing` and `item-missing`.
- `php -l lanes/lightningcss/src/DeclarationBlock.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-cssom-declaration-enumeration.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 738 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 4190 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-cssom-declaration-enumeration.php --self-test` -> `OK`.
- `git diff --check -- lanes/lightningcss` -> no output.

## Coverage And Non-Overlap

- Focused assertion delta: `+9` over the accepted full-lane status count (`4181 -> 4190`).
- Mapped denominator remains `2078 / 3532`; this deepens the already represented `DeclarationBlock` CSSOM inventory instead of claiming a new upstream row.
- This does not repeat the latest queued CSSOM clusters for animation-composition, escaped declaration names, CSS-wide shorthand opacity, background longhand splitting, `-ms-flex`, or WebKit mask-box-image.
- The stale 2026-05-25 `CustomMediaTransformer` rework note is unrelated to this CSSOM declaration enumeration slice.

## Dependency Closure

No new support component is needed. This reuses the existing bounded native `DeclarationBlock` parser, property normalization, and priority bucket ordering.

## Next

Continue CSSOM parity only on non-overlapping declaration behaviors, or switch to mapped LightningCSS property-value, parser recovery, source-map, bundle/import graph, CSS Modules, visitor/custom at-rule, or target-prefixing gaps.
