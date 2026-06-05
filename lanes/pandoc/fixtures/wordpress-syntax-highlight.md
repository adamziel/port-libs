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
