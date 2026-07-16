posts = load_legacy_posts()
for post in posts:
    normalize_blocks(post)
    save_post(post)
hydrate_featured_media(post)
write_report(posts)
