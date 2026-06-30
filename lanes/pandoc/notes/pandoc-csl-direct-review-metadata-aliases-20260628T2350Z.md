# Pandoc Direct CSL Review Metadata Aliases

Slice: `pandoc-csl-direct-review-metadata-aliases-20260628T2350Z`
Issue: `plib-9b8`
Target: `integration/pandoc-semantics-json`

Implemented one bounded native PHP Citation/CSL direct JSON handoff case:
direct item input now normalizes `review-title`/`reviewTitle`,
`review-subtitle`/`reviewSubtitle`, and `review-genre`/`reviewGenre` into the
canonical reviewed metadata fields used by the local CSL renderer.

The slice preserves raw alias provenance, default bibliography text,
`review-title` and `reviewed-title` CSL variable rendering, `review-genre` and
`reviewed-genre` rendering, citation cluster output, and WordPress bibliography
handoff without invoking Pandoc, citeproc, BibTeX, Biber, browser renderers,
office converters, TeX engines, online services, or external validators.

Verification:

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslDirectReviewMetadataAliasTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslDirectReviewMetadataAliasTest.php`
- Result: `1 test files, 16 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- Result: `1 test files, 6018 assertions, 0 failures`
- `php -r 'foreach (["lanes/pandoc/lane-status.json","lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'`

Accounting:

- `phpPass`: `470 -> 471`
- `phpFail`: `0`

Non-goals:

This slice does not repeat the accepted BibLaTeX review metadata work, direct
source-file aliases, original-title aliases, translated-title aliases, or wider
JSON/native AST metadata compatibility. It is limited to direct CSL JSON review
title/subtitle/genre aliases and their renderer/WordPress visibility.
