<script setup lang="ts">
import { buttonVariants } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

interface PaginationLink {
  url: string | null
  label: string
  active: boolean
}

interface Props {
  links: PaginationLink[]
  lastPage: number
}

const props = defineProps<Props>()

interface DisplayLink {
  url: string | null
  label: string
  active: boolean
  ariaLabel: string
}

// Laravel's paginator labels are HTML (e.g. "&laquo; Previous"). Decode them to
// plain text so we can render real, accessible links instead of injecting raw
// markup into the DOM.
const items = computed<DisplayLink[]>(() =>
  props.links.map((link) => {
    const label = link.label
      .replace(/&laquo;/g, '«')
      .replace(/&raquo;/g, '»')
      .replace(/<[^>]*>/g, '')
      .trim()
    const isPrevious = label.includes('«') || /previous/i.test(label)
    const isNext = label.includes('»') || /next/i.test(label)
    const ariaLabel = isPrevious
      ? 'Go to previous page'
      : isNext
        ? 'Go to next page'
        : `Go to page ${label}`

    return { url: link.url, label, active: link.active, ariaLabel }
  }),
)
</script>

<template>
  <nav
    v-if="lastPage > 1"
    aria-label="Pagination"
    class="flex justify-center gap-1"
  >
    <template v-for="(item, index) in items" :key="index">
      <Link
        v-if="item.url"
        :href="item.url"
        :aria-label="item.ariaLabel"
        :aria-current="item.active ? 'page' : undefined"
        :class="cn(buttonVariants({ variant: item.active ? 'default' : 'outline', size: 'sm' }))"
      >
        {{ item.label }}
      </Link>
      <span
        v-else
        aria-hidden="true"
        :class="cn(buttonVariants({ variant: 'outline', size: 'sm' }), 'pointer-events-none opacity-50')"
      >
        {{ item.label }}
      </span>
    </template>
  </nav>
</template>
