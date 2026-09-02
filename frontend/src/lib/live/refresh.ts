"use client";

import { useCallback, useEffect, useRef, useState } from "react";

export function nextRefreshDelay(normalMs: number, failures: number, maxMs: number): number {
  return Math.min(normalMs * 2 ** failures, maxMs);
}

export function statusChanged<T>(previous: T | null, next: T, compare: (a: T, b: T) => boolean): boolean {
  return previous !== null && !compare(previous, next);
}

interface LiveResourceOptions<T> {
  key: string | number | null;
  initialData?: T | null;
  intervalMs: number;
  maxBackoffMs: number;
  load: (signal: AbortSignal) => Promise<T>;
  enabled?: boolean;
  onData?: (data: T, previous: T | null) => void;
  onError?: (reason: unknown, previous: T | null) => void;
}

export function useLiveResource<T>({
  key,
  initialData = null,
  intervalMs,
  maxBackoffMs,
  load,
  enabled = true,
  onData,
  onError,
}: LiveResourceOptions<T>) {
  const [data, setData] = useState<T | null>(initialData);
  const [error, setError] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [paused, setPaused] = useState(false);
  const [lastUpdatedAt, setLastUpdatedAt] = useState<number | null>(null);
  const [refreshVersion, setRefreshVersion] = useState(0);
  const loadRef = useRef(load);
  const onDataRef = useRef(onData);
  const onErrorRef = useRef(onError);
  const dataRef = useRef<T | null>(initialData);
  useEffect(() => {
    loadRef.current = load;
    onDataRef.current = onData;
    onErrorRef.current = onError;
  }, [load, onData, onError]);

  useEffect(() => {
    dataRef.current = initialData;
    // A route/locality key is an external data-source transition; keep its supplied server snapshot.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setData(initialData);
    setError(false);
    setLastUpdatedAt(null);
  }, [initialData, key]);

  useEffect(() => {
    if (!enabled || key === null) return;
    let timer: ReturnType<typeof setTimeout> | null = null;
    let controller: AbortController | null = null;
    let inFlight = false;
    let failures = 0;
    let disposed = false;

    const canRefresh = () => typeof document === "undefined" || (document.visibilityState === "visible" && navigator.onLine);
    const setPauseState = () => setPaused(!canRefresh());
    const schedule = (delay: number) => {
      if (disposed || !canRefresh()) return;
      timer = setTimeout(refresh, delay);
    };
    const refresh = () => {
      if (disposed || inFlight || !canRefresh()) { setPauseState(); return; }
      inFlight = true;
      controller = new AbortController();
      setRefreshing(true);
      loadRef.current(controller.signal)
        .then((next) => {
          if (disposed) return;
          const previous = dataRef.current;
          dataRef.current = next;
          setData(next);
          setError(false);
          setLastUpdatedAt(Date.now());
          onDataRef.current?.(next, previous);
          failures = 0;
        })
        .catch((reason: unknown) => {
          if (disposed || (reason instanceof Error && reason.name === "AbortError")) return;
          failures = Math.min(failures + 1, 2);
          setError(true);
          onErrorRef.current?.(reason, dataRef.current);
        })
        .finally(() => {
          if (disposed) return;
          inFlight = false;
          controller = null;
          setRefreshing(false);
          schedule(nextRefreshDelay(intervalMs, failures, maxBackoffMs));
        });
    };
    const resume = () => {
      setPauseState();
      if (canRefresh()) {
        if (timer) clearTimeout(timer);
        refresh();
      }
    };

    setPauseState();
    refresh();
    document.addEventListener("visibilitychange", resume);
    window.addEventListener("online", resume);
    window.addEventListener("offline", setPauseState);
    return () => {
      disposed = true;
      if (timer) clearTimeout(timer);
      controller?.abort();
      document.removeEventListener("visibilitychange", resume);
      window.removeEventListener("online", resume);
      window.removeEventListener("offline", setPauseState);
    };
  }, [enabled, intervalMs, key, maxBackoffMs, refreshVersion]);

  const refresh = useCallback(() => setRefreshVersion((value) => value + 1), []);

  return { data, error, refreshing, paused, lastUpdatedAt, refresh };
}
