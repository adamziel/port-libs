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
