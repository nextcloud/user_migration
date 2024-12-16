/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getLoggerBuilder } from '@nextcloud/logger'

import { APP_ID } from './constants.js'

export default getLoggerBuilder()
	.setApp(APP_ID)
	.detectUser()
	.build()
