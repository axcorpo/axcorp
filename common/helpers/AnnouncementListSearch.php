<?php

namespace common\helpers;

use Yii;

/**
 * Stable GET query key for announcement listing text search.
 * Do not use {@see Inflector::slug()} on translated "Search" labels — it can mangle UTF-8 (e.g. "ctare" instead of "cautare").
 */
final class AnnouncementListSearch
{
	/**
	 * Query parameter name for the unified search field.
	 * Override globally with {@see \Yii::$app->params} `announcementSearchRequestKey` (ASCII letters, digits, _- only).
	 */
	public static function getQueryParam(): string
	{
		$key = Yii::$app->params['announcementSearchRequestKey'] ?? null;
		if (is_string($key) && $key !== '' && preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,62}$/', $key)) {
			return $key;
		}
		$lang = strtolower((string) Yii::$app->language);
		if (str_starts_with($lang, 'ro')) {
			return 'cautare';
		}
		return 'search';
	}

	/**
	 * Normalizes free-form text for the `announcement_translation.search_text` denormalized column
	 * and for the search query itself, so both sides of the LIKE match on the same form.
	 *
	 * Steps:
	 *   - decode HTML entities (&amp;, &nbsp;, &#259; …)
	 *   - strip HTML tags (content column stores rich HTML; tags would pollute matches)
	 *   - lowercase (UTF-8)
	 *   - fold Romanian diacritics (ă/â → a, î → i, ș/ş → s, ț/ţ → t)
	 *   - collapse all whitespace (incl. NBSP after entity decode) to single spaces
	 */
	public static function normalize(string $text): string
	{
		if ($text === '') {
			return '';
		}
		$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = strip_tags($text);
		$text = mb_strtolower($text, 'UTF-8');
		$text = self::stripDiacritics($text);
		$text = preg_replace('/\s+/u', ' ', $text);
		return trim((string) $text);
	}

	/**
	 * Splits the user's search phrase into meaningful tokens for per-term SQL LIKE matching.
	 *
	 * Tokens are diacritic-folded + lowercased (via {@see normalize()}) so they match the
	 * `search_text` haystack — both sides share the same form.
	 *
	 * Behavior:
	 *   - splits on any non-letter / non-digit (handles diacritics via \p{L})
	 *   - drops Romanian + English filler words (and, or, in, cu, pe, de, la, …)
	 *   - keeps numeric tokens regardless of length (so "5", "2024", "100k" stay)
	 *   - keeps alpha tokens with length >= 2 (single letters are noise)
	 *   - dedupes while preserving first-occurrence order
	 *
	 * @return string[]
	 */
	public static function tokenize(string $search): array
	{
		$normalized = self::normalize($search);
		if ($normalized === '') {
			return [];
		}
		$parts = preg_split('/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);
		if (!$parts) {
			return [];
		}
		$stop = self::stopWordSet();
		$tokens = [];
		$seen = [];
		foreach ($parts as $p) {
			if (isset($stop[$p])) {
				continue;
			}
			if (!ctype_digit($p) && mb_strlen($p, 'UTF-8') < 2) {
				continue;
			}
			if (isset($seen[$p])) {
				continue;
			}
			$seen[$p] = true;
			$tokens[] = $p;
		}
		return $tokens;
	}

	/**
	 * Splits tokens into those the InnoDB FULLTEXT index can serve and those it cannot.
	 *
	 * A token is NOT indexable when it is shorter than `innodb_ft_min_token_size`
	 * (default 3 — adjust here if that server variable is changed) or when it is on
	 * InnoDB's built-in FULLTEXT stopword list (words never indexed, so a required
	 * `+word*` term would wrongly match nothing). Unindexable tokens keep the LIKE
	 * fallback — see Announcement::listSearchSqlLikeOr().
	 *
	 * @param string[] $tokens Output of {@see tokenize()}.
	 * @return array{0: string[], 1: string[]} `[indexable, unindexable]`
	 */
	public static function partitionFulltextTokens(array $tokens): array
	{
		// INFORMATION_SCHEMA.INNODB_FT_DEFAULT_STOPWORD (MySQL 5.7/8.0)
		static $innodbStopwords = [
			'a' => 1, 'about' => 1, 'an' => 1, 'are' => 1, 'as' => 1, 'at' => 1, 'be' => 1, 'by' => 1,
			'com' => 1, 'de' => 1, 'en' => 1, 'for' => 1, 'from' => 1, 'how' => 1, 'i' => 1, 'in' => 1,
			'is' => 1, 'it' => 1, 'la' => 1, 'of' => 1, 'on' => 1, 'or' => 1, 'that' => 1, 'the' => 1,
			'this' => 1, 'to' => 1, 'was' => 1, 'what' => 1, 'when' => 1, 'where' => 1, 'who' => 1,
			'will' => 1, 'with' => 1, 'und' => 1, 'www' => 1,
		];
		$indexable = [];
		$unindexable = [];
		foreach ($tokens as $token) {
			if (mb_strlen($token, 'UTF-8') >= 3 && !isset($innodbStopwords[$token])) {
				$indexable[] = $token;
			} else {
				$unindexable[] = $token;
			}
		}
		return [$indexable, $unindexable];
	}

	/**
	 * Builds the boolean-mode MATCH ... AGAINST query: every token required, as a word prefix
	 * (`+beton* +celular*`). Tokens come from {@see tokenize()} so they contain only letters
	 * and digits — no boolean-mode operators to escape.
	 *
	 * @param string[] $tokens
	 */
	public static function booleanFulltextQuery(array $tokens): string
	{
		return implode(' ', array_map(static function (string $token): string {
			return '+' . $token . '*';
		}, $tokens));
	}

	private static function stripDiacritics(string $s): string
	{
		return strtr($s, [
			'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
			'Ă' => 'a', 'Â' => 'a', 'Î' => 'i', 'Ș' => 's', 'Ş' => 's', 'Ț' => 't', 'Ţ' => 't',
		]);
	}

	/**
	 * Diacritic-stripped, lowercased stop-word set (keys are stop words, values are 1).
	 * Members are compared against tokens after the same normalization, so the list itself
	 * is written without diacritics.
	 */
	private static function stopWordSet(): array
	{
		static $set = null;
		if ($set !== null) {
			return $set;
		}
		$list = [
			// Romanian connectors / particles / pronouns / common verbs
			'a', 'ai', 'am', 'ar', 'are', 'asa', 'asta', 'aceasta', 'acea', 'acel', 'acei', 'acele',
			'acest', 'acesta', 'acesti', 'aceste', 'aceea', 'aceia', 'atunci', 'au', 'avea', 'aveam',
			'aveau', 'avem', 'aveti',
			'ba', 'bine',
			'ca', 'care', 'carei', 'caror', 'catre', 'ce', 'cea', 'cei', 'cel', 'cele', 'ceva',
			'cine', 'cu', 'cum', 'cand', 'cat', 'cati', 'cate',
			'da', 'dar', 'de', 'deci', 'deja', 'des', 'despre', 'din', 'dintr', 'dintre',
			'doar', 'doi', 'doua', 'daca', 'dupa',
			'e', 'ea', 'ei', 'ele', 'eram', 'este', 'esti', 'eu',
			'fara', 'fi', 'fie', 'fii', 'fiind', 'foarte', 'fost',
			'i', 'iar', 'iata', 'in', 'inca', 'inainte', 'insa', 'intr', 'intre', 'isi', 'iti',
			'la', 'langa', 'le', 'li', 'lor', 'lui',
			'mai', 'mea', 'mei', 'mele', 'meu', 'mi', 'mine', 'mult', 'multa', 'multe', 'multi',
			'ne', 'ni', 'niste', 'noi', 'nostru', 'noastra', 'nostri', 'noastre', 'nu',
			'o', 'or', 'ori', 'orice',
			'pana', 'pe', 'pentru', 'peste', 'prin', 'printr', 'putin',
			'sa', 'sale', 'sau', 'se', 'si', 'sub', 'sunt', 'spre',
			'ta', 'tai', 'tale', 'tau', 'te', 'ti', 'tine', 'toata', 'toate', 'tot', 'toti',
			'totusi', 'totul',
			'un', 'una', 'unei', 'unele', 'unii', 'unu', 'unul',
			'va', 'vor', 'voi', 'vom', 'vostru', 'voastra', 'vreau', 'vrei', 'vrea', 'vrem', 'vreti',
			// English
			'and', 'or', 'the', 'an', 'in', 'on', 'of', 'for', 'to', 'with', 'at', 'by', 'from',
			'as', 'but', 'if', 'then', 'that', 'this', 'these', 'those', 'it', 'its', 'is', 'are',
			'was', 'were', 'be', 'been', 'being', 'has', 'have', 'had', 'do', 'does', 'did',
			'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'not', 'no', 'yes',
			'so', 'very', 'too', 'also', 'just', 'only', 'more', 'less', 'much', 'many', 'some',
			'any', 'all', 'both', 'each', 'every', 'few', 'several',
			'my', 'mine', 'your', 'yours', 'his', 'her', 'hers', 'our', 'ours', 'their', 'theirs',
			'what', 'which', 'who', 'whom', 'where', 'when', 'why', 'how',
			'i', 'you', 'he', 'she', 'we', 'they', 'me', 'him', 'us', 'them', 'than', 'about',
			'under', 'over', 'into', 'between', 'within', 'without', 'near',
		];
		$set = [];
		foreach ($list as $w) {
			$set[self::stripDiacritics($w)] = 1;
		}
		return $set;
	}
}
