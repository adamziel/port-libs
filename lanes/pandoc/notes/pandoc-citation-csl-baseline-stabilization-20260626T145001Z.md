# Citation CSL baseline stabilization - 2026-06-26

Scope: `plib-pt7dg.1`, focused on `CitationCslProcessorTest.php` baseline failures in citation/CSL reader-processing and CSL Markdown handoff.

## Clean origin/main baseline

Before edits, the branch and `origin/main` were identical:

- `git rev-parse HEAD origin/main`
- Both resolved to `5649201c688e69d166178486d8c1b2726da76378`.

Focused clean-main/branch baseline:

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/src/CslStyle.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- Result: `1 test files, 5666 assertions, 61 failures`.

Primary baseline bucket: Markdown bracketed citation clusters with prefixes, locators, suppression, URL keys, and adjacent citation groups were not being parsed into CSL citation/group nodes, leaving raw citation syntax in many CSL handoff tests.

## Branch stabilization

Implemented:

- Markdown reader support for complex bracketed citation clusters, including per-entry prefixes, suppressed author markers, locator suffixes, forced locator braces, and braced URL-style citation keys.
- Missing-item citation rendering now preserves prefix/suffix text inside rendered CSL clusters.
- Markdown writer now honors processed `citation_group` rendered text and keeps CSL-generated bibliography/shorthand definition lists compact.
- Bracketed citation parsing defers to bracketed spans when an attribute block follows the bracket.

Focused branch result:

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/src/MarkdownWriter.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- Result: `1 test files, 5947 assertions, 19 failures`.

Passing recovery examples now include:

- `parses pandoc bracketed citation clusters with prefixes locators suppression and url keys`
- `appends deterministic csl bibliography blocks for markdown and wordpress output`
- CSL date/date-part/season Markdown handoff tests that previously failed because citations remained raw.
- BibTeX/BibLaTeX crossref and shorthand Markdown bibliography checks previously blocked by raw citations or loose CSL definition-list spacing.

## Remaining classified failures

The focused file remains red in 19 known buckets:

- WordPress bibliography options are not emitted on CSL `<dl>` output.
- Et-al-use-last uses ASCII `...` in Markdown output where the test expects Unicode ellipsis.
- Quote rendering uses straight quotes where locale quote tests expect curly quotes and punctuation-in-quote behavior.
- Pandoc JSON suffix-locator diagnostics collapse spaces in WordPress text output.
- Citation-number bibliography tests expect structured CSL display-part HTML; current output is flattened text.
- Near-note and first-reference-note tests expect original note labels such as `fn-a`; current WordPress footnote output renumbers as `fn-1`.
- CSL bibliography display-part/formatting tests expect nested `<div class="csl-entry">` structures and inline formatted citation parts; current handoff flattens those parts.

These are distinct from the raw bracketed-citation parsing baseline bucket addressed here.
