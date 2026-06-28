<?php
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
?>

<div id="calendar-oauth-settings" class="section">
	<h2 class="app-name">Calendar Sync Integration</h2>
	<p class="settings-hint">Configure OAuth 2.0 Credentials for Google Calendar and Microsoft Outlook Calendar synchronization.</p>

	<div class="oauth-provider-section">
		<h3>Google Calendar Integration</h3>
		<div class="oauth-fields">
			<p>
				<label for="google-client-id">Google Client ID:</label>
				<input type="text" id="google-client-id" name="google-client-id" class="input-field" placeholder="Client ID from Google Developer Console" />
			</p>
			<p>
				<label for="google-client-secret">Google Client Secret:</label>
				<input type="password" id="google-client-secret" name="google-client-secret" class="input-field" placeholder="••••••••••••••••" />
				<span id="google-secret-status" class="secret-status-badge"></span>
			</p>
		</div>
	</div>

	<div class="oauth-provider-section">
		<h3>Microsoft Outlook Integration</h3>
		<div class="oauth-fields">
			<p>
				<label for="microsoft-client-id">Microsoft Client ID (Application ID):</label>
				<input type="text" id="microsoft-client-id" name="microsoft-client-id" class="input-field" placeholder="Application (client) ID from Azure Portal" />
			</p>
			<p>
				<label for="microsoft-client-secret">Microsoft Client Secret (Value):</label>
				<input type="password" id="microsoft-client-secret" name="microsoft-client-secret" class="input-field" placeholder="••••••••••••••••" />
				<span id="microsoft-secret-status" class="secret-status-badge"></span>
			</p>
		</div>
	</div>

	<div class="oauth-actions">
		<button id="save-oauth-settings" class="button primary">Save Credentials</button>
		<span id="oauth-settings-save-status"></span>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
	const googleClientIdInput = document.getElementById('google-client-id');
	const googleClientSecretInput = document.getElementById('google-client-secret');
	const googleSecretStatus = document.getElementById('google-secret-status');

	const microsoftClientIdInput = document.getElementById('microsoft-client-id');
	const microsoftClientSecretInput = document.getElementById('microsoft-client-secret');
	const microsoftSecretStatus = document.getElementById('microsoft-secret-status');

	const saveButton = document.getElementById('save-oauth-settings');
	const saveStatus = document.getElementById('oauth-settings-save-status');

	// Load existing credentials
	fetch(OC.generateUrl('/apps/calendar/v1/admin/oauth/credentials'))
		.then(response => response.json())
		.then(data => {
			googleClientIdInput.value = data.google_client_id || '';
			if (data.google_client_secret_set) {
				googleSecretStatus.textContent = 'Saved';
				googleSecretStatus.className = 'secret-status-badge status-saved';
			} else {
				googleSecretStatus.textContent = 'Not Set';
				googleSecretStatus.className = 'secret-status-badge status-empty';
			}

			microsoftClientIdInput.value = data.microsoft_client_id || '';
			if (data.microsoft_client_secret_set) {
				microsoftSecretStatus.textContent = 'Saved';
				microsoftSecretStatus.className = 'secret-status-badge status-saved';
			} else {
				microsoftSecretStatus.textContent = 'Not Set';
				microsoftSecretStatus.className = 'secret-status-badge status-empty';
			}
		})
		.catch(error => {
			console.error('Failed to load OAuth credentials:', error);
		});

	// Save credentials
	saveButton.addEventListener('click', function () {
		saveButton.disabled = true;
		saveStatus.textContent = 'Saving...';
		saveStatus.style.color = 'var(--color-text-maxcontrast)';

		const payload = {
			googleClientId: googleClientIdInput.value.trim(),
			googleClientSecret: googleClientSecretInput.value.trim(),
			microsoftClientId: microsoftClientIdInput.value.trim(),
			microsoftClientSecret: microsoftClientSecretInput.value.trim(),
		};

		fetch(OC.generateUrl('/apps/calendar/v1/admin/oauth/credentials'), {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'requesttoken': OC.requestToken,
			},
			body: JSON.stringify(payload),
		})
		.then(response => response.json())
		.then(data => {
			saveButton.disabled = false;
			if (data.status === 'success') {
				saveStatus.textContent = 'Saved successfully!';
				saveStatus.style.color = 'var(--color-success)';
				if (payload.googleClientSecret) {
					googleSecretStatus.textContent = 'Saved';
					googleSecretStatus.className = 'secret-status-badge status-saved';
					googleClientSecretInput.value = '';
				}
				if (payload.microsoftClientSecret) {
					microsoftSecretStatus.textContent = 'Saved';
					microsoftSecretStatus.className = 'secret-status-badge status-saved';
					microsoftClientSecretInput.value = '';
				}
			} else {
				saveStatus.textContent = 'Failed to save: ' + (data.message || 'unknown error');
				saveStatus.style.color = 'var(--color-error)';
			}
		})
		.catch(error => {
			saveButton.disabled = false;
			saveStatus.textContent = 'Failed to save due to connection error.';
			saveStatus.style.color = 'var(--color-error)';
			console.error(error);
		});
	});
});
</script>

<style>
#calendar-oauth-settings {
	margin-bottom: 30px;
}
.oauth-provider-section {
	margin-top: 20px;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background-color: var(--color-background-hover);
}
.oauth-provider-section h3 {
	margin-top: 0;
	font-weight: bold;
}
.oauth-fields p {
	display: flex;
	align-items: center;
	margin: 8px 0;
}
.oauth-fields label {
	width: 280px;
	font-weight: 500;
}
.oauth-fields input {
	flex-grow: 1;
	max-width: 400px;
}
.secret-status-badge {
	margin-left: 10px;
	padding: 2px 8px;
	border-radius: 10px;
	font-size: 0.85em;
	font-weight: bold;
}
.status-saved {
	background-color: var(--color-success-light);
	color: var(--color-success);
}
.status-empty {
	background-color: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
}
.oauth-actions {
	margin-top: 20px;
	display: flex;
	align-items: center;
	gap: 15px;
}
</style>
