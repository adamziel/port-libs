# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T200037Z`

Base accepted HEAD: `23ac5b361540ae4c76b2fbb0d32c27d96db41cc5`

Date: 2026-06-05 UTC

## Behavior

`LegacyDocReader` now fails closed for complex legacy Word documents when the
required table-stream CLX piece table is unavailable. A DOC FIB with the
complex flag set no longer falls back to direct `fcMin`/`fcMac` text-range
extraction if:

- neither selected table stream is present; or
- the table stream exists but the CLX piece-table location is absent.

The WordPress legacy DOC handoff smoke mutates the in-memory CFB packet to
prove a complex packet with a missing CLX is rejected before raw
`WordDocument` bytes can be exposed as review text.

## Source Truth

Legacy Word complex documents route text through the table-stream CLX/Pcd piece
table. Treating a complex FIB as a simple direct text range can expose FIB
padding, supplemental subdocument bytes, or stale FastSave data as rendered
content. This slice ports only that bounded fail-closed reader contract. It
does not implement Word automation, LibreOffice fallback, CFB repair, encrypted
DOC decryption, or full upstream Pandoc runner parity.

## Verification Evidence

Baseline before this slice:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: `1 test files, 558 assertions, 0 failures`.

Post-implementation focused test:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: `1 test files, 560 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`

Result: `legacy doc handoff self-test ok`.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped checks: `1511 -> 1512`.
- Lane PHP pass count: `1058 -> 1059`.
- Focused legacy DOC test coverage: `558 -> 560` assertions.
- Added one mapped native legacy DOC/CFB fail-closed piece-table case.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`LegacyDocReader`, `CompoundFileBinary`, Pandoc-like AST, and WordPress block
writer paths. It does not invoke Pandoc, Cabal, Haskell test binaries, Word,
LibreOffice, `zip`, `unzip`, external template engines, TeX/PDF engines,
browser renderers, roff, Typst, JavaScript, online sanitizers, or online
services.

## Non-Overlap

This slice does not repeat accepted legacy DOC/CFB work for CFB header parsing,
MiniFAT/FAT chain traversal, directory timestamps/CLSID/state-bit provenance,
FAT/DIFAT sector identity rejection, OLE property metadata, encrypted FIB
rejection, fExtChar Unicode text ranges, successful CLX main-text extraction,
CLX PCD flag validation, FibRgLw97 subdocument text boundaries, bookmarks,
note/comment PLCs, section/style/formatting tables, field-code result handoff,
ObjectPool embedded object inventory, macro-project preflight, DOCX, ODT,
EPUB3, ZIP/OPC, XML/HTML5 DOM, or table geometry.

The owned behavior is only fail-closed complex-FIB handling when CLX
piece-table data is missing.

## Follow-Up

Keep FastSave edge cases, textbox/header-footer subdocument routing, richer
style/list application, embedded object export policy, encrypted DOC
password/decryption policy, fuller CFB DIFAT/MiniFAT boundary fixture coverage,
and full upstream Pandoc runner parity as separate bounded slices.
