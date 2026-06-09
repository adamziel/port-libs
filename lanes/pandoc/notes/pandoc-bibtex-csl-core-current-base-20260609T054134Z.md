# pandoc-bibtex-csl-core-current-base-20260609T054134Z

Base accepted HEAD: `50ff75128f57e5d1c91c6f6643df81bffbb2e704`

## Scope

Mapped one bounded BibTeX/CSL handoff gap: LaTeX text-symbol macros in `.bib`
field text now decode before CSL citation, bibliography, and WordPress block
rendering while raw BibTeX field provenance stays available for review.

Handled macros:

- `\textbackslash`
- `\textless`
- `\textgreater`
- `\textcopyright`
- `\textregistered`
- `\texttrademark`
- `\textnumero`
- `\textdegree`
- `\textbar`
- `\textasciicircum`
- `\textasciitilde`

## Red Probe

Before the patch, the native BibTeX handoff leaked text-symbol macros into CSL
item values:

```sh
php -r 'require "tools/bootstrap.php"; $bib="@book{symbols,title={Path \\textbackslash{} assets \\textless{}review\\textgreater{}},publisher={Audit \\textcopyright{} Team},note={packet\\textasciitilde{}draft \\textregistered{} \\texttrademark{}}}\n"; $items=\PortLibs\Pandoc\CitationCslProcessor::bibtexItems($bib); var_export([$items[0]["title"] ?? null, $items[0]["publisher"] ?? null, $items[0]["note"] ?? null]); echo "\n";'
```

Output before:

```php
array (
  0 => 'Path \\textbackslash assets \\textlessreview\\textgreater',
  1 => 'Audit \\textcopyright Team',
  2 => 'packet\\textasciitildedraft \\textregistered \\texttrademark',
)
```

After the patch, the same probe decodes the rendered item text while preserving
raw BibTeX fields for audit:

```php
array (
  0 => 'Path \\ assets <review>',
  1 => 'Audit © Team',
  2 => 'packet~draft ® ™',
)
```

## Focused Evidence

Baseline before this slice:

```sh
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
# 1 test files, 3779 assertions, 0 failures
```

Final focused test:

```sh
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
# 1 test files, 3791 assertions, 0 failures
```

Example smoke:

```sh
php lanes/pandoc/examples/wordpress-bibtex-csl-text-symbol-handoff.php --self-test
# wordpress-bibtex-csl-text-symbol-handoff self-test passed
```

Delta:

- `phpPass`: `2390 -> 2391`
- `benchmarkDenominator.mapped`: `2783 -> 2784`
- `inventory.mappedBibtexCslCoreCases`: `7 -> 8`
- `inventory.bibtexCslCoreAssertions`: `121 -> 133`
- Focused assertions: `+12`

## Dependency Closure

No new support component is needed. This reuses native PHP
`BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, and
`WordPressBlockWriter`. Full upstream Pandoc/citeproc runner parity remains a
separate upstream-runner dependency task requiring a hydrated Pandoc checkout
and Haskell test executables.

## Non-Overlap

This slice does not repeat accepted BibTeX/CSL type routing, xdata/crossref,
entryset/related/xref, date parsing, label-prefix/sort-initial provenance, or
the earlier bounded punctuation macro decoding. It only covers the text-symbol
macro handoff listed above.

Root harness was not run for this isolated micro-slice.
