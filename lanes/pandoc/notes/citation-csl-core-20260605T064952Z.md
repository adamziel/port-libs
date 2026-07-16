# Citation CSL Core Current Base - Names Substitute

Date: 2026-06-05 UTC

Base: `346ee12a1740e2d5877ab0e26f367cddd50eae7b`

Slice: `pandoc-citation-csl-core-current-base-20260605T064952Z`

## Source Truth

Bounded CSL 1.0 style behavior: a `cs:names` element can include a `cs:substitute` child, and substitute children are tried in declared order when the primary names variable has no values. This slice implements the local PHP contract needed by the Pandoc citation handoff: missing primary authors can fall back to editor names, translator names, then title text without invoking citeproc, Pandoc, BibTeX, Biber, or bibliography managers.

## Implementation

- `CslStyle` now preserves `substitute` children for parsed `names` rendering elements and validates any nested macro references.
- `CitationCslProcessor` now renders explicit names substitutes in order and avoids the older implicit title fallback while trying substitute name variables.
- Citation labels now use translator names as a generic fallback after author/editor names for review list terms.
- `wordpress-citation-csl-handoff.php` now includes an editor-only source packet that verifies author-missing substitute rendering in WordPress blocks.

## Verification

Red-first check after adding expectations:

`php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`

Result: `1 test files, 721 assertions, 1 failures` on missing parsed `substitute` metadata.

Focused green check:

`php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`

Result: `1 test files, 738 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-citation-csl-handoff.php --self-test`

Result: `wordpress-citation-csl-handoff self-test passed`.

## Dependency Closure

No new support component is needed. This reuses the existing native CSL style XML parser, CSL item normalizer, Markdown reader, Markdown writer, and WordPress block writer. Full upstream Pandoc runner parity remains a separate Cabal/hydrated-checkout dependency task; no upstream runner, external citeproc, Pandoc binary, office tool, TeX/PDF engine, browser renderer, online sanitizer, or online service was run.

## Non-Overlap

This slice does not overlap the accepted ZIP central-directory signature slice, BibTeX/BibLaTeX metadata mapping, CSL date-part forms, locator/page labels, number rendering, citation-position conditionals, EPUB3/DOCX/ODT package work, XML/HTML5 DOM work, archive compression, table geometry, math/TeX, PDF engine handoff, legacy DOC/CFB, charset/Unicode, or syntax-highlighting slices.
