# pandoc-bibtex-csl-core-current-base-20260605T150244Z

Lane: `pandoc`
Base accepted HEAD: `4b3f5d4114499e26b9bdc5ec4aade9cc1ee778a2`
Micro-slice: `pandoc-bibtex-csl-core-current-base-20260605T150244Z`

## Behavior

Bounded BibTeX/BibLaTeX handoff now preserves sort override fields and uses
them in CSL style sorting without changing the visible creator, date, or title:

- `sortname` / `sort-name` maps to `sort-name` and overrides author/editor sort
  comparisons.
- `sorttitle` / `sort-title` maps to `sort-title` and overrides title sort
  comparisons.
- `sortyear` / `sort-year` maps to `sort-year` and overrides issued/date sort
  comparisons.
- `sortkey` / `sort-key` maps to `sort-key` for explicit review styles that
  sort by a source-provided master key.

This keeps imported `.bib` packets reviewable when source bibliographies carry
manual sorting hints for particles, filing titles, intentionally backdated sort
years, or source-defined shelf/order keys.

## Changes

- `src/BibtexCslParser.php`
  - Maps bounded BibLaTeX sort override fields into CSL item metadata.
- `src/CitationCslProcessor.php`
  - Normalizes sort override fields onto CSL item records.
  - Exposes `sort-key`, `sort-name`, `sort-title`, and `sort-year` to bounded
    CSL text variables.
  - Applies the override fields to author/editor, issued/date, title, and
    explicit sort-key comparisons.
- `tests/CitationCslProcessorTest.php`
  - Adds a focused native PHP case for BibLaTeX sort override metadata,
    citation sorting, bibliography sorting, WordPress output, and direct CSL
    item normalization.
- `examples/wordpress-bibtex-csl-handoff.php`
  - Adds WordPress smoke coverage for sort override preservation and a styled
    bibliography order check.
- `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`
  - Record `phpPass` `960 -> 961`, mapped denominator `1415 -> 1416`, and the
    latest focused BibTeX/CSL slice.

## Verification

Red-first focused test before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1206 assertions, 1 failures
```

The failing assertion showed missing `sort-name` metadata.

Focused test after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1230 assertions, 0 failures
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

Diff check:

```text
git diff --check -- lanes/pandoc
```

Result: passed with no output.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`,
`MarkdownWriter`, and `WordPressBlockWriter` support paths.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell
runner, Word, LibreOffice, zip/unzip, TeX/PDF engine, browser renderer,
external bibliography manager, external validator, online sanitizer, or online
service was executed.

Full upstream-runner parity remains gated on hydrating the local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal project
files and runner test suites present.

## Non-Overlap

This slice does not repeat recent BibTeX/CSL handoffs for crossref/xdata,
source-file policy, entry sets, related entries, original/translation metadata,
legal fields, date ranges, title details, publication/eprint metadata, journal
abbreviations, page-first metadata, main-title/multivolume metadata,
note/addendum/howpublished, entry subtype, editorial roles, name annotations,
shorthand labels, short creator lists, software/dataset metadata, event
metadata, event organizers, ID aliases, distributed publisher/place lists,
split URL dates, library call-number metadata, or the `and others` et-al
sentinel. It only owns bounded BibLaTeX sort override metadata and its effect
on CSL style sort comparisons.

## Follow-Up

Keep localized collation, full citeproc disambiguation, rich CSL macro/date/name
rendering, note-style citation positions, and upstream Haskell runner parity as
separate bounded slices.
