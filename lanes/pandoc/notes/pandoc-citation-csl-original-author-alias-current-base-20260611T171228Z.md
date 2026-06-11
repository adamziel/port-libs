# pandoc-citation-csl-original-author-alias-current-base-20260611T171228Z

Slice: `plib-u8rw9`, citation/bibliography CSL provenance.
Required base: `392b11a2e`.

## Change

`BibtexCslParser` now maps hyphenated BibLaTeX `original-author` fields into
CSL `original-author` creator metadata. The parser also treats
`original-author+an` as name annotations, so annotation provenance stays on the
creator names instead of being demoted to generic field annotations.

Focused coverage verifies raw BibTeX item metadata, normalized CSL item fields,
style rendering through `<names variable="original-author"/>`, bibliography
annotation summaries, and WordPress bibliography handoff.

No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

## Verification

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed: 1 test file, 4663 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 64279 assertions, 0 failures.
