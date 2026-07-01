# Formats Merge Lane 2 Broader Leaf Fold

## Folded Leaves

This shard folds broader completed format leaves upward into
`integration/pandoc-formats` without touching `main`.

- `integration/pandoc-formats-media`: ancestry fold only; parent already carried
  the MediaBag resource-map, lookup-provenance, and missing-placeholder code and
  tests, so stale leaf accounting was not allowed to overwrite parent status.
- `integration/pandoc-formats-html`: ancestry fold only; no cherry-unique HTML
  changes remained against the parent.
- `integration/pandoc-formats-office`: ancestry fold only; no cherry-unique
  office/XLSX changes remained against the parent.
- `integration/pandoc-formats-registry`: folded the rich-package unsupported
  format review packet into the parent, keeping the existing wiki registry review
  packet test and adding the rich-package direct-format support tests.

Remaining leaves with cherry-unique code after this shard:
`integration/pandoc-formats-pdf-typst`,
`integration/pandoc-formats-markdown-reader`,
`integration/pandoc-formats-markdown-writer`, and
`integration/pandoc-formats-small`.

## Validation

Run after resolving the registry merge:

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`

Focused result: `PandocFormatRegistryTest.php` passed with 308 assertions and
0 failures.

No Pandoc binary, office suite, notebook runtime, browser, TeX/Typst engine, or
external converter was invoked.
