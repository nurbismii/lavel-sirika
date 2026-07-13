import test from 'node:test';
import assert from 'node:assert/strict';
import {
    cameraConstraints,
    cameraDirectionLabel,
    fallbackCameraId,
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

test('uses the first available camera as fallback', () => {
    assert.equal(fallbackCameraId([{ id: 'fallback-camera' }]), 'fallback-camera');
});
