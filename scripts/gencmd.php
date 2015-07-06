<?php
# gencmd.php template_file topoid
require_once 'tt.lib.php';

$tt = new TopoTpl($argv[1]);
$topoid = intval($argv[2]);
$ti = new TopoInst($tt,$topoid);

$toponame = 'topo'.$topoid;
$username = $toponame;
?>
#!/bin/bash
<?php
printf("# %s %s %s/%s :%d",$tt->title,$toponame,long2ip($ti->vip_base),$tt->vip_block_cidr,$ti->udpport_base);
if ($tt->udpport_step > 1) printf("-%d",$ti->udpport_base+$tt->udpport_step-1);
if ($tt->rtable_step > 0) printf(" rtable%d-%d",$ti->rtable_base,$ti->rtable_base+$tt->rtable_step-1);
echo "\n";
?>

act=$1
hostname=`hostname`
<?php printf("topoid=%d\n",$topoid); ?>
<?php printf("username=%s\n",$username); ?>
userhome=/home/$username
approot=/home/vnl/apps
topodataroot=/home/vnl/topo
vnlsvcpid=0
if [ -f $userhome/vnlsvc.pid ]; then vnlsvcpid=`cat $userhome/vnlsvc.pid`; fi

if [ "$act" = '' ]
then
  echo Usage:
  echo sudo $0 create
  echo sudo $0 start
  echo sudo $0 stop
  echo sudo $0 destroy
  echo $0 status
  echo $0 kill
  echo $0 run
  echo $0 setlossy eth0 10
  exit 1
fi

if [ $act = 'create' ]
then
  groupadd $username
  useradd -g $username -m -d $userhome -s /bin/bash $username
  mkfifo $userhome/vnlsvc.command
  echo 0 > $userhome/vnlsvc.pid
  ln -s $topodataroot/$topoid.sh $userhome/topo.sh
  mkdir $userhome/.ssh
  mv $topodataroot/$topoid.pvtkey $userhome/.ssh/id_rsa
  mv $topodataroot/$topoid.pubkey $userhome/.ssh/id_rsa.pub
  echo -n "command=\"$topodataroot/$topoid.sh sshshell\",no-X11-forwarding,no-agent-forwarding " > $userhome/.ssh/authorized_keys
  cat $userhome/.ssh/id_rsa.pub >> $userhome/.ssh/authorized_keys
  if [ -f $topodataroot/$topoid.tar ]; then rm $topodataroot/$topoid.tar; fi
  chown -R $username:$username $userhome $userhome/.ssh
  chmod 755 $userhome/.ssh
  chmod 600 $userhome/.ssh/id_rsa
  chmod 644 $userhome/.ssh/id_rsa.pub $userhome/.ssh/authorized_keys
fi

if [ $act = 'start' -o $act = 'create' ]
then
  if [ $vnlsvcpid -ne 0 ]; then kill $vnlsvcpid; fi
<?php
  foreach ($tt->ng_hosts as $tthost) {
?>
  if [ $hostname = '<?php echo $tthost->hostname; ?>' ]
  then
<?php
    foreach ($tthost->ifs as $ttif) {
      $tapname = $toponame.$ttif->vname;
      list($vip,$cidr,$mask) = $ti->resolve_vip($ttif->vip);
      printf("\t\tip tuntap add dev %s mode tap user \$username\n",$tapname);
      printf("\t\tip addr add %s/%d dev %s\n",$vip,$cidr,$tapname);
      printf("\t\tip link set %s up\n",$tapname);
    }
    foreach ($tthost->routes as $ttroute) {
      list($svip,$scidr,$smask) = $ti->resolve_vip($ttroute->src);
      list($dvip,$dcidr,$dmask) = $ti->resolve_vip($ttroute->dst);
      $via = $ti->resolve_vip($ttroute->via,TRUE);
      if ($ttroute->is_source_route) {
        $rtable = $ti->rtable_base + $ttroute->rtable;
        printf("\t\tip rule add from %s/%d table %d\n",$svip,$scidr,$rtable);
        printf("\t\tip route add %s/%d via %s table %d\n",$dvip,$dcidr,$via,$rtable);
      } else {
        printf("\t\tip route add %s/%d via %s\n",$dvip,$dcidr,$via);
      }
    }
    printf("\t\tsudo -u %s /home/vnl/topo/%s.sh run\n",$username,$topoid);
?>
  fi
<?php
  }
?>
fi

if [ $act = 'stop' -o $act = 'destroy' ]
then
  killall -u $username
  sleep 0.2
<?php
  foreach ($tt->ng_hosts as $tthost) {
?>
  if [ $hostname = '<?php echo $tthost->hostname; ?>' ]
  then
<?php
    foreach ($tthost->routes as $ttroute) {
      if ($ttroute->is_source_route) {
        list($svip,$scidr,$smask) = $ti->resolve_vip($ttroute->src);
        printf("\t\tip rule del from %s/%d\n",$svip,$scidr);
      }
    }
    foreach ($tthost->ifs as $ttif) {
      $tapname = $toponame.$ttif->vname;
      printf("\t\tip tuntap del dev %s mode tap\n",$tapname);
    }
?>
  fi
<?php
  }
?>
fi

if [ $act = 'destroy' ]
then
  userdel -f -r $username
  rm $topodataroot/$topoid.*
fi

if [ $act = 'sshshell' ]
then
  if [ "$SSH_ORIGINAL_COMMAND" = '' ]
  then
<?php
  foreach ($tt->hosts as $tthost) {
    printf("\t\tif [ \$hostname = '%s' ]; then vhost=%s; hostmode=%s; fi\n",$tthost->hostname,$tthost->vname,$tthost->mode);
  }
?>
    echo [TOPOLOGY CONTROL]
    echo vnltopo$topoid.sh $vhost status
    echo vnltopo$topoid.sh $vhost kill
    echo vnltopo$topoid.sh $vhost run
    echo vnltopo$topoid.sh $vhost setlossy ifname lossrate
    if [ $hostmode = 'native' -o $hostmode = 'gateway' ]
    then
      echo [NETWORK UTILITY]
      echo vnltopo$topoid.sh $vhost ping dst [ifname]
      echo vnltopo$topoid.sh $vhost floodping dst [ifname]
      echo vnltopo$topoid.sh $vhost traceroute dst [ifname]
      echo vnltopo$topoid.sh $vhost sendtcp dst [ifname srcport dstport]
      echo vnltopo$topoid.sh $vhost sendudp dst [ifname srcport dstport]
    fi
    exit 1
  fi
  set $SSH_ORIGINAL_COMMAND
  act=$1
fi

if [ $act = 'status' ]
then
<?php
  foreach ($tt->hosts as $tthost) {
?>
  if [ $hostname = '<?php echo $tthost->hostname; ?>' ]
  then
    echo Virtual Network Lab: host <?php echo $tthost->vname; ?>, <?php echo $tthost->mode; ?> mode
    echo [SERVICE PROCESS]
    if [ $vnlsvcpid -gt 0 ]; then ps -F $vnlsvcpid 2>/dev/null; fi
<?php
    if ($tthost->isNG()) {
      echo "\t\techo [INTERFACES]\n";
      $ifid = -1;
      foreach ($tthost->ifs as $ttif) {
        ++$ifid;
        $tapname = $toponame.$ttif->vname;
        printf("\t\tifconfig %s | grep . | sed 's/%s//' | sed 's/^\\s\\s\\s\\s\\s//'\n",$tapname,$toponame);
        printf("\t\tif [ -f \$userhome/%d.lossy ]; then echo '     lossy: '`cat \$userhome/%d.lossy`%%; fi\n",$ifid,$ifid);
      }
      echo "\t\techo [ROUTING TABLE]\n";
      printf("\t\tip route | grep %s[^0-9] | sed 's/%s//'\n",$toponame,$toponame);
      foreach ($tthost->routes as $ttroute) {
        if ($ttroute->is_source_route) {
          $rtable = $ti->rtable_base + intval($ttroute->rtable);
          printf("\t\tip route show table %d | sed 's/%s//'\n",$rtable,$toponame);
        }
      }
    } else {
      echo "\t\techo [INTERFACES]\n";
      $ifid = -1;
      foreach ($tthost->ifs as $ttif) {
        ++$ifid;
        printf("\t\techo %s\n",$ttif->vname);
        printf("\t\tif [ -f \$userhome/%d.lossy ]; then echo -n '     lossy: '; echo `cat \$userhome/%d.lossy`%%; fi\n",$ifid,$ifid);
      }
      echo "\t\techo [ROUTING TABLE]\n\t\techo NOT APPLICABLE\n";
    }
?>
  fi
<?php
  }
?>
fi

if [ $act = 'kill' ]
then
  if [ $vnlsvcpid -ne 0 ]; then kill $vnlsvcpid; fi
fi

if [ $act = 'run' ]
then
  if [ $vnlsvcpid -ne 0 ]; then kill $vnlsvcpid; fi
  rm $userhome/*.lossy &>/dev/null
<?php
  foreach ($tt->hosts as $tthost) {
?>
  if [ $hostname = '<?php echo $tthost->hostname; ?>' ]
  then
<?php
    $cmd = '$approot/vnlsvc';
    foreach ($tthost->ifs as $ttif) {
      $tapname = $toponame.$ttif->vname;
      list($vip,$cidr,$mask) = $ti->resolve_vip($ttif->vip);
      $cmd .= sprintf(' -i %s/%s/%s/%s#%s/%s:%d/%s:%d/',
        $ttif->vname,
        $tapname,
        $ti->random_mac(),
        $vip,$mask,
        $ttif->tunnel_local,$ti->udpport_base+$ttif->udpport,
        $ttif->tunnel_remote,$ti->udpport_base+$ttif->udpport
      );
    }
    $cmd .= ' -c $userhome/vnlsvc.command -p $userhome/vnlsvc.pid';
    if ($tthost->isNG()) {
      $cmd = 'nohup '.$cmd.' >/dev/null 2>&1 &';
    } else {
      $cmd = $cmd.' -s';
    }
    printf("\t\t%s\n",$cmd);
?>
  fi
<?php
  }
?>
fi

if [ $act = 'setlossy' ]
then
  ifname=$2
  lossy=$3
  if [ $vnlsvcpid -eq 0 ]; then exit; fi
  kill -0 $vnlsvcpid
  if [ $? -eq 0 ]
  then
<?php
  foreach ($tt->hosts as $tthost) {
    $ifid = -1;
    foreach ($tthost->ifs as $ttif) {
      ++$ifid;
?>
    if [ $hostname = '<?php echo $tthost->hostname; ?>' -a $ifname = '<?php echo $ttif->vname; ?>' ]
    then
<?php
    printf("\t\t\tphp5 \$approot/setlossy.php \$userhome/vnlsvc.command %d \$lossy\n",$ifid);
    printf("\t\t\techo \$lossy > \$userhome/%d.lossy\n",$ifid);
?>
    fi
<?php
    }
  }
?>
  fi
fi

if [ $act = 'ping' -o $act = 'floodping' -o $act = 'traceroute' -o $act = 'sendtcp' -o $act = 'sendudp' ]
then
  dst=$2
  ifname=$3
<?php
  foreach ($tt->ng_hosts as $tthost) {
    $ifid = -1;
?>
    if [ $hostname = '<?php echo $tthost->hostname; ?>' ]
    then
<?php
    foreach ($tthost->ifs as $ttif) {
      $vip = $ti->resolve_vip($ttif->vip,TRUE);
      if (++$ifid == 0) {
        printf("\t\t\tsrc=%s\n",$vip);
      } else {
        printf("\t\t\tif [ \"\$ifname\" = '%s' ]; then src=%s; fi\n",$ttif->vname,$vip);
      }
    }
?>
    fi
<?php
  }
?>
  if [ "$src" = '' -o "$dst" = '' ]
  then
    echo source or destination address missing
    exit 1
  fi
  if [ $act = 'ping' ]
  then
    echo \# ping -n -w 60 -I $src $dst
    ping -n -w 60 -I $src $dst
  fi
  if [ $act = 'floodping' ]
  then
    echo \# ping -n -f -i 0.2 -w 10 -I $src $dst
    ping -n -f -i 0.2 -w 10 -I $src $dst
  fi
  if [ $act = 'traceroute' ]
  then
    echo \# traceroute -n -s $src --sport=33500 $dst
    traceroute -n -s $src --sport=33500 $dst
  fi
  srcport=$3
  if [ "$srcport" = '' ]; then srcport=16200; fi
  dstport=$4
  if [ "$dstport" = '' ]; then dstport=16200; fi
  if [ $act = 'sendtcp' ]
  then
    echo \# echo VNL-PACKET \| nc -w 1 -p $srcport -s $src $dst $dstport
    echo VNL-PACKET | nc -w 1 -p $srcport -s $src $dst $dstport
  fi
  if [ $act = 'sendudp' ]
  then
    echo \# traceroute -n -f 64 -m 64 -q 1 -s $src --sport=$srcport -p $dstport $dst
    traceroute -n -f 64 -m 64 -q 1 -s $src --sport=$srcport -p $dstport $dst
  fi
fi
