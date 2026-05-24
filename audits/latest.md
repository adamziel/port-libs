# Independent Audit - 2026-05-24T20:35Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, and
recent Git history through `9ec80339 Refresh independent audit status`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T20:33:51Z -> 2026-05-24T20:34:14Z
HEAD during audit: 9ec80339
recent history: 9ec80339 Refresh independent audit status; 6bd136e9 Record Syncthing handoff rejection; 6d25aa9c Record stale markerPDF handoff rejection; b66f0343 Refresh independent audit status; d5efa13d Refresh markerPDF ASCIIHex integration status; 5e46840f Integrate markerPDF ASCIIHex stream filter slice
tracked status rows: 215 -> 216
default status rows including untracked: 23162 -> 23165
dirty shortstat: 215 files changed, 183147 insertions(+), 22908 deletions(-) -> 216 files changed, 183189 insertions(+), 22908 deletions(-)
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
dependency backlog: 37 rows, 0 active (blocked 1, candidate 25, deferred 11)
root run by this audit: not started
```

Required exact pre-root process gate:

```text
first gate in this audit: 1774605 php tools/run-tests.php
2026-05-24T20:33:51Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T20:33:51Z ps -o pid,user,ppid,stat,etime,command -p 1774605: header only; PID had exited before owner sampling
2026-05-24T20:34:14Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T20:37:59Z pre-finish pgrep -af '^php tools/run-tests\.php$': 1784278 php tools/run-tests.php
2026-05-24T20:37:59Z owner evidence: 1784278 claude 1782110 Rs 00:10 php tools/run-tests.php
```

I did not start `php tools/run-tests.php`. The first exact root gate was
occupied by PID `1774605`; by the time I attempted owner sampling the process
had exited. Later exact gates were empty, but the checkout continued moving
during the same audit window, so it was not stable enough for an audit-owned
root run. A new no-argument root harness appeared before finishing as PID
`1784278`, owned by `claude`; it was not audit-owned and I did not start a
duplicate.

Latest sampled manifest/status counts. These are samples from a moving
worktree, not an acceptance ledger:

```text
lane          manifest mapped/total     status phpPass/phpFail
difftastic    1148/1288                 3760/0
dolt          613/613                   462/0
esbuild       223/2567                  223/0
gitoxide      1472/2877                 7603/0
libsqlite     218/1589                  217/0
LightningCSS  3011/3548                 4387/0
markerPDF     165/78                    270/0
pandoc        2276/2276                 403/0
quadrable     55/55                     260/0
rclone        489/1601                  484/0
Readability   1579/1984                 148/0
syncthing     658/658                   9170/0
```

## Findings

1. **Critical - the checkout is still not stable enough for repo-wide acceptance evidence.**
   - Paths: `tools/run-tests.php`, `progress.md`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:29` small reviewable committed slices,
     `goal.md:48` verify and commit before reassignment, and `goal.md:49`
     honest repo-wide tests/static checks.
   - Evidence: tracked dirty rows moved `215 -> 216`, untracked-inclusive
     status rows moved `23162 -> 23165`, and dirty shortstat moved from
     `215 files changed, 183147 insertions(+), 22908 deletions(-)` to
     `216 files changed, 183189 insertions(+), 22908 deletions(-)` during
     this audit. The first exact root gate matched `1774605 php
     tools/run-tests.php`; the PID exited before owner sampling, and later
     empty gates occurred only after the dirty aggregate had already moved.
     Any root result produced from this checkout should remain slice-scoped
     unless the integrator proves a frozen tested snapshot.

2. **Critical - the project remains a broad unaccepted dirty batch, not a sequence of small slices.**
   - Paths: `lanes/difftastic/lane-status.json`,
     `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`,
     `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/pandoc/lane-status.json`, `lanes/quadrable/lane-status.json`,
     `lanes/rclone/lane-status.json`, `lanes/readability/lane-status.json`,
     `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:36`, and
     `goal.md:48` require small, correct, verified, committed slices.
   - Evidence: current lane statuses still say `pending`, `uncommitted`, or
     root/integrator acceptance pending for nearly every active lane. Recent
     history is rejection/audit led after the accepted markerPDF ASCIIHex
     slice: `6bd136e9` rejected Syncthing, `6d25aa9c` rejected stale
     markerPDF, and no broad lane batch has been converted into accepted
     project progress. Focused green lane evidence is not a substitute for
     owner-free, reduced dirty scope plus aggregate verification.

3. **High - `porting.html` is an accepted-snapshot dashboard, not current coordination for the live worktree.**
   - Paths: `porting.html:34-38`, `porting.html:56-67`,
     `porting-summary.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require visible
     coordination by denominator, mapped tests, PHP pass/fail, phase, audit,
     blocker, and commit.
   - Evidence: the dashboard honestly labels snapshot `main 5e46840f9573`
     generated `2026-05-24 20:23:24 UTC`, but live metadata has moved beyond
     it. Examples: Difftastic dashboard `240/586` mapped versus current
     `1148/1288`; Dolt dashboard `303/613` versus current `613/613`;
     Gitoxide dashboard `1432/2877` versus current `1472/2877`; libsqlite
     dashboard `210/1589` versus current `218/1589`; LightningCSS dashboard
     `886/3532` versus current `3011/3548`; rclone dashboard denominator
     `2553` versus current manifest denominator `1601`; Syncthing dashboard
     `324` PHP passes versus current status `9170`. The page is useful as an
     accepted baseline, but it must not be read as live progress for dirty
     lane handoffs.

4. **High - support-library coverage is visible but still backlog-only, and one current Syncthing rich slice lacks a reusable URL row.**
   - Paths: `dependency-backlog.json:1-220`, `porting.html:71-129`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: the support-library directives requiring a
     bounded native component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence,
     malformed/corrupt cases, and no whole applications or hidden shell-outs;
     also `goal.md:30` and `goal.md:35`.
   - Evidence: `dependency-backlog.json` has 37 rows and 0 active bounded
     support ports. The tracker now covers all 12 base tools at a row level,
     but none has accepted support manifests, PHP ledgers, malformed/corrupt
     evidence, activation records, or bounded install-attempt notes. Rclone is
     accumulating lane-local WebDAV LOCK/If/COPY/MOVE/DELETE/MKCOL/PUT
     behavior while `webdav-protocol-core` stays inactive. Syncthing now
     records global discovery URL query construction/query encoding, but
     `url-percent-encoding-core` lists rclone, gitoxide, esbuild,
     lightningcss, and readability only; Syncthing URL/query evidence must
     remain lane-local unless that row is extended behind an accepted gate
     with malformed query and percent-encoding cases.

5. **High - Pandoc rich document-conversion status is overstated by manifest coverage.**
   - Paths: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/lane-status.json`, `dependency-backlog.json:7-220`,
     `porting.html:63`, `porting.html:93-127`.
   - Goal requirement at risk: `goal.md:12` requires a document conversion
     kernel with Markdown, HTML, WXR, EPUB/PDF-oriented intermediate forms,
     and WordPress block output; `goal.md:35-40` require meaningful upstream
     parity, edge cases, and explicit blockers.
   - Evidence: the live Pandoc manifest samples `2276/2276` mapped and lane
     status reports `403/0` PHP evidence, but the full Haskell upstream runner
     remains unexecuted. Required rich areas are only inactive support rows:
     DOC via `legacy-doc-cfb-core`; DOCX/OpenXML via `docx-openxml-core`;
     PDF input/text extraction via `pdf-text-dictionary-core`; PDF output
     handoff via `pandoc-pdf-engine-handoff-core`; EPUB via
     `epub3-package-core`; ODT/OpenDocument via `odf-open-document-core`;
     templates via `pandoc-doctemplates-core`; citations via
     `citation-bibliography-csl-core`; math via `math-tex-conversion-core`;
     tables via `table-geometry-core`; package containers via
     `shared-zip-package-core`; XML/HTML via `xml-html5-dom-core`;
     Unicode/charset via `unicode-text-repair-width` and
     `charset-encoding-core`; JSON/YAML metadata via
     `json-json5-document-core` and `yaml-metadata-core`; and
     archive/compression via `archive-compression-streams`. None of those rows
     has accepted support-suite evidence yet.

6. **High - markerPDF still mixes denominator units and external-runtime planning with native extraction evidence.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/lane-status.json`,
     `lanes/markerpdf/src/ChunkConversionPlanner.php`,
     `lanes/markerpdf/src/MarkerRuntimePlanner.php`, `porting.html:62`,
     `dependency-backlog.json`.
   - Goal requirement at risk: `goal.md:9` PDF-to-structured-content
     extraction, `goal.md:25` real upstream benchmark denominator, and
     `goal.md:30` no wrappers/shell-outs/external engines as progress.
   - Evidence: markerPDF has an accepted native ASCIIHex stream-filter slice,
     but its manifest still reports `165/78` mapped, comparing semantic
     mappings to a tracked-path denominator. Its manifest also records
     Streamlit/runtime planning, chunk conversion command planning, model
     loader planning, supplied OCR/layout/table/equation handoffs, and
     benchmark archive inspection. Those can be useful boundaries, but they
     are not full native PDF conversion progress until bounded PDF text,
     OCR/layout, table, ZIP/archive, and image/render support rows have their
     own upstream/spec denominators and malformed/corrupt evidence.

7. **Medium - manifest, status, and dashboard count units are still inconsistent.**
   - Paths: `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/libsqlite/lane-status.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/lane-status.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`, `porting.html:56-67`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and `goal.md:45`
     require durable coordination by upstream denominator, mapped tests, PHP
     pass/fail counts, and progress.
   - Evidence: libsqlite current manifest says `218/1589` mapped while status
     shows `217/0`; rclone manifest says `489/1601` while status says
     `484/0`; markerPDF maps more behaviors than its path denominator
     (`165/78`). Status `phpPass` values are sometimes assertions, sometimes
     behavior cases, sometimes test-file ledgers, and dashboard values come
     from an older accepted snapshot. Percentages cannot be compared safely
     until upstream denominator units, mapped behavior units, PHP assertions,
     PHP behavior cases, and accepted commit state are split explicitly.

8. **Medium - shell-out and bridge-adjacent evidence needs stricter quarantine from progress credit.**
   - Paths: `lanes/gitoxide/tests/FetchV2SessionTest.php`,
     `lanes/gitoxide/tests/GitIndexTest.php`,
     `lanes/gitoxide/tests/GitUrlTest.php`,
     `lanes/gitoxide/src/SshReceivePackTransport.php`,
     `lanes/gitoxide/src/GitFilterDriver.php`,
     `lanes/gitoxide/src/CredentialProgram.php`,
     `lanes/difftastic/tests/TokenDifferTest.php`,
     `lanes/markerpdf/src/ChunkConversionPlanner.php`,
     `lanes/markerpdf/src/MarkerRuntimePlanner.php`.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` allow bridge code
     only as temporary fixture/oracle tooling and disallow shell-outs as native
     implementation progress.
   - Evidence: current tests and source include `proc_open`-based Git oracle
     tests, `shell_exec` calls to run PHP example scripts, command planners for
     Git SSH/filter/credential behavior, and markerPDF runtime/chunk command
     planners. Some of this may be acceptable as explicit oracle or planning
     scaffolding, but it should not inflate native implementation counts or
     support-library progress unless the manifest marks it as temporary
     oracle tooling and the native path remains independently tested.

## Required Next Intervention

Freeze writers/status publishers long enough for two stable polls, then accept
or reject exactly one owner-free reduced lane batch whose dirty files match its
evidence. Normalize that lane's manifest/status count units before publishing
it, regenerate `progress.md`, `porting.html`, and `porting-summary.json` from
the accepted commit, and only then run one serialized no-argument
`php tools/run-tests.php` after `pgrep -af '^php tools/run-tests\.php$'` is
empty. Keep support-library rows inactive until a base-lane rich slice is
accepted or blocked on a bounded dependency component; extend
`url-percent-encoding-core` for Syncthing only behind an accepted discovery-URL
gate.
