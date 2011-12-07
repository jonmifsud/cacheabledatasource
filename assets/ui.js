jQuery(function($){
    var checkedAttr  = !cacheEnabled ? '' : ' checked="checked" ';
    var disabledAttr = !cacheEnabled ? ' disabled="disabled" ' : '';
    // Add a cache-button to the datasource screen:
    $("#blueprints-datasources fieldset.settings:first").append('<label><input name="dbdatasourcecache[cache]" type="checkbox" ' + checkedAttr + ' />' +
        ' Cache this datasource for <input type="text"' +
        ' name="dbdatasourcecache[time]" size="5" value="' + cacheMinutes + '" ' + disabledAttr + ' " /> minutes.');
    $("input[name='dbdatasourcecache[cache]']").change(function(){
        if($(this).attr("checked"))
        {
            $("input[name='dbdatasourcecache[time]']").removeAttr('disabled');
        } else {
            $("input[name='dbdatasourcecache[time]']").attr('disabled', 'disabled');
        }
    });
});