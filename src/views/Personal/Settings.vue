<!--
  - @copyright 2022 Christopher Ng <chrng8@gmail.com>
  -
  - @author Christopher Ng <chrng8@gmail.com>
  -
  - @license GNU AGPL version 3 or any later version
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
	<div>
		<ExportSection :migrators="migrators" :status="status" />
		<ImportSection :migrators="migrators" :status="status" />
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import { APP_ID } from '../../shared/constants'
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
			migrators: [],
			status: { current: null },
		}
	},

	beforeMount() {
		this.fetchMigrators()
		this.fetchStatus()
		setInterval(this.fetchStatus, STATUS_POLLING_INTERVAL * 1000)
	},

	methods: {
		async fetchMigrators() {
			try {
				const response = await axios.get(generateOcsUrl('/apps/{appId}/api/v1/migrators', { appId: APP_ID }))
				this.migrators = response?.data?.ocs?.data
			} catch (error) {
				this.logger.error(`Error getting migrators: ${error.message || 'Unknown error'}`, { error })
			}
		},

		async fetchStatus() {
			try {
				const response = await axios.get(generateOcsUrl('/apps/{appId}/api/v1/status', { appId: APP_ID }))
				this.status = response?.data?.ocs?.data
			} catch (error) {
				this.logger.error(`Error polling server for migration status: ${error.message || 'Unknown error'}`, { error })
			}
		},
	}
}
</script>
