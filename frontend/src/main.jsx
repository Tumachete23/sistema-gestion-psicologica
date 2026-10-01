import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { checkHealth } from './health.js';
import './style.css';

function App() {
  const [status, setStatus] = useState('Comprobando conexión…');
  useEffect(() => {
    const controller = new AbortController();
    checkHealth(fetch, controller.signal)
      .then(() => setStatus('API disponible'))
      .catch((error) => {
        if (error.name !== 'AbortError') setStatus('No se pudo conectar con la API.');
      });
    return () => controller.abort();
  }, []);

  return <main>
    <p>HU-001 · Base del proyecto</p>
    <h1>Sistema de gestión psicológica</h1>
    <p>Entorno de desarrollo inicial.</p>
    <p role="status">{status}</p>
  </main>;
}

createRoot(document.getElementById('root')).render(<React.StrictMode><App /></React.StrictMode>);
