<?php

$bgpPeers = \SnmpQuery::hideMib()->walk("CUMULUS-BGPVRF-MIB::bgpPeerTable")->mapTable(
    function ($data, $vrfId, $peerIdType, $ifFace) {
        $data['vrfId' ] = $vrfId;
        $data['peerIdType'] = $peerIdType;
        $data['ifIndex'] = explode('.', $ifFace)[4];
        return $data;
});

$vrfs = DeviceCache::getPrimary()->vrfs()->select('vrf_id', 'vrf_oid')->get();

foreach ($bgpPeers as $bgpPeer) {
    $bgpLocalAs = \SnmpQuery::hideMib()->get("CUMULUS-BGPVRF-MIB::bgpLocalAs.{$bgpPeer['vrfId']}")->value();
    $astext = \LibreNMS\Util\AutonomousSystem::get($bgpPeer['bgpPeerRemoteAs'])->name();
    echo "AS$bgpLocalAs ";

    $vrf = $vrfs->where('vrf_oid', $bgpPeer['vrfId'])->first();
    $vrfId = $vrf->vrf_id;

    if (! DeviceCache::getPrimary()->bgppeers()->where('bgpPeerRemoteAddr', $bgpPeer['bgpPeerRemoteAddr'])->where('vrf_id', $vrfId)->exists()) {
        $peers = [
            'vrf_id' => $vrfId,
            'bgpPeerIdentifier' => $bgpPeer['bgpPeerIdentifier'],
            'bgpPeerRemoteAs' => $bgpPeer['bgpPeerRemoteAs'],
            'bgpPeerState' => $bgpPeer['bgpPeerState'],
            'bgpPeerAdminStatus' => $bgpPeer['bgpPeerAdminStatus'],
            'bgpLocalAddr' => $bgpPeer['bgpPeerLocalAddr'],
            'bgpPeerRemoteAddr' => $bgpPeer['bgpPeerRemoteAddr'],
            'bgpPeerInUpdates' => $bgpPeer['bgpPeerInUpdates'],
            'bgpPeerOutUpdates' => $bgpPeer['bgpPeerOutUpdates'],
            'bgpPeerInTotalMessages' => $bgpPeer['bgpPeerInTotalMessages'],
            'bgpPeerOutTotalMessages' => $bgpPeer['bgpPeerOutTotalMessages'],
            'bgpPeerFsmEstablishedTime' => $bgpPeer['bgpPeerFsmEstablishedTime'],
            'bgpPeerInUpdateElapsedTime' => $bgpPeer['bgpPeerInUpdateElapsedTime'],
            'bgpPeerIface' => $bgpPeer['ifIndex'],
            'bgpPeerDescr' => $bgpPeer['bgpPeerDesc'],
            'astext' => $astext,
        ];

        DeviceCache::getPrimary()->bgppeers()->create($peers);

        if (Config::get('autodiscovery.bgp')) {
            $name = gethostbyaddr($bgpPeer['bgpPeerRemoteAddr']);
            discover_new_device($name, $device, 'BGP');
        }
        echo '+';
    } else {
        $peers = [
            'bgpPeerRemoteAs' => $bgpPeer['bgpPeerRemoteAs'],
            'astext' => $astext,
        ];
        DeviceCache::getPrimary()->bgppeers()->update(
            [
                'bgpPeerIdentifier' => $bgpPeer['bgpPeerIdentifier'],
                'vrf_id' => $vrfId,
            ],
            $peers
        );
        echo '.';
    }
}
