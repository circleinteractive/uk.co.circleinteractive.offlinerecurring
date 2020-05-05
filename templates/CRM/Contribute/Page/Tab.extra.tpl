{if $action eq 16 AND call_user_func(array('CRM_Core_Permission', 'check'), 'add offline recurring payments')}
  <div class="offline-recurring-addlink">
    <a accesskey="N" class="button offline-recurring-addlink" href='{crmURL p="civicrm/offlinerecurring/add" q="action=add&cid=`$contactId`&reset=1"}'>
      <span><div class="crm-i fa-plus-circle"></div>
        {ts}Record Offline Recurring Contribution{/ts}
      </span>
    </a>
  </div>
  {literal}
    <script type="text/javascript">
      CRM.$(function($) {
        $('div.contact-summary-contribute-tab div.action-link .button offline-recurring-addlink').remove();
        $('div.contact-summary-contribute-tab div.action-link').append($('div.offline-recurring-addlink a'));
      });
    </script>
  {/literal}
{/if}
