# Pandoc CSV / TSV Audit - 2026-06-21 03:09:44 UTC

## Scope

- Pinned upstream commit: `912bfa5e2e3f5c74eb125dfc19404f67c61ca58b`.
- Upstream sources inspected:
  - `src/Text/Pandoc/CSV.hs`
  - `src/Text/Pandoc/Readers/CSV.hs`
- Upstream fixtures inspected:
  - `test/command/csv.md`
  - `test/command/01.csv`
  - `test/command/3533-rst-csv-tables.csv`
  - `test/command/3533-rst-csv-tables.md`

## Result

`PortLibs\Pandoc\DelimitedTextReader` now covers the pinned upstream direct CSV reader and the available shared CSV parser option evidence:

- Default CSV comma delimiter, double-quote quoted fields, doubled quote escaping, first row as table header, and empty cells.
- TSV tab delimiter with literal quote handling and post-tab space skipping.
- Pandoc-style post-delimiter whitespace skipping when `keepSpace` is false.
- Parser options matching `Text.Pandoc.CSV.CSVOptions`: custom delimiter, custom quote, custom escape, and keep-space toggle.
- Space-delimited single-quote parsing from the RST `csv-table` command fixture.
- Backslash-escaped quote parsing from the RST `csv-table` command fixture.
- Semicolon-delimited source fixture parsing from `test/command/01.csv`.
- Multiline quoted cells preserved in the raw cell `text` attribute and represented as `linebreak` inline nodes in the AST.

## Remaining Gaps

- RST `csv-table` directive parsing itself is not claimed here; it remains part of the unsupported/partial RST reader gap.
- CSV/TSV output formats are not upstream Pandoc writer targets in the tracked output registry.
- The broader Pandoc goal still has unsupported input families (`typst`, `pptx`, `xlsx`, wiki/roff/text markup readers) and partial high-surface formats (`docx`, `html`, `json/native`, bibliography formats, LaTeX/TeX, RTF, PDF-adjacent import).

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/DelimitedTextReaderTest.php`
  - `1` test file, `419` assertions, `0` failures.
- `php tools/run-tests.php lanes/pandoc/tests/DelimitedTextReaderTest.php lanes/pandoc/tests/PandocConverterTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php lanes/pandoc/tests/RichPackageUnsupportedFormatRegistryTest.php`
  - `4` test files, `723` assertions, `0` failures.
- Broad reader/writer smoke including CSV/TSV, OPML, and PDF bridge tests:
  - `23` test files, `18,328` assertions, `0` failures.
- PDF guard after this CSV/TSV slice:
  - `2` test files, `3,051` assertions, `0` failures.
- Problematic invoice smoke remained generic:
  - `tables=10`, `geometry=10`, `phrase_ok=yes`, `bad_phrase=no`, `gray_attrs=34`.
- Exact-string guard for invoice path/content terms:
  - `0` hits.
