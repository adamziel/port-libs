# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T001902Z`

Base accepted HEAD: `4387eff3c6950226648700389da4d046c02a09df`

## Behavior Added

- Extended `CompoundFileBinary` directory preflight before stream exposure:
  - validates non-empty directory object types as storage, stream, or root
    storage entries;
  - rejects CFB directory names containing `/`, `\`, `:`, or `!`;
  - validates sibling-tree ordering using the MS-CFB name relationship:
    directory-name byte length first, then case-insensitive UTF-16 code-unit
    comparison;
  - validates red/black color flags and rejects consecutive red nodes in a
    sibling tree.
- Updated legacy DOC in-memory CFB fixtures and the WordPress legacy DOC smoke
  so fixture directory sectors emit sorted black sibling trees instead of
  physical-order right-sibling chains.

## Source Truth

- Microsoft MS-CFB `Compound File Directory Entry`
  (`https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/60fe8611-66c3-496b-b70d-a504c94c9ace`)
  defines directory entry object types, UTF-16 directory names, illegal name
  characters, and sibling-tree pointers.
- Microsoft MS-CFB `Red-Black Tree`
  (`https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/d30e462c-5f8a-435b-9c4c-cc0b9ea89956`)
  defines unique sibling names by the special name ordering, the length-first
  and case-insensitive UTF-16 comparison relationship, and the consecutive-red
  invariant.

This slice is intentionally bounded to CFB directory safety and legacy DOC
stream lookup. It does not implement full CFB repair, directory red-black
rebalancing, Word styles/list tables, footnote/endnote PLCs, fields, image
extraction, embedded objects, macros, encryption/decryption, Word automation,
LibreOffice conversion, Pandoc execution, or upstream Haskell runner parity.

## Verification

- `php -l lanes/pandoc/src/CompoundFileBinary.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 79 assertions, 0 failures`
  - PASS lines: 13
  - Delta: +3 PASS lines / +4 assertions over the accepted-base focused
    legacy DOC/CFB test.
- `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - Result: `legacy doc handoff self-test ok`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native CFB reader,
legacy DOC reader, Pandoc-like AST, Markdown writer, and WordPress block writer.
It does not invoke Pandoc, Cabal, Haskell test binaries, Word, LibreOffice,
`zip`, `unzip`, external template engines, TeX/PDF engines, browser renderers,
roff, Typst, MathJax, KaTeX, or online services.

## Non-Overlap

This patch does not repeat accepted PDF engine fake-runner diagnostics, EPUB3,
BibTeX/CSL, CSL, YAML, doctemplate resource-map rendering, ZIP/OPC, archive
compression, DOCX/ODT, table geometry, math/TeX, charset/Unicode,
Markdown/HTML reader/writer, CFB FAT/MiniFAT parsing, OLE string/date/count/
security metadata, CLX piece-table extraction, Word FIB encrypted-stream
preflight, fExtChar Unicode text-range decoding, or accepted CFB storage-path
hierarchy traversal. It owns only bounded CFB directory sibling-tree ordering
and red-black color validation before legacy DOC stream lookup.

## Follow-Up

Keep multi-sector CFB directory fixture chains, red-black tree repair or
rebalancing, encrypted DOC password/decryption policy, legacy DOC style and
list tables, footnote/endnote PLCs, field-code extraction, image extraction
policy, embedded-object handling, vector heading-pair/docpart metadata,
user-defined property sets, and full upstream Pandoc runner parity as separate
bounded slices.
