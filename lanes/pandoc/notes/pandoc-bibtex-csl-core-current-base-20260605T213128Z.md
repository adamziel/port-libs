# Pandoc BibTeX/CSL Core Current Base Issue Title

Slice: `pandoc-bibtex-csl-core-current-base-20260605T213128Z`
Base accepted HEAD: `6272e91a585b76e3a72ea7581df0faf625059c88`
Date: 2026-06-05 UTC

## Behavior

Implemented one bounded BibTeX/CSL support-library behavior:
BibLaTeX `issuetitle`, `issuesubtitle`, and `issuetitleaddon` metadata now
survives the native PHP handoff for special journal issues.

- `BibtexCslParser` composes `issuetitle` plus `issuesubtitle` into
  `issue-title` and maps `issuetitleaddon` to `issue-title-addon`.
- `CitationCslProcessor` normalizes those fields as `issueTitle` and
  `issueTitleAddon`, renders them in default bibliography review text, exposes
  `issue-title` / `issue-title-addon` to bounded CSL style output, and supports
  `issue-title` sort keys.
- `wordpress-bibtex-csl-handoff.php` now proves WordPress review blocks keep
  imported special-issue titles visible without invoking external tooling.

## Evidence

Red-first focused run after adding the test:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL maps bounded biblatex issue title fields into csl review metadata
1 test files, 1411 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS maps bounded biblatex issue title fields into csl review metadata
1 test files, 1424 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test
wordpress-bibtex-csl-handoff self-test passed
```

Syntax and JSON checks:

```text
php -l lanes/pandoc/src/BibtexCslParser.php
php -l lanes/pandoc/src/CitationCslProcessor.php
php -l lanes/pandoc/tests/CitationCslProcessorTest.php
php -l lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php
php -r "json_decode(file_get_contents('lanes/pandoc/lane-status.json'), true, 512, JSON_THROW_ON_ERROR); echo 'lane-status json ok'.PHP_EOL;"
php -r "json_decode(file_get_contents('lanes/pandoc/UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR); echo 'manifest json ok'.PHP_EOL;"
git diff --check -- lanes/pandoc
```

All syntax checks reported no syntax errors. JSON validation passed for both
lane JSON files. `git diff --check -- lanes/pandoc` passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1080 -> 1081`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1532 -> 1533`.
- Focused `CitationCslProcessorTest.php`: `+1` PASS case and `+15`
  assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`BibtexCslParser`, `CitationCslProcessor`, `CslStyle`, `MarkdownReader`, and
`WordPressBlockWriter`.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell
runner, external bibliography manager, online sanitizer, or online service was
executed.

The upstream-runner dependency blocker remains unchanged: full upstream Pandoc
runner parity still needs a hydrated Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal package and runner files
present before any Haskell runner plan is marked ready.

## Non-Overlap

This does not repeat accepted BibTeX/CSL slices for crossref/xdata
inheritance, source-file policy, entry sets, related entries, original/
translation metadata, legal fields, date ranges, date markers, title/subtitle
metadata, publication/eprint metadata, journal abbreviations, page-first
metadata, main-title/multivolume metadata, note/addendum/howpublished, entry
subtype, editorial roles, name annotations, shorthand labels, short creator
lists, software/dataset metadata, event metadata, event organizers, ID aliases,
distributed publisher/place lists, split URL dates, library call-number
metadata, `and others` et-al sentinels, sort override metadata,
container-author metadata, or pagination/bookpagination page-unit metadata.
It only owns bounded BibLaTeX special issue-title metadata handoff.

## Follow-Up

Keep fuller BibLaTeX issue metadata such as issue dates, venue-specific issue
labels, localized issue-title terms, richer reviewed-item fields, note-style
citeproc output, broader CSL style catalogs, and full upstream Pandoc/citeproc
runner parity as separate bounded slices.
