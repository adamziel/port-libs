# Pandoc BibTeX/CSL available/submitted date handoff

Micro-slice: `pandoc-bibtex-csl-core-current-base-20260609T024431Z`

Base accepted HEAD: `12507a9792ad5cde3ccd9d84d97d5835d2a8ef77`

## Scope

Added a bounded native `.bib` import bridge for CSL `available-date` and
`submitted` date variables. The existing CSL renderer already normalized and
rendered those direct item variables; this slice maps explicit BibTeX field
aliases into that existing path:

- `availabledate`, plus split `availableyear`, `availablemonth`, and
  `availableday`
- `submitteddate`, `submitted`, plus split `submittedyear`, `submittedmonth`,
  and `submittedday`
- matching `available*` and `submitted*` time/end-time part fields

The parser reuses the existing BibTeX date parser, including literal fallback,
uncertain/circa markers, month-name aliases, split date parts, and timezone
validation. It does not add a citeproc, BibTeX, Biber, Pandoc, Cabal, or online
service dependency.

## Evidence

Baseline before this slice:

```sh
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
```

Result: `1 test files, 3396 assertions, 0 failures`.

Focused verification after this slice:

```sh
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
```

Result: `1 test files, 3433 assertions, 0 failures`.

```sh
php lanes/pandoc/examples/wordpress-bibtex-csl-available-submitted-date-handoff.php --self-test
```

Result: `wordpress-bibtex-csl-available-submitted-date-handoff self-test passed`.

Syntax/metadata checks:

```sh
php -l lanes/pandoc/src/BibtexCslParser.php
php -l lanes/pandoc/tests/CitationCslProcessorTest.php
php -l lanes/pandoc/examples/wordpress-bibtex-csl-available-submitted-date-handoff.php
php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc lane JSON ok\n";'
```

Results: all PHP files reported no syntax errors; lane JSON decoded
successfully.

```sh
git diff --check -- lanes/pandoc
```

Result: no whitespace errors.

## Non-Overlap

This does not repeat the accepted direct CSL `available-date`/`submitted`
renderer slice, DOCX OpenXML directional inline wrappers, source locator labels,
uncommon locator vocabulary, part/version/section/supplement number rendering,
or broader BibLaTeX metadata handoff. The new behavior is limited to `.bib`
field import into already-supported CSL date variables and a WordPress block
smoke for that import path.

## Dependency Closure

No new support component is required. The slice reuses the native
`BibtexCslParser`, `CitationCslProcessor`, Markdown reader, and WordPress block
writer. Full upstream Pandoc runner parity remains out of scope here because
the upstream Pandoc checkout/cache is absent in this isolated worktree and the
runner would require building Haskell Tasty executables through Cabal.

## Next

Keep broader BibLaTeX date inheritance edge cases, CSL locale refinements,
citation-position disambiguation, DOCX document-part parsing, EPUB XHTML AST
parsing, and upstream-runner dependency planning as separate bounded gates.
