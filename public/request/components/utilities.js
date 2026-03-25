export async function fetchWithTimeout(url, options = {}, timeout = 5000) {
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
  if (Array.isArray(payload))
    return payload.map((p) => ({
      id: String(p.fldID ?? p.id ?? p.key ?? p.name),
      name: String(
        p.fldLocation ??
          p.abbreviation ??
          p.fldProject ??
          p.fldItem ??
          p.fldJob ??
          p.fldTOW,
      ),
    }));
  if (payload.data && Array.isArray(payload.data))
    return payload.data.map((p) => ({
      id: String(p.fldID ?? p.id ?? p.key ?? p.name),
      name: String(
        p.fldLocation ??
          p.abbreviation ??
          p.fldProject ??
          p.fldItem ??
          p.fldJob ??
          p.fldTOW,
      ),
    }));
  return [];
}

export function capitalizeFirst(str) {
  return str.charAt(0).toUpperCase() + str.slice(1);
}
