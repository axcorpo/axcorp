<?xml version="1.0" encoding="UTF-8"?>
<!--
	Browser-only presentation for /sitemap (a static file next to index.php, so it loads
	without any URL rule): browsers without a
	native XML tree viewer (Chrome, Edge) would otherwise render the sitemap as a wall of
	concatenated text. Crawlers ignore the xml-stylesheet processing instruction entirely.
	Styled after the site's dark theme.
-->
<xsl:stylesheet version="1.0"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:sm="http://www.sitemaps.org/schemas/sitemap/0.9"
	xmlns:xhtml="http://www.w3.org/1999/xhtml">
	<xsl:output method="html" encoding="UTF-8" indent="yes"/>

	<xsl:template match="/">
		<html lang="ro">
			<head>
				<meta charset="utf-8"/>
				<meta name="viewport" content="width=device-width, initial-scale=1"/>
				<title>Sitemap – AXCORP</title>
				<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bai+Jamjuree:wght@500;600;700&amp;family=DM+Sans:wght@400;500&amp;display=swap"/>
				<style>
					* { box-sizing: border-box; }
					body { margin: 0; padding: 48px 16px; font: 15px/1.6 "DM Sans", -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: rgba(255, 255, 255, 0.75); background: #000; }
					.wrap { max-width: 1180px; margin: 0 auto; }
					.top { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 32px; }
					.logo img { max-width: 200px; height: auto; display: block; }
					.back { color: #fff; text-decoration: none; padding: 10px 22px; border-radius: 999px; background-image: linear-gradient(90deg, #A291FE 0%, #5C57E7 50%, #71BBF9 100%); font-weight: 600; font-size: 14px; }
					.sub { display: inline-flex; align-items: center; gap: 8px; color: #fff; font-weight: 500; margin: 0 0 8px; }
					.sub::before { content: "✦"; color: #A291FE; }
					h1 { margin: 0 0 6px; font-family: "Bai Jamjuree", sans-serif; font-size: clamp(32px, 4vw, 48px); line-height: 1.15; color: #fff; letter-spacing: -0.02em; }
					p.meta { margin: 0 0 28px; color: rgba(255, 255, 255, 0.5); }
					.card { background: #131313; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px; overflow: hidden; }
					.table-wrap { overflow-x: auto; }
					table { width: 100%; border-collapse: collapse; }
					th, td { padding: 14px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.07); text-align: left; vertical-align: top; }
					th { color: #fff; font-family: "Bai Jamjuree", sans-serif; font-weight: 600; white-space: nowrap; background: rgba(92, 87, 231, 0.18); }
					tr:last-child td { border-bottom: 0; }
					tr:hover td { background: rgba(255, 255, 255, 0.03); }
					td.num { color: rgba(255, 255, 255, 0.4); text-align: right; white-space: nowrap; width: 1%; }
					td.nowrap { white-space: nowrap; }
					a { color: #C8BEFF; text-decoration: none; word-break: break-all; }
					a:hover { color: #fff; text-decoration: underline; }
					.badge { display: inline-block; padding: 2px 10px; border-radius: 999px; background: rgba(255, 255, 255, 0.06); color: rgba(255, 255, 255, 0.75); font-size: 13px; }
					footer { margin-top: 24px; color: rgba(255, 255, 255, 0.4); font-size: 13px; text-align: center; }
				</style>
			</head>
			<body>
				<div class="wrap">
					<div class="top">
						<a class="logo" href="/"><img src="/img/logo-alt.png" alt="AXCORP"/></a>
						<a class="back" href="/">Înapoi la site</a>
					</div>
					<p class="sub">Hartă site</p>
					<h1>Sitemap XML</h1>
					<p class="meta">
						<xsl:value-of select="count(sm:urlset/sm:url)"/> pagini indexabile
					</p>
					<div class="card">
						<div class="table-wrap">
							<table>
								<tr>
									<th>#</th>
									<th>Adresă</th>
									<th>Frecvență</th>
									<th>Prioritate</th>
									<th>Ultima modificare</th>
								</tr>
								<xsl:for-each select="sm:urlset/sm:url">
									<tr>
										<td class="num"><xsl:value-of select="position()"/></td>
										<td>
											<a href="{sm:loc}"><xsl:value-of select="sm:loc"/></a>
										</td>
										<td class="nowrap"><span class="badge"><xsl:value-of select="sm:changefreq"/></span></td>
										<td class="nowrap"><xsl:value-of select="sm:priority"/></td>
										<td class="nowrap"><xsl:value-of select="substring(sm:lastmod, 1, 10)"/></td>
									</tr>
								</xsl:for-each>
							</table>
						</div>
					</div>
					<footer>AXCORP – Conectăm afaceri. Susținem creșterea.</footer>
				</div>
			</body>
		</html>
	</xsl:template>
</xsl:stylesheet>
