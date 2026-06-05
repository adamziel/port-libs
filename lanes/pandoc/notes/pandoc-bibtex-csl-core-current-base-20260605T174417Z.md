# Pandoc BibTeX/CSL Core Current Base Related References

Slice: `pandoc-bibtex-csl-core-current-base-20260605T174417Z`
Base accepted HEAD: `468f67cc261481eaaf7f76a7fa67c6e0dfff4edd`
Date: 2026-06-05 UTC

## Scope

Implemented one bounded BibTeX/CSL handoff behavior: BibLaTeX
`related`, `relatedtype`, and `relatedstring` metadata now flows out of the
raw parser payload into normalized CSL review metadata.

`CitationCslProcessor` now exposes:

- normalized `relatedKeys`, `relatedType`, `relatedString`,
  `relatedItems`, and `missingRelatedKeys` fields;
- bounded CSL text variables `related`, `related-summary`,
  `related-keys`, `related-type`, `related-string`, and
  `missing-related-keys`;
- default bibliography review text for WordPress import queues, including
  missing related keys.

The WordPress BibTeX/CSL example now verifies that a related manual keeps the
companion source set and missing related key visible in the rendered
bibliography.

## Red/Green Evidence

Red-first focused test after adding the new case and before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1320 assertions, 1 failures
```

The failure showed missing normalized `relatedKeys` metadata:

```text
Expected: array (
  0 => 'migration-review-set',
  1 => 'missing-related',
)
Actual: NULL
```

Focused test after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1336 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test
wordpress-bibtex-csl-handoff self-test passed
```

## Status Delta

- `lane-status.json` `phpPass`: `1021 -> 1022`
- `UPSTREAM_TEST_MANIFEST.json` mapped: `1475 -> 1476`
- `mappedBibtexCslCoreCases`: `2 -> 3`
- `bibtexCslCoreAssertions`: `38 -> 70`
- Focused `CitationCslProcessorTest.php`: `+1` PASS case and `+32`
  assertions, ending at `1 test files, 1336 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`,
`WordPressBlockWriter`, and bounded CSL style renderer.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell
runner, external bibliography manager, online sanitizer, or online service was
executed.

The upstream-runner dependency gate remains unchanged: hydrate the pinned
Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal
project/package files and runner suites present before any non-mutating Cabal
plan is marked ready.

## Non-Overlap

This does not repeat accepted BibTeX/CSL slices for crossref/xdata
inheritance, source-file policy, entry sets, original/translation metadata,
legal fields, date ranges, title details, publication/eprint metadata, journal
abbreviations, page-first metadata, main-title/multivolume metadata,
note/addendum/howpublished, entry subtype, editorial roles, name annotations,
shorthand labels, short creator lists, software/dataset metadata, event
metadata, event organizers, ID aliases, distributed publisher/place lists,
split URL dates, library call-number metadata, `and others` et-al sentinels,
sort override metadata, container-author metadata, or event-label
localization. It only owns normalized related-reference review metadata and
bounded CSL/WordPress rendering for that metadata.

Root harness: not run - isolated micro-slice.
