# Markdown reader braced citations

Work item: `plib-vbm3m`

## Summary

`MarkdownReader` now parses bare braced citation ids such as `@{source key}`
and unescapes backslash-escaped characters inside braced citation ids for both
bare and bracketed citation forms. This makes the existing upstream-mapped
braced citation surge pass for author-in-text, normal, suppress-author, grouped,
locator, and writer round-trip forms.

## Non-overlap

This slice only touches Markdown citation id parsing. It does not change
citation rendering, bibliography processing, citeproc metadata, footnotes,
reference links, raw HTML, tables, or package readers.

## Validation

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderCitationSurgeTest.php`

No Pandoc binary, TeX/browser engine, Node tooling, online service, or external
validator was invoked.
