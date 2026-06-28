<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="settings-cloud-sync-section">
		<h3 class="cloud-sync-header">
			{{ $t('calendar', 'Cloud Sync (Nextcloud to Provider)') }}
		</h3>
		<p class="cloud-sync-description">
			{{ $t('calendar', 'Automatically publish events from your default calendar to third-party services in real-time. (One-way sync: NC to Provider only)') }}
		</p>

		<div class="sync-providers-list">
			<!-- Google Calendar Card -->
			<div class="sync-provider-card">
				<div class="provider-info">
					<div class="provider-logo-container google-logo">
						<GoogleIcon :size="24" />
					</div>
					<div class="provider-details">
						<h4 class="provider-name">Google Calendar</h4>
						<p v-if="!status.google.configured" class="provider-status status-unconfigured">
							{{ $t('calendar', 'Not configured by administrator') }}
						</p>
						<p v-else-if="status.google.connected" class="provider-status status-connected">
							{{ $t('calendar', 'Connected (Syncing Default Calendar)') }}
						</p>
						<p v-else class="provider-status status-disconnected">
							{{ $t('calendar', 'Disconnected') }}
						</p>
					</div>
				</div>
				<div class="provider-actions">
					<button
						v-if="status.google.configured && !status.google.connected"
						class="sync-btn btn-connect"
						@click="connect('google')">
						{{ $t('calendar', 'Connect Account') }}
					</button>
					<button
						v-if="status.google.connected"
						class="sync-btn btn-disconnect"
						@click="disconnect('google')">
						{{ $t('calendar', 'Disconnect') }}
					</button>
				</div>
			</div>

			<!-- Microsoft Outlook Card -->
			<div class="sync-provider-card">
				<div class="provider-info">
					<div class="provider-logo-container microsoft-logo">
						<MicrosoftIcon :size="24" />
					</div>
					<div class="provider-details">
						<h4 class="provider-name">Microsoft Outlook Calendar</h4>
						<p v-if="!status.microsoft.configured" class="provider-status status-unconfigured">
							{{ $t('calendar', 'Not configured by administrator') }}
						</p>
						<p v-else-if="status.microsoft.connected" class="provider-status status-connected">
							{{ $t('calendar', 'Connected (Syncing Default Calendar)') }}
						</p>
						<p v-else class="provider-status status-disconnected">
							{{ $t('calendar', 'Disconnected') }}
						</p>
					</div>
				</div>
				<div class="provider-actions">
					<button
						v-if="status.microsoft.configured && !status.microsoft.connected"
						class="sync-btn btn-connect"
						@click="connect('microsoft')">
						{{ $t('calendar', 'Connect Account') }}
					</button>
					<button
						v-if="status.microsoft.connected"
						class="sync-btn btn-disconnect"
						@click="disconnect('microsoft')">
						{{ $t('calendar', 'Disconnect') }}
					</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import GoogleIcon from 'vue-material-design-icons/Google.vue'
import MicrosoftIcon from 'vue-material-design-icons/Microsoft.vue'

export default {
	name: 'SettingsCloudSyncSection',

	components: {
		GoogleIcon,
		MicrosoftIcon,
	},

	data() {
		return {
			status: {
				google: { configured: false, connected: false },
				microsoft: { configured: false, connected: false },
			},
		}
	},

	created() {
		this.fetchStatus()
	},

	methods: {
		async fetchStatus() {
			try {
				const response = await axios.get(generateUrl('/apps/calendar/oauth/status'))
				this.status = response.data
			} catch (error) {
				console.error('Failed to fetch OAuth sync status:', error)
			}
		},

		connect(provider) {
			window.location.href = generateUrl(`/apps/calendar/oauth/connect/${provider}`)
		},

		async disconnect(provider) {
			try {
				await axios.post(generateUrl(`/apps/calendar/oauth/disconnect/${provider}`))
				showSuccess(this.$t('calendar', 'Successfully disconnected and cleared remote sync mappings.'))
				this.fetchStatus()
			} catch (error) {
				showError(this.$t('calendar', 'Failed to disconnect account.'))
				console.error(error)
			}
		},
	},
}
</script>

<style scoped>
.settings-cloud-sync-section {
	margin-top: 24px;
	padding-top: 16px;
	border-top: 1px solid var(--color-border);
}

.cloud-sync-header {
	font-weight: bold;
	font-size: 1.1em;
	margin-bottom: 6px;
}

.cloud-sync-description {
	color: var(--color-text-maxcontrast);
	margin-bottom: 16px;
	font-size: 0.9em;
}

.sync-providers-list {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.sync-provider-card {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 12px 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background-color: var(--color-background-hover);
}

.provider-info {
	display: flex;
	align-items: center;
	gap: 12px;
}

.provider-logo-container {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 40px;
	height: 40px;
	border-radius: 50%;
	background-color: var(--color-main-background);
}

.google-logo {
	color: #ea4335;
}

.microsoft-logo {
	color: #0078d4;
}

.provider-details {
	display: flex;
	flex-direction: column;
}

.provider-name {
	font-weight: bold;
	margin: 0 0 2px 0;
}

.provider-status {
	font-size: 0.85em;
	margin: 0;
}

.status-connected {
	color: var(--color-success);
}

.status-unconfigured {
	color: var(--color-text-maxcontrast);
}

.status-disconnected {
	color: var(--color-text-maxcontrast);
}

.sync-btn {
	padding: 6px 12px;
	border-radius: var(--border-radius);
	font-size: 0.9em;
	cursor: pointer;
	border: none;
	font-weight: 500;
}

.btn-connect {
	background-color: var(--color-primary);
	color: var(--color-primary-text);
}

.btn-connect:hover {
	background-color: var(--color-primary-element-light);
}

.btn-disconnect {
	background-color: transparent;
	border: 1px solid var(--color-error);
	color: var(--color-error);
}

.btn-disconnect:hover {
	background-color: var(--color-error);
	color: #fff;
}
</style>
