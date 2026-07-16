# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260604T113812Z`

Base accepted HEAD: `bac990a62b1368014746f6066bdc811af5cd8165`

## Behavior Added

- Added a bounded native `CompoundFileBinary` reader for Microsoft Compound
  File Binary containers:
  - validates CFB header signature, byte order, sector sizes, FAT/DIFAT
    sectors, and terminated sector chains;
  - parses directory entries and exposes root-level streams by name;
  - reads regular FAT streams and MiniFAT-backed small streams with cycle and
    size-limit checks.
- Added `LegacyDocReader` for safe legacy Word `.doc` text and metadata
  handoff:
  - validates the `WordDocument` FIB signature;
  - extracts non-complex FIB `fcMin`/`fcMac` text ranges;
  - extracts bounded complex text through `0Table`/`1Table` Clx/Pcdt/PlcPcd
    piece tables;
  - decodes compressed single-byte pieces including MS-DOC smart quote
    mappings and UTF-16LE text pieces;
  - maps paragraph marks and hard line breaks into existing Pandoc-like AST
    paragraph and `linebreak` nodes;
  - reads OLE `SummaryInformation` and `DocumentSummaryInformation` property
    set strings into metadata used by WordPress import tooling.
- Added a WordPress legacy DOC handoff smoke that builds a minimal CFB package
  in memory, imports it through `LegacyDocReader`, and renders WordPress blocks.

## Source Truth

- CFB stream and MiniFAT behavior follows the Microsoft MS-CFB model: a
  compound document is a FAT-backed hierarchy of storage and stream objects,
  with regular sectors and mini sectors linked by explicit chain terminators.
- Legacy Word text extraction follows the Microsoft MS-DOC text retrieval
  contract: read the `WordDocument` FIB, use `FibRgFcLcb97.fcClx/lcbClx` to
  locate the Clx in the selected table stream when present, and decode Pcd
  `FcCompressed` pieces as compressed single-byte or UTF-16 text.
- This slice ports the bounded format contract needed for import text and
  metadata handoff. It intentionally does not implement full binary Word
  layout, styles, list tables, fields, footnote PLCs, embedded OLE objects,
  macros, encryption/decryption, arbitrary repair of corrupt CFB trees, or any
  Word/LibreOffice/Pandoc execution.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 29 assertions, 0 failures`
  - PASS lines: 4
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `10 test files, 3076 assertions, 0 failures`
- `php -l lanes/pandoc/src/CompoundFileBinary.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/LegacyDocReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`
  - Result: no syntax errors.
- `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - Result: `legacy doc handoff self-test ok`.

Root harness not run - isolated micro-slice.

## Dependency Closure

No external dependency is needed. This slice adds the smallest native PHP
support component required for bounded legacy DOC import: CFB stream access
plus WordDocument text/property extraction. It reuses the existing Pandoc-like
AST, Markdown writer, and WordPress block writer. It does not invoke Pandoc,
Cabal, Haskell test binaries, Word, LibreOffice, `zip`, `unzip`, external
template engines, TeX/PDF engines, browser renderers, roff, or online services.

## Non-Overlap

This patch does not repeat accepted ZIP central-directory metadata,
local-header validation, OPC content types, OPC relationship graph target
integrity preflight, doctemplate, YAML, Citation/CSL, Markdown reader/writer,
HTML reader, WordPress Markdown handoff, DOCX body/core-property parsing,
DOCX style/numbering handoff, DOCX table span handoff, ODT handoff,
Math/TeX conversion, table geometry, archive compression, or PDF engine
handoff planning. It owns only the new legacy DOC/CFB text and metadata
support-library path.

## Follow-Up

Keep CFB storage hierarchy paths, directory sibling-tree traversal beyond flat
root stream lookup, encrypted DOC preflight, legacy DOC styles/list tables,
footnote/endnote PLCs, fields, image extraction policy, and embedded-object
handling as separate bounded slices.
