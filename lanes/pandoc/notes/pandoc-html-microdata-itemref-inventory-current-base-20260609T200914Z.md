# Pandoc HTML Microdata Itemref Inventory Slice

Micro-slice: `pandoc-html-microdata-itemref-inventory-current-base-20260609T200914Z`

## Scope

This slice extends the native PHP HTML fragment reader handoff for scoped
microdata items that carry `itemref`.

`Html5DomFragment` now preserves bounded, inert reviewer metadata for the
declared itemref inventory:

- `data-pandoc-microdata-ref-count`
- `data-pandoc-microdata-ref-resolved` and resolved count
- `data-pandoc-microdata-ref-missing` and missing count

The implementation checks whether referenced IDs exist in the parsed source
document, but it does not traverse or merge a document-wide microdata graph.
Source `itemref` remains stripped from WordPress output and the imported HTML
keeps only sanitizer-owned `data-pandoc-*` metadata.

## Verification

Syntax checks:

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`

Focused checks:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: 1 file, 2493 assertions, 0 failures
- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
- Result: `html5 dom fragment handoff self-test ok`

Full Pandoc PHP gate:

- `php tools/run-tests.php lanes/pandoc/tests`
- Result after rebasing on `origin/main`: 42 files, 57144 assertions, 0 failures
- Rebased lane counters: `phpPass` 2835 -> 2836 and `suiteProgress` 738 -> 739

## Non-Overlap

This does not implement JSON-LD export, RDFa graph construction, Schema.org
validation, cross-item property expansion, browser DOM execution, or Pandoc /
browser / validator calls. It is only itemref presence and missing-reference
metadata for already-normalized native HTML reader fragments.
