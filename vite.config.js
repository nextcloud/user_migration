/*!
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createAppConfig } from '@nextcloud/vite-config'
import { resolve } from 'node:path'

export default createAppConfig({
	personalSettings: resolve(import.meta.dirname, 'src', 'personal-settings.js'),
}, {
	emptyOutputDirectory: {
		additionalDirectories: ['css'],
	},
	extractLicenseInformation: {
		includeSourceMaps: true,
	},
})
