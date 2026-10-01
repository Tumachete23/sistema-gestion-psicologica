import test from 'node:test';
import assert from 'node:assert/strict';
import { checkHealth } from '../src/health.js';

test('comprueba el endpoint relativo y acepta status ok', async () => {
  assert.equal(await checkHealth(async (url) => {
    assert.equal(url, '/api/health');
    return { ok: true, json: async () => ({ status: 'ok' }) };
  }), 'ok');
});

test('rechaza errores HTTP', async () => {
  await assert.rejects(checkHealth(async () => ({ ok: false })), /API no disponible/);
});

test('rechaza respuestas inesperadas', async () => {
  await assert.rejects(checkHealth(async () => ({ ok: true, json: async () => ({}) })), /Respuesta inesperada/);
});
