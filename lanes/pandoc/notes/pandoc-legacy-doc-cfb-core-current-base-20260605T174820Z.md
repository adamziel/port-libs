# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T174820Z`

Base accepted HEAD: `02c0aa77efdf30724ddab3b0e4c265250fc6529e`

Date: 2026-06-05 UTC

No `port-pandoc-*.needs-lane-rework.md` note was present for this lane before
starting the slice.

## Behavior

`CompoundFileBinary` now rejects inconsistent CFB header chain declarations
before directory traversal or legacy Word stream lookup:

- a regular MiniFAT start sector with zero MiniFAT sectors;
- a positive MiniFAT sector count without a valid regular MiniFAT start
  sector; and
- a regular DIFAT start sector with zero DIFAT sectors.

The WordPress legacy DOC handoff smoke mutates the generated CFB packet through
the same header inconsistencies and verifies they fail closed before
`WordDocument` text, OLE metadata, embedded object inventory, or macro stream
metadata can be exposed.

## Source Truth

- Microsoft MS-CFB `Compound File Header` defines the first MiniFAT sector
  location, MiniFAT sector count, first DIFAT sector location, and DIFAT sector
  count as paired header fields.
- Microsoft MS-CFB Mini Stream and DIFAT chain behavior require valid chain
  starts only when the corresponding sector count is present.

This slice is intentionally bounded to CFB header consistency preflight for
legacy DOC import. It does not implement MiniFAT repair, DIFAT chain repair,
directory black-height validation, Word styles/list application, image
extraction, embedded object export, macro execution, encryption/decryption,
Word automation, or broader upstream runner parity.

## Verification Evidence

Baseline focused legacy DOC verification:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result before editing: `1 test files, 554 assertions, 0 failures`.

Red-first focused verification after adding the new test:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: `1 test files, 555 assertions, 1 failures`.

Focused legacy DOC verification after implementation:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: `1 test files, 557 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`

Result: `legacy doc handoff self-test ok`.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped checks: `1476 -> 1477`.
- Lane PHP pass count: `1022 -> 1023`.
- Focused legacy DOC test coverage: `554 -> 557` assertions.
- Added one focused PASS case for CFB MiniFAT/DIFAT header consistency
  preflight.

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`CompoundFileBinary`, `LegacyDocReader`, Pandoc-like AST, and
`WordPressBlockWriter` paths. It does not invoke Pandoc, Cabal, Haskell test
binaries, Word, LibreOffice, `zip`, `unzip`, external template engines,
TeX/PDF engines, browser renderers, roff, Typst, JavaScript, online
sanitizers, external validators, or online services.

## Non-Overlap

This slice does not repeat accepted legacy DOC work for CFB header version
checks, directory-sector count preflight, MiniFAT/FAT chain traversal,
FAT/DIFAT sector identity preflight, CFB directory timestamps/CLSID/state-bit
provenance, CFB directory object-field validation, CFB sibling tree ordering and
red-root validation, OLE property metadata, encrypted FIB rejection, fExtChar
Unicode text ranges, CLX piece-table extraction, CLX PCD flag validation,
FibRgLw97 subdocument text boundaries, bookmarks, note/comment PLCs, section,
style, formatting, list tables, field-code result handoff, ObjectPool embedded
object inventory, macro-project preflight, DOCX, ODT, EPUB3, ZIP/OPC,
XML/HTML5 DOM, or table geometry.

The owned behavior is only MiniFAT/DIFAT header start-sector/count consistency
validation before legacy DOC stream lookup.

## Follow-Up

Keep MiniFAT sector-chain cycle and short-chain fixtures, directory
black-height validation, textbox/header-footer subdocument routing, richer
style/list application, embedded object export policy, encrypted DOC
password/decryption policy, and full upstream Pandoc runner parity as separate
bounded slices.
