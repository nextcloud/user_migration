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
		<h2>{{ t(APP_ID, 'Import') }}</h2>

		<!-- TODO use server API -->

		<Button v-if="status.current !== 'import'"
			type="secondary"
			:aria-label="t(APP_ID, 'Import your data')"
			:disabled="status.current === 'export'"
			@click.stop.prevent="pickImportFile">
			<template #icon>
				<PackageUp title="" :size="20" />
			</template>
			{{ t(APP_ID, 'Import') }}
		</Button>
		<Button v-else
			type="secondary"
			:aria-label="t(APP_ID, 'Show import status')"
			:disabled="status.current === 'export'"
			@click.stop.prevent="openModal">
			{{ t(APP_ID, 'Show status') }}
		</Button>

		<span class="error">{{ filePickerError }}</span>

		<Modal v-if="modalOpened"
			@close="closeModal">
			<div class="section__modal">
				<h2>{{ t(APP_ID, 'Importing…') }}</h2>
				<ProgressBar size="medium"
					:value="60"
					:error="error" />
			</div>
		</Modal>
	</div>
</template>

<script>
import Button from '@nextcloud/vue/dist/Components/Button'
import Modal from '@nextcloud/vue/dist/Components/Modal'
import PackageUp from 'vue-material-design-icons/PackageUp'
import { getFilePickerBuilder } from '@nextcloud/dialogs'

import { APP_ID } from '../shared/constants'

const picker = getFilePickerBuilder(t('files', 'Choose a file to import'))
	.setMultiSelect(false)
	// TODO add custom mime type for user_migration files?
	// .setMimeTypeFilter([])
	.setModal(true)
	.setType(1)
	.allowDirectories(false)
	// TODO start at default export folder path?
	// .startAt('')
	.build()

export default {
	name: 'ImportSection',

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
		Modal,
		PackageUp,
	},

	data() {
		return {
			filePickerError: null,
			modalOpened: false,
			error: false,
			APP_ID,
		}
	},

	methods: {
		openModal() {
			this.modalOpened = true
		},

		closeModal() {
			this.modalOpened = false
		},

		async pickImportFile() {
			this.filePickerError = null

			try {
				const filePath = await picker.pick()
				this.logger.debug(`path ${filePath} selected for import`)
				if (!filePath.startsWith('/')) {
					throw new Error(t(APP_ID, 'Invalid import file selected'))
				}
				this.openModal()
				// TODO start background job
			} catch (error) {
				this.importError = true
				this.logger.error(`Selecting file for import aborted: ${error.message || 'Unknown error'}`, { error })
				this.filePickerError = error.message || t(APP_ID, 'Unknown error')
			}
		},
	},
}
</script>

<style lang="scss" scoped>
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