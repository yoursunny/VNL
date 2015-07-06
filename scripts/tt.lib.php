<?php
class TopoTplIf {
  public $vname;
  public $vip;
  public $tunnel_local;
  public $tunnel_remote;
  public $udpport;

  function __construct($xif) {
    $this->vname = strval($xif['vname']);
    $this->vip = strval($xif['vip']);
    $this->tunnel_local = strval($xif['tip']);
    $this->tunnel_remote = strval($xif['rtip']);
    $this->udpport = isset($xif['udpport']) ? intval($xif['udpport']) : 0;
  }
}

class TopoTplRoute {
  public $is_source_route;
  public $dst;
  public $src;
  public $rtable;
  public $via;
  public $oif;

  function __construct($xroute) {
    if ($this->is_source_route = isset($xroute['rtable'])) {
      $this->dst = '+0.0.0.0/0';
      $this->src = strval($xroute['src']);
      $this->rtable = intval($xroute['rtable']);
    } else {
      $this->dst = strval($xroute['dst']);
      $this->src = '+0.0.0.0/0';
    }
    if ($this->dst == 'default') $this->dst = '+0.0.0.0/0';
    $this->via = strval($xroute['via']);
    if (isset($xroute['oif'])) {
      $this->oif = strval($xroute['oif']);
    }
  }
}

class TopoTplHost {
  const SPLIT = 'split';
  const NATIVE = 'native';
  const GATEWAY = 'gateway';

  public $mode;
  public $vname;
  public $hostname;
  public $sshserver;
  public $ifs;
  public $routes;

  function __construct($xhost) {
    $this->mode = strval($xhost['mode']);
    $this->vname = strval($xhost['vname']);
    $this->hostname = strval($xhost['hostname']);
    $this->sshserver = strval($xhost['sshserver']);

    $this->ifs = array();
    foreach ($xhost->if as $xif) {
      $this->ifs[] = new TopoTplIf($xif);
    }

    $this->routes = array();
    foreach ($xhost->route as $xroute) {
      $this->routes[] = new TopoTplRoute($xroute);
    }
  }

  function isNG() {
    return $this->mode == TopoTplHost::NATIVE || $this->mode == TopoTplHost::GATEWAY;
  }
}

class TopoTplVnsRtable {
  public $filename;
  public $routes;
  function __construct($xvnsrtable) {
    $this->filename = strval($xvnsrtable['filename']);

    $this->routes = array();
    foreach ($xvnsrtable->route as $xroute) {
      $this->routes[] = new TopoTplRoute($xroute);
    }
  }
}

class TopoInst {
  public $tt;
  public $topoid;
  public $vip_base;
  public $udpport_base;
  public $rtable_base;
  function __construct($tt,$topoid) {
    if ($topoid<$tt->topoid_min || $tt->topoid_max<$topoid) die("topoid out of range\n");
    $this->tt = $tt;
    $this->topoid = $topoid;
    $this->vip_base = $tt->vip_zero + $tt->vip_block_size*$topoid;
    $this->udpport_base = $tt->udpport_zero + $tt->udpport_step*$topoid;
    $this->rtable_base = $tt->rtable_zero + $tt->rtable_step*$topoid;
  }

  public function resolve_vip($vip,$iponly=FALSE) {
    if ($vip=='none') $vip = '+0.0.0.0/32';
    $ipbase = $this->vip_base;
    if ($vip[0]=='+') {
      $ipbase = 0;
      $vip = substr($vip,1);
    }
    if (strpos($vip,'/')===FALSE) {
      $ip = $vip;
      $cidr = 32;
    } else {
      list($ip,$cidr) = explode('/',$vip);
      $cidr = intval($cidr);
    }
    $ip = long2ip($ipbase + ip2long($ip));
    if ($iponly) return $ip;
    else {
      $mask = long2ip((0xFFFFFFFF << (32-$cidr)) & 0xFFFFFFFF);
      return array($ip,$cidr,$mask);
    }
  }
  public function random_mac() {
    $mac = substr(md5(uniqid('',TRUE)),0,12);
    // ensure MAC is unicast address
    $mac = $mac[0].'2:'.$mac[2].$mac[3].':'.$mac[4].$mac[5].':'.$mac[6].$mac[7].':'.$mac[8].$mac[9].':'.$mac[10].$mac[11];
    return $mac;
  }
}

class TopoTpl {
  public $name;
  public $title;
  public $topoid_min;
  public $topoid_max;
  public $vip_zero;
  public $udpport_zero;
  public $udpport_step;
  public $rtable_zero;
  public $rtable_step;
  public $vip_block_cidr;
  public $vip_block_size;
  public $hosts;
  public $split_hosts;
  public $native_hosts;
  public $gateway_hosts;
  public $ng_hosts;
  public $vnsrtables;
  function __construct($filename) {
    $xtt = simplexml_load_file($filename);
    if (!$xtt) die("cannot load topology template\n");
    $this->name = strval($xtt['name']);
    $this->title = strval($xtt->title);

    $this->topoid_min = intval($xtt->range->topoid['min']);
    $this->topoid_max = intval($xtt->range->topoid['max']);
    $vip_min = ip2long($xtt->range->vip['min']);
    $vip_max = ip2long($xtt->range->vip['max']);
    $this->vip_block_cidr = intval(substr($xtt->vip['block'],1));
    $this->vip_block_size = 1 << (32-$this->vip_block_cidr);
    $udpport_min = intval($xtt->range->udpport['min']);
    $udpport_max = intval($xtt->range->udpport['max']);
    $this->udpport_step = isset($xtt->range->udpport['step']) ? intval($xtt->range->udpport['step']) : 1;
    $rtable_min = intval($xtt->range->rtable['min']);
    $rtable_max = intval($xtt->range->rtable['max']);
    $this->rtable_step = intval($xtt->range->rtable['step']);
    $this->vip_zero = $vip_min - $this->vip_block_size*$this->topoid_min;
    $this->udpport_zero = $udpport_min - $this->udpport_step*$this->topoid_min;
    $this->rtable_zero = $rtable_min - $this->rtable_step*$this->topoid_min;
    if ($this->vip_zero + $this->vip_block_size*($this->topoid_max+1)-1 > $vip_max) die("vip out of range\n");
    if ($this->udpport_zero + $this->udpport_step*$this->topoid_max > $udpport_max) die("udpport out of range\n");
    if ($this->rtable_zero + $this->rtable_step*($this->topoid_max+1)-1 > $rtable_max) die("rtable out of range\n");

    $this->hosts = array();
    foreach ($xtt->host as $xhost) {
      $this->hosts[] = $tthost = new TopoTplHost($xhost);
      if ($tthost->mode == TopoTplHost::SPLIT) $this->split_hosts[] = $tthost;
      if ($tthost->mode == TopoTplHost::NATIVE) {
        $this->native_hosts[] = $tthost;
        $this->ng_hosts[] = $tthost;
      }
      if ($tthost->mode == TopoTplHost::GATEWAY) {
        $this->gateway_hosts[] = $tthost;
        $this->ng_hosts[] = $tthost;
      }
    }

    $this->vnsrtables = array();
    foreach ($xtt->vnsrtable as $xvnsrtable) {
      $ttvrt = new TopoTplVnsRtable($xvnsrtable);
      $this->vnsrtables[$ttvrt->filename] = $ttvrt;
    }
  }
}

?>
