# VNL deployment

Host machine:

1. It's RECOMMENDED to use Ubuntu 14.04 64-bit as host OS.
   Currently, guest-apps are compiled on host and executed on guest, and guests are Ubuntu 14.04 64-bit. Using a different host OS may cause ABI mismatch errors.
2. To determine the requirements on CPU and memory: look at all topology templates you want to deploy, and count how many virtual hosts they use.
   Each virtual host requires 1 CPU core, 1.2 GB memory, 5 GB disk space; more CPU cores are needed to support heavier traffic.
3. The following Ubuntu packages are REQUIRED: `git build-essential php5-cli`.
   This might be an incomplete list.
4. VirtualBox and Vagrant are REQUIRED.
   This software has been tested with VirtualBox 5.1 and Vagrant 1.8, but it's RECOMMENDED to use latest versions of same major version number (5.x and 1.x).

Initial steps:

1. Clone this repository.
2. cd into the repository.
3. To customize the contents of www site inside virtual topologies, clone your www repository into `guest-apps/www`. It must have an `index.php` file to be recognized.
   To use the default www contents, skip this step, and `scripts/build.sh` will clone <https://github.com/yoursunny/VNL-www>.
4. Build guest-apps: `bash scripts/build.sh`.

Topology template deployment:

1.  Choose a topology template from `topo` directory, and export as `$TOPO` variable.
    For example, `export TOPO=1r4s`.
2.  Allocate an IPv4 address in `192.168.56.0/24` subnet as the host-only interface IP on the gateway box, and export as `$GATEWAYIP` variable.
    This interface will communicate on host machine's `vboxnet0` interface whose IP address is `192.168.56.1/24`.
    For example, `export GATEWAYIP=192.168.56.3`.
3.  Create a directory for the deployment: `mkdir -p deployments/$TOPO`
4.  Write `Vagrantfile`: `php5 scripts/vagrant.php topo/$TOPO.xml $GATEWAYIP > deployments/$TOPO/Vagrantfile`
5.  Determine how many virtual topologies to be created. Typically you want to add the maximum number of virtual topologies, since the overhead of an idle virtual topology is small.
    The range of virtual topology ids can be found in `topo/$TOPO.xml` under `/topotemplate/range/topoid` key.
    For example: `export TOPOIDMIN=101; export TOPOIDMAX=163`.
6.  Create virtual topologies: `for TOPOID in $(seq $TOPOIDMIN $TOPOIDMAX); do bash scripts/addtopo.sh $TOPO $TOPOID; done`.
7.  `cd deployments/$TOPO`.
8.  Upgrade guest images: `vagrant box update`.
    You may see `Box not installed, can't check for updates.` warning message; this is harmless.
9.  Bring up the boxes: `vagrant up`.
    This may take up to 20 minutes.
    You may see warning messages such as `You assigned a static IP ending in ".1" to this machine.` and `stdin: is not a tty`; they are harmless.
10. Add a host route toward the gateway box. The destination subnet should cover all virtual IPs as seen in `topo/$TOPO.xml` file `/topotemplate/range/vip` element.
    For example, `sudo ip route add 172.29.4.0/22 via $GATEWAYIP`.
    This step is REQUIRED every time the host system reboots, and MUST be performed after `vagrant up` has completed.
11. Enable IPv4 routing on host: `echo 1 | sudo tee /proc/sys/net/ipv4/ip_forward >/dev/null`.
    This step is REQUIRED every time the host system reboots, and MUST be performed after `vagrant up` has completed.

Maintenance:

* Backup: backup files from `deployments/$TOPO` directory.
* Restore: restore files to `deployments/$TOPO` directory.
  If you have modified anything in `topo/$TOPO.*`, the backup is no longer valid.
* Restart boxes: `vagrant reload`.
  You may see error messages such as `useradd: user 'topo101' already exists` and `cannot create fifo /home/topo101/vnlsvc.command: File exists`; these are caused by the deployment script attempting to add existing virtual topologies again, and they are harmless.
* Start boxes: `vagrant up`.
* Stop boxes: `vagrant halt`.
  This will shutdown virtual machines, so that you can reboot/upgrade host system.
* Destroy boxes: `vagrant destroy -f`.
  As long as the files in `deployments/$TOPO` are kept, you may destroy the boxes and start them again without losing any virtual topologies.
* Guest system upgrades: destroy boxes, execute `vagrant box update`, and start boxes again.
* Host system upgrades: stop boxes, update and reboot host system, execute "add host route" and "enable IPv4 routing on host" steps, and start boxes again.
