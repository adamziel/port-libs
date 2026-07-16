# Pandoc Citation/CSL Supplement Number Handoff

Micro-slice: `pandoc-citation-csl-core-current-base-20260609T015928Z`

Base accepted HEAD: `afefe2709cd2d600e733f14d1a2c7daf937774dc`

## Behavior

- Added bounded item-level `supplement` support for CSL citation styles.
- `CitationCslProcessor` now normalizes the CSL item `supplement` field and renders it through `<text variable="supplement">`.
- `CslStyle` now accepts `supplement` for `<number>` and `<label>` elements; numeric text forms also apply to `supplement`.
- The WordPress handoff now preserves source supplement values with contextual `supplement` labels, ordinal/roman/long-ordinal number forms, numeric-range pluralization, and nonnumeric fallbacks.

## Red Check

Before source edits, this focused command showed the unsupported-variable gap:

```bash
php -r 'require "tools/bootstrap.php"; use PortLibs\Pandoc\CitationCslProcessor; $style="<?xml version=\"1.0\"?><style xmlns=\"http://purl.org/net/xbiblio/csl\" version=\"1.0\"><info><title>T</title><id>x</id><updated>2026-06-09T00:00:00Z</updated></info><citation><layout><number variable=\"supplement\"/></layout></citation></style>"; try { CitationCslProcessor::fromItems([["id"=>"s","type"=>"report","supplement"=>"2"]])->withCslStyle($style); echo "unexpected pass\n"; } catch (Throwable $e) { echo get_class($e) . ": " . $e->getMessage() . "\n"; }'
```

Output:

```text
InvalidArgumentException: CSL citation number variable is not supported: supplement
```

## Verification

```bash
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
```

Result: `1 test files, 3281 assertions, 0 failures`.

```bash
php -l lanes/pandoc/src/CitationCslProcessor.php
php -l lanes/pandoc/src/CslStyle.php
php -l lanes/pandoc/tests/CitationCslProcessorTest.php
php -l lanes/pandoc/examples/wordpress-citation-csl-supplement-number-handoff.php
```

Result: no syntax errors in changed PHP files.

```bash
php lanes/pandoc/examples/wordpress-citation-csl-supplement-number-handoff.php --self-test
```

Result: `wordpress-citation-csl-supplement-number-handoff self-test passed`.

```bash
git diff --check -- lanes/pandoc
```

Result: passed with no output.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2096` -> `2097`.
- `benchmarkDenominator.mapped`: `2507` -> `2508`.
- `mappedCitationCslCoreCases`: `12` -> `13`.
- Focused assertion delta in the new Citation/CSL PASS case: 21 assertions.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP CSL style parser, locale terms, numeric formatter, Markdown reader, and WordPress block writer. No Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, TeX/PDF engine, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat the accepted locator vocabulary work: `supplement` was already recognized as a locator label. This slice covers item-level CSL `supplement` as a number/label/text variable in citation styles.
