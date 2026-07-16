import test from 'node:test';
import assert from 'node:assert/strict';
import {
    cameraErrorMessage,
    cameraConstraints,
    cameraDirectionLabel,
    fallbackCameraId,
    fallbackCameraIds,
    oppositeCameraDirection,
} from '../../resources/js/scan-camera.mjs';

test('uses rear camera as the default request', () => {
    assert.deepEqual(cameraConstraints('environment'), {
        facingMode: { exact: 'environment' },
    });
    assert.equal(cameraDirectionLabel('environment'), 'Kamera belakang');
    assert.equal(oppositeCameraDirection('environment'), 'user');
});

test('uses front camera and identifies rear as its opposite', () => {
    assert.deepEqual(cameraConstraints('user'), {
        facingMode: { exact: 'user' },
    });
    assert.equal(cameraDirectionLabel('user'), 'Kamera depan');
    assert.equal(oppositeCameraDirection('user'), 'environment');
});

test('returns null when no fallback camera exists', () => {
    assert.equal(fallbackCameraId([]), null);
});

test('prefers a rear camera when it is not first in the device list', () => {
    assert.equal(
        fallbackCameraId([
            { id: 'front-camera', label: 'Front Camera' },
            { id: 'rear-camera', label: 'Back Camera' },
        ]),
        'rear-camera'
    );
});

test('uses the first available camera as fallback', () => {
    assert.equal(fallbackCameraId([{ id: 'fallback-camera' }]), 'fallback-camera');
});

test('orders unique rear camera IDs before other cameras', () => {
    assert.deepEqual(
        fallbackCameraIds([
            { id: 'front', label: 'Front Camera' },
            { id: 'rear', label: 'Back Camera' },
            { id: 'front', label: 'Front Camera' },
        ]),
        ['rear', 'front']
    );
});

test('explains when camera access is denied', () => {
    assert.equal(
        cameraErrorMessage({ name: 'NotAllowedError' }),
        'Izin kamera ditolak. Izinkan akses kamera di pengaturan browser.'
    );
});
