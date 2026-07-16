<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
  <xsl:output method="html" encoding="UTF-8" omit-xml-declaration="yes"/>
  <!--
    A deliberately bounded, namespace-agnostic source reference for generic
    XML. It uses local element names only, so it is useful for XML documents
    that share common document vocabulary without claiming a schema-specific
    interpretation of arbitrary elements.
  -->
  <xsl:template match="/">
    <html><head><meta charset="UTF-8"/><title>libxml2/libxslt generic XML source reference</title></head>
      <body><main class="xml-source-reference"><xsl:apply-templates/></main></body>
    </html>
  </xsl:template>

  <xsl:template match="*[local-name() = 'title' or local-name() = 'heading' or local-name() = 'article-title' or local-name() = 'book-title' or local-name() = 'subtitle' or local-name() = 'trans-title' or local-name() = 'alt-title']">
    <xsl:variable name="section-depth" select="count(ancestor::*[local-name() = 'section' or local-name() = 'sec' or local-name() = 'chapter' or local-name() = 'part' or local-name() = 'appendix' or local-name() = 'app'])"/>
    <xsl:choose>
      <xsl:when test="$section-depth &lt;= 1"><h1><xsl:apply-templates/></h1></xsl:when>
      <xsl:when test="$section-depth = 2"><h2><xsl:apply-templates/></h2></xsl:when>
      <xsl:when test="$section-depth = 3"><h3><xsl:apply-templates/></h3></xsl:when>
      <xsl:when test="$section-depth = 4"><h4><xsl:apply-templates/></h4></xsl:when>
      <xsl:when test="$section-depth = 5"><h5><xsl:apply-templates/></h5></xsl:when>
      <xsl:otherwise><h6><xsl:apply-templates/></h6></xsl:otherwise>
    </xsl:choose>
  </xsl:template>

  <xsl:template match="*[local-name() = 'p' or local-name() = 'para' or local-name() = 'paragraph' or local-name() = 'license-p']">
    <p><xsl:apply-templates/></p>
  </xsl:template>

  <xsl:template match="*[local-name() = 'ul' or local-name() = 'bullet-list' or local-name() = 'unordered-list' or local-name() = 'list' or local-name() = 'itemizedlist']">
    <ul><xsl:apply-templates select="*[local-name() = 'li' or local-name() = 'item' or local-name() = 'list-item' or local-name() = 'listitem']"/></ul>
  </xsl:template>

  <xsl:template match="*[local-name() = 'ol' or local-name() = 'ordered-list' or local-name() = 'numbered-list' or local-name() = 'orderedlist']">
    <ol><xsl:apply-templates select="*[local-name() = 'li' or local-name() = 'item' or local-name() = 'list-item' or local-name() = 'listitem']"/></ol>
  </xsl:template>

  <xsl:template match="*[local-name() = 'li' or local-name() = 'item' or local-name() = 'list-item' or local-name() = 'listitem']">
    <li><xsl:apply-templates/></li>
  </xsl:template>

  <xsl:template match="*[local-name() = 'table' or local-name() = 'informaltable']">
    <table><xsl:apply-templates/></table>
  </xsl:template>

  <xsl:template match="*[local-name() = 'thead' or local-name() = 'tbody' or local-name() = 'tfoot']">
    <xsl:element name="{local-name()}"><xsl:apply-templates/></xsl:element>
  </xsl:template>

  <xsl:template match="*[local-name() = 'tr' or local-name() = 'row']">
    <tr><xsl:apply-templates select="*[local-name() = 'th' or local-name() = 'td' or local-name() = 'entry']"/></tr>
  </xsl:template>

  <xsl:template match="*[local-name() = 'th']">
    <th><xsl:apply-templates/></th>
  </xsl:template>

  <xsl:template match="*[local-name() = 'td' or local-name() = 'entry']">
    <td><xsl:apply-templates/></td>
  </xsl:template>

  <xsl:template match="*[local-name() = 'a' or local-name() = 'link' or local-name() = 'xref' or local-name() = 'ext-link' or local-name() = 'uri']">
    <a>
      <xsl:if test="@href"><xsl:attribute name="href"><xsl:value-of select="@href"/></xsl:attribute></xsl:if>
      <xsl:if test="not(@href) and @*[local-name() = 'href' and namespace-uri() != '']"><xsl:attribute name="href"><xsl:value-of select="@*[local-name() = 'href' and namespace-uri() != '']"/></xsl:attribute></xsl:if>
      <xsl:if test="not(@href) and not(@*[local-name() = 'href']) and @rid"><xsl:attribute name="href">#<xsl:value-of select="@rid"/></xsl:attribute></xsl:if>
      <xsl:apply-templates/>
    </a>
  </xsl:template>

  <xsl:template match="*[local-name() = 'bold' or local-name() = 'b' or local-name() = 'strong']">
    <strong><xsl:apply-templates/></strong>
  </xsl:template>

  <xsl:template match="*[local-name() = 'italic' or local-name() = 'i' or local-name() = 'em' or local-name() = 'emph']">
    <em><xsl:apply-templates/></em>
  </xsl:template>

  <xsl:template match="*[local-name() = 'sup']"><sup><xsl:apply-templates/></sup></xsl:template>
  <xsl:template match="*[local-name() = 'sub']"><sub><xsl:apply-templates/></sub></xsl:template>
  <xsl:template match="*[local-name() = 'code' or local-name() = 'monospace']"><code><xsl:apply-templates/></code></xsl:template>
  <xsl:template match="*[local-name() = 'br']"><br/></xsl:template>

  <!-- Unknown elements are transparent containers. -->
  <xsl:template match="*"><xsl:apply-templates/></xsl:template>
  <xsl:template match="text()"><xsl:value-of select="."/></xsl:template>
</xsl:stylesheet>
