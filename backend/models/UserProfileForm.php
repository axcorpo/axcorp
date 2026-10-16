<?php

namespace backend\models;

use common\validators\PasswordValidator;
use common\models\User;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;
use yii\web\UploadedFile;

class UserProfileForm extends User
{
	/**
	 * @var UploadedFile The imageFile.
	 */
	public $imageFile;

	/**
	 * @var string The role name.
	 */
	public $role;

	/**
	 * @var string The new password.
	 */
	/**
	 * @var string The current password, required in order to change it.
	 */
	public $current_password;

	public $new_password;

	/**
	 * @var string The new password confirm.
	 */
	public $new_password_confirm;


	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['first_name', 'last_name'], 'required'],
			[['gender'], 'integer'],
			['gender', 'in', 'range' => [1, 2]],
			[['new_password', 'new_password_confirm', 'first_name', 'middle_name', 'last_name'], 'string', 'max' => 255],
			[['new_password', 'new_password_confirm', 'first_name', 'middle_name', 'last_name'], 'trim'],
			[['new_password'], PasswordValidator::class],
			// Changing a password is a re-authentication point: without it, temporary
			// access to a signed-in session converts straight into permanent control
			// of the account.
			['current_password', 'required', 'when' => function ($model) {
				return !empty($model->new_password);
			}],
			['current_password', 'validateCurrentPassword'],
			['new_password_confirm', 'required', 'when' => function ($model) {
				return !empty($model->new_password);
			}, 'whenClient' => 'function (attribute, value) {
				return attribute.$form.find("[name*=\"[new_password]\"]").val() != "";
			}'],
			['new_password_confirm', 'compare', 'compareAttribute' => 'new_password', 'message' => Yii::t('common', 'Passwords don\'t match.')],
			[['imageFile'], 'file', 'extensions' => ['jpeg', 'jpg', 'png', 'gif'], 'mimeTypes' => ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'], 'maxSize' => Yii::$app->settings->get('maxFileSize'), 'skipOnEmpty' => true],
			[['parent_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['parent_id' => 'id']],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'imageFile' => Yii::t('label', 'Image'),
			'role' => Yii::t('label', 'Role'),
			'current_password' => Yii::t('label', 'Current Password'),
			'new_password' => Yii::t('label', 'New Password'),
			'new_password_confirm' => Yii::t('label', 'Confirm New Password'),
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function scenarios()
	{
		return Model::scenarios();
	}

	/**
	 * @inheritdoc
	 */
	public function afterFind()
	{
		parent::afterFind();

		$this->role = $this->authAssignment->itemName->description;
	}

    /**
     * Verifies the current password. Inline validator for `current_password`.
     *
     * @param string $attribute
     * @param array $params
     */
    public function validateCurrentPassword($attribute, $params)
    {
    	if (empty($this->new_password) || $this->hasErrors($attribute)) {
    		return;
    	}
    	if (!$this->validatePassword((string) $this->current_password)) {
    		$this->addError($attribute, Yii::t('common', 'The provided credentials are invalid.'));
    	}
    }

    /**
     * {@inheritdoc}
     */
    public function load($data, $formName = null)
    {
        $result = parent::load($data, $formName);
		if (!empty($data)) {
            $this->imageFile = UploadedFile::getInstance($this, 'imageFile');
        }
		return $result;
	}

	/**
	 * Saves the user files.
	 *
	 * @return bool
	 */
	protected function saveFiles()
	{
		try {
            if (!($file = $this->imageFile)) {
                return true;
            }
			$dirPath = Yii::getAlias("@uploads/user/{$this->uuid}");
			$oldFilePath = "{$dirPath}/{$this->oldAttributes['image']}";
			$fileName = StringHelper::truncate(implode('_', array_filter([
				Inflector::slug($this->fullName),
				Yii::$app->security->generateRandomString(8),
			])), 255 - (mb_strlen($file->extension) + 1), '') . ".{$file->extension}";
			$filePath = "{$dirPath}/{$fileName}";

			FileHelper::createDirectory($dirPath);
			if (!$file->saveAs($filePath)) {
				throw new \Exception();
			}
			if (!$this->updateAttributes(['image' => $fileName])) {
				throw new \Exception();
			}
			if (is_file($oldFilePath) && $oldFilePath != $filePath) {
				FileHelper::unlink($oldFilePath);
			}
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * @inheritdoc
	 */
	public function save($runValidation = true, $attributeNames = null)
	{
		$dbTransaction = static::getDb()->beginTransaction();
		try {
			if (!empty($this->new_password)) {
				$this->setPassword($this->new_password);
				$this->generateAuthKey();
			}
			if (!parent::save($runValidation, $attributeNames)) {
				throw new \Exception();
			}
			if (!$this->saveFiles()) {
				throw new \Exception();
			}
			$dbTransaction->commit();
			// The workspace copies are in other databases, outside the transaction; a new
			// password and key that stopped at master would leave the old ones valid there.
			if (!empty($this->new_password)) {
				$this->syncCredentials();
			}
			return $this;
		} catch(\Exception $e) {
			$dbTransaction->rollBack();
			return false;
		}
	}
}
