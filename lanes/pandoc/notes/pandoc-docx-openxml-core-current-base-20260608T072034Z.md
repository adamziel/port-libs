## pandoc-docx-openxml-core-current-base-20260608T072034Z

Base accepted HEAD: `0beefbb15b02a8a82f64dd1fad4516dc169139da`

Implemented one bounded DOCX/OpenXML OMML behavior cluster: `m:nary`
operators with `m:sub` and `m:sup` limits are now converted into the existing
`docx-omml` math AST text path before Markdown and WordPress handoff. The slice
maps sum/integral-style operators to TeX-style command text and respects
`m:subHide` / `m:supHide` limit flags.

Source-truth and non-overlap:

- The existing native DOCX reader already mapped OMML text, scripts,
  fractions, radicals, and display/inline math into `AstNode('math')`; this
  slice only fills the next bounded OMML structural gap.
- It does not overlap recent DOCX/OpenXML run-language, embedded package,
  tracked-formatting, deleted-OMML-revision, paragraph-border, or structured
  document tag slices.
- No Pandoc, Word, LibreOffice, zip/unzip, Cabal, Haskell runner, TeX engine,
  online service, or external office/converter tool was executed.

Focused evidence:

- Baseline before adding this case:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 2429 assertions, 0 failures`.
- Red-first after adding the fixture/test but before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  failed the new n-ary case with actual text `i=1na_i + 01f(x) dx`.
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 2438 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  passed with `docx body handoff self-test ok`.

Status delta:

- `lane-status.json` `phpPass`: `1559 -> 1560`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1980 -> 1981`.
- New focused assertions in `DocxReaderTest.php`: `+9`.

Dependency closure:

No new support component is needed. The implementation reuses the existing
native OPC/DOCX reader, OMML math AST path, Markdown math writer, and
WordPress math-span writer.

Follow-up:

Remaining DOCX OMML work should choose a non-overlapping structure such as
matrix/array, delimiter, accent, function, or equation-array handoff with a
focused native PHP fixture.
