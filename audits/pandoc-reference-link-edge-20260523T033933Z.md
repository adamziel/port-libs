# Pandoc Reference Link Edge Evidence - 2026-05-23T033933Z

Session: `port-pandoc`

## Scope

- Lane: Pandoc native PHP.
- Implemented the bounded post-grid `test/markdown-reader-more.txt/native`
  reference-link edge slice after the entity/link-title and parenthesized URL
  work.
- Did not edit non-Pandoc lanes, `progress.md`, `porting.html`, or
  `porting-summary.json`.
- Did not invoke upstream `pandoc`, Haskell test executables, or any upstream
  binary as implementation behavior.

## Upstream Evidence

- `test/markdown-reader-more.txt` lines 337-358:
  backslash-containing link label, unresolved reference-link fallback, shortcut
  reference followed by `[@mapreduce]`, and empty reference definition.
- `test/markdown-reader-more.native` lines 1549-1649:
  link label containing `Str "*"` plus `RawInline "\\a"`, bracketed fallback
  text with emphasized contents, `Link` followed by `Cite`, and an empty-href
  reference link after the intervening `bar` paragraph.

## Implementation

- `MarkdownReader` now keeps blank-line-separated empty reference definitions
  empty instead of consuming the following paragraph as the destination.
- Added a bounded citation inline node for `[@id]` markers so citation-adjacent
  shortcut links remain links and WordPress output keeps the marker visible.
- Added bare one-letter raw-TeX command parsing for link labels like `\a`.
- `WordPressBlockWriter` renders citation markers as escaped literal inline
  text and allows them inside inline contexts.
- The WordPress Markdown fixture now exercises backslash/raw-TeX link labels,
  unresolved reference-looking fallback text, citation-adjacent links, and empty
  reference placeholders.

## Verification

```text
Focused slice:
3 tests, 66 assertions, 0 failures
```

```text
Full Pandoc MarkdownReaderTest.php:
159 tests, 1,610 assertions, 0 failures
```

```text
PHP lint:
lanes/pandoc/src/MarkdownReader.php: no syntax errors
lanes/pandoc/src/WordPressBlockWriter.php: no syntax errors
lanes/pandoc/tests/MarkdownReaderTest.php: no syntax errors
```

```text
php lanes/pandoc/examples/wordpress-import-markdown.php
549 output lines; output includes the backslash label, fallback marker,
citation-adjacent link, and empty-reference placeholder cases.
```

```text
Root php tools/run-tests.php:
direct run: 180 test files, 16,700 assertions, 129 failures
compact failure-file rerun: 180 test files, 16,745 assertions, 130 failures
```

Root failures were outside Pandoc in the compact rerun:

- 66 `lanes/lightningcss/tests/CssMinifierTest.php`
- 37 `lanes/lightningcss/tests/TransitionPrefixerTest.php`
- 19 `lanes/lightningcss/tests/NestingTransformerTest.php`
- 6 `lanes/lightningcss/tests/CustomMediaTransformerTest.php`
- 1 `lanes/lightningcss/tests/MediaQueryParserTest.php`
- 1 `lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`

The visible LightningCSS failure cause was:
`Call to undefined method PortLibs\LightningCSS\CssMinifier::composeFontDeclarationBlocks()`.

## Residual Risk

- Full upstream Pandoc Haskell runner remains unexecuted for the existing lane
  reason: it requires hydrating/building upstream Tasty executables and the
  Pandoc dependency graph from the blob-filtered cache.
- Root integration remains blocked outside Pandoc by LightningCSS and markerPDF
  failures.
