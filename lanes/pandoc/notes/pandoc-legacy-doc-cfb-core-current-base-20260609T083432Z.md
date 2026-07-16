# Legacy DOC CFB CHPX Semantic Inline Formatting

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260609T083432Z`

Base accepted HEAD: `436db66ac9717cbf75ff2ec29905ae0ddef22b3a`

## Behavior

- Applies safe decoded legacy DOC `PlcBteChpx` / `ChpxFkp` direct character properties to visible Pandoc-like inline AST nodes.
- Maps enabled `sprmCFBold`, `sprmCFItalic`, and `sprmCKul` to `strong`, `emph`, and `underline` nodes for the covered text character range.
- Keeps source SPRM metadata, raw operands, hidden text state, and unsupported review properties metadata-only; those details do not render into WordPress blocks or Markdown output.
- Exposes `inlineTextFormattingApplications` and related metadata so import reports can audit which CHPX run was applied.

## Evidence

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - `1 test files, 2437 assertions, 1 failures`
  - Failure was the missing semantic inline formatting application for the CHPX direct-formatting fixture.
- Final focused: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - `1 test files, 2465 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-character-formatting-handoff.php --self-test`
  - `legacy doc character-formatting handoff self-test ok`

## Delta

- Added 1 focused PHP PASS line for this legacy DOC/CFB behavior slice.
- Added 12 focused assertions over the prior `LegacyDocReaderTest.php` baseline.
- Increased mapped legacy DOC/CFB core inventory from 7 to 8 cases.
- Increased mapped denominator from 2898 to 2899.

## Dependency Closure

No new support component is needed. This reuses the existing native `CompoundFileBinary` reader, `LegacyDocReader` FIB text range handling, `PlcBteChpx` / `ChpxFkp` decoding, file-offset to character-position mapping, Pandoc-like AST inline nodes, Markdown writer, and WordPress block writer.

Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external template engines, TeX/PDF engines, browser renderers, online services, live providers, and office automation were not executed.

## Non-Overlap

This does not repeat accepted CHPX metadata-only extraction, PAPX paragraph formatting metadata, inline picture placeholders, FFData form-field Data-stream linkage, revision marks, fields, list formatting, encrypted DOC preflight, CFB directory parsing, DOCX/OpenXML package work, ODF/ODT package work, EPUB package work, archive compression, or external converter behavior.

## Follow-Up

Next useful legacy DOC/CFB work is stylesheet-linked character formatting, paragraph style application, or broader CHPX/PAPX run splitting across mixed text segments.
