<?xml version="1.0"?>
<xsl:stylesheet version="1.0" xmlns:xsl=
  "http://www.w3.org/1999/XSL/Transform"
  xmlns:php="http://php.net/xsl" >
<xsl:output method="xml" 
  doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-
transitional.dtd" 
  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//
EN" indent="yes"/>

<xsl:template match="/consume">
<html>
  <head>
  </head>
  <body>
    <div id="head">
      <h2 class="recipeName"><xsl:value-of select="name" /></h2>
      <h3 class="recipeVersion"><xsl:value-of select="version" /></h3>
    </div>
    <div id="content">
      <h3 class="recipeUpdated"><xsl:value-of select="php:function('date', 'F j, Y, g:i a', php:function('strtotime',string(updated)))" /></h3>
      <table border="1">
        <thead>
        <tr bgcolor="#9acd32">
          <xsl:for-each select="variables/*/*">
            <xsl:if test="label != ''">
              <th align="left"><xsl:value-of select="label" /></th>
            </xsl:if>
          </xsl:for-each>
        </tr>
        </thead>
        <tbody>
        <tr>
        <xsl:for-each select="variables/*/*">
          <xsl:if test="label != ''">
            <td>
              <xsl:value-of select="value" />
            </td>
          </xsl:if>
        </xsl:for-each>
        </tr>
        </tbody>
      </table>
    </div>
  </body>
</html>
</xsl:template>
</xsl:stylesheet>