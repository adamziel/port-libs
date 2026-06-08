# XML/HTML5 DOM Core Current Base - Math Spacing References

## Slice

- Lane: pandoc
- Micro-slice: pandoc-xml-html5-dom-core-current-base-20260608T111631Z
- Accepted base: e9c8df6061c444c862955dfe47e8f5bcb299d3b3

## Implementation

`XmlHtmlDom` now pre-normalizes a bounded set of HTML5 named character references that libxml leaves as literal ampersand text but Pandoc HTML-reader-style imports need as decoded text before sanitizer and raw HTML handoff:

- `ApplyFunction` / `af` -> U+2061
- `InvisibleTimes` / `it` -> U+2062
- `InvisibleComma` / `ic` -> U+2063
- `MediumSpace` -> U+205F
- `ZeroWidthSpace` -> U+200B
- `NegativeThinSpace` -> U+200B

This extends the existing local `HTML5_ADDITIONAL_NAMED_CHARACTER_REFERENCES` map. It does not broaden semicolonless legacy handling; the existing semicolonless allowlist remains limited to `copy` and `nbsp`.

## Source Truth

The upstream Pandoc HTML reader inventory already maps the HTML-reader Special Characters section and XML/HTML5 DOM support rows. This slice ports the bounded format contract needed by the PHP DOM handoff: normalize selected HTML5 references before libxml parsing, preserve decoded characters in text and attributes, and serialize deterministic WordPress raw HTML without invoking browser or Pandoc tooling.

## Evidence

- Baseline focused DOM family before this slice: `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php` -> `3 test files, 1589 assertions, 0 failures`.
- Red-first after adding tests only: same command -> `3 test files, 1568 assertions, 3 failures`; failures showed the new references still serialized as `&amp;ApplyFunction;`, `&amp;InvisibleTimes;`, `&amp;MediumSpace;`, `&amp;InvisibleComma;`, `&amp;ZeroWidthSpace;`, and `&amp;NegativeThinSpace;`.
- Final focused DOM family: same command -> `3 test files, 1607 assertions, 0 failures`.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php --self-test` -> `xml/html5 dom handoff self-test ok`.
- PHP lint: changed source, test, and example PHP files all report `No syntax errors detected`.
- Diff hygiene: `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Mapping Delta

- `lane-status.json` `phpPass`: `1625 -> 1626`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2044 -> 2045`.
- `xmlHtmlDomCoreCases`: `8 -> 9`.
- `mappedXmlHtmlDomCoreCases`: `8 -> 9`.
- `xmlHtmlDomCoreAssertions`: `124 -> 142` (`+18` focused assertions).

## Dependency Closure

No new native PHP support component is needed. The slice reuses the existing `XmlHtmlDom` pre-parser normalization, `Html5Dom`/`Html5DomFragment` traversal, and `WordPressBlockWriter` raw HTML handoff. Excluded by scope: Pandoc, Cabal/Haskell runners, browser renderers, external XML/HTML tools, online sanitizers, online services, live provider tests, and live-service provider tests.

## Non-Overlap

This does not repeat earlier XML/HTML5 DOM rows for `NoBreak`, `NewLine`, `Tab`, `hopf`, `nbsp`, `copy`, SVG/MathML foreign-content casing, CDATA, iframe policy metadata, select label fallback, passive link relations, or safe SVG data-image resources.

## Follow-Up

Next XML/HTML5 DOM work should target a non-overlapping HTML reader support gap such as cite/provenance metadata, additional safe passive metadata mappings, or a bounded parser-to-AST handoff case while remaining native PHP and external-tool free.
