<?php

namespace OCA\UserMigration\AppInfo;

use OCP\AppFramework\App;

class Application extends App {
	public const APP_ID = 'user_migration';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}
}
