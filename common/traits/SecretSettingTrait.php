<?php

namespace common\traits;

/**
 * Keeps stored secrets out of the rendered form without losing them on save.
 *
 * Yii's `passwordInput()` fills the `value` attribute from the model, so every settings
 * page shipped its own SMTP password, Stripe key and API secrets to the browser in the
 * markup — readable from view-source, and a click away behind the reveal toggle. The
 * views now render these fields empty, which on its own would blank the stored value the
 * first time somebody saved the form for an unrelated reason.
 *
 * So the pair belongs together: the form renders nothing, and a field submitted empty
 * means "leave it as it was" rather than "clear it". Clearing a secret is done by
 * removing the integration, not by emptying one input.
 *
 * A using class lists its secret attributes in [[secretAttributes()]], calls
 * [[rememberSecrets()]] from `afterFind()`, and gets the restore for free through
 * `beforeValidate()` — before validation, so a `required` rule does not fire on a field
 * the user deliberately left untouched.
 */
trait SecretSettingTrait
{
	/**
	 * @var array The stored secrets, captured before the request could overwrite them.
	 * Not an attribute, so load() cannot reach it.
	 */
	private $_storedSecrets = [];

	/**
	 * The attributes holding secrets that must never be rendered back.
	 *
	 * @return string[]
	 */
	abstract public function secretAttributes();

	/**
	 * Captures the stored secrets. Call from `afterFind()`, after the attributes are
	 * populated.
	 */
	public function rememberSecrets()
	{
		foreach ($this->secretAttributes() as $attribute) {
			$this->_storedSecrets[$attribute] = $this->{$attribute};
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function beforeValidate()
	{
		foreach ($this->secretAttributes() as $attribute) {
			if ((string) $this->{$attribute} === '' && !empty($this->_storedSecrets[$attribute])) {
				$this->{$attribute} = $this->_storedSecrets[$attribute];
			}
		}

		return parent::beforeValidate();
	}
}
