# Independent Audit - 2026-05-24T15:19Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12
`lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git history
through `47b35a65 Record integration hold status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 15:13-15:19
observed HEAD movement after audit edit: b03fca974352 -> 712f22ba2551 -> 47b35a65a3e9
recent history: 47b35a65 Record integration hold status; 712f22ba Record integration hold status; b03fca97 Refresh independent audit status; 3e616649 Record integration hold status
tracked dirty rows: 329 -> 331
default status rows including untracked: 19012 -> 19080
git diff --shortstat moved during audit sampling: 329 files changed, 254038 insertions(+), 32160 deletions(-) -> 331 files changed, 254564 insertions(+), 32334 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred), updated 2026-05-24 12:29:10 UTC
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started
```

Required pre-root process gate:

```text
15:13:01Z pgrep -af '^php tools/run-tests\.php$': no rows
15:13:52Z pgrep -af '^php tools/run-tests\.php$': no rows
15:16:38Z pgrep -af '^php tools/run-tests\.php$' matched PID 544441: php tools/run-tests.php
15:17:01Z pgrep -af '^php tools/run-tests\.php$': no rows; PID 544441 exited before ps owner sampling
15:18:35Z pgrep -af '^php tools/run-tests\.php$': no rows
15:19:43Z pgrep -af '^php tools/run-tests\.php$': no rows
```

I did not start `php tools/run-tests.php`. The exact no-argument root gate was
briefly occupied after the first audit edit, and even when it cleared the
checkout was not stable enough for attributable evidence: `HEAD`, dirty counts,
shortstat, and lane metadata changed while sampled. Syncthing lane metadata
advanced while the audit was reading it (`phpPass` moved from 8305 in the prior
audit to 8336, and the status text now carries a future `15:21 UTC` timestamp
relative to an earlier `15:13 UTC` shell sample).

Current manifest/status sample versus the published dashboard:

```text
lane          current status/manifest                 dashboard
difftastic    3449 pass, 969/1182 mapped              3245 pass, 851/1077
dolt          431 pass, 613/613 mapped                425 pass, 613/613
esbuild       450 pass, 450/2567 mapped               429 pass, 429/2567
gitoxide      7339 pass, 2877/2877 mapped             7152 pass, 2877/2877
libsqlite     363 pass, 363/1589 mapped               348 pass, 349/1589
LightningCSS  4168 pass, 2850/3548 mapped             4065 pass, 2765/3548
markerPDF     500 pass, manifest 364/413 mapped       484 pass, 347/396
pandoc        377 pass, manifest 2057/2276 mapped     362 pass, 1891/2276
quadrable     241 pass, 55/55 mapped                  232 pass, 55/55
rclone        949 pass, 949/1601 mapped               906 pass, 906/1601
readability   3693 assertions, 1984/1984 mapped       3545 pass, 1984/1984
syncthing     8336 pass, 658/658 mapped               7902 pass, 658/658
```

## Findings

1. **Critical - the repository is still a moving dirty aggregate, not an acceptance checkpoint.**
   - Paths: `goal.md`, `progress.md`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: "Commit small, reviewable slices with passing
     tests" and maintain current owner/session, blocker, and latest commit per
     lane.
   - Evidence: all 12 lane status files still record `pending`,
     `uncommitted`, or `not committed` latest-commit prose. The worktree has
     331 tracked dirty rows and 19080 status rows including untracked files.
     `HEAD` and the shortstat changed during this audit, and recent history remains
     audit/integration-hold dominated rather than accepted implementation
     commits.

2. **Critical - root verification remains unavailable as trustworthy acceptance evidence.**
   - Paths: `tools/run-tests.php`, `progress.md`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: "Periodically run repo-wide tests and static
     checks. Record failures honestly."
   - Evidence: the exact required gate briefly matched PID `544441`
     (`php tools/run-tests.php`) at 15:16:38 UTC, then cleared before owner
     sampling. The tree was still moving before and after that, so starting a
     new no-argument root harness would create another non-attributable result.

3. **High - `porting.html` and `porting-summary.json` are stale against every lane.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:38`, `porting-summary.json:3`,
     `porting-summary.json:4`.
   - Goal requirement at risk: the dashboard must show current benchmark
     source, upstream denominator, mapped tests, PHP pass/fail, scenarios,
     phase, audit, current work, blocker, and commit.
   - Evidence: dashboard artifacts still publish source `89260857cc71` from
     `2026-05-24 12:29:46 UTC`, while current observed `HEAD` is
     `47b35a65a3e9` and
     every lane's status/manifest counts exceed the rendered row. Several
     dashboard commit cells are truncated prose such as `not com`, `uncommi`,
     and `pending`.

4. **High - support-library coverage is still backlog-only, not first-class lane-granular execution.**
   - Paths: `dependency-backlog.json:3`, `dependency-backlog.json:4`,
     `dependency-backlog.json:7`, `dependency-backlog.json:81`,
     `dependency-backlog.json:129`, `dependency-backlog.json:145`,
     `dependency-backlog.json:163`, `dependency-backlog.json:179`,
     `dependency-backlog.json:195`, `dependency-backlog.json:214`,
     `dependency-backlog.json:233`, `dependency-backlog.json:256`,
     `dependency-backlog.json:272`, `dependency-backlog.json:322`,
     `dependency-backlog.json:340`, `dependency-backlog.json:365`,
     `dependency-backlog.json:629`, `porting.html:72`.
   - Goal requirement at risk: support libraries need bounded native PHP
     scope, activation gate, dependency-specific upstream/spec denominator,
     mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where
     relevant, and as much upstream/full-suite evidence as can actually run.
   - Evidence: `dependency-backlog.json` has 37 rows and 0 active bounded
     support ports. Pandoc's required DOC, DOCX/OpenXML, PDF handoff/text
     extraction and output, EPUB, ODT/OpenDocument, templates, citations, math,
     tables, package containers, XML/HTML, Unicode/charset, JSON/YAML metadata,
     syntax highlighting, and archive/compression areas are routed to gated
     rows, but none has its own support manifest, PHP ledger,
     malformed/corrupt evidence, accepted base-lane activation record, or
     bounded `sudo -n` install-attempt/ruled-out note.

5. **High - Pandoc rich conversion is routed on paper but not proven as a native document kernel.**
   - Paths: `goal.md`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:332`,
     `lanes/pandoc/lane-status.json:5`,
     `lanes/pandoc/lane-status.json:12`,
     `dependency-backlog.json:81`, `dependency-backlog.json:129`,
     `dependency-backlog.json:145`, `dependency-backlog.json:163`,
     `dependency-backlog.json:214`, `dependency-backlog.json:233`,
     `dependency-backlog.json:256`.
   - Goal requirement at risk: Pandoc must become a document conversion kernel
     with a shared AST and readers/writers for Markdown, HTML, WXR, EPUB/PDF
     oriented forms, and WordPress output.
   - Evidence: the lane now reports `2049/2276` mapped and `377` local checks,
     but full Haskell runner parity remains unexecuted, and the essential rich
     dependencies remain inactive backlog rows rather than manifest-backed
     native components with spec/upstream pass/fail ledgers.

6. **High - markerPDF still mixes useful native PDF work with non-progress runtime/application boundaries.**
   - Paths: `goal.md`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: wrappers, bridge calls, shell-outs, whole
     applications, and external converter/runtime plans cannot count as native
     deliverables.
   - Evidence: the current native `PdfTextExtractor` slice is useful
     (manifest `364/413` mapped, `500` focused behavior tests), but the blocker/status
     surface still includes Streamlit/FastAPI/Uvicorn, Poetry, Python
     multiprocessing/model workers, pdftext, OCRMyPDF/Tesseract/Ghostscript,
     pypdfium/PIL, Pandoc/XeLaTeX, Texify/Nougat, benchmark apps, and publish
     workflows. Those must stay blockers, supplied-result contracts, or
     explicit non-goals until replaced by bounded native support components.

7. **High - dependency-adjacent lane-local helpers are expanding ahead of shared support gates.**
   - Paths: `lanes/rclone/lane-status.json:11`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/rclone/src/GzipReader.php:7`,
     `lanes/rclone/src/VfsWebDavCompression.php:8`,
     `lanes/rclone/src/VfsZipArchive.php:13`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:9`,
     `dependency-backlog.json:54`, `dependency-backlog.json:629`.
   - Goal requirement at risk: dependency expansion must be bounded, gated,
     tested, and shared across lanes when it implements an essential rich
     function.
   - Evidence: rclone has lane-local WebDAV, gzip, and ZIP-like helpers while
     `webdav-protocol-core` and `archive-compression-streams` remain inactive.
     markerPDF uses `ZipArchive` for benchmark archive inspection while the
     shared package/container row is inactive. These may remain lane scaffolds,
     but they cannot count as support-library progress without their own
     manifests, malformed cases, PHP ledgers, and reuse contracts.

8. **Medium - near-complete percentages overstate accepted parity.**
   - Paths: `porting.html:32`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: passing focused tests are not enough; upstream
     denominators, fixture parity, edge cases, error behavior, docs/examples,
     and hard gaps must remain visible.
   - Evidence: the dashboard reports `98.3%` average progress and most lanes
     report `98-99%`, while every lane remains unaccepted in a dirty worktree,
     root verification is not tied to a frozen snapshot, several full upstream
     runners are static/bounded/unexecuted, and no support-library row is
     active.

9. **Medium - manifest/status schema is too free-form for durable coordination.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/pandoc/lane-status.json:5`,
     `lanes/lightningcss/lane-status.json:13`.
   - Goal requirement at risk: coordination data must be machine-checkable
     across denominator, mapped tests, PHP pass/fail, phase, audit status,
     blocker, and latest commit.
   - Evidence: several `benchmarkDenominator.status` fields are long
     concatenated slice histories, `latestCommit` fields mix prose with stale
     commit references, LightningCSS still names `HEAD 05a6bd1a892f` while the
     observed `HEAD` is `47b35a65a3e9`, and markerPDF/Pandoc status prose lags
     their manifests (`412/363` and `2049` in status text versus manifest
     `413/364` and `2057`). These fields look precise but do not define an
     accepted commit boundary.

## Next Intervention

Freeze lane writers, focused/root runners, dashboard/status publishers,
support-library scouts, capacity rows, and integration-hold writers. Require
two stable polls of `HEAD`, tracked/default status counts, shortstat, the exact
root gate `pgrep -af '^php tools/run-tests\.php$'`, dashboard/dependency
counts, lane status timestamps, and relevant log mtimes. Accept exactly one
owner-free lane batch at a time, normalizing manifest/status schema and commit
fields before claiming progress. Promote support libraries only behind an
accepted base-lane gate or true component blocker, each with its own manifest,
malformed-case evidence, PHP ledger, and bounded install-attempt note.
Regenerate `progress.md`, `porting.html`, and `porting-summary.json` from the
accepted commit, then run one serialized no-argument root harness only if the
exact process gate remains empty on that frozen snapshot.
