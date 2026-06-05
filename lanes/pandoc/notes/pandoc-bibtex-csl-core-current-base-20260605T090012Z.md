# Pandoc BibTeX CSL Core Current Base

Micro-slice: `pandoc-bibtex-csl-core-current-base-20260605T090012Z`
Base: `dee38a517ce5ee272eef5f61b93a5a54e201fd7b`

## Behavior

- Added bounded BibLaTeX `ids` alias handoff support.
- `BibtexCslParser` now maps `ids = {legacy-key, alternate-key}` to CSL-like
  `citation-aliases` metadata while preserving raw BibTeX fields for review.
- `CitationCslProcessor` indexes aliases as secondary citation keys, resolves
  them to the canonical item for rendering, canonicalizes alias citations for
  citation-position/year-suffix state, and de-duplicates bibliography output by
  primary item id.
- Bounded CSL style rendering can expose aliases with
  `<text variable="citation-aliases"/>`.
- Conflicting aliases are rejected instead of silently shadowing another item.
- Updated the WordPress BibTeX handoff smoke so alias citations remain
  resolvable and produce one bibliography item.

## Source Truth

This follows BibLaTeX's `ids` citation-alias field convention and the lane-local
BibTeX/BibLaTeX-to-CSL handoff contract. The slice is bounded to native PHP
metadata preservation, citation lookup, bibliography de-duplication, style
variable exposure, and WordPress review output. It does not attempt note-style
citation rendering, full citeproc disambiguation, or external bibliography
processor parity.

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, Haskell runner, Cabal
build, online service, or external converter was executed.

## Evidence

Red check before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
Focused test run: 1 selected test files (root lock skipped)
...
FAIL maps bounded biblatex ids aliases into canonical csl citations
Values are not identical
Expected: array (
  0 => 'legacy-manual',
  1 => 'source-packet-manual',
)
Actual: NULL
1 test files, 886 assertions, 1 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
Focused test run: 1 selected test files (root lock skipped)
...
PASS maps bounded biblatex ids aliases into canonical csl citations
...
1 test files, 906 assertions, 0 failures
```

The new case adds 31 focused assertions.

Additional required checks:

```text
php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test
wordpress-bibtex-csl-handoff self-test passed

php -l lanes/pandoc/src/BibtexCslParser.php
No syntax errors detected in lanes/pandoc/src/BibtexCslParser.php

php -l lanes/pandoc/src/CitationCslProcessor.php
No syntax errors detected in lanes/pandoc/src/CitationCslProcessor.php

php -l lanes/pandoc/tests/CitationCslProcessorTest.php
No syntax errors detected in lanes/pandoc/tests/CitationCslProcessorTest.php

php -l lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php
```

`git diff --check -- lanes/pandoc` passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `UPSTREAM_TEST_MANIFEST.json` mapped count: `1250 -> 1251`.
- `mappedBibtexCslCoreCases`: `2 -> 3`.
- `bibtexCslCoreAssertions`: `38 -> 69`.
- `lane-status.json` keeps `phpPass` at `790` because this expands assertions
  inside an existing passing focused test file rather than adding a new passing
  test file.

## Non-overlap

This does not repeat accepted BibTeX/CSL crossref, xdata, source-file policy,
entry-set/related, translation/original publication, legal/patent, date-range,
title/subtitle/title-addon, publication-detail, main-title/multi-volume,
editorial-role, secondary-editor, name-annotation/nameaddon, software/dataset,
event metadata, or event-organizer slices. It owns only BibLaTeX `ids` aliases
and canonical citation/bibliography identity.

Remaining bounded BibTeX/CSL follow-ups include BibLaTeX `shorthand`, localized
alias labels, note-style citations, citation-position disambiguation parity,
broader CSL style catalogs, and full citeproc parity.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
BibTeX parser, CSL item normalizer, CSL style renderer, Markdown reader/writer,
and WordPress block writer. Full upstream Pandoc/citeproc runner parity remains
gated on hydrating a Pandoc checkout with `cabal.project`, `pandoc.cabal`, and
`pandoc-lua-engine/pandoc-lua-engine.cabal`; this slice did not run external
converters or Haskell tooling.
