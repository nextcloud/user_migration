<?php

declare(strict_types=1);

/**
 * @copyright 2022 Christopher Ng <chrng8@gmail.com>
 *
 * @author Christopher Ng <chrng8@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

$requirements = [
	'apiVersion' => '1',
];

return [
	'ocs' => [
		['name' => 'Api#migrators', 'url' => '/api/v{apiVersion}/migrators', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'Api#status', 'url' => '/api/v{apiVersion}/status', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'Api#cancel', 'url' => '/api/v{apiVersion}/cancel', 'verb' => 'PUT', 'requirements' => $requirements],
		['name' => 'Api#export', 'url' => '/api/v{apiVersion}/export', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'Api#import', 'url' => '/api/v{apiVersion}/import', 'verb' => 'POST', 'requirements' => $requirements],
	],
];
