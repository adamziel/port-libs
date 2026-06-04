# Pandoc Citation CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260604T200125Z`

Accepted base: `d869572f8668c6737c83d32f1a657cfaca6c635c`

## Behavior

- Extended the bounded native `CitationCslProcessor` CSL JSON handoff with
  normalized `issued` and `accessed` date variables.
- Supports year/month/day `date-parts`, numeric-string date parts, literal
  issued dates such as `forthcoming`, and focused malformed date diagnostics.
- Preserves CSL name metadata for `non-dropping-particle`, `dropping-particle`,
  `suffix`, `comma-suffix`, `static-ordering`, and `parse-names`.
- Author/editor fallback labels now keep non-dropping particles for citation
  labels and bibliography terms, while bibliography entries keep suffix comma
  policy and avoid doubled periods for suffixes such as `Jr.`.
- Bibliography output includes an `Accessed YYYY-MM-DD.` audit segment when a
  cited URL-backed item carries an `accessed` date.
- Updated the WordPress citation handoff example with a `--self-test` path that
  proves source-access dates and name particles survive WordPress block output.

## Source Truth

- The upstream runner is still unavailable in this isolated worktree; there is
  no hydrated Pandoc or citeproc checkout under `.upstream-cache`.
- This slice uses the accepted Pandoc lane manifest/manual audit as source
  truth for the existing citation/CSL support row and stays within CSL JSON
  item fields already used by the native YAML/reference metadata handoff.
- It does not attempt CSL style XML, locale terms, disambiguation, note-style
  citeproc output, BibTeX/BibLaTeX parsing, online lookups, or arbitrary style
  catalog parity.

## Evidence

- Before this slice, `CitationCslProcessorTest.php` had 4 focused cases and 85
  assertions. After this slice it has 5 focused cases and 107 assertions.
- `php -l lanes/pandoc/src/CitationCslProcessor.php`:
  no syntax errors.
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`:
  no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-citation-csl-handoff.php`:
  no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`:
  1 selected test file, 107 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`:
  11 selected test files, 3,436 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-citation-csl-handoff.php --self-test`:
  `wordpress-citation-csl-handoff self-test passed`.
- `git diff --check -- lanes/pandoc`:
  no whitespace errors.

## Non-Overlap

This does not repeat accepted CSL JSON item basics, simple author-date
citations, bracketed citation cluster parsing, missing citation preservation,
table geometry rowspans, DOCX field-code hyperlinks, ZIP/OPC package
preflights, YAML metadata parsing, doctemplate pipes/partials, ODT/DOCX
package parsing, math/TeX handoff, legacy DOC/CFB extraction, or charset
helpers. It maps only bounded CSL date/name metadata and accessed-date
bibliography output.

## Dependency Closure

No new support component is needed. This reuses the existing native
`CitationCslProcessor`, Markdown reader/writer, WordPress block writer, and
CSL JSON metadata handoff. Remaining citation closure is still CSL style
XML/locale term processing, locale-dependent date/name rendering beyond this
bounded metadata shape, BibTeX/BibLaTeX parsing, citation-position
disambiguation, note-style output, and full upstream runner hydration.

Root harness: not run - isolated micro-slice.
