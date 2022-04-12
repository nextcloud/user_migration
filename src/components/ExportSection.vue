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
	<div class="section">
		<h2>{{ t(APP_ID, 'Export') }}</h2>

		<h3 class="settings-hint">{{ t(APP_ID, 'Select the data you want to export') }}</h3>

		<div class="section__grid">
			<!-- Base user data is permanently enabled -->
			<div class="section__checkbox">
				<CheckboxRadioSwitch :checked="true"
					:disabled="true">
					User information and settings
				</CheckboxRadioSwitch>
				<em class="section__description">Some descriptive text about the data to be exported. Aliquam eu sem at lacus consequat malesuada sit amet et nulla.</em>
			</div>
			<div v-for="({id, displayName, description}) in sortedMigrators"
				class="section__checkbox"
				:key="id">
				<CheckboxRadioSwitch name="migrators"
					:value="id"
					:checked.sync="selectedMigrators">
					{{ displayName }}
				</CheckboxRadioSwitch>
				<em class="section__description">{{ description }}</em>
			</div>
			<!-- TODO since TrashbinMigrator depends on FilesMigrator server should have some sort of migrator dependency API -->
		</div>

		<!-- <span>Migrators: {{ selectedMigrators }}</span> -->

		<Button v-if="status.current !== 'export'"
			type="secondary"
			:aria-label="t(APP_ID, 'Export your data')"
			:disabled="status.current === 'import'"
			@click.stop.prevent="startExport">
			<template #icon>
				<PackageDown title="" :size="20" />
			</template>
			{{ t(APP_ID, 'Export') }}
		</Button>
		<Button v-else
			type="secondary"
			:aria-label="t(APP_ID, 'Show export status')"
			:disabled="status.current === 'import'"
			@click.stop.prevent="openModal">
			{{ t(APP_ID, 'Show status')}}
		</Button>

		<Modal v-if="modalOpened"
			@close="closeModal">
			<div class="section__modal">
				<EmptyContent>
					{{ t(APP_ID, 'Export in progress…') }}
					<template #icon>
						<PackageDown />
					</template>
					<template #desc>
						{{ t(APP_ID, 'Please do not use your account while exporting.') }}
					</template>
				</EmptyContent>
				<!-- TODO show list of data currently being exported  -->
				<!-- TODO use spinner as percentage of export complete cannot be queried from server -->
				<ProgressBar size="medium"
					:value="60"
					:error="error" />
			</div>
		</Modal>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import confirmPassword from '@nextcloud/password-confirmation'

import Button from '@nextcloud/vue/dist/Components/Button'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'
import Modal from '@nextcloud/vue/dist/Components/Modal'
import PackageDown from 'vue-material-design-icons/PackageDown'
import ProgressBar from '@nextcloud/vue/dist/Components/ProgressBar'

import { APP_ID } from '../shared/constants'

export default {
	name: 'ExportSection',

	props: {
		migrators: {
			type: Array,
			default: () => [],
		},
		status: {
			type: Object,
			default: () => ({}),
		},
	},

	components: {
		Button,
		EmptyContent,
		PackageDown,
		Modal,
		ProgressBar,
		CheckboxRadioSwitch,
	},

	data() {
		return {
			modalOpened: false,
			selectedMigrators: ['settings'],
			error: false,
			APP_ID,
		}
	},

	computed: {
		sortedMigrators() {
			// TODO sort migrators?
			return this.migrators
		}
	},

	methods: {
		async startExport() {
			try {
				await confirmPassword()
				await axios.post(
					generateOcsUrl('/apps/{appId}/api/v1/export', { appId: APP_ID }),
					{ migrators: this.selectedMigrators },
				)
				this.openModal()
			} catch (error) {
				this.logger.error(`Error starting user export: ${error.message || 'Unknown error'}`, { error })
				// TODO show error message in a dialog
			}
		},

		openModal() {
			this.modalOpened = true
		},

		closeModal() {
			this.modalOpened = false
		},
	},
}
</script>

<style lang="scss" scoped>
.section__grid {
	display: grid;
	gap: 10px 20px;
	grid-auto-flow: row;
	grid-template-columns: repeat(auto-fit, 400px);
	margin-bottom: 40px;

	.section__description {
		display: inline-block;
		min-height: 44px;
		margin-left: 26px;
	}
}

.section__modal {
	align-self: center;
	margin: 20px auto;
	width: 80%;
	height: 80%;
	display: flex;
	flex-direction: column;
	justify-content: center;
	align-items: center;
}
</style>