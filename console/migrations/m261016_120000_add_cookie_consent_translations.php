<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Texts of the category cookie consent banner (Necessary / Analytics / Marketing): the three
 * buttons, the settings panel, the "Cookie settings" footer link and the contact map
 * placeholder shown while marketing cookies are refused.
 *
 * Messages already in `language_source` are left alone, so the migration is safe on a
 * database where the texts were added by hand. Clear the frontend cache afterwards.
 */
class m261016_120000_add_cookie_consent_translations extends Migration
{
	/**
	 * @var array [message => ro-RO translation], all in the `frontend` category.
	 */
	protected $messages = [
		'Necessary' => 'Necesare',
		'Always on: the session, your login and your cookie choice. The site cannot work without them.' => 'Mereu active: sesiunea, autentificarea și alegerea ta privind cookie-urile. Site-ul nu poate funcționa fără ele.',
		'Analytics' => 'Analiză',
		'Google Analytics, to measure how the site is used. Nothing is loaded until you accept.' => 'Google Analytics, pentru a măsura cum este folosit site-ul. Nu se încarcă nimic până nu accepți.',
		'Facebook Pixel, Facebook chat and Google Maps, for relevant ads, live chat and the map on the contact page. Nothing is loaded until you accept.' => 'Facebook Pixel, chatul Facebook și Google Maps, pentru reclame relevante, chat live și harta din pagina de contact. Nu se încarcă nimic până nu accepți.',
		'Necessary cookies keep the site working. Analytics and marketing cookies are set only if you accept them, and you can change your choice at any time from the "Cookie settings" link in the footer.' => 'Cookie-urile necesare țin site-ul funcțional. Cookie-urile de analiză și marketing se setează doar dacă le accepți, iar alegerea se poate schimba oricând din linkul „Setări cookie-uri” din subsol.',
		'Accept all' => 'Acceptă tot',
		'Refuse all' => 'Refuză tot',
		'Save settings' => 'Salvează setările',
		'Cookie settings' => 'Setări cookie-uri',
		'The map is shown only if you accept marketing cookies.' => 'Harta se afișează doar dacă accepți cookie-urile de marketing.',
	];

	/**
	 * @inheritdoc
	 */
	public function safeUp()
	{
		foreach ($this->messages as $message => $translation) {
			$exists = (new Query())
				->from('{{%language_source}}')
				->where(['category' => 'frontend', 'message' => $message])
				->exists($this->db);
			if ($exists) {
				continue;
			}
			$this->insert('{{%language_source}}', ['category' => 'frontend', 'message' => $message]);
			$this->insert('{{%language_translate}}', [
				'id' => $this->db->getLastInsertID(),
				'language' => 'ro-RO',
				'translation' => $translation,
			]);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown()
	{
		$ids = (new Query())
			->select('id')
			->from('{{%language_source}}')
			->where(['category' => 'frontend', 'message' => array_keys($this->messages)])
			->column($this->db);
		$this->delete('{{%language_translate}}', ['id' => $ids]);
		$this->delete('{{%language_source}}', ['id' => $ids]);
	}
}
