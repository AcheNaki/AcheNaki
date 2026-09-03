export type CityCorporation = "DNCC" | "DSCC";

export interface Area {
  id: number;
  name: string;
  slug: string;
  city_corporation: CityCorporation;
}

export interface SubArea {
  id: number;
  name: string;
  slug: string;
}

export type UtilityType = "ELECTRICITY" | "GAS";
export type ElectricityStatus = "AVAILABLE" | "UNAVAILABLE" | "UNSTABLE";
export type GasStatus = "NORMAL" | "LOW" | "VERY_LOW" | "UNAVAILABLE";
export type TimeBucket =
  | "NOW"
  | "MIN_5"
  | "MIN_15"
  | "MIN_30"
  | "HOUR_1"
  | "HOUR_2"
  | "OVER_2_HOURS"
  | "UNKNOWN";

interface BaseReportPayload {
  area_id: number;
  sub_area_id: number;
  time_bucket: TimeBucket;
}

export interface ElectricityReportPayload extends BaseReportPayload {
  utility_type: "ELECTRICITY";
  status: ElectricityStatus;
}

export interface GasReportPayload extends BaseReportPayload {
  utility_type: "GAS";
  status: GasStatus;
  can_cook?: boolean | null;
}

export interface UtilityReport {
  id: number;
  utility_type: UtilityType;
  status: ElectricityStatus | GasStatus;
  area_id: number;
  sub_area_id: number;
  reported_at: string;
  time_bucket: TimeBucket;
  estimated_started_at: string | null;
  can_cook?: boolean | null;
}

export interface UtilityReportSubmission {
  data: UtilityReport;
  meta: { duplicate: boolean };
}

export interface ApiCollection<T> {
  data: T[];
}

export interface ApiValidationError {
  error: {
    code: "validation_failed";
    message: string;
    details: Record<string, string[]>;
  };
}

export type ConfidenceLevel = "LOW" | "MEDIUM" | "HIGH";
export type ElectricityProjectionStatus = ElectricityStatus | "MIXED" | "INSUFFICIENT_DATA";
export type GasProjectionStatus = GasStatus | "MIXED" | "INSUFFICIENT_DATA";
export type ProjectionStatus = ElectricityProjectionStatus | GasProjectionStatus;

export interface LiveUtilityStatus<TStatus extends ProjectionStatus = ProjectionStatus> {
  status: TStatus;
  confidence: ConfidenceLevel | null;
  status_since: string | null;
  recent_reports: number;
  supporting_reports: number;
  contradicting_reports: number;
  last_report_at: string | null;
}

export interface LiveStatusLocality {
  id: number;
  name: string;
  slug: string;
  area: {
    id: number;
    name: string;
    slug: string;
  };
}

export interface LocalityLiveStatus {
  sub_area: LiveStatusLocality;
  electricity: LiveUtilityStatus<ElectricityProjectionStatus>;
  gas: LiveUtilityStatus<GasProjectionStatus>;
}

interface LiveStatusListItemBase {
  sub_area: LiveStatusLocality;
}

export type LiveStatusListItem =
  | (LiveStatusListItemBase &
      LiveUtilityStatus<ElectricityProjectionStatus> & { utility_type: "ELECTRICITY" })
  | (LiveStatusListItemBase &
      LiveUtilityStatus<GasProjectionStatus> & { utility_type: "GAS" });

export interface AnalyticsCoverage {
  observed_seconds: number;
  unknown_seconds: number;
}

export interface ElectricityOutageEvent {
  id: number;
  started_at: string;
  ended_at: string | null;
  ongoing: boolean;
  start_confidence: ConfidenceLevel;
  end_confidence: ConfidenceLevel | null;
  duration_seconds: number;
}

export interface ElectricityCoverageSegment {
  status: ElectricityStatus;
  started_at: string;
  ended_at: string;
  duration_seconds: number;
}

export interface GasStateInterval {
  id: number;
  status: GasStatus;
  started_at: string;
  ended_at: string | null;
  observed_until_at: string;
  ongoing: boolean;
  start_confidence: ConfidenceLevel;
  duration_seconds: number;
}

export interface DailyUtilityAnalytics {
  sub_area: LiveStatusLocality;
  date: string;
  timezone: "Asia/Dhaka";
  window: {
    started_at: string;
    ended_at: string;
    duration_seconds: number;
    partial: boolean;
  };
  electricity: {
    outage_count: number;
    total_outage_seconds: number;
    longest_outage_seconds: number;
    ongoing_outage_seconds: number;
    state_seconds: {
      available: number;
      unavailable: number;
      unstable: number;
    };
    coverage: AnalyticsCoverage;
    segments: ElectricityCoverageSegment[];
    events: ElectricityOutageEvent[];
  };
  gas: {
    state_seconds: {
      normal: number;
      low: number;
      very_low: number;
      unavailable: number;
    };
    coverage: AnalyticsCoverage;
    intervals: GasStateInterval[];
  };
}

export interface DashboardProjectionItem extends LiveStatusListItemBase, LiveUtilityStatus {
  utility_type: UtilityType;
}

export interface DashboardData {
  calculated_at: string;
  fresh_projection_counts: { electricity: number; gas: number };
  struggling: DashboardProjectionItem[];
  recent_changes: DashboardProjectionItem[];
}

// City-wide aggregate counts. Every field is a count of accepted reports or of distinct
// localities — the payload carries no reporter, location or scoring detail.
export interface LiveSummary {
  window_minutes: number;
  reports: number;
  localities_updated: number;
  electricity_issue_localities: number;
  gas_issue_localities: number;
  currently_struggling_localities: number;
  calculated_at: string;
}

export interface RecentlyResolvedElectricityEvent {
  sub_area: {
    name: string;
    slug: string;
    area: { name: string; slug: string };
  };
  started_at: string;
  ended_at: string;
  duration_seconds: number;
}

export interface AreaLiveStatuses {
  area: Area;
  localities: LocalityLiveStatus[];
}

export interface LocationSearchResult {
  kind: "AREA" | "SUB_AREA";
  name: string;
  area: { name: string; slug: string };
  sub_area_slug: string | null;
}
