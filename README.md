# Native PHP Port Libraries

This workspace tracks native standard-PHP ports of selected systems libraries for WordPress, Playground, Data Liberation, shared-hosting, and migration workflows.

The durable coordination files are:

- `goal.md`: source objective.
- `progress.md`: human-readable status and next work per lane.
- `porting.html`: generated dashboard.
- `lanes/*/UPSTREAM_TEST_MANIFEST.json`: upstream source and benchmark denominator mapping.
- `lanes/*/lane-status.json`: current implementation/audit status consumed by the dashboard.

Run the current PHP checks with:

```sh
php tools/run-tests.php
```

Regenerate the dashboard with:

```sh
php tools/generate-dashboard.php
```

