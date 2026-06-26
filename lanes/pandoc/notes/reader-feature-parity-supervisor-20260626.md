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
- XLSX: merged cells, hyperlinks, rich text, date/number formats, cell style attributes, formula/error metadata, worksheet table/autofilter metadata, and worksheet drawing images have focused tests.
- PPTX: images with geometry/crop metadata, notes, lists, hyperlinks, merged table cells, layout/master/theme path metadata, theme table colors, side-specific table border metadata, and chart data extraction have focused tests.
- DOCX: comments, inserted/deleted revisions, style spans, direct run font/color/highlight metadata, headers/footers, notes, numbering, bookmarks, simple and split complex fields, and OMML have focused tests.
- Polecat control-plane note: `gt polecat list --all --json` and `gt polecat list port_libs --json` timed out during the 2026-06-26 continuation pass, so the supervisor kept implementation moving in the integration worktree instead of waiting on idle-worker discovery.

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

## Post-Integration Addendum
- Accepted worker output:
  - CSV delimiter hardening from `port_libs/polecats/garnet`.
  - BibLaTeX cleanup from `port_libs/polecats/obsidian`.
  - PPTX inherited placeholder support from `port_libs/polecats/basalt`.
  - DOCX comment ranges, move revisions, table vertical merges/style metadata, image metadata/dimensions, and run style deepening from `port_libs/polecats/flint`, transplanted onto the current branch by the supervisor to avoid stale-base regressions.
  - Regression, integration-review, and missing-feature audit artifacts from `jasper`, `opal`, and `onyx`.
- Rejected worker output:
  - XLSX source replacement from `port_libs/polecats/amber`, because it was based on an older reader shape and would remove already-landed XLSX features. The current branch already covers merged cells, hyperlinks, rich text, style indexes, dates, worksheet drawing images, and image metadata.
- Review findings resolved after the worker audit:
  - ZIP/OPC now enforces per-entry, aggregate read, and entry-count budgets in `ZipOpcPackage`.
  - XLSX/PPTX relationship target resolution now uses strict package path normalization for internal relationships.
  - DOCX media target normalization rejects unresolved above-root traversal.
  - CSV/TSV metadata now preserves exact ragged row widths before normalization.
  - DOCX optional XML sidecars now record diagnostics instead of aborting a readable document.
- Still open after this integration:
  - Full parse diagnostics/provenance are incomplete across readers.
  - BibLaTeX still lacks full data-model/citeproc parity.
  - XLSX now preserves formula/error metadata plus worksheet table/autofilter metadata but still lacks formula evaluation, pivots, charts, slicers, hidden-sheet policy controls, and full style/theme inheritance.
  - PPTX now preserves more image geometry/crop and table border metadata but still lacks SmartArt/diagram reconstruction, comments, full z-order/layout inheritance, and rich media extraction.
  - DOCX now preserves direct run font/color/highlight metadata and maps simple split complex fields, but still lacks accept/reject revision modes, section-specific header/footer application, TOC/index/bibliography field semantics, text boxes, VML/object images, and complete table/style inheritance.
