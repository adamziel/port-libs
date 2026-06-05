# Pandoc Citation/CSL Core Current Base - Sort Separator

Slice: `pandoc-citation-csl-core-current-base-20260605T135203Z`

Base: `e99263b83e6372b0198e6a802ab673c50a101156`

## Source Truth

- CSL 1.0.2 defines `sort-separator` on `cs:name` as the delimiter used between name parts switched by `name-as-sort-order`, defaulting to `, `. Source: https://docs.citationstyles.org/en/v1.0.2/specification.html
- This bounded PHP slice preserves raw delimiter whitespace from the CSL XML attribute and applies it only to bibliography names inverted by `name-as-sort-order`.
- No Pandoc, citeproc, Cabal build, Haskell runner, BibTeX, Biber, bibliography manager, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF engine, browser renderer, JavaScript, online sanitizer, or online service was executed.

## Implementation

- `CslStyle` now parses and summarizes `cs:name sort-separator` while preserving delimiter whitespace.
- `CitationCslProcessor` now carries `sortSeparator` through normalized name-rendering options and uses it between family and given parts for inverted bibliography names.
- `wordpress-citation-csl-sort-separator-handoff.php` shows WordPress block output for custom inverted-name separators without invoking external citation tooling.

## Verification

Baseline before the implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1147 assertions, 0 failures
```

Red-first probe:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
FAIL applies bounded csl sort separator for inverted bibliography names
Expected: ' | '
Actual: NULL
1 test files, 1149 assertions, 1 failures
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1155 assertions, 0 failures

php lanes/pandoc/examples/wordpress-citation-csl-sort-separator-handoff.php --self-test
wordpress-citation-csl-sort-separator-handoff self-test passed
```

```text
php -l lanes/pandoc/src/CslStyle.php
No syntax errors detected in lanes/pandoc/src/CslStyle.php
php -l lanes/pandoc/src/CitationCslProcessor.php
No syntax errors detected in lanes/pandoc/src/CitationCslProcessor.php
php -l lanes/pandoc/tests/CitationCslProcessorTest.php
No syntax errors detected in lanes/pandoc/tests/CitationCslProcessorTest.php
php -l lanes/pandoc/examples/wordpress-citation-csl-sort-separator-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-citation-csl-sort-separator-handoff.php
```

```text
php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " OK\n"; }'
lanes/pandoc/lane-status.json OK
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json OK
```

```text
git diff --check -- lanes/pandoc
<no output; passed>
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `931 -> 932`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped checks: `1387 -> 1388`.
- `mappedCitationCslCoreCases`: `10 -> 11`.
- Focused coverage: `CitationCslProcessorTest.php` moved from 58 PASS cases / 1147 assertions to 59 PASS cases / 1155 assertions.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP `CslStyle`, `CitationCslProcessor`, `MarkdownReader`, and `WordPressBlockWriter` support rows.

Full upstream Pandoc runner parity remains gated on hydrating the pinned Pandoc checkout with Cabal package/project files and creating a non-mutating Haskell test-runner plan. The local CSL sort-separator support is not blocked by that runner gate.

## Non-Overlap And Follow-Up

This slice only touches Citation/CSL name rendering for inverted bibliography names. It does not overlap accepted CSL date-part/date-form rendering, name-part formatting, initialize-with-hyphen, et-al delimiter policy, citation-number assignment/collapse, near-note positioning, year-suffix disambiguation, subsequent-author substitution, BibTeX/BibLaTeX metadata, ZIP/OPC/archive, DOCX, ODT, EPUB, PDF engine, YAML, doctemplate, table geometry, math, charset, syntax-highlighting, or upstream-runner dependency audit slices.

Follow-up CSL work should keep locale-specific name delimiter inheritance, `delimiter-precedes-last`, `demote-non-dropping-particle`, institution name-part rendering, broader citeproc parity, bibliography manager parity, and full upstream citeproc/Pandoc runner parity as separate bounded slices.
