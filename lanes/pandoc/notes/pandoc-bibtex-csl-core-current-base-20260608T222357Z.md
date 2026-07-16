# Pandoc BibTeX/CSL Unpublished Speech Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260608T222357Z`
Base accepted HEAD: `1a91e11e37bf1452c01f3630ee84977c3a03b00f`

## Behavior

- Added bounded native BibLaTeX `@unpublished` entry-type normalization in
  `BibtexCslParser`.
- `@unpublished` with a non-empty `eventtitle` now maps to CSL `speech`.
- `@unpublished` without `eventtitle` remains CSL `manuscript`.
- Preserved the existing `type` field as CSL `genre`, plus `eventtitle`,
  `eventdate`, and `venue` handoff metadata.
- Added focused CSL `cs:choose` type-conditional coverage and WordPress
  bibliography handoff coverage for the speech/manuscript distinction.
- Added a WordPress smoke for unpublished event handoff without invoking
  Pandoc, citeproc, BibTeX, Biber, external bibliography managers, Cabal,
  Haskell runners, online services, live provider tests, or live-service
  provider tests.

Source truth: the official Pandoc User's Guide says `unpublished` without
`eventtitle` maps to CSL `manuscript`, while `unpublished` with `eventtitle`
maps to CSL `speech` for talks, unpublished conference papers, and posters:
https://pandoc.org/MANUAL.html#conference-papers-published-vs.-unpublished

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` files existed before
  editing.
- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  -> `1 test files, 3022 assertions, 0 failures`.
- Red-first focused test after adding the unpublished/eventtitle expectation:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  -> `1 test files, 3024 assertions, 1 failures` because `@unpublished` with
  `eventtitle` still produced `manuscript`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  -> `1 test files, 3045 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-bibtex-csl-unpublished-speech-handoff.php --self-test`
  -> `wordpress-bibtex-csl-unpublished-speech-handoff self-test passed`.
- PHP lint:
  `php -l lanes/pandoc/src/BibtexCslParser.php` -> no syntax errors;
  `php -l lanes/pandoc/tests/CitationCslProcessorTest.php` -> no syntax
  errors;
  `php -l lanes/pandoc/examples/wordpress-bibtex-csl-unpublished-speech-handoff.php`
  -> no syntax errors.
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  -> `json ok`.
- Whitespace check: `git diff --check -- lanes/pandoc` -> passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1923` -> `1924`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2345` -> `2346`.
- `mappedBibtexCslCoreCases`: `7` -> `8`.
- `bibtexCslCoreAssertions`: `121` -> `144`.
- Focused assertion delta: `+23` assertions over the accepted-base
  Citation/CSL baseline.

## Dependency Closure

No new support component is needed. This reuses the native PHP
`BibtexCslParser`, `CitationCslProcessor`, `CslStyle`, `MarkdownReader`, and
`WordPressBlockWriter`. Full Pandoc/citeproc runner parity remains gated on the
upstream Haskell/Cabal runner path; no external runner or bibliography tool was
executed.

## Non-Overlap

This slice does not repeat accepted media entry-type mappings, media
identifiers, entry subtype metadata, call numbers, article numbers,
pagination/bookpagination, event metadata inheritance, event venue lists,
related/xref metadata, role mappings, or CSL creator variable coverage. It only
owns the Pandoc-documented BibLaTeX `@unpublished` plus `eventtitle` type
handoff into CSL `speech`.

## Follow-Up

Possible follow-ups should stay non-overlapping: event-type subtype labels,
conference paper publication-state behavior, or another safe BibLaTeX-to-CSL
field handoff not already mapped by the current BibTeX/CSL cases.
