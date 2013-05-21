<?xml version="1.0" encoding="UTF-8"?>
<!--

This is the XSL for the Table of Contents for PonyDocs's PDFBook output.  Use this to customize how the table of contents renders in PDF output.

Refer to: http://code.google.com/p/wkhtmltopdf/

-->
<xsl:stylesheet version="1.0"
                xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:outline="http://code.google.com/p/wkhtmltopdf/outline"
                xmlns="http://www.w3.org/1999/xhtml">
  <xsl:output doctype-public="-//W3C//DTD XHTML 1.0 Strict//EN"
              doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"
              indent="yes" />
  <xsl:template match="outline:outline">
    <html>
      <head>
        <title>Table of Contents</title>
        <style>
         body {
            font-family: 'Myriad Pro';
            font-size: 12pt;
         }
          h1 {
            text-align: left;
          }

            /**
         * div {border-bottom: 1px dashed #65a5cc;}
         */


          span {float: right;}

          li {list-style: none;}
          ul {
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 16pt;
          }

          ul ul {
            font-size: 11pt; 
            font-weight: normal;
          }

          ul {padding-left: 0em;}

          ul ul {padding-left: .5in;}

          a {
           text-decoration:none; color: #65a5cc;
           display: block;
          }
          ul ul ul {
            display: none;
          }
        </style>
      </head>
      <body>
        <h1>Table of Contents</h1>
        <ul><xsl:apply-templates select="outline:item/outline:item"/></ul>
      </body>
    </html>
  </xsl:template>
  <xsl:template match="outline:item">
    <li>
      <xsl:if test="@title!=''">
       <xsl:if test="@title!='Table of Contents'">
          <a>
            <xsl:if test="@link">
              <xsl:attribute name="href"><xsl:value-of select="@link"/></xsl:attribute>
            </xsl:if>
            <xsl:if test="@backLink">
              <xsl:attribute name="name"><xsl:value-of select="@backLink"/></xsl:attribute>
            </xsl:if>
            <div>
            <span> <xsl:value-of select="@page" /> </span>
            <xsl:value-of select="@title" /> 
            </div>
            </a>
        </xsl:if>
      </xsl:if>
      <ul>
        <xsl:apply-templates select="outline:item"/>
      </ul>
    </li>
  </xsl:template>
</xsl:stylesheet>
