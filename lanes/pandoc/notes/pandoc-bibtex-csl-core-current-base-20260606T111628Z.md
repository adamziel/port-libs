# Pandoc BibTeX/CSL Text Macro Wrapper Slice

Micro-slice: `pandoc-bibtex-csl-core-current-base-20260606T111628Z`
Base: `f3e6ef9e9a7803edbdb9db6d76cbe13ebbfcd147`

## Scope

Added bounded native PHP BibTeX/BibLaTeX text-wrapper cleanup for CSL handoff.
`BibtexCslParser` now strips an allowlist of common single-argument LaTeX text
formatting wrappers while preserving visible text:

- `emph`, `enquote`, `textbf`, `textit`, `textnormal`, `textrm`, `textsc`,
  `textsf`, `textsl`, `textsubscript`, `textsuperscript`, `texttt`
- `mkbibbold`, `mkbibbrackets`, `mkbibemph`, `mkbibitalic`, `mkbibparens`,
  `mkbibquote`

The cleanup is intentionally bounded. It prevents reviewer-facing title,
container-title, publisher, and note fields from leaking wrapper command names
such as `\textscPacket` or `\mkbibemphReview`, but it does not attempt full TeX
macro expansion, math-mode parsing, or citeproc parity.

## Evidence

Baseline focused citation coverage before the new case:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1647 assertions, 0 failures
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1663 assertions, 0 failures
```

WordPress smokes:

```text
php lanes/pandoc/examples/wordpress-bibtex-csl-text-macro-handoff.php --self-test
wordpress-bibtex-csl-text-macro-handoff self-test passed

php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test
wordpress-bibtex-csl-handoff self-test passed
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1310 -> 1311`.
- Manifest mapped denominator: `1724 -> 1725`.
- Focused citation coverage: `+1` PASS case and `+16` assertions.

## Non-Overlap

This does not repeat accepted BibTeX/CSL source-file policy, xdata/crossref
inheritance, entry sets, related entries, original/translation metadata, legal
fields, date ranges, title/subtitle/addon fields, publication/eprint metadata,
journal abbreviations, page-first metadata, main-title/multivolume metadata,
note/addendum/howpublished metadata, entry-subtype, editorial roles, name
annotations, shorthand aliases, software/dataset metadata, event metadata,
event organizers, event-place lists, call numbers, pagination, issue titles,
article numbers, reviewed-work metadata, reprint titles, or accent decoding.
It covers only visible text preservation for bounded LaTeX wrapper macros in
BibTeX/BibLaTeX fields.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, and
`WordPressBlockWriter` paths. No Pandoc, citeproc, BibTeX, Biber, Cabal
solver/build/test command, Haskell runner, external bibliography manager,
online sanitizer, online service, live provider test, or live-service provider
test was executed.

Full upstream Pandoc/citeproc runner parity remains gated on hydrating the
pinned Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with
Cabal project/package files and runner dependency closure.
