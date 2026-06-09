# Pandoc Citation/CSL Current-Base: Contextual Label Pluralization

Slice: `pandoc-citation-csl-core-current-base-20260609T013229Z`
Base accepted HEAD: `800b696344a9bf658321def4bebfd04d22ba2df2`
Date: 2026-06-09 UTC

## Behavior

This slice maps one bounded native Citation/CSL support case: contextual
`cs:label` rendering now treats semicolon-separated locator and number values as
plural. This closes the local gap where values such as `sub-verbo` locator
`migration; media`, page locator `3; 4`, and bibliography `section`
`front matter; migration notes` were rendered with singular labels.

The change is intentionally narrow. Existing comma-only named locator behavior
is preserved so accepted output such as the singular `p. ii, A-D` page locator
does not change.

## Focused Evidence

Baseline before this slice:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 3238 assertions, 0 failures
```

Red-first probe after adding the focused test but before the source fix:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 3242 assertions, 1 failures
FAIL pluralizes bounded csl contextual labels for semicolon locator lists
Expected: '(Smith 2024 s.vv. migration; media; Smith 2024 s.v. migration; Smith 2024 pp. 3; 4)'
Actual: '(Smith 2024 s.v. migration; media; Smith 2024 s.v. migration; Smith 2024 p. 3; 4)'
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 3247 assertions, 0 failures

php lanes/pandoc/examples/wordpress-citation-csl-label-pluralization-handoff.php --self-test
wordpress-citation-csl-label-pluralization-handoff self-test passed

php -l lanes/pandoc/src/CitationCslProcessor.php
No syntax errors detected in lanes/pandoc/src/CitationCslProcessor.php

php -l lanes/pandoc/tests/CitationCslProcessorTest.php
No syntax errors detected in lanes/pandoc/tests/CitationCslProcessorTest.php

php -l lanes/pandoc/examples/wordpress-citation-csl-label-pluralization-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-citation-csl-label-pluralization-handoff.php

php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } } echo "json ok\n";'
json ok

git diff --check -- lanes/pandoc
passed with no output
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2052 -> 2053`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2465 -> 2466`
- `UPSTREAM_TEST_MANIFEST.json` `inventory.mappedCitationCslCoreCases`: `12 -> 13`
- Focused assertions: `3238 -> 3247`

## Dependency Closure

No new support component is needed. This reuses native `CslStyle`,
`CitationCslProcessor`, `MarkdownReader`, `AstNode` citation clusters,
`WordPressBlockWriter`, and the lane-local focused test/example harness.

No Pandoc binary, citeproc, BibTeX, Biber, Cabal solver/build/test command,
Haskell runner, external bibliography manager, online service, live provider
test, or live-service provider test was executed.

## Non-Overlap And Follow-Up

This does not repeat accepted Citation/CSL slices for locator vocabulary,
locator conditionals, page-range formatting, term forms, symbol `and`, names
labels, display parts, subsequent-author substitution, et-al behavior, or
section number/text forms. It owns only semicolon-list contextual plural
detection for existing CSL labels.

Suggested next non-overlapping Citation/CSL work: note-style bibliography
behavior, name delimiter variants, or remaining style-option semantics not
already covered by accepted label/term/display/name slices.
