import type {
  ElectricityReportPayload,
  ElectricityStatus,
  GasReportPayload,
  GasStatus,
  TimeBucket,
  UtilityType,
} from "../api/types";

export interface ChoiceOption<T extends string> {
  value: T;
  label: string;
  description?: string;
  symbol?: string;
}

export const utilityOptions: ChoiceOption<UtilityType>[] = [
  { value: "ELECTRICITY", label: "Electricity", description: "Power and voltage", symbol: "⚡" },
  { value: "GAS", label: "Gas", description: "Household gas pressure", symbol: "◒" },
];

export const electricityStatusOptions: ChoiceOption<ElectricityStatus>[] = [
  { value: "AVAILABLE", label: "Power Available", description: "Power is available now", symbol: "⚡" },
  { value: "UNAVAILABLE", label: "No Power / Loadshedding", description: "No electricity right now", symbol: "○" },
  { value: "UNSTABLE", label: "Voltage Unstable", description: "Voltage or supply is unstable", symbol: "⚠" },
];

export const gasStatusOptions: ChoiceOption<GasStatus>[] = [
  { value: "NORMAL", label: "Normal", description: "Usual cooking pressure", symbol: "●" },
  { value: "LOW", label: "Low", description: "Lower than usual", symbol: "◐" },
  { value: "VERY_LOW", label: "Very Low", description: "Barely usable pressure", symbol: "◔" },
  { value: "UNAVAILABLE", label: "No Gas", description: "No usable gas right now", symbol: "○" },
];

export const timeBucketOptions: ChoiceOption<TimeBucket>[] = [
  { value: "NOW", label: "Just now" },
  { value: "MIN_5", label: "~5 min ago" },
  { value: "MIN_15", label: "~15 min ago" },
  { value: "MIN_30", label: "~30 min ago" },
  { value: "HOUR_1", label: "~1 hour ago" },
  { value: "HOUR_2", label: "~2 hours ago" },
  { value: "OVER_2_HOURS", label: "2+ hours ago" },
  { value: "UNKNOWN", label: "Not sure" },
];

export function statusOptionsFor(utility: UtilityType | null) {
  if (utility === "ELECTRICITY") return electricityStatusOptions;
  if (utility === "GAS") return gasStatusOptions;
  return [];
}

export function labelFor<T extends string>(options: ChoiceOption<T>[], value: T): string {
  return options.find((option) => option.value === value)?.label ?? value;
}

interface PayloadInput {
  areaId: number;
  subAreaId: number;
  utility: UtilityType;
  status: ElectricityStatus | GasStatus;
  timeBucket: TimeBucket;
  canCook?: boolean | null;
}

export function buildReportPayload(input: PayloadInput): ElectricityReportPayload | GasReportPayload {
  if (input.utility === "ELECTRICITY") {
    return {
      area_id: input.areaId,
      sub_area_id: input.subAreaId,
      utility_type: "ELECTRICITY",
      status: input.status as ElectricityStatus,
      time_bucket: input.timeBucket,
    };
  }

  return {
    area_id: input.areaId,
    sub_area_id: input.subAreaId,
    utility_type: "GAS",
    status: input.status as GasStatus,
    time_bucket: input.timeBucket,
    ...(input.canCook !== undefined ? { can_cook: input.canCook } : {}),
  };
}

