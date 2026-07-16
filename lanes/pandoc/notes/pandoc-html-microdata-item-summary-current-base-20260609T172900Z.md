# Pandoc HTML Microdata Item Summary Slice

Micro-slice: `pandoc-html-microdata-item-summary-current-base-20260609T172900Z`

## Scope

This slice extends the native PHP HTML fragment reader handoff for microdata
items. `Html5DomFragment` now summarizes each sanitized `itemscope` element with
inert review attributes:

- `data-pandoc-microdata-properties` for unique direct item property tokens
- `data-pandoc-microdata-property-count` for direct property assignments
- `data-pandoc-microdata-value-count` for scalar property assignments
- `data-pandoc-microdata-nested-item-count` when a direct property is another item

The walker uses normalized child nodes, stops at nested scoped items, and keeps
the existing per-property `data-pandoc-microdata-value` metadata intact.

## Verification

Syntax checks:

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`

Focused test:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: 1 file, 2477 assertions, 0 failures

Full Pandoc PHP gate:

- `php tools/run-tests.php lanes/pandoc/tests`
- Result: 39 files, 56526 assertions, 0 failures

## Non-Overlap

This does not implement document-wide `itemref` graph traversal, JSON-LD export,
RDFa graph construction, schema vocabulary validation, browser DOM execution, or
external Pandoc/browser/validator calls. It is only scoped item property summary
metadata for already-normalized native HTML reader fragments.
