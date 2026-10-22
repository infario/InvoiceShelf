# WhatsApp Share for InvoiceShelf 2.4.x

Send invoices to customers on WhatsApp with one click. Clicking **Send on
WhatsApp** opens a `wa.me` chat with a pre-filled message — nothing is sent
until the user presses send in WhatsApp, and the customer can be sent the
PDF manually from the same chat. No WhatsApp Business API, no credentials,
no cost.

## Features

- **Invoice dropdown action** — "Send on WhatsApp" next to Send/Resend
  Invoice in the invoice list, invoice detail view and dashboard
  (requires the one-file core patch, see Install).
- **Module page** at `/admin/whatsapp-share` — searchable/filterable
  invoice list with a Send button per row, works with or without the
  core patch.
- **Per-company settings** on the same page: default country code and a
  message template with `{customer_name}`, `{invoice_number}`,
  `{amount_due}`, `{due_date}` and `{company_name}` placeholders.
- **Phone normalization** — handles `+91 98200 12345`, trunk-zero and
  `0091...` formats and bare national numbers; unusable numbers disable
  the button instead of opening a broken chat.

Deliberate non-goals for this MVP: opening WhatsApp does not confirm
delivery, so invoice status is never changed automatically (use the
existing "Mark as Sent"), and no PDF link is attached yet.

## Requirements

- InvoiceShelf **2.4.x** (developed and tested on 2.4.7)
- A customer phone number on the invoice's customer

## Install

1. Copy this `WhatsappShare` folder into your install's `Modules/`
   directory (or upload the module zip via Admin → Modules).
2. Enable it: Admin → Modules → WhatsappShare, or
   `php artisan install:module WhatsappShare 0.1.0`.
3. Open `/admin/whatsapp-share`, set your country code and template.

### Optional: the dropdown button (one-file core patch)

The module page works on a stock install. The in-dropdown button needs
the host to render one extra menu item, shipped in this fork as a single
change to
`resources/scripts/admin/components/dropdowns/InvoiceIndexDropdown.vue`
(it only dispatches a `whatsapp-share:send-invoice` window event with
the invoice; all WhatsApp logic stays in the module). Apply
`core-patch/invoice-dropdown-whatsapp.patch`, rebuild the frontend
(`pnpm build`) and upload the regenerated `public/build/` — see
`PATCH-UPLOADS.md` in the fork for the full update/re-upload runbook.

## Development

The frontend bundle is built with the host's toolchain and committed in
`dist/`, so installs never build anything:

```bash
# from the repository root
pnpm exec vite build --config Modules/WhatsappShare/vite.config.js
```

Two build details matter:

- **Vue is externalized to `window.Vue`** via a shim plugin in
  `vite.config.js`. Bundling a second Vue copy breaks reactivity across
  the host/module boundary (host renderer never sees module ref
  updates).
- **`process.env.NODE_ENV` is defined** — Vue/axios probe it and the
  browser has no `process`.

Settings API (Sanctum + `company` header, like core APIs):

- `GET /api/m/whatsapp-share/settings`
- `POST /api/m/whatsapp-share/settings`
  `{ "default_country_code": "91", "message_template": "..." }`

Values are stored per company in `company_settings` as
`whatsapp_share_default_country_code` / `whatsapp_share_message_template`.
No tables, no migrations.

## Updates

`Modules/` is in the updater's `update_protected_paths`, so this module
survives InvoiceShelf's inbuilt updates untouched. Only the optional
core patch needs re-applying after an update — the fork's
`PATCH-UPLOADS.md` documents that procedure.

## License

AGPL-3.0, matching InvoiceShelf.
