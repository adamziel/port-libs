# Pandoc JSON native AST core

Slice: `pandoc-json-native-ast-core-current-base-20260609T113745Z`

Implemented a bounded native PHP Pandoc JSON adapter for the current shared
`AstNode` model. `PandocJsonReader` now accepts the Pandoc JSON filter exchange
shape with `pandoc-api-version`, typed `meta`, and tagged `blocks`; it maps core
block and inline constructors into the lane AST without invoking Pandoc or any
external converter. `PandocJsonWriter` emits the same tagged-object shape for
documents, metadata, attributes, block lists, inline marks, links/images, notes,
raw markdown/HTML/TeX, list attributes, line blocks, divs, and definition lists.

Focused behavior added:

- Top-level JSON filter packet shape and API-version preservation.
- `MetaString`, `MetaBool`, `MetaInlines`, `MetaBlocks`, `MetaList`, and
  `MetaMap` conversion.
- Core block constructors: `Plain`, `Para`, `Header`, `CodeBlock`, `RawBlock`,
  `BlockQuote`, `OrderedList`, `BulletList`, `DefinitionList`, `LineBlock`,
  `HorizontalRule`, and `Div`.
- Core inline constructors: `Str`, `Space`, `SoftBreak`, `LineBreak`, inline
  mark constructors, `Quoted`, `Code`, `Math`, `RawInline`, `Link`, `Image`,
  `Note`, and `Span`.
- Explicit malformed-packet errors for missing `blocks`, invalid API-version
  tuples, unsupported constructors, and non-document writer input.
- WordPress handoff smoke path that reads Pandoc JSON, renders WordPress blocks,
  and writes the filtered JSON packet back out.

Verification:

- `php -l lanes/pandoc/src/PandocJsonReader.php`:
  no syntax errors detected.
- `php -l lanes/pandoc/src/PandocJsonWriter.php`:
  no syntax errors detected.
- `php -l lanes/pandoc/src/WordPressBlockWriter.php`:
  no syntax errors detected.
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`:
  no syntax errors detected.
- `php -l lanes/pandoc/examples/wordpress-native-json-filter-handoff.php`:
  no syntax errors detected.
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`:
  1 test file, 69 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`:
  2 test files, 2,384 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-native-json-filter-handoff.php --self-test`:
  `pandoc json native handoff ok`.
- `git diff --check -- lanes/pandoc`:
  passed.

Root verification was not run because this is an isolated micro-slice.

Dependency closure: no new support component is needed. This slice reuses the
existing lane-local AST, WordPress block writer, and PHP JSON extension. It does
not activate DOCX/OpenXML, XML/HTML DOM, YAML metadata, archive/compression,
Unicode/charset, citation, math, template, EPUB/ODT, PDF, or CFB support rows.

Exclusions: modern Pandoc table JSON remains out of this core slice because the
table JSON contract is owned by the table-geometry/package conversion work and
needs its own fixture-backed mapping.
