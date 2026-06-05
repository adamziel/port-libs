``` {.php #migration-review data-source=batch-42}
<?php
function render_title($post) {
    return esc_html($post['title']); // WordPress-safe title
}
```

``` {.json}
{"title":"Legacy post","draft":false,"count":2}
```

``` {.latex}
\documentclass[11pt]{article}
\usepackage{graphicx}
% WordPress import review note
\newcommand{\ReviewTitle}{$title$}
\begin{document}
\section{Import 42}
\includegraphics[width=0.5\textwidth]{media.png}
\end{document}
```

``` {.patch #source-diff .numberLines startFrom=9}
diff --git a/content.php b/content.php
index 1111111..2222222 100644
--- a/content.php
+++ b/content.php
@@ -1,3 +1,4 @@
-echo $old_title;
+echo esc_html($new_title);
 context line
\ No newline at end of file
```

```` {.md #markdown-review .numberLines startFrom=5}
# Migration Review

- [x] Preserve [media](uploads/hero.png)
- Keep `legacy_shortcode` visible
> Reviewer note with <https://example.test/post>

[asset]: uploads/hero.png "Hero image"

``` {.php}
echo esc_html($title);
```
````

``` {.rb}
# WordPress import audit task
require 'json'
module Migration
  class ReviewPacket
    def initialize(path:)
      @path = path
    end

    def call
      puts JSON.parse(File.read(@path))['title']
    rescue JSON::ParserError => error
      warn "invalid import: #{error.message}"
      nil
    end
  end
end
```

``` {.pandoc-lua #lua-filter-review .numberLines startFrom=3}
-- WordPress import Lua filter
function Header(el)
  local title = pandoc.utils.stringify(el.content)
  if el.level == 1 then
    return pandoc.Div({el}, {class = "import-title"})
  end
  return nil
end
```

``` {.ts #ts-review .numberLines startFrom=12}
// Gutenberg block migration packet
type BlockPayload = {
  title?: string;
  meta: Record<string, unknown>;
};

export async function migrateBlock(payload: BlockPayload): Promise<void> {
  const title = payload.title ?? `Untitled`;
  if (payload.meta?.sourceId !== undefined) {
    console.log(`import:${payload.meta.sourceId}`);
  }
  return;
}
```

``` {.python3 #python-review .numberLines startFrom=20}
# WordPress import JSON cleanup
from dataclasses import dataclass
from pathlib import Path
@dataclass
class ReviewPacket:
    source_id: int
    title: str | None = None

def normalize_title(packet: ReviewPacket) -> str:
    raw = json.loads(Path(packet.source_path).read_text())["title"]
    if raw is None:
        return "Untitled"
    return raw.strip()
```

``` {.cpp #cpp-review .numberLines startFrom=30}
#include <string>
#include "wp_import.h"
// WordPress import extension review
namespace Migration {
class ReviewPacket {
public:
    explicit ReviewPacket(std::string title) : title_(std::move(title)) {}
    bool is_draft() const { return title_.empty() || title_ == "Draft"; }
private:
    std::string title_;
};
}
```

``` {.Dockerfile #docker-review .numberLines startFrom=4}
# syntax=docker/dockerfile:1.7
FROM wordpress:php8.3-apache AS source
ARG WP_ENV=production
ENV WORDPRESS_CONFIG_EXTRA="define('WP_DEBUG', false);"
COPY --from=source /var/www/html /review/html
RUN set -eux; \
    php -m | grep json
```

``` {.Makefile #make-review .numberLines startFrom=6}
# WordPress asset build review
PLUGIN_VERSION ?= 1.2.3
assets/build: package.json src/block.js
	$(NPM) run build
	wp i18n make-pot . languages/plugin.pot
deploy:
	@$(WP_CLI) plugin update my-plugin --version $(PLUGIN_VERSION)
```

``` {.jsx #jsx-review .numberLines startFrom=18}
// Gutenberg block preview component
import React from 'react';

export default function ImportPreview(props) {
  const { title, sourceId } = props;
  return <section className="wp-block-import" data-source={sourceId}>
    <h2>{title}</h2>
    <InnerBlocks allowedBlocks={["core/paragraph"]} />
  </section>;
}
```

``` {.r #r-review .numberLines startFrom=27}
## WordPress import analysis
library(dplyr)
scores <- data.frame(title = c("Draft", "Published"), views = c(10L, NA_integer_))
scores <- scores |>
  dplyr::filter(!is.na(title), views >= 10) |>
  mutate(slug = tolower(gsub("[^a-z0-9]+", "-", title)))
if (any(scores$views > 100)) {
  print("popular import")
}
```

``` {.ini #php-ini-review .numberLines startFrom=2}
; WordPress hosting php.ini review
[PHP]
memory_limit = 256M
upload_max_filesize = 64M
display_errors = Off
error_reporting = E_ALL
[opcache]
opcache.enable = 1
```

``` {.toml #toml-review .numberLines startFrom=11}
# WordPress static export review
[tool.wordpress-import]
enabled = true
source = "markdown"
published_at = 2026-06-05T08:40:00Z
max_posts = 250
media_paths = ["uploads", "assets"]
[theme.variation]
palette = { primary = "#005cc5", contrast = "#ffffff" }
```

``` {.pl #perl-review .numberLines startFrom=14}
#!/usr/bin/env perl
use strict;
use warnings;
package WP::ImportReview;
sub normalize_title {
    my ($packet) = @_;
    my ($title) = $packet->{title} // 'Untitled';
    $title =~ s/^\s+|\s+$//g;
    if ($title eq '') {
        warn "empty title for $packet->{id}";
        return undef;
    }
    return lc $title;
}
```

``` {.java #java-review .numberLines startFrom=21}
package org.wordpress.importer;

import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.Optional;

// WordPress import review helper
public final class ReviewPacket {
    private final Path sourcePath;

    public ReviewPacket(Path sourcePath) {
        this.sourcePath = sourcePath;
    }

    @Deprecated
    public Optional<String> title() throws IOException {
        var json = Files.readString(sourcePath);
        if (json.isBlank()) {
            return Optional.empty();
        }
        return Optional.of("Imported");
    }
}
```

``` {.xml #wxr-xml-review .numberLines startFrom=33}
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE rss [<!ENTITY legacy "Legacy">]>
<!-- WordPress WXR media review -->
<rss version="2.0"
     xmlns:wp="http://wordpress.org/export/1.2/"
     xmlns:content="http://purl.org/rss/1.0/modules/content/">
  <channel>
    <wp:wxr_version>1.2</wp:wxr_version>
    <item data-source="legacy-42">
      <title>&legacy; &amp; Reviewed</title>
      <content:encoded><![CDATA[<!-- wp:paragraph --><p>Legacy shortcode [gallery]</p>]]></content:encoded>
    </item>
  </channel>
</rss>
```

``` {.sh #shell-review .numberLines startFrom=50}
#!/usr/bin/env bash
set -euo pipefail
wp post list --post_type=post --format=ids | while read -r post_id; do
  title=$(wp post get "$post_id" --field=post_title)
  if [[ -z "$title" ]]; then
    cat <<'HTML' > "$TMPDIR/post-$post_id.html"
<!-- wp:paragraph --><p>Missing title</p><!-- /wp:paragraph -->
HTML
  fi
done
```

``` {.php #token-title-review .numberLines .tokenTitles startFrom=3}
<?php
echo esc_html($title); // reviewer token titles
```

``` {.css #css-review .numberLines startFrom=70}
/* WordPress block style review */
@media (min-width: 48rem) {
  .wp-block-import-card > a:hover,
  .wp-block-import-card:focus-visible {
    --accent-color: #005cc5;
    margin-block: 1.5rem;
    color: var(--accent-color) !important;
    content: "Read more";
  }
}
```

``` {.rs #rust-review .numberLines startFrom=88}
// WordPress import review helper
use serde_json::Value;

#[derive(Debug)]
pub struct ReviewPacket<'a> {
    pub title: Option<&'a str>,
    source_id: u64,
}

impl<'a> ReviewPacket<'a> {
    pub fn normalized_title(&self) -> String {
        let title = self.title.unwrap_or("Untitled");
        if title.trim().is_empty() {
            return format!("import-{}", self.source_id);
        }
        title.to_string()
    }
}
```

``` {.nix #nix-review .numberLines startFrom=101}
# WordPress deployment expression review
{ pkgs ? import <nixpkgs> {} }:
let
  inherit (pkgs) stdenv writeText;
  pluginSlug = "legacy-import";
  mediaPaths = [ ./uploads ./assets ];
  reviewer = if stdenv.isLinux then "wp-cli" else "manual";
in
pkgs.writeText "${pluginSlug}-review.json" ''
  {"reviewer":"${reviewer}","media":${builtins.toJSON mediaPaths}}
''
```

``` {.scss #scss-review .numberLines startFrom=120}
// WordPress theme Sass review
$accent-color: #005cc5 !default;
$breakpoints: ("desktop": 48rem, "wide": 72rem);

@mixin import-card($selector) {
  #{$selector} {
    color: $accent-color;
    &:hover { color: darken($accent-color, 10%); }
  }
}

@include import-card(".wp-block-import-card");
```
