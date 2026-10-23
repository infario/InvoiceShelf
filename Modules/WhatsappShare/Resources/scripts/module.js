import WhatsappInvoicesPage from './views/WhatsappInvoicesPage.vue'
import moduleLocales from '~/locales/locales'
import { sendInvoiceViaWhatsapp } from './whatsapp'

/**
 * WhatsApp Share module entry.
 *
 * The host shell loads this bundle for every page (ModuleFacade::script),
 * and window.InvoiceShelf.booting hands us the app and router before
 * mount. Registrations:
 *
 * 1. The module page as a child of the named 'admin' LayoutBasic route
 *    (sidebar + header + scroll stay intact).
 * 2. The listener for the core dropdown's 'whatsapp-share:send-invoice'
 *    window event. Core (InvoiceIndexDropdown.vue) only dispatches the
 *    event with the invoice row; every WhatsApp decision lives here.
 */
window.InvoiceShelf.booting((app, router) => {
  window.InvoiceShelf.addMessages(moduleLocales)

  router.addRoute('admin', {
    path: 'whatsapp-share',
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
