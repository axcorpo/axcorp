<?php

namespace common\helpers;

use yii\helpers\HtmlPurifier;

/**
 * Allow-list sanitiser for rich text that came from outside the admin.
 *
 * The support-ticket editors are open to every registered customer, and what they write
 * is printed as HTML in the helpdesk screens an administrator reads. Nothing sanitised
 * it, in either direction, which made a ticket the shortest path from an ordinary
 * account into an administrator's session.
 *
 * Same shape as the workspace app's Announcement::sanitizeContentHtml(): keep exactly
 * the formatting the editor offers and drop everything else, rather than hunting for
 * dangerous constructs. A blocklist of `<script>` and friends — which is what the
 * workspace's older ApplicationBootstrap::sanitize() does — loses to the next encoding
 * trick; an allow-list only ever lets through what it was told to.
 *
 * Purifying on the way in would leave whatever is already stored, so callers apply this
 * at save time and the helpdesk views apply it again on the way out.
 */
class HtmlSanitizer
{
	/**
	 * The formatting the support-ticket editor offers, and nothing else: no images, no
	 * iframes, no embeds, no event-handler attributes, no inline styles beyond the
	 * editor's own alignment buttons.
	 */
	const RICH_TEXT_CONFIG = [
		'HTML.Allowed' => 'p[style],br,hr,strong,b,em,i,u,s,blockquote,pre,code,'
			. 'ul[style],ol[style],li[style],a[href|target|rel],'
			. 'table,thead,tbody,tr,th,td,h1,h2,h3,h4,h5,h6,span',
		// Only the layout styles the editor's align / indent buttons emit; any other
		// inline CSS (colours, fonts, positioning) is stripped, so nothing can be
		// overlaid on the surrounding page.
		'CSS.AllowedProperties' => ['text-align', 'padding-left', 'margin-left'],
		// Purifier drops target= unless the frame target is allowed.
		'Attr.AllowedFrameTargets' => ['_blank'],
		'HTML.Nofollow' => true,
	];

	/**
	 * Reduces rich text to the allowed subset.
	 *
	 * @param string|null $value
	 * @return string|null null and '' pass through untouched
	 */
	public static function richText($value)
	{
		if ($value === null || $value === '') {
			return $value;
		}

		return HtmlPurifier::process($value, static::RICH_TEXT_CONFIG);
	}

	/**
	 * Formatting the website content (e.g. the homepage carousel) is built from: the
	 * layout wrappers, their classes and the wow.js animation data, links styled as
	 * buttons. Still an allow-list: no images, no scripts, no event-handler attributes,
	 * no `javascript:` links.
	 */
	const WEBSITE_ELEMENTS = [
		'div', 'p', 'span', 'br', 'hr', 'strong', 'b', 'em', 'i', 'u', 's', 'small', 'blockquote',
		'ul', 'ol', 'li', 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
	];

	/**
	 * The wow.js attributes the website markup animates with; HTML Purifier does not know
	 * data-* attributes, so they are declared on each allowed element.
	 */
	const WEBSITE_DATA_ATTRIBUTES = ['data-wow-duration', 'data-wow-delay', 'data-wow-offset', 'data-wow-iteration'];

	/**
	 * Reduces website content to the allowed subset.
	 *
	 * @param string|null $value
	 * @return string|null null and '' pass through untouched
	 */
	public static function websiteContent($value)
	{
		if ($value === null || $value === '') {
			return $value;
		}

		return HtmlPurifier::process($value, function (\HTMLPurifier_Config $config) {
			$attributes = implode('|', array_merge(['class', 'style', 'title'], static::WEBSITE_DATA_ATTRIBUTES));
			$allowed = [];
			foreach (static::WEBSITE_ELEMENTS as $element) {
				$allowed[] = $element === 'a'
					? "a[{$attributes}|href|target|rel]"
					: "{$element}[{$attributes}]";
			}
			$config->set('HTML.Allowed', implode(',', $allowed));
			$config->set('CSS.AllowedProperties', [
				'text-align', 'width', 'max-width', 'padding-left', 'margin-left',
			]);
			$config->set('Attr.AllowedFrameTargets', ['_blank']);
			$config->set('HTML.DefinitionID', 'masteranunturi-website-content');
			$config->set('HTML.DefinitionRev', 1);
			if ($definition = $config->maybeGetRawHTMLDefinition()) {
				foreach (static::WEBSITE_ELEMENTS as $element) {
					foreach (static::WEBSITE_DATA_ATTRIBUTES as $attribute) {
						$definition->addAttribute($element, $attribute, 'Text');
					}
				}
			}
		});
	}

	/**
	 * What makes stored HTML executable in a browser. Used as a cheap test first, so that
	 * HTML without any of it is returned byte for byte and only a hit goes through the
	 * DOM parser.
	 */
	const EXECUTABLE_PATTERN = '/<\s*\/?\s*(script|iframe|frame|frameset|object|embed|applet|base|meta|link|form|svg|math|template|noscript)\b'
		. '|[\s\/"\']on[a-z]+\s*='
		. '|(javascript|vbscript|livescript)\s*:'
		. '|data\s*:\s*text\/html'
		. '|srcdoc|formaction|expression\s*\(/i';

	/**
	 * Elements removed together with their content.
	 */
	const EXECUTABLE_ELEMENTS = [
		'script', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'base', 'meta', 'link',
		'form', 'svg', 'math', 'template', 'noscript',
	];

	/**
	 * Attributes that load or navigate to a URL.
	 */
	const URL_ATTRIBUTES = ['href', 'src', 'action', 'background', 'poster', 'data', 'lowsrc', 'dynsrc', 'xlink:href', 'cite', 'longdesc'];

	/**
	 * Removes what can execute from stored HTML and keeps everything else as it is.
	 *
	 * For the HTML the admin stores and the applications print as HTML or render to PDF:
	 * document and email templates, articles, pages, notes. An allow-list would strip the
	 * tables, inline styles and images those are made of, so this removes exactly the
	 * executable parts instead -- script-capable elements, `on*` handlers, `javascript:`
	 * and similar URLs, CSS expressions -- working on the parsed DOM rather than on text,
	 * so an encoding trick cannot hide a handler from it. Shortcodes such as
	 * `{{APP_NAME}}` are plain text to the parser and pass through untouched.
	 *
	 * @param string|null $value
	 * @return string|null the value unchanged when there was nothing executable in it
	 */
	public static function stripExecutable($value)
	{
		if (!is_string($value) || $value === '' || !preg_match(static::EXECUTABLE_PATTERN, html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
			return $value;
		}

		$document = new \DOMDocument('1.0', 'UTF-8');
		$previous = libxml_use_internal_errors(true);
		$document->loadHTML(
			'<?xml encoding="UTF-8"?><div id="__stored_html_root">' . $value . '</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
		);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		$xpath = new \DOMXPath($document);
		foreach (static::EXECUTABLE_ELEMENTS as $name) {
			foreach (iterator_to_array($document->getElementsByTagName($name)) as $node) {
				$node->parentNode->removeChild($node);
			}
		}
		foreach (iterator_to_array($xpath->query('//*[@*]')) as $element) {
			/** @var \DOMElement $element */
			foreach (iterator_to_array($element->attributes) as $attribute) {
				$name = strtolower($attribute->nodeName);
				// Control characters and whitespace are ignored by browsers inside a scheme.
				$compact = preg_replace('/[\x00-\x20]+/', '', html_entity_decode($attribute->value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
				if (strpos($name, 'on') === 0
					|| in_array($name, ['srcdoc', 'formaction'], true)
					|| (in_array($name, static::URL_ATTRIBUTES, true) && preg_match('/^(javascript|vbscript|livescript|data:(?!image\/(png|jpe?g|gif|webp);))/i', $compact))
					|| ($name === 'style' && preg_match('/expression\(|javascript:|vbscript:|behavior:|-moz-binding/i', $compact))
				) {
					$element->removeAttributeNode($attribute);
				}
			}
		}

		$root = $document->getElementById('__stored_html_root');
		$html = '';
		foreach ($root->childNodes as $child) {
			$html .= $document->saveHTML($child);
		}

		return $html;
	}

	/**
	 * Removes the markup from a plain-text value (a name, a title, a subject line).
	 *
	 * Only a value that actually contains a tag is touched, so text like "< 5 ani" or
	 * "a & b" is left alone.
	 *
	 * @param string|null $value
	 * @return string|null
	 */
	public static function plainText($value)
	{
		if (!is_string($value) || !preg_match('/<[a-zA-Z!\/?]/', $value)) {
			return $value;
		}

		// strip_tags() keeps what is between the tags; for a script or a style that is
		// code, not text, so those go with their content.
		$value = preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', $value);

		return trim(strip_tags($value));
	}

	/**
	 * A stored URL that is safe to put in an href.
	 *
	 * Encoding does nothing here: `javascript:alert(1)` contains no markup and survives
	 * Html::encode() intact, so a link built from a stored URL runs it on click. Only
	 * the schemes that actually navigate are allowed through.
	 *
	 * @param string|null $url
	 * @return string|null null when the URL is not one we are willing to link to
	 */
	public static function href($url)
	{
		if ($url === null || trim($url) === '') {
			return null;
		}

		$url = trim($url);
		// Relative and protocol-relative URLs stay on our own origin.
		if (preg_match('#^(/|\#|\?)#', $url)) {
			return $url;
		}

		$scheme = parse_url($url, PHP_URL_SCHEME);
		if ($scheme === null) {
			// No scheme at all ("example.com/x") -- treat it as http, which is what a
			// browser does with a bare host anyway.
			return 'http://' . $url;
		}

		return in_array(strtolower($scheme), ['http', 'https', 'mailto', 'tel'], true) ? $url : null;
	}
}
