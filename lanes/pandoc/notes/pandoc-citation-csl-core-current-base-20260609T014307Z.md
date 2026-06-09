# Pandoc Citation/CSL Current-Base: Collapse Delimiters

Slice: `pandoc-citation-csl-core-current-base-20260609T014307Z`
Base accepted HEAD: `9ab19c9e2380838c7ca01f28e9b3c5ee81262c5f`
Date: 2026-06-09 UTC

## Behavior

This slice maps one bounded native Citation/CSL support case from the CSL
1.0.2 cite grouping/collapsing contract:
https://docs.citationstyles.org/en/v1.0.2/specification.html#cite-collapsing

`CslStyle` now preserves explicit `cite-group-delimiter`,
`year-suffix-delimiter`, and `after-collapse-delimiter` attributes on
`cs:citation`. `CitationCslProcessor` applies those options during bounded
author-date collapse rendering:

- `collapse="year"` uses `cite-group-delimiter` between collapsed years.
- `collapse="year-suffix"` uses `year-suffix-delimiter` between collapsed
  suffixes.
- `after-collapse-delimiter` is used only after a rendered author-date group
  that actually collapsed; ordinary cite boundaries continue to use the layout
  delimiter.

Existing default outputs are preserved when these attributes are absent.

## Focused Evidence

Baseline before this slice:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 3261 assertions, 0 failures
```

Red-first probe after adding the focused test but before the source fix:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 3263 assertions, 1 failures
FAIL applies bounded csl custom delimiters for collapsed citation groups
Expected: ' + '
Actual: NULL
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 3273 assertions, 0 failures

php lanes/pandoc/examples/wordpress-citation-csl-collapse-delimiter-handoff.php --self-test
wordpress-citation-csl-collapse-delimiter-handoff self-test passed

php -l lanes/pandoc/src/CitationCslProcessor.php
No syntax errors detected in lanes/pandoc/src/CitationCslProcessor.php

php -l lanes/pandoc/src/CslStyle.php
No syntax errors detected in lanes/pandoc/src/CslStyle.php

php -l lanes/pandoc/tests/CitationCslProcessorTest.php
No syntax errors detected in lanes/pandoc/tests/CitationCslProcessorTest.php

php -l lanes/pandoc/examples/wordpress-citation-csl-collapse-delimiter-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-citation-csl-collapse-delimiter-handoff.php

php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } } echo "json ok\n";'
json ok

git diff --check -- lanes/pandoc
passed with no output
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2072 -> 2073`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2484 -> 2485`
- `UPSTREAM_TEST_MANIFEST.json` `inventory.mappedCitationCslCoreCases`: `12 -> 13`
- Focused assertions: `3261 -> 3273`

## Dependency Closure

No new support component is needed. This reuses native `CslStyle`,
`CitationCslProcessor`, `MarkdownReader`, `AstNode` citation clusters,
`WordPressBlockWriter`, and the lane-local focused test/example harness.

No Pandoc binary, citeproc, BibTeX, Biber, Cabal solver/build/test command,
Haskell runner, external bibliography manager, online service, live provider
test, or live-service provider test was executed.

## Non-Overlap And Follow-Up

This does not repeat accepted Citation/CSL slices for year-suffix
disambiguation, citation-number collapse, contextual label pluralization,
bibliography options, subsequent-author substitution, note-style position
conditionals, source/date sort keys, display parts, locator labels, or
name-rendering term behavior. It owns only explicit collapsed-citation
delimiter style options.

Suggested next non-overlapping Citation/CSL work: note-style bibliography
behavior, remaining name delimiter variants, or additional CSL style-option
semantics not covered by collapse delimiter handling.
