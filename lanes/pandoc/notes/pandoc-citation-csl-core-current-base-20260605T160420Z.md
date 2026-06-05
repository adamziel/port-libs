# Pandoc Citation/CSL Current-Base Demote Particle Handoff

Slice: `pandoc-citation-csl-core-current-base-20260605T160420Z`
Base accepted HEAD: `2e813bea91bc8b597a218cba9f792e088892e3a0`

## Source Truth

- Bounded upstream contract: CSL styles expose a root `demote-non-dropping-particle` option with `never`, `sort-only`, and `display-and-sort` values. See the official CSL specification: https://docs.citationstyles.org/en/v1.0.2/specification.html
- This patch ports the format contract only. It does not invoke Pandoc, citeproc, BibTeX, Biber, Cabal, Haskell test binaries, external bibliography managers, online services, Word, LibreOffice, or archive/converter tools.

## Implementation

- `CslStyle` now parses and validates explicit root-level `demote-non-dropping-particle`, carrying it through citation and bibliography name-rendering summaries.
- `CitationCslProcessor` now demotes non-dropping particles for author sort keys when the style requests `sort-only` or `display-and-sort`.
- In inverted bibliography names, `display-and-sort` moves non-dropping particles after the given-name segment while preserving the existing `never` output shape.
- Added a WordPress block smoke for demoted CSL particle display and bibliography sort order.

## Evidence

Red-first focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1279 assertions, 1 failures
```

Final focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1289 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-citation-csl-demote-particle-handoff.php --self-test
wordpress-citation-csl-demote-particle-handoff self-test passed
```

Final hygiene:

```text
php -l lanes/pandoc/src/CslStyle.php
php -l lanes/pandoc/src/CitationCslProcessor.php
php -l lanes/pandoc/tests/CitationCslProcessorTest.php
php -l lanes/pandoc/examples/wordpress-citation-csl-demote-particle-handoff.php
php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'
git diff --check -- lanes/pandoc
```

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `987 -> 988`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1442 -> 1443`
- Focused coverage: one new Citation/CSL PASS case with 12 behavior assertions.

## Dependency Closure

No new support component is needed. The slice reuses native PHP CSL style parsing, citation/bibliography name rendering, bibliography sorting, `MarkdownReader`, and `WordPressBlockWriter`.

## Non-Overlap

This is not another et-al, sort-separator, subsequent-author, BibTeX/BibLaTeX metadata, YAML, DOCX, table-geometry, or upstream-runner audit slice. It owns only explicit CSL non-dropping-particle demotion behavior and the WordPress block handoff for that behavior.

Root harness: not run - isolated micro-slice.
