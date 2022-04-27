<!--
  - @copyright 2022 Christopher Ng <chrng8@gmail.com>
  -
  - @author Christopher Ng <chrng8@gmail.com>
  -
  - @license AGPL-3.0-or-later
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
-->

<template>
	<section>
		<ExportSection :loading="loading"
			:notifications-enabled="notificationsEnabled"
			:migrators="migrators"
			:status="status"
			@refresh-status="onRefreshStatus" />
		<ImportSection :loading="loading"
			:notifications-enabled="notificationsEnabled"
			:status="status"
			@refresh-status="onRefreshStatus" />
	</section>
</template>

<script>
import { getCapabilities } from '@nextcloud/capabilities'
import { showError } from '@nextcloud/dialogs'

import { getMigrators, getStatus } from '../../services/migrationService'

import ExportSection from '../../components/ExportSection'
import ImportSection from '../../components/ImportSection'

// Polling interval in seconds
const STATUS_POLLING_INTERVAL = 30

export default {
	name: 'Settings',

	components: {
		ExportSection,
		ImportSection,
	},

	data() {
		return {
			loading: true,
			migrators: [],
			status: { current: null },
		}
	},

	async created() {
		await this.fetchMigrators()
		await this.fetchStatus()
		this.loading = false
		setInterval(this.fetchStatus, STATUS_POLLING_INTERVAL * 1000)
	},

	computed: {
		notificationsEnabled() {
			return Boolean(getCapabilities()?.notifications)
		},
	},

	methods: {
		async fetchMigrators() {
			try {
				this.migrators = await getMigrators()
			} catch (error) {
				const errorMessage = error.message || 'Unknown error'
				this.logger.error(`Error getting available migrators: ${errorMessage}`, { error })
				showError(errorMessage)
			}
		},

		async fetchStatus() {
			try {
				this.status = await getStatus()
			} catch (error) {
				const errorMessage = error.message || 'Unknown error'
				this.logger.error(`Error polling server for export and import status: ${errorMessage}`, { error })
				showError(errorMessage)
			}
		},

		async onRefreshStatus(callback) {
			await this.fetchStatus()
			callback()
		},
	},
}
</script>
