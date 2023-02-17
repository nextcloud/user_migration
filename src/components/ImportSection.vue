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
	<NcSettingsSection :title="t('user_migration', 'Import')"
		:description="!loading ? t('user_migration', 'Please note that existing data may be overwritten') : ''"
		:limit-width="false">
		<template v-if="!loading">
			<div v-if="status.current === 'import'"
				class="section__status">
				<NcButton type="secondary"
					:aria-label="t('user_migration', 'Show import status')"
					:disabled="status.current === 'export' || cancellingImport"
					@click.stop.prevent="openModal">
					<template #icon>
						<InformationOutline :size="20" />
					</template>
					{{ t('user_migration', 'Show status') }}
				</NcButton>
				<NcButton type="tertiary"
					:aria-label="t('user_migration', 'Cancel import')"
					:disabled="status.status !== 'waiting' || cancellingImport"
					@click.stop.prevent="cancelImport">
					{{ t('user_migration', 'Cancel') }}
				</NcButton>
				<span class="settings-hint">{{ status.status === 'waiting' ? t('user_migration', 'Import queued') : t('user_migration', 'Import in progress…') }}</span>
				<NcLoadingIcon v-if="cancellingImport" class="section__loading" :size="34" />
			</div>
			<div v-else class="section__status">
				<NcButton type="primary"
					:aria-label="t('user_migration', 'Import your data')"
					:disabled="status.current === 'export' || startingImport"
					@click.stop.prevent="pickImportFile">
					<template #icon>
						<PackageUp :size="20" />
					</template>
					{{ t('user_migration', 'Import') }}
				</NcButton>
				<NcLoadingIcon v-if="startingImport" class="section__loading" :size="34" />
			</div>

			<span class="section__picker-error error">{{ filePickerError }}</span>

			<NcModal v-if="modalOpened"
				@close="closeModal">
				<div class="section__modal">
					<NcEmptyContent :title="modalMessage"
						:description="modalDescription">
						<template #icon>
							<PackageUp />
						</template>
						<template #action>
							<div class="section__modal-action">
								<NcLoadingIcon v-if="status.status === 'waiting' || status.status === 'started'"
									class="section__icon"
									:size="40" />
								<template v-else>
									<CheckCircleOutline class="section__icon"
										:size="40" />
									<NcButton class="section__modal-button"
										type="primary"
										:aria-label="t('user_migration', 'Close import status')"
										@click.stop.prevent="closeModal">
										{{ t('user_migration', 'Close') }}
									</NcButton>
								</template>
							</div>
						</template>
					</NcEmptyContent>
				</div>
			</NcModal>
		</template>
		<NcLoadingIcon v-else :size="40" />
	</NcSettingsSection>
</template>

<script>
// import { getFilePickerBuilder } from '@nextcloud/dialogs'

import { NcButton, NcEmptyContent, NcLoadingIcon, NcModal, NcSettingsSection } from '@nextcloud/vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import InformationOutline from 'vue-material-design-icons/InformationOutline.vue'
import PackageUp from 'vue-material-design-icons/PackageUp.vue'

import { queueImportJob, cancelJob } from '../services/migrationService.js'
import { handleError } from '../shared/utils.js'

/*
const picker = getFilePickerBuilder(t('user_migration', 'Choose a file to import'))
	.setMultiSelect(false)
	// TODO add custom mime type for user_migration files?
	// .setMimeTypeFilter([])
	.setModal(true)
	.setType(1)
	.allowDirectories(false)
	.build()
*/

export default {
	name: 'ImportSection',

	components: {
		CheckCircleOutline,
		InformationOutline,
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		NcModal,
		NcSettingsSection,
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

		modalDescription() {
			if (this.status.status === 'waiting') {
				if (this.notificationsEnabled) {
					return t('user_migration', 'You will be notified when your import has completed. This may take a while.')
				}
				return t('user_migration', 'This may take a while.')
			} else if (this.status.status === 'started') {
				return t('user_migration', 'Please do not use your account while importing.')
			}
			return ''
		},
	},

	methods: {
		async pickImportFile() {
			this.filePickerError = null

			try {
				// TODO: bring this back once nextcloud-dialogs is updated to support the filter function
				// const filePath = await picker.pick()
				const filePath = await new Promise((resolve, reject) => {
					OC.dialogs.filepicker(
						t('user_migration', 'Choose a file to import'),
						resolve,
						false,
						null,
						true,
						1,
						null,
						{
							allowDirectoryChooser: false,
							filter: entry => {
								if (entry.mimetype === 'httpd/unix-directory') {
									return true
								}
								return entry.name.endsWith('.nextcloud_export')
							},
						}
					)
				})

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
					handleError(error)
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
				handleError(error)
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
.section__status {
	display: flex;
	gap: 0 14px;
	margin-top: 20px;

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
	.section__icon {
		height: 40px;
	}

	.section__modal-action {
		display: flex;
		flex-direction: column;
	}

	.section__modal-button {
		margin: 20px auto 0 auto;
	}
}
</style>
