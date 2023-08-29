[{$smarty.block.parent}]

[{if $oView->getPaymentError() == -50}]
    <div class="[{if $oViewConf->getActiveTheme() == 'flow'}]alert alert-danger[{else}]status error[{/if}]">[{$oView->getPaymentErrorText()}]</div>
[{/if}]
