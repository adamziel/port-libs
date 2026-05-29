# PRAGMA index_xinfo foreign-key current-source next335-342

Adds follow-on current-source slices for SET NULL and SET DEFAULT action diagnostics when child lookup coverage is present only through partial or expression indexes.

- next335: ON UPDATE SET NULL with partial child lookup index
- next336: ON DELETE SET NULL with partial child lookup index
- next337: ON UPDATE SET NULL with expression child lookup index
- next338: ON DELETE SET NULL with expression child lookup index
- next339: ON UPDATE SET DEFAULT with partial child lookup index
- next340: ON DELETE SET DEFAULT with partial child lookup index
- next341: ON UPDATE SET DEFAULT with expression child lookup index
- next342: ON DELETE SET DEFAULT with expression child lookup index

The source remains `pragma_foreign_key_list_actions_plus_table_info`; the action rows also depend on the existing child lookup classification backed by `PRAGMA index_list` and `PRAGMA index_xinfo`.
