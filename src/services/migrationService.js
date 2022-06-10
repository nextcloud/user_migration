/**
 * @copyright 2022 Christopher Ng <chrng8@gmail.com>
 *
 * @author Christopher Ng <chrng8@gmail.com>
 *
 * @license AGPL-3.0-or-later
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

import axios from '@nextcloud/axios'
import confirmPassword from '@nextcloud/password-confirmation'
import { generateOcsUrl } from '@nextcloud/router'

import { APP_ID, API_VERSION } from '../shared/constants.js'
import { formatQueryParamArray } from '../shared/utils.js'

/**
 * @return {object}
 */
export const getMigrators = async () => {
	const url = generateOcsUrl('/apps/{appId}/api/v{apiVersion}/migrators', { appId: APP_ID, apiVersion: API_VERSION })
	const response = await axios.get(url)

	return response.data.ocs?.data
}

/**
 * @return {object}
 */
export const getStatus = async () => {
	const url = generateOcsUrl('/apps/{appId}/api/v{apiVersion}/status', { appId: APP_ID, apiVersion: API_VERSION })
	const response = await axios.get(url)

	return response.data.ocs?.data
}

/**
 * @return {object}
 */
export const cancelJob = async () => {
	const url = generateOcsUrl('/apps/{appId}/api/v{apiVersion}/cancel', { appId: APP_ID, apiVersion: API_VERSION })

	await confirmPassword()

	const response = await axios.put(url)

	return response.data.ocs?.data
}

/**
 * @param {string[]} migrators Array of migrators
 *
 * @return {object}
 */
export const checkExportability = async (migrators) => {
	const url = generateOcsUrl('/apps/{appId}/api/v{apiVersion}/export', { appId: APP_ID, apiVersion: API_VERSION }) + formatQueryParamArray('migrators', migrators)
	const response = await axios.get(url)

	return response.data.ocs?.data
}

/**
 * @param {string[]} migrators Array of migrators
 *
 * @return {object}
 */
export const queueExportJob = async (migrators) => {
	const url = generateOcsUrl('/apps/{appId}/api/v{apiVersion}/export', { appId: APP_ID, apiVersion: API_VERSION })

	await confirmPassword()

	const response = await axios.post(url, {
		migrators,
	})

	return response.data.ocs?.data
}

/**
 * @param {string} path Path to file
 *
 * @return {object}
 */
export const queueImportJob = async (path) => {
	const url = generateOcsUrl('/apps/{appId}/api/v{apiVersion}/import', { appId: APP_ID, apiVersion: API_VERSION })

	await confirmPassword()

	const response = await axios.post(url, {
		path,
	})

	return response.data.ocs?.data
}
