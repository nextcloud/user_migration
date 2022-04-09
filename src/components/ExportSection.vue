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

		<!-- TODO use server API -->

		<div class="section__grid">
			<!-- Base user data is permanently enabled -->
			<div class="section__checkbox">
				<CheckboxRadioSwitch name="migrators"
					value="settings"
					:checked.sync="tempChecked"
					:disabled="true">
					User information and settings
				</CheckboxRadioSwitch>
				<em class="section__description">Some descriptive text about the data to be exported. Aliquam eu sem at lacus consequat malesuada sit amet et nulla.</em>
			</div>
			<div class="section__checkbox">
				<CheckboxRadioSwitch name="migrators"
					value="profile"
					:checked.sync="tempChecked">
					Profile information
				</CheckboxRadioSwitch>
				<em class="section__description">Profile picture, Full name, Email, Phone number, Address, Website, Twitter, Organisation, Role, Headline, About, and whether your Profile is enabled</em>
			</div>
			<!-- TODO since TrashbinMigrator depends on FilesMigrator server should have some sort of migrator dependency API -->
			<div class="section__checkbox">
				<CheckboxRadioSwitch name="migrators"
					value="files"
					:checked.sync="tempChecked">
					Files
				</CheckboxRadioSwitch>
				<em class="section__description">Includes trashbin, versions, comments, Collaborative tags (systemtags), and favorite state (tags)</em>
			</div>
			<div class="section__checkbox">
				<CheckboxRadioSwitch name="migrators"
					value="calendar"
					:checked.sync="tempChecked">
					Calendar
				</CheckboxRadioSwitch>
				<em class="section__description">Some descriptive text about the data to be exported. Aliquam eu sem at lacus consequat malesuada sit amet et nulla.</em>
			</div>
			<div class="section__checkbox">
				<CheckboxRadioSwitch name="migrators"
					value="contacts"
					:checked.sync="tempChecked">
					Contacts
				</CheckboxRadioSwitch>
				<em class="section__description">Some descriptive text about the data to be exported. Aliquam eu sem at lacus consequat malesuada sit amet et nulla.</em>
			</div>
		</div>

		<!-- <span>Migrators: {{ tempChecked }}</span> -->

		<Button v-if="job.current !== 'export'"
			type="secondary"
			:aria-label="t(APP_ID, 'Export your data')"
			:disabled="job.current === 'import'"
			@click.stop.prevent="startExport">
			<template #icon>
				<PackageDown title="" :size="20" />
			</template>
			{{ t(APP_ID, 'Export') }}
		</Button>
		<Button v-else
			type="secondary"
			:aria-label="t(APP_ID, 'Show export status')"
			:disabled="job.current === 'import'"
			@click.stop.prevent="openModal">
			{{ t(APP_ID, 'Show status')}}
		</Button>

		<Modal v-if="modalOpened"
			@close="closeModal">
			<div class="section__modal">
				<h2>{{ t(APP_ID, 'Exporting…') }}</h2>
				<ProgressBar size="medium"
					:value="60"
					:error="error" />
			</div>
			<!-- TODO should we show progress text like in the CLI output? -->
		</Modal>
	</div>
</template>

<script>
import Button from '@nextcloud/vue/dist/Components/Button'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'
import Modal from '@nextcloud/vue/dist/Components/Modal'
import PackageDown from 'vue-material-design-icons/PackageDown'
import ProgressBar from '@nextcloud/vue/dist/Components/ProgressBar'

import { APP_ID } from '../shared/constants'

export default {
	name: 'ExportSection',

	props: {
		job: {
			type: Object,
			default: () => ({}),
		},
	},

	components: {
		Button,
		PackageDown,
		Modal,
		ProgressBar,
		CheckboxRadioSwitch,
	},

	data() {
		return {
			modalOpened: false,
			tempChecked: ['settings'],
			error: false,
			APP_ID,
		}
	},

	methods: {
		startExport() {
			try {
				// TODO call export API endpoint
				this.openModal()
			} catch (e) {

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
