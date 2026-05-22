def migrate_post(post):
    if post.get("legacy_builder"):
        purge_builder_shortcodes(post)
    else:
        normalize_blocks(post)

    try:
        sync_attachments(post)
    except ValueError as error:
        log_skip(post["ID"], error)

    save_post(post)
