# Pandoc JSON Raw Format Constructors

Date: 2026-06-25
Base: origin/plainmath-parity-20260625
Bead: plib-0441f

This slice preserves tagged Pandoc JSON `Format` helper constructors for
`RawBlock` and `RawInline` payloads on the plainmath parity branch.

Covered behavior:

- `JsonReader` accepts both bare raw format strings and tagged
  `{"t":"Format","c":"..."}` constructor payloads.
- Tagged constructor payloads are retained as `formatConstructor` and
  `formatNative` sidecars on the shared AST.
- `JsonWriter` reuses the retained tagged constructor when the current raw
  format still matches, including after text-only edits.
- Bare raw format strings remain bare on JSON output.

Verification:

- `php -l lanes/pandoc/src/JsonReader.php`
- `php -l lanes/pandoc/src/JsonWriter.php`
- `php -l lanes/pandoc/tests/JsonReaderWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/JsonReaderWriterTest.php`
  passed 1 file, 52 assertions, 0 failures.

No shell-out to Pandoc, Haskell tooling, office suites, TeX/browser engines,
Node tooling, or external validators was used.
