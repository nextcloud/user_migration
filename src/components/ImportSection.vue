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
	<div class="section">
		<h2>{{ t('user_migration', 'Import') }}</h2>

		<template v-if="!loading">
			<h3 class="section__hint settings-hint">
				{{ t('user_migration', 'Please note that existing data may be overwritten') }}
			</h3>

			<div v-if="status.current === TYPE.IMPORT"
				class="section__status">
				<Button type="secondary"
					:aria-label="t('user_migration', 'Show import status')"
					:disabled="status.current === TYPE.EXPORT || cancellingImport"
					@click.stop.prevent="openModal">
					<template #icon>
						<InformationOutline title="" :size="20" />
					</template>
					{{ t('user_migration', 'Show status') }}
				</Button>
				<Button class="section__modal-button"
					type="secondary"
					:aria-label="t('user_migration', 'Cancel import')"
					:disabled="status.status !== STATUS.WAITING || cancellingImport"
					@click.stop.prevent="cancelImport">
					{{ t('user_migration', 'Cancel') }}
				</Button>
				<span class="settings-hint">{{ status.status === STATUS.WAITING ? t('user_migration', 'Import queued') : t('user_migration', 'Import in progress…') }}</span>
				<div v-if="cancellingImport" class="icon-loading section__loading" />
			</div>
			<div v-else class="section__status">
				<Button type="secondary"
					:aria-label="t('user_migration', 'Import your data')"
					:disabled="status.current === TYPE.EXPORT || startingImport"
					@click.stop.prevent="pickImportFile">
					<template #icon>
						<PackageUp title="" :size="20" />
					</template>
					{{ t('user_migration', 'Import') }}
				</Button>
				<div v-if="startingImport" class="icon-loading section__loading" />
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
						<template v-if="status.status === STATUS.WAITING" #desc>
							{{ notificationsEnabled ? t('user_migration', 'You will be notified when your import has completed. This may take a while.') : t('user_migration', 'This may take a while.') }}
						</template>
						<template v-else-if="status.status === STATUS.STARTED" #desc>
							{{ t('user_migration', 'Please do not use your account while importing.') }}
						</template>
					</EmptyContent>
					<div v-if="status.status === STATUS.WAITING || status.status === STATUS.STARTED"
						class="section__icon icon-loading" />
					<template v-else>
						<CheckCircleOutline class="section__icon"
							title=""
							:size="40" />
						<Button class="section__modal-button"
							type="secondary"
							:aria-label="t('user_migration', 'Close import status')"
							@click.stop.prevent="closeModal">
							{{ t('user_migration', 'Close') }}
						</Button>
					</template>
				</div>
			</Modal>
		</template>
		<div v-else class="icon-loading" />
	</div>
</template>

<script>
import { getFilePickerBuilder, showError } from '@nextcloud/dialogs'

import Button from '@nextcloud/vue/dist/Components/Button'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import InformationOutline from 'vue-material-design-icons/InformationOutline'
import Modal from '@nextcloud/vue/dist/Components/Modal'
import PackageUp from 'vue-material-design-icons/PackageUp'

import { queueImportJob, cancelJob } from '../services/migrationService.js'
import { STATUS, TYPE } from '../shared/constants.js'

const filePickerFilter = (entry) => {
	if (entry.mimetype === 'httpd/unix-directory') {
		return true
	}
	return entry.name.endsWith('.nextcloud_export')
}

const filePicker = getFilePickerBuilder(t('user_migration', 'Choose a file to import'))
	.setMultiSelect(false)
	.setFilter(filePickerFilter)
	.setModal(true)
	.setType(1)
	.allowDirectories(false)
	.build()

export default {
	name: 'ImportSection',

	components: {
		Button,
		CheckCircleOutline,
		EmptyContent,
		InformationOutline,
		Modal,
		PackageUp,
	},

	props: {
		notificationsEnabled: {
			type: Boolean,
			default: false,
		},
		loading: {
			type: Boolean,
			default: true,
		},
		pendingRequest: {
			type: Object,
			default: () => ({}),
		},
		status: {
			type: Object,
			default: () => ({}),
		},
	},

	data() {
		return {
			modalOpened: false,
			startingImport: false,
			cancellingImport: false,
			filePickerError: null,
			STATUS,
		}
	},

	computed: {
		modalMessage() {
			if (this.status.status === STATUS.WAITING) {
				return t('user_migration', 'Import queued')
			} else if (this.status.status === STATUS.STARTED) {
				return t('user_migration', 'Import in progress…')
			}
			return t('user_migration', 'Import completed successfully')
		},
	},

	methods: {
		async pickImportFile() {
			this.filePickerError = null

			try {
				const filePath = await filePicker.pick()
				this.logger.debug(`Path "${filePath}" selected for import`)

				if (!filePath.startsWith('/')) {
					throw new Error(`Invalid path: ${filePath}`)
				}

				try {
					this.startingImport = true
					await queueImportJob(filePath)
					this.$emit('refresh-status', () => {
						this.openModal()
						this.startingImport = false
					})
				} catch (error) {
					this.startingImport = false
					const errorMessage = error.message || 'Unknown error'
					this.logger.error(`Error starting user import: ${errorMessage}`, { error })
					showError(errorMessage)
				}
			} catch (error) {
				const errorMessage = error.message || 'Unknown error'
				this.logger.error(`Error selecting file to import: ${errorMessage}`, { error })
				this.filePickerError = errorMessage
			}
		},

		async cancelImport() {
			try {
				this.cancellingImport = true
				await cancelJob()
				this.$emit('refresh-status', () => {
					this.cancellingImport = false
				})
			} catch (error) {
				this.cancellingImport = false
				const errorMessage = error.message || 'Unknown error'
				this.logger.error(`Error cancelling user import: ${errorMessage}`, { error })
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
	gap: 0 14px;

	.section__loading {
		margin-left: 6px;
	}

	.settings-hint {
		margin: auto 0;
	}
}

.section__picker-error {
	display: inline-block;
	margin: 20px 0;
}

.section__modal {
	margin: 80px auto 60px auto;

	&::v-deep .empty-content {
		margin-top: 0;
	}

	.section__icon {
		height: 40px;
		margin: 20px 0;
	}

	.section__modal-button {
		margin: 40px auto 0 auto;
	}
}
</style>
