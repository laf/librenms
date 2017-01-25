<?php

echo '
<form class="form-horizontal">
    <div class="form-group">
        <label for="icmp" class="col-sm-4 control-label">Disable ICMP Test?</label>
        <div class="col-sm-3">
            '.dynamic_override_config('checkbox', 'override_icmp_disable', $device).'
        </div>
    </div>
    <div class="form-group">
        <label for="oxidized" class="col-sm-4 control-label">Exclude from Oxidized?</label>
        <div class="col-sm-3">
            '.dynamic_override_config('checkbox', 'override_Oxidized_disable', $device).'
        </div>
    </div>
    <div class="form-group">
        <label for="unixagent" class="col-sm-4 control-label">Unix agent port</label>
        <div class="col-sm-3">
            '.dynamic_override_config('text', 'override_Unixagent_port', $device).'
        </div>
    </div>
    <div class="form-group">
        <label for="unixagent" class="col-sm-4 control-label">Enable RRD Tune for all ports?</label>
        <div class="col-sm-3">
            '.dynamic_override_config('checkbox', 'override_rrdtool_tune', $device).'
        </div>
    </div>
    <div class="form-group">
        <label for="unifiuser" class="col-sm-4 control-label">Unifi username</label>
        <div class="col-sm-3">
            '.dynamic_override_config('text', 'override_Unifi_user', $device).'
        </div>
    </div>
    <div class="form-group">
        <label for="unifipass" class="col-sm-4 control-label">Unifi password</label>
        <div class="col-sm-3">
            '.dynamic_override_config('password', 'override_Unifi_pass', $device).'
        </div>
    </div>
    <div class="form-group">
        <label for="unifiurl" class="col-sm-4 control-label">Unifi URL</label>
        <div class="col-sm-3">
            '.dynamic_override_config('text', 'override_Unifi_url', $device).'
        </div>
    </div>
</form>
';
