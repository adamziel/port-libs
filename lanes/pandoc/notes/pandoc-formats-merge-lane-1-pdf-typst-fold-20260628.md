# Formats merge lane 1: PDF/Typst fold

Issue: plib-iufsz
Date: 2026-06-28
Target: integration/pandoc-formats
Leaf checked: integration/pandoc-formats-pdf-typst

The PDF/Typst leaf branch had 12 commits not reachable by ancestry from
integration/pandoc-formats, but the corresponding tree changes were already
present on the parent under rebased/cherry-picked commits. A two-dot diff showed
no remaining PDF/Typst file delta between the parent and the leaf; the only
current content delta was the parent's unrelated WordPressBlockWriter change.

This merge records the leaf ancestry in the formats parent without changing the
PDF/Typst implementation surface.

Validation:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`

Result: 1 focused test file, 3,638 assertions, 0 failures.
