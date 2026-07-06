require('./bootstrap');

import Alpine from 'alpinejs';
import { Html5Qrcode } from 'html5-qrcode';

window.sirikaScan = function ({ verifyUrl, csrfToken }) {
    return {
        qrReader: null,
        cameraRunning: false,
        loading: false,
        manualToken: '',
        result: null,

        async startCamera() {
            if (this.cameraRunning || this.loading) {
                return;
            }

            try {
                this.qrReader = this.qrReader || new Html5Qrcode('sirika-qr-reader');
                const cameras = await Html5Qrcode.getCameras();

                if (!cameras.length) {
                    this.result = { result: 'invalid', message: 'Kamera tidak ditemukan.', permit: null };
                    return;
                }

                await this.qrReader.start(
                    cameras[0].id,
                    { fps: 10, qrbox: { width: 240, height: 240 } },
                    (decodedText) => this.handleDecodedText(decodedText),
                    () => {}
                );

                this.cameraRunning = true;
            } catch (error) {
                this.result = {
                    result: 'invalid',
                    message: 'Kamera tidak dapat dibuka.',
                    permit: null,
                };
                this.cameraRunning = false;
            }
        },

        async stopCamera() {
            if (!this.qrReader || !this.cameraRunning) {
                return;
            }

            await this.qrReader.stop();
            this.cameraRunning = false;
        },

        async handleDecodedText(decodedText) {
            if (this.loading) {
                return;
            }

            await this.stopCamera();
            await this.submitToken(decodedText);
        },

        submitManual() {
            this.submitToken(this.manualToken);
        },

        async submitToken(token) {
            const cleanToken = token ? String(token).trim() : '';

            if (!cleanToken || this.loading) {
                return;
            }

            this.loading = true;

            try {
                const response = await fetch(verifyUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        token: cleanToken,
                        device_info: window.navigator.userAgent,
                    }),
                });

                const payload = await response.json();

                this.result = response.ok ? payload : {
                    result: 'invalid',
                    message: payload.message || 'Scan tidak dapat diproses.',
                    permit: null,
                };
                this.manualToken = '';
            } catch (error) {
                this.result = {
                    result: 'invalid',
                    message: 'Scan gagal diproses.',
                    permit: null,
                };
            } finally {
                this.loading = false;
            }
        },
    };
};

window.Alpine = Alpine;
Alpine.start();
