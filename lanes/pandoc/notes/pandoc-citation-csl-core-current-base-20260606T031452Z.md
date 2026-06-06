# Pandoc Citation/CSL Current-Base Institution Name Handoff

Slice: `pandoc-citation-csl-core-current-base-20260606T031452Z`
Base accepted HEAD: `ec3e9194c10aec1d28ce93f5e409f6a334a84508`

## Behavior

Implemented bounded CSL `cs:institution` support under `cs:names` for literal organization authors:

- `CslStyle` parses one `institution` child per `names` element and records bounded `institution-part name="long"` rendering metadata.
- `CitationCslProcessor` applies that metadata only to literal names, preserving existing person-name rendering, name-part formatting, et-al behavior, and family/given script ordering.
- Supported institution-part formatting includes `prefix`, `suffix`, `text-case`, `strip-periods`, and `quotes`.
- Unsupported `institution-parts` modes beyond `long` and unsupported institution-part names remain explicit bounded-scope errors.

This maps the CSL institution long-name formatting contract needed for organization-author citation and bibliography handoff without invoking Pandoc, citeproc, BibTeX, Biber, Cabal, Haskell runners, external bibliography managers, online services, or live provider tests.

## Evidence

- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 1505 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-citation-csl-institution-handoff.php --self-test`
  - Result: `wordpress-citation-csl-institution-handoff self-test passed`

Focused delta:

- `phpPass`: `1170 -> 1171`
- `benchmarkDenominator.mapped`: `1620 -> 1621`
- `mappedCitationCslCoreCases`: `10 -> 11`

## Dependency Closure

No new support component is needed. This slice reuses native PHP `CslStyle`, `CitationCslProcessor`, `MarkdownReader`, and `WordPressBlockWriter` support.

The upstream-runner blocker is unchanged: full Pandoc/citeproc parity still needs a hydrated Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`, Cabal project/package files, runner dependency closure, and Haskell Tasty runner builds before any bounded upstream runner execution can be marked ready.

## Non-Overlap

This slice avoids the accepted citation clusters for date-part forms, style layouts/macros/choose predicates, `is-numeric`, `is-uncertain-date`, locator/page labels, number rendering, punctuation-in-quote, name-part family/given formatting, name-form short/count, initialization, sort separators, compact family/given scripts, demoted particles, delimiter-precedes-last, et-al variants, subsequent et-al, subsequent-author substitution, year suffixes, citation collapse, BibTeX/BibLaTeX metadata handoff, and non-CSL Pandoc support-library lanes.

## Follow-Up

Keep these as separate bounded slices:

- CSL short/long-short institution abbreviations and abbreviation-list lookup.
- Locale-specific institution rendering variants.
- Full note-style output and bibliography disambiguation.
- Full citeproc/Pandoc runner parity.
