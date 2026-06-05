# Pandoc BibTeX/CSL Current-Base Shorthand Labels

Slice: `pandoc-bibtex-csl-core-current-base-20260605T093431Z`
Base: `1fdb5223a4b72ef1c1155f017cdae1bee3efbbfd`
Date: 2026-06-05 UTC

## Behavior

Added bounded native BibLaTeX shorthand and short-creator handoff to the PHP CSL support path:

- `shorthand` and fallback `label` map to CSL `citation-label`.
- `shorthandintro` maps to `shorthand-intro`.
- `shortauthor` and `shorteditor` are parsed as CSL-shaped name lists and normalized as `shortAuthors` and `shortEditors`.
- Default citation rendering uses a standalone shorthand/citation label when present, uses short author/editor labels for author-date terms, and keeps full author/editor names in bibliography entries.
- Bounded CSL layout rendering can read `citation-label`, `shorthand`, `shorthand-intro`, `short-author`, and `short-editor` variables.

Source-truth checks:

- CTAN BibLaTeX manual documents `shortauthor`, `shorteditor`, `shorthand`, and `shorthandintro` as label/short citation fields: https://ctan.math.washington.edu/tex-archive/macros/latex/contrib/biblatex/doc/biblatex.pdf
- CSL-JSON schema includes the `citation-label` item property: https://raw.githubusercontent.com/citation-style-language/schema/master/schemas/input/csl-data.json

## Red-First Evidence

Before implementation, the new focused case failed on missing `citation-label` metadata:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
FAIL maps bounded biblatex shorthand labels and short creator lists
1 test files, 927 assertions, 1 failures
```

The accepted focused file had 925 assertions before this case; the final focused file has 954 assertions, so this slice adds 29 focused assertions and one mapped BibTeX/CSL behavior case.

## Verification

```text
php -l lanes/pandoc/src/BibtexCslParser.php
No syntax errors detected in lanes/pandoc/src/BibtexCslParser.php

php -l lanes/pandoc/src/CitationCslProcessor.php
No syntax errors detected in lanes/pandoc/src/CitationCslProcessor.php

php -l lanes/pandoc/tests/CitationCslProcessorTest.php
No syntax errors detected in lanes/pandoc/tests/CitationCslProcessorTest.php

php -l lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php

php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 954 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests
20 test files, 9796 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS'
794

php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test
wordpress-bibtex-csl-handoff self-test passed
```

Post-status verification:

```text
php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'
pandoc json ok

git diff --check -- lanes/pandoc
passed
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1267 -> 1268`.
- `mappedBibtexCslCoreCases`: `2 -> 3`.
- `bibtexCslCoreAssertions`: `38 -> 67`.
- `lane-status.json` `phpPass`: `807 -> 808` for the new mapped focused case.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP `BibtexCslParser`, `CitationCslProcessor`, Markdown reader, and WordPress block writer. No Pandoc, citeproc, BibTeX, Biber, Cabal, Haskell runner, Word, LibreOffice, online service, or external bibliography manager was executed.

## Non-Overlap

This slice does not touch upstream-runner dependency audit evidence, date-part rendering, charset/Unicode width, doctemplate pipes/partials, legacy DOC/CFB metadata, YAML explicit sequence keys, PDF engine handoff, DOCX/OpenXML, math/TeX, EPUB/ODT package handling, archive compression, XML/HTML5 DOM, or syntax highlighting. Follow-up BibTeX/CSL work should stay separate: style-specific first-citation shorthand-intro policies, broader BibLaTeX labelalpha generation, citation abbreviation files, and full upstream citeproc/Pandoc parity remain out of scope.
