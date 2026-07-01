# Pandoc JSON/native textual Attr tuple sidecars

Slice: `plib-rsu3y`

Textual Native `Attr` tuples were previously normalized into shared `id`, `classes`, and `attributes` fields, but their source tuple shape was dropped. That meant standalone `NativeWriter` could not treat those attributes as JSON/native provenance unless another sidecar was present.

This slice keeps non-empty textual `Attr` tuples as `attrConstructor`/`attrNative` sidecars while preserving the existing normalized public attributes. The JSON/native writer can now reuse unchanged source key/value tuple lists and regenerate edited attr ids without carrying stale tuple payloads.

Validation:

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/NativeReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php lanes/pandoc/tests/PandocNativeWriterJsonProvenanceTest.php` passed with 2 files, 500 assertions, and 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` passed with 2 files, 6,768 assertions, and 0 failures.

No upstream Pandoc, converter, TeX, browser, office, or validator shell-outs were used.
