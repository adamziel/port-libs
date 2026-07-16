# Pandoc Markdown Reader Reference Label Completion - 2026-07-01

## Scope

Completed a bounded Markdown reader slice for upstream-style reference labels.
`MarkdownReader` now parses reference definitions with the same balanced
bracket scanner used by inline reference uses, so escaped closing brackets and
nested bracket groups resolve for shortcut, collapsed, explicit, image, and
WordPress handoff paths.

Reference label lookup now normalizes Markdown escapes, HTML entities,
whitespace, and Unicode case before matching. Overlong labels are rejected at
the upstream 999-character boundary, and repeated definitions keep the first
normalized target instead of replacing it with later duplicates.

## Validation

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderReferenceDefinitionSurgeTest.php lanes/pandoc/tests/MarkdownReaderReferenceLabelNormalizationSurgeTest.php`
  - 2 files, 458 assertions, 0 failures
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests`
  - 332 files, 123261 assertions, 9393 baseline failures outside this slice

Adjacent reference/footnote sweep notes:

- The reference-definition and reference-label normalization suites pass after
  this slice.
- The wider reader sweep remains baseline-red outside this slice in existing
  footnote continuation/balanced-label, inline code/raw-HTML link-label, and
  multiline reference-title cases.

No Pandoc, Haskell/Cabal, browser, office suite, TeX/PDF engine, external
validator, online service, or live provider was invoked.
