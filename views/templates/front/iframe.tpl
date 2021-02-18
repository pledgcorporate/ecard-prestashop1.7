{extends "$layout"}
{block name="content"}
  <form id="installment-form" method="POST" action="{$paramsPledg.actionUrl}">
      <input type="button" style="display:none;" id="pledgButton"/>
      <div class="payment_module pledg" id="payment_pledg">
          <div id="installment-container" class="pledgContainer"></div>
      </div>
  </form>

  <!-- This script contains the code of the plugin -->
  <script src="https://s3-eu-west-1.amazonaws.com/pledg-assets/ecard-plugin/{$paramsPledg.mode}/plugin.min.js"></script>

  <!-- This script contains the call to the plugin
          See the "Javascript" section below -->
  <script type="text/javascript">
      var isError = false;
      var messageError = '';
      var isOpen = false;

      function getPledgButton() {
          return document.querySelector("#pledgButton");
      }

      function getPledgForm() {
          return document.querySelector("#installment-form")
      }

      function getPledgContainer() {
          return document.querySelector("#installment-container")
      }

      function addHiddenInput(form, label, value) {
          var hiddenInput = document.createElement("input")
          hiddenInput.setAttribute("type", "hidden")
          hiddenInput.setAttribute("name", label)
          hiddenInput.setAttribute("value", value)
          form.appendChild(hiddenInput)
      }

      function displayError() {
          getPledgContainer().remove();
          var alertDiv = document.createElement("div");
          alertDiv.setAttribute("class", "alert alert-danger");
          alertDiv.textContent = messageError;
          document.querySelector("#payment_pledg").appendChild(alertDiv);
          setTimeout(function(){
                window.history.back();
          }, 3000);
      }

      var pledg = new Pledg(getPledgButton(), {
          containerElement:getPledgContainer(),
          paymentNotificationUrl: "{$paramsPledg.notificationUrl nofilter}",
          {if $paramsPledg.signature}
                signature: "{$paramsPledg.signature}",
          {else}
                merchantUid: "{$paramsPledg.merchantUid}",
                title: "{addslashes($paramsPledg.title)}",
                reference: "{$paramsPledg.reference}",
                amountCents: "{$paramsPledg.amountCents}",
                currency: "{$paramsPledg.currency}",
                civility: "{$paramsPledg.civility}",
                firstName: "{$paramsPledg.firstName}",
                lastName: "{$paramsPledg.lastName}",
                {if $paramsPledg.birthDate && $paramsPledg.birthDate != ''}birthDate: "{$paramsPledg.birthDate}",{/if}
                email: "{$paramsPledg.email}",
                countryCode: "{$paramsPledg.countryCode}",
                metadata: {$paramsPledg.metadata nofilter},
                address: {$paramsPledg.address nofilter},
                shippingAddress: {$paramsPledg.shippingAddress nofilter},
                phoneNumber: "{$paramsPledg.phoneNumber}",
                lang: "{$paramsPledg.lang}",
          {/if}
          showCloseButton: {if $paramsPledg.showCloseButton}true{else}false{/if},
          onSuccess: function (resultpayment) {
              var form = getPledgForm();
              addHiddenInput(form, "merchantUid", "{$paramsPledg.merchantUid}");
              addHiddenInput(form, "reference", "{$paramsPledg.reference}");
              addHiddenInput(form, "transaction", resultpayment.purchase.reference);
              var rp_string = JSON.stringify(resultpayment);
              addLog('Pledg Form Success Paid. Return value : ' . rp_string, 'success', 'PledgPayment', {$paramsPledg.id});
              form.submit();
          },
          onError: function (error) {
              isError = true;
              messageError = error.message;
              addLog('Error Pledg Payment Form : ' + error.message, 'error', 'PledgPayment', {$paramsPledg.id});
              if (isOpen) {
                  displayError();
              }
          },
          onOpen: function() {
              addLog('Open Pledg Payment Form', 'success', 'PledgPayment', {$paramsPledg.id});
              isOpen = true;
              if (isError) {
                  displayError();
              }
          }
      });

  </script>

  <script>
  function addLog(msg, typeLog, classObject = '', idObject = '') {
      var urlAjax = "{$link->getModuleLink('pledg', 'log', [], true)|escape:'html'}";
      $.ajax({
          url : urlAjax,
          method: 'POST',
          data: {
              message: msg,
              type: typeLog,
              class: classObject,
              id: idObject
          }
      });
  }
  </script>
{/block}