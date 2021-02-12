{if $description}
    {$description nofilter}
{/if}
<div class="payment-box">
    <input type="hidden" class="locale" value="{$pledg_locale}"/>
    <input type="hidden" class="payment_detail_trad" value="{$payment_detail_trad}"/>
    <input type="hidden" class="url_api" value="{$url_api}"/>
    <div class="payment-detail-container">
    </div>
</div>