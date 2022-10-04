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
	<NcSettingsSection :title="t('user_migration', 'Export')"
		:description="!loading ? t('user_migration', 'Please select the data you want to export') : ''"
		:limit-width="false">
		<template v-if="!loading">
			<div class="section__grid">
				<!-- Base user data is permanently enabled -->
				<div class="section__checkbox">
					<NcCheckboxRadioSwitch :checked="true"
						:disabled="true">
						{{ t('user_migration', 'User information and settings') }}
					</NcCheckboxRadioSwitch>
					<em class="section__description">{{ t('user_migration', 'Basic user information including user ID and display name as well as your settings') }}</em>
				</div>
				<div v-for="({ id, displayName, description }) in sortedMigrators"
					:key="id"
					class="section__checkbox">
					<NcCheckboxRadioSwitch name="migrators"
						:value="id"
						:checked.sync="selectedMigrators">
						{{ displayName }}
					</NcCheckboxRadioSwitch>
					<em class="section__description">{{ description }}</em>
				</div>
			</div>

			<div v-if="status.current === 'export'"
				class="section__status">
				<NcButton type="secondary"
					:aria-label="t('user_migration', 'Show export status')"
					:disabled="status.current === 'import' || cancellingExport"
					@click.stop.prevent="openModal">
					<template #icon>
						<InformationOutline :size="20" />
					</template>
					{{ t('user_migration', 'Show status') }}
				</NcButton>
				<NcButton type="tertiary"
					:aria-label="t('user_migration', 'Cancel export')"
					:disabled="status.status !== 'waiting'"
					@click.stop.prevent="cancelExport">
					{{ t('user_migration', 'Cancel') }}
				</NcButton>
				<span class="settings-hint">{{ status.status === 'waiting' ? t('user_migration', 'Export queued') : t('user_migration', 'Export in progress…') }}</span>
				<NcLoadingIcon v-if="cancellingExport" class="section__loading" :size="34" />
			</div>
			<div v-else class="section__status">
				<NcButton type="primary"
					:aria-label="t('user_migration', 'Export your data')"
					:disabled="status.current === 'import' || startingExport"
					@click.stop.prevent="startExport">
					<template #icon>
						<PackageDown :size="20" />
					</template>
					{{ t('user_migration', 'Export') }}
				</NcButton>
				<span v-if="estimatedSizeWithUnits" class="settings-hint">{{ t('user_migration', 'Estimated size: {estimatedSizeWithUnits}', { estimatedSizeWithUnits }) }}</span>
				<NcLoadingIcon v-if="startingExport" class="section__loading" :size="34" />
			</div>

			<NcModal v-if="modalOpened"
				@close="closeModal">
				<div class="section__modal">
					<NcEmptyContent
						:title="modalMessage"
						:description="modalDescription">
						<template #icon>
							<PackageDown />
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
										:aria-label="t('user_migration', 'Close export status')"
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
import { NcButton, NcCheckboxRadioSwitch, NcEmptyContent, NcLoadingIcon, NcModal, NcSettingsSection } from '@nextcloud/vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import InformationOutline from 'vue-material-design-icons/InformationOutline.vue'
import PackageDown from 'vue-material-design-icons/PackageDown.vue'

import { queueExportJob, cancelJob, checkExportability } from '../services/migrationService.js'
import { handleError, handleWarning } from '../shared/utils.js'

export default {
	name: 'ExportSection',

	components: {
		CheckCircleOutline,
		InformationOutline,
		NcButton,
		NcCheckboxRadioSwitch,
		NcEmptyContent,
		NcLoadingIcon,
		NcModal,
		NcSettingsSection,
		PackageDown,
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
		migrators: {
			type: Array,
			default: () => [],
		},
		status: {
			type: Object,
			default: () => ({}),
		},
	},

	data() {
		return {
			cancellingExport: false,
			estimatedSizeWithUnits: null,
			modalOpened: false,
			selectedMigrators: [],
			startingExport: false,
		}
	},

	computed: {
		sortedMigrators() {
			// TODO do this in better way if needed
			const sortOrder = ['files', 'trashbin', 'account', 'calendar', 'contacts']
			return [...this.migrators].sort((a, b) => sortOrder.indexOf(a.id) - sortOrder.indexOf(b.id))
		},

		modalMessage() {
			if (this.status.status === 'waiting') {
				return t('user_migration', 'Export queued')
			} else if (this.status.status === 'started') {
				return t('user_migration', 'Export in progress…')
			}
			return t('user_migration', 'Export completed successfully')
		},

		modalDescription() {
			if (this.status.status === 'waiting') {
				if (this.notificationsEnabled) {
					return t('user_migration', 'You will be notified when your export has completed. This may take a while.')
				}
				return t('user_migration', 'This may take a while.')
			} else if (this.status.status === 'started') {
				return t('user_migration', 'Please do not use your account while exporting.')
			}
		},
	},

	watch: {
		sortedMigrators: {
			immediate: true,
			handler(migrators, oldMigrators) {
				this.selectedMigrators = migrators.map(({ id }) => id)
			},
		},

		selectedMigrators: {
			immediate: false,
			async handler(migrators, oldMigrators) {
				try {
					const { estimatedSize, units, warning } = await checkExportability(migrators)
					if (warning) {
						handleWarning(warning)
					}
					this.estimatedSizeWithUnits = `${estimatedSize} ${units}`
				} catch (error) {
					handleError(error)
				}
			},
		},
	},

	methods: {
		async startExport() {
			try {
				this.startingExport = true
				await queueExportJob(this.selectedMigrators)
				this.$emit('refresh-status', () => {
					this.openModal()
					this.startingExport = false
				})
			} catch (error) {
				this.startingExport = false
				handleError(error)
			}
		},

		async cancelExport() {
			try {
				this.cancellingExport = true
				await cancelJob()
				this.$emit('refresh-status', () => {
					this.cancellingExport = false
				})
			} catch (error) {
				this.cancellingExport = false
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
.section__grid {
	display: grid;
	gap: 40px;
	grid-auto-flow: row;
	grid-template-columns: repeat(auto-fit, minmax(320px, 400px));
	margin-bottom: 40px;

	.section__description {
		display: inline-block;
		min-height: 44px;
		margin-left: 26px;
	}
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
