<?php

namespace common\components;

use richweber\google\translate\Translation;
use yii\helpers\Json;

/**
 * Google Translate API client with multi-segment requests (one translation per `q`, same order).
 *
 * @see https://cloud.google.com/translate/docs/reference/rest/v2/translate
 */
class GoogleTranslation extends Translation
{
	/** Max `q` segments per request (API limit 128). */
	public const BATCH_MAX_ITEMS = 100;

	/** Conservative total characters per request. */
	public const BATCH_MAX_CHARS = 4500;

	/**
	 * Translates many strings in as few API calls as possible; output order matches input.
	 *
	 * @param string $source
	 * @param string $target
	 * @param string[] $texts
	 * @return string[]
	 */
	public function translateMany(string $source, string $target, array $texts): array
	{
		$results = array_fill(0, count($texts), '');
		$pending = [];

		foreach ($texts as $index => $text) {
			$text = (string)$text;
			if ($text === '') {
				continue;
			}
			$pending[$index] = $text;
		}

		foreach ($this->buildBatches($pending) as $batch) {
			$indices = array_keys($batch);
			$chunk = array_values($batch);
			$translated = $this->translateBatch($source, $target, $chunk);
			foreach ($indices as $position => $index) {
				$results[$index] = isset($translated[$position])
					? trim((string)$translated[$position])
					: $batch[$index];
			}
		}

		return $results;
	}

	/**
	 * One API request with multiple `q` parameters; response order matches input.
	 *
	 * @param string $source
	 * @param string $target
	 * @param string[] $texts Non-empty strings only.
	 * @return string[]
	 */
	public function translateBatch(string $source, string $target, array $texts): array
	{
		if ($texts === []) {
			return [];
		}
		if ((string)$this->key === '') {
			throw new \RuntimeException('Google Translate API key is not configured (params: google.translateKey).');
		}

		$response = $this->getResponse($this->buildBatchRequestUrl($source, $target, $texts));
		$items = $response['data']['translations'] ?? [];

		$out = [];
		foreach ($items as $item) {
			$out[] = (string)($item['translatedText'] ?? '');
		}

		if (count($out) !== count($texts)) {
			throw new \RuntimeException('Google Translate returned ' . count($out) . ' segments for ' . count($texts) . ' inputs.');
		}

		return $out;
	}

	/**
	 * @param array<int, string> $texts
	 * @return array<int, array<int, string>>
	 */
	protected function buildBatches(array $texts): array
	{
		$batches = [];
		$current = [];
		$currentChars = 0;

		foreach ($texts as $index => $text) {
			$len = strlen($text);
			if (
				$current !== []
				&& (
					count($current) >= self::BATCH_MAX_ITEMS
					|| $currentChars + $len > self::BATCH_MAX_CHARS
				)
			) {
				$batches[] = $current;
				$current = [];
				$currentChars = 0;
			}
			$current[$index] = $text;
			$currentChars += $len;
		}

		if ($current !== []) {
			$batches[] = $current;
		}

		return $batches;
	}

	/**
	 * @param string[] $texts
	 */
	protected function buildBatchRequestUrl(string $source, string $target, array $texts): string
	{
		$query = http_build_query([
			'key' => $this->key,
			'source' => $source,
			'target' => $target,
		]);

		foreach ($texts as $text) {
			$query .= '&q=' . rawurlencode($text);
		}

		return self::API_URL . '?' . $query;
	}

	/**
	 * @inheritdoc
	 */
	protected function getResponse($request)
	{
		// ignore_errors keeps the body on a non-2xx status: Google sends its actual
		// error as JSON there, which would otherwise be lost (file_get_contents
		// returns false and the decode failed as an unhelpful "Syntax error").
		$response = file_get_contents($request, false, stream_context_create([
			'http' => ['ignore_errors' => true],
		]));
		if ($response === false) {
			$error = error_get_last();
			throw new \RuntimeException('Google Translate request failed: ' . ($error['message'] ?? 'no response'));
		}

		try {
			$decoded = Json::decode($response, true);
		} catch (\Throwable $e) {
			throw new \RuntimeException('Google Translate returned a non-JSON response: ' . mb_substr(trim(strip_tags($response)), 0, 300));
		}

		if (isset($decoded['error'])) {
			$message = $decoded['error']['message'] ?? Json::encode($decoded['error']);
			throw new \RuntimeException((string)$message);
		}

		return $decoded;
	}
}
