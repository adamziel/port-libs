# Pandoc Reader Parity Evaluator - 2026-06-26

Hooked bead: `plib-r4luj`
Branch: `polecat/basalt/plib-r4luj@mqu9gdsh`
Target branch: `plainmath-parity-20260625`
Target branch tip: `717d6d8e5a7691ca6ce92cada6a145cad6093bf5`

## Inputs Inspected

- Current branch history and test files at the verified HEAD.
- Existing worker notes:
  - `lanes/pandoc/notes/reader-feature-parity-supervisor-20260626.md`
  - `lanes/pandoc/notes/reader-regression-evaluator-20260626.md`
  - `lanes/pandoc/notes/reader-missing-feature-audit-20260626.md`
  - `lanes/pandoc/notes/reader-integration-review-20260626.md`
- Current focused reader tests:
  - `lanes/pandoc/tests/CsvReaderTest.php`
  - `lanes/pandoc/tests/BibTexReaderTest.php`
  - `lanes/pandoc/tests/XlsxReaderTest.php`
  - `lanes/pandoc/tests/PptxReaderTest.php`
  - `lanes/pandoc/tests/DocxReaderTest.php`
- Light source scan of the matching reader implementations to separate tested
  support from still-missing constructs.

No source files were modified. `.beads/config.yaml` was not touched.

## Commands And Results

```bash
php tools/run-tests.php lanes/pandoc/tests/CsvReaderTest.php
```

Result: passed. `1 test files, 73 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/pandoc/tests/BibTexReaderTest.php
```

Result: passed. `1 test files, 105 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/pandoc/tests/XlsxReaderTest.php
```

Result: passed. `1 test files, 51 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/pandoc/tests/PptxReaderTest.php
```

Result: passed. `1 test files, 45 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
```

Result: passed. `1 test files, 104 assertions, 0 failures`.

Focused reader total: 5 files, 378 assertions, 0 failures.

```bash
php tools/run-tests.php lanes/pandoc/tests
```

Result: passed. `28 test files, 23974 assertions, 0 failures`.

## Regressions

No focused reader or full Pandoc lane regression was observed in this run.

The current assertion counts are higher than the earlier regression note for
CSV, BibTeX, PPTX, and DOCX, reflecting accepted follow-up coverage on this
branch: CSV ragged row details; BibLaTeX creator roles, seasonal dates,
relations, eprint/status metadata, and diagnostics; PPTX inherited
placeholder/chart/table styling; and DOCX optional XML diagnostics, comment
ranges, moves, table vertical merges/style metadata, and image
metadata/dimensions.

## Requirement Gap Table

| Area | User objective | Current branch support | Remaining gap |
| --- | --- | --- | --- |
| CSV/TSV | Full CSV/TSV features | CSV and TSV route through the converter. Focused tests cover quoted commas, escaped quotes, multiline quoted cells, `sep=` directives, semicolon detection that ignores quoted commas, headerless mode, comments, alternate quote/escape settings, UTF BOM/UTF-16LE decoding, ragged-row metadata with exact row widths, empty input, and inferred column type metadata. | Not full parity. Dialect handling is still heuristic and bounded to simple one-character delimiters. There are no recoverable diagnostics for unclosed quotes, stray quotes, or malformed record shapes. Import is whole-string, not streaming. There is no source line/column provenance, locale-aware number/date parsing, table captions, alignment inference, column width/schema import, or typed AST values beyond metadata. Unquoted fields are trimmed, so significant leading/trailing spaces can be lost. |
| BibTeX/BibLaTeX | Full BibTeX support | Tests cover entries, strings, preambles, comments, braced/quoted/bare/concatenated values, BibLaTeX routing, direct `crossref`/`xdata` inheritance, `xdata`/`set` filtering, selected field aliases, DOI/URL output, TeX accent and URL cleanup, date aliases, simple date ranges, name particles, selected BibLaTeX creator roles (`bookauthor`, `commentator`, `afterword`), seasonal/circa/uncertain dates, relation metadata, eprint/version/status metadata, duplicate-key and missing-`xdata` diagnostics, visible CSL-like bibliography blocks, and empty-bibliography notices. | Not full BibTeX/BibLaTeX or citeproc parity. The reader does not implement CSL locale rendering, sorting, disambiguation, label generation, or style-specific bibliography output. BibLaTeX data-model coverage remains partial; many entry types and aliases collapse to broad CSL types. Entry sets are treated as data-only. Relationship handling is metadata-only and remains incomplete for fields such as `xref`, `ids`, `shorthand`, label fields, and style-specific related-entry behavior. Many creator roles beyond the focused cases still need coverage. Complex corporate names, et-al markers, open date ranges, time components, arbitrary TeX macros, math, and protected title-case semantics need more support and diagnostics. |
| XLSX | Date/number formats, images, styles | Tests cover workbook/core metadata, multiple sheets, shared strings, inline strings, booleans, numbers, hyperlinks, merged cells, rich text runs, bold/italic/underline/strike styling, text and fill color, horizontal alignment, common date/datetime/percent formatting, raw value and number-format metadata, worksheet drawing image URL/alt/title/name/relationship/anchor metadata, and byte input conversion. | Spreadsheet semantics remain partial. Formula evaluation and formula metadata are not robust. Comments/threaded comments, charts, pivot tables, slicers, filters, named ranges, Excel table objects, data validation, conditional formatting, hidden/very-hidden sheet semantics, print/view state, row/column hidden state, row heights, column widths, borders, vertical alignment, theme/tint inheritance, protection, drawing shapes/text boxes/connectors, image payload extraction, dimensions, crop, rotation, and captions are missing or very limited. |
| PPTX | Layout/master/theme/charts/table styles | Tests cover presentation metadata, slide size, slide relationships, speaker notes, slide media, hyperlinks, bullets and ordered lists, pictures, layout/master/theme path metadata, inherited placeholder title/body/footer text, theme color resolution for table fills/borders, table colspans/rowspans/alignment, simple cached chart data extraction to a table, and byte input conversion. | Slide fidelity remains partial. Layout/master inheritance does not preserve full placeholder geometry, transforms, z-order, backgrounds, default text styles, bullet styles, or theme font schemes. Chart support is cached-data extraction only; chart type, axes, labels, legends, formatting, and embedded workbook semantics are not modeled. SmartArt, diagrams, comments, modern comments, ink annotations, audio/video, embedded files, OLE/ActiveX objects, linked media, crop/rotation/position, rich media metadata, transitions, and animations remain unsupported or intentionally outside static extraction. |
| DOCX | Most of those features | Package-reader tests now cover core metadata, notes, comments, headers/footers, paragraph and character styles, style inheritance for a focused character style, links, insert/delete spans, comment ranges, move-from/move-to spans, numbering levels/start/style/delimiter, bookmarks, simple REF fields, a bounded OMML subset, malformed optional XML diagnostics, table style metadata, vertical merges, cell shading/vertical alignment, image alt/title/name/id, and image dimensions. The full lane also contains upstream-native WordPress mapping checks for richer DOCX review artifacts. | Package-reader parity is still incomplete. Accept/reject revision modes, paragraph-level revisions, section-specific headers/footers, split `instrText` and complex fields, TOC/index/bibliography/page/caption fields, content controls, footnote/endnote separators, `altChunk`, text boxes, shapes, VML/object images, linked images, crop/rotation, media payload extraction, table `gridBefore`/`gridAfter`, captions, header rows, cell borders/widths, row heights, full table/style inheritance, latent/default/theme styles, numbering style links, full OMML, and task-list glyph semantics remain gaps. |

## Priority Follow-Ups

1. Add diagnostics and source provenance before broadening feature support. Silent
   omission is still the highest audit risk across all five formats.
2. Prioritize table fidelity for XLSX/PPTX/DOCX: captions, headers, spans,
   cell coordinates, widths, and row/column provenance.
3. Keep media work bounded to extraction metadata and payload handoff. Do not add
   script, macro, OLE/ActiveX execution, DRM, protected package bypass, or
   external network fetching.
4. Split future implementation issues by format and fixture family. The current
   branch is green, but every remaining gap is large enough to deserve focused
   tests rather than a broad parity claim.
