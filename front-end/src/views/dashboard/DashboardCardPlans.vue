<template>
  <v-card>
    <v-card-title class="align-start">
      <span>Best Selling Plans</span>

      <v-spacer></v-spacer>

    </v-card-title>

    <v-card-text v-if="bestPlans.length > 0">
      <v-list class="pb-0">
        <v-list-item
          v-for="(data,index) in bestPlans"
          :key="data.id"
          :class="`d-flex align-center px-0 ${index > 0 ? 'mt-4':''}`"
        >
          <v-avatar
            :color="data.color"
            size="40"
            :class="`${data.color} white--text font-weight-medium me-3`"
          >
            <span class="text-base">{{ data.plan_id }}</span>
          </v-avatar>

          <div class="d-flex align-center flex-grow-1 flex-wrap">
            <div class="me-2">
              <div class="font-weight-semibold">
                <span class="text--primary text-base me-1">{{ data.plan_name }} - {{data.plan_type == 0 ? 'Schools': 'Parents'}}</span>
              </div>
              <v-list-item-subtitle class="text-xs">
                Sold {{data.count}} {{ data.count == 1? 'time': 'times'}}
              </v-list-item-subtitle>
            </div>

            <v-spacer></v-spacer>

            <div>
              <h4 class="font-weight-semibold">
                {{ data.total }}
              </h4>
            </div>
          </div>
        </v-list-item>
      </v-list>
    </v-card-text>

    <v-card-text v-else>
      <div class="d-flex align-center justify-center flex-column">
        <v-icon size="150" color="grey lighten-2">mdi-emoticon-sad-outline</v-icon>
        <span class="text--secondary text-sm">No enough data found</span>
      </div>
    </v-card-text>
  </v-card>
</template>

<script>
import { mdiDotsVertical, mdiChevronUp, mdiChevronDown } from '@mdi/js'

export default {
  //props
  props: {
    bestPlans: {
      type: Array,
      default: () => [],
    },
  },
  setup() {
    return {
      icons: {
        mdiDotsVertical,
        mdiChevronUp,
        mdiChevronDown,
      },
    }
  },
}
</script>
