# BibTeX/CSL Standard Name Suffix Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260609T072950Z`
Base: `7e30824b38b73655628a135f3cb7279a6bf5d6b4`

## Source Truth

BibTeX name grammar allows the canonical three-part form `von Last, Jr, First`.
The bounded PHP handoff already supported the lane's accepted import convention
`Last, First, Jr.`, but treated canonical `Smith, Jr., Ada` as given `Jr.` and
suffix `Ada`. This slice maps the canonical suffix-in-the-middle form into CSL
given/suffix metadata while preserving the accepted trailing-suffix convention.

No Pandoc, BibTeX, Biber, citeproc, Cabal/Haskell runner, office tool, archive
tool, external converter, online service, live provider test, or live-service
provider test was executed.

## Implemented Behavior

- `BibtexCslParser::nameToCsl()` now detects bounded name suffix tokens in the
  middle part of a three-part BibTeX name and swaps them into CSL `suffix`.
- Supported bounded suffix tokens include `Jr.`, `Sr.`, `Junior`, `Senior`,
  roman numeral suffixes, and numeric ordinal suffixes.
- Existing `Last, First, Jr.` inputs stay unchanged because only the middle
  comma part is treated as canonical BibTeX suffix syntax.
- WordPress bibliography output now preserves `Smith, Ada, Jr.` and
  `de la Cruz, Ana Maria, III` from `.bib` source text.

## Evidence

Baseline focused verification before this patch:

`php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`

Result: `1 test files, 3933 assertions, 0 failures`.

Red check before implementation:

`php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`

Result: `1 test files, 3935 assertions, 1 failures`; the new case failed with
expected given `Ada` but actual given `Jr.`.

Focused verification after implementation:

`php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`

Result: `1 test files, 3953 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-bibtex-csl-standard-name-suffix-handoff.php --self-test`

Result: `wordpress-bibtex-csl-standard-name-suffix-handoff self-test passed`.

## Status Delta

- Adds 1 focused PHP PASS case.
- Adds 20 focused assertions in `CitationCslProcessorTest.php`.
- Updates `UPSTREAM_TEST_MANIFEST.json` mapped count `2871 -> 2872`.
- Updates `mappedBibtexCslCoreCases` `7 -> 8`.
- Updates `bibtexCslCoreAssertions` `121 -> 141`.
- Updates `lane-status.json` `phpPass` `2494 -> 2495`.

## Dependency Closure

No new support component is needed. This slice reuses native `BibtexCslParser`,
`CitationCslProcessor`, `MarkdownReader`, and `WordPressBlockWriter`.

Full upstream Pandoc runner parity remains gated on a hydrated pinned Pandoc
checkout and reviewed non-mutating Cabal dependency plan before any Haskell
build, runner, or benchmark execution.

## Non-Overlap

This does not repeat accepted BibTeX/CSL clusters for source/section/supplement
variables, volume/part titles, type conditionals, xdata/crossref inheritance,
field or name annotations, direct creator role fields, language/date metadata,
source attachments, entry sets, related entries, or CSL rendering behavior. It
only owns canonical three-part BibTeX name suffix parsing.

## Follow-Up

Choose a non-overlapping BibTeX/CSL parser or handoff gap such as remaining
BibLaTeX datamodel fields, name parsing edge cases outside standard suffix
grammar, or bibliography review provenance.
