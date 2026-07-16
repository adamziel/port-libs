# Pandoc Charset/Unicode Width Current-Base Slice

Micro-slice: `pandoc-charset-unicode-width-core-current-base-20260609T012742Z`
Base accepted HEAD: `942d0b99001290be4ad52e5f31464bd1e4c71c99`

## Behavior

Added BOM stale-label diagnostics to `UnicodeText::declaredCharset()`.
Byte-order marks remain authoritative for the declared source encoding, but
conflicting `Content-Type` charset labels and UTF-8 in-document XML/HTML meta
charset declarations are now surfaced as ignored diagnostics, for example
`ignored-content-type-charset:windows-1252` and
`ignored-html-meta-charset:windows-1252`.

Matching explicit UTF-8 labels remain silent so normal BOM-marked UTF-8 input
does not gain noisy diagnostics.

## Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` files existed.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  passed with `1 test files, 1194 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  failed with `1 test files, 1185 assertions, 1 failures` because
  `declaredCharset()` returned empty diagnostics for BOM-overridden
  `windows-1252` content-type and HTML meta labels.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  passed with `1 test files, 1199 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  passed with `charset unicode handoff self-test ok`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `UnicodeText`
declared-charset preflight, `MarkdownReader` byte-source handoff,
`WordPressBlockWriter`, and the existing WordPress charset audit example.
No Pandoc, Cabal solver/build/test command, Haskell runner, external charset
converter, browser renderer, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap

This avoids accepted legacy byte-decoder and display-width slices. It is
limited to BOM precedence diagnostics for declared charset preflight under
`lanes/pandoc/**`.

## Next

Pick a non-overlapping charset/Unicode gap such as BOM-aware HTML parser
handoff integration, Unicode line-break metadata, or remaining
terminal-profile width variants.
