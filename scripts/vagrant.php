<?php
# vagrant.php template_file gateway_ip
require_once 'tt.lib.php';

$tt = new TopoTpl($argv[1]);
$gateway_ip = $argv[2];
if (FALSE === ip2long($gateway_ip)) {
  die('vagrant.php template_file gateway_ip');
}
?>
# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure(2) do |config|
  config.vm.box = 'ubuntu/trusty64'

  config.vm.provider 'virtualbox' do |vb|
    vb.memory = 1024
    vb.cpus = 4
  end
<?php
foreach ($tt->hosts as $tthost) {
  list($sshhost,$sshport) = explode(':',$tthost->sshserver);
?>

  config.vm.define '<?php echo $tthost->hostname; ?>' do |host|
    host.vm.hostname = '<?php echo $tthost->hostname; ?>'
    host.vm.network :forwarded_port, guest: 22, host: <?php echo $sshport; ?>, id: 'ssh'
<?php
  if ($tthost->mode == TopoTplHost::GATEWAY) {
?>
    host.vm.network :private_network, ip: '<?php echo $gateway_ip ?>', virtualbox__intnet: false
<?php
  }
?>
<?php
  foreach ($tthost->ifs as $ttif) {
?>
    host.vm.network :private_network, ip: '<?php echo $ttif->tunnel_local; ?>', virtualbox__intnet: true
<?php
  }
?>
  end
<?php
}
?>
end
