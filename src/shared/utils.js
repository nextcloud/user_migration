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

import { showWarning, showError } from '@nextcloud/dialogs'

import logger from './logger.js'

/**
 * @param {AxiosError} error Error object
 *
 * @return {void}
 */
export const handleWarning = (error) => {
	const warningMessage = error.response.data.ocs?.meta?.message || 'Unknown warning'
	logger.warn(warningMessage, { error })
	showWarning(warningMessage)
}

/**
 * @param {AxiosError} error Error object
 *
 * @return {void}
 */
export const handleError = (error) => {
	const errorMessage = error.response.data.ocs?.meta?.message || 'Unknown error'
	logger.error(errorMessage, { error })
	showError(errorMessage)
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
