<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section>
		<ExportSection
			:loaded="loaded"
			:notifications-enabled="notificationsEnabled"
			:migrators="migrators"
			:status="status"
			@refresh-status="onRefreshStatus" />
		<ImportSection
			:loaded="loaded"
			:notifications-enabled="notificationsEnabled"
			:status="status"
			@refresh-status="onRefreshStatus" />
	</section>
</template>

<script>
import { getCapabilities } from '@nextcloud/capabilities'
import ExportSection from '../../components/ExportSection.vue'
import ImportSection from '../../components/ImportSection.vue'
import { getMigrators, getStatus } from '../../services/migrationService.js'
import { handleError } from '../../shared/utils.js'

// Polling interval in seconds
const STATUS_POLLING_INTERVAL = 30

export default {
	name: 'MigrationSettings',

	components: {
		ExportSection,
		ImportSection,
	},

	data() {
		return {
			loaded: false,
			migrators: [],
			status: { current: null },
		}
	},

	computed: {
		notificationsEnabled() {
			return Boolean(getCapabilities()?.notifications)
		},
	},

	async created() {
		await this.fetchMigrators()
		await this.fetchStatus()
		this.loaded = true
		setInterval(this.fetchStatus, STATUS_POLLING_INTERVAL * 1000)
	},

	methods: {
		async fetchMigrators() {
			try {
				this.migrators = await getMigrators()
			} catch (error) {
				handleError(error)
			}
		},

		async fetchStatus() {
			try {
				this.status = await getStatus()
			} catch (error) {
				handleError(error)
			}
		},

		async onRefreshStatus(callback) {
			await this.fetchStatus()
			callback()
		},
	},
}
</script>
