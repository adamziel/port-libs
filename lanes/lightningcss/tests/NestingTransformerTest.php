<?php

declare(strict_types=1);

use PortLibs\LightningCSS\NestingTransformer;

return [
    'nesting transformer maps upstream direct parent selector lowering' => static function (TestRunner $t): void {
        $transformer = new NestingTransformer();

        $t->same(
            '.foo{color:#00f}.foo>.bar{color:red}',
            $transformer->lower('.foo { color: blue; & > .bar { color: red; } }')
        );
        $t->same(
            '.foo{color:#00f}.foo.bar{color:red}',
            $transformer->lower('.foo { color: blue; &.bar { color: red; } }')
        );
        $t->same(
            '.foo{color:#00f}.foo.foo{padding:2ch}',
            $transformer->lower('.foo { color: blue; && { padding: 2ch; } }')
        );
    },
    'nesting transformer maps upstream selector-list parent references' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.foo, .bar {
  color: blue;
  & + .baz, &.qux {
    color: red;
  }
}
CSS;

        $t->same(
            '.foo,.bar{color:#00f}:is(.foo,.bar)+.baz,:is(.foo,.bar).qux{color:red}',
            (new NestingTransformer())->lower($css)
        );
    },
    'nesting transformer maps upstream repeated parent reference selectors' => static function (TestRunner $t): void {
        $transformer = new NestingTransformer();

        // Pinned upstream 22bdda3d src/lib.rs::test_nesting nesting_test lines 24569, 24587, and 24636.
        $t->same(
            '.foo{color:#00f}.foo .bar .foo .baz .foo .qux{color:red}',
            $transformer->lower('.foo { color: blue; & .bar & .baz & .qux { color: red; } }')
        );
        $t->same(
            '.foo{color:#00f;padding:2ch}',
            $transformer->lower('.foo { color: blue; & { padding: 2ch; } }')
        );
        $t->same(
            '.foo:is(.bar,.foo.baz){color:red}',
            $transformer->lower('.foo { &:is(.bar, &.baz) { color: red; } }')
        );
    },
    'nesting transformer maps upstream nested attached selector list lowering' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.a {
  &.b,
  &.c {
    &.d {
      color: red;
    }
  }
}
CSS;

        $t->same(
            '.a.b.d,.a.c.d{color:red}',
            (new NestingTransformer())->lower($css)
        );
    },
    'nesting transformer maps upstream implicit descendant and recursive nesting' => static function (TestRunner $t): void {
        $css = <<<'CSS'
figure {
  margin: 0;

  & > figcaption {
    background: hsl(0 0% 0% / 50%);

    & > p {
      font-size: .9rem;
    }
  }
}
CSS;

        $t->same(
            'figure{margin:0}figure>figcaption{background:#00000080}figure>figcaption>p{font-size:.9rem}',
            (new NestingTransformer())->lower($css)
        );
    },
    'nesting transformer maps upstream nested pseudo-element selectors' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.foo {
  &::before, &::after {
    background: blue;

    @media screen {
      background: orange;
    }
  }
}
CSS;

        $t->same(
            '.foo:before,.foo:after{background:#00f}@media screen{.foo:before,.foo:after{background:orange}}',
            (new NestingTransformer())->lower($css)
        );
    },
    'nesting transformer maps upstream nested state selector lowering' => static function (TestRunner $t): void {
        $css = <<<'CSS'
custom-element {
  color: blue;

  &:state(loading) {
    opacity: 0.5;

    & .spinner {
      display: block;
    }
  }

  &:state(error) {
    border: 2px solid red;
  }
}
CSS;

        // Pinned upstream 22bdda3d src/lib.rs::test_selectors nesting_test line 7362.
        $t->same(
            'custom-element{color:#00f}custom-element:state(loading){opacity:.5}custom-element:state(loading) .spinner{display:block}custom-element:state(error){border:2px solid red}',
            (new NestingTransformer())->lower($css)
        );
    },
    'nesting transformer maps upstream attached type selector lowering' => static function (TestRunner $t): void {
        $transformer = new NestingTransformer();

        $t->same(
            'article.foo>figure{color:red}',
            $transformer->lower('.foo { &article > figure { color: red; } }')
        );
        $t->same(
            'span:is(div>.foo){background:green}',
            $transformer->lower('div > .foo { &span { background: green; } }')
        );
        $t->same(
            'h1:is(.foo .bar){background:green}',
            $transformer->lower('.foo .bar { &h1 { background: green; } }')
        );
        $t->same(
            'h1.foo.bar{background:green}',
            $transformer->lower('.foo.bar { &h1 { background: green; } }')
        );
        $t->same(
            'h1:is(.foo .bar) .baz{background:green}',
            $transformer->lower('.foo .bar { &h1 .baz { background: green; } }')
        );
        $t->same(
            'h1:is(.foo .bar){background:green}',
            $transformer->lower('.foo .bar { @nest h1& { background: green; } }')
        );
        $t->same(
            'html:scope{color:red}.foo:scope{color:#00f}',
            $transformer->lower('&html { color: red; } .foo& { color: blue; }')
        );
    },
    'nesting transformer maps upstream nested media and @nest lowering' => static function (TestRunner $t): void {
        $transformer = new NestingTransformer();

        $t->same(
            '.foo{display:grid}@media (orientation:landscape){.foo{grid-auto-flow:column}}',
            $transformer->lower('.foo { display: grid; @media (orientation: landscape) { grid-auto-flow: column; } }')
        );
        // Pinned upstream 22bdda3d src/lib.rs::test_nesting nesting_test line 24734.
        $t->same(
            '@media (width>=640px){.foo{color:red!important}}',
            $transformer->lower('.foo { @media (min-width: 640px) { color: red !important; } }')
        );
        $t->same(
            '.foo{color:red}.parent .foo{color:#00f}',
            $transformer->lower('.foo { color: red; @nest .parent & { color: blue; } }')
        );
    },
    'nesting transformer maps upstream parent placement and leading combinators' => static function (TestRunner $t): void {
        $transformer = new NestingTransformer();

        $t->same(
            '.foo{color:red}.bar .foo{color:#00f}',
            $transformer->lower('.foo { color: red; .bar & { color: blue; } }')
        );
        $t->same(
            '.foo{color:red}.foo+.bar+.foo{color:#00f}',
            $transformer->lower('.foo { color: red; + .bar + & { color: blue; } }')
        );
        $t->same(
            '.baz :is(.foo .bar){background:green}',
            $transformer->lower('.foo .bar { .baz & { background: green; } }')
        );
        $t->same(
            '.baz :is(.foo .bar){background:green}',
            $transformer->lower('.foo .bar { @nest .baz & { background: green; } }')
        );
    },
    'nesting transformer maps upstream nested supports and container lowering' => static function (TestRunner $t): void {
        $transformer = new NestingTransformer();

        $t->same(
            '.foo{display:grid}@supports (foo:bar){.foo{grid-auto-flow:column}.foo>.bar{color:#00f}}',
            $transformer->lower('.foo { display: grid; @supports (foo: bar) { grid-auto-flow: column; & > .bar { color: blue; } } }')
        );
        $t->same(
            '@container (width>=100px){.foo{grid-auto-flow:column}article.foo>figure{color:red}}',
            $transformer->lower('.foo { @container (min-width: 100px) { grid-auto-flow: column; &article > figure { color: red; } } }')
        );
    },
    'nesting transformer maps upstream nested layer lowering' => static function (TestRunner $t): void {
        $transformer = new NestingTransformer();

        $t->same(
            '.foo{display:grid}@layer test{.foo{grid-auto-flow:column}}',
            $transformer->lower('.foo { display: grid; @layer test { grid-auto-flow: column; } }')
        );
        $t->same(
            '.foo{display:grid}@layer{.foo{grid-auto-flow:column}}',
            $transformer->lower('.foo { display: grid; @layer { grid-auto-flow: column; } }')
        );
    },
    'nesting transformer maps upstream namespace-attached selector lowering' => static function (TestRunner $t): void {
        $css = <<<'CSS'
@namespace "http://example.com/foo";
@namespace toto "http://toto.example.org";

.foo {
  &div {
    color: red;
  }

  &* {
    color: green;
  }

  &|x {
    color: red;
  }

  &*|x {
    color: green;
  }

  &toto|x {
    color: red;
  }
}
CSS;

        $t->same(
            '@namespace "http://example.com/foo";@namespace toto "http://toto.example.org";div.foo{color:red}*.foo{color:green}|x.foo{color:red}*|x.foo{color:green}toto|x.foo{color:red}',
            (new NestingTransformer())->lower($css)
        );
    },
    'nesting transformer recovers invalid styled-jsx placeholder media query' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.container {
  padding: 3rem;

  @media (max-width: --styled-jsx-placeholder-0__) {
    .responsive {
      color: purple;
    }
  }
}
CSS;

        $t->same(
            '.container{padding:3rem}@media (width<=--styled-jsx-placeholder-0__){.container .responsive{color:purple}}',
            (new NestingTransformer())->lower($css)
        );
    },
    'nesting transformer recovers upstream styled-jsx placeholder declaration values' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.container {
  --local-var: --styled-jsx-placeholder-0__;
  color: var(--text-color);
  background: linear-gradient(to right, --styled-jsx-placeholder-1__, --styled-jsx-placeholder-2__);

  .item {
    transform: translate(calc(var(--x) + --styled-jsx-placeholder-3__px), calc(var(--y) + --styled-jsx-placeholder-4__px));
  }

  div {
    margin: calc(10px + --styled-jsx-placeholder-5__px);
  }
}
CSS;

        $t->same(
            '.container{--local-var:--styled-jsx-placeholder-0__;color:var(--text-color);background:linear-gradient(to right,--styled-jsx-placeholder-1__,--styled-jsx-placeholder-2__)}.container .item{transform:translate(calc(var(--x) + --styled-jsx-placeholder-3__px),calc(var(--y) + --styled-jsx-placeholder-4__px))}.container div{margin:calc(10px + --styled-jsx-placeholder-5__px)}',
            (new NestingTransformer())->lower($css)
        );
    },
    'nesting transformer honors explicit nesting include and exclude targets' => static function (TestRunner $t): void {
        $transformer = new NestingTransformer();
        $css = '.foo { color: blue; & > .bar { color: red; } }';

        $t->same(
            '.foo{color:#00f}.foo>.bar{color:red}',
            $transformer->transformForTargets($css, [
                'browsers' => ['chrome' => 112],
                'include' => ['nesting'],
            ])
        );
        $t->same(
            '.foo{color:#00f;&>.bar{color:red}}',
            $transformer->transformForTargets($css, [
                'browsers' => ['chrome' => 50],
                'exclude' => ['nesting'],
            ])
        );
        $t->same(
            '.foo{color:#00f;&>.bar{color:red}}',
            $transformer->transformForTargets($css)
        );
        $t->same(
            '.foo{color:#00f;&>.bar{color:red}}',
            $transformer->transformForTargets($css, ['browsers' => ['chrome' => 112]])
        );
        $t->same(
            '.foo{color:#00f}.foo>.bar{color:red}',
            $transformer->transformForTargets($css, ['browsers' => ['chrome' => 95]])
        );
    },
    'nesting transformer maps upstream nested scope boundary lowering' => static function (TestRunner $t): void {
        $transformer = new NestingTransformer();

        $t->same(
            '@scope(.bar){color:#ff0}',
            $transformer->lower('.foo { @scope (.bar) { color: yellow; } }')
        );
        $t->same(
            '.parent{color:#00f}@scope(.parent>.scope) to (.parent>.scope .limit){:scope .content{color:#ff0}}',
            $transformer->lower('.parent { color: blue; @scope (& > .scope) to (& .limit) { & .content { color: yellow; } } }')
        );
        $t->same(
            '@scope(.card){.wp-block-theme :scope{color:#ff0}}',
            $transformer->lower('@scope (.card) { @nest .wp-block-theme & { color: yellow; } }')
        );
        $t->same(
            '@media (width>=600px){@scope(.card){.wp-block-theme :scope .title{color:#ff0}}}',
            $transformer->lower('@media (min-width: 600px) { @scope (.card) { @nest .wp-block-theme & .title { color: yellow; } } }')
        );
    },
    'nesting transformer maps upstream nested starting-style lowering' => static function (TestRunner $t): void {
        $css = <<<'CSS'
h1 {
  background: red;

  @starting-style {
    background: yellow;
  }
}
CSS;

        $t->same(
            'h1{background:red}@starting-style{h1{background:#ff0}}',
            (new NestingTransformer())->lower($css)
        );
    },
    'wordpress nested block stylesheet lowers without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-query {
  color: blue;

  .wp-block-post-title {
    color: red;

    .is-featured & {
      opacity: .9;
    }
  }

  &:hover .wp-block-post-title {
    text-decoration-color: yellow;
  }

  @media (min-width: 600px) {
    .wp-block-post-title {
      color: blue;
    }
  }

  @scope (& > .wp-block-post-template) to (& .wp-block-post-excerpt) {
    & .wp-block-post-title {
      color: yellow;
    }

    @nest .is-grid & .wp-block-post-title {
      color: blue;
    }
  }
}
CSS;

        $t->same(
            '.wp-block-query{color:#00f}.wp-block-query .wp-block-post-title{color:red}.is-featured :is(.wp-block-query .wp-block-post-title){opacity:.9}.wp-block-query:hover .wp-block-post-title{text-decoration-color:#ff0}@media (width>=600px){.wp-block-query .wp-block-post-title{color:#00f}}@scope(.wp-block-query>.wp-block-post-template) to (.wp-block-query>.wp-block-post-template .wp-block-post-excerpt){:scope .wp-block-post-title{color:#ff0}.is-grid :scope .wp-block-post-title{color:#00f}}',
            (new NestingTransformer())->lower($css)
        );
    },
    'wordpress block transition starting-style lowers without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-button.is-style-fade-in {
  opacity: 1;
  transform: translateY(0);

  @starting-style {
    opacity: 0;
    transform: translateY(12px);
  }
}
CSS;

        $t->same(
            '.wp-block-button.is-style-fade-in{opacity:1;transform:translateY(0)}@starting-style{.wp-block-button.is-style-fade-in{opacity:0;transform:translateY(12px)}}',
            (new NestingTransformer())->lower($css)
        );
    },
    'wordpress nested pseudo-element selectors lower for block controls' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-button {
  &::before, &::after {
    content: "";
    border-color: yellow;
  }
}
CSS;

        $t->same(
            '.wp-block-button:before,.wp-block-button:after{content:"";border-color:#ff0}',
            (new NestingTransformer())->lower($css)
        );
    },
    'wordpress nested layer rules lower for block theme delivery' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-query {
  @layer theme.blocks {
    & .wp-block-post-title {
      color: yellow;
    }
  }
}
CSS;

        $t->same(
            '@layer theme.blocks{.wp-block-query .wp-block-post-title{color:#ff0}}',
            (new NestingTransformer())->lower($css)
        );
    },
    'wordpress styled-jsx placeholders recover inside nested block CSS' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-cover {
  --wp--custom--cover-offset: --styled-jsx-placeholder-0__;
  background: linear-gradient(to bottom, --styled-jsx-placeholder-1__, var(--wp--preset--color--base));

  .wp-block-cover__inner-container {
    transform: translateY(calc(var(--wp--style--block-gap) + --styled-jsx-placeholder-2__px));
  }

  @media (max-width: --styled-jsx-placeholder-3__) {
    .wp-block-heading {
      margin-block-start: calc(10px + --styled-jsx-placeholder-4__px);
    }
  }
}
CSS;

        $t->same(
            '.wp-block-cover{--wp--custom--cover-offset:--styled-jsx-placeholder-0__;background:linear-gradient(to bottom,--styled-jsx-placeholder-1__,var(--wp--preset--color--base))}.wp-block-cover .wp-block-cover__inner-container{transform:translateY(calc(var(--wp--style--block-gap) + --styled-jsx-placeholder-2__px))}@media (width<=--styled-jsx-placeholder-3__){.wp-block-cover .wp-block-heading{margin-block-start:calc(10px + --styled-jsx-placeholder-4__px)}}',
            (new NestingTransformer())->lower($css)
        );
    },
];
