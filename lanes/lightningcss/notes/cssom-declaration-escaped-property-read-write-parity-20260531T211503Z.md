# CSSOM Declaration Escaped Property Read Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T211503Z`

Accepted base: `3a3374ad59c06e8a3561833481036dd945373160`

## Upstream Source Truth

- Pinned upstream manifest commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/declaration.rs::parse_declaration` parses declaration names with `cssparser::Parser::expect_ident()` before resolving `PropertyId`, so CSS identifier escapes are decoded before CSSOM storage and lookup.
- `DeclarationBlock` CSSOM APIs operate on decoded property identifiers for read, write, ordering, and removal behavior.

## Behavior Ported

- `DeclarationBlock` now decodes valid CSS identifier escapes in declaration names before normalizing known property names or preserving custom-property case.
- Escaped names such as `c\6f lor` are stored, read, updated, ordered, and removed as `color`.
- Escaped custom property names such as `--Block\2D Accent` preserve decoded custom-property casing and round-trip through `getProperty`, `setProperty`, and `removeProperty`.
- Top-level declaration colon detection now skips valid CSS escapes so escaped identifier content does not terminate a declaration name.

## Evidence

Red-first probe before the implementation showed escaped names stored raw:

- `c\6flor: red` produced key `c\6flor`, and `getProperty('color')` returned null.
- `c\6f lor: red` produced key `c\6f lor`, and `getProperty('color')` returned null.
- `--Block\2D Accent: red` produced key `--Block\2D Accent`, and decoded custom-property lookup returned null.

Passing verification after the implementation:

- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - `1 test files, 770 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 4403 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-escaped-declaration-cssom.php --self-test`
  - `OK`
- `php -l lanes/lightningcss/src/DeclarationBlock.php && php -l lanes/lightningcss/tests/DeclarationBlockTest.php && php -l lanes/lightningcss/examples/wordpress-escaped-declaration-cssom.php`
  - no syntax errors
- `git diff --check -- lanes/lightningcss`
  - pass

Focused assertion movement: `757 -> 770` in `DeclarationBlockTest.php` (`+13`).

Full lane assertion movement: `4390 -> 4403` (`+13`).

Mapped coverage remains `2117 / 3532`; this deepens the already represented CSSOM `DeclarationBlock` upstream cluster rather than adding a new manifest row.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `DeclarationBlock` scanner and adds bounded CSS identifier-escape decoding locally.

## Non-overlap

This slice avoids the stale CustomMedia rework note and does not overlap accepted CSSOM enumeration, property-location, block-write guard, shorthand read/write, target-prefixing, CSS Modules, bundler/import-graph, source-map, or property-value batches.
