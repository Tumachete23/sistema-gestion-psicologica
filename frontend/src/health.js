export async function checkHealth(fetcher = fetch, signal) {
  const response = await fetcher('/api/health', { signal });
  if (!response.ok) throw new Error('API no disponible');
  const data = await response.json();
  if (data.status !== 'ok') throw new Error('Respuesta inesperada');
  return data.status;
}
