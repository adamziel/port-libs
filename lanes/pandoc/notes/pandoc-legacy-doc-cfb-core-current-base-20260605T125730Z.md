# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T125730Z`

Base accepted HEAD: `a7fcab9938b3f699e7572fbf8e5c7dcf121bd3dc`

Date: 2026-06-05 UTC

## Behavior

`CompoundFileBinary` now performs bounded FAT/DIFAT sector identity preflight
before exposing any CFB stream. The reader rejects:

- duplicate FAT sector IDs listed through the DIFAT;
- FAT sectors whose own FAT entry is not marked `FATSECT`;
- DIFAT sectors whose own FAT entry is not marked `DIFSECT`;
- DIFAT sectors reused as FAT sectors.

The WordPress legacy DOC handoff smoke now mutates the in-memory CFB packet to
prove duplicate and misclassified FAT sectors are rejected before `WordDocument`
text or metadata can be exposed.

## Source Truth

The MS-CFB allocation model reserves sector IDs for FAT and DIFAT sectors, and
those sectors are identified by reserved FAT entry values rather than ordinary
chain links. This slice ports that safety contract for native PHP legacy `.doc`
preflight. It does not implement CFB repair, arbitrary salvage of damaged FAT
chains, Word automation, LibreOffice fallback, or full upstream Pandoc runner
parity.

## Verification Evidence

Baseline before adding the new focused case:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: `1 test files, 500 assertions, 0 failures`.

Red-first after adding the corrupt FAT-sector fixture before implementation:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: `1 test files, 501 assertions, 1 failures`; the new test failed because
duplicated FAT sector IDs were accepted.

Post-implementation focused test:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: `1 test files, 502 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`

Result: `legacy doc handoff self-test ok`.

Syntax and JSON checks:

- `php -l lanes/pandoc/src/CompoundFileBinary.php` passed.
- `php -l lanes/pandoc/tests/LegacyDocReaderTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php` passed.
- `php -r '$files=["lanes/pandoc/lane-status.json","lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"]; foreach ($files as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'` passed.

Final whitespace check:

`git diff --check -- lanes/pandoc`

Result: no whitespace errors.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped checks: `1365 -> 1366`.
- Lane PHP pass count: `907 -> 908`.
- Focused legacy DOC test coverage: `500 -> 502` assertions.
- Added one mapped native legacy DOC/CFB preflight case.

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`CompoundFileBinary`, `LegacyDocReader`, Pandoc-like AST, and WordPress block
writer paths. It does not invoke Pandoc, Cabal, Haskell test binaries, Word,
LibreOffice, `zip`, `unzip`, external template engines, TeX/PDF engines,
browser renderers, roff, Typst, JavaScript, online sanitizers, or online
services.

## Non-Overlap

This slice does not repeat accepted legacy DOC work for CFB header parsing,
MiniFAT/FAT chain traversal, directory timestamps/CLSID/state-bit provenance,
OLE property metadata, encrypted FIB rejection, fExtChar Unicode text ranges,
CLX main-text extraction, CLX PCD flag validation, FibRgLw97 subdocument text
boundaries, bookmarks, note/comment PLCs, section/style/formatting tables,
field-code result handoff, ObjectPool embedded object inventory, macro-project
preflight, DOCX, ODT, EPUB3, ZIP/OPC, XML/HTML5 DOM, or table geometry.

The owned behavior is only CFB FAT/DIFAT sector identity rejection before
legacy DOC stream lookup.

## Follow-Up

Keep DIFAT-chain fixture coverage beyond the header DIFAT, MiniFAT boundary
corruption cases, directory black-height validation, FastSave edge cases,
textbox/header-footer subdocument routing, richer style/list application,
embedded object export policy, encrypted DOC password/decryption policy, and
full upstream Pandoc runner parity as separate bounded slices.
