import http from '@/scripts/http'

/**
 * WhatsApp Share helpers.
 *
 * Everything a "Send on WhatsApp" click needs that is not UI: turning a
 * free-form customer phone number into the digits wa.me expects, turning
 * a message template plus an invoice into the final text, and opening
 * the chat. Kept free of Vue so the module page, the dropdown listener
 * in module.js and future surfaces all share one implementation.
 */

let settingsCache = null
let settingsPromise = null

export const DEFAULT_SETTINGS = {
  default_country_code: '91',
  message_template:
    'Hello {customer_name}, your invoice {invoice_number} of {amount_due} is due on {due_date}. Thank you! - {company_name}',
}

export async function fetchWhatsappSettings(force = false) {
  if (settingsCache && !force) {
    return settingsCache
  }

  if (!settingsPromise || force) {
    settingsPromise = http
      .get('/api/m/whatsapp-share/settings')
      .then((response) => {
        settingsCache = { ...DEFAULT_SETTINGS, ...response.data }
        return settingsCache
      })
      .catch(() => {
        settingsCache = { ...DEFAULT_SETTINGS }
        return settingsCache
      })
      .finally(() => {
        settingsPromise = null
      })
  }

  return settingsPromise
}

export async function saveWhatsappSettings(settings) {
  const response = await http.post('/api/m/whatsapp-share/settings', settings)
  settingsCache = { ...DEFAULT_SETTINGS, ...settings }
  return response
}

/**
 * Normalize a free-form phone number to international digits for wa.me.
 *
 * Handles the shapes customers actually store: "+91 98200 12345",
 * "098200 12345" (trunk zero), "0091..." (international prefix) and
 * bare national numbers (country code prepended from settings).
 * Returns null when what remains cannot be a real number (E.164
 * allows 8 to 15 digits), so callers can refuse instead of opening
 * a broken chat.
 */
export function normalizePhone(raw, defaultCountryCode) {
  if (!raw) {
    return null
  }

  let digits = String(raw).replace(/\D/g, '')

  if (digits.startsWith('00')) {
    digits = digits.slice(2)
  }

  if (digits.startsWith('0')) {
    digits = digits.slice(1)
  }

  const countryCode = String(defaultCountryCode || '').replace(/\D/g, '')

  if (countryCode && !digits.startsWith(countryCode)) {
    digits = countryCode + digits
  }

  if (digits.length < 8 || digits.length > 15) {
    return null
  }

  return digits
}

export function formatAmount(invoice) {
  const currency = invoice.currency || {}
  const precision = currency.precision ?? 2
  const amount = (Number(invoice.due_amount) || 0) / Math.pow(10, precision)
  const formatted = amount.toLocaleString(undefined, {
    minimumFractionDigits: precision,
    maximumFractionDigits: precision,
  })

  return currency.code ? `${currency.code} ${formatted}` : formatted
}

export function renderMessage(template, invoice) {
  const replacements = {
    '{customer_name}': invoice.customer?.name || '',
    '{invoice_number}': invoice.invoice_number || '',
    '{amount_due}': formatAmount(invoice),
    '{due_date}': invoice.formatted_due_date || invoice.due_date || '',
    '{company_name}': invoice.company?.name || '',
  }

  let message = template || DEFAULT_SETTINGS.message_template

  for (const [placeholder, value] of Object.entries(replacements)) {
    message = message.split(placeholder).join(String(value))
  }

  return message
}

export function buildWaMeUrl(phone, message) {
  return `https://wa.me/${phone}?text=${encodeURIComponent(message)}`
}

/**
 * The one entry point every surface calls. Resolves true when a chat
 * was opened; throws an Error with a user-readable message when the
 * customer has no usable WhatsApp number.
 */
export async function sendInvoiceViaWhatsapp(invoice) {
  const settings = await fetchWhatsappSettings()
  const phone = normalizePhone(invoice?.customer?.phone, settings.default_country_code)

  if (!phone) {
    throw new Error(
      `No valid WhatsApp number for ${invoice?.customer?.name || 'this customer'}. Add a phone number with country code to the customer and try again.`
    )
  }

  const message = renderMessage(settings.message_template, invoice)

  window.open(buildWaMeUrl(phone, message), '_blank', 'noopener')

  return true
}
