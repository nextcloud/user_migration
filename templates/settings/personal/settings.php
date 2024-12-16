<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\UserMigration\AppInfo\Application;

script(Application::APP_ID, [Application::APP_ID . '-personal-settings']);
?>

<div id="personal-settings"></div>
