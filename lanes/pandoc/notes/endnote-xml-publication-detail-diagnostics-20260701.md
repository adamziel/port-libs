# EndNote XML Publication Detail Diagnostics

Slice: `pandoc-endnote-xml-publication-details`

## Scope

- `CitationCslProcessor` now preserves EndNote XML publication detail evidence for journal/container titles, abbreviations, volume, issue, pages, DOI, URL-like fields, and malformed or empty detail diagnostics.
- Raw unsupported EndNote publication detail fields remain visible in `rawEndnoteXml`, including non-DOI/non-URL electronic resource numbers and custom fields.
- CSL variable rendering exposes `endnote-publication-detail-summary` and `endnote-publication-detail-diagnostic-summary` while preserving existing EndNote name, title/date, publication-type, attachment, and citation-locator diagnostics.
- Central lane counters were left on current `origin/main` to avoid regressing newer status metadata; this note records the follow-up accounting path for this repaired slice.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php` passed.
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed: 1 test file, 6220 assertions, 0 failures.
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed.
- `git diff --check` passed.
- `php tools/run-tests.php lanes/pandoc/tests` was attempted and remains outside this slice's passing gate: 534 test files, 142327 assertions, 8912 failures. The first failures are in existing DocBook reader, HTML writer global attribute, LaTeX writer, and Markdown surge fixtures, not in the focused CitationCslProcessor coverage.

No Pandoc binary, citeproc, BibTeX, Biber, Node tooling, online service, live provider, or external validator was invoked.
