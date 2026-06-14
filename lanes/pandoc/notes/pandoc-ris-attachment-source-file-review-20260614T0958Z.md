# Pandoc RIS Attachment Source File Review

Slice: `pandoc-ris-attachment-source-file-review-20260614T0958Z`

Base: current main `e36a5b808f`.

## Behavior

`CitationCslProcessor::risItems()` now preserves RIS `L1`, `L2`, `L3`, and
`L4` attachment/link tags as bounded source-file review metadata.

The normalized `fromRis()` handoff reuses the existing source-file policy:

- safe relative paths remain in `sourceFiles`;
- remote URI values become `sourceFileDiagnostics` with `remote-uri`;
- path traversal values become `sourceFileDiagnostics` with `path-traversal`;
- labels retain the source RIS tag, such as `RIS L1`.

The focused test verifies raw RIS item extraction, normalized source-file
metadata, CSL source-file variables, source-file diagnostics, citation and
bibliography rendering, and WordPress bibliography output.

## Evidence

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed: 1 test file, 5622 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed after rebase: 46 test files, 82604 assertions, 0 failures.
- `jq empty lanes/pandoc/lane-status.json`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`

## Metrics

- `phpPass`: `3506 -> 3507`
- `mappedCitationRisParserCases`: `2 -> 3`
- `citationRisParserAssertions`: `41 -> 57`
- Upstream mapped denominator: `3424 -> 3425`

## Non-Overlap

This does not repeat accepted BibTeX/BibLaTeX metadata, direct CSL JSON alias,
CSL style rendering, citation locator, EPUB, DOCX, ODF, PDF/Typst, XML/HTML5
DOM, ZIP/OPC, or native AST slices. It only extends the native RIS parser
handoff for attachment/link tags that were previously dropped before source-file
normalization.

No Pandoc binary, citeproc, BibTeX, Biber, bibliography manager, browser
renderer, online service, live provider test, or external validator was invoked.
