<?php

namespace common\models;

use Yii;
use yii\rbac\Item;

/**
 * This is the model class for table "{{%auth_item}}".
 *
 * @property string $name
 * @property int $type
 * @property string $description
 * @property string $rule_name
 * @property resource $data
 * @property int $created_at
 * @property int $updated_at
 *
 * @property AuthAssignment[] $authAssignments
 * @property AuthRule $ruleName
 * @property AuthItemChild[] $authItemChildren
 * @property AuthItemChild[] $authItemChildren0
 * @property AuthItem[] $children
 * @property AuthItem[] $parents
 */
class AuthItem extends UuidActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%auth_item}}';
	}

	/**
	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['name', 'type'], 'required'],
			[['type', 'created_at', 'updated_at'], 'integer'],
			[['description', 'data'], 'string'],
			[['name', 'rule_name'], 'string', 'max' => 64],
			[['name'], 'unique'],
			[['rule_name'], 'exist', 'skipOnError' => true, 'targetClass' => AuthRule::class, 'targetAttribute' => ['rule_name' => 'name']],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'name' => Yii::t('label', 'Name'),
			'type' => Yii::t('label', 'Type'),
			'description' => Yii::t('label', 'Description'),
			'rule_name' => Yii::t('label', 'Rule Name'),
			'data' => Yii::t('label', 'Data'),
			'created_at' => Yii::t('label', 'Created At'),
			'updated_at' => Yii::t('label', 'Updated At'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getAuthAssignments()
	{
		return $this->hasMany(AuthAssignment::class, ['item_name' => 'name']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getRuleName()
	{
		return $this->hasOne(AuthRule::class, ['name' => 'rule_name']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getAuthItemChildren()
	{
		return $this->hasMany(AuthItemChild::class, ['parent' => 'name']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getAuthItemChildren0()
	{
		return $this->hasMany(AuthItemChild::class, ['child' => 'name']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 * @throws \yii\base\InvalidConfigException
	 */
	public function getChildren()
	{
		return $this->hasMany(AuthItem::class, ['name' => 'child'])->viaTable('{{%auth_item_child}}', ['parent' => 'name']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 * @throws \yii\base\InvalidConfigException
	 */
	public function getParents()
	{
		return $this->hasMany(AuthItem::class, ['name' => 'parent'])->viaTable('{{%auth_item_child}}', ['child' => 'name']);
	}

	/**
	 * Finds all roles.
	 *
	 * @return mixed
	 */
	public static function findAllRoles()
	{
		return static::find()
			->select([
				'name',
				'description',
			])
			->where([
				'AND',
				['=', 'type', Item::TYPE_ROLE],
				['!=', 'name', 'superAdmin'],
			])
			->all();
	}

	/**
	 * Gets the permissions items array.
	 *
	 * @return array
	 */
	public static function getAllPermissions()
	{
		return [
			'Website' => [
				'heading' => Yii::t('common', 'Website'),
				'groups' => [
					'Page' => [
						'heading' => Yii::t('common', 'Pages'),
						'items' => [
							'viewPage' => Yii::t('common', 'View'),
							'createPage' => Yii::t('common', 'Create'),
							'updatePage' => Yii::t('common', 'Update'),
							'deletePage' => Yii::t('common', 'Delete'),
							'restorePage' => Yii::t('common', 'Restore'),
						],
					],
					'Menu' => [
						'heading' => Yii::t('common', 'Menus'),
						'items' => [
							'viewMenu' => Yii::t('common', 'View'),
							'createMenu' => Yii::t('common', 'Create'),
							'updateMenu' => Yii::t('common', 'Update'),
							'deleteMenu' => Yii::t('common', 'Delete'),
							'restoreMenu' => Yii::t('common', 'Restore'),
						],
					],
					'Carousel' => [
						'heading' => Yii::t('common', 'Carousels'),
						'items' => [
							'viewCarousel' => Yii::t('common', 'View'),
							'createCarousel' => Yii::t('common', 'Create'),
							'updateCarousel' => Yii::t('common', 'Update'),
							'deleteCarousel' => Yii::t('common', 'Delete'),
							'restoreCarousel' => Yii::t('common', 'Restore'),
						],
					],
					'Faq' => [
						'heading' => Yii::t('common', 'FAQs'),
						'items' => [
							'viewFaq' => Yii::t('common', 'View'),
							'createFaq' => Yii::t('common', 'Create'),
							'updateFaq' => Yii::t('common', 'Update'),
							'deleteFaq' => Yii::t('common', 'Delete'),
							'restoreFaq' => Yii::t('common', 'Restore'),
						],
					],
					'Testimonial' => [
						'heading' => Yii::t('common', 'Testimonials'),
						'items' => [
							'viewTestimonial' => Yii::t('common', 'View'),
							'createTestimonial' => Yii::t('common', 'Create'),
							'updateTestimonial' => Yii::t('common', 'Update'),
							'deleteTestimonial' => Yii::t('common', 'Delete'),
							'restoreTestimonial' => Yii::t('common', 'Restore'),
						],
					],
					'Partner' => [
						'heading' => Yii::t('common', 'Partners'),
						'items' => [
							'viewPartner' => Yii::t('common', 'View'),
							'createPartner' => Yii::t('common', 'Create'),
							'updatePartner' => Yii::t('common', 'Update'),
							'deletePartner' => Yii::t('common', 'Delete'),
							'restorePartner' => Yii::t('common', 'Restore'),
						],
					],
					'Article' => [
						'heading' => Yii::t('common', 'Articles'),
						'groups' => [
							'ArticleCategory' => [
								'heading' => Yii::t('common', 'Categories'),
								'items' => [
									'viewArticleCategory' => Yii::t('common', 'View'),
									'createArticleCategory' => Yii::t('common', 'Create'),
									'updateArticleCategory' => Yii::t('common', 'Update'),
									'deleteArticleCategory' => Yii::t('common', 'Delete'),
									'restoreArticleCategory' => Yii::t('common', 'Restore'),
								],
							],
							'Article' => [
								'heading' => Yii::t('common', 'Articles'),
								'items' => [
									'viewArticle' => Yii::t('common', 'View'),
									'createArticle' => Yii::t('common', 'Create'),
									'updateArticle' => Yii::t('common', 'Update'),
									'deleteArticle' => Yii::t('common', 'Delete'),
									'restoreArticle' => Yii::t('common', 'Restore'),
								],
							],
						],
					],
					'Service' => [
						'heading' => Yii::t('common', 'Services'),
						'groups' => [
							'ServiceCategory' => [
								'heading' => Yii::t('common', 'Categories'),
								'items' => [
									'viewServiceCategory' => Yii::t('common', 'View'),
									'createServiceCategory' => Yii::t('common', 'Create'),
									'updateServiceCategory' => Yii::t('common', 'Update'),
									'deleteServiceCategory' => Yii::t('common', 'Delete'),
									'restoreServiceCategory' => Yii::t('common', 'Restore'),
								],
							],
							'Service' => [
								'heading' => Yii::t('common', 'Services'),
								'items' => [
									'viewService' => Yii::t('common', 'View'),
									'createService' => Yii::t('common', 'Create'),
									'updateService' => Yii::t('common', 'Update'),
									'deleteService' => Yii::t('common', 'Delete'),
									'restoreService' => Yii::t('common', 'Restore'),
								],
							],
						],
					],
				],
			],
			'Subscriber' => [
				'heading' => Yii::t('common', 'Subscribers'),
				'groups' => [
					'Subscriber' => [
						'heading' => Yii::t('common', 'Subscribers'),
						'items' => [
							'viewSubscriber' => Yii::t('common', 'View'),
							'createSubscriber' => Yii::t('common', 'Create'),
							'updateSubscriber' => Yii::t('common', 'Update'),
							'deleteSubscriber' => Yii::t('common', 'Delete'),
							'restoreSubscriber' => Yii::t('common', 'Restore'),
						],
					],
					'Invoice' => [
						'heading' => Yii::t('common', 'Invoices'),
						'items' => [
							'viewInvoice' => Yii::t('common', 'View'),
							'updateInvoice' => Yii::t('common', 'Update'),
						],
					],
				],
			],
			'Workspace' => [
				'heading' => Yii::t('common', 'Workspaces'),
				'groups' => [
					'Workspace' => [
						'heading' => Yii::t('common', 'Workspaces'),
						'items' => [
							'viewWorkspace' => Yii::t('common', 'View'),
							'createWorkspace' => Yii::t('common', 'Create'),
							'updateWorkspace' => Yii::t('common', 'Update'),
							'deleteWorkspace' => Yii::t('common', 'Delete'),
							'restoreWorkspace' => Yii::t('common', 'Restore'),
						],
					],
				],
			],
			'Affiliate' => [
				'heading' => Yii::t('common', 'Affiliates'),
				'groups' => [
					'Affiliate' => [
						'heading' => Yii::t('common', 'Affiliates'),
						'items' => [
							'viewAffiliate' => Yii::t('common', 'View'),
							'createAffiliate' => Yii::t('common', 'Create'),
							'updateAffiliate' => Yii::t('common', 'Update'),
							'deleteAffiliate' => Yii::t('common', 'Delete'),
						],
					],
					'AffiliatePayout' => [
						'heading' => Yii::t('common', 'Affiliate Payouts'),
						'items' => [
							'manageAffiliatePayout' => Yii::t('common', 'Manage'),
						],
					],
				],
			],
			'Nomenclature' => [
				'heading' => Yii::t('common', 'Nomenclature'),
				'groups' => [
					'Package' => [
						'heading' => Yii::t('common', 'Packages'),
						'items' => [
							'viewPackage' => Yii::t('common', 'View'),
							'createPackage' => Yii::t('common', 'Create'),
							'updatePackage' => Yii::t('common', 'Update'),
							'deletePackage' => Yii::t('common', 'Delete'),
							'restorePackage' => Yii::t('common', 'Restore'),
						],
					],
					'DocumentSeries' => [
						'heading' => Yii::t('common', 'Document Series'),
						'items' => [
							'viewDocumentSeries' => Yii::t('common', 'View'),
							'createDocumentSeries' => Yii::t('common', 'Create'),
							'updateDocumentSeries' => Yii::t('common', 'Update'),
							'deleteDocumentSeries' => Yii::t('common', 'Delete'),
							'restoreDocumentSeries' => Yii::t('common', 'Restore'),
						],
					],
					'UnitOfMeasure' => [
						'heading' => Yii::t('common', 'Unit Of Measures'),
						'items' => [
							'viewUnitOfMeasure' => Yii::t('common', 'View'),
							'createUnitOfMeasure' => Yii::t('common', 'Create'),
							'updateUnitOfMeasure' => Yii::t('common', 'Update'),
							'deleteUnitOfMeasure' => Yii::t('common', 'Delete'),
							'restoreUnitOfMeasure' => Yii::t('common', 'Restore'),
						],
					],
					'EmailTemplate' => [
						'heading' => Yii::t('common', 'Email Templates'),
						'items' => [
							'viewEmailTemplate' => Yii::t('common', 'View'),
							'createEmailTemplate' => Yii::t('common', 'Create'),
							'updateEmailTemplate' => Yii::t('common', 'Update'),
							'deleteEmailTemplate' => Yii::t('common', 'Delete'),
							'restoreEmailTemplate' => Yii::t('common', 'Restore'),
						],
					],
					'InvoiceTemplate' => [
						'heading' => Yii::t('common', 'Invoice Templates'),
						'items' => [
							'viewInvoiceTemplate' => Yii::t('common', 'View'),
							'createInvoiceTemplate' => Yii::t('common', 'Create'),
							'updateInvoiceTemplate' => Yii::t('common', 'Update'),
							'deleteInvoiceTemplate' => Yii::t('common', 'Delete'),
							'restoreInvoiceTemplate' => Yii::t('common', 'Restore'),
						],
					],
				],
			],
			'Marketing' => [
				'heading' => Yii::t('common', 'Marketing'),
				'groups' => [
					'Direct' => [
						'heading' => Yii::t('common', 'Direct Marketing Campaigns'),
						'items' => [
							'viewDirectMarketingCampaign' => Yii::t('common', 'View'),
							'createDirectMarketingCampaign' => Yii::t('common', 'Create'),
							'updateDirectMarketingCampaign' => Yii::t('common', 'Update'),
							'deleteDirectMarketingCampaign' => Yii::t('common', 'Delete'),
							'restoreDirectMarketingCampaign' => Yii::t('common', 'Restore'),
						],
					],
					'FollowUp' => [
						'heading' => Yii::t('common', 'Follow-Up Marketing Campaigns'),
						'items' => [
							'viewFollowupMarketingCampaign' => Yii::t('common', 'View'),
							'createFollowupMarketingCampaign' => Yii::t('common', 'Create'),
							'updateFollowupMarketingCampaign' => Yii::t('common', 'Update'),
							'deleteFollowupMarketingCampaign' => Yii::t('common', 'Delete'),
							'restoreFollowupMarketingCampaign' => Yii::t('common', 'Restore'),
						],
					],
				],
			],
			'Helpdesk' => [
				'heading' => Yii::t('common', 'Helpdesk'),
				'groups' => [
					'SupportTicket' => [
						'heading' => Yii::t('common', 'Support Tickets'),
						'items' => [
							'viewHelpdeskSupportTicket' => Yii::t('common', 'View'),
							'createHelpdeskSupportTicket' => Yii::t('common', 'Create'),
							'updateHelpdeskSupportTicket' => Yii::t('common', 'Update'),
							'deleteHelpdeskSupportTicket' => Yii::t('common', 'Delete'),
							'restoreHelpdeskSupportTicket' => Yii::t('common', 'Restore'),
						],
					],
					'SupportTicketDepartment' => [
						'heading' => Yii::t('common', 'Support Ticket Departments'),
						'items' => [
							'viewHelpdeskSupportTicketDepartment' => Yii::t('common', 'View'),
							'createHelpdeskSupportTicketDepartment' => Yii::t('common', 'Create'),
							'updateHelpdeskSupportTicketDepartment' => Yii::t('common', 'Update'),
							'deleteHelpdeskSupportTicketDepartment' => Yii::t('common', 'Delete'),
							'restoreHelpdeskSupportTicketDepartment' => Yii::t('common', 'Restore'),
						],
					],
					'SupportTicketPriority' => [
						'heading' => Yii::t('common', 'Support Ticket Priorities'),
						'items' => [
							'viewHelpdeskSupportTicketPriority' => Yii::t('common', 'View'),
							'createHelpdeskSupportTicketPriority' => Yii::t('common', 'Create'),
							'updateHelpdeskSupportTicketPriority' => Yii::t('common', 'Update'),
							'deleteHelpdeskSupportTicketPriority' => Yii::t('common', 'Delete'),
							'restoreHelpdeskSupportTicketPriority' => Yii::t('common', 'Restore'),
						],
					],
					'SupportTicketStatus' => [
						'heading' => Yii::t('common', 'Support Ticket Statuses'),
						'items' => [
							'viewHelpdeskSupportTicketStatus' => Yii::t('common', 'View'),
							'createHelpdeskSupportTicketStatus' => Yii::t('common', 'Create'),
							'updateHelpdeskSupportTicketStatus' => Yii::t('common', 'Update'),
							'deleteHelpdeskSupportTicketStatus' => Yii::t('common', 'Delete'),
							'restoreHelpdeskSupportTicketStatus' => Yii::t('common', 'Restore'),
						],
					],
				],
			],
			'User' => [
				'heading' => Yii::t('common', 'Users'),
				'groups' => [
					'User' => [
						'heading' => Yii::t('common', 'Users'),
						'items' => [
							'viewUser' => Yii::t('common', 'View'),
							'createUser' => Yii::t('common', 'Create'),
							'updateUser' => Yii::t('common', 'Update'),
							'deleteUser' => Yii::t('common', 'Delete'),
							'restoreUser' => Yii::t('common', 'Restore'),
						],
					],
					'Role' => [
						'heading' => Yii::t('common', 'Roles'),
						'items' => [
							'viewUserRole' => Yii::t('common', 'View'),
							'createUserRole' => Yii::t('common', 'Create'),
							'updateUserRole' => Yii::t('common', 'Update'),
							'deleteUserRole' => Yii::t('common', 'Delete'),
						],
					],
				],
			],
			'Setting' => [
				'heading' => Yii::t('common', 'Settings'),
				'groups' => [
					'Setting' => [
						'heading' => Yii::t('common', 'Settings'),
						'items' => [
							'updateGeneralSetting' => Yii::t('common', 'General'),
							'updateEmailSetting' => Yii::t('common', 'Email'),
							'updatePaymentSetting' => Yii::t('common', 'Payment'),
							'updateCommercialSetting' => Yii::t('common', 'Commercial'),
							'updateSeoSetting' => Yii::t('common', 'SEO'),
							'updateSocialNetworkSetting' => Yii::t('common', 'Social Networks'),
							'updateContactSetting' => Yii::t('common', 'Contact'),
							'updateAffiliateSetting' => Yii::t('common', 'Affiliates'),
							'clearCacheSetting' => Yii::t('common', 'Clear Cache'),
						],
					],
					'Language' => [
						'heading' => Yii::t('common', 'Languages'),
						'items' => [
							'viewLanguage' => Yii::t('common', 'View'),
							'updateLanguage' => Yii::t('common', 'Update'),
							'translateIntoLanguage' => Yii::t('common', 'Translate'),
						],
					],
					'Currency' => [
						'heading' => Yii::t('common', 'Currencies'),
						'items' => [
							'viewCurrency' => Yii::t('common', 'View'),
							'updateCurrency' => Yii::t('common', 'Update'),
						],
					],
					'Integration' => [
						'heading' => Yii::t('common', 'Integrations'),
						'items' => [
							'viewIntegration' => Yii::t('common', 'View'),
							'updateIntegration' => Yii::t('common', 'Update'),
							'deleteIntegration' => Yii::t('common', 'Delete'),
						],
					],
				],
			],
			'Notification' => [
				'heading' => Yii::t('common', 'Notifications'),
				'items' => [
					'viewNotification' => Yii::t('common', 'View'),
					'createNotification' => Yii::t('common', 'Create'),
					'updateNotification' => Yii::t('common', 'Update'),
					'deleteNotification' => Yii::t('common', 'Delete'),
					'restoreNotification' => Yii::t('common', 'Restore'),
				],
			],
			'Backup' => [
				'heading' => Yii::t('common', 'Backups'),
				'items' => [
					'viewBackup' => Yii::t('common', 'View'),
					'createBackup' => Yii::t('common', 'Create'),
					'downloadBackup' => Yii::t('common', 'Download'),
					'recoverBackup' => Yii::t('common', 'Recover'),
					'deleteBackup' => Yii::t('common', 'Delete'),
					'restoreBackup' => Yii::t('common', 'Restore'),
				],
			],
            'Tutorial' => [
                'heading' => Yii::t('common', 'Tutorials'),
                'groups' => [
                    'TutorialCategory' => [
                        'heading' => Yii::t('common', 'Categories'),
                        'items' => [
                            'viewTutorialCategory' => Yii::t('common', 'View'),
                            'createTutorialCategory' => Yii::t('common', 'Create'),
                            'updateTutorialCategory' => Yii::t('common', 'Update'),
                            'deleteTutorialCategory' => Yii::t('common', 'Delete'),
                            'restoreTutorialCategory' => Yii::t('common', 'Restore'),
                        ],
                    ],
                    'Tutorial' => [
                        'heading' => Yii::t('common', 'Tutorials'),
                        'items' => [
                            'viewTutorial' => Yii::t('common', 'View'),
                            'createTutorial' => Yii::t('common', 'Create'),
                            'updateTutorial' => Yii::t('common', 'Update'),
                            'deleteTutorial' => Yii::t('common', 'Delete'),
                            'restoreTutorial' => Yii::t('common', 'Restore'),
                        ],
                    ],
                ],
            ],
			'EventLog' => [
				'heading' => Yii::t('common', 'Event Logs'),
				'items' => [
					'viewEventLog' => Yii::t('common', 'View'),
				],
			],
		];
	}

	/**
	 * Filters the permissions list by the current authenticated user permissions.
	 *
	 * @param array $data
	 * @return array
	 */
	public static function filterPermissions($data)
	{
		$permissions = [];

		foreach ($data as $key => $val) {
			if (isset($val['visible']) && $val['visible'] === false) {
				continue;
			}
			if (isset($val['groups'])) {
				// Filter the permissions for the group items
				$groups = self::filterPermissions($val['groups']);
				// Push to the stack only if the group items array is not empty
				if (!empty($groups)) {
					$permissions[$key] = [
						'heading' => $val['heading'],
						'groups' => $groups,
					];
				}
			} elseif (isset($val['items'])) {
				// Filter the permissions for the child items
				$items = self::filterPermissions($val['items']);
				// Push to the stack only if the child items array is not empty
				if (!empty($items)) {
					$permissions[$key] = [
						'heading' => $val['heading'],
						'items' => $items,
					];
				}
			} else {
				// Check the user permission
				if (Yii::$app->user->can($key)) {
					$permissions[$key] = $val;
				}
			}
		}

		return $permissions;
	}

	/**
	 * Gets the filtered permissions for the current authenticated user.
	 *
	 * @return array
	 */
	public static function getFilteredPermissions()
	{
		return self::filterPermissions(self::getAllPermissions());
	}
}
