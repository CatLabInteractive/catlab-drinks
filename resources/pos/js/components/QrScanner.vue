<!--
  - CatLab Drinks - Simple bar automation system
  - Copyright (C) 2019 Thijs Van der Schaeghe
  - CatLab Interactive bvba, Gent, Belgium
  - http://www.catlab.eu/
  -
  - This program is free software; you can redistribute it and/or modify
  - it under the terms of the GNU General Public License as published by
  - the Free Software Foundation; either version 3 of the License, or
  - (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU General Public License for more details.
  -
  - You should have received a copy of the GNU General Public License along
  - with this program; if not, write to the Free Software Foundation, Inc.,
  - 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
  -->

<template>
	<div>
		<p v-if="cameraError" class="error">{{ cameraError }}</p>
		<qr-stream @detect="onDetect" @error="onError" v-if="!cameraError">
			<div class="qr-overlay">
				<p>Point your camera at the QR code</p>
			</div>
		</qr-stream>
	</div>
</template>

<script>
import { QrcodeStream } from 'vue-qrcode-reader';

export default {
	components: {
		'qr-stream': QrcodeStream
	},

	data() {
		return {
			cameraError: null
		};
	},

	methods: {
		onDetect(detectedCodes) {
			if (!detectedCodes || detectedCodes.length === 0) {
				return;
			}

			const rawValue = detectedCodes[0].rawValue;
			if (!rawValue) {
				return;
			}

			this.processScannedUrl(rawValue);
		},

		processScannedUrl(url) {
			try {
				const parsed = new URL(url);
				const dataParam = parsed.searchParams.get('data');

				if (!dataParam) {
					this.$emit('error', 'QR code does not contain connection data.');
					return;
				}

				const json = atob(dataParam);
				const data = JSON.parse(json);

				if (!data.api || !data.token) {
					this.$emit('error', 'Invalid connection data: missing api or token.');
					return;
				}

				this.$emit('scanned', data);
			} catch (e) {
				this.$emit('error', 'Could not parse QR code data.');
			}
		},

		onError(error) {
			if (error.name === 'NotAllowedError') {
				this.cameraError = 'Camera access was denied. Please allow camera access in your browser settings to scan QR codes.';
			} else if (error.name === 'NotFoundError') {
				this.cameraError = 'No camera found on this device. Please use a device with a camera or enter the token manually.';
			} else if (error.name === 'NotReadableError') {
				this.cameraError = 'Camera is already in use by another application.';
			} else {
				this.cameraError = 'Could not access camera: ' + error.message;
			}
		}
	}
};
</script>
