# Pandoc JSON/native textual metadata constructors

Slice: `plib-arz4a`

`NativeReader` now preserves textual Native `Meta*` constructor payloads from
`Pandoc Meta {unMeta = fromList ...}` input. Parsed documents carry exact
`metaNativeValues` plus `metaConstructorProvenance` JSON-pointer sidecars for
top-level and nested `MetaMap`/`MetaList` values, while retaining the existing
`titleInlines`, `authorInlines`, `authors`, and `dateInlines` helper attrs.

`PandocJsonWriter` already consults those sidecars, so textual Native metadata
fields such as `MetaInlines`, `MetaMap`, `MetaList`, and `MetaBlocks` can be
re-emitted unchanged through JSON/native handoff instead of collapsing to
generic strings.

Post-rebase validation:

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/NativeReaderTextualMetadataConstructorProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTextualMetadataConstructorProvenanceTest.php`: 1 file, 23 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTextualMetadataConstructorProvenanceTest.php lanes/pandoc/tests/NativeReaderTextConstructorProvenanceTest.php lanes/pandoc/tests/NativeReaderTextTableHelperProvenanceTest.php`: 3 files, 245 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php`: baseline-red outside this slice with 1 file, 317 assertions, 5 unrelated failures.

No external Pandoc, office suite, TeX/browser engine, Typst, Jupyter, Node,
zip/unzip, external validators, or live services were invoked.
