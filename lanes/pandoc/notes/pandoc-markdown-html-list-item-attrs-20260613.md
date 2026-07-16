# Pandoc Markdown HTML List Item Attributes - 2026-06-13

Scope: bounded list-item-id parity for MarkdownReader HTML import to WordPress handoff.

Implemented:
- `MarkdownReader::parseHtmlListElement()` now carries `htmlElementPandocAttrs()` from each HTML `li` into the shared `list_item` node.
- Safe HTML item attributes such as `id`, `class`, `data-*`, and `title` now survive the Markdown/HTML reader path into `WordPressBlockWriter` list item output.
- Unsafe item attributes remain filtered by the existing WordPress writer policy; the focused test covers event handler and style filtering.

Evidence:
- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`
- Focused result after final rebase onto current main `6d30f5c54b`: 1 file, 6716 assertions, 0 failures.
- Full result after final rebase onto current main `6d30f5c54b`: 45 files, 74759 assertions, 0 failures.

Counters:
- `phpPass`: 3328 -> 3329
- `mapped`: 3287 -> 3288
- `mappedMarkdownHtmlListItemAttributeCases`: 1
- `markdownHtmlListItemAttributeAssertions`: 12

Policy:
- No Pandoc binary, Cabal/Haskell runner, browser renderer, Node tooling, TeX/PDF engine, office suite, online service, live provider test, or external validator was invoked.

Remaining list gaps:
- Ordered-list start/style propagation across more constructors.
- Broader nested continuation parity.
- List item id propagation beyond this bounded HTML reader to WordPress path, especially JSON/native and LaTeX-facing surfaces where canonical Pandoc list-item constructors do not carry item attributes.
