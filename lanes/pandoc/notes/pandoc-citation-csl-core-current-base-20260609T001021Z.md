# Pandoc Citation/CSL Term Forms Handoff

Slice: `pandoc-citation-csl-core-current-base-20260609T001021Z`
Base accepted HEAD: `256cc5a788644a524334e4ade8b4640cb13ce3e0`

## Scope

This slice maps one bounded native Citation/CSL support case: CSL `cs:text`
term rendering now validates and preserves the standard locale term forms
`long`, `short`, `verb`, `verb-short`, and `symbol`, while retaining native
locale fallback behavior for `verb-short` and `symbol` forms.

Source truth: the CSL specification documents `cs:text term` `form` values as
`long`, `short`, `verb`, `verb-short`, and `symbol`, and defines fallback from
`verb-short` to `verb` and from `symbol` to `short` before `long`:
https://docs.citationstyles.org/en/v1.0.2/specification.html

## Implementation

- `CslStyle` now uses a shared bounded term-form validator for `cs:text
  term="..." form="..."` and locale `<term form="...">` definitions.
- Invalid non-CSL term forms are rejected during native style parsing instead
  of being silently stored as ad hoc custom forms.
- Existing term fallback remains native PHP: `verb-short` can fall back to
  `verb`, and `symbol` can fall back to `short`/`long` through the existing
  term table.

## Focused Evidence

Baseline before this slice:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 3165 assertions, 0 failures
```

Red-first probe after adding the focused test but before the source fix:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 3181 assertions, 1 failures
FAIL applies bounded csl text term forms and locale fallbacks
Expected invalid text term form to be rejected
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 3183 assertions, 0 failures

php lanes/pandoc/examples/wordpress-citation-csl-term-form-handoff.php --self-test
wordpress-citation-csl-term-form-handoff self-test passed

php -l lanes/pandoc/src/CslStyle.php
No syntax errors detected in lanes/pandoc/src/CslStyle.php

php -l lanes/pandoc/tests/CitationCslProcessorTest.php
No syntax errors detected in lanes/pandoc/tests/CitationCslProcessorTest.php

php -l lanes/pandoc/examples/wordpress-citation-csl-term-form-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-citation-csl-term-form-handoff.php

git diff --check -- lanes/pandoc
passed with no output
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP CSL style parser,
locale term table, citation renderer, MarkdownReader citation parsing, and
WordPress block writer. No Pandoc binary, citeproc, BibTeX, Biber, Cabal,
Haskell runner, external bibliography manager, online service, live provider
test, or live-service provider test was executed.

## Non-Overlap And Follow-Up

This does not repeat accepted slices for `name and="symbol"`, label symbol
forms, name label verb forms, number text forms, gendered ordinals, locator
labels, subsequent et-al behavior, name form count/short, or YAML metadata.
It owns only bounded `cs:text term` term-form validation and fallback handoff.

Suggested next non-overlapping Citation/CSL work: note-style bibliography
behavior, broader bibliography display alignment, or remaining style-option
semantics that are not already covered by the accepted Citation/CSL slices.
