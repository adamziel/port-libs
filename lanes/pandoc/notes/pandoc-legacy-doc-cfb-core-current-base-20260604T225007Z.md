# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260604T225007Z`

Base accepted HEAD: `524dc40526b2fcb46fefc7d28613d818c4db4c08`

## Behavior Added

- Extended `LegacyDocReader` FIB handling for bounded legacy Word `.doc`
  preflight:
  - exposes the Word FIB `fEncrypted` state as `fib['encrypted']`;
  - rejects encrypted legacy DOC streams before attempting text extraction;
  - exposes the Word FIB `fExtChar` state as `fib['extendedCharacters']`;
  - decodes direct FIB `fcMin`/`fcMac` text ranges as UTF-16LE when
    `fExtChar` is set, instead of relying on a byte-shape heuristic.
- Updated the WordPress legacy DOC handoff smoke so its in-memory `.doc` packet
  exercises the direct Unicode FIB text path and reports the FIB preflight
  flags in its review summary.

## Source Truth

- The slice follows the Microsoft MS-DOC FIB flag contract for `fEncrypted`
  and `fExtChar`. A native importer should not expose encrypted DOC payload
  text without a decryption implementation, and direct FIB text ranges marked
  with `fExtChar` are decoded as Unicode.
- This is intentionally bounded to safe stream/text preflight and does not
  implement Word encryption/decryption, styles, list tables, fields,
  footnote/endnote PLCs, embedded objects, macro streams, Word automation,
  LibreOffice conversion, Pandoc execution, or CFB tree repair.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 38 assertions, 0 failures`
  - PASS lines: 6
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `13 test files, 3,698 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - Result: `legacy doc handoff self-test ok`

Additional lint and whitespace checks are recorded in the worker final report.

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
sector/MiniFAT parsing, OLE property metadata, or CLX piece-table extraction.
It owns only the bounded legacy DOC Word FIB encrypted-stream and direct
Unicode-text preflight cluster.

## Follow-Up

Keep CFB storage hierarchy traversal, encrypted DOC password/decryption policy,
legacy DOC style and list tables, footnote/endnote PLCs, field-code extraction,
image extraction policy, embedded-object handling, and full upstream Pandoc
runner parity as separate bounded slices.
