# Pandoc CSL Static Authority Name Slice

- Micro-slice: `pandoc-citation-csl-core-current-base-20260609T044330Z`
- Base accepted HEAD: `b7207ea8e728f24041eefd971a1a50d4e50c22fc`
- Scope: bounded CSL citation/name handoff for authority metadata displayed in WordPress review output.

## Behavior

`CitationCslProcessor` now uses the same static-ordering and compact family-given script decision for plain normalized name displays that it already used for rendered bibliography names. This keeps scalar `authority` text generated from CSL name lists in source order for non-Byzantine/static names such as `Yamada Taro`, and without an inserted Latin-space separator for compact CJK names such as `山田太郎`.

The updated WordPress authority smoke keeps the existing `is-creator="authority"` route and adds static/compact authority packets so both `text variable="authority"` and `names variable="authority"` remain reviewable without external citeproc execution.

## Evidence

Red-first focused check:

```sh
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
```

Result: failed on the new `renders bounded csl static ordered authority names in scalar handoff` case because scalar authority text rendered `Taro Yamada; Mei Sato, Reporter` instead of `Yamada Taro; Sato Mei, Reporter`.

Green focused check:

```sh
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
```

Result: `1 test files, 3684 assertions, 0 failures`.

Example smoke:

```sh
php lanes/pandoc/examples/wordpress-citation-csl-authority-creator-handoff.php --self-test
```

Result: `wordpress-citation-csl-authority-creator-handoff self-test passed`.

Additional verification completed in the final worker pass: PHP lint for changed PHP files, lane-status and manifest JSON validation, and `git diff --check -- lanes/pandoc`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP CSL JSON normalizer, CSL style parser, citation/bibliography renderer, Markdown reader, WordPress block writer, and focused PHP test runner. No Pandoc, citeproc, BibTeX/Biber, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external converter, online service, or live provider test was invoked.

## Non-Overlap

This slice does not alter the previously accepted authority creator condition behavior, citation name sort-order rendering, compact CJK bibliography rendering, bibliography display parts, PDF handoff diagnostics, DOCX/ODT/EPUB/ZIP package behavior, YAML metadata, Math/TeX, XML/HTML5 DOM, or upstream runner evidence. It only fixes scalar name-list flattening for CSL authority metadata.
