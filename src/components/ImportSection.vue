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
		<h2>{{ t('user_migration', 'Import') }}</h2>

		<h3 class="section__hint settings-hint">{{ t('user_migration', 'Please note that existing data may be overwritten') }}</h3>

		<!-- TODO use server API -->

		<Button v-if="status.current !== 'import'"
			type="secondary"
			:aria-label="t('user_migration', 'Import your data')"
			:disabled="status.current === 'export'"
			@click.stop.prevent="pickImportFile">
			<template #icon>
				<PackageUp title="" :size="20" />
			</template>
			{{ t('user_migration', 'Import') }}
		</Button>
		<div class="section__status" v-else>
			<Button type="secondary"
				:aria-label="t('user_migration', 'Show import status')"
				:disabled="status.current === 'export'"
				@click.stop.prevent="openModal">
				{{ t('user_migration', 'Show status') }}
			</Button>
			<span class="settings-hint">{{ status.status === 'waiting' ? t('user_migration', 'Import queued') : t('user_migration', 'Import in progress…') }}</span>
		</div>

		<span class="section__picker-error error">{{ filePickerError }}</span>

		<Modal v-if="modalOpened"
			@close="closeModal">
			<div class="section__modal">
				<EmptyContent>
					{{ modalMessage }}
					<template #icon>
						<PackageUp decorative />
					</template>
					<template v-if="status.status === 'started'" #desc>
						{{ t('user_migration', 'Please do not use your account while importing.') }}
					</template>
				</EmptyContent>
				<div v-if="status.status === 'waiting' || status.status === 'started'"
					class="section__icon icon-loading" />
				<CheckCircleOutline v-else
					class="section__icon"
					title=""
					:size="40" />
			</div>
		</Modal>
	</div>
</template>

<script>
import { getFilePickerBuilder, showError } from '@nextcloud/dialogs'

import Button from '@nextcloud/vue/dist/Components/Button'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import Modal from '@nextcloud/vue/dist/Components/Modal'
import PackageUp from 'vue-material-design-icons/PackageUp'

const picker = getFilePickerBuilder(t('user_migration', 'Choose a file to import'))
	.setMultiSelect(false)
	// TODO add custom mime type for user_migration files?
	// .setMimeTypeFilter([])
	.setModal(true)
	.setType(1)
	.allowDirectories(false)
	.build()

export default {
	name: 'ImportSection',

	props: {
		status: {
			type: Object,
			default: () => ({}),
		},
	},

	components: {
		Button,
		CheckCircleOutline,
		EmptyContent,
		Modal,
		PackageUp,
	},

	data() {
		return {
			modalOpened: false,
			filePickerError: null,
		}
	},

	computed: {
		modalMessage() {
			if (this.status.status === 'waiting') {
				return t('user_migration', 'Import queued')
			} else if (this.status.status === 'started') {
				return t('user_migration', 'Import in progress…')
			}
			return t('user_migration', 'Import completed successfully')
		},
	},

	methods: {
		async pickImportFile() {
			this.filePickerError = null

			try {
				const filePath = await picker.pick()
				this.logger.debug(`Path "${filePath}" selected for import`)
				if (!filePath.startsWith('/')) {
					throw new Error()
				}
				try {
					// TODO call API to start background job
					this.$emit('refresh-status')
					this.openModal()
				} catch (error) {
					const errorMessage = error.message || 'Unknown error'
					this.logger.error(`Error starting user import: ${errorMessage}`, { error })
					showError(errorMessage)
				}
			} catch (error) {
				const errorMessage = error.message || 'Unknown error'
				this.logger.error(`Error selecting file to import: ${errorMessage}`, { error })
				this.filePickerError = errorMessage
				showError(errorMessage)
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
.section__hint {
	margin-bottom: 20px;
}

.section__status {
	display: flex;
	gap: 0 20px;

	.settings-hint {
		margin: auto 0;
	};
}

.section__picker-error {
	display: inline-block;
	margin: 20px 0;
}

.section__modal {
	margin: 110px auto;

	&::v-deep .empty-content {
		margin-top: 0;
	}

	.section__icon {
		height: 40px;
		margin-top: 20px;
	}
}
</style>
