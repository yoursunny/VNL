<?php
# vagrant.php template_file gateway_ip
require_once 'tt.lib.php';

$tt = new TopoTpl($argv[1]);
?>
# -*- mode: ruby -*-
# vi: set ft=ruby :

gateway_ip = ''
raise 'missing gateway_ip' unless gateway_ip!=''

provision_script1 = <<EOT
groupadd vnl
useradd -g vnl -s /bin/bash vnlmaster
mkdir -p /home/vnlmaster
chown -R vnlmaster /home/vnlmaster
echo 'vnlmaster ALL=(ALL) NOPASSWD:ALL' > /etc/sudoers.d/vnlmaster
mkdir -p /home/vnl/apps /home/vnl/topo
chown -R vagrant /home/vnl

#apt-get update -qq
#apt-get dist-upgrade -y -qq
#apt-get install -y -qq php5-cli
EOT

start_script1 = <<EOT
echo 1 > /proc/sys/net/ipv4/ip_forward
ip link set dev eth1 mtu 2000
ip route add 239.207.49.0/24 dev eth1

if [ `hostname` = 'gateway' ]
then
  ip route add 10.192.144.0/26 via 192.168.56.1
  ip route add 10.192.144.64/26 via 192.168.56.1
  ip route add 192.12.69.0/24 via 192.168.56.1
fi

rsync --verbose --archive --delete -z --copy-links --include=*.sh --include=*.pubkey --include=*.pvtkey --exclude=* /vagrant/ /home/vnl/topo/
chown -R vnlmaster:vnl /home/vnl

cd /home/vnl/topo
for toposcript in *.sh
do
  bash $toposcript create
done

exit 0
EOT

Vagrant.configure(2) do |config|
  config.vm.box = 'ubuntu/trusty64'

  config.vm.provider 'virtualbox' do |vb|
    vb.memory = 1024
    vb.cpus = 4
  end

  config.vm.provision 'shell', inline: provision_script1
  config.vm.provision 'file', source: '../../guest-apps/vnlsvc/vnlsvc', destination: '/home/vnl/apps/vnlsvc'
  config.vm.provision 'file', source: '../../guest-apps/udpsum/udpsum', destination: '/home/vnl/apps/udpsum'
  config.vm.provision 'file', source: '../../guest-apps/setlossy.php', destination: '/home/vnl/apps/setlossy.php'
  config.vm.provision 'shell', run: 'always', inline: start_script1

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
    host.vm.network :private_network, ip: gateway_ip, virtualbox__intnet: false
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
