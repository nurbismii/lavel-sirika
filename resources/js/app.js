require('./bootstrap');

import Alpine from 'alpinejs';
import { Html5Qrcode } from 'html5-qrcode';
import './route-map';
import {
    cameraConstraints,
    cameraDirectionLabel,
    cameraErrorMessage,
    cameraPreflightError,
    fallbackCameraIds,
    oppositeCameraDirection,
} from './scan-camera';

window.sirikaScan = function ({ verifyUrl, csrfToken }) {
    return {
        qrReader: null,
        cameraRunning: false,
        cameraStarting: false,
        cameraDirection: 'environment',
        cameraDirectionLabel: 'Kamera belakang',
        cameraAvailable: false,
        loading: false,
        manualToken: '',
        result: null,

        async startCamera(direction = this.cameraDirection) {
            if (this.cameraRunning || this.cameraStarting || this.loading) {
                return;
            }

            this.cameraStarting = true;

            try {
                const preflightError = cameraPreflightError({
                    isSecureContext: window.isSecureContext,
                    hasMediaDevices: Boolean(window.navigator.mediaDevices?.getUserMedia),
                });

                if (preflightError) {
                    throw preflightError;
                }

                this.qrReader = new Html5Qrcode('sirika-qr-reader');
                const config = { fps: 10, qrbox: { width: 240, height: 240 } };
                const onSuccess = (decodedText) => this.handleDecodedText(decodedText);
                const onFailure = () => {};

                try {
                    await this.qrReader.start(
                        cameraConstraints(direction),
                        config,
                        onSuccess,
                        onFailure
                    );
                    this.cameraDirection = direction;
                } catch (error) {
                    let finalError = error;

                    for (const cameraId of fallbackCameraIds(await Html5Qrcode.getCameras())) {
                        try {
                            this.qrReader = new Html5Qrcode('sirika-qr-reader');
                            await this.qrReader.start(cameraId, config, onSuccess, onFailure);
                            this.cameraDirection = null;
                            finalError = null;
                            break;
                        } catch (fallbackError) {
                            finalError = fallbackError;
                        }
                    }

                    if (finalError) {
                        throw finalError;
                    }
                }

                this.cameraDirectionLabel = this.cameraDirection
                    ? cameraDirectionLabel(this.cameraDirection)
                    : 'Kamera perangkat';
                this.cameraRunning = true;
                this.cameraAvailable = true;
            } catch (error) {
                console.error(error);
                this.result = {
                    result: 'invalid',
                    message: cameraErrorMessage(error),
                    permit: null,
                };
                this.cameraRunning = false;
                this.cameraAvailable = false;
            } finally {
                this.cameraStarting = false;
            }
        },

        async switchCamera() {
            if (!this.cameraRunning || this.loading) {
                return;
            }

            await this.stopCamera();
            await this.startCamera(oppositeCameraDirection(this.cameraDirection));
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
