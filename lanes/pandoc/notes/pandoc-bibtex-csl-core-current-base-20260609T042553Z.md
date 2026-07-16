# Pandoc BibTeX/CSL Core Current-Base Slice

Session: `port-dev-pandoc-bibtex-csl-20260609T042553Z`
Micro-slice: `pandoc-bibtex-csl-core-current-base-20260609T042553Z`
Base accepted HEAD: `11fc57ec36d6cc974a7a65f55020cfb9f1af6d59`

## Behavior

Mapped BibLaTeX `@letter` entries to CSL `personal_communication` in `BibtexCslParser` while preserving raw BibTeX provenance as `letter`.

The focused test covers:

- parsed `.bib` item normalization to `personal_communication`;
- raw `rawBibtex.type` provenance as `letter`;
- recipient and note metadata handoff;
- CSL `<if type="personal_communication">` citation and bibliography rendering;
- WordPress block output after Markdown citation replacement and appended bibliography.

## Evidence

Red-first probe before implementation:

```text
php -r 'require "tools/bootstrap.php"; $items = \PortLibs\Pandoc\CitationCslProcessor::bibtexItems("@letter{source-letter, author={Smith, Ada}, title={Legacy Source Letter}, date={2026}}"); echo ($items[0]["type"] ?? "missing") . PHP_EOL;'
letter
```

Baseline focused test before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 3655 assertions, 0 failures
```

Final focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 3673 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-bibtex-csl-letter-type-handoff.php --self-test
wordpress-bibtex-csl-letter-type-handoff self-test passed
```

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP BibTeX parser, CSL item normalization, CSL choose/type rendering, Markdown reader, WordPress block writer, and focused PHP test runner.

No Pandoc, BibTeX, Biber, citeproc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external converter, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This does not repeat the accepted `@manual` to `book` or `@booklet` to `pamphlet` handoff. It covers only `@letter` source packets that previously fell through as normalized CSL type `letter` and therefore could not match `personal_communication` CSL type conditionals.
