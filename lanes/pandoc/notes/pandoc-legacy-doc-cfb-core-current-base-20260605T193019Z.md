# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T193019Z`

Base accepted HEAD: `8136e31e3cbc131cb905067bff7696d833252432`

Date: 2026-06-05 UTC

No `port-pandoc-*.needs-lane-rework.md` note was present for this lane before
starting the slice.

## Behavior

`CompoundFileBinary` now preflights CFB sector ownership before legacy Word
stream lookup:

- FAT, DIFAT, directory, MiniFAT, and root mini-stream sectors are reserved as
  metadata chains;
- regular stream chains cannot reuse reserved metadata sectors;
- regular stream chains cannot share sectors with other regular streams; and
- MiniFAT-backed streams cannot share mini-sector chain entries.

The focused fixture mutates `WordDocument` so its regular stream start sector
points at the directory sector. The parser now rejects that package during CFB
preflight instead of letting lazy stream decoding read directory bytes as
legacy Word text. The WordPress legacy DOC handoff smoke also mutates the root
mini-stream start sector onto the directory sector and verifies fail-closed
behavior before text, OLE metadata, embedded objects, or macro stream metadata
can be exposed.

## Source Truth

- Microsoft MS-CFB `Compound File Header` defines FAT, DIFAT, directory,
  MiniFAT, and mini-stream chain roots as distinct allocation structures.
- Microsoft MS-CFB `Sector Allocation` and `Mini Stream` behavior require
  stream data to follow its own sector chain rather than reusing metadata
  chains or another stream's chain.

This slice is intentionally bounded to sector-allocation overlap preflight for
legacy DOC import. It does not implement CFB repair, black-height validation,
encrypted DOC decryption, embedded object export, macro execution, or full
Pandoc upstream runner parity.

## Verification Evidence

Baseline focused legacy DOC verification:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result before editing: `1 test files, 557 assertions, 0 failures`.

Red-first focused verification after adding the new test:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: `1 test files, 558 assertions, 1 failures`.

Focused legacy DOC verification after implementation:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: `1 test files, 558 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`

Result: `legacy doc handoff self-test ok`.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped checks: `1502 -> 1503`.
- Lane PHP pass count: `1049 -> 1050`.
- Focused legacy DOC test coverage: `557 -> 558` assertions.
- Added one focused PASS case for CFB sector-allocation overlap preflight.

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`CompoundFileBinary`, `LegacyDocReader`, Pandoc-like AST, and
`WordPressBlockWriter` paths. It does not invoke Pandoc, Cabal, Haskell test
binaries, Word, LibreOffice, `zip`, `unzip`, `ZipArchive`, external office
tools, external validators, online sanitizers, or online services.

## Non-Overlap

This slice does not repeat accepted legacy DOC work for CFB signature/header
version checks, directory-sector count preflight, MiniFAT/DIFAT header
start-count consistency, FAT/DIFAT sector identity preflight, CFB directory
timestamps/CLSID/state-bit provenance, CFB directory object-field validation,
CFB sibling tree ordering and red-root validation, OLE property metadata,
encrypted FIB rejection, fExtChar Unicode text ranges, CLX piece-table
extraction, CLX PCD flag validation, FibRgLw97 subdocument text boundaries,
bookmarks, note/comment PLCs, section/style/formatting/list tables, field-code
result handoff, ObjectPool embedded object inventory, macro-project preflight,
DOCX, ODT, EPUB3, ZIP/OPC, XML/HTML5 DOM, or table geometry.

The owned behavior is only CFB sector-allocation overlap and shared-sector
validation before legacy DOC stream lookup.

## Follow-Up

Keep directory red-black black-height validation, MiniFAT short/cycle fixtures,
DIFAT-sector chain expansion fixtures, textbox/header-textbox subdocument
routing, richer style/list application, embedded object export policy,
encrypted DOC password/decryption policy, and full upstream Pandoc runner
parity as separate bounded slices.
