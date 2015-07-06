<?php
# ttlist.php mode template_file <topoid>
require_once 'tt.lib.php';

$mode = $argv[1];
$tt = new TopoTpl($argv[2]);
if (isset($argv[3])) {
  $topoid = intval($argv[3]);
  $ti = new TopoInst($tt,$topoid);
}

if ($mode=='vm') {
  foreach ($tt->hosts as $tthost) echo $tthost->hostname."\n";
}

if ($mode=='sshconfig') {
  $username = 'topo'.$topoid;
  foreach ($tt->hosts as $tthost) {
    printf("Host %s\n",$tthost->vname);
    printf("  User %s\n",$username);
    list($sshhost,$sshport) = explode(':',$tthost->sshserver);
    if ($sshhost=='') $sshhost = gethostname();
    printf("  HostName %s\n  Port %d\n",$sshhost,$sshport);
    printf("  IdentityFile vnltopo%d.pvtkey\n",$topoid);
    printf("  LogLevel quiet\n  StrictHostKeyChecking no\n  UserKnownHostsFile /dev/null\n");
  }
}

if ($mode=='connscript') {
?>
#!/bin/bash
# Virtual Network Lab, soft host connecting script
<?php printf("topoid=%d\n",$topoid); ?>
if [ "$1" = '' ]
then
<?php
  foreach ($tt->hosts as $tthost) printf("\techo \$0 %s ...\n",$tthost->vname);
?>
  exit 1
fi
if [ ! -f vnltopo$topoid.sshconfig ]; then echo 'sshconfig missing' >/dev/stderr; exit 1; fi
if [ ! -f vnltopo$topoid.pvtkey ]; then echo 'pvtkey missing' >/dev/stderr; exit 1; fi
chown `id -u`:`id -g` vnltopo$topoid.pvtkey
chmod 600 vnltopo$topoid.pvtkey
ssh -F vnltopo$topoid.sshconfig "$@"
<?php
}

if ($mode=='ip') {
  foreach ($tt->hosts as $tthost) {
    foreach ($tthost->ifs as $ttif) {
      $vip = $ti->resolve_vip($ttif->vip,TRUE);
      printf("ip_%s_%s=%s\n",$tthost->vname,$ttif->vname,$vip);
    }
  }
}

if ($mode=='rtablelist') {
  foreach ($tt->vnsrtables as $filename=>$ttvrt) echo $filename."\n";
}
if ($mode=='rtable') {
  $filename = strval($argv[4]);
  $ttvrt = $tt->vnsrtables[$filename];
  if (!$ttvrt) die("vnsrtable not found\n");
  foreach ($ttvrt->routes as $ttroute) {
    list($vip,$cidr,$mask) = $ti->resolve_vip($ttroute->dst);
    $via = $ti->resolve_vip($ttroute->via,TRUE);
    printf("%s %s %s %s\n",$vip,$via,$mask,$ttroute->oif);
  }
}
?>
