{if $description}
    {$description nofilter}
{/if}

<div id="payment-modal-{$id_payment}"></div> 

<script src="https://s3-eu-west-1.amazonaws.com/pledg-assets/ecard-plugin/master/plugin.min.js"></script>
<script type="text/javascript">

var button = document.querySelector("#payment-option-{$id_payment}-container")

new Pledg(button, {
    containerElement: document.querySelector("#payment-modal-{$id_payment}"),
    showCloseButton: false,
    lang: '{$lang_iso}',
    merchantUid: "{$merchantUid}",
    amountCents: {$amountCents},
    email: '{$email}',
    title: "{$title}",
    reference: "{$reference}",
    firstName: "{$firstName}",
    lastName: "{$lastName}",
    address: {
        street: "{$street}",
        city: "{$city}",
        zipcode: "{$zipcode}",
        stateProvince: "{$stateProvince}",
        country: "{$country}"
    },
    onOpen: function() {
        setTimeout(function(){
            document.querySelector('#payment-confirmation').style.display = "none";
        }, 500);
    },
    onSuccess: function (result) {
    	document.querySelector("input[name='token']").value = result.transaction.id;
        document.querySelector("#pay-with-payment-option-{$id_payment}-form #payment-form").submit()
    },
    onError: function (error) {

    },
});
</script>