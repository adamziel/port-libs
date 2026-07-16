# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T143428Z`

Base accepted HEAD: `8850ac3e8de32f09aefe13cb9cb062ea6939410c`

Date: 2026-06-05 UTC

No `port-pandoc-*.needs-lane-rework.md` note was present for this lane before
starting the slice.

## Behavior

`CompoundFileBinary` now rejects malformed CFB directory object metadata before
legacy Word stream lookup:

- stream directory entries with a non-`NOSTREAM` child ID;
- storage directory entries that declare nonzero stream bytes;
- root directory entries with left or right sibling IDs; and
- red roots for storage child sibling trees.

The WordPress legacy DOC handoff smoke mutates the generated CFB packet through
the same directory-object corruptions and verifies they fail closed before
`WordDocument` text, metadata, embedded objects, or macro streams can be
exposed.

## Source Truth

- Microsoft MS-CFB `Compound File Directory Entry` defines `NOSTREAM`, object
  types, stream child-ID constraints, storage/root stream-size roles, and the
  directory entry field layout:
  https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/60fe8611-66c3-496b-b70d-a504c94c9ace
- Microsoft MS-CFB `Other Directory Entries` distinguishes stream-object data
  fields from storage-object metadata fields:
  https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/b37413bb-f3ef-4adc-b18e-29bddd62c26e
- Microsoft MS-CFB `Red-Black Tree` requires valid sibling object trees and
  black tree roots outside the root directory singleton:
  https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/d30e462c-5f8a-435b-9c4c-cc0b9ea89956

## Verification Evidence

Focused legacy DOC verification:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: `1 test files, 554 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`

Result: `legacy doc handoff self-test ok`.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped checks: `1402 -> 1404`.
- Lane PHP pass count: `947 -> 949`.
- Focused legacy DOC test coverage: `550 -> 554` assertions.
- Added two focused PASS cases for CFB directory object-type and red sibling
  tree-root preflight.

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`CompoundFileBinary`, `LegacyDocReader`, Pandoc-like AST, and
`WordPressBlockWriter` paths. It does not invoke Pandoc, Cabal, Haskell test
binaries, Word, LibreOffice, `zip`, `unzip`, external template engines,
TeX/PDF engines, browser renderers, roff, Typst, JavaScript, online
sanitizers, external validators, or online services.

## Non-Overlap

This slice does not repeat accepted legacy DOC work for CFB header parsing,
MiniFAT/FAT chain traversal, FAT/DIFAT sector identity preflight, directory
timestamps/CLSID/state-bit provenance, OLE property metadata, encrypted FIB
rejection, fExtChar Unicode text ranges, CLX main-text extraction, CLX PCD flag
validation, FibRgLw97 subdocument text boundaries, bookmarks, note/comment
PLCs, section/style/formatting/list tables, field-code result handoff,
ObjectPool embedded object inventory, macro-project preflight, DOCX, ODT,
EPUB3, ZIP/OPC, XML/HTML5 DOM, or table geometry.

The owned behavior is only CFB directory object-field and sibling-tree-root
validation before legacy DOC stream lookup.

## Follow-Up

Keep DIFAT-chain fixtures beyond header DIFAT, MiniFAT boundary corruption,
directory black-height validation, textbox/header-footer subdocument routing,
richer style/list application, embedded object export policy, encrypted DOC
password/decryption policy, and full upstream Pandoc runner parity as separate
bounded slices.
