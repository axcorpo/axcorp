<?php

use common\db\Migration;

/**
 * Handles the creation of table `user`.
 *
 * The primary key is a UUID v7 generated in PHP, stored as BINARY(16).
 */
class m130524_201442_create_user_table extends Migration
{
	public function up()
	{
		$tableOptions = null;
		if ($this->db->driverName === 'mysql') {
			$tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
		}

		$this->createTable('{{%user}}', [
			'id' => $this->binaryUuidPrimaryKey(),
			'parent_id' => $this->binaryUuid(false),
			'auth_key' => $this->string(32)->notNull(),
			'password_hash' => $this->string()->notNull(),
			'password_reset_token' => $this->string()->unique(),
			'email' => $this->string()->notNull()->unique(),
			'username' => $this->string()->notNull()->unique(),
			'phone' => $this->string(255)->unique(),
			'image' => $this->string(255)->defaultValue(null),
			'first_name' => $this->string(255)->notNull(),
			'middle_name' => $this->string(255)->defaultValue(null),
			'last_name' => $this->string(255)->notNull(),
			'gender' => $this->smallInteger()->defaultValue(null),
			'created_at' => $this->dateTime()->notNull(),
			'updated_at' => $this->dateTime()->notNull(),
			'status' => $this->smallInteger()->notNull()->defaultValue(0),
			'deleted' => $this->smallInteger()->defaultValue(0),
		], $tableOptions);
	}

	public function down()
	{
		$this->dropTable('{{%user}}');
	}
}
