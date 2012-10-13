/* dezi-for-wordpress javascript helpers */
if (typeof 'jQuery' == 'undefined')
    throw new Exception("jQuery not loaded");

var $j = jQuery.noConflict();

function dezi4w_switch1() {
    if ($j('#deziconnect_single').is(':checked')) {
        $j('#dezi_admin_tab2').css('display', 'block');
        $j('#dezi_admin_tab2_btn').addClass('dezi_admin_on');
        $j('#dezi_admin_tab3').css('display', 'none');
        $j('#dezi_admin_tab3_btn').removeClass('dezi_admin_on');
    }
    if ($j('#deziconnect_separated').is(':checked')) {
        $j('#dezi_admin_tab2').css('display', 'none');
        $j('#dezi_admin_tab2_btn').removeClass('dezi_admin_on');
        $j('#dezi_admin_tab3').css('display', 'block');
        $j('#dezi_admin_tab3_btn').addClass('dezi_admin_on');
    }
}


function dezi4w_doLoad($type, $prev) {
    if ($prev == null) {
        $j.post("options-general.php?page=dezi-for-wordpress/dezi-for-wordpress.php", {method: "load", type: $type}, dezi4w_handleResults, "json");
    } else {
        $j.post("options-general.php?page=dezi-for-wordpress/dezi-for-wordpress.php", {method: "load", type: $type, prev: $prev}, dezi4w_handleResults, "json");
    }
}

function dezi4w_handleResults(data) {
    $j('#percentspan').text(data.percent + "%");
    if (!data.end) {
        dezi4w_doLoad(data.type, data.last);
    } else {
        $j('#percentspan').remove();
        dezi4w_enableAll();
    }
}

function dezi4w_disableAll() {
    $j("input[name^='dezi4w_content_load']").attr('disabled','disabled');
    $j('[name=dezi4w_deleteall]').attr('disabled','disabled');
    $j('[name=dezi4w_init_blogs]').attr('disabled','disabled');
    $j('[name=dezi4w_optimize]').attr('disabled','disabled');
    $j('[name=dezi4w_ping]').attr('disabled','disabled');
    $j('#settingsbutton').attr('disabled','disabled');
}

function dezi4w_enableAll() {
    $j("input[name^='dezi4w_content_load']").removeAttr('disabled');
    $j('[name=dezi4w_deleteall]').removeAttr('disabled');
    $j('[name=dezi4w_init_blogs]').removeAttr('disabled');
    $j('[name=dezi4w_optimize]').removeAttr('disabled');
    $j('[name=dezi4w_ping]').removeAttr('disabled');
    $j('#settingsbutton').removeAttr('disabled');
}

$percentspan = '<span style="font-size:1.2em;font-weight:bold;margin:20px;padding:20px" id="percentspan">0%</span>';

$j(document).ready(function() {
   dezi4w_switch1();
   $j("input[name^='dezi4w_content_load']").click(function(event){
      event.preventDefault();
      var regex = /\b[a-z]+\b/;
      var match = regex.exec(this.name);
      var post_type = match[0];
      $j(this).after($percentspan);
      dezi4w_disableAll();
      dezi4w_doLoad(post_type, null);
    });
});
