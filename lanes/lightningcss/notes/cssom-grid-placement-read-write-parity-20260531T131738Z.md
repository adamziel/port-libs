# CSSOM Grid Placement Read/Write Parity

Slice: `lightningcss-cssom-declaration-read-write-parity-20260531T131738Z`

Source truth:

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream files inspected:
  - `src/declaration.rs`: `DeclarationBlock::set` updates compatible shorthands in place via `set_longhand`, and `DeclarationBlock::remove` splits shorthands when removing a covered longhand.
  - `src/properties/grid.rs`: `grid-row`, `grid-column`, and `grid-area` are declared as shorthands over `grid-row-start`, `grid-column-start`, `grid-row-end`, and `grid-column-end`.
  - `tests/test_cssom.rs`: existing CSSOM helper cluster already covers grid placement reads, so this slice extends the same DeclarationBlock cluster with write/remove parity and does not claim a new mapped denominator row.

Implementation:

- `DeclarationBlock::setProperty()` now updates `grid-area`, `grid-row`, and `grid-column` when setting one of their placement longhands in the same importance bucket.
- `DeclarationBlock::removeProperty()` now splits grid placement shorthands into remaining longhands when removing one placement longhand, and removes direct placement longhands when removing the `grid-area`, `grid-row`, or `grid-column` shorthand id.
- Grid placement parsing now accepts upstream shorthand omission forms by filling omitted end lines with `auto` or the named grid area default.
- `examples/wordpress-grid-area-cssom.php` now has a self-test covering grid placement read, write, and removal behavior for block layout area rewrites.

Evidence:

- Red check before implementation: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` failed the new grid placement set/remove assertions with 126 assertions / 2 failures.
- Green focused check after implementation: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` passed with 138 assertions / 0 failures.
- Full lane check: `php tools/run-tests.php lanes/lightningcss/tests` passed with 13 files / 1396 assertions / 0 failures.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP declaration scanner, top-level slash splitting, priority-bucket partitioning, and serializer.
