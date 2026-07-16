# JSON Native Metadata Constructor Provenance

Date: 2026-06-12
Bead: plib-f1aol
Base: origin/main 29dafdc63e2b4d4f552cea8e000d6458be97b0dd

This slice adds metadata constructor provenance to native Pandoc JSON/native AST ingestion. `PandocJsonReader` still exposes normalized `meta` helpers, and `NativeReader` still preserves tagged native metadata values; both readers now also expose `metaConstructorProvenance` on the document node.

The provenance map is keyed by escaped metadata paths and records the Pandoc metadata constructor plus native payload for `MetaString`, `MetaBool`, `MetaInlines`, `MetaBlocks`, `MetaList`, and `MetaMap`. This lets review handoff inspect constructor completeness without changing writer output or normalized metadata semantics.

Verification:
- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`: 1 test file, 1934 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 73516 assertions, 0 failures.

Accounting:
- Adds 1 mapped JSON/native metadata constructor provenance case.
- Adds 39 focused assertions.
- Moves Pandoc lane `phpPass` from 3277 to 3278.
- Moves mapped denominator from 3247 to 3248.
