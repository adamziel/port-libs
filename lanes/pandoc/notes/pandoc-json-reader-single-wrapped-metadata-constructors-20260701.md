# Pandoc JSON single-wrapped metadata constructors

Slice: `plib-arz4a`

This bounded JSON/native AST constructor-completeness slice extends the strict
`JsonReader` compatibility path for single-wrapped Pandoc metadata
constructors:

- `MetaString` accepts `[string]`.
- `MetaBool` accepts `[bool]` and rejects non-boolean payloads instead of
  truthy-casting arrays.
- `MetaList` accepts `[[...]]`.
- `MetaMap` accepts `[{"key": ...}]`.

`JsonWriter` continues to emit canonical current Pandoc JSON metadata
constructors, so wrapped input is normalized on writeback.

Accounting:

- `mappedPandocJsonSingleWrappedMetadataConstructorCases`: `1`
- `pandocJsonSingleWrappedMetadataConstructorAssertions`: `14`

Validation:

- `php -l lanes/pandoc/src/JsonReader.php`
- `php -l lanes/pandoc/tests/JsonReaderSingleWrappedConstructorCompatibilityTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/JsonReaderSingleWrappedConstructorCompatibilityTest.php`
  - Result: `1 test files, 53 assertions, 0 failures`

No external Pandoc executable, JSON filter, office suite, TeX/browser engine,
zip/unzip, Jupyter, Node tooling, validator, fetcher, or live service was
invoked.
