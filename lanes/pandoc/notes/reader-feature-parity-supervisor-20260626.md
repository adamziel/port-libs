# Supervisor Goal: Pandoc Reader Feature Parity Sweep

## Outcome
- Deepen CSV/TSV, BibTeX/BibLaTeX, XLSX, PPTX, and DOCX reader coverage for high-value real-world document features.
- Keep JavaScript, authorization, crypto, and DRM explicitly out of scope.
- Land inspectable code, tests, and a final missing-feature audit.

## Intensity
- Level: high
- Starting workers: 8 polecats
- Scaling rule: add workers only for independent reader scopes with clear test artifacts and low merge conflict risk.

## Current Checkpoint
- CSV/TSV: dialect detection, comments, alternate quote/escape, encoding/BOM handling, ragged/headerless rows, and column type metadata have focused tests.
- BibTeX/BibLaTeX: string/xdata inheritance, date aliases, name particles, field aliases, TeX accent/URL cleanup, and data-only entry filtering have focused tests.
- XLSX: merged cells, hyperlinks, rich text, date/number formats, cell style attributes, and worksheet drawing images have focused tests.
- PPTX: images, notes, lists, hyperlinks, merged table cells, layout/master/theme path metadata, theme table colors, and chart data extraction have focused tests.
- DOCX: comments, inserted/deleted revisions, styles, headers/footers, notes, numbering, bookmarks, fields, and OMML have focused tests.

## Non-Goals
- Do not add JavaScript execution or scripting support.
- Do not add crypto authorization, signatures, password handling, DRM, or protected-package bypasses.
- Do not port writers unless a test fixture needs writer output for reader verification.
- Do not refactor unrelated reader internals.

## Ground Truth
- Existing reader code in `lanes/pandoc/src/*Reader.php`.
- Existing focused tests in `lanes/pandoc/tests/*ReaderTest.php`.
- WordPress block writer behavior in `lanes/pandoc/src/WordPressBlockWriter.php`.
- Verification command: `php tools/run-tests.php lanes/pandoc/tests/<ReaderTest.php>`.

## Worker Topology
- `csv-tsv-evaluator`: audit and harden delimiter/encoding/type handling in `CsvReader.php` and `CsvReaderTest.php`.
- `bibtex-evaluator`: audit BibTeX/BibLaTeX field parity, nested TeX cleanup, entry filtering, and CSL metadata mapping in `BibTexReader.php` and tests.
- `xlsx-worker`: deepen XLSX support beyond the checkpoint, especially workbook edge cases around formatted numbers/dates, styles, images, relationships, and tables.
- `pptx-worker`: deepen PPTX support beyond the checkpoint, especially charts, inherited placeholders, theme styles, and table formatting.
- `docx-worker`: deepen DOCX review/style/table/media coverage, especially comment ranges, moves, table styling, image metadata, and revision markers.
- `regression-evaluator`: run focused and full Pandoc tests, report failures with exact repro commands.
- `upstream-auditor`: compare implemented features against likely Pandoc reader expectations and produce a missing-feature note.
- `integration-reviewer`: inspect diffs for conflicts, duplicated logic, unsafe XML/package handling, and missing assertions.

## Workflow
1. Workers inspect the checkpoint and owned files.
2. Workers implement or produce a written audit only inside their scope.
3. Each worker runs focused tests for its scope.
4. Supervisor integrates accepted diffs, rejects shallow or duplicate work, and runs broader tests.
5. Supervisor records the remaining feature gaps.

## Quality Gates
- Every implementation worker must add or update focused tests.
- Every reader change must preserve malformed/empty input behavior where already tested.
- XML/ZIP relationships must be resolved through existing helpers, not ad hoc string concatenation.
- Table/image/style metadata must survive WordPress block conversion when that is how the reader is verified.
- No worker may alter `.beads/config.yaml`.

## Rejected Distractions
- Renaming readers, moving tests, formatting-only churn, broad writer refactors, or changing public AST conventions without a failing case.
- Support for JavaScript execution, DRM, encrypted documents, or authorization mechanisms.

## Final Acceptance Criteria
- Focused tests pass for all touched reader tests.
- Full Pandoc lane test pass is attempted and failures are either fixed or documented with exact failing tests.
- The final audit says what is supported and what remains missing for CSV/TSV, BibTeX/BibLaTeX, XLSX, PPTX, and DOCX.
