Pandoc citation/bibliography CSL core slice 2026-06-11T22:10:30Z

- Added direct CSL item normalization for legacy access-date aliases already accepted by the BibTeX path: `accessDate`, `access-date`, `urlDate`, `url-date`, `urldate`, and `visited`.
- Extended CSL date rendering/sort variable lookup so bounded styles can render those aliases through the canonical accessed-date metadata without external citeproc handoff.
- Focused regression coverage is in `lanes/pandoc/tests/CitationCslProcessorTest.php` under direct CSL access date alias handling.
