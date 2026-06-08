# Pandoc BibTeX/CSL Keyword Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260608T170650Z`
Base: `4b4ed6566d9aa97b39e2a564de2e67000bb01006`

## Behavior

This slice adds bounded native BibTeX/BibLaTeX keyword handoff support without invoking Pandoc, citeproc, BibTeX, Biber, Cabal, Haskell runners, external bibliography managers, online services, live provider tests, or live-service provider tests.

`CitationCslProcessor` now normalizes `keyword` and `keywords` aliases into a CSL item keyword list, exposes a semicolon-delimited `keywordSummary`, renders keyword metadata in default bibliography review parts, and supports bounded CSL text variables `keyword`, `keywords`, `keyword-summary`, and `keywords-summary`.

The WordPress smoke demonstrates imported source-audit keywords remaining visible in citation bibliography blocks for reviewer queues.

## Evidence

- Baseline focused check before the behavior change: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2575 assertions, 0 failures`.
- Final focused check: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2593 assertions, 0 failures`.
- New example smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-keyword-handoff.php --self-test` passed.
- Coupled existing example smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test` passed.

Status delta: one new focused PHP PASS case, `+18` focused assertions, mapped denominator `2115 -> 2116`, `mappedBibtexCslCoreCases` `7 -> 8`, and `bibtexCslCoreAssertions` `121 -> 139`.

## Dependency Closure

No new support component is needed. The slice reuses native `BibtexCslParser` keyword parsing, `CitationCslProcessor` normalized metadata and CSL text-variable rendering, `MarkdownReader`, and `WordPressBlockWriter`.

## Non-Overlap

This does not repeat recent BibTeX/CSL handoffs for entry subtype, library call-number, pagination, article-number/eid, event-place lists, refsection/refsegment context, language options, related-entry metadata, or label disambiguation fields. It covers only keyword list propagation and reviewer-visible keyword rendering.

## Follow-Up

Next BibTeX/CSL work should pick a non-overlapping native bibliography handoff gap such as related-entry set metadata, additional date/name metadata, or CSL style rendering variables.
