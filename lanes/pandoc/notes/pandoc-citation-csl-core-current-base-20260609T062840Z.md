# Pandoc Citation/CSL Multi-Variable Substitute Slice

- Micro-slice: `pandoc-citation-csl-core-current-base-20260609T062840Z`
- Base accepted HEAD: `d9055a06d30a55d79eba71a2d656134139a1a3c6`
- Scope: bounded native PHP Citation/CSL support only.

## Behavior

`CitationCslProcessor` now suppresses only the name variable that actually
rendered from a non-independent multi-variable `cs:names` substitute. A style
such as `variable="editor translator"` can use `editor` as the fallback for a
missing author while a later `translator` names element still renders its
translator credit.

The special combined identical `editor translator` rendering path still marks
both rendered groups when both groups are intentionally rendered together.

## Evidence

- Baseline before the new test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 3845 assertions, 0 failures`.
- Red-first after adding the focused test only: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` failed with `1 test files, 3850 assertions, 1 failures`; the editor fallback suppressed the later translator output.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 3855 assertions, 0 failures`.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-citation-csl-multi-variable-substitute-handoff.php --self-test` passed.
- PHP syntax checks passed for `lanes/pandoc/src/CitationCslProcessor.php`, `lanes/pandoc/tests/CitationCslProcessorTest.php`, and `lanes/pandoc/examples/wordpress-citation-csl-multi-variable-substitute-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Diff hygiene passed: `git diff --check -- lanes/pandoc`.

## Mapping

- `lane-status.json` `phpPass`: `2447 -> 2448`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2835 -> 2836`.
- `UPSTREAM_TEST_MANIFEST.json` `mappedCitationCslCoreCases`: `12 -> 13`.
- Focused assertion delta: `3845 -> 3855` in `CitationCslProcessorTest.php`.

## Dependency Closure

No new support component is needed. This reuses the existing `CslStyle`
parser, `CitationCslProcessor` names/substitute tracking, Pandoc-like AST,
Markdown reader, and WordPress block writer. Broader citeproc parity and any
external Pandoc/citeproc execution remain out of scope for this isolated lane.

## Non-Overlap

This does not repeat the prior Citation/CSL event-organizer label terms,
multi-variable name labels, choose-substitute behavior, or single-variable
substitute suppression slices. It only narrows substitution suppression for
multi-variable names fallbacks to the name group that actually rendered.

## Exclusions

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external template engine, external converter, TeX/PDF engine,
browser renderer, online service, live provider test, or live-service provider
test was executed.
