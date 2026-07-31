import { REPORT_CATEGORIES } from './reportOptions';

export const REPORT_STATUS_OPTIONS = [
  { value: '', label: 'All statuses' },
  { value: 'pending', label: 'Pending' },
  { value: 'reviewed', label: 'Reviewed' },
  { value: 'resolved', label: 'Resolved' },
  { value: 'rejected', label: 'Rejected' },
];

export const REPORT_SEVERITY_OPTIONS = [
  { value: '', label: 'All severities' },
  { value: 'low', label: 'Low' },
  { value: 'normal', label: 'Normal' },
  { value: 'high', label: 'High' },
  { value: 'critical', label: 'Critical' },
];

export const REPORT_CATEGORY_OPTIONS = [
  { value: '', label: 'All categories' },
  ...REPORT_CATEGORIES,
];

const labelFrom = (options, value) => (
  options.find((option) => option.value === value)?.label || value || 'Unknown'
);

export const reportStatusLabel = (value) => labelFrom(REPORT_STATUS_OPTIONS, value);
export const reportSeverityLabel = (value) => labelFrom(REPORT_SEVERITY_OPTIONS, value);
export const reportCategoryLabel = (value) => labelFrom(REPORT_CATEGORY_OPTIONS, value);

export const isClosedReport = (report) => ['resolved', 'rejected'].includes(report?.status);

export function formatReportDate(value) {
  if (!value) return 'Not set';

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value));
}

export function cleanReportError(error, fallback = 'Unable to update report.') {
  return error?.response?.data?.message || fallback;
}
