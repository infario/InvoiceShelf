<template>
  <div class="wa-page">
    <header class="wa-header">
      <div>
        <h1 class="wa-title">{{ $t('whatsapp_share.title') }}</h1>
        <p class="wa-subtitle">{{ $t('whatsapp_share.subtitle') }}</p>
      </div>
      <a class="wa-back" href="/admin/invoices">{{ $t('whatsapp_share.back_to_invoices') }}</a>
    </header>

    <section class="wa-card">
      <h2 class="wa-card-title">{{ $t('whatsapp_share.settings') }}</h2>
      <div class="wa-settings-grid">
        <label class="wa-field">
          <span>{{ $t('whatsapp_share.default_country_code') }}</span>
          <input v-model="settings.default_country_code" type="text" inputmode="numeric" placeholder="91" />
          <small>{{ $t('whatsapp_share.default_country_code_hint') }}</small>
        </label>
        <label class="wa-field wa-field-wide">
          <span>{{ $t('whatsapp_share.message_template') }}</span>
          <textarea v-model="settings.message_template" rows="3" />
          <small>{{ $t('whatsapp_share.message_template_hint') }}</small>
        </label>
      </div>
      <div class="wa-actions">
        <button class="wa-btn" :disabled="savingSettings" @click="onSaveSettings">
          {{ savingSettings ? $t('whatsapp_share.saving') : $t('whatsapp_share.save_settings') }}
        </button>
        <span v-if="settingsSaved" class="wa-saved">{{ $t('whatsapp_share.settings_saved') }}</span>
      </div>
    </section>

    <section class="wa-card">
      <div class="wa-toolbar">
        <h2 class="wa-card-title">{{ $t('whatsapp_share.invoices') }}</h2>
        <div class="wa-filters">
          <input
            v-model="search"
            type="search"
            :placeholder="$t('whatsapp_share.search_placeholder')"
            @input="onSearchInput"
          />
          <select v-model="statusFilter" @change="loadInvoices(1)">
            <option value="unpaid">{{ $t('whatsapp_share.filter_unpaid') }}</option>
            <option value="">{{ $t('whatsapp_share.filter_all') }}</option>
            <option value="DRAFT">DRAFT</option>
            <option value="SENT">SENT</option>
            <option value="VIEWED">VIEWED</option>
            <option value="OVERDUE">OVERDUE</option>
            <option value="COMPLETED">COMPLETED</option>
          </select>
        </div>
      </div>

      <p v-if="error" class="wa-error">{{ error }}</p>
      <p v-if="loading" class="wa-muted">{{ $t('whatsapp_share.loading') }}</p>

      <table v-else class="wa-table">
        <thead>
          <tr>
            <th>{{ $t('whatsapp_share.col_invoice') }}</th>
            <th>{{ $t('whatsapp_share.col_customer') }}</th>
            <th>{{ $t('whatsapp_share.col_phone') }}</th>
            <th>{{ $t('whatsapp_share.col_amount_due') }}</th>
            <th>{{ $t('whatsapp_share.col_due_date') }}</th>
            <th>{{ $t('whatsapp_share.col_status') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="invoice in visibleInvoices" :key="invoice.id">
            <td>{{ invoice.invoice_number }}</td>
            <td>{{ invoice.customer?.name }}</td>
            <td>{{ invoice.customer?.phone || '—' }}</td>
            <td>{{ formatAmount(invoice) }}</td>
            <td>{{ invoice.formatted_due_date }}</td>
            <td>{{ invoice.status }}</td>
            <td class="wa-row-action">
              <button
                class="wa-btn wa-btn-green"
                :disabled="!normalizePhone(invoice.customer?.phone, settings.default_country_code)"
                :title="normalizePhone(invoice.customer?.phone, settings.default_country_code) ? '' : $t('whatsapp_share.no_valid_phone')"
                @click="onSend(invoice)"
              >
                {{ $t('whatsapp_share.send_on_whatsapp') }}
              </button>
            </td>
          </tr>
          <tr v-if="visibleInvoices.length === 0">
            <td colspan="7" class="wa-muted">{{ $t('whatsapp_share.no_invoices') }}</td>
          </tr>
        </tbody>
      </table>

      <div class="wa-pagination">
        <button class="wa-btn wa-btn-light" :disabled="page <= 1 || loading" @click="loadInvoices(page - 1)">
          {{ $t('whatsapp_share.previous') }}
        </button>
        <span>{{ $t('whatsapp_share.page') }} {{ page }} / {{ lastPage }}</span>
        <button class="wa-btn wa-btn-light" :disabled="page >= lastPage || loading" @click="loadInvoices(page + 1)">
          {{ $t('whatsapp_share.next') }}
        </button>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import http from '@/scripts/http'
import {
  fetchWhatsappSettings,
  formatAmount,
  normalizePhone,
  saveWhatsappSettings,
  sendInvoiceViaWhatsapp,
} from '../whatsapp'

const settings = reactive({ default_country_code: '91', message_template: '' })
const invoices = ref([])
const page = ref(1)
const lastPage = ref(1)
const loading = ref(false)
const savingSettings = ref(false)
const settingsSaved = ref(false)
const error = ref('')
const search = ref('')
const statusFilter = ref('unpaid')

let searchTimer = null

const visibleInvoices = computed(() => {
  if (statusFilter.value !== 'unpaid') {
    return invoices.value
  }
  return invoices.value.filter((invoice) => Number(invoice.due_amount) > 0 && invoice.status !== 'COMPLETED')
})

async function loadInvoices(targetPage = 1) {
  loading.value = true
  error.value = ''
  try {
    const params = { page: targetPage, limit: 15 }
    if (search.value) {
      params.invoice_number = search.value
    }
    if (statusFilter.value && statusFilter.value !== 'unpaid') {
      params.status = statusFilter.value
    }
    const response = await http.get('/api/v1/invoices', { params })
    invoices.value = response.data.data || []
    page.value = response.data.meta?.current_page || targetPage
    lastPage.value = response.data.meta?.last_page || 1
  } catch (e) {
    error.value = 'Could not load invoices. Please try again.'
  } finally {
    loading.value = false
  }
}

function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadInvoices(1), 350)
}

async function onSaveSettings() {
  savingSettings.value = true
  settingsSaved.value = false
  error.value = ''
  try {
    await saveWhatsappSettings({
      default_country_code: settings.default_country_code,
      message_template: settings.message_template,
    })
    settingsSaved.value = true
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save settings. Check the country code and template.'
  } finally {
    savingSettings.value = false
  }
}

async function onSend(invoice) {
  error.value = ''
  try {
    await sendInvoiceViaWhatsapp(invoice)
  } catch (e) {
    error.value = e.message
  }
}

onMounted(async () => {
  const loaded = await fetchWhatsappSettings()
  settings.default_country_code = loaded.default_country_code
  settings.message_template = loaded.message_template
  await loadInvoices(1)
})
</script>

<style>
.wa-page { max-width: 1100px; margin: 0 auto; padding: 32px 24px 64px; font-family: inherit; color: #1f2937; }
.wa-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.wa-title { font-size: 24px; font-weight: 700; margin: 0; }
.wa-subtitle { color: #6b7280; margin: 6px 0 0; }
.wa-back { color: #4f46e5; text-decoration: none; font-size: 14px; }
.wa-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 20px; }
.wa-card-title { font-size: 16px; font-weight: 600; margin: 0 0 12px; }
.wa-settings-grid { display: grid; grid-template-columns: 220px 1fr; gap: 16px; }
.wa-field { display: flex; flex-direction: column; gap: 6px; font-size: 14px; }
.wa-field input, .wa-field textarea, .wa-filters input, .wa-filters select {
  border: 1px solid #d1d5db; border-radius: 8px; padding: 8px 10px; font-size: 14px; background: #fff;
}
.wa-field small { color: #6b7280; }
.wa-actions { display: flex; align-items: center; gap: 12px; margin-top: 16px; }
.wa-saved { color: #059669; font-size: 14px; }
.wa-btn { background: #4f46e5; color: #fff; border: 0; border-radius: 8px; padding: 8px 14px; font-size: 14px; cursor: pointer; }
.wa-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.wa-btn-green { background: #16a34a; }
.wa-btn-light { background: #eef2ff; color: #3730a3; }
.wa-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; }
.wa-filters { display: flex; gap: 10px; }
.wa-table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 14px; }
.wa-table th, .wa-table td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #f3f4f6; }
.wa-row-action { text-align: right; }
.wa-pagination { display: flex; align-items: center; gap: 12px; margin-top: 16px; font-size: 14px; }
.wa-error { color: #dc2626; font-size: 14px; }
.wa-muted { color: #6b7280; font-size: 14px; }
@media (max-width: 720px) { .wa-settings-grid { grid-template-columns: 1fr; } }
</style>
