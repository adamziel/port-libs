# Pandoc Citation/CSL Core Current-Base Slice 2026-06-09T061346Z

Base accepted HEAD: `ad25c5c67f0859a34d555620436625e00d668451`

## Behavior

- Added default CSL locale terms for `event-organizer` label forms:
  - `short`: `org.` / `orgs.`
  - `verb`: `organized by`
  - `verb-short`: `org. by`
- This uses the existing `CslStyle` term table plus the existing
  `CitationCslProcessor` names-label renderer, which already routes both
  `event-organizer` and the `organizer` alias to the `event-organizer` term.
- Added a bounded WordPress handoff example showing citation and bibliography
  output for plural and singular event-organizer credits.

## Verification

Baseline before this patch:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 3807 assertions, 0 failures
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 3820 assertions, 0 failures
```

Syntax checks:

```text
php -l lanes/pandoc/src/CslStyle.php
No syntax errors detected in lanes/pandoc/src/CslStyle.php

php -l lanes/pandoc/tests/CitationCslProcessorTest.php
No syntax errors detected in lanes/pandoc/tests/CitationCslProcessorTest.php

php -l lanes/pandoc/examples/wordpress-citation-csl-event-organizer-terms-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-citation-csl-event-organizer-terms-handoff.php
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-citation-csl-event-organizer-terms-handoff.php --self-test
wordpress-citation-csl-event-organizer-terms-handoff self-test passed
```

Root harness: not run - isolated micro-slice.

## Mapping And Dependency Closure

- `lane-status.json` `phpPass`: `2428 -> 2429`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2817 -> 2818`.
- `mappedCitationCslCoreCases`: `12 -> 13`.
- No new native support component is needed. This reuses existing CSL locale
  parsing, names-label metadata, native citation rendering, AST handoff, and
  WordPress block writing.
- No Pandoc, Cabal solver/build/test command, Haskell runner, Word,
  LibreOffice, zip/unzip, external template engine, external converter,
  TeX/PDF engine, browser renderer, online service, live provider test, or
  live-service provider test was executed.

## Non-Overlap

This does not repeat accepted BibTeX event-organizer metadata import,
localized event bibliography labels, name substitute, institution, sort-key
name-list, multi-variable name-label, default author/composer/container-author/
original-author label, note-number, number-variable, or display-part slices. It
only fills the remaining default CSL term forms for event-organizer name-label
rendering.
