#!/bin/bash
R="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"

tt=$1
topoid=$2

cd deployments/$tt || exit 1

# make service script
php5 $R/scripts/gencmd.php $R/topo/$tt.xml $topoid > $topoid.sh
chmod +x $topoid.sh

# make key pair
if [ ! -f $topoid.pvtkey -o ! -f $topoid.pubkey ]
then
  if [ -f $topoid.pvtkey ]; then rm $topoid.pvtkey; fi
  if [ -f $topoid.pubkey ]; then rm $topoid.pubkey; fi
  ssh-keygen -f $topoid.pvtkey -N '' -C topo$topoid
  mv $topoid.pvtkey.pub $topoid.pubkey
fi

# make user package
if [ -d vnltopo$topoid ]; then rm -rf vnltopo$topoid; fi
mkdir vnltopo$topoid
cd vnltopo$topoid
php5 $R/scripts/ttlist.php sshconfig $R/topo/$tt.xml $topoid >vnltopo$topoid.sshconfig
cp ../$topoid.pvtkey ./vnltopo$topoid.pvtkey
php5 $R/scripts/ttlist.php connscript $R/topo/$tt.xml $topoid >vnltopo$topoid.sh
php5 $R/scripts/ttlist.php ip $R/topo/$tt.xml $topoid >vnltopo$topoid.iplist
for rtablefilename in `php5 $R/scripts/ttlist.php rtablelist $R/topo/$tt.xml`
do
  php5 $R/scripts/ttlist.php rtable $R/topo/$tt.xml $topoid $rtablefilename >$rtablefilename
done
tar cf ../$topoid.tar *
cd ..
rm -rf vnltopo$topoid
