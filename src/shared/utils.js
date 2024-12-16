/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showWarning, showError } from '@nextcloud/dialogs'

import logger from './logger.js'

/**
 * @param {AxiosError|string} error Error or message
 *
 * @return {string}
 */
const parseMessage = (error) => {
	if (typeof error === 'string') {
		return error || 'Unknown error'
	}
	return error.response.data.ocs?.meta?.message || 'Unknown error'
}

/**
 * @param {AxiosError|string} error Error or message
 * @param {import('@nextcloud/dialogs/dist/toast').ToastOptions} toastOptions Toast options
 *
 * @return {void}
 */
export const handleWarning = (error, toastOptions = {}) => {
	const message = parseMessage(error)
	logger.warn(message, { error })
	showWarning(message, toastOptions)
}

/**
 * @param {AxiosError|string} error Error or message
 * @param {import('@nextcloud/dialogs/dist/toast').ToastOptions} toastOptions Toast options
 *
 * @return {void}
 */
export const handleError = (error, toastOptions = {}) => {
	const message = parseMessage(error)
	logger.error(message, { error })
	showError(message, toastOptions)
}

/**
 * @param {string} name Name of the query parameter
 * @param {string[]} values Array of values
 *
 * @return {string}
 */
export const formatQueryParamArray = (name, values) => {
	if (values.length === 0) {
		return `?${name}[]=`
	}

	return `?${values.map(value => `${name}[]=${value}`).join('&')}`
}
