# Pandoc BibTeX/CSL Date Markers Slice

- Session: `port-dev-pandoc-bibtex-csl-20260605T202752Z`
- Micro-slice: `pandoc-bibtex-csl-core-current-base-20260605T202752Z`
- Accepted base: `980a74fd659efa35046d60e34265350692a700e6`
- Lane: `pandoc`

## Behavior

Implemented bounded native PHP BibLaTeX date marker preservation for CSL handoff:

- `BibtexCslParser` now preserves date-field-only `~`, `?`, and `%` suffixes while still emitting structured CSL `date-parts`.
- Single dates and bounded date ranges keep `raw`, `circa`, and `uncertain` metadata for `date`, `origdate`, `urldate`, and `eventdate`.
- `CitationCslProcessor` normalizes those markers into `issuedDate`, `accessedDate`, `originalDate`, and `eventDate`.
- Default review bibliographies include `Date markers: ...` metadata.
- Bounded CSL style rendering can address `date-marker-summary`, `issued-status`, `accessed-status`, `original-date-status`, `event-date-status`, and matching `*-raw` variables.
- The WordPress BibTeX/CSL handoff smoke now includes an approximate/uncertain date source and verifies visible review output.

## Non-Overlap

This slice does not repeat accepted BibTeX/CSL work for date ranges, split URL dates, date-part CSL forms, entry subtype, call-number/library, pagination/bookpagination, shorthand/short creator lists, related entries, event metadata, sort overrides, or container-author handoff. It extends only the missing date-marker metadata path on top of the existing date parsing and CSL rendering support.

## Dependency Closure

No new support component is required. The patch reuses native PHP `BibtexCslParser`, `CitationCslProcessor`, `CslStyle`, `MarkdownReader`, and `WordPressBlockWriter`.

No Pandoc, citeproc, BibTeX, Biber, Cabal build, Haskell runner, external bibliography manager, online sanitizer, or online service was executed.

## Evidence

Red-first focused run after adding the test failed on the missing marker metadata:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL preserves bounded biblatex uncertain and approximate date markers in csl metadata
1 test files, 1386 assertions, 1 failures
```

Focused test after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS preserves bounded biblatex uncertain and approximate date markers in csl metadata
1 test files, 1409 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test
wordpress-bibtex-csl-handoff self-test passed
```

Final lane checks:

```text
php -l lanes/pandoc/src/BibtexCslParser.php
No syntax errors detected in lanes/pandoc/src/BibtexCslParser.php

php -l lanes/pandoc/src/CitationCslProcessor.php
No syntax errors detected in lanes/pandoc/src/CitationCslProcessor.php

php -l lanes/pandoc/tests/CitationCslProcessorTest.php
No syntax errors detected in lanes/pandoc/tests/CitationCslProcessorTest.php

php -l lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php

php -r "json_decode(file_get_contents('lanes/pandoc/lane-status.json'), true, 512, JSON_THROW_ON_ERROR); echo 'lane-status json ok'.PHP_EOL;"
lane-status json ok

php -r "json_decode(file_get_contents('lanes/pandoc/UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR); echo 'manifest json ok'.PHP_EOL;"
manifest json ok

git diff --check -- lanes/pandoc
```

Expected movement:

- `phpPass`: `1068 -> 1069`
- Manifest mapped checks: `1521 -> 1522`
- Focused assertion growth: `+24` assertions in `CitationCslProcessorTest.php`

## Follow-Up

Keep fuller EDTF seasons/open intervals, BibLaTeX date eras, BCE date edge cases, richer citeproc date rendering, and full external bibliography-manager parity as separate bounded slices.
