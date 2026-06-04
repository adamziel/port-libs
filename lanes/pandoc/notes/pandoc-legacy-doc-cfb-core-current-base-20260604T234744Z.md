# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260604T234744Z`

Base accepted HEAD: `d74bad6c88fb561dfd80595abb30cd894a59e542`

## Behavior Added

- Extended `CompoundFileBinary` directory parsing from flat physical-entry
  indexing to bounded storage-tree traversal:
  - starts from the Root Entry child stream ID;
  - walks left/right sibling trees for each storage level;
  - descends storage child trees and exposes nested stream paths such as
    `Review/Notes`;
  - keeps root-level stream lookup by basename for existing legacy DOC callers;
  - rejects cyclic or out-of-range directory trees before returning stream
    bytes.
- Updated legacy DOC in-memory CFB fixtures and the WordPress legacy DOC smoke
  so their directory sectors emit valid Root Entry child and right-sibling
  pointers instead of relying on physical directory order.

## Source Truth

- Microsoft MS-CFB `Compound File Directory Entry`
  (`https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/60fe8611-66c3-496b-b70d-a504c94c9ace`)
  defines directory entries, stream IDs, `NOSTREAM`, and the child/left/right
  sibling fields used by storage and stream objects.
- Microsoft MS-CFB `Stream ID 0: Root Directory Entry`
  (`https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/5af03a5e-66dc-469c-8970-7229a11e2a3f`)
  shows the root storage child pointer as the entry point for contained
  objects.
- Microsoft MS-CFB `Red-Black Tree`
  (`https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/d30e462c-5f8a-435b-9c4c-cc0b9ea89956`)
  defines each storage object's immediate children as a sibling tree with
  unique names.

This slice is intentionally bounded to hierarchy traversal and safety checks.
It does not implement red-black sort/color validation, multi-sector directory
fixture generation, CFB repair, Word styles/list tables, footnote/endnote PLCs,
fields, image extraction, embedded objects, macros, encryption/decryption, Word
automation, LibreOffice conversion, Pandoc execution, or upstream Haskell
runner parity.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 75 assertions, 0 failures`
  - PASS lines: 10
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `16 test files, 4,089 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - Result: `legacy doc handoff self-test ok`

Additional lint, JSON, and whitespace checks are recorded in the worker final
report.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native CFB reader,
legacy DOC reader, Pandoc-like AST, Markdown writer, and WordPress block writer.
It does not invoke Pandoc, Cabal, Haskell test binaries, Word, LibreOffice,
`zip`, `unzip`, external template engines, TeX/PDF engines, browser renderers,
roff, Typst, or online services.

## Non-Overlap

This patch does not repeat accepted PDF engine fake-runner diagnostics, EPUB3,
BibTeX/CSL, CSL, YAML, doctemplate, ZIP/OPC, archive compression, DOCX/ODT,
table geometry, math/TeX, charset/Unicode, Markdown/HTML reader/writer, CFB
sector/MiniFAT parsing, OLE string/date/count/security metadata, CLX
piece-table extraction, Word FIB encrypted-stream preflight, or fExtChar
Unicode text-range decoding. It owns only bounded CFB directory-tree traversal
and cycle guards for legacy DOC stream lookup.

## Follow-Up

Keep multi-sector directory chain fixtures, red-black sibling ordering
validation, encrypted DOC password/decryption policy, legacy DOC style and list
tables, footnote/endnote PLCs, field-code extraction, image extraction policy,
embedded-object handling, vector heading-pair/docpart metadata, user-defined
property sets, and full upstream Pandoc runner parity as separate bounded
slices.
