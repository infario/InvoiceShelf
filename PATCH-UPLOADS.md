# Fork runbook — WhatsApp Share on InvoiceShelf 2.4.x

This fork (`infario/2.4`, based on the upstream 2.4.x tag the server runs)
exists for one feature: WhatsApp invoice sharing. Its entire runtime core
diff is **one file**:

- `resources/scripts/admin/components/dropdowns/InvoiceIndexDropdown.vue`
  (renders a "Send on WhatsApp" item when the WhatsappShare module is
  enabled and dispatches a window event; all logic lives in
  `Modules/WhatsappShare/`, which the inbuilt updater never touches —
  `Modules` is in `update_protected_paths`.)

Supporting, non-runtime changes in the fork: `Modules/WhatsappShare/`
(module source + committed `dist/`), `.gitignore` (tracks the module),
`tests/Feature/WhatsappShareSettingsTest.php`, this runbook. None of
those are uploaded to the server except the module folder itself.

## Production update procedure (inbuilt updater + re-upload)

The server (client.infario.com) keeps using InvoiceShelf's **inbuilt
updater**, staying on the **2.4.x stable channel**. Never accept a v3
major upgrade through the updater — 3.x is a rewrite; that is a
migration project, not an update.

After each inbuilt update (rehearse on staging first):

1. Locally: `git fetch upstream --tags`, merge the new tag into
   `infario/2.4` (`git merge 2.4.8`). Conflicts are expected at most in
   the dropdown file above.
2. Verify: `php artisan test --compact --filter=WhatsappShareSettingsTest`
   plus the invoice suite, then `pnpm build`.
3. Generate the upload list against the **new** tag — never from memory,
   never reuse files built against the old version:
   `git diff --name-only 2.4.8..infario/2.4`
4. Upload the changed runtime files plus the **full** regenerated
   `public/build/` directory (Vite manifest + hashed chunks must match;
   never cherry-pick chunks). `Modules/` needs no re-upload unless the
   module itself changed.
5. Clear caches once after PHP uploads. Shared hosting has no shell, so
   use the module's activation script (next section) — it ends with
   `optimize:clear`. On a machine with shell, `php artisan optimize:clear`
   does the same.
6. Smoke test: login → invoice list → dropdown shows "Send on WhatsApp"
   → click opens the correct `wa.me` chat; `/admin/whatsapp-share`
   loads and settings save.

## Activation on shared hosting (no shell, no phpMyAdmin)

Admin → Modules is the **marketplace** UI: without an invoiceshelf.com
API token it shows only the connect form, and sideloaded modules never
appear there at all. Activate instead with the script shipped in the
module at `Modules/WhatsappShare/activate-on-server.php`:

1. Set `$expectedKey` in the script to a long random string.
2. FTP it into `public/` under any name (e.g. `whatsapp-activate-temp.php`).
3. Visit `https://client.infario.com/whatsapp-activate-temp.php?key=YOUR-KEY`.
   It runs `ModuleInstaller::complete()` (statuses file +
   `modules`-table row + cache clear) and prints the row back.
4. **Delete the script from `public/` immediately.**
5. Hard-refresh (`Ctrl+Shift+R`) and smoke test as above.

The `modules` row and `storage/app/modules_statuses.json` live outside
the updater's overwrite set, so activation survives inbuilt updates.
Re-run the script only after re-uploading module PHP files, or if the
dropdown item disappears.

## Local development notes

- PHP 8.4+ is required by the lock file. On this machine a standalone
  PHP 8.4 lives at `D:\php84\php.exe` (XAMPP's 8.2 cannot install deps).
- Run tests/builds from the branch root:
  `D:\php84\php.exe artisan test --compact`, `pnpm build`.
- Rebuild the module bundle after touching module JS/Vue:
  `pnpm exec vite build --config Modules/WhatsappShare/vite.config.js`
  and commit the regenerated `dist/`.
- Known pre-existing failures on Windows (also on clean 2.4.7, upstream
  CI is Linux): 2 tests in `tests/Unit/UpdaterTest.php`
  (path-separator handling in `cleanStaleFiles`).
