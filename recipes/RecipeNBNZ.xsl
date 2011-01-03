<?xml version="1.0"?>
<xsl:stylesheet version="1.0" xmlns:xsl=
  "http://www.w3.org/1999/XSL/Transform"
  xmlns:php="http://php.net/xsl" >
<xsl:output method="xml" 
  doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-
transitional.dtd" 
  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//
EN" indent="yes"/>

<xsl:key name="accountdetails" match="/consume/variable/variable/variable" use="concat(label, ../../label)"/>

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
      <xsl:for-each select="variable">
        <table border="1">
          <caption><xsl:value-of select="label" /></caption>
          <thead>
          <tr bgcolor="#9acd32">
            <xsl:apply-templates select="variable/*[generate-id() = generate-id(key('accountdetails',concat(label, ../../label))[1])]"/>
          </tr>
          </thead>
          <tbody>
          <xsl:for-each select="variable" >
            <tr>
            <xsl:for-each select="variable">
              <td>
                <xsl:value-of select="value" />
              </td>
            </xsl:for-each>
            </tr>
          </xsl:for-each>
          </tbody>
        </table>
      </xsl:for-each>
    </div>
  </body>
</html>
</xsl:template>

<xsl:template match="*">
  <th align="left"><xsl:value-of select="label" /></th>
</xsl:template>

</xsl:stylesheet>
