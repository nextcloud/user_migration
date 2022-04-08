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

		<Button aria-label="Import your data"
			type="secondary"
			:wide="true"
			@click.stop.prevent="pickImportFile">
			<template #icon>
				<PackageUp title=""
					:size="20" />
			</template>
			{{ t(APP_ID, 'Import') }}
		</Button>
		<span class="error">{{ filePickerError }}</span>

		<!-- TODO display import in progress modal -->
	</div>
</template>

<script>
import Button from '@nextcloud/vue/dist/Components/Button'
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

	components: {
		Button,
		PackageUp,
	},

	data() {
		return {
			filePickerError: null,
			APP_ID,
		}
	},

	methods: {
		pickImportFile() {
			this.filePickerError = null

			picker.pick()
				.then(filePath => {
					this.logger.debug(`path ${filePath} selected for import`)
					if (!filePath.startsWith('/')) {
						throw new Error(t(APP_ID, 'Invalid import file selected'))
					}
				}).catch(error => {
					this.logger.error(`Selecting file for import aborted: ${error.message || 'Unknown error'}`, { error })
					this.filePickerError = error.message || t(APP_ID, 'Unknown error')
				})
		},
	},
}
</script>
