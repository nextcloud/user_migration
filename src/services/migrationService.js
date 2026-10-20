/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { generateOcsUrl } from '@nextcloud/router'
import { API_VERSION, APP_ID } from '../shared/constants.js'
import { formatQueryParamArray } from '../shared/utils.js'

import '@nextcloud/password-confirmation/dist/style.css'

/**
 * @return {object}
 */
export async function getMigrators() {
	const url = generateOcsUrl('/apps/{appId}/api/v{apiVersion}/migrators', { appId: APP_ID, apiVersion: API_VERSION })
	const response = await axios.get(url)

	return response.data.ocs?.data
}

/**
 * @return {object}
 */
export async function getStatus() {
	const url = generateOcsUrl('/apps/{appId}/api/v{apiVersion}/status', { appId: APP_ID, apiVersion: API_VERSION })
	const response = await axios.get(url)

	return response.data.ocs?.data
}

/**
 * @return {object}
 */
export async function cancelJob() {
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
export async function checkExportability(migrators) {
	const url = generateOcsUrl('/apps/{appId}/api/v{apiVersion}/export', { appId: APP_ID, apiVersion: API_VERSION }) + formatQueryParamArray('migrators', migrators)
	const response = await axios.get(url)

	return response.data.ocs?.data
}

/**
 * @param {string[]} migrators Array of migrators
 *
 * @return {object}
 */
export async function queueExportJob(migrators) {
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
export async function queueImportJob(path) {
	const url = generateOcsUrl('/apps/{appId}/api/v{apiVersion}/import', { appId: APP_ID, apiVersion: API_VERSION })

	await confirmPassword()

	const response = await axios.post(url, {
		path,
	})

	return response.data.ocs?.data
}
