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
		<ExportSection :job="job" />
		<ImportSection :job="job" />
	</div>
</template>

<script>
// TODO remove ignore
/* eslint-disable */
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import ExportSection from '../../components/ExportSection'
import ImportSection from '../../components/ImportSection'
import { APP_ID } from '../../shared/constants'

// Polling interval in seconds
const JOB_POLLING_INTERVAL = 30

export default {
	name: 'Settings',

	components: {
		ExportSection,
		ImportSection,
	},

	data() {
		return {
			job: {
				// possible values: null, 'export', 'import'
				current: null,
				statusText: null,
			},
		}
	},

	beforeMount() {
		this.fetchJob()
		setInterval(this.fetchJob, JOB_POLLING_INTERVAL * 1000)
	},

	methods: {
		async fetchJob() {
			try {
				// TODO poll job status from server API
				// const response = await axios.get(generateOcsUrl(`apps/${APP_ID}/api/v1/job`))
				const response = {}
				// this.job = response?.data?.ocs?.data
			} catch (error) {
				this.logger.error(`Error polling server for user migration job: ${error.message || 'Unknown error'}`, { error })
			}
		},
	}
}
</script>
