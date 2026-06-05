# pandoc-bibtex-csl-core-current-base-20260605T142622Z

Lane: `pandoc`
Base accepted HEAD: `6c126186066ceb7460fca9cb3fcff42503b6c891`
Micro-slice: `pandoc-bibtex-csl-core-current-base-20260605T142622Z`

## Behavior

Bounded BibTeX/BibLaTeX name lists now recognize an unbraced `others` name as
the source-authored truncation marker used by BibTeX/BibLaTeX. The parser maps
that marker to native CSL name metadata with `etAl: true`, the CSL renderer
emits the configured et-al term from that exact position, and braced
`{others}` remains a literal creator for audit fidelity.

This keeps reviewer bibliographies honest for `.bib` packets that intentionally
record partial name lists such as:

```bibtex
author = {Smith, Ada and Ng, Nia and others}
```

## Changes

- `src/BibtexCslParser.php`
  - Marks unbraced cleaned name-list token `others` as a CSL et-al sentinel.
  - Leaves outer-braced literal names, including `{others}`, unchanged.
- `src/CitationCslProcessor.php`
  - Carries `csl-et-al`, `etAl`, and `et-al` name metadata into normalized CSL
    item names.
  - Renders explicit source-authored et-al sentinels independent of threshold
    truncation, preserving listed names before the sentinel.
  - Excludes the sentinel from name sort keys.
- `tests/CitationCslProcessorTest.php`
  - Adds a focused native PHP case for author/editor `and others`, literal
    `{others}`, default rendering, custom CSL et-al terms, and WordPress block
    output.
- `examples/wordpress-bibtex-csl-handoff.php`
  - Adds a review-packet smoke entry proving the behavior survives
    Markdown-to-WordPress bibliography handoff.
- `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`
  - Record `phpPass` `943 -> 944`, mapped denominator `1399 -> 1400`, and the
    latest focused BibTeX/CSL slice.

## Verification

Red-first focused test before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1174 assertions, 1 failures
```

Focused test after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1192 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test
wordpress-bibtex-csl-handoff self-test passed
```

Syntax checks:

```text
php -l lanes/pandoc/src/BibtexCslParser.php
No syntax errors detected in lanes/pandoc/src/BibtexCslParser.php

php -l lanes/pandoc/src/CitationCslProcessor.php
No syntax errors detected in lanes/pandoc/src/CitationCslProcessor.php

php -l lanes/pandoc/tests/CitationCslProcessorTest.php
No syntax errors detected in lanes/pandoc/tests/CitationCslProcessorTest.php

php -l lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php
```

JSON validation:

```text
php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
json ok
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
BibTeX/BibLaTeX parser, CSL renderer, Markdown reader, and WordPress block
writer.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell
runner, Word, LibreOffice, zip/unzip, TeX/PDF engine, browser renderer,
external validator, online sanitizer, or online service was executed.

Full upstream-runner parity remains gated on hydrating the local Pandoc checkout
at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal project files and
runner test suites present.

## Non-Overlap

This slice does not repeat recent BibTeX/CSL handoffs for crossref/xdata,
source-file policy, entry sets, related entries, original/translation metadata,
legal fields, date ranges, title details, publication/eprint metadata, journal
abbreviations, page-first metadata, main-title/multivolume metadata,
note/addendum/howpublished, entry subtype, editorial roles, name annotations,
shorthand labels, short creator lists, software/dataset metadata, event
metadata, event organizers, ID aliases, distributed publisher/place lists, split
URL dates, or library call-number metadata. It only owns the BibTeX/BibLaTeX
`and others` name-list sentinel handoff into CSL et-al rendering.

## Follow-Up

Keep full BibTeX/BibLaTeX name-list grammar parity, localized et-al terms,
note-style citation positions, richer CSL style XML/locales, and full citeproc
parity as separate bounded slices.
