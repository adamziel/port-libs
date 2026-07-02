# Pandoc CommonMark Type-7 Container Raw Boundary

## Scope

- Added native `MarkdownReader` handling for CommonMark type-7 complete HTML
  tag raw blocks, including inside list-item continuations when the list item
  is already at a block boundary.
- Standalone complete tag lines such as `<span ...>` and void `<source ...>`
  now remain `raw_html` blocks inside list items instead of being folded into a
  paragraph after a preceding blank line.
- Existing blockquote behavior is covered by the same fixture shape because
  blockquotes are parsed by re-entering the normal Markdown reader.
- Paragraph-interruption checks continue to keep closing and custom tag lines
  as inline raw HTML without leaking markup into the paragraph text cache.

## Verification

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderCommonMarkRawContainerBoundaryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawContainerBoundaryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawContainerBoundaryTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawHtmlInterruptCompletionTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkParagraphRawBoundaryCompletionTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawClosingBoundaryCompletionTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawListItemBoundaryCompletionTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawPrecedenceCompletionTest.php`
  - 6 test files, 271 assertions, 0 failures

No Pandoc executable, Cabal/Haskell runner, office suite, TeX/PDF engine,
browser renderer, zip/unzip, Jupyter, Node tooling, external validator, online
service, live provider test, or live-service provider test was executed.

## Accounting

- CommonMark raw container mapped cases move from 12 to 16 by adding two
  type-7 complete-tag forms across blockquote and list containers.
