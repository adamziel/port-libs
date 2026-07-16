# Pandoc Citation Locator Diagnostics Current Base

Slice: `pandoc-citation-locator-diagnostics-current-base-20260609T202434Z`

Base: `45e8269431b076af3e4b01ab1cf906dfbaec4c98`

## Source Truth

- This bounded native PHP slice exposes citation locator diagnostics from the lane-local Citation/CSL AST state already parsed by `MarkdownReader` and rendered by `CitationCslProcessor`.
- Diagnostics are review metadata only: rendered citation strings remain unchanged.
- No Pandoc, citeproc, BibTeX, Biber, Cabal build, Haskell runner, browser renderer, online service, live provider test, or live-service provider test was executed.

## Implementation

- `CitationCslProcessor::citationLocatorDiagnostics()` collects bounded diagnostics from citation trees.
- Normalized `citation` and `citation_group` nodes now carry `cslLocatorDiagnostics` when a locator falls back to unlabeled page semantics or an explicit AST locator label is unsupported.
- `wordpress-citation-csl-locator-diagnostics-handoff.php` provides a WordPress handoff self-test for ambiguous locator text and unsupported explicit labels.

## Verification

```text
php -l lanes/pandoc/src/CitationCslProcessor.php
No syntax errors detected in lanes/pandoc/src/CitationCslProcessor.php
php -l lanes/pandoc/tests/CitationCslProcessorTest.php
No syntax errors detected in lanes/pandoc/tests/CitationCslProcessorTest.php
php -l lanes/pandoc/examples/wordpress-citation-csl-locator-diagnostics-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-citation-csl-locator-diagnostics-handoff.php
```

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 4128 assertions, 0 failures
```

```text
php lanes/pandoc/examples/wordpress-citation-csl-locator-diagnostics-handoff.php --self-test
wordpress-citation-csl-locator-diagnostics-handoff self-test passed
```

```text
php tools/run-tests.php lanes/pandoc/tests
42 test files, 57163 assertions, 0 failures
```

```text
php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " OK\n"; }'
lanes/pandoc/lane-status.json OK
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json OK
```

## Status Delta

- Rebased on `f85581b04`.
- `lanes/pandoc/lane-status.json` `phpPass`: `2836 -> 2837`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3051 -> 3052`.
- `mappedCitationCslCoreCases`: `12 -> 13`.
- Focused coverage: `CitationCslProcessorTest.php` moved from 4109 assertions to 4128 assertions.

## Non-Overlap And Follow-Up

This slice only adds locator review diagnostics. It does not change locator rendering, CSL locator term vocabularies, locator conditional matching, page-range formatting, citation sorting/collapsing, BibTeX/BibLaTeX metadata, source-file diagnostics, or broader citeproc parity.

Follow-up work should keep richer citeproc locator parsing, localized locator diagnosis text, and full upstream Pandoc/citeproc runner parity as separate bounded slices.
