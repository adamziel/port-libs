# Pandoc CSL Citation Inline Formatting Slice

- Micro-slice: `pandoc-citation-csl-core-current-base-20260609T025545Z`
- Base accepted HEAD: `f3cb4f0219cafa35ccd839e4b1e650317d63e7bb`
- Scope: bounded CSL citation rendering handoff for WordPress inline output.

## Behavior

`CitationCslProcessor` now preserves plain `rendered` citation text while also exposing `cslInlineParts` when a custom CSL citation layout resolves to one formatted rendering element, such as a formatted `<group>` wrapping author-year output. `WordPressBlockWriter` consumes those parts as escaped inline spans using the same CSL formatting class/style mapping already used by bibliography display parts.

This keeps Markdown output unchanged and avoids adding HTML to the canonical citation string.

## Evidence

Red-first focused check:

```sh
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
```

Result: failed on the new `surfaces bounded csl rendering formatting on citation inline parts` case because `cslInlineParts` was missing.

Green focused check:

```sh
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
```

Result: `1 test files, 3430 assertions, 0 failures`.

Additional verification completed in the final worker pass: PHP lint for changed PHP files, the new WordPress example self-test, lane-status JSON validation, and `git diff --check -- lanes/pandoc`.

## Dependency Closure

No new support component is needed. This reuses existing native PHP CSL style parsing, citation rendering, Markdown parsing, and WordPress block writing. No Pandoc, citeproc, BibTeX/Biber, Cabal/Haskell runner, online service, external renderer, or template engine was invoked.

## Non-Overlap

This slice does not alter bibliography display-part behavior from the prior CSL display slices and does not touch Math/TeX, DOCX, EPUB, ODT, archive, YAML, or upstream-runner surfaces. It only adds a WordPress-safe inline citation formatting handoff for a bounded custom CSL citation layout shape.
