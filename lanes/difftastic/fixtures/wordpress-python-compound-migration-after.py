def migrate_post(post):
    if post.get("legacy_builder"):
        purge_builder_shortcodes(post)
    elif post.get("raw_html"):
        sanitize_raw_html(post)
    else:
        normalize_blocks(post)

    try:
        sync_attachments(post)
    except ValueError as error:
        log_skip(post["ID"], error)
    finally:
        cleanup_temp_media(post)

    save_post(post)
