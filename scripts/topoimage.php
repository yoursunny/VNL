<?php
# topoimage.php template_prefix topoid
require_once 'tt.lib.php';

$tt = new TopoTpl($argv[1].'.xml');
$topoid = intval($argv[2]);
$ti = new TopoInst($tt,$topoid);
$toponame = 'topo'.$topoid;

$g = imagecreatefrompng($argv[1].'.topoimage.png');
$color = imagecolorallocate($g,0x00,0x60,0x00);
imagestring($g,5,0,0,'VNL '.$toponame,$color);
imagestring($g,4,0,14,long2ip($ti->vip_base).'/'.$tt->vip_block_cidr,$color);

function drawifip($x,$y,$ifname,$vipoffset,$cidr=31) {
  global $g,$color,$ti;
  $vip = long2ip($ti->vip_base+$vipoffset).'/'.$cidr;
  if ($x<0) {
    $xifname = -$x -9*strlen($ifname);
    $xip = -$x -8*strlen($vip);
  } else {
    $xifname = $xip = $x;
  }
  if ($y<0) $y = -$y -28;
  imagestring($g,5,$xifname,$y,$ifname,$color);
  imagestring($g,4,$xip,$y+14,$vip,$color);
}
include $argv[1].'.topoimage.php';

imagepng($g);
imagedestroy($g);

?>
