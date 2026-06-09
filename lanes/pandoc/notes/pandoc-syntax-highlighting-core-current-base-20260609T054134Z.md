# Pandoc syntax highlighting Coq proof handoff

Slice: `pandoc-syntax-highlighting-core-current-base-20260609T054134Z`
Base: `50ff75128f57e5d1c91c6f6643df81bffbb2e704`
Date: 2026-06-09 UTC

## Scope

- Added native Coq syntax highlighting support to `SyntaxHighlighter`.
- Normalized `coq`, `coq-script`, `coqdoc`, `gallina`, `rocq`, and `rocq-prover` language aliases to the Coq tokenizer.
- Added a line-numbered Coq proof review fixture and WordPress syntax-highlighting example coverage.
- Added focused assertions for Coq comments, vernacular commands, theorem signatures, record fields, datatypes, constants, tactics, strings, line numbering, WordPress style metadata, and Rocq alias dispatch.

## Red-first evidence

Before the implementation, a direct `coq` highlight probe normalized to `NULL`, returned an empty language, and reported `unsupported-language`.

Baseline focused suite:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 2645 assertions, 0 failures
```

## Verification

Focused syntax suite after the patch:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 2676 assertions, 0 failures
```

WordPress example smoke:

```text
php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
syntax highlighting handoff self-test ok
```

Final lane checks for this handoff:

```text
php -l lanes/pandoc/src/SyntaxHighlighter.php
No syntax errors detected in lanes/pandoc/src/SyntaxHighlighter.php
php -l lanes/pandoc/tests/SyntaxHighlighterTest.php
No syntax errors detected in lanes/pandoc/tests/SyntaxHighlighterTest.php
php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php
php -r 'foreach (["lanes/pandoc/lane-status.json","lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'
lanes/pandoc/lane-status.json json ok
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json json ok
git diff --check -- lanes/pandoc
passed with no output
```

## Dependency closure

No new dependency component is needed. This slice reuses the native PHP scanner, Markdown fixture reader, WordPress HTML block writer, and existing Pandoc JSON theme CSS handling. It does not require Pandoc, Skylighting, Haskell test binaries, external syntax highlighters, Office tools, TeX/PDF engines, browser renderers, archive binaries, online services, or live provider tests.

## Non-overlap

This follows the accepted fixture-backed syntax-highlighting lane and avoids the latest Idris proof review slice. It does not change OPC, ODT, DOCX, EPUB, archive/compression, YAML, CSL/BibTeX, math, PDF-engine, XML/HTML5 DOM, charset, or table-geometry behavior.

## Follow-up

Useful non-overlapping syntax targets remain Agda/proof-assistant fixture coverage or deeper Coq nested-comment parity if upstream fixtures require it.
