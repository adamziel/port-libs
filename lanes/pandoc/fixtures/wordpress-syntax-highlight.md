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
    payload = Path(packet.source_path).read_bytes()
    if payload.startswith(b"\xef\xbb\xbf"):
        payload = payload.removeprefix(b"\xef\xbb\xbf")
    pattern = rb"legacy-\d+"
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
[[theme."palette variants"]]
name = "editor"
created_at = 2026-06-05T08:40:00 # local review time
review.cutoff = 08:40:00.125
"accent.color" = "#005cc5"
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

``` {.php #php-attribute-review .numberLines startFrom=530}
<?php
#[BlockVariation(name: 'legacy/import', title: 'Legacy Import')]
final readonly class ImportBlock
{
    public function __construct(public string $title = 'Untitled') {}
    public function status(): ImportStatus
    {
        return $this->title === '' ? ImportStatus::Draft : ImportStatus::Ready;
    }
}

enum ImportStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
}

$normalize = fn(array $item): string => $item['title'] ?? ImportStatus::Draft->value;
```

``` {.adoc #asciidoc-review .numberLines startFrom=550}
// WordPress importer runbook review
= Legacy Import Review
:source-id: legacy-42
:wp-block: core/paragraph

[[review-queue]]
NOTE: Preserve `legacy_shortcode` blocks before publishing.

image::uploads/hero.jpg[Hero image]
link:https://example.test/wp-admin/edit.php[Reviewer queue]

[source,php]
----
echo esc_html($title); // reviewed output <1>
----
<1> Escaped WordPress block title.
```

``` {.php #phpdoc-review .numberLines .tokenTitles startFrom=570}
<?php
/**
 * Builds a review title from a migrated block packet.
 *
 * @template TPacket of array<string,mixed>
 * @param array<string,mixed> $item Source block attributes.
 * @param list<WP_Post>|null $attachments Imported media records.
 * @return non-empty-string
 * @throws ImportReviewException when title metadata is unsafe.
 */
function normalize_review_title(array $item, ?array $attachments = null): string
{
    return trim((string) ($item['title'] ?? 'Untitled'));
}
```

``` {.terraform #terraform-review .numberLines startFrom=590}
# WordPress import infrastructure review
terraform {
  required_version = ">= 1.6.0"
}

variable "source_id" {
  type    = string
  default = "legacy-42"
}

locals {
  review_tags = {
    Source = var.source_id
    System = "wordpress-import"
  }
}

resource "aws_s3_bucket" "media" {
  bucket = "wp-${var.source_id}-media"
  tags   = merge(local.review_tags, {
    Purpose = "attachment-review"
  })
}

output "review_packet" {
  value = jsonencode({
    source  = var.source_id
    bucket  = aws_s3_bucket.media.bucket
    dry_run = true
  })
}
```

``` {.shopify #liquid-review .numberLines startFrom=620}
{%- comment -%} WordPress migration review for Shopify product snippets {%- endcomment -%}
<article class="wp-block-import-card" data-source="{{ product.id }}">
  {% assign title = product.title | default: "Untitled" | escape %}
  {% if product.available and product.images.size > 0 %}
    <img src="{{ product.featured_image | image_url: width: 800 }}" alt="{{ title }}">
  {% else %}
    <p>{{ product.description | strip_html | truncatewords: 24 }}</p>
  {% endif %}
  {% render "review-badge", source_id: product.id, status: "needs-review" %}
</article>
```

``` {.elm #elm-review .numberLines startFrom=640}
{- WordPress import review UI state -}
module ImportReview exposing (Model, Msg(..), view)

import Html exposing (Html)
import Html.Attributes as Attr
import Json.Decode as Decode

type alias Model =
    { title : String
    , sourceId : Int
    , published : Bool
    }

type Msg
    = Approve
    | Reject String

decoder : Decode.Decoder Model
decoder =
    Decode.map3 Model
        (Decode.field "title" Decode.string)
        (Decode.field "sourceId" Decode.int)
        (Decode.succeed False)

view : Model -> Html Msg
view model =
    Html.div [ Attr.class "wp-block-import-card", Attr.attribute "data-source" (String.fromInt model.sourceId) ]
        [ Html.h2 [] [ Html.text model.title ]
        , Html.button [] [ Html.text (if model.published then "Published" else "Needs review") ]
        ]
```

``` {.jsonc #jsonc-review .numberLines startFrom=660}
// WordPress import review settings
{
  // Keep unsafe legacy shortcodes visible for editors.
  "source": "legacy-42",
  unlistedBlocks: ["core/html", "legacy/shortcode"],
  "media": {
    "download": true,
    "maxBytes": 1048576,
  },
  /* Reviewer-only routing; ignored by strict JSON consumers. */
  "review": {
    "queue": "needs-review",
    "notify": null,
    "dryRun": false,
  },
}
```

``` {.less #less-review .numberLines startFrom=680}
// WordPress block theme LESS review
@accent-color: #005cc5;
@spacing: 1.5rem;

.import-card(@selector, @state: hover) when (@state = hover) {
  @{selector} {
    --accent-color: @accent-color;
    margin-block: @spacing;
    color: darken(@accent-color, 10%);
    &:hover { color: lighten(@accent-color, 8%); }
  }
}

.import-card(".wp-block-import-card");
@media (min-width: 48rem) {
  .wp-block-import-card { content: "Read more"; }
}
```

``` {.typst #typst-review .numberLines startFrom=700}
// WordPress import Typst review template
#set page(width: 8.5in, height: 11in, margin: 1in)
#set text(font: "Source Sans 3", size: 11pt)

#let source-id = "legacy-42"
#let title = "Imported post"
#let badge(body) = rect(
  fill: rgb("#005cc5"),
  inset: 6pt,
  radius: 3pt,
  [#body]
)

= #title

#badge([Needs review])
#show link: it => underline(it)
#link("https://example.test/wp-admin/post.php?post=#source-id")[Review source]

#table(
  columns: (1fr, 2fr),
  [Field], [Value],
  [Source], [#source-id],
)
```

``` {.kt #kotlin-review .numberLines startFrom=720}
// Android WordPress import review
package org.wordpress.importer

import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json

@Serializable
data class ReviewPacket(
    val title: String?,
    val sourceId: Long,
    val media: List<String> = emptyList(),
)

fun normalizeTitle(raw: String): String {
    val packet = Json.decodeFromString<ReviewPacket>(raw)
    return packet.title?.trim()?.ifBlank { "Import ${packet.sourceId}" } ?: "Untitled"
}

val blocks = mapOf("core/paragraph" to true, "core/html" to false)
```

``` {.dart #dart-review .numberLines startFrom=740}
// Flutter WordPress import review card
import 'package:flutter/widgets.dart';

@immutable
class ReviewPacket {
  const ReviewPacket({required this.title, required this.sourceId, this.media = const []});

  final String title;
  final int sourceId;
  final List<String> media;

  Future<Widget> buildCard(BuildContext context) async {
    final safeTitle = title.trim().isEmpty ? 'Untitled' : title.trim();
    return Column(
      children: <Widget>[
        Text('Import $sourceId: $safeTitle'),
        if (media.isNotEmpty) Text('${media.length} attachments'),
      ],
    );
  }
}
```

``` {.swift #swift-review .numberLines startFrom=760}
// SwiftUI WordPress import review card
import SwiftUI

struct ReviewPacket: Decodable, Identifiable {
    let id: Int
    let title: String?
    let media: [String]
}

@MainActor
final class ReviewModel: ObservableObject {
    @Published var packet: ReviewPacket?

    func load(from data: Data) async throws {
        packet = try JSONDecoder().decode(ReviewPacket.self, from: data)
    }
}

struct ImportReviewCard: View {
    @StateObject private var model = ReviewModel()

    var body: some View {
        VStack(alignment: .leading) {
            Text(model.packet?.title?.trimmingCharacters(in: .whitespacesAndNewlines) ?? "Untitled")
            if let count = model.packet?.media.count, count > 0 {
                Text("\(count) attachments")
            }
            Button("Review in WordPress") {
                Task { try? await model.load(from: Data()) }
            }
        }
    }
}
```

``` {.clj #clojure-review .numberLines startFrom=780}
;; Babashka WordPress import review
(ns importer.review
  (:require [clojure.edn :as edn]
            [clojure.string :as str]))

(defn normalize-title [packet]
  (let [title (str/trim (or (:post/title packet) "Untitled"))]
    (if (str/blank? title)
      (str "Import " (:source/id packet))
      title)))

(def review-packet
  {:source/id 42
   :post/title "Migrated block"
   :media/items [{:url "uploads/hero.jpg" :alt nil}]
   :blocks #{"core/paragraph" "core/image"}})

#_(println "discarded debug")
(map normalize-title [review-packet])
```

``` {.scala #scala-review .numberLines startFrom=800}
// Scala WordPress import review
package importer.review

import scala.util.Try

final case class ReviewPacket(
    title: Option[String],
    sourceId: Long,
    media: List[String] = Nil,
) derives CanEqual

object ReviewPacket:
  private val defaultTitle = "Untitled"

  def normalize(packet: ReviewPacket): String =
    val title = packet.title.map(_.trim).filter(_.nonEmpty).getOrElse(defaultTitle)
    if title.isEmpty then s"Import ${packet.sourceId}" else title

  val blocks: Map[String, Boolean] =
    Map("core/paragraph" -> true, "core/html" -> false)
```

``` {.ex #elixir-review .numberLines startFrom=820}
# Phoenix WordPress import review
defmodule Importer.ReviewPacket do
  @derive Jason.Encoder
  @enforce_keys [:source_id, :title]
  defstruct [:source_id, :title, media: [], blocks: MapSet.new()]

  @spec normalize_title(%__MODULE__{}) :: String.t()
  def normalize_title(%__MODULE__{title: title, source_id: source_id}) when is_binary(title) do
    title
    |> String.trim()
    |> case do
      "" -> "Import #{source_id}"
      clean -> clean
    end
  end

  def from_json(raw) do
    with {:ok, packet} <- Jason.decode(raw, keys: :atoms),
         true <- Map.has_key?(packet, :source_id) do
      {:ok, struct(__MODULE__, packet)}
    else
      _ -> {:error, :invalid_packet}
    end
  end
end
```

``` {.vue #vue-sfc-review .numberLines startFrom=840}
<!-- Vue WordPress import card review -->
<template>
  <article class="wp-block-import-card" :data-source="packet.sourceId">
    <h2>{{ packet.title?.trim() || "Untitled" }}</h2>
    <button v-if="packet.reviewUrl" @click="openReview(packet.reviewUrl)">
      Review in WordPress
    </button>
  </article>
</template>

<script setup lang="ts">
import { computed } from "vue";

type ReviewPacket = {
  sourceId: string;
  title?: string;
  reviewUrl?: string;
};

const props = defineProps<{ packet: ReviewPacket }>();
const packet = computed(() => props.packet);

function openReview(url: string) {
  window.location.href = url;
}
</script>

<style scoped>
.wp-block-import-card {
  --accent-color: #005cc5;
  color: var(--accent-color);
}

.wp-block-import-card button:hover {
  text-decoration: underline;
}
</style>
```

``` {.ml #ocaml-review .numberLines startFrom=880}
(* WordPress import review normalizer *)
open Yojson.Safe

type review_packet = {
  source_id : int;
  title : string option;
  media : string list;
}

let normalize_title ?(fallback="Untitled") packet =
  match packet.title with
  | Some title when String.trim title <> "" -> String.trim title
  | _ -> Printf.sprintf "Import %d" packet.source_id

let blocks = [ "core/paragraph"; "core/html" ]
let reviewed = Result.Ok true
```

``` {.jl #julia-review .numberLines startFrom=900}
# WordPress import review normalizer
module ImportReview

using JSON3

Base.@kwdef struct ReviewPacket
    source_id::Int
    title::Union{String, Nothing} = nothing
    blocks::Vector{String} = String[]
end

function normalize_title(packet::ReviewPacket)::String
    title = something(packet.title, "Untitled")
    if isempty(strip(title))
        return "Import $(packet.source_id)"
    end
    return strip(title)
end

packet = JSON3.read(raw_json, ReviewPacket)
@info "review packet" source=packet.source_id dry_run=true
```

``` {.vue #vue-custom-block-review .numberLines startFrom=920}
<!-- Vue custom metadata blocks for WordPress review -->
<template>
  <ImportCard :title="packet.title" />
</template>

<i18n lang="json">
{"en":{"title":"Imported","review":true},"fr":{"title":"Imported FR"}}
</i18n>

<route lang="yaml">
meta:
  requiresReview: true
  roles:
    - editor
    - importer
</route>

<docs lang="md">
## Import Notes

- [x] Review [queue](https://example.test/wp-admin/edit.php)
- Keep `legacy_shortcode` visible
</docs>
```

``` {.awk #awk-review .numberLines startFrom=940}
# AWK WordPress export review
BEGIN {
    FS = ","
    OFS = "\t"
    print "source_id", "title", "status"
}

NR > 1 && $3 ~ /publish|draft/ {
    title = gensub(/^"|"$/, "", "g", $2)
    gsub(/[[:space:]]+/, " ", title)
    if (length(title) == 0) {
        title = "Untitled"
    }
    printf "%s\t%s\t%s\n", $1, title, tolower($3)
}

END {
    print "reviewed", NR - 1 > "/dev/stderr"
}
```

``` {.bat #batch-review .numberLines startFrom=960}
@echo off
REM Windows WordPress import review
setlocal EnableExtensions EnableDelayedExpansion
set "SOURCE_DIR=%~dp0exports"
set "WP_ENV=production"
if not exist "%SOURCE_DIR%\wxr.xml" (
    echo Missing export: "%SOURCE_DIR%\wxr.xml"
    exit /b 1
)
for %%P in ("%SOURCE_DIR%\*.html") do (
    php "%~dp0tools\normalize-title.php" "%%~fP" >> ".\review.log"
    if !ERRORLEVEL! NEQ 0 goto :failed
)
wp post list --format=ids > ".\post-ids.txt"
goto :done

:failed
echo Import review failed for %WP_ENV%
exit /b 2

:done
endlocal
```

``` {.matlab #matlab-review .numberLines startFrom=980}
% WordPress technical note scoring review
function [score, slug] = normalizeImport(packet)
    arguments
        packet.title string
        packet.views double = NaN
    end

    title = strtrim(packet.title);
    if strlength(title) == 0
        title = "Untitled";
    end

    slug = lower(regexprep(title, "[^a-z0-9]+", "-"));
    score = double(packet.views) ./ max(1, numel(title));
    meta = struct("reviewed", true, "slug", slug);
end
```

``` {.fish #fish-review .numberLines startFrom=1000}
# Fish shell WordPress import review
function normalize_title --argument-names packet_path
    set -l title (jq -r '.title // ""' $packet_path)
    string trim -- $title | read -l title
    if test -z "$title"
        set title "Untitled"
    end
    printf "review:%s\n" $title
end

for review_path in exports/*.json
    set -l slug (string lower (path basename $review_path .json))
    wp post meta update $slug import_source $review_path; or return 1
end
```

``` {.sed #sed-review .numberLines startFrom=1020}
# sed WordPress block cleanup review
1i\
<!-- wp:paragraph -->
/^[[:space:]]*$/d
s#<script[^>]*>.*</script>##g
s/\[gallery[^\]]*\]/<!-- wp:shortcode -->[gallery]<!-- \/wp:shortcode -->/g
/<!-- wp:html -->/,/<!-- \/wp:html -->/{
  s/\r$//
  t normalized
  b
}
:normalized
p
```

``` {.biblatex #bibtex-review .numberLines startFrom=1040}
% WordPress bibliography review handoff
@online{wp-data-liberation,
  author       = {Doe, Jane and WordPress.org Contributors},
  title        = {Data Liberation Review Packet},
  date         = {2026-06-08},
  url          = {https://example.test/import-review},
  langid       = {english},
  keywords     = {wordpress, migration, blocks},
}

@string{wp = "WordPress"}

@article{legacy-shortcode,
  title = wp # " shortcode audit",
  journaltitle = {Import Notes},
  year = 2025,
}
```

``` {.vim #vim-review .numberLines startFrom=1060}
" Vimscript WordPress import review
scriptencoding utf-8
let g:wp_import_review = v:true
let s:source_path = expand('~/exports/wxr.json')
setlocal keywordprg=:help

function! s:NormalizeTitle(packet) abort
  let l:title = trim(a:packet.title)
  if empty(l:title)
    return 'Untitled'
  endif
  return substitute(l:title, '\s\+', ' ', 'g')
endfunction

command! -nargs=1 ReviewImport call s:NormalizeTitle(json_decode(readfile(<q-args>)[0]))
nnoremap <leader>wr :execute 'edit ' . fnameescape(s:source_path)<CR>
syntax match wpImportSource /\v(import_source|post_title)/
highlight wpImportSource ctermfg=Green guifg=#005cc5
```

``` {.racket #scheme-review .numberLines startFrom=1080}
#lang racket
; WordPress import review helper
(struct packet (source-id title blocks) #:transparent)

(define (normalize-title raw)
  (let* ([trimmed (string-trim raw)]
         [fallback "Untitled"])
    (if (string-blank? trimmed)
        fallback
        trimmed)))

(define (packet->blocks item)
  (match item
    [(packet source-id title blocks)
     (for/list ([block blocks]
                #:when (hash-ref block 'review? #t))
       (hash 'source source-id
             'title (normalize-title title)
             'block-name (hash-ref block 'name "core/paragraph")))]))

(provide normalize-title packet->blocks)
```

``` {.csv #csv-review .numberLines startFrom=1100}
# WordPress CSV import review
source_id,title,status,views,featured
42,"Legacy, ""quoted"" title",draft,120,true
43,Untitled,publish,0,false
44,"Media path: uploads/hero.jpg",needs-review,,null
```

``` {.erl #erlang-review .numberLines startFrom=1120}
%% Erlang WordPress import review worker
-module(wp_import_review).
-behaviour(gen_server).
-export([normalize_title/1, handle_call/3]).
-define(DEFAULT_TITLE, <<"Untitled">>).

-record(review_packet, {source_id :: integer(), title = undefined, blocks = []}).

normalize_title(#review_packet{source_id = SourceId, title = Title} = Packet)
    when is_binary(Title); is_list(Title) ->
    Trimmed = string:trim(unicode:characters_to_list(Title)),
    case Trimmed of
        "" -> <<"Untitled">>;
        _  -> list_to_binary(Trimmed)
    end.

handle_call({review, #review_packet{blocks = Blocks}}, _From, State) ->
    HtmlBlocks = [maps:get(<<"blockName">>, Block, <<"core/paragraph">>) || Block <- Blocks],
    {reply, {ok, HtmlBlocks}, State}.
```

``` {.objc #objectivec-review .numberLines startFrom=1140}
// Objective-C WordPress import review helper
#import <Foundation/Foundation.h>

@interface WPImportReviewPacket : NSObject
@property (nonatomic, copy, nullable) NSString *title;
@property (nonatomic, assign) NSInteger sourceId;
- (NSString *)normalizedTitle;
@end

@implementation WPImportReviewPacket

- (NSString *)normalizedTitle {
    NSString *trimmed = [self.title stringByTrimmingCharactersInSet:NSCharacterSet.whitespaceAndNewlineCharacterSet];
    if (trimmed.length == 0) {
        return [NSString stringWithFormat:@"Import %ld", (long)self.sourceId];
    }
    return trimmed ?: @"Untitled";
}

@end

int main(void) {
    @autoreleasepool {
        WPImportReviewPacket *packet = [WPImportReviewPacket new];
        packet.sourceId = 42;
        NSLog(@"%@", [packet normalizedTitle]);
    }
    return 0;
}
```

``` {.raku #raku-review .numberLines startFrom=1160}
# Raku WordPress import review helper
use JSON::Fast;

unit module WP::Import::Review;

class ReviewPacket {
    has Int $.source-id;
    has Str $.title is rw = "Untitled";
    has @.blocks;
}

sub normalize-title(ReviewPacket $packet --> Str) is export {
    my Str $title = $packet.title.trim;
    return "Untitled" if $title eq "";
    $title.subst(/\s+/, " ", :g);
}

multi sub blocks-to-html(ReviewPacket $packet where *.blocks.elems > 0) {
    gather for $packet.blocks -> %block {
        take "<!-- wp:{%block<name>} -->{%block<content>}<!-- /wp:{%block<name>} -->";
    }
}

say normalize-title(ReviewPacket.new(source-id => 42, title => " Legacy "));
```

``` {.fnl #fennel-review .numberLines startFrom=1180}
; Fennel WordPress import review helper
(local json (require :json))

(fn normalize-title [packet]
  (let [title (or packet.title "Untitled")
        trimmed (string.gsub title "^%s*(.-)%s*$" "%1")]
    (if (= trimmed "")
        "Untitled"
        trimmed)))

(fn packet->blocks [packet]
  (collect [_ block (ipairs packet.blocks)]
    (when (not= block.name nil)
      {:source-id packet.source_id
       :block-name (or block.name "core/paragraph")
       :html (string.format "<!-- wp:%s -->%s<!-- /wp:%s -->" block.name block.content block.name)})))

(print (normalize-title {:title " Legacy " :source_id 42}))
```

``` {.meson #meson-review .numberLines startFrom=1200}
# Meson WordPress native helper review
project('wp-import-review', 'c', version: '1.0')

plugin_slug = 'legacy-import'
review_sources = files('review.c', 'audit.c')
wp_cli = find_program('wp', required: false)
config = configuration_data()
config.set('PLUGIN_SLUG', plugin_slug)

library(
  'wp_import_review',
  review_sources,
  c_args: ['-DWP_IMPORT_REVIEW=1'],
  install: true,
)

if get_option('review_tools')
  executable('wp-import-review', 'review.c', dependencies: dependency('json-c', required: false))
endif
```

``` {.Justfile #just-review .numberLines startFrom=1220}
# Justfile WordPress import review tasks
set shell := ["bash", "-uc"]
export WP_IMPORT_SOURCE := "legacy-42"

default source_id="legacy-42":
    @just review {{source_id}}

review source_id:
    wp post list --meta_key=source_id --meta_value={{source_id}} --format=json
    php tools/render-review.php {{source_id}}

publish source_id dry_run="true":
    if [ "{{dry_run}}" = "true" ]; then echo "dry run"; else wp post update {{source_id}} --post_status=publish; fi
```

``` {.proto #protobuf-review .numberLines startFrom=1240}
// Protobuf WordPress import review schema
syntax = "proto3";

package wordpress.import.v1;

import "google/protobuf/timestamp.proto";

option php_namespace = "WordPress\\Import\\Review";
option java_package = "org.wordpress.importer.review";

message ReviewPacket {
  reserved 12 to 15;
  string source_id = 1 [json_name = "sourceId"];
  optional string title = 2;
  repeated Block blocks = 3;
  map<string, string> metadata = 4;
  oneof publish_target {
    string post_status = 5;
    bool dry_run = 6 [default = true];
  }
}

message Block {
  string name = 1;
  bytes raw_html = 2;
  repeated string media_url = 3;
}

service ImportReview {
  rpc Queue(ReviewPacket) returns (ReviewPacket);
}
```

``` {.tcl #tcl-review .numberLines startFrom=1260}
# Tcl WordPress import review script
package require json

proc normalize_title {packet} {
    set title [dict get $packet title]
    if {$title eq ""} {
        return "Untitled"
    }
    return [string trim $title]
}

set source_id 42
set packet [dict create title " Legacy " source_id $source_id]
set title [normalize_title $packet]
exec wp post meta update $source_id import_title $title
puts [json::write object title $title source_id $source_id dry_run true]
```

``` {.php #line-highlight-review .numberLines highlight-lines=2,4-5 startFrom=1280}
<?php
$title = trim($packet['title'] ?? '');
if ($title === '') {
    $title = 'Untitled';
}
echo esc_html($title);
```

``` {.f90 #fortran-review .numberLines startFrom=1300}
! Fortran WordPress import review helper
module wp_import_review
  implicit none
  type :: review_packet
    integer :: source_id
    character(len=:), allocatable :: title
  end type review_packet
contains
  pure function normalized_title(packet) result(title)
    type(review_packet), intent(in) :: packet
    character(len=:), allocatable :: title
    title = trim(packet%title)
    if (len_trim(title) == 0) then
      write(title, '(A,I0)') 'Import ', packet%source_id
    end if
  end function normalized_title
end module wp_import_review
```

``` {.d #d-review .numberLines startFrom=1320}
// D WordPress import review helper
module wp.review.packet;

import std.algorithm : strip;
import std.format : format;

@safe pure string normalizedTitle(ReviewPacket packet)
{
    immutable title = packet.title.strip;
    if (title.length == 0) {
        return format!"Import %s"(packet.sourceId);
    }
    return title;
}

struct ReviewPacket {
    ulong sourceId;
    string title;
}
```

``` {.common-lisp #common-lisp-review .numberLines startFrom=1340}
;;;; Common Lisp WordPress import review helper
(defpackage #:wp-import.review
  (:use #:cl)
  (:export #:queue-review-packet))

(in-package #:wp-import.review)

(defstruct review-packet
  source-id
  title
  blocks)

(defun normalized-title (packet)
  (let* ((title (string-trim " " (review-packet-title packet))))
    (if (string= title "")
        (format nil "Import ~A" (review-packet-source-id packet))
        title)))

(defun queue-review-packet (packet)
  (list :source-id (review-packet-source-id packet)
        :title (normalized-title packet)
        :blocks (remove-if-not #'identity (review-packet-blocks packet))))
```

``` {.pascal #pascal-review .numberLines startFrom=1360}
// Pascal WordPress import review helper
program WPImportReview;

{$mode objfpc}{$H+}

type
  TReviewPacket = record
    SourceId: Integer;
    Title: string;
  end;

function NormalizedTitle(const Packet: TReviewPacket): string;
begin
  Result := Trim(Packet.Title);
  if Result = '' then
    Result := Format('Import %d', [Packet.SourceId]);
end;

var
  Packet: TReviewPacket;
begin
  Packet.SourceId := 42;
  Packet.Title := ' Legacy ';
  WriteLn(NormalizedTitle(Packet));
end.
```

``` {.gradle #groovy-review .numberLines startFrom=1380}
// Groovy Gradle/Jenkins WordPress import review helper
import groovy.json.JsonSlurper

@Grab('org.codehaus.groovy:groovy-json:3.0.21')
class ReviewPacket {
    Long sourceId
    String title
    List<String> blocks = []
}

def packet = new JsonSlurper().parseText('{"sourceId":42,"title":" Legacy ","blocks":["core/paragraph"]}') as ReviewPacket
def normalizedTitle = packet.title?.trim() ?: "Import ${packet.sourceId}"

pipeline {
    agent any
    stages {
        stage('WordPress review') {
            steps {
                sh "wp post meta update ${packet.sourceId} import_title '${normalizedTitle}'"
                writeJSON file: 'review.json', json: [title: normalizedTitle, dryRun: true]
            }
        }
    }
}
```

``` {.crystal #crystal-review .numberLines startFrom=1400}
# Crystal WordPress import review helper
require "json"

@[Link("wp-review")]
module WPImport
  struct ReviewPacket
    include JSON::Serializable

    property source_id : Int32
    property title : String?
    property blocks : Array(String)
  end

  def self.normalized_title(packet : ReviewPacket) : String
    title = packet.title.try(&.strip) || "Import #{packet.source_id}"
    if title.empty?
      return "Import #{packet.source_id}"
    end
    title
  rescue ex : JSON::ParseException
    STDERR.puts "invalid review packet: #{ex.message}"
    "Untitled"
  end
end
```

``` {.shell-session #shell-session-review .numberLines startFrom=1420}
$ wp post list --post_type=post --format=ids
42
$ title=$(wp post get 42 --field=post_title)
Legacy Review
$ printf '%s\n' "$title"
Legacy Review
```

``` {.nim #nim-review .numberLines startFrom=1440}
# Nim WordPress import review helper
import std/[json, options, strutils]

type
  ReviewPacket* = object
    sourceId*: int
    title*: Option[string]
    blocks*: seq[string]

proc normalizeTitle*(packet: ReviewPacket): string {.raises: [ValueError].} =
  let title = packet.title.get("Untitled").strip()
  if title.len == 0:
    return "Import " & $packet.sourceId
  result = title

proc queueReview*(raw: string): JsonNode =
  let packet = raw.parseJson().to(ReviewPacket)
  %*{
    "title": normalizeTitle(packet),
    "dryRun": true,
    "blocks": packet.blocks
  }
```

``` {.v #v-review .numberLines startFrom=1460}
// V WordPress import review helper
module review

import json
import strings

[json: source_id]
struct ReviewPacket {
    source_id int
    title ?string
    blocks []string
}

pub fn normalize_title(packet ReviewPacket) !string {
    mut title := packet.title or { 'Untitled' }
    title = strings.trim_space(title)
    if title.len == 0 {
        return error('missing title for ${packet.source_id}')
    }
    $if debug {
        println('review ${packet.source_id}')
    }
    return title
}

pub fn queue_review(raw string) !map[string]string {
    packet := json.decode(ReviewPacket, raw)!
    title := normalize_title(packet)!
    return {'title': title, 'dryRun': 'true'}
}
```

``` {.idris #idris-review .numberLines startFrom=1480}
-- Idris WordPress import review helper
module WP.Import.Review

%default total
%language ElabReflection

record ReviewPacket where
  constructor MkReviewPacket
  sourceId : Nat
  title : Maybe String
  blocks : List String

normalizeTitle : ReviewPacket -> String
normalizeTitle packet =
  case title packet of
    Just raw => if length raw == 0 then "Untitled" else raw
    Nothing => "Import " ++ show (sourceId packet)

queueReview : String -> Either String ReviewPacket
queueReview raw =
  let packet = MkReviewPacket 42 (Just raw) ["core/paragraph"] in
      Right packet
```

``` {.coq #coq-review .numberLines startFrom=1510}
(* Coq WordPress import proof review *)
From Coq Require Import Strings.String Lists.List.
Import ListNotations.

Record review_packet := {
  source_id : nat;
  title : string;
  blocks : list string
}.

Definition normalize_title (packet : review_packet) : string :=
  match String.length (title packet) with
  | O => "Untitled"
  | S _ => title packet
  end.

Theorem normalize_title_idempotent :
  forall packet, normalize_title packet = normalize_title packet.
Proof.
  intros packet.
  reflexivity.
Qed.
```

``` {.agda #agda-review .numberLines startFrom=1535}
-- Agda WordPress import proof review
module WP.Import.Review where

{-# OPTIONS --safe #-}

open import Agda.Builtin.Nat using (Nat; zero; suc)
open import Agda.Builtin.String using (String)
open import Agda.Builtin.Maybe using (Maybe; just; nothing)

record ReviewPacket : Set where
  constructor mkReviewPacket
  field
    sourceId : Nat
    title : Maybe String

normalizeTitle : ReviewPacket -> String
normalizeTitle packet with ReviewPacket.title packet
... | just raw = raw
... | nothing = "Untitled"

postulate
  normalizeTitleIdempotent : (packet : ReviewPacket) -> normalizeTitle packet == normalizeTitle packet
```

``` {.purs #purescript-review .numberLines startFrom=1565}
-- PureScript WordPress import review
module WP.Import.Review where

import Effect (Effect)
import Data.Maybe (Maybe(..), fromMaybe)

newtype ReviewPacket = ReviewPacket
  { sourceId :: Int
  , title :: Maybe String
  , blocks :: Array String
  }

normalizeTitle :: ReviewPacket -> String
normalizeTitle (ReviewPacket packet) =
  case packet.title of
    Just raw -> raw
    Nothing -> "Untitled"

queueReview :: String -> Effect ReviewPacket
queueReview raw = pure (ReviewPacket { sourceId: 42, title: Just raw, blocks: ["core/paragraph"] })
```

``` {.fsx #fsharp-review .numberLines startFrom=1585}
// F# WordPress import review helper
module WP.Import.Review

open System
open System.Text.Json

type ReviewPacket =
    { SourceId: int
      Title: string option
      Blocks: string list }

[<RequireQualifiedAccess>]
type ReviewStatus =
    | Draft
    | Publish of slug: string

let normalizeTitle (packet: ReviewPacket) =
    match packet.Title with
    | Some title when not (String.IsNullOrWhiteSpace title) -> title.Trim()
    | _ -> $"Import {packet.SourceId}"

let queueReview packet =
    async {
        let blocks = packet.Blocks |> List.filter (String.IsNullOrWhiteSpace >> not)
        return {| title = normalizeTitle packet; blockCount = blocks.Length |}
    }
```

``` {.rakudoc #raku-pod-quote-review .numberLines startFrom=1610}
=begin pod
=head1 Import review

Preserve WordPress shortcode notes while auditing generated HTML.
=end pod

my $title = q:to/END/;
Legacy shortcode [gallery]
END

my $html = qq:to/HTML/;
<!-- wp:paragraph --><p>$title</p><!-- /wp:paragraph -->
HTML

say $title.trim;
```
