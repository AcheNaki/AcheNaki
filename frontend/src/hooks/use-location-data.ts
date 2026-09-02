"use client";

import { useCallback, useEffect, useState } from "react";
import { getAreas, getSubAreas } from "@/lib/api/client";
import type { Area, SubArea } from "@/lib/api/types";

interface RemoteData<T> {
  data: T[];
  loading: boolean;
  error: boolean;
  retry: () => void;
}

function isAbort(error: unknown): boolean {
  return error instanceof Error && error.name === "AbortError";
}

export function useAreas(): RemoteData<Area> {
  const [attempt, setAttempt] = useState(0);
  const [result, setResult] = useState<{
    attempt: number;
    data: Area[];
    error: boolean;
  }>({ attempt: -1, data: [], error: false });
  const retry = useCallback(() => setAttempt((value) => value + 1), []);

  useEffect(() => {
    const controller = new AbortController();

    getAreas(controller.signal)
      .then((data) => setResult({ attempt, data, error: false }))
      .catch((reason: unknown) => {
        if (!isAbort(reason)) setResult({ attempt, data: [], error: true });
      });

    return () => controller.abort();
  }, [attempt]);

  const isCurrent = result.attempt === attempt;
  return {
    data: isCurrent ? result.data : [],
    loading: !isCurrent,
    error: isCurrent && result.error,
    retry,
  };
}

export function useSubAreas(areaId: number | null): RemoteData<SubArea> {
  const [attempt, setAttempt] = useState(0);
  const [result, setResult] = useState<{
    areaId: number | null;
    attempt: number;
    data: SubArea[];
    error: boolean;
  }>({ areaId: null, attempt: 0, data: [], error: false });
  const retry = useCallback(() => setAttempt((value) => value + 1), []);

  useEffect(() => {
    if (areaId === null) return;

    const controller = new AbortController();

    getSubAreas(areaId, controller.signal)
      .then((data) => setResult({ areaId, attempt, data, error: false }))
      .catch((reason: unknown) => {
        if (!isAbort(reason)) setResult({ areaId, attempt, data: [], error: true });
      });

    return () => controller.abort();
  }, [areaId, attempt]);

  if (areaId === null) {
    return { data: [], loading: false, error: false, retry };
  }

  const hasCurrentResult = result.areaId === areaId && result.attempt === attempt;

  return {
    data: hasCurrentResult ? result.data : [],
    loading: !hasCurrentResult,
    error: hasCurrentResult && result.error,
    retry,
  };
}
