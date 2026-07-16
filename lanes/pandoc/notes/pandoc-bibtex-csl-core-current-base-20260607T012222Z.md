# Pandoc BibTeX/CSL Media Identifier Slice

- Session: `port-dev-pandoc-bibtex-csl-20260607T012222Z`
- Micro-slice: `pandoc-bibtex-csl-core-current-base-20260607T012222Z`
- Accepted base: `4841a8141eb09153691392303a67ae59443e4510`
- Lane: `pandoc`

## Behavior

Implemented one bounded native PHP BibTeX/BibLaTeX handoff cluster for media and report identifier fields:

- `isan` maps to CSL review metadata `ISAN` and normalized `isan`.
- `ismn` maps to `ISMN` and normalized `ismn`.
- `isrn` maps to `ISRN` and normalized `isrn`.
- `iswc` maps to `ISWC` and normalized `iswc`.

`CitationCslProcessor` now exposes those values in default bibliography review text and bounded CSL `<text variable="ISAN|ISMN|ISRN|ISWC"/>` rendering. The WordPress BibTeX/CSL smoke includes a media identifier source packet and verifies both default bibliography output and custom CSL text-variable output.

## Source Truth And Non-Overlap

The source-truth behavior is the BibLaTeX datamodel's identifier fields for audiovisual works, music publications, technical reports, and musical works, as documented by the BibLaTeX package manual at `https://mirrors.ibiblio.org/CTAN/macros/latex/contrib/biblatex/doc/biblatex.pdf`. This slice only preserves imported identifier metadata; it does not run BibTeX, Biber, citeproc, Pandoc, Cabal/Haskell runners, or external bibliography managers.

This does not repeat accepted DOI/URL/ISBN/ISSN, PMID/PMCID, archive/eprint, article-number/eid, call-number/library, pagination/bookpagination, related/xref, entry-subtype, custom-field, or date/time BibTeX/CSL slices.

## Evidence

Baseline focused run before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1826 assertions, 0 failures
```

Final focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS maps bounded biblatex media and report identifiers into csl metadata
1 test files, 1850 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test
wordpress-bibtex-csl-handoff self-test passed
```

Status delta:

- `phpPass`: `1425 -> 1426`
- mapped denominator: `1841 -> 1842`
- `mappedBibtexCslCoreCases`: `4 -> 5`
- `bibtexCslCoreAssertions`: `65 -> 89`
- Focused assertion delta: `+24`

## Dependency Closure

No new support component is needed. This reuses native PHP `BibtexCslParser`, `CitationCslProcessor`, `CslStyle`, `MarkdownReader`, `WordPressBlockWriter`, the existing WordPress BibTeX/CSL handoff example, and the focused lane PHP harness.

Full upstream runner parity remains blocked by the existing upstream-runner dependency gate: the pinned Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` must be hydrated and Haskell/Cabal solver/build/runner work must be explicitly authorized before non-mutating upstream test planning can count as runner parity.

## Follow-Up

Keep follow-up work bounded and non-overlapping: URL description labels, additional safe BibLaTeX datamodel aliases, or Citation/CSL style behavior assigned to the Citation/CSL lane. Do not execute Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runners, external bibliography managers, online services, live provider tests, or live-service provider tests from this lane.
