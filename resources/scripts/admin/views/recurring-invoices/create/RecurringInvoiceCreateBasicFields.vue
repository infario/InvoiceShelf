<template>
  <div class="col-span-5 pr-0">
    <BaseCustomerSelectPopup
      v-model="recurringInvoiceStore.newRecurringInvoice.customer"
      :valid="v.customer_id"
      :content-loading="isLoading"
      type="recurring-invoice"
    />

    <div class="flex mt-7">
      <div class="relative w-20 mt-8">
        <BaseSwitch
          v-model="recurringInvoiceStore.newRecurringInvoice.send_automatically"
          class="absolute -top-4"
        />
      </div>

      <div class="ml-2">
        <p class="p-0 mb-1 leading-snug text-left text-black">
          {{ $t('recurring_invoices.send_automatically') }}
        </p>
        <p
          class="p-0 m-0 text-xs leading-tight text-left text-gray-500"
          style="max-width: 480px"
        >
          {{ $t('recurring_invoices.send_automatically_desc') }}
        </p>
      </div>
    </div>
  </div>

  <div
    class="
      grid grid-cols-1
      col-span-7
      gap-4
      mt-8
      lg:gap-6 lg:mt-0 lg:grid-cols-2
    "
  >
    <BaseInputGroup
      :label="$t('recurring_invoices.starts_at')"
      :content-loading="isLoading"
      required
      :error="v.starts_at.$error && v.starts_at.$errors[0].$message"
    >
      <BaseDatePicker
        v-model="recurringInvoiceStore.newRecurringInvoice.starts_at"
        :content-loading="isLoading"
        :calendar-button="true"
        calendar-button-icon="calendar"
        :invalid="v.starts_at.$error"
        @change="onStartsAtChange"
      />
    </BaseInputGroup>

    <BaseInputGroup
      :label="$t('recurring_invoices.next_invoice_date')"
      :content-loading="isLoading"
      required
    >
      <BaseDatePicker
        v-model="recurringInvoiceStore.newRecurringInvoice.next_invoice_at"
        :content-loading="isLoading"
        :calendar-button="true"
        :disabled="true"
        :loading="isLoadingNextDate"
        calendar-button-icon="calendar"
      />
    </BaseInputGroup>

    <BaseInputGroup
      :label="$t('recurring_invoices.limit_by')"
      :content-loading="isLoading"
      class="lg:mt-0"
      required
      :error="v.limit_by.$error && v.limit_by.$errors[0].$message"
    >
      <BaseMultiselect
        v-model="recurringInvoiceStore.newRecurringInvoice.limit_by"
        :content-loading="isLoading"
        :options="limits"
        label="label"
        :invalid="v.limit_by.$error"
        value-prop="value"
      />
    </BaseInputGroup>

    <BaseInputGroup
      v-if="hasLimitBy('DATE')"
      :label="$t('recurring_invoices.limit_date')"
      :content-loading="isLoading"
      :required="hasLimitBy('DATE')"
      :error="v.limit_date.$error && v.limit_date.$errors[0].$message"
    >
      <BaseDatePicker
        v-model="recurringInvoiceStore.newRecurringInvoice.limit_date"
        :content-loading="isLoading"
        :invalid="v.limit_date.$error"
        calendar-button-icon="calendar"
      />
    </BaseInputGroup>

    <BaseInputGroup
      v-if="hasLimitBy('COUNT')"
      :label="$t('recurring_invoices.count')"
      :content-loading="isLoading"
      :required="hasLimitBy('COUNT')"
      :error="v.limit_count.$error && v.limit_count.$errors[0].$message"
    >
      <BaseInput
        v-model="recurringInvoiceStore.newRecurringInvoice.limit_count"
        :content-loading="isLoading"
        :invalid="v.limit_count.$error"
        type="number"
      />
    </BaseInputGroup>

    <BaseInputGroup
      :label="$t('recurring_invoices.status')"
      required
      :content-loading="isLoading"
      :error="v.status.$error && v.status.$errors[0].$message"
    >
      <BaseMultiselect
        v-model="recurringInvoiceStore.newRecurringInvoice.status"
        :options="getStatusOptions"
        :content-loading="isLoading"
        :invalid="v.status.$error"
        :placeholder="$t('recurring_invoices.select_a_status')"
        value-prop="value"
        label="key"
      />
    </BaseInputGroup>

    <BaseInputGroup
      :label="$t('recurring_invoices.frequency.select_frequency')"
      required
      :content-loading="isLoading"
      :error="
        v.selectedFrequency.$error && v.selectedFrequency.$errors[0].$message
      "
    >
      <BaseMultiselect
        v-model="recurringInvoiceStore.newRecurringInvoice.selectedFrequency"
        :content-loading="isLoading"
        :options="recurringInvoiceStore.frequencies"
        label="label"
        :invalid="v.selectedFrequency.$error"
        object
        @change="onFrequencyPresetChange"
      />
    </BaseInputGroup>

    <template v-if="showYearlyOnStart">
      <p class="col-span-full m-0 text-xs text-gray-500">
        {{ $t('recurring_invoices.frequency.yearly_on_start_hint') }}
      </p>
    </template>

    <template v-if="showYearlyOnDate || showMonthlyOnDay">
      <BaseInputGroup
        :label="$t('recurring_invoices.frequency.day_of_month')"
        :content-loading="isLoading"
        required
      >
        <BaseMultiselect
          v-model="recurringInvoiceStore.frequencyBuilder.day"
          :content-loading="isLoading"
          :options="dayOptions"
          label="label"
          value-prop="value"
          @change="syncBuilderFrequency"
        />
      </BaseInputGroup>

      <BaseInputGroup
        v-if="showYearlyOnDate"
        :label="$t('recurring_invoices.frequency.month_of_year')"
        :content-loading="isLoading"
        required
      >
        <BaseMultiselect
          v-model="recurringInvoiceStore.frequencyBuilder.month"
          :content-loading="isLoading"
          :options="monthOptions"
          label="label"
          value-prop="value"
          @change="syncBuilderFrequency"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('recurring_invoices.frequency.time_hour')"
        :content-loading="isLoading"
      >
        <BaseMultiselect
          v-model="recurringInvoiceStore.frequencyBuilder.hour"
          :content-loading="isLoading"
          :options="hourOptions"
          label="label"
          value-prop="value"
          @change="syncBuilderFrequency"
        />
      </BaseInputGroup>

      <p class="col-span-full m-0 text-xs text-gray-500">
        {{
          showYearlyOnDate
            ? $t('recurring_invoices.frequency.yearly_on_date_hint')
            : $t('recurring_invoices.frequency.monthly_on_day_hint')
        }}
      </p>
    </template>

    <BaseInputGroup
      v-if="isCustomFrequency"
      :label="$t('recurring_invoices.frequency.title')"
      :content-loading="isLoading"
      required
      :error="v.frequency.$error && v.frequency.$errors[0].$message"
      class="col-span-full"
    >
      <BaseInput
        v-model="recurringInvoiceStore.newRecurringInvoice.frequency"
        :content-loading="isLoading"
        :invalid="v.frequency.$error"
        :loading="isLoadingNextDate"
        placeholder="0 0 1 11 *"
        @update:model-value="debounceNextDate"
      />
      <p class="mt-1 mb-0 text-xs text-gray-500">
        {{ $t('recurring_invoices.frequency.custom_cron_hint') }}
      </p>
    </BaseInputGroup>

    <ExchangeRateConverter
      :store="recurringInvoiceStore"
      store-prop="newRecurringInvoice"
      :v="v"
      :is-loading="isLoading"
      :is-edit="isEdit"
      :customer-currency="recurringInvoiceStore.newRecurringInvoice.currency_id"
    />
  </div>
</template>

<script setup>
import { useGlobalStore } from '@/scripts/admin/stores/global'
import { useDebounceFn } from '@vueuse/core'
import { useRecurringInvoiceStore } from '@/scripts/admin/stores/recurring-invoice'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'

import ExchangeRateConverter from '@/scripts/admin/components/estimate-invoice-common/ExchangeRateConverter.vue'
import {
  FREQUENCY_SENTINELS,
  buildMonthlyCron,
  buildYearlyCron,
  toDateParts,
  yearlyCronFromStartsAt,
} from '@/scripts/admin/helpers/recurring-frequency'

const props = defineProps({
  v: {
    type: Object,
    default: null,
  },
  isLoading: {
    type: Boolean,
    default: false,
  },
  isEdit: {
    type: Boolean,
    default: false,
  },
})

const route = useRoute()
const recurringInvoiceStore = useRecurringInvoiceStore()
const globalStore = useGlobalStore()
const { t } = useI18n()

const isLoadingNextDate = ref(false)

const limits = reactive([
  { label: t('recurring_invoices.limit.none'), value: 'NONE' },
  { label: t('recurring_invoices.limit.date'), value: 'DATE' },
  { label: t('recurring_invoices.limit.count'), value: 'COUNT' },
])

const dayOptions = Array.from({ length: 31 }, (_, i) => ({
  label: String(i + 1),
  value: i + 1,
}))

const monthOptions = [
  { label: '1 — January', value: 1 },
  { label: '2 — February', value: 2 },
  { label: '3 — March', value: 3 },
  { label: '4 — April', value: 4 },
  { label: '5 — May', value: 5 },
  { label: '6 — June', value: 6 },
  { label: '7 — July', value: 7 },
  { label: '8 — August', value: 8 },
  { label: '9 — September', value: 9 },
  { label: '10 — October', value: 10 },
  { label: '11 — November', value: 11 },
  { label: '12 — December', value: 12 },
]

const hourOptions = Array.from({ length: 24 }, (_, i) => ({
  label: String(i).padStart(2, '0') + ':00',
  value: i,
}))

const selectedFrequencyValue = computed(
  () => recurringInvoiceStore.newRecurringInvoice.selectedFrequency?.value,
)

const isCustomFrequency = computed(
  () => selectedFrequencyValue.value === FREQUENCY_SENTINELS.CUSTOM,
)

const showYearlyOnStart = computed(
  () => selectedFrequencyValue.value === FREQUENCY_SENTINELS.YEARLY_ON_START,
)

const showYearlyOnDate = computed(
  () => selectedFrequencyValue.value === FREQUENCY_SENTINELS.YEARLY_ON_DATE,
)

const showMonthlyOnDay = computed(
  () => selectedFrequencyValue.value === FREQUENCY_SENTINELS.MONTHLY_ON_DAY,
)

const getStatusOptions = computed(() => {
  if (props.isEdit) {
    return globalStore.config.recurring_invoice_status.update_status.map((item) => {
      return Object.assign({}, item, {
        key: t(item.key),
      })
    })
  }
  return globalStore.config.recurring_invoice_status.create_status.map((item) => {
    return Object.assign({}, item, {
      key: t(item.key),
    })
  })
})

watch(
  () => recurringInvoiceStore.newRecurringInvoice.selectedFrequency,
  (newValue) => {
    if (recurringInvoiceStore.isFetchingInitialSettings) {
      return
    }

    applyFrequencySelection(newValue?.value)
  },
)

onMounted(() => {
  // on create
  if (!route.params.id) {
    getNextInvoiceDate()
  }
})

function hasLimitBy(LimitBy) {
  return recurringInvoiceStore.newRecurringInvoice.limit_by === LimitBy
}

function onFrequencyPresetChange() {
  // BaseMultiselect @change fires after v-model update; the watch handles sync.
  getNextInvoiceDate()
}

function onStartsAtChange() {
  if (showYearlyOnStart.value) {
    applyYearlyFromStartsAt()
  }
  getNextInvoiceDate()
}

function applyFrequencySelection(value) {
  if (!value) {
    recurringInvoiceStore.newRecurringInvoice.frequency = null
    return
  }

  if (value === FREQUENCY_SENTINELS.CUSTOM) {
    // Keep existing cron when switching into Advanced while editing; clear only
    // when the field is empty so the user can type a new expression.
    if (!recurringInvoiceStore.newRecurringInvoice.frequency) {
      recurringInvoiceStore.newRecurringInvoice.frequency = null
    }
    return
  }

  if (value === FREQUENCY_SENTINELS.YEARLY_ON_START) {
    seedBuilderFromStartsAt()
    applyYearlyFromStartsAt()
    return
  }

  if (value === FREQUENCY_SENTINELS.YEARLY_ON_DATE) {
    seedBuilderFromStartsAt()
    syncBuilderFrequency()
    return
  }

  if (value === FREQUENCY_SENTINELS.MONTHLY_ON_DAY) {
    seedBuilderFromStartsAt()
    syncBuilderFrequency()
    return
  }

  // Fixed cron preset.
  recurringInvoiceStore.newRecurringInvoice.frequency = value
}

function seedBuilderFromStartsAt() {
  const parts = toDateParts(recurringInvoiceStore.newRecurringInvoice.starts_at)
  if (!parts) {
    return
  }

  recurringInvoiceStore.frequencyBuilder.day = parts.day
  recurringInvoiceStore.frequencyBuilder.month = parts.month
}

function applyYearlyFromStartsAt() {
  const cron = yearlyCronFromStartsAt(
    recurringInvoiceStore.newRecurringInvoice.starts_at,
    {
      hour: recurringInvoiceStore.frequencyBuilder.hour || 0,
      minute: recurringInvoiceStore.frequencyBuilder.minute || 0,
    },
  )

  recurringInvoiceStore.newRecurringInvoice.frequency = cron
}

function syncBuilderFrequency() {
  const value = selectedFrequencyValue.value
  const builder = recurringInvoiceStore.frequencyBuilder

  if (value === FREQUENCY_SENTINELS.YEARLY_ON_DATE) {
    recurringInvoiceStore.newRecurringInvoice.frequency = buildYearlyCron({
      day: builder.day,
      month: builder.month,
      hour: builder.hour,
      minute: builder.minute || 0,
    })
  } else if (value === FREQUENCY_SENTINELS.MONTHLY_ON_DAY) {
    recurringInvoiceStore.newRecurringInvoice.frequency = buildMonthlyCron({
      day: builder.day,
      hour: builder.hour,
      minute: builder.minute || 0,
    })
  } else if (value === FREQUENCY_SENTINELS.YEARLY_ON_START) {
    applyYearlyFromStartsAt()
  }

  debounceNextDate()
}

const debounceNextDate = useDebounceFn(() => {
  getNextInvoiceDate()
}, 500)

async function getNextInvoiceDate() {
  const val = recurringInvoiceStore.newRecurringInvoice.frequency

  if (!val || Object.values(FREQUENCY_SENTINELS).includes(val)) {
    return
  }

  isLoadingNextDate.value = true

  let data = {
    starts_at: recurringInvoiceStore.newRecurringInvoice.starts_at,
    frequency: val,
  }

  try {
    await recurringInvoiceStore.fetchRecurringInvoiceFrequencyDate(data)
  } catch (error) {
    console.error(error)
    isLoadingNextDate.value = false
  }

  isLoadingNextDate.value = false
}
</script>
