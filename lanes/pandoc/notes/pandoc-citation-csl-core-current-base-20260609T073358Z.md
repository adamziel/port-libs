# Pandoc Citation/CSL Core Current Base - 2026-06-09T07:33:58Z

## Scope

Implemented bounded CSL text-variable rendering for normalized source-file attachment metadata.

This is a narrow Citation/CSL support-library slice. It reuses the existing native `CitationCslProcessor` source-file attachment policy and exposes the already-normalized importable paths plus rejected-path diagnostics through CSL `<text variable="...">` rendering:

- `source-file-summary`, `source-files-summary`, `source-files`, `source-attachment-summary`, `source-attachments`
- `source-file-paths`, `source-file-path`
- `source-file-labels`, `source-file-label`
- `source-file-media-types`, `source-file-media-type`
- `source-file-diagnostic-summary`, `source-file-diagnostics`, `source-file-policy-summary`
- `source-file-diagnostic-reasons`, `source-file-policy-reasons`

## Evidence

- Baseline focused run before lane edits: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 3954 assertions, 0 failures`.
- Red-first focused run after adding the source-file CSL test, before implementation: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> failed because `source-file-summary` fell through to the missing-source-file branch.
- Final focused run after implementation: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 3967 assertions, 0 failures`.
- New focused PASS case: `renders bounded csl source file attachment summaries for wordpress review`.
- New assertion delta: `+13`.
- Example smoke: `php lanes/pandoc/examples/wordpress-citation-csl-source-file-handoff.php --self-test` -> passed.

## Non-Overlap

This slice does not repeat accepted source-file attachment policy diagnostics, BibTeX/BibLaTeX attachment parsing, source provenance variables, source sort keys, citation aliases, xdata provenance, or custom BibLaTeX field/list/name rendering. It only closes the CSL rendering path for source-file attachment summaries and diagnostics that the processor already normalized.

## Dependency Closure

No new support component is needed. The slice reuses:

- `CitationCslProcessor` source-file normalization and policy diagnostics.
- `CslStyle` XML parsing and text-variable rendering.
- Existing Pandoc-like AST, Markdown reader, and WordPress block writer.

External Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, tar, external template engines, TeX/PDF engines, browser renderers, online services, live provider tests, live-service provider tests, BibTeX, Biber, bibliography managers, and citeproc were not executed.

## Follow-Up

A future non-overlapping Citation/CSL slice can add source-file sort-key behavior or localized attachment labels, but no follow-up is required before accepting this patch.
