/**
 * Background refresh for read-only list endpoints.
 *
 * Kept deliberately conservative so an open tab costs almost nothing:
 * - only one request in flight at a time (chained setTimeout, never setInterval)
 * - completely asleep while the tab is hidden, with a catch-up refresh on return
 * - slows down to `idleInterval` after a few unchanged responses and snaps back
 *   to `interval` as soon as something actually changes
 * - errors back off quietly and leave whatever the page last rendered in place
 *
 * The fetcher should return `true` when it changed what is on screen, so the
 * poller can tell an active page from an idle one.
 */
export function createLivePoll({
  fetcher,
  interval = 20000,
  idleInterval = 90000,
  errorInterval = 45000,
  minGap = 4000,
  unchangedBeforeIdle = 4,
  isPaused = () => false,
} = {}) {
  let timerId = null;
  let running = false;
  let inFlight = false;
  let unchangedStreak = 0;
  let lastRunAt = 0;

  function nextDelay() {
    return unchangedStreak >= unchangedBeforeIdle ? idleInterval : interval;
  }

  function clearTimer() {
    if (timerId !== null) {
      clearTimeout(timerId);
      timerId = null;
    }
  }

  function schedule(delay) {
    clearTimer();
    if (running) {
      timerId = setTimeout(tick, delay);
    }
  }

  async function run() {
    inFlight = true;
    lastRunAt = Date.now();
    try {
      const changed = await fetcher();
      unchangedStreak = changed ? 0 : unchangedStreak + 1;
      return !!changed;
    } finally {
      inFlight = false;
    }
  }

  async function tick() {
    if (!running) return;

    if (document.hidden) {
      // Stay stopped until the tab is visible again; onVisibility restarts us.
      clearTimer();
      return;
    }

    // A pause is not evidence that the data is idle, so the streak is left alone.
    if (inFlight || isPaused()) {
      schedule(interval);
      return;
    }

    try {
      await run();
      schedule(nextDelay());
    } catch {
      unchangedStreak = 0;
      schedule(errorInterval);
    }
  }

  /**
   * Refresh immediately, throttled by `minGap` so bursts of triggers collapse
   * into a single request. Never rejects.
   */
  async function refreshNow({ force = false } = {}) {
    if (!running || inFlight || isPaused()) return false;
    if (!force && Date.now() - lastRunAt < minGap) return false;

    try {
      const changed = await run();
      schedule(nextDelay());
      return changed;
    } catch {
      unchangedStreak = 0;
      schedule(errorInterval);
      return false;
    }
  }

  function onVisibility() {
    if (document.hidden) {
      clearTimer();
      return;
    }
    // Schedule first so the loop survives even if the catch-up is throttled away.
    schedule(nextDelay());
    refreshNow();
  }

  function start() {
    if (running) return;
    running = true;
    document.addEventListener("visibilitychange", onVisibility);
    schedule(interval);
  }

  function stop() {
    running = false;
    clearTimer();
    document.removeEventListener("visibilitychange", onVisibility);
  }

  return { start, stop, refreshNow };
}

/**
 * Cheap fingerprint of a payload, used to skip re-rendering when a poll returns
 * data the page is already showing. Falls back to a unique value so an
 * unserializable payload is treated as changed rather than silently dropped.
 */
export function dataSignature(value) {
  try {
    return JSON.stringify(value);
  } catch {
    return `unserializable:${Date.now()}:${Math.random()}`;
  }
}
