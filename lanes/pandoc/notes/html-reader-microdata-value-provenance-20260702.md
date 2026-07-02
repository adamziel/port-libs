# HTML Reader Microdata Value Provenance

2026-07-02 `plib-gbl`: `HtmlReader` now records metadata-only microdata value byte provenance for extracted `itemprop` values. Each property summary carries source byte length, emitted byte length, and truncation state, while item summaries and document metadata roll up truncated property-value counts.

The microdata value cap remains 512 bytes, but truncation now preserves valid UTF-8 when a multibyte scalar crosses the byte boundary. This keeps large microdata payloads bounded without emitting invalid text into downstream metadata or WordPress-facing handoff paths.

Focused coverage: `HtmlReaderTest.php` maps 8 HTML reader microdata metadata cases with 96 assertions. No external Pandoc, browser engine, HTML validator, network fetcher, office suite, TeX/Typst engine, Node tooling, or live service is invoked by this slice.
