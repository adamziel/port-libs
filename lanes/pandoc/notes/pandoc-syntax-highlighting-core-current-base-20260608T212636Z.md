# Pandoc Syntax Highlighting Current Base: BibTeX/BibLaTeX

Slice: `pandoc-syntax-highlighting-core-current-base-20260608T212636Z`
Base: `d1134e2a181aaf4c0c02f2b0d3b93f388be55ad8`

## Summary

Added bounded native PHP `SyntaxHighlighter` support for BibTeX/BibLaTeX code fences used in bibliography review packets. The slice maps `bib`, `bibtex`, and `biblatex` aliases to `bibtex`, preserves Pandoc-style requested-language wrappers, and carries highlighted output through the Markdown fixture and WordPress syntax-highlighting handoff example.

Token coverage is intentionally bounded to code-fence review needs:

- `%` comments.
- `@entry` keywords and citation keys.
- Field names before `=`.
- Quoted and braced literal values.
- `@string` macro assignments and `#` concatenation.
- Month constants and numeric year/date fragments.
- Punctuation/operator boundaries.

## Source Truth And Scope

Pandoc delegates code-block highlighting by language class to Skylighting. This lane ports the format contract needed by the PHP support library: stable alias normalization, source wrapper metadata, and token-class handoff for reviewer-visible code blocks. It does not attempt full Skylighting XML/theme parity.

No Pandoc, Cabal solver/build/test command, Haskell runner, Skylighting binary/library, BibTeX, Biber, external highlighter, browser renderer, JavaScript runtime, online service, live provider test, or live-service provider test was executed.

## Verification

Baseline before the patch:

```sh
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
# 1 test files, 1914 assertions, 0 failures
```

Final focused verification:

```sh
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
# 1 test files, 1943 assertions, 0 failures
```

Example smoke:

```sh
php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
# passed
```

Syntax/diff checks:

```sh
php -l lanes/pandoc/src/SyntaxHighlighter.php
php -l lanes/pandoc/tests/SyntaxHighlighterTest.php
php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php
git diff --check -- lanes/pandoc
# passed
```

## Dependency Closure

No new support component is needed. The slice reuses native `SyntaxHighlighter`, `MarkdownReader` code-block attributes, and the WordPress syntax-highlighting handoff example.

## Non-Overlap

This does not touch accepted CSS, Rust, Python, GraphQL, AsciiDoc, HCL/Terraform, Typst, HTML-template, Sed, shell, or other syntax-highlighting slices, and it does not modify the BibTeX/CSL bibliography parser. It owns only BibTeX/BibLaTeX source-code token handoff.
