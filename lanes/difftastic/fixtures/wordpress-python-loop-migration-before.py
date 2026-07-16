posts = load_legacy_posts()
for post in posts:
    normalize_blocks(post)
    hydrate_featured_media(post)
    save_post(post)
write_report(posts)
