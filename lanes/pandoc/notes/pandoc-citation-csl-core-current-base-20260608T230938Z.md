# pandoc-citation-csl-core-current-base-20260608T230938Z

## Scope

- Lane: pandoc
- Micro-slice: `pandoc-citation-csl-core-current-base-20260608T230938Z`
- Accepted base: `e4c5b8530d7050cd247624ff66dfa0499e76de2a`
- Behavior cluster: bounded CSL `source` variable normalization, rendering,
  default bibliography provenance, and WordPress handoff.

## Source Truth

CSL item data includes a `source` variable for bibliography-source provenance.
The lane already preserved unknown raw fields for custom style fallback, but it
did not normalize `source`, expose it from `CitationCslProcessor::item()`, sort
against it as a first-class variable, or surface it in the default bibliography
used by WordPress review packets.

This slice makes `source` a native normalized item field, renders it explicitly
from CSL `<text variable="source"/>`, includes it in default bibliography
entries as `Source: ...`, and verifies that the WordPress block handoff keeps
that provenance visible. It does not introduce an external citeproc runner.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note existed before editing.
- Baseline focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 3089 assertions, 0 failures`
- Red-first focused command after adding the new case:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 3092 assertions, 1 failures`
  - Failure reason: normalized items did not expose `source` metadata and
    default bibliography entries did not surface `Source:` provenance.
- Final focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 3100 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-citation-csl-source-handoff.php --self-test`
  - Result: `wordpress-citation-csl-source-handoff self-test passed`
- PHP syntax:
  - `php -l lanes/pandoc/src/CitationCslProcessor.php`
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php -l lanes/pandoc/examples/wordpress-citation-csl-source-handoff.php`
  - Result: all reported no syntax errors.
- Diff whitespace:
  - `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1957 -> 1958`
- Focused Citation/CSL coverage: `1 test files, 3089 assertions -> 1 test
  files, 3100 assertions`
- No `UPSTREAM_TEST_MANIFEST.json` counter was changed; this is a bounded
  native support-library behavior slice.

## Dependency Closure

No new support component is needed. This reuses native
`CitationCslProcessor`, `CslStyle`, `MarkdownReader`, `WordPressBlockWriter`,
and focused `CitationCslProcessorTest.php` coverage.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell
runner, Word, LibreOffice, zip/unzip, external bibliography manager, external
converter, online service, live provider test, or live-service provider test
was executed.

## Non-Overlap

This does not repeat accepted audio/artwork BibLaTeX entry-type aliases,
source-file attachment diagnostics, citation alias provenance, event-place
lists, section/version/part number labels, role-label term defaults, note-style
metadata, sort-key override fields, subsequent-author/substitute slices, or
upstream-runner dependency audit slices. It is limited to the CSL `source`
variable as normalized provenance and default bibliography output.

## Follow-Up

A next non-overlapping Citation/CSL slice could cover source-aware sort
fixtures across bibliography layouts, a distinct CSL conditional/rendering
variable gap, or a remaining BibLaTeX-to-CSL provenance handoff.

## Root Harness

Not run - isolated micro-slice.
