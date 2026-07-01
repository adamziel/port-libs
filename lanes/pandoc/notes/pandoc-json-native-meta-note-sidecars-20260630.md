# Pandoc JSON/native metadata and note sidecars

Hook: `plib-7ylsj`, Pandoc JSON/native AST constructor completeness.

This slice keeps `NativeWriter` on the JSON/native handoff path when a document
contains direct Pandoc metadata constructors or valid note-label sidecars:

- Direct `MetaString`, `MetaBool`, `MetaInlines`, `MetaBlocks`, `MetaList`,
  and `MetaMap` payloads now count as JSON-native provenance for writer-mode
  selection.
- Valid `note` labels now count as JSON-only sidecars so Markdown labelled
  notes preserve `noteLabel` through NativeWriter JSON output.
- The actual JSON serialization remains delegated to `PandocJsonWriter`; the
  change only prevents lossy fallback to textual Native where these sidecars
  cannot be represented.

Validation:

- `php -l lanes/pandoc/src/NativeWriter.php`
- Selected `PandocJsonNativeAstTest.php` closures for direct pre-tagged
  metadata payloads and Markdown note labels: 27 assertions, 0 failures.
- Full `PandocJsonNativeAstTest.php` now has 9 unrelated baseline failures,
  down from 11 before this slice.

No Pandoc binary, TeX, browser, office suite, ZIP tooling, Node tooling, or
external validator was invoked.
