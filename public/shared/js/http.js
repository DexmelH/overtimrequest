export async function fetchWithTimeout(url, options = {}, timeout = 8000) {
  const controller = new AbortController();
  const id = setTimeout(() => controller.abort(), timeout);
  options.signal = controller.signal;
  try {
    const response = await fetch(url, options);
    clearTimeout(id);
    return response;
  } catch (error) {
    clearTimeout(id);
    throw error;
  }
}

export async function retryFetch(fn, attempts = 3, baseDelay = 250) {
  let lastErr;
  for (let i = 0; i < attempts; i++) {
    try {
      return await fn();
    } catch (err) {
      lastErr = err;
      const delay = baseDelay * Math.pow(2, i);
      await new Promise((res) => setTimeout(res, delay));
    }
  }
  throw lastErr;
}

export function normalizePayload(payload) {
  if (!payload) return [];
  const list = Array.isArray(payload)
    ? payload
    : payload.data && Array.isArray(payload.data)
      ? payload.data
      : [];
  return list.map((p) => ({
    id: String(p.fldID ?? p.id ?? p.key ?? ""),
    name: String(
      p.fldLocation ??
        p.abbreviation ??
        p.fldProject ??
        p.fldItem ??
        p.fldJob ??
        p.fldTOW ??
        p.name ??
        "",
    ),
  }));
}

export async function apiGet(endpoint, { timeout = 8000, retries = 3 } = {}) {
  const response = await retryFetch(
    () =>
      fetchWithTimeout(
        endpoint,
        {
          method: "GET",
          credentials: "same-origin",
          headers: { Accept: "application/json" },
        },
        timeout,
      ),
    retries,
    300,
  );
  if (!response.ok) {
    if (response.status === 401) {
      const root = `//${document.location.hostname}`;
      window.location.href = root + "/KDTPortalLogin";
    }
    throw new Error(`Request failed (${response.status})`);
  }
  return response.json();
}

export async function apiPost(endpoint, formData, { timeout = 12000 } = {}) {
  const response = await fetchWithTimeout(
    endpoint,
    {
      method: "POST",
      credentials: "same-origin",
      body: formData,
    },
    timeout,
  );
  if (!response.ok) {
    throw new Error(`Request failed (${response.status})`);
  }
  return response.json();
}
