# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T203154Z`

Base accepted HEAD: `bd7df865700dfaabbc10e2b866ce008e83e43e09`

Date: 2026-06-05 UTC

## Behavior

`LegacyDocReader` now consumes DWORD zero padding after OLE property-set
`LPSTR` and `LPWSTR` values. This keeps DocumentSummaryInformation vector
properties readable when legacy DOC metadata stores padded `VT_VECTOR|VT_LPSTR`
document parts or padded `VT_VECTOR|VT_VARIANT` heading pairs.

The WordPress legacy DOC handoff smoke now writes spec-shaped padded vector
fixtures for the same DocumentSummaryInformation heading-pair and document-part
metadata. WordPress review packets continue to expose the parsed metadata under
`documentParts` and `headingPairs` without exposing raw CFB bytes.

## Source Truth

OLE property-set variable values are DWORD-aligned inside property streams and
vectors. The native reader must skip zero padding between values before reading
the next vector element. This slice ports only that bounded metadata-reader
contract; it does not implement Word automation, LibreOffice fallback, CFB
repair, encrypted DOC decryption, or full upstream Pandoc runner parity.

## Verification Evidence

Baseline before this slice:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: `1 test files, 560 assertions, 0 failures`.

Red-first after adding the padded vector check:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: `1 test files, 561 assertions, 1 failures`; padded
DocumentSummaryInformation vector values were ignored and `documentParts` was
missing.

Post-implementation focused test:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: `1 test files, 564 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`

Result: `legacy doc handoff self-test ok`.

PHP lint:

- `php -l lanes/pandoc/src/LegacyDocReader.php` passed.
- `php -l lanes/pandoc/tests/LegacyDocReaderTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php` passed.

Lane JSON validation:

- `lanes/pandoc/lane-status.json ok`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json ok`.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped checks: `1522 -> 1523`.
- Lane PHP pass count: `1070 -> 1071`.
- Focused legacy DOC test coverage: `560 -> 564` assertions.
- Added one mapped native legacy DOC/CFB metadata case.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`LegacyDocReader`, `CompoundFileBinary`, OLE property-set parser, Pandoc-like
AST, and WordPress block writer paths. It does not invoke Pandoc, Cabal,
Haskell test binaries, Word, LibreOffice, `zip`, `unzip`, external template
engines, TeX/PDF engines, browser renderers, roff, Typst, JavaScript, online
sanitizers, or online services.

## Non-Overlap

This slice does not repeat accepted legacy DOC/CFB work for CFB header parsing,
MiniFAT/FAT chain traversal, directory timestamps/CLSID/state-bit provenance,
FAT/DIFAT sector identity rejection, scalar OLE property metadata, encrypted
FIB rejection, fExtChar Unicode text ranges, CLX main-text extraction, CLX PCD
flag validation, FibRgLw97 subdocument text boundaries, bookmarks, note/comment
PLCs, section/style/formatting tables, list tables, field-code result handoff,
ObjectPool embedded object inventory, macro-project preflight, DOCX, ODT,
EPUB3, ZIP/OPC, XML/HTML5 DOM, or table geometry.

The owned behavior is only DWORD padding between variable OLE property-set
vector values used by legacy DOC metadata.

## Follow-Up

Keep FastSave edge cases, textbox/header-footer subdocument routing, richer
style/list application, embedded object export policy, encrypted DOC
password/decryption policy, additional OLE property-set vector families, fuller
CFB DIFAT/MiniFAT boundary fixture coverage, and full upstream Pandoc runner
parity as separate bounded slices.
