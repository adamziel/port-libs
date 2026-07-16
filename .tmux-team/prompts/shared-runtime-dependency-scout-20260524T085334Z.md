You are one scout in the supervised native PHP porting team at `/home/claude/port-libs`.

This session is audit-only. Do not edit implementation files, lane files, `dependency-backlog.json`, `progress.md`, `porting.html`, or `porting-summary.json`. Do not stage, commit, push, reset, or revert. Your owned output is:

- `audits/shared-runtime-dependency-scout-20260524T085334Z.md`

Objective context:

The supervisor received a directional nudge: for every tool, make sure important optional upstream libraries needed for essential rich function are represented and eventually ported as bounded native PHP components. Reuse support libraries across tools where relevant. Do not port whole applications, service wrappers, live cloud/provider integrations, parser-generator runtimes, converter shell-outs, or external CLIs as progress. Support-library rows need real upstream/spec denominators and as much full test-suite evidence as can be run honestly.

Assigned task:

Audit non-document shared support-library coverage across esbuild, LightningCSS, rclone, Syncthing, gitoxide, difftastic, libsqlite, Dolt, and Quadrable. Determine whether `dependency-backlog.json` has enough bounded rows for essential optional libraries and cross-lane reuse. Identify missing rows, rows that should be reprioritized, and rows whose activation gates should be sharpened.

Ground truth to inspect:

- `goal.md`
- `progress.md`, especially `Auxiliary Dependency Backlog`
- `dependency-backlog.json`
- `audits/support-library-progress-tracker-20260524T083724Z.md`
- `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json` and `lanes/esbuild/lane-status.json`
- `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json` and `lanes/lightningcss/lane-status.json`
- `lanes/rclone/UPSTREAM_TEST_MANIFEST.json` and `lanes/rclone/lane-status.json`
- `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json` and `lanes/syncthing/lane-status.json`
- `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json` and `lanes/gitoxide/lane-status.json`
- `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json` and `lanes/difftastic/lane-status.json`
- `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json` and `lanes/libsqlite/lane-status.json`
- `lanes/dolt/UPSTREAM_TEST_MANIFEST.json` and `lanes/dolt/lane-status.json`
- `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json` and `lanes/quadrable/lane-status.json`

Rules:

- Do not read, print, copy, or dump secret values. Do not inspect process environments, credential stores, provider config files, OAuth/browser auth state, cloud remotes, or secret-bearing inputs.
- Avoid live-service provider tests and provider configs. Keep rclone/Syncthing analysis local-only and spec/fixture based.
- Use bounded local reads and `jq`; do not run full root tests or dashboard generation.
- If local manifests are insufficient, use targeted public upstream documentation/source metadata only. Keep quotes short and cite URLs in the audit if used.
- Treat optional upstream libraries as in scope only when they unlock essential runtime/protocol/data-format behavior. Keep every proposed project at the smallest useful native component boundary.
- Do not propose activating all dependency projects at once. Recommend priority order and the concrete base-lane gate that should open each one.
- Every proposed new or changed tracker row must include: id, neededBy lanes, essential capability, scope boundary, activation gate, upstream/spec denominator, expected PHP evidence, malformed/corrupt cases where relevant, reuse notes, and explicit no-shell-out/no-live-service/no-whole-application exclusions.
- Record when existing rows are sufficient and should not be duplicated.

Completion criteria:

1. Write `audits/shared-runtime-dependency-scout-20260524T085334Z.md` with:
   - current tracker coverage summary;
   - recommended additions, if any;
   - recommended priority/gate changes, if any;
   - explicit rejects for live services, external CLIs, parser-generator runtimes, and whole applications;
   - the exact local files inspected;
   - checks run.
2. Run `jq empty dependency-backlog.json lanes/esbuild/UPSTREAM_TEST_MANIFEST.json lanes/esbuild/lane-status.json lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json lanes/lightningcss/lane-status.json lanes/rclone/UPSTREAM_TEST_MANIFEST.json lanes/rclone/lane-status.json lanes/syncthing/UPSTREAM_TEST_MANIFEST.json lanes/syncthing/lane-status.json lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json lanes/gitoxide/lane-status.json lanes/difftastic/UPSTREAM_TEST_MANIFEST.json lanes/difftastic/lane-status.json lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json lanes/libsqlite/lane-status.json lanes/dolt/UPSTREAM_TEST_MANIFEST.json lanes/dolt/lane-status.json lanes/quadrable/UPSTREAM_TEST_MANIFEST.json lanes/quadrable/lane-status.json`.
3. Run `git diff --check -- audits/shared-runtime-dependency-scout-20260524T085334Z.md`.

When done, report only:

- artifact path;
- key recommended tracker changes;
- checks run;
- unresolved blockers.
