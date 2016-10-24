#!/bin/bash

cd guest-apps

cd vnlsvc
make
cd ..

cd udpsum
gcc -o udpsum --std=c99 udpsum.c
cd ..

mkdir -p www
cd www
if ! [[ -f index.php ]]; then
  git init
  git fetch --depth=1 https://github.com/yoursunny/VNL-www.git
  git checkout FETCH_HEAD
fi
cd ..

mkdir -p build build/www
cp vnlsvc/vnlsvc udpsum/udpsum setlossy.php build/
rsync --verbose --archive --delete -z --copy-links --exclude=.git www/ build/www
