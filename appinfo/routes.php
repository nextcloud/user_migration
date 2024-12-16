<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirements = [
	'apiVersion' => '1',
];

return [
	'ocs' => [
		['name' => 'Api#migrators', 'url' => '/api/v{apiVersion}/migrators', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'Api#status', 'url' => '/api/v{apiVersion}/status', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'Api#cancel', 'url' => '/api/v{apiVersion}/cancel', 'verb' => 'PUT', 'requirements' => $requirements],
		['name' => 'Api#exportable', 'url' => '/api/v{apiVersion}/export', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'Api#export', 'url' => '/api/v{apiVersion}/export', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'Api#import', 'url' => '/api/v{apiVersion}/import', 'verb' => 'POST', 'requirements' => $requirements],
	],
];
