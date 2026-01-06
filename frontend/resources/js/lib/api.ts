// Simple API helper to allow switching between Laravel and Java backends.
// If VITE_API_BASE is set (e.g., http://localhost:8081), calls will be made to that host.
// Otherwise it falls back to same-origin ("" prefix). In dev, if the app runs on 8000 and
// VITE_API_BASE isn't provided, we default to the Spring Boot port 8081 to avoid JSON errors.

const rawEnvBase = (import.meta as any)?.env?.VITE_API_BASE as string | undefined;
let resolvedBase = (rawEnvBase ?? '').trim().replace(/\/$/, '');

if (!resolvedBase && typeof window !== 'undefined') {
  const port = window.location.port;
  // Heuristic: when UI is served by Laravel at 8000 in dev, backend is at 8081 by convention.
  if (port === '8000') {
    resolvedBase = 'http://localhost:8081';
    // Avoid noisy logs; fallback is safe in dev.
  }
}

export const apiBase: string = resolvedBase;

export const apiUrl = (path: string): string => {
  if (!path.startsWith('/')) return `${apiBase}/${path}`;
  return `${apiBase}${path}`;
};

export const apiFetch: typeof fetch = (path: RequestInfo | URL, init?: RequestInit) => {
  if (typeof path === 'string') {
    return fetch(apiUrl(path), init);
  }
  return fetch(path, init);
};
