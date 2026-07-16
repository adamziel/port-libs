# Pandoc Citation/CSL Current-Base Choose Match Values Slice

Micro-slice: `pandoc-citation-csl-core-current-base-20260607T025815Z`
Base accepted HEAD: `a6234f10be73d34eb8d5b44905e2a9aba403ceaa`

## Behavior

- `CitationCslProcessor` now evaluates multi-value CSL `type` and `locator` conditions per declared value.
- Default `match="all"` now requires every declared value to match, `match="any"` accepts at least one declared value, and `match="none"` accepts no declared values.
- Existing `variable`, `position`, `disambiguate`, `is-numeric`, `is-uncertain-date`, and `is-circa-date` condition behavior is unchanged.
- The WordPress handoff example preserves the corrected all/any/none branch routing for reviewer citation packets.

## Source Truth

- Official CSL 1.0.2 `cs:choose` semantics: `https://docs.citationstyles.org/en/v1.0.2/specification.html#choose`
- No upstream citeproc, Pandoc, Cabal, Haskell runner, BibTeX, Biber, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 1868 assertions, 0 failures`.
- Red-first: the new choose match-values case failed because `type="book article-journal"` and `locator="page section"` incorrectly routed through default all-match branches.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 1880 assertions, 0 failures`.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-citation-csl-choose-match-values-handoff.php --self-test` passed.
- Syntax checks passed for `CitationCslProcessor.php`, `CitationCslProcessorTest.php`, and `wordpress-citation-csl-choose-match-values-handoff.php`.
- Whitespace check: `git diff --check -- lanes/pandoc` passed with no output.

## Status Delta

- `lane-status.json` `phpPass`: `1442 -> 1443`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1859 -> 1860`.
- `mappedCitationCslCoreCases`: `11 -> 12`.
- Focused test growth: `+1` PHP PASS case and `+12` focused assertions.

## Dependency Closure

No new support component is needed. This slice reuses the native `CslStyle` parser, `CitationCslProcessor` condition evaluator, `MarkdownReader`, `WordPressBlockWriter`, and the focused lane PHP harness. Full upstream citeproc/Pandoc/Haskell runner parity remains intentionally out of scope for this bounded support-library handoff.

## Non-Overlap

This slice does not touch the latest accepted CSL name-label, et-al, particle, disambiguation, date, locator-label, or bibliography substitution surfaces. Follow-up Citation/CSL work should stay in non-overlapping native behavior such as locale-option gaps, note-style citation context, label terms beyond mapped cases, or explicit citeproc parity diagnostics.
