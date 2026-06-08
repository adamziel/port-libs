# pandoc-odf-open-document-core-current-base-20260608T124843Z

## Summary

Implemented one bounded ODF/OpenDocument dynamic-field mapping slice in native PHP:

- `text:script` is preserved as an inert `odf-field` span with script language and safe source metadata.
- `text:execute-macro` is preserved as an inert `odf-field` span with macro name metadata.
- `text:dde-connection` is preserved as an inert `odf-field` span with connection metadata.
- `text:dde-connection-decl` entries are collected as document-scope content declarations and merged into matching DDE field metadata by `text:connection-name`.

This is metadata preservation only. The reader does not execute scripts, macros, or DDE links.

## Source Truth

The slice follows the accepted native ODF reader contract: ODF dynamic fields must remain visible/reviewable in the AST and WordPress handoff as inert metadata spans rather than disappearing from paragraph text. The XML behavior is bounded to OpenDocument content elements and attributes already handled by `OdfReader` namespace-aware parsing.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external converter, script, macro, DDE link, online service, live provider test, or live-service provider test was executed.

## Verification

Red-first check:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 1922 assertions, 1 failure
```

The failure showed script, execute-macro, and DDE field text being dropped from the paragraph before source support.

Final focused check:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 1961 assertions, 0 failures
```

Final syntax, status, and diff checks:

```text
php -l lanes/pandoc/src/OdfReader.php
php -l lanes/pandoc/tests/OdfReaderTest.php
php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
git diff --check -- lanes/pandoc
```

All four checks passed.

## Counters

- `lane-status.json` `phpPass`: 1644 -> 1645
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: 2064 -> 2065
- `odfOpenDocumentCoreCases`: 13 -> 14
- `mappedOdfOpenDocumentCoreCases`: 13 -> 14
- `odfOpenDocumentCoreAssertions`: 295 -> 360

## Dependency Closure

No new support component is needed. This reuses `OdfReader`, existing ODT ZIP fixtures, `AstNode` span metadata, `MarkdownWriter`, and `WordPressBlockWriter`.

## Non-Overlap And Follow-Up

This slice does not repeat the accepted ODF heading anchor, conditional/hidden text, drop-down, hidden-paragraph, or database subtotal metadata work. A good next ODF slice would be data-pilot metadata, tracked table changes, or style-driven table cell semantics.
