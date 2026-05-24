# Independent Audit - 2026-05-24T19:25Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, and
recent Git history through `49b5a511 Record Quadrable handoff rejection`.
I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 19:16-19:25
HEAD moved during audit before this audit/progress-only commit: eebc7e291846 -> 49b5a5114238
recent history: 49b5a511 Record Quadrable handoff rejection; eebc7e29 Refresh independent audit status; 7370ac38 Record libsqlite handoff rejection; 3ebca3ab Record rclone handoff rejection; 836e60b2 Record Gitoxide handoff rejection
branch sample: main...origin/main [ahead 993, behind 68]
default status rows including untracked moved: 21594 -> 21663 -> 21745 -> 21801 -> 21859
dirty shortstat moved: 234 files changed, 210751 insertions(+), 25386 deletions(-) -> 236 files changed, 211047 insertions(+), 25386 deletions(-) -> 238 files changed, 211334 insertions(+), 25636 deletions(-) -> 240 files changed, 211736 insertions(+), 25655 deletions(-) -> 240 files changed, 211929 insertions(+), 25655 deletions(-)
largest dirty scopes: audits 10451 rows, .tmux-team 8888 rows, lanes/difftastic 478, lanes/syncthing 257, lanes/rclone 256, lanes/pandoc 246, lanes/dolt 229, lanes/gitoxide 215, lanes/lightningcss 202, lanes/readability 196
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows, 0 active (blocked 1, candidate 25, deferred 11), updated 2026-05-24 12:29:10 UTC
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started; exact no-argument root gate was occupied by non-audit root PIDs early, clear in one later sample, active again in the final sample, and the tree was still not a frozen accepted snapshot
```

Required exact pre-root process gate:

```text
2026-05-24T19:16:15Z pgrep -af '^php tools/run-tests\.php$': 1160629 php tools/run-tests.php
owner evidence: 1160629 claude 1160532 R+ 00:31 php tools/run-tests.php
2026-05-24T19:18:30Z pgrep -af '^php tools/run-tests\.php$': 1170934 php tools/run-tests.php
owner evidence: 1170934 claude 1165105 Rs 00:29 php tools/run-tests.php
2026-05-24T19:21:19Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T19:23:12Z pgrep -af '^php tools/run-tests\.php$': 1198651 php tools/run-tests.php
owner evidence: 1198651 claude 1198597 R+ 01:30 php tools/run-tests.php
2026-05-24T19:25:12Z pgrep -af '^php tools/run-tests\.php$': 1209921 php tools/run-tests.php
owner evidence: 1209921 claude 1185116 Rs 00:47 php tools/run-tests.php
```

I did not start `php tools/run-tests.php`. The exact no-argument root harness
gate was occupied in earlier samples, clear in one later sample, and active
again in the final sample. The checkout was not stable: `HEAD`, default status
row count, and dirty shortstat all changed during this audit window.

Latest sampled manifest/status counts. These are samples from a moving
worktree, not an acceptance ledger:

```text
lane          manifest mapped/total     status phpPass/phpFail
difftastic    1085/1235                 3684/0
dolt          613/613                   454/0
esbuild       475/2567                  475/0
gitoxide      1445/2877                 7512/0
libsqlite     210/1589                  210/0
LightningCSS  2996/3548                 3952/0
markerPDF     162/78                    267/0
pandoc        2276/2276                 396/0
quadrable     55/55                     256/0
rclone        976/1601                  976/0
Readability   1984/1984                 3868/0
syncthing     658/658                   9007/0
```

## Findings

1. **Critical - the repository is still a live dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:15`, `progress.md:51`,
     `audits/integration-status.md:3-89`, `lanes/*/lane-status.json:12-14`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:36`, and
     `goal.md:48` require small reviewable slices, passing tests, and verified
     handoff cleanup before progress is committed.
   - Evidence: `HEAD` moved from `eebc7e291846` to `49b5a5114238` while this
     audit was running before this audit/progress-only commit. Default status
     rows including untracked files moved
     `21594 -> 21663 -> 21745 -> 21801 -> 21859`,
     and dirty shortstat moved from
     `234 files changed, 210751 insertions(+), 25386 deletions(-)` through
     `236 files changed, 211047 insertions(+), 25386 deletions(-)` and
     `238 files changed, 211334 insertions(+), 25636 deletions(-)` to
     `240 files changed, 211929 insertions(+), 25655 deletions(-)`. Every lane
     still has pending or uncommitted status, and recent integration commits
     are rejections rather than accepted lane output.

2. **Critical - no trustworthy no-argument root acceptance result exists for the current tree.**
   - Paths: `tools/run-tests.php`, `progress.md:51`,
     `audits/integration-status.md:23-36`, `lanes/*/lane-status.json:12-14`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording; this run also required the exact duplicate
     root gate before any root run.
   - Evidence: the exact root gate matched non-audit PID `1160629` with owner
     evidence `1160629 claude 1160532 R+ 00:31 php tools/run-tests.php`, then
     later matched non-audit PID `1170934` with owner evidence
     `1170934 claude 1165105 Rs 00:29 php tools/run-tests.php`. A later gate
     sample was clear, but the final sample matched active non-audit PID
     `1198651`, then the post-amend sample matched active non-audit PID
     `1209921` with owner evidence
     `1209921 claude 1185116 Rs 00:47 php tools/run-tests.php`. I did not
     start the root harness because the tree was moving and the final sampled
     gate was occupied. No root result from this window can be treated as a
     serialized frozen-snapshot acceptance result.

3. **Critical - `porting.html` and `porting-summary.json` are materially stale.**
   - Paths: `porting.html:32-38`, `porting.html:56-67`,
     `porting-summary.json:2-8`, `porting-summary.json:11-120`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to track denominator, mapped tests, PHP pass/fail, WordPress
     scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: both dashboard files still publish source commit
     `89260857cc71`, generated `2026-05-24 12:29:46 UTC`, while current sampled
     `HEAD` is `49b5a5114238`. Current lane metadata differs from the
     dashboard across the table: Difftastic is now `1085/1235` and `3684`
     status pass units versus dashboard `851/1077` and `3245`; Gitoxide is
     `1445/2877` and `7512` versus dashboard `2877/2877` and `7152`; libsqlite
     is `210/1589` and `210` versus dashboard `349/1589` and `348`; markerPDF
     is `162/78` and `267` versus dashboard `347/396` and `484`; Pandoc is
     `396` pass units versus dashboard `362`; Dolt is `454` status pass units
     versus dashboard `425`; rclone is `976/1601` and `976` status pass units
     versus dashboard `906/1601` and `906`; Readability is `3868` versus
     dashboard `3545`; Syncthing is `9007` versus dashboard `7902`.

4. **High - recent integration history confirms the workers are still handing off accumulated multi-slice patches.**
   - Paths: `audits/integration-status.md:3-89`,
     `audits/integration-status.md:91-170`,
     `audits/integration-status.md:172-252`, recent Git history.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35-40`, and
     `goal.md:48` require focused slices, meaningful fixture parity, explicit
     blockers, verification, and cleanup before assigning the next task.
   - Evidence: the newest Quadrable rejection says the proof-marker slice had
     focused evidence but the dirty lane state included `42` tracked files and
     `126` total rows across older BLAKE2s/key/store/sync/iterator/varint and
     command examples/tests. The libsqlite rejection found a narrow claim but
     `13` tracked files plus `164` untracked files from older storage/WAL/JSON
     and B-tree work. The rclone rejection found status/manifest claiming
     WebDAV PUT body-copy-error while the tracked patch was a OneDrive batch and
     untracked rclone scope was `247` files. Gitoxide and markerPDF were
     rejected for the same accumulated-scope pattern.

5. **High - manifest/status ledgers are still non-atomic and in inconsistent units.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:12-22`,
     `lanes/gitoxide/lane-status.json:5-13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12-20`,
     `lanes/markerpdf/lane-status.json:5-13`,
     `porting-summary.json:11-120`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:44-45` require durable coordination by upstream denominator,
     mapped tests, PHP pass/fail, current work, blocker, and commit.
   - Evidence: Gitoxide manifest says `1445/2877` mapped while older dashboard
     still says `2877/2877`, and the lane is a reduced discovery handoff rather
     than full Gitoxide parity. markerPDF now has a numeric denominator schema,
     but it reports `162/78` mapped, so its numerator exceeds its repository
     path denominator and cannot be compared as suite progress. Difftastic
     reports `1085` mapped artifacts while status reports `3684` assertion-like
     pass units, and Dolt status pass units moved during the audit while the
     manifest mapped denominator stayed fixed. Several status fields count
     assertions, behavior entries, mapped semantics, or upstream files
     interchangeably, which makes the `98-99%` dashboard status misleading.

6. **High - support-library coverage is visible but still not first-class lane-granular work.**
   - Paths: `dependency-backlog.json:1-22`,
     `dependency-backlog.json:81-95`,
     `dependency-backlog.json:129-176`,
     `dependency-backlog.json:179-210`,
     `dependency-backlog.json:214-388`,
     `dependency-backlog.json:391-426`,
     `dependency-backlog.json:629-646`, `porting.html:71-115`,
     `progress.md:17-36`.
   - Goal requirement at risk: `goal.md:35-40` require real denominators,
     meaningful fixture parity, edge-case coverage, honest blockers, and no
     progress credit for wrappers or shell-outs. The latest support-library
     directive additionally requires bounded native components, activation
     gates, dependency-specific upstream/spec denominators, mapped fixtures,
     PHP pass/fail evidence, malformed/corrupt cases where relevant, and
     bounded `sudo -n` install attempts or ruled-out notes before missing
     packages become final blockers.
   - Evidence: the backlog names Pandoc DOC, DOCX/OpenXML, PDF input/output
     handoff, EPUB, ODT/OpenDocument, templates, citations, math, tables,
     package containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax
     highlighting, and archive/compression. It also names important rich
     support for other lanes. But all rows remain `candidate`, `deferred`, or
     one `blocked`; there are still `0` active support rows, no accepted
     support manifests, no dependency-specific PHP ledgers, no malformed/corrupt
     evidence records, no accepted activation records, and no bounded install
     attempt notes. Current lane-local rich slices must not receive
     support-library progress credit.

7. **High - Pandoc remains far short of the original rich conversion-kernel goal despite 99% status language.**
   - Paths: `goal.md:12`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12-16`,
     `lanes/pandoc/lane-status.json:5-14`,
     `dependency-backlog.json:81-95`,
     `dependency-backlog.json:129-176`,
     `dependency-backlog.json:214-388`,
     `dependency-backlog.json:391-426`,
     `dependency-backlog.json:629-646`.
   - Goal requirement at risk: `goal.md:12` requires a document conversion
     kernel with a shared AST plus readers/writers for Markdown, HTML, WXR,
     EPUB/PDF-oriented intermediate forms, and WordPress block output.
   - Evidence: Pandoc records `2276/2276` mapped over a static inventory, but
     full Haskell runner parity remains unexecuted. The current slice explicitly
     excludes upstream Pandoc invocation, network fetches, browser tooling,
     converter shell-outs, PDF processing, ZIP/package parsers, external
     renderers, citation/CSL engines, XML/HTML support-library expansion,
     PlainMath/MathML conversion, TeX math/ref conversion, and broader syntax
     highlighting. Those are not minor optionals; they are central to the
     original document-conversion goal.

8. **High - markerPDF still blends native extraction with runtime/application planning.**
   - Paths: `goal.md:9`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12-20`,
     `lanes/markerpdf/lane-status.json:5-14`,
     `audits/integration-status.md:172-252`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and
     `goal.md:35-40` say wrappers, shell-outs, whole applications, and
     plan-only runtime behavior must not count as native implementation
     progress.
   - Evidence: the reduced `PdfTextExtractor` work is useful and explicitly
     avoids Python/pdftext/PDFium/Poppler/Ghostscript. But the manifest still
     carries Streamlit, FastAPI/Uvicorn, batch/chunk conversion scripts,
     Poetry/package metadata, model loader/planner, OCR/Texify/Surya/Nougat,
     tabled, debug renderer, and benchmark archive planning evidence. Those can
     be inventory or blockers, but not native markerPDF port progress unless
     turned into bounded PHP components with native pass/fail ledgers and
     malformed/corrupt cases.

9. **Medium - libsqlite regressed from a broad advertised native surface to a reduced manifest/status sample.**
   - Paths: `goal.md:10`, `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:12-18`,
     `lanes/libsqlite/lane-status.json:5-14`,
     `audits/integration-status.md:91-170`.
   - Goal requirement at risk: `goal.md:10`, `goal.md:29`, and
     `goal.md:35-38` require a pure PHP SQLite reader/writer with a real
     denominator, explicit slices, and broad-but-honest upstream coverage.
   - Evidence: the current manifest/status sample is only `210/1589` and
     `210` pass units after a rejection that documented a much larger,
     mismatched dirty patch. That is safer than claiming all the older work,
     but it means the previously advertised JSON/storage/B-tree breadth is not
     accepted progress. The next intervention must be a reduced single-slice
     handoff, not more breadth.

10. **Medium - high percentage estimates hide acceptance blockers across all lanes.**
    - Paths: `porting.html:56-67`, `lanes/*/lane-status.json:4-14`.
    - Goal requirement at risk: `goal.md:3`, `goal.md:44-45`, and
      `goal.md:52` require visible honest progress and current blockers in the
      dashboard.
    - Evidence: almost every lane reports `95-99%` progress even while
      `latestCommit` is `pending`, `uncommitted`, or rejected; full upstream
      runner parity is absent or bounded; and root aggregate verification is
      pending. The percentages are not meaningful acceptance percentages until
      they distinguish accepted native PHP behavior from moving dirty work and
      static-inventory coverage.

## Required Next Intervention

Freeze writers/runners/status publishers long enough for two stable polls.
Accept or reject one owner-free reduced lane batch whose dirty files match its
evidence exactly. Normalize manifest/status units for that lane in the same
atomic change, regenerate `porting.html` and `porting-summary.json` from the
accepted commit, then run exactly one serialized no-argument
`php tools/run-tests.php` only if `pgrep -af '^php tools/run-tests\.php$'`
stays empty on that frozen snapshot. Do not activate a support-library row
until the base lane is accepted-ready or accepted-blocked on that exact bounded
component with its own denominator, mapped fixtures, malformed/corrupt cases,
PHP pass/fail ledger, and install-attempt notes where missing packages matter.
