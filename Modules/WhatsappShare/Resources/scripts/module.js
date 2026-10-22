import WhatsappInvoicesPage from './views/WhatsappInvoicesPage.vue'
import moduleLocales from '~/locales/locales'
import { sendInvoiceViaWhatsapp } from './whatsapp'

/**
 * WhatsApp Share module entry.
 *
 * The host shell loads this bundle for every page (ModuleFacade::script),
 * and window.InvoiceShelf.booting hands us the app and router before
 * mount. Two registrations happen here:
 *
 * 1. The module page at /admin/whatsapp-share (list + settings).
 * 2. The listener for the core dropdown's 'whatsapp-share:send-invoice'
 *    window event. Core (InvoiceIndexDropdown.vue) only dispatches the
 *    event with the invoice row; every WhatsApp decision — settings,
 *    phone normalization, template, wa.me URL — lives in this module,
 *    so removing the module removes the feature cleanly.
 */
window.InvoiceShelf.booting((app, router) => {
  window.InvoiceShelf.addMessages(moduleLocales)

  router.addRoute({
    path: '/admin/whatsapp-share',
    name: 'whatsapp-share',
    component: WhatsappInvoicesPage,
    meta: { requiresAuth: true },
  })

  window.addEventListener('whatsapp-share:send-invoice', async (event) => {
    const invoice = event.detail?.invoice

    if (!invoice) {
      return
    }

    try {
      await sendInvoiceViaWhatsapp(invoice)
    } catch (error) {
      window.alert(error.message)
    }
  })
})
