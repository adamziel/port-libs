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

``` {.go #go-review .numberLines startFrom=135}
// WordPress import packet normalizer
package review

import (
    "context"
    "encoding/json"
)

type ReviewPacket struct {
    Title string `json:"title"`
    Meta map[string]any
}

func NormalizeTitle(ctx context.Context, packet *ReviewPacket) (string, error) {
    if packet == nil || packet.Title == "" {
        return "Untitled", nil
    }
    var payload map[string]any
    if err := json.Unmarshal([]byte(packet.Title), &payload); err != nil {
        return packet.Title, err
    }
    go func() { _ = ctx.Err() }()
    return packet.Title, nil
}
```

``` {.ps1 #powershell-review .numberLines startFrom=150}
# WordPress Windows import review
[CmdletBinding()]
param(
    [string]$SourcePath,
    [switch]$DryRun
)

$packet = Get-Content -LiteralPath $SourcePath | ConvertFrom-Json
if ($null -eq $packet.title -or $packet.title.Trim() -eq "") {
    Write-Warning "Missing title in $SourcePath"
    return
}

$blocks = @(
    "<!-- wp:paragraph --><p>$($packet.title)</p><!-- /wp:paragraph -->"
)
$meta = @{
    source = $SourcePath
    dryRun = $DryRun
}
$blocks | ForEach-Object { $_.Trim() } | Set-Content -LiteralPath ".\review.html"
```

``` {.dot #dot-review .numberLines startFrom=170}
// WordPress import workflow graph
digraph ImportFlow {
  graph [rankdir=LR, label="Legacy import"];
  node [shape=box, style="rounded,filled", color="#005cc5"];
  ingest [label="Read Markdown"];
  review [label="Reviewer Queue", URL="https://example.test/wp-admin/edit.php"];
  publish [label="Publish"];
  ingest -> review [label="normalize"];
  review -> publish [label="approve", weight=2];
  subgraph cluster_media {
    label="Media";
    media [label="Import attachments"];
  }
}
```

``` {.mjs #gutenberg-js-review .numberLines startFrom=190}
// Gutenberg import block registration review
import { registerBlockType } from "@wordpress/blocks";
import apiFetch from "@wordpress/api-fetch";

const slugify = (title = "Untitled") =>
  title.trim().toLowerCase().replace(/\s+/gu, "-");

export async function registerImportBlock(sourceId) {
  const response = await apiFetch({ path: "/wp/v2/posts?per_page=1" });
  registerBlockType("legacy/import-review", {
    title: `Import ${sourceId}`,
    attributes: { sourceId: { type: "string" } },
    edit({ attributes }) {
      console.log(JSON.stringify(response));
      return wp.element.createElement("p", null, slugify(attributes.sourceId));
    },
  });
}
```

``` {.cs #csharp-review .numberLines startFrom=210}
// ASP.NET legacy import packet review
using System.Text.Json;
using System.Text.Json.Serialization;

namespace Legacy.Import;

public sealed record ReviewPacket(
    [property: JsonPropertyName("title")] string? Title,
    [property: JsonPropertyName("sourceId")] long SourceId
);

public static class WordPressBlockNormalizer
{
    public static async Task<string> RenderAsync(string rawJson)
    {
        var packet = JsonSerializer.Deserialize<ReviewPacket>(rawJson);
        var title = packet?.Title ?? "Untitled";
        if (string.IsNullOrWhiteSpace(title))
        {
            return $"<!-- wp:paragraph --><p>Import {packet?.SourceId}</p><!-- /wp:paragraph -->";
        }

        await Console.Out.WriteLineAsync(title);
        return title.Trim();
    }
}
```

``` {.mysql #sql-migration-review .numberLines startFrom=230}
-- WordPress SQL migration review
START TRANSACTION;
CREATE TABLE `wp_posts` (
  `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `post_title` varchar(255) NOT NULL DEFAULT '',
  `post_status` varchar(20) NOT NULL DEFAULT 'draft',
  PRIMARY KEY (`ID`)
);
INSERT INTO `wp_posts` (`ID`, `post_title`, `post_status`)
VALUES (42, 'Imported', 'publish')
ON DUPLICATE KEY UPDATE `post_title` = VALUES(`post_title`);
SELECT JSON_EXTRACT(`meta_value`, '$.title') AS `title`
FROM `wp_postmeta`
WHERE `post_id` = :post_id AND `meta_key` LIKE 'review\_%' ESCAPE '\\';
COMMIT;
```

``` {.pgsql #postgres-trigger-review .numberLines startFrom=250}
-- PostgreSQL trigger review
CREATE OR REPLACE FUNCTION wp_review_notice()
RETURNS trigger
LANGUAGE plpgsql
AS $review$
BEGIN
  RAISE NOTICE 'import %', NEW.post_title;
  NEW.reviewed_at := CURRENT_TIMESTAMP;
  RETURN NEW;
END;
$review$;
CREATE TRIGGER wp_review_before_insert
BEFORE INSERT ON wp_posts
FOR EACH ROW EXECUTE FUNCTION wp_review_notice();
```

``` {.htaccess #htaccess-review .numberLines startFrom=270}
# WordPress permalink review
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
Header set X-Import-Source "legacy"
</IfModule>
```

``` {.pandoc-lua #lua-long-bracket-review .numberLines startFrom=290}
--[=[ WordPress block fixture can contain <!-- comments --> ]=]
local rawBlock = [=[
<!-- wp:paragraph -->
<p>Imported ${title}</p>
<!-- /wp:paragraph -->
]=]
return pandoc.RawBlock("html", rawBlock)
```

``` {.php #php-heredoc-review .numberLines startFrom=310}
<?php
$block = <<<HTML
<!-- wp:paragraph -->
<p>Imported {$title}</p>
<!-- /wp:paragraph -->
HTML;

$raw = <<<'NOWDOC'
<!-- wp:html -->
<div data-source="legacy">raw</div>
<!-- /wp:html -->
NOWDOC;

echo $block . $raw;
```

``` {.rst #rst-review .numberLines startFrom=330}
.. WordPress import review note

Import Review
=============

:source: legacy-doc
:status: **needs review**

.. _review queue: https://example.test/wp-admin/edit.php

.. code-block:: php

   echo esc_html($title);

Preserve ``legacy_shortcode`` and :doc:`media map <uploads>` with `queue link`_.
See https://example.test/review.
```

``` {.tsx #tsx-review .numberLines startFrom=350}
// Gutenberg typed block inspector review
import type { BlockEditProps } from "@wordpress/blocks";
import { InspectorControls } from "@wordpress/block-editor";

type ReviewAttributes = {
  title?: string;
  sourceId: number;
};

export const Edit = ({ attributes, setAttributes }: BlockEditProps<ReviewAttributes>) => (
  <InspectorControls>
    <PanelBody title={`Import ${attributes.sourceId}`}>
      <TextControl
        label="Title"
        value={attributes.title ?? "Untitled"}
        onChange={(title: string) => setAttributes({ title })}
      />
    </PanelBody>
  </InspectorControls>
);
```

``` {.cmake #cmake-review .numberLines startFrom=370}
# WordPress native extension build review
cmake_minimum_required(VERSION 3.20)
project(WPImportReview VERSION 1.0 LANGUAGES C)

set(PLUGIN_SLUG "legacy-import" CACHE STRING "WordPress plugin slug")
option(WP_IMPORT_BUILD_SHARED "Build shared review helper" ON)

add_library(wp_import_review MODULE src/review.c)
target_compile_definitions(wp_import_review PRIVATE
  PLUGIN_SLUG="${PLUGIN_SLUG}"
  $<$<CONFIG:Debug>:WP_IMPORT_DEBUG=1>
)
target_include_directories(wp_import_review PRIVATE ${CMAKE_CURRENT_SOURCE_DIR}/include)
install(TARGETS wp_import_review LIBRARY DESTINATION lib/wordpress/plugins/${PLUGIN_SLUG})
```

``` {.nginx #nginx-review .numberLines startFrom=390}
# WordPress Nginx permalink and PHP-FPM review
server {
  listen 443 ssl http2;
  server_name example.test www.example.test;
  root /srv/www/legacy-import;

  location / {
    try_files $uri $uri/ /index.php?$args;
  }

  location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php-fpm.sock;
  }

  add_header X-Import-Source "legacy" always;

  location @wordpress {
    rewrite ^ /index.php last;
  }
}
```

``` {.twig #twig-template-review .numberLines startFrom=410}
{# Timber theme template review #}
{% extends "base.twig" %}
{% set blocks = ["core/paragraph", "core/image"] %}
{% for item in posts if item.status == "publish" %}
<article class="wp-block-import-card">
  <h2>{{ item.title|default("Untitled")|e }}</h2>
  {{ function("wp_kses_post", item.content)|raw }}
</article>
{% else %}
  {{ include("partials/empty.twig", { source: sourceId }) }}
{% endfor %}
```

``` {.hbs #handlebars-template-review .numberLines startFrom=430}
{{!-- Handlebars theme migration review --}}
<section class="wp-block-import-card" data-source={{sourceId}}>
  {{#if title}}
    <h2>{{title}}</h2>
  {{else}}
    <h2>{{default "Untitled"}}</h2>
  {{/if}}
  {{#each media}}
    <img src={{url}} alt={{alt}} />
  {{/each}}
  {{{rawBlock}}}
  {{> footer source=sourceId count=2}}
</section>
```

``` {.mermaid #mermaid-review .numberLines startFrom=450}
%% WordPress import workflow diagram review
%%{ init: { "theme": "base" } }%%
flowchart LR
  ingest[Read WXR] --> normalize{Normalize blocks}
  normalize -->|safe HTML| review[Reviewer Queue]
  normalize -- media --> media[(Attachment Library)]
  review -. approve .-> publish[Publish]
  classDef warning fill:#fff4ce,stroke:#d29922,color:#24292f;
  class normalize warning;
```

``` {.html #html-embedded-review .numberLines startFrom=470}
<!-- WordPress embedded asset review -->
<div class="wp-block-import-card" data-source="legacy-42">
  <style>
    .wp-block-import-card { color: var(--accent-color); }
    @media (min-width: 48rem) { .wp-block-import-card { margin-block: 1rem; } }
  </style>
  <script type="module">
    const block = wp.element.createElement("p", null, "Imported");
    if (window.wp?.data) {
      console.log(JSON.stringify({ ok: true, source: "legacy-42" }));
    }
  </script>
</div>
```

``` {.html #html-php-template-review .numberLines startFrom=490}
<!-- WordPress PHP template review -->
<article class="wp-block-import-card">
  <?php if (! empty($post_title)) : ?>
    <h2><?= esc_html($post_title) ?></h2>
  <?php else : ?>
    <h2>Untitled</h2>
  <?php endif; ?>
</article>
```

``` {.gql #graphql-review .numberLines startFrom=510}
# WPGraphQL import review query
query ImportReview($postId: ID!, $includeMedia: Boolean = true) {
  post(id: $postId, idType: DATABASE_ID) {
    title
    blocks {
      name
      attributes
    }
    media: featuredImage @include(if: $includeMedia) {
      node {
        sourceUrl
        altText
      }
    }
  }
}

type ReviewPacket implements Node {
  id: ID!
  title: String
  blocks: [String!]!
}
```
