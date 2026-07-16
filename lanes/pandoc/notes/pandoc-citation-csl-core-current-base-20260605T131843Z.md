# Pandoc Citation/CSL Core Current Base - Initialize With Hyphen

Slice: `pandoc-citation-csl-core-current-base-20260605T131843Z`

Base: `1316e4b8e007a66a99c6b69ce35b9a31ce98507b`

## Behavior

- Added bounded native CSL support for `initialize-with-hyphen` on `cs:name`.
- Default behavior remains `true`, preserving initialized hyphenated given names such as `Jean-Luc` as `J.-L.`.
- `initialize-with-hyphen="false"` suppresses the hyphen in initialized given names, producing `J. L.` for bibliography or citation name elements that request it.
- Invalid boolean values now raise an explicit CSL name-attribute diagnostic.
- WordPress block handoff now has a self-test example for citation initials plus bibliography initials with different hyphen policies.

## Source Truth

- This implements the bounded CSL style contract for name initialization in native PHP.
- No external citeproc, Pandoc, Haskell runner, BibTeX, Biber, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF engine, browser renderer, JavaScript, online sanitizer, or online service was executed.

## Evidence

Baseline before the implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1133 assertions, 0 failures
```

Red-first probe:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
FAIL applies bounded csl initialize with hyphen for hyphenated given names
Expected: 'J. L. Hyphen Given Packet.'
Actual: 'J.-L. Hyphen Given Packet.'
1 test files, 1140 assertions, 1 failures
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1147 assertions, 0 failures
```

```text
php lanes/pandoc/examples/wordpress-citation-csl-initialization-handoff.php --self-test
wordpress-citation-csl-initialization-handoff self-test passed
```

```text
php -l lanes/pandoc/src/CitationCslProcessor.php
No syntax errors detected in lanes/pandoc/src/CitationCslProcessor.php
php -l lanes/pandoc/src/CslStyle.php
No syntax errors detected in lanes/pandoc/src/CslStyle.php
php -l lanes/pandoc/tests/CitationCslProcessorTest.php
No syntax errors detected in lanes/pandoc/tests/CitationCslProcessorTest.php
php -l lanes/pandoc/examples/wordpress-citation-csl-initialization-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-citation-csl-initialization-handoff.php
```

```text
php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " OK\n"; }'
lanes/pandoc/lane-status.json OK
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json OK
```

```text
git diff --check -- lanes/pandoc
```

`git diff --check` produced no output.

Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `917` -> `918`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped checks: `1375` -> `1376`.
- `mappedCitationCslCoreCases`: `10` -> `11`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `CslStyle`, `CitationCslProcessor`, `MarkdownReader`, and `WordPressBlockWriter` support rows.

Upstream runner parity remains gated on a hydrated Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`, `pandoc.cabal`, and `pandoc-lua-engine/pandoc-lua-engine.cabal` present before any bounded Haskell runner can be planned.

## Non-Overlap

This slice only touches Citation/CSL core name-initial rendering. It does not overlap the accepted syntax-highlighting Rust alias/token handoff, CSL et-al delimiter policy, date-part rendering, bibliography entry-subtype metadata, BibTeX/BibLaTeX parsing, DOCX/OpenXML, YAML metadata, archive/package helpers, table geometry, PDF-engine handoff, math/TeX, charset/Unicode, or upstream-runner dependency audit slices.

## Follow-Up

Keep broader citeproc parity, disambiguate-add-givenname, initialize-with locale/institution edge cases, name form variants, locale-specific name delimiters, and full style-module coverage as separate bounded Citation/CSL slices.
