# Independent Audit - 2026-05-24T20:23Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`,
`audits/integration-status.md`, and recent Git history through
`5e46840f Integrate markerPDF ASCIIHex stream filter slice`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T20:17:45Z -> 2026-05-24T20:23:12Z
HEAD during audit: 65f206b33220 -> 5e46840f9573
recent history: 5e46840f Integrate markerPDF ASCIIHex stream filter slice; 65f206b3 Refresh independent audit status; 1d3c64bf Refresh independent audit status; 958ad536 Integrate markerPDF Tm horizontal scale slice; 4756c15a Record esbuild handoff rejection
tracked status rows: 216 -> 222
default status rows including untracked: 22855 -> 22994
dirty shortstat: 216 files changed, 181840 insertions(+), 22982 deletions(-) -> 220 files changed, 182648 insertions(+), 23102 deletions(-)
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
dependency backlog: 37 rows, 0 active (blocked 1, candidate 25, deferred 11)
root run by this audit: not started
```

Required exact pre-root process gate:

```text
2026-05-24T20:17:45Z pgrep -af '^php tools/run-tests\.php$': 1561076 php tools/run-tests.php
2026-05-24T20:17:45Z owner evidence: 1561076 claude 1493028 Rs 00:45 php tools/run-tests.php
2026-05-24T20:18:37Z pgrep -af '^php tools/run-tests\.php$': 1561076 php tools/run-tests.php
2026-05-24T20:18:37Z owner evidence: 1561076 claude 1493028 Rs 01:31 php tools/run-tests.php
2026-05-24T20:23:12Z pgrep -af '^php tools/run-tests\.php$': no rows
later handoff sample: 1654694 php tools/run-tests.php; ps owner sampling missed it because the process exited before `ps -p 1654694`
latest recheck: no rows
```

I did not start `php tools/run-tests.php`. The initial exact root gate was
occupied, `HEAD` moved during the audit to integrate markerPDF ASCIIHex, and
the dirty aggregate changed during and after the active root window. A root
result from this churn should not be used as a general acceptance baseline
unless the integrator proves the tested snapshot was frozen.

Latest sampled manifest/status counts. These are samples from a moving
worktree, not an acceptance ledger:

```text
lane          manifest mapped/total     status phpPass/phpFail
difftastic    1139/1279                 3752/0
dolt          613/613                   460/0
esbuild       222/2567                  222/0
gitoxide      1471/2877                 7603/0
libsqlite     217/1589                  216/0
LightningCSS  3010/3548                 4382/0
markerPDF     165/78                    270/0
pandoc        2276/2276                 402/0
quadrable     55/55                     260/0
rclone        484/1601                  484/0
Readability   1578/1984                 147/0
syncthing     658/658                   9139/0
```

## Findings

1. **Critical - repo-wide root evidence is not currently a stable acceptance baseline.**
   - Paths: `tools/run-tests.php`, `progress.md:49-52`,
     `lanes/markerpdf/lane-status.json:10`, `lanes/*/lane-status.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49` require committed, verified slices and honest repo-wide
     test evidence.
   - Evidence: the exact root gate matched PID `1561076 php
     tools/run-tests.php`, owned by `claude`, at the initial samples. During
     this audit window `HEAD` moved from `65f206b33220` to `5e46840f9573`,
     default status rows moved `22855 -> 22994`, and dirty shortstat moved
     from `216 files changed, 181840 insertions(+), 22982 deletions(-)` to
     `220 files changed, 182648 insertions(+), 23102 deletions(-)`.
     MarkerPDF now records an integration-owned root pass at
     `lanes/markerpdf/lane-status.json:10`; that may be valid for the held
     markerPDF ASCIIHex slice, but it does not validate the broader moving
     dirty aggregate.

2. **Critical - the repository is still an accumulated dirty batch, not small reviewable progress.**
   - Paths: `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`,
     `audits/integration-status.md`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:36`, and
     `goal.md:48` require small, reviewable slices with passing tests,
     commits, cleanup, and new assignment only after verification.
   - Evidence: at the latest sample the checkout had `222` tracked dirty rows
     and `22994` default status rows including untracked files. Current lane
     metadata repeatedly says `pending`, `not committed`, or `uncommitted`,
     even when focused tests are green. Recent history is still dominated by
     audit/status/rejection/integration churn rather than a clean sequence of
     accepted lane commits.

3. **Critical - `porting.html` and `porting-summary.json` are materially stale.**
   - Paths: `porting.html:32-38`, `porting.html:56-67`,
     `porting-summary.json:2-8`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to show current denominator, mapped tests, PHP pass/fail,
     phase, audit, blocker, and commit.
   - Evidence: the dashboard still publishes source snapshot
     `84a9d33d56d3`, generated `2026-05-24 19:49:49 UTC`, while current
     `HEAD` is `5e46840f9573`. The dashboard shows Difftastic `240/586`
     mapped while current metadata is `1139/1279`; Gitoxide `1432/2877` while
     current is `1471/2877`; LightningCSS `886/3532` while current is
     `3010/3548`; rclone denominator `2553` while current is `1601`; and
     Syncthing `324` PHP passes while current status reports `9139`.

4. **High - Pandoc's rich document-conversion status is overstated.**
   - Paths: `goal.md:12`, `lanes/pandoc/lane-status.json:4-14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12-16`,
     `dependency-backlog.json:7`, `dependency-backlog.json:81`,
     `dependency-backlog.json:129`, `dependency-backlog.json:145`,
     `dependency-backlog.json:163`, `dependency-backlog.json:179`,
     `dependency-backlog.json:214`, `dependency-backlog.json:233`,
     `dependency-backlog.json:256`, `dependency-backlog.json:272`,
     `dependency-backlog.json:322`, `dependency-backlog.json:340`,
     `dependency-backlog.json:365`, `dependency-backlog.json:391`,
     `dependency-backlog.json:413`, `dependency-backlog.json:629`.
   - Goal requirement at risk: `goal.md:12` requires a conversion kernel with
     Markdown, HTML, WXR, EPUB/PDF-oriented intermediate forms, and WordPress
     block output; `goal.md:35-40` require real denominators, edge behavior,
     and explicit blockers.
   - Evidence: Pandoc reports `estimatedProgress: 99` and `mapped: 2276` of
     `total: 2276`, but current PHP evidence is only `402` focused behavior
     tests and the full Haskell runner remains unexecuted. Required rich
     capabilities - DOC, DOCX/OpenXML, PDF input/output handoff, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package
     containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax
     highlighting, and archive/compression - are visible only as inactive
     backlog rows without accepted support manifests or dependency ledgers.

5. **High - support-library coverage is still not first-class, and one current slice exposes a missing reuse route.**
   - Paths: `dependency-backlog.json:1-78`,
     `dependency-backlog.json:532`, `lanes/rclone/lane-status.json:9-14`,
     `lanes/syncthing/lane-status.json:5-14`.
   - Goal requirement at risk: the support-library directives require a
     bounded native component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence,
     malformed/corrupt cases where relevant, and bounded install-attempt
     notes before dependency progress credit.
   - Evidence: the backlog has `37` rows and `0` active bounded support ports.
     Rclone is accumulating WebDAV DELETE/MKCOL/PUT/COPY/MOVE lane-local
     behavior while `webdav-protocol-core` remains inactive. Syncthing's
     current slice implements global discovery URL query construction using
     Go-style query escaping and malformed percent-query handling, but
     `url-percent-encoding-core` lists rclone, gitoxide, esbuild,
     lightningcss, and readability only; Syncthing is not named in that shared
     support route. Those claims must remain lane-local until a support row is
     extended or activated with its own denominator and malformed URL/query
     cases.

6. **High - markerPDF has an accepted ASCIIHex slice, but the lane still mixes denominator units and supplied/model boundaries.**
   - Paths: `goal.md:9`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12-20`,
     `lanes/markerpdf/lane-status.json:4-14`,
     `dependency-backlog.json:272`, `dependency-backlog.json:322`.
   - Goal requirement at risk: `goal.md:30` and `goal.md:35-40` say
     wrappers, shell-outs, whole applications, runtime launchers, and
     plan-only behavior must not count as native implementation progress.
   - Evidence: markerPDF now records `5e46840f Integrate markerPDF ASCIIHex
     stream filter slice` and a slice-scoped root pass, but the manifest still
     reports `mapped: 165` against `total: 78`, mixing repository-path
     inventory with focused semantic mappings. The lane still excludes full
     benchmarks/app parity because the real pipeline needs Poetry plus
     pdftext, pypdfium2, Surya/OCR, tabled-pdf, Texify, Torch, Nougat
     comparison tooling, model downloads, Streamlit/FastAPI/Uvicorn, and
     external PDF/OCR tooling. The inactive `pdf-text-dictionary-core` and
     `table-geometry-core` rows mean searchable-PDF/table claims remain
     lane-local supplied-boundary evidence, not reusable support progress.

7. **High - high percentages hide unaccepted root/integrator blockers.**
   - Paths: `lanes/dolt/lane-status.json:4-14`,
     `lanes/gitoxide/lane-status.json:4-14`,
     `lanes/libsqlite/lane-status.json:4-14`,
     `lanes/lightningcss/lane-status.json:4-14`,
     `lanes/pandoc/lane-status.json:4-14`,
     `lanes/quadrable/lane-status.json:4-14`,
     `lanes/rclone/lane-status.json:4-14`,
     `lanes/syncthing/lane-status.json:4-14`.
   - Goal requirement at risk: `goal.md:35` says passing tests are not
     enough; each lane also needs meaningful upstream fixture parity,
     edge/error coverage, docs/examples, and WordPress scenarios.
   - Evidence: Dolt, Pandoc, Quadrable, rclone, Syncthing, LightningCSS, and
     libsqlite all report `98-99%` or broad green focused evidence while
     their blocker fields still say aggregate root verification and
     supervisor/integrator acceptance are pending. These percentages should be
     read as lane-local confidence, not accepted project progress.

8. **Medium - manifest/status count units remain inconsistent across lanes.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12-20`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12-16`,
     `lanes/rclone/lane-status.json:4-6`, `porting.html:62-65`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:45` require durable coordination by upstream denominator,
     mapped tests, and PHP pass/fail counts.
   - Evidence: markerPDF maps semantics against a repository-path
     denominator, Pandoc maps file/artifact inventory to behavior claims,
     rclone has moved from a `2553` repository-file dashboard denominator to
     a `1601` Go test-function denominator, and status PHP pass counts are
     behavior/assertion ledgers rather than upstream denominator counts. The
     project cannot compare lane percentages until these units are normalized
     or explicitly separated.

## Required Next Intervention

Treat the recent root results as slice-scoped only unless the integrator can
prove the tested snapshot was frozen. Freeze writers/status publishers, accept
or reject one owner-free reduced lane batch whose dirty files exactly match its
evidence, normalize that lane's manifest/status count units, and regenerate
`progress.md`, `porting.html`, and `porting-summary.json` from the accepted
commit. Extend or activate support rows only behind an accepted base-lane gate;
in particular, route Syncthing URL query escaping through a bounded
URL/percent-encoding support row if the slice is meant to be reusable. Run one
serialized no-argument `php tools/run-tests.php` only after
`pgrep -af '^php tools/run-tests\.php$'` is empty and the snapshot is stable
across two polls.
