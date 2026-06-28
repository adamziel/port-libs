# Pandoc JSON/native raw format alias constructors

Slice: `plib-ccn6d`
Area: Pandoc JSON/native AST constructor completeness.

Implemented one bounded constructor-completeness case for additional textual
native raw format alias combinations on top of the existing mainline
Markdown/TeX raw-format classifier. The focused fixture covers Markdown-family
`RawBlock`/`RawInline` formats, a TeX-family `RawBlock` alias, and a ConTeXt
`RawInline` alias, then verifies Pandoc JSON writer/reader handoff preserves the
original format payloads and raw text without invoking Pandoc, TeX, browser,
office, or external validator tools.

Validation:

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- Selected `PandocJsonNativeAstTest.php` closures for raw Markdown/TeX aliases,
  raw TeX inline text-reader handoff, and single-wrapped helper constructors

Result:

- New regression `maps textual native raw markdown and tex aliases into specific ast constructors`: PASS
- Selected closures passed with 56 assertions and 0 failures.
- Full `PandocJsonNativeAstTest.php` remains known baseline-red outside this slice.

Accounting:

- `lane-status.json` `phpPass`: `456 -> 457`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2303 -> 2304`
- `mappedJsonNativeRawFormatAliasCases`: `1`
