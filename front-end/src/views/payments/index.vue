<template>
  <div>
    <v-card>
      <v-card-title>
      <v-icon color="primary">
        mdi-credit-card-outline
      </v-icon>
        <span class="pl-2">Payments</span>
        <v-spacer></v-spacer>
        <v-chip color="primary" text-color="white" class="mr-2">
          Total: {{ totalPaid }}
        </v-chip>
      </v-card-title>
      <v-data-table
        item-key="id"
        :loading="isLoading"
        loading-text="Loading... Please wait"
        :headers="headers"
        :items="payments"
        :search="search"
      >
        <template v-slot:top>
          <v-text-field
            v-model="search"
            label="Search"
            class="mx-4"
          ></v-text-field>
        </template>

        <template v-slot:item.payer_type="{ item }">
          <v-icon class="mr-2">
            {{ item.payer_type == 'school' ? 'mdi-school' : 'mdi-account' }}
          </v-icon>
          {{ item.payer.name }}
        </template>

        <template v-slot:item.payment_date="{ item }">
          {{ item.payment_date | moment("LL") }}
        </template>
      </v-data-table>
    </v-card>
  </div>
</template>

<script>
import auth from '@/services/AuthService'
export default {
  components: {},
  data() {
    return {
      payments: [],
      totalPaid: 0,
      isLoading: false,
      search: "",
      headers: [
        { text: "ID", value: "id", align: "start", filterable: false },
        { text: "Plan", value: "plan_name" },
        { text: "Paid price", value: "price" },
        { text: "Date", value: "payment_date" },
        { text: "Payer", value: "payer_type" },
        // { text: "Actions", value: "actions", sortable: false },
      ],
    };
  },
  mounted() {
    this.loadPayments();
  },
  methods: {
    loadPayments() {
      this.isLoading = true;
      this.payments = [];
      axios
        .get(`/charges/all`)
        .then((response) => {
          this.payments = response.data.charges;
          this.totalPaid = response.data.totalPaid;
        })
        .catch((error) => {
          this.$notify({
            title: "Error",
            text: "Error while retrieving payments",
            type: "error",
          });
          console.log(error);
          auth.checkError(error.response.data.message, this.$router, this.$swal);
        })
        .then(() => {
          this.isLoading = false;
        });
    },
    viewUpcomingPayment(payment)
    {
      this.$router.push({
        name: "view-upcoming-payment",
        params: {
          user_id: payment.id,
        },
      });
    },
    redeemPayment(index) {
      this.$swal({
        title: "Redeem Payment",
        html: "Are you sure you want to redeem " + this.payments[index].total_amount + " for " + this.payments[index].user_name + "? <br/><br/>" +
        this.showRedemptionDetails(index),
        icon: "warning",
        footer: this.showRedemptionFooter(index),
        showCancelButton: true,
        confirmButtonText: "Yes, redeem it!",
      }).then((result) => {
        if (result.isConfirmed) {
          this.redeemPaymentServer(this.payments[index].id, index);
        }
      });
    },

    showRedemptionFooter(index)
    {
      let redemption_preference = this.payments[index].redemption_preference;
      if(redemption_preference==2)
      {
        return '<h2>Bank Account</h2>';
      }
      else if(redemption_preference==3)
      {
        return '<h2>PayPal</h2>';
      }
      else if(redemption_preference==4)
      {
        return '<h2>Mobile Money</h2>';
      }
      else
      {
        return '<h2>Cash</h2>';
      }
    },
    showRedemptionDetails(index)
    {
      let redemption_preference = this.payments[index].redemption_preference;
      console.log(redemption_preference);
      if(redemption_preference==2)
      {
        return '<ul><li><b>Bank Name:</b> ' + this.payments[index].redemption_details.bank_name + '</li>' +
        '<li><b>Account Number:</b> ' + this.payments[index].redemption_details.account_number + '</li>' +
        '<li><b>Beneficiary Name:</b> ' + this.payments[index].redemption_details.beneficiary_name + '</li>' +
        '<li><b>Beneficiary Address:</b> ' + this.payments[index].redemption_details.beneficiary_address + '</li>' +
        (this.payments[index].redemption_details.iban != null ? '<li><b>IBAN:</b> ' + this.payments[index].redemption_details.iban + '</li>' : '') +
        (this.payments[index].redemption_details.swift != null ? '<li><b>Swift:</b> ' + this.payments[index].redemption_details.swift + '</li>' : '') +
        (this.payments[index].redemption_details.routing_number != null ? '<li><b>Routing Number:</b> ' + this.payments[index].redemption_details.routing_number + '</li>' : '') +
        (this.payments[index].redemption_details.bic != null ? '<li><b>Bank Identification Code:</b> ' + this.payments[index].redemption_details.bic + '</li>' : '') +
        '</ul>';
      }
      else if(redemption_preference==3)
      {
        return "<b>PayPal:</b> " + this.payments[index].redemption_details.email;
      }
      else if(redemption_preference==4)
      {
        return '<ul><li><b>Phone Number:</b> ' + this.payments[index].redemption_details.phone_number + '</li>' +
        '<li><b>Network:</b> ' + this.payments[index].redemption_details.network + '</li>' +
        '<li><b>Name:</b> ' + this.payments[index].redemption_details.name + '</li>' +
        '</ul>';
      }
      else
      {
        return "";
      }
    },

    redeemPaymentServer(user_id, index) {
      this.isLoading = true;
      axios
        .post(`/users/redeem`, {
          user_id: user_id,
        })
        .then((response) => {
          this.$notify({
            title: "Success",
            text: "Payment redeemed",
            type: "success",
          });
          this.payments.splice(index, 1);
        })
        .catch((error) => {
          this.$notify({
            title: "Error",
            text: "Error while redeeming payment",
            type: "error",
          });
          console.log(error);
          this.$swal("Error", error.response.data.message, "error");
        })
        .then(() => {
          this.isLoading = false;
        });
    },
  },
};
</script>

<style lang="scss">
.theme--light.v-list-item:not(.v-list-item--active):not(.v-list-item--disabled):hover{
  cursor: pointer;
  background: rgba($primary-shade--light, 0.15) !important;
}
</style>
