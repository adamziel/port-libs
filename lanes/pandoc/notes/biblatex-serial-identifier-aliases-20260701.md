# BibLaTeX Serial Identifier Aliases - 2026-07-01

- Expanded the legacy `BibtexCslProcessor` ISBN/ISSN field aliases to match strict CSL handoff coverage for `isbn13`, `isbn-13`, `isbn10`, `isbn-10`, `eisbn`, `e-isbn`, `electronicisbn`, `electronic-isbn`, `printissn`, `print-issn`, `pissn`, `p-issn`, `eissn`, `e-issn`, `electronicissn`, `electronic-issn`, `onlineissn`, `online-issn`, `issnonline`, and `issn-online`.
- Added focused coverage proving legacy BibLaTeX print/electronic serial identifiers normalize into existing CSL `ISBN`/`ISSN` variables and remain style-visible through citation, bibliography, and WordPress handoff.
- No external Pandoc, citeproc, BibTeX, Biber, office suite, TeX/browser engine, Node tooling, validators, online services, or live providers were invoked.
