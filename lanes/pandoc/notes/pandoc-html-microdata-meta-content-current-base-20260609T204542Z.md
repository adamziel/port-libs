# Pandoc HTML Microdata Meta Content Slice

Micro-slice: `pandoc-html-microdata-meta-content-current-base-20260609T204542Z`

## Scope

This slice extends the native PHP HTML fragment reader handoff for microdata
metadata elements.

`Html5DomFragment` now preserves `<meta itemprop="..." content="...">` as inert
reviewer metadata instead of dropping it with the stripped source `meta` tag.
Standalone microdata meta nodes still become reviewer spans, and passive meta
conversions such as Open Graph image links and named description spans now keep
their normal metadata output while also carrying the microdata review attrs:

- `data-pandoc-microdata-property` preserves sanitized `itemprop` tokens.
- `data-pandoc-microdata-value` preserves sanitized `content` values.
- `data-pandoc-microdata-source="meta"` records the source element type.
- enclosing `itemscope` summaries count meta-derived properties and scalar
  values through the existing microdata summary walker.

The implementation reuses the existing semantic token sanitizer and only attaches
microdata values to already sanitized meta review output. Source `meta`,
`itemprop`, and `content` attributes remain absent from WordPress blocks.

## Verification

Syntax checks:

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`

Focused test:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: 1 file, 2575 assertions, 0 failures

Full Pandoc PHP gate:

- `php tools/run-tests.php lanes/pandoc/tests`
- Result: 42 files, 58641 assertions, 0 failures

## Non-Overlap

This does not implement JSON-LD export, RDFa graph construction, Schema.org
validation, document-wide microdata graph traversal, browser DOM execution,
Pandoc execution, or external validators. It only preserves bounded
`meta itemprop/content` review metadata in the existing native HTML fragment
handoff.
