# pandoc-bibtex-csl-core-current-base-20260606T132742Z

## Scope

Bounded BibTeX/CSL current-base slice on accepted base
`c004817c65b5e36e22e0d13ad28c2be2d8a34107`.

This slice preserves BibLaTeX custom review fields `usera` through `userf`
and verbatim custom fields `verba` through `verbc` without invoking Pandoc,
citeproc, BibTeX, Biber, Cabal, Haskell runners, external bibliography
managers, online services, or live provider tests.

## Behavior

- `BibtexCslParser` now maps bounded custom fields into
  `biblatex-custom-fields` on CSL items after existing TeX cleanup.
- `CitationCslProcessor` normalizes those fields into
  `biblatexCustomFields` plus a deterministic `biblatexCustomFieldSummary`.
- Default bibliography output appends a review summary when custom fields are
  present.
- Bounded CSL style XML can render `usera`-`userf`, `verba`-`verbc`,
  `biblatex-custom-fields`, and `biblatex-custom-field-summary`.
- Direct CSL item input can provide either top-level custom fields or a
  `biblatex-custom-fields` map; unsupported/non-scalar custom field data fails
  closed.
- The WordPress BibTeX/CSL handoff smoke now includes a custom-field review
  source and verifies escaped WordPress output.

## Evidence

Red-first focused check before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1681 assertions, 1 failures
```

The new fixture failed because `biblatex-custom-fields` was not present on the
parsed CSL item.

Final focused verification:

```text
php -l lanes/pandoc/src/BibtexCslParser.php
No syntax errors detected in lanes/pandoc/src/BibtexCslParser.php

php -l lanes/pandoc/src/CitationCslProcessor.php
No syntax errors detected in lanes/pandoc/src/CitationCslProcessor.php

php -l lanes/pandoc/tests/CitationCslProcessorTest.php
No syntax errors detected in lanes/pandoc/tests/CitationCslProcessorTest.php

php -l lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php

php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1694 assertions, 0 failures

php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test
wordpress-bibtex-csl-handoff self-test passed

php -r '<lane JSON validation command>'
lanes/pandoc/lane-status.json: valid JSON
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json: valid JSON

git diff --check -- lanes/pandoc
<no output>
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1335` -> `1336`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1749` -> `1750`.
- `mappedBibtexCslCoreCases`: `3` -> `4`.
- `bibtexCslCoreAssertions`: `52` -> `65`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native
BibTeX parser, CSL processor/style renderer, Markdown writer, and WordPress
writer handoff paths.

Remaining out-of-scope dependency work is full citeproc parity, localized or
typed semantics for arbitrary BibLaTeX custom fields, BibTeX/Biber execution,
Pandoc/Haskell runner execution, and hydrated upstream citation-suite parity.

## Non-Overlap

This does not repeat accepted BibTeX/CSL slices for entry subtype, call-number,
pagination/bookpagination, article-number/eid, reviewed-work metadata,
reprint-title, event-place lists, event organizers/localization, source-file
attachments, related/license fields, split URL dates, pubmed identifiers,
publisher/location lists, journal abbreviations, issue-title fields, or
legal/patent metadata.
