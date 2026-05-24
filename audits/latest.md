# Independent Audit - 2026-05-24T20:29Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, and
recent Git history through `b66f0343 Refresh independent audit status`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T20:28:12Z -> 2026-05-24T20:29:11Z
HEAD during audit: b66f0343af9d
recent history: b66f0343 Refresh independent audit status; d5efa13d Refresh markerPDF ASCIIHex integration status; 5e46840f Integrate markerPDF ASCIIHex stream filter slice; 65f206b3 Refresh independent audit status; 1d3c64bf Refresh independent audit status
tracked status rows: 214 -> 214 -> 215
untracked files: 22917 -> 22919 -> 22920
dirty shortstat: 214 files changed, 182467 insertions(+), 22962 deletions(-) -> 215 files changed, 182514 insertions(+), 22963 deletions(-)
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
dependency backlog: 37 rows, 0 active (blocked 1, candidate 25, deferred 11)
root run by this audit: not started
```

Required exact pre-root process gate:

```text
2026-05-24T20:28:12Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T20:29:00Z pgrep -af '^php tools/run-tests\.php$': 1693599 php tools/run-tests.php
2026-05-24T20:29:11Z pgrep -af '^php tools/run-tests\.php$': 1693599 php tools/run-tests.php
2026-05-24T20:29:11Z owner evidence: 1693599 claude 1693577 R 00:25 php tools/run-tests.php
```

I did not start `php tools/run-tests.php`. The checkout moved between the
initial clear gate and the later occupied gate, and another no-argument root
harness was active by the time owner evidence was sampled.

Latest sampled manifest/status counts. These are samples from a moving
worktree, not an acceptance ledger:

```text
lane          manifest mapped/total     status phpPass/phpFail
difftastic    1148/1288                 3752/0
dolt          613/613                   462/0
esbuild       223/2567                  223/0
gitoxide      1471/2877                 7603/0
libsqlite     217/1589                  217/0
LightningCSS  3010/3548                 4387/0
markerPDF     165/78                    270/0
pandoc        2276/2276                 402/0
quadrable     55/55                     260/0
rclone        484/1601                  484/0
Readability   1578/1984                 147/0
syncthing     658/658                   9170/0
```

## Findings

1. **Critical - the checkout is not stable enough for repo-wide acceptance evidence.**
   - Paths: `tools/run-tests.php`, `progress.md`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:29` small committed slices,
     `goal.md:48` verify and commit before reassignment, and `goal.md:49`
     honest repo-wide tests/static checks.
   - Evidence: tracked dirty rows changed `214 -> 215`, untracked files
     changed `22917 -> 22920`, and dirty shortstat changed to `215 files
     changed, 182514 insertions(+), 22963 deletions(-)` during this audit
     window. The exact root gate was initially empty, then PID `1693599 php
     tools/run-tests.php` appeared and was owned by `claude`; an audit-owned
     duplicate root run would have violated the harness rule. Any root result
     produced from this moving dirty checkout should be treated as
     slice-scoped unless the integrator proves a frozen tested snapshot.

2. **Critical - the repository remains a broad unaccepted dirty batch, not a sequence of small reviewable slices.**
   - Paths: `lanes/difftastic/lane-status.json`,
     `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`,
     `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/pandoc/lane-status.json`, `lanes/quadrable/lane-status.json`,
     `lanes/rclone/lane-status.json`, `lanes/readability/lane-status.json`,
     `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:36`, and
     `goal.md:48` require small, correct, verified, committed slices.
   - Evidence: most current lane-status files explicitly say `pending`,
     `uncommitted`, or that root aggregate verification and integrator
     acceptance remain pending. Several lanes report green focused tests while
     preserving broad dirty scopes or interleaved handoffs, including
     Readability's mixed AO3/UTF-16 pile, Quadrable's unrelated unaccepted
     slices, Gitoxide's stacked discovery handoff, rclone's WebDAV request
     batch, and Syncthing's large discovery/GUI/API accumulation.

3. **High - `porting.html` is an accepted-snapshot dashboard, not a current working-state dashboard.**
   - Paths: `porting.html:34-38`, `porting.html:56-67`,
     `porting-summary.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     visible coordination by denominator, mapped tests, PHP pass/fail, phase,
     audit, blocker, and commit.
   - Evidence: the dashboard honestly labels itself as source commit
     `5e46840f9573`, but current dirty metadata has already moved far beyond
     that snapshot. Examples: Difftastic dashboard `240/586` mapped versus
     current manifest `1148/1288`; Dolt dashboard `303/613` versus current
     `613/613`; LightningCSS dashboard `886/3532` versus current
     `3010/3548`; rclone dashboard denominator `2553` versus current
     manifest denominator `1601`; Syncthing dashboard `324` PHP passes versus
     current status `9170`; Pandoc dashboard `619/2276` versus current
     manifest `2276/2276`. The page is useful as an accepted baseline, but it
     must not be read as live progress for the dirty tree.

4. **High - support-library work is still backlog-only despite lane-local rich-function claims.**
   - Paths: `dependency-backlog.json`, `porting.html:71-129`,
     `lanes/rclone/lane-status.json`, `lanes/syncthing/lane-status.json`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: support-library directives for a bounded
     native component, activation gate, dependency-specific denominator,
     mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases, and
     no whole applications or hidden shell-outs; also `goal.md:30` and
     `goal.md:35`.
   - Evidence: `dependency-backlog.json` still has 37 rows and 0 active
     bounded support ports. Rclone is accumulating WebDAV DELETE/MKCOL/PUT
     and COPY/MOVE behavior while `webdav-protocol-core` remains inactive.
     Syncthing manifests global discovery lookup URL construction/query
     encoding, but `url-percent-encoding-core` lists rclone, gitoxide,
     esbuild, lightningcss, and readability only, and its activation gate does
     not include Syncthing discovery URLs. That evidence must remain
     lane-local unless the URL support row is extended behind an accepted
     gate with malformed query/percent cases.

5. **High - Pandoc rich document-conversion status is overstated by manifest coverage.**
   - Paths: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/lane-status.json`, `dependency-backlog.json`,
     `porting.html:63`, `porting.html:93-127`.
   - Goal requirement at risk: `goal.md:12` requires a document conversion
     kernel with Markdown, HTML, WXR, EPUB/PDF-oriented intermediate forms,
     and WordPress block output; `goal.md:35-40` require meaningful upstream
     parity, edge cases, and explicit blockers.
   - Evidence: current Pandoc manifest samples `2276/2276` mapped and lane
     status reports `99%`, but PHP evidence is `402` focused behavior tests
     and the full Haskell runner remains unexecuted. Required rich areas -
     DOC, DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument,
     citations, math, tables, templates, package containers, XML/HTML,
     Unicode/charset, JSON/YAML metadata, syntax highlighting, and
     archive/compression - are visible only as inactive support rows without
     accepted dependency manifests, PHP ledgers, malformed/corrupt evidence,
     activation records, or bounded install-attempt notes.

6. **High - markerPDF still mixes incompatible denominator units and external-runtime boundaries.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/lane-status.json`, `porting.html:62`,
     `dependency-backlog.json`.
   - Goal requirement at risk: `goal.md:9` PDF-to-structured-content
     extraction, `goal.md:25` real upstream benchmark denominator, and
     `goal.md:30` no wrappers/shell-outs/external engines as progress.
   - Evidence: markerPDF has an accepted native ASCIIHex stream-filter slice,
     but its manifest still reports `165/78` mapped, which compares semantic
     mappings to a tracked-path denominator. Full markerPDF parity remains
     blocked by Poetry and heavy Python/model/runtime surfaces including
     pdftext, pypdfium2, Surya/OCR, tabled-pdf, Texify, Torch/Nougat,
     Streamlit/FastAPI/Uvicorn, OCRMyPDF/Tesseract, Ghostscript, and
     benchmark archives. The inactive `pdf-text-dictionary-core`,
     `layout-ocr-result-core`, and `table-geometry-core` rows mean broader
     searchable-PDF/OCR/table claims are not reusable support-library
     progress.

7. **Medium - manifest, status, and dashboard count units remain inconsistent across lanes.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `porting.html:56-67`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:45` require durable coordination by upstream denominator,
     mapped tests, PHP pass/fail counts, and progress.
   - Evidence: markerPDF maps more behaviors than its path denominator;
     Pandoc maps inventory artifacts to broad conversion claims while PHP
     status counts focused behavior tests; rclone's dashboard still shows a
     `2553` denominator while the current manifest uses `1601`; status
     `phpPass` values are sometimes assertion counts, sometimes behavior-case
     counts, and sometimes test-file ledgers. Percentages cannot be compared
     safely until the ledger separates upstream-denominator units from PHP
     assertion/behavior counts.

8. **Medium - high lane percentages hide acceptance blockers.**
   - Paths: `lanes/dolt/lane-status.json`,
     `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/pandoc/lane-status.json`, `lanes/quadrable/lane-status.json`,
     `lanes/rclone/lane-status.json`, `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: `goal.md:35` says passing tests are not enough,
     and `goal.md:48-49` require verified integration and repo-wide checks.
   - Evidence: Dolt, libsqlite, LightningCSS, Pandoc, Quadrable, rclone, and
     Syncthing all report `98-99%` in lane-status while their blockers still
     name root aggregate verification and supervisor/integrator acceptance.
     These values are lane-local confidence, not accepted project progress.

## Required Next Intervention

Freeze writers/status publishers long enough for two stable polls, then accept
or reject exactly one owner-free reduced lane batch whose dirty files match its
evidence. Normalize that lane's manifest/status count units before publishing
it, regenerate `progress.md`, `porting.html`, and `porting-summary.json` from
the accepted commit, and only then run one serialized no-argument
`php tools/run-tests.php` after `pgrep -af '^php tools/run-tests\.php$'` is
empty. Keep support-library rows inactive until a base-lane rich slice is
accepted or blocked on a bounded dependency component; extend
`url-percent-encoding-core` for Syncthing only behind that accepted gate.
