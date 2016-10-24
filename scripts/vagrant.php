<?php
# vagrant.php template_file gateway_ip
require_once 'tt.lib.php';

$tt = new TopoTpl($argv[1]);
?>
# -*- mode: ruby -*-
# vi: set ft=ruby :

gateway_ip = '<?php echo $argv[2]; ?>'
raise 'missing gateway_ip' unless gateway_ip!=''

provision_script1 = <<EOT
groupadd vnl
useradd -g vnl -s /bin/bash vnlmaster
mkdir -p /home/vnlmaster
chown -R vnlmaster /home/vnlmaster
echo 'vnlmaster ALL=(ALL) NOPASSWD:ALL' > /etc/sudoers.d/vnlmaster
mkdir -p /home/vnl/topo
chown -R vagrant /home/vnl

apt-get update -qq
apt-get dist-upgrade -y -qq
apt-get install -y -qq php5-cli
EOT

provision_script2 = <<EOT
apt-get -y -qq install lighttpd php5-cgi vsftpd
lighty-enable-mod fastcgi fastcgi-php

mkdir /home/vnlappserver/

cd /home/vnlappserver/
cp -R /home/vnl/apps/www /home/vnl/apps/udpsum ./
mkdir -p ftproot

cp /home/vnl/apps/lighttpd.conf /etc/lighttpd/lighttpd.conf
cp /home/vnl/apps/vsftpd.conf /etc/vsftpd.conf

dd if=/dev/urandom of=www/1MB.bin bs=1M count=1
dd if=/dev/urandom of=www/2MB.bin bs=1M count=2
dd if=/dev/urandom of=www/4MB.bin bs=1M count=4
dd if=/dev/urandom of=www/8MB.bin bs=1M count=8
dd if=/dev/urandom of=www/16MB.bin bs=1M count=16
dd if=/dev/urandom of=www/32MB.bin bs=1M count=32
dd if=/dev/urandom of=www/64MB.bin bs=1M count=64

dd if=/dev/urandom of=ftproot/1MB.bin bs=1M count=1
dd if=/dev/urandom of=ftproot/2MB.bin bs=1M count=2
dd if=/dev/urandom of=ftproot/4MB.bin bs=1M count=4
dd if=/dev/urandom of=ftproot/8MB.bin bs=1M count=8
dd if=/dev/urandom of=ftproot/16MB.bin bs=1M count=16
dd if=/dev/urandom of=ftproot/32MB.bin bs=1M count=32
dd if=/dev/urandom of=ftproot/64MB.bin bs=1M count=64

chown -R vnlmaster:vnlmaster ./
chmod 644 www/* ftproot/*

cp /home/vnl/apps/udpsum.sh /etc/init.d/udpsum
update-rc.d udpsum defaults

service lighttpd restart
service vsftpd restart
service udpsum start
EOT

start_script1 = <<EOT
echo 1 > /proc/sys/net/ipv4/ip_forward
ip link set dev eth1 mtu 2000
ip route add 239.207.49.0/24 dev eth1

rsync --verbose --archive --delete -z --copy-links --include=*.sh --include=*.pubkey --include=*.pvtkey --exclude=* /vagrant/ /home/vnl/topo/
chown -R vnlmaster:vnl /home/vnl

cd /home/vnl/topo
for toposcript in *.sh
do
  bash $toposcript create
done

exit 0
EOT

# routes for Arizona Computer Science
start_script2 = <<EOT
ip route add 10.192.144.0/26 via 192.168.56.1
ip route add 10.192.144.64/26 via 192.168.56.1
ip route add 192.12.69.0/24 via 192.168.56.1
EOT

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
  foreach ($tthost->ifs as $i=>$ttif) {
    if ($i == 0) {
?>
    host.vm.network :private_network, ip: '<?php echo $ttif->tunnel_local; ?>', virtualbox__intnet: '<?php echo $tt->name; ?>'
<?php
    } else {
?>
    host.vm.provision 'shell', run: 'always', inline: 'ip addr add <?php echo $ttif->tunnel_local; ?>/24 dev eth1'
<?php
    }
  }
  if ($tthost->mode == TopoTplHost::GATEWAY) {
?>
    host.vm.network :private_network, ip: gateway_ip, virtualbox__intnet: false
<?php
  }
?>

    host.vm.provision 'shell', inline: provision_script1
    host.vm.provision 'file', source: '../../guest-apps/build/apps', destination: '/home/vnl/'
<?php
  if ($tthost->mode == TopoTplHost::NATIVE) {
?>
    host.vm.provision 'shell', inline: provision_script2
<?php
  }
?>
    host.vm.provision 'shell', run: 'always', inline: start_script1
<?php
  if ($tthost->mode == TopoTplHost::GATEWAY) {
?>
    host.vm.provision 'shell', run: 'always', inline: start_script2
<?php
  }
?>
  end
<?php
}
?>
end
