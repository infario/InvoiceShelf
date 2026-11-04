/**
 * Helpers for recurring-invoice frequency UI.
 *
 * The backend only stores a 5-field cron expression. These helpers build and
 * parse common human schedules (yearly / monthly on a chosen day) so the
 * create form does not force users to type cron by hand.
 */

export const FREQUENCY_SENTINELS = {
  YEARLY_ON_START: 'YEARLY_ON_START',
  YEARLY_ON_DATE: 'YEARLY_ON_DATE',
  MONTHLY_ON_DAY: 'MONTHLY_ON_DAY',
  CUSTOM: 'CUSTOM',
}

/**
 * @param {{ minute?: number, hour?: number, day: number, month: number }} parts
 * @returns {string}
 */
export function buildYearlyCron({ minute = 0, hour = 0, day, month }) {
  return `${clamp(minute, 0, 59)} ${clamp(hour, 0, 23)} ${clamp(day, 1, 31)} ${clamp(month, 1, 12)} *`
}

/**
 * @param {{ minute?: number, hour?: number, day: number }} parts
 * @returns {string}
 */
export function buildMonthlyCron({ minute = 0, hour = 0, day }) {
  return `${clamp(minute, 0, 59)} ${clamp(hour, 0, 23)} ${clamp(day, 1, 31)} * *`
}

/**
 * Parse a yearly-on-fixed-date cron (`M H D Mo *`). Returns null if the
 * expression is not that shape (wildcards, lists, steps, or weekday set).
 *
 * @param {string|null|undefined} cron
 * @returns {{ minute: number, hour: number, day: number, month: number }|null}
 */
export function parseYearlyCron(cron) {
  const parts = splitCron(cron)
  if (!parts) {
    return null
  }

  const [minute, hour, day, month, weekday] = parts
  if (weekday !== '*' || !isPlainInt(minute) || !isPlainInt(hour) || !isPlainInt(day) || !isPlainInt(month)) {
    return null
  }

  const parsed = {
    minute: Number(minute),
    hour: Number(hour),
    day: Number(day),
    month: Number(month),
  }

  if (
    parsed.minute < 0 ||
    parsed.minute > 59 ||
    parsed.hour < 0 ||
    parsed.hour > 23 ||
    parsed.day < 1 ||
    parsed.day > 31 ||
    parsed.month < 1 ||
    parsed.month > 12
  ) {
    return null
  }

  return parsed
}

/**
 * Parse a monthly-on-day cron (`M H D * *`).
 *
 * @param {string|null|undefined} cron
 * @returns {{ minute: number, hour: number, day: number }|null}
 */
export function parseMonthlyCron(cron) {
  const parts = splitCron(cron)
  if (!parts) {
    return null
  }

  const [minute, hour, day, month, weekday] = parts
  if (
    month !== '*' ||
    weekday !== '*' ||
    !isPlainInt(minute) ||
    !isPlainInt(hour) ||
    !isPlainInt(day)
  ) {
    return null
  }

  const parsed = {
    minute: Number(minute),
    hour: Number(hour),
    day: Number(day),
  }

  if (
    parsed.minute < 0 ||
    parsed.minute > 59 ||
    parsed.hour < 0 ||
    parsed.hour > 23 ||
    parsed.day < 1 ||
    parsed.day > 31
  ) {
    return null
  }

  return parsed
}

/**
 * Build a yearly cron from a starts_at value (Date, ISO string, or Y-m-d).
 *
 * @param {Date|string|null|undefined} startsAt
 * @param {{ minute?: number, hour?: number }} [time]
 * @returns {string|null}
 */
export function yearlyCronFromStartsAt(startsAt, time = {}) {
  const date = toDateParts(startsAt)
  if (!date) {
    return null
  }

  return buildYearlyCron({
    day: date.day,
    month: date.month,
    hour: time.hour ?? 0,
    minute: time.minute ?? 0,
  })
}

/**
 * @param {Date|string|null|undefined} value
 * @returns {{ day: number, month: number, year: number }|null}
 */
export function toDateParts(value) {
  if (!value) {
    return null
  }

  if (value instanceof Date && !Number.isNaN(value.getTime())) {
    return {
      day: value.getDate(),
      month: value.getMonth() + 1,
      year: value.getFullYear(),
    }
  }

  const raw = String(value).trim()
  // Prefer Y-m-d / ISO prefix so timezone shifts do not move the calendar day.
  const iso = raw.match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (iso) {
    return {
      year: Number(iso[1]),
      month: Number(iso[2]),
      day: Number(iso[3]),
    }
  }

  const parsed = new Date(raw)
  if (Number.isNaN(parsed.getTime())) {
    return null
  }

  return {
    day: parsed.getDate(),
    month: parsed.getMonth() + 1,
    year: parsed.getFullYear(),
  }
}

/**
 * @param {string|null|undefined} cron
 * @returns {string[]|null}
 */
function splitCron(cron) {
  if (!cron || typeof cron !== 'string') {
    return null
  }

  const parts = cron.trim().split(/\s+/)
  return parts.length === 5 ? parts : null
}

function isPlainInt(value) {
  return /^\d+$/.test(String(value))
}

function clamp(value, min, max) {
  const n = Number(value)
  if (Number.isNaN(n)) {
    return min
  }
  return Math.min(max, Math.max(min, Math.trunc(n)))
}
