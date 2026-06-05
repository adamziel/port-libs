# Pandoc BibTeX/CSL article-number handoff

Date: 2026-06-05 UTC
Base accepted HEAD: 13d069769033a9b5e2cc2577f3200aec1f8fed06
Micro-slice: pandoc-bibtex-csl-core-current-base-20260605T230222Z

## Scope

This slice maps bounded BibLaTeX electronic article identifiers into the native PHP CSL handoff. It covers `eid`, `article-number`, and `articlenumber` input fields and exposes the canonical CSL-ish `article-number` value as normalized `articleNumber` metadata.

## Behavior

- `BibtexCslParser` now emits `article-number` for bounded BibLaTeX article identifiers.
- `CitationCslProcessor` normalizes `article-number`, `articleNumber`, `articlenumber`, and `eid` into `articleNumber`.
- Default review bibliographies include `Article number: ...` when the value is present.
- Bounded CSL styles can render `<text variable="article-number">`; `eid` and `articlenumber` aliases are also accepted by the native renderer.
- The WordPress BibTeX/CSL handoff smoke now includes an `eid` article source and a custom CSL article-number render check.

## Source Truth And Non-Overlap

The behavior is part of the bounded BibTeX/BibLaTeX and CSL support-library surface for richer Pandoc conversion. It does not overlap the accepted pagination/bookpagination, issue-title, eprint/archive, DOI/URL/ISBN/ISSN, call-number, entry-subtype, journal abbreviation, date marker, event, related-entry, or title/subtitle BibTeX/CSL slices.

No local Pandoc upstream checkout was available in `/home/claude/port-libs/.upstream-cache/pandoc`, and this slice did not run upstream Pandoc, citeproc, BibTeX, Biber, Cabal, Haskell runners, external bibliography managers, online services, or external converters.

## Red-First Evidence

Before implementation, the new focused test failed at the parser handoff:

`php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`

Result: `1 test files, 1426 assertions, 1 failures`; expected `e2026-42`, actual `NULL` for `article-number`.

After implementation:

`php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`

Result: `1 test files, 1436 assertions, 0 failures`.

## WordPress Smoke

`php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test`

Result: `wordpress-bibtex-csl-handoff self-test passed`.

## Dependency Closure

No new support component is required. The slice reuses the existing native PHP `BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, and `WordPressBlockWriter` handoff paths.

The upstream-runner dependency blocker remains unchanged: full upstream Pandoc runner parity still needs a hydrated Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal project/package files and the test-pandoc/test-pandoc-lua-engine runner dependency closure before any bounded Haskell runner execution.

## Next

Keep `reprinttitle`, related option formatting, richer identifier families beyond article-number/eid, full CSL locale/citeproc parity, and upstream Pandoc runner parity as separate bounded slices.
