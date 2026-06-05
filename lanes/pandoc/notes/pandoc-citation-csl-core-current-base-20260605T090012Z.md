# Pandoc Citation/CSL Et-Al Handoff

Slice: `pandoc-citation-csl-core-current-base-20260605T090012Z`

Base: `dee38a517ce5ee272eef5f61b93a5a54e201fd7b`

## Source Truth

- Official CSL 1.0.2 specification: https://docs.citationstyles.org/en/v1.0.2/specification.html. `cs:names` may contain `cs:et-al`; CSL name rendering supports `delimiter-precedes-et-al` with `contextual`, `after-inverted-name`, `always`, and `never` policies.
- The spec also lists `cs:et-al` among rendering elements that accept bounded formatting attributes such as affixes, `text-case`, `strip-periods`, and quotes.
- No Pandoc, citeproc, Cabal build, Haskell runner, BibTeX, Biber, Word, LibreOffice, zip/unzip, TeX/PDF engine, browser renderer, online sanitizer, or online service was executed.

## Implementation

- `CslStyle` now parses a single bounded `cs:et-al` child under `cs:names`.
- The parser validates `term="et-al"` or `term="and others"` and bounded rendering attributes: `prefix`, `suffix`, `text-case`, `strip-periods`, and `quotes`.
- `CslStyle` now records `delimiter-precedes-et-al` for CSL name-list rendering and rejects invalid values.
- `CitationCslProcessor` now renders the et-al term through the style term/formatting path instead of hardcoding the default term.
- The processor applies `delimiter-precedes-et-al` policies, including `after-inverted-name` for truncated inverted names.
- The WordPress smoke example exposes the rendered citation text, bibliography entries, CSL summary metadata, and WordPress block output for reviewer handoff.

## Verification

```text
php -l lanes/pandoc/src/CslStyle.php
No syntax errors detected in lanes/pandoc/src/CslStyle.php

php -l lanes/pandoc/src/CitationCslProcessor.php
No syntax errors detected in lanes/pandoc/src/CitationCslProcessor.php

php -l lanes/pandoc/tests/CitationCslProcessorTest.php
No syntax errors detected in lanes/pandoc/tests/CitationCslProcessorTest.php

php -l lanes/pandoc/examples/wordpress-citation-csl-et-al-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-citation-csl-et-al-handoff.php

php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
json ok

php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 902 assertions, 0 failures

php lanes/pandoc/examples/wordpress-citation-csl-et-al-handoff.php --self-test
wordpress-citation-csl-et-al-handoff self-test passed

php tools/run-tests.php lanes/pandoc/tests
20 test files, 9457 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS'
775
```

```text
git diff --check -- lanes/pandoc
<no output; passed>
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `790 -> 791`
- `benchmarkDenominator.mapped`: `1250 -> 1251`
- `mappedCitationCslCoreCases`: `10 -> 11`
- Focused behavior: one new CSL name-list rendering PASS case covering `cs:et-al` term formatting and delimiter policy.

## Dependency Closure

No new support component is needed. This slice reuses the existing bounded native PHP `CslStyle`, `CitationCslProcessor`, Markdown reader, and WordPress block writer paths.

Full upstream Pandoc runner parity remains gated on hydrating the pinned Pandoc checkout with Cabal package/project files and creating a non-mutating Haskell test-runner plan. The local CSL support is not blocked by that runner gate.

## Non-Overlap And Follow-Up

This slice does not repeat the accepted CSL date-part, text-case, quote/strip-periods, macro, choose, locator/label, number, citation-position, name-part, name-substitute, year-suffix, collapse, bibliography display-part, or BibTeX/BibLaTeX metadata work.

Follow-up CSL work should keep full disambiguation and collapse parity, near-note and subsequent-author substitution, punctuation-in-quote locale behavior, richer name-part typography, citation sorting, note-style output, and full citeproc parity as separate bounded slices.
