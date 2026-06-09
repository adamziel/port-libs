# BibTeX/CSL Patent Type Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260609T061346Z`
Base accepted HEAD: `ad25c5c67f0859a34d555620436625e00d668451`

## Behavior

This slice ports one bounded BibLaTeX patent type handoff cluster:

- `@patent` entries keep the raw BibLaTeX `type` string in CSL `genre`.
- The same raw string is now exposed as `patent-type`.
- Known BibLaTeX patent type strings such as `patreqeu`, `patentus`, and `patrequs` map to review labels such as `European patent request`, `U.S. patent`, and `U.S. patent request`.
- Unknown/custom patent type strings fall back to a readable first-letter uppercase label, for example `utility model` -> `Utility model`.
- `CitationCslProcessor` renders `patent-type` and `patent-type-label` in custom CSL styles and uses the review label for default patent bibliography entries.

Source truth: the bounded type strings are taken from the BibLaTeX manual's patent type vocabulary (`patent*` and `patreq*` country/request variants), available from CTAN at `https://mirrors.ctan.org/macros/latex/contrib/biblatex/doc/biblatex.pdf`. This ports the local format contract only; it does not invoke BibTeX, Biber, Pandoc, or citation-manager runners.

## Evidence

No Pandoc lane rework note existed for this worktree before the slice.

Red-first focused check before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
FAIL maps bounded biblatex patent type strings into csl review labels
1 test files, 3811 assertions, 1 failures
```

Focused verification after implementation:

```text
php -l lanes/pandoc/src/BibtexCslParser.php
No syntax errors detected in lanes/pandoc/src/BibtexCslParser.php

php -l lanes/pandoc/src/CitationCslProcessor.php
No syntax errors detected in lanes/pandoc/src/CitationCslProcessor.php

php -l lanes/pandoc/tests/CitationCslProcessorTest.php
No syntax errors detected in lanes/pandoc/tests/CitationCslProcessorTest.php

php -l lanes/pandoc/examples/wordpress-bibtex-csl-patent-type-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-bibtex-csl-patent-type-handoff.php

php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 3837 assertions, 0 failures

php lanes/pandoc/examples/wordpress-bibtex-csl-patent-type-handoff.php --self-test
wordpress-bibtex-csl-patent-type-handoff self-test passed
```

```text
git diff --check -- lanes/pandoc
passed with no output
```

Root harness: not run - isolated micro-slice.

## Status Delta

- Added 1 focused PHP PASS case to the Pandoc lane.
- Focused assertion count for `CitationCslProcessorTest.php` is now 3837.
- Updated `lanes/pandoc/lane-status.json` from `phpPass` 2428 to 2429.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `BibtexCslParser`, `CitationCslProcessor`, CSL variable renderer, Markdown reader, and WordPress block writer.

No Pandoc, Cabal/Haskell runner, BibTeX, Biber, Word, LibreOffice, zip/unzip, external template engine, external converter, TeX/PDF engine, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted generic legal metadata, authority/jurisdiction/status mapping, article-number, source-locator, thesis-type, media-type, direct creator-role, or date addendum CSL handoffs. It only adds bounded patent type strings and their CSL/WordPress review labels.
