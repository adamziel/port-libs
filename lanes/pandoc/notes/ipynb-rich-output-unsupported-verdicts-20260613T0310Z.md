# IPYNB Rich Output Unsupported Verdicts

This slice adds metadata-only diagnostics for notebook rich outputs without
rendering or exposing output payload bytes.

- `IpynbReader` now records safe output summaries for code-cell outputs:
  output type, stream/error line counts, metadata key counts, MIME type lists,
  and rich-output unsupported verdicts.
- Rich MIME bundles from `display_data` and `execute_result` emit
  `ipynb-rich-output-unsupported` diagnostics with
  `payloadPolicy=metadata-only-no-payload-bytes`.
- WordPress handoff receives only scalar summary attributes such as
  `data-ipynb-output-mime-types` and
  `data-ipynb-rich-output-unsupported-count`.
- The focused payload-redaction test proves HTML, image, and JSON output
  payload strings do not appear in the AST JSON or WordPress HTML.

Counter movement:

- `phpPass`: `3325 -> 3326`
- `mappedIpynbRichOutputUnsupportedVerdictCases`: `1`
- `ipynbRichOutputUnsupportedVerdictAssertions`: `43`
- Focused `IpynbReaderTest.php`: `1` file, `86` assertions, `0` failures
- Full `lanes/pandoc/tests`: `45` files, `74664` assertions, `0` failures

Verification:

- `php -l lanes/pandoc/src/IpynbReader.php`
- `php -l lanes/pandoc/tests/IpynbReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/IpynbReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`

No Pandoc binary, Jupyter, Python notebook runner, browser renderer, Node
tooling, external validator, online service, live provider test, or
live-service provider test was invoked.
